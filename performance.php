<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    // try to be forgiving: if username exists but user_id missing, attempt to fetch it
    if (isset($_SESSION['username'])) {
        include 'db.php';
        $stmt = $conn->prepare("SELECT id FROM user_pos WHERE username = ? LIMIT 1");
        $stmt->bind_param("s", $_SESSION['username']);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($row = $res->fetch_assoc()) {
            $_SESSION['user_id'] = intval($row['id']);
        }
        $stmt->close();
    }
}
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

include 'db.php';
$user_id = intval($_SESSION['user_id']);

// initialize defaults
$total_items = 0;
$total_revenue = 0.0;
$total_tax = 0.0;
$total_cost = 0.0;
$total_profit = 0.0;
$total_sales = 0;
$highest_sale = 0.0;
$avg_sale = 0.0;
$top_product = 'N/A';
$top_qty = 0;

// helper to run 1-col SELECT COALESCE(...) queries
function one_col_query($conn, $sql, $user_id) {
    $val = 0;
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->bind_result($res);
        if ($stmt->fetch()) {
            $val = $res;
        }
        $stmt->close();
    }
    return $val;
}

// 1) Total items sold (sum qty in sale_items)
$sql = "SELECT COALESCE(SUM(qty), 0) FROM sale_items WHERE user_id = ?";
$total_items = (int) one_col_query($conn, $sql, $user_id);

// 2) Total tax collected (sum tax_amount from sale_items)
$sql = "SELECT COALESCE(SUM(tax_amount), 0) FROM sale_items WHERE user_id = ?";
$total_tax = (float) one_col_query($conn, $sql, $user_id);

// 3) Total revenue = sum of (price*qty + tax_amount)
$sql = "SELECT COALESCE(SUM(price*qty + tax_amount), 0) FROM sale_items WHERE user_id = ?";
$total_revenue = (float) one_col_query($conn, $sql, $user_id);

// 4) Total cost (sum cost_price * qty by joining products)
$sql = "SELECT COALESCE(SUM(p.cost_price * si.qty), 0)
        FROM sale_items si
        JOIN products p ON si.product_id = p.id
        WHERE si.user_id = ?";
$total_cost = (float) one_col_query($conn, $sql, $user_id);

// 5) Total profit = revenue - cost
$total_profit = $total_revenue - $total_cost - $total_tax;

// 6) Total sales count (number of sale headers)
$sql = "SELECT COALESCE(COUNT(*), 0) FROM sales WHERE user_id = ?";
$total_sales = (int) one_col_query($conn, $sql, $user_id);

// 7) Highest ever sale (max grand_total)
$sql = "SELECT COALESCE(MAX(grand_total), 0) FROM sales WHERE user_id = ?";
$highest_sale = (float) one_col_query($conn, $sql, $user_id);

// 8) Average sale value (avg grand_total)
$sql = "SELECT COALESCE(AVG(grand_total), 0) FROM sales WHERE user_id = ?";
$avg_sale = (float) one_col_query($conn, $sql, $user_id);

// 9) Top performing product (by quantity sold)
if ($stmt = $conn->prepare("
    SELECT p.name, COALESCE(SUM(si.qty),0) AS total_qty
    FROM sale_items si
    JOIN products p ON si.product_id = p.id
    WHERE si.user_id = ?
    GROUP BY si.product_id
    ORDER BY total_qty DESC
    LIMIT 1
")) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->bind_result($tp_name, $tp_qty);
    if ($stmt->fetch()) {
        $top_product = $tp_name ?: 'N/A';
        $top_qty = (int)$tp_qty;
    }
    $stmt->close();
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8" />
<title>Performance Dashboard</title>
<meta name="viewport" content="width=device-width,initial-scale=1" />
<style>
    :root{
      --bg:#f7f8fb;
      --card-bg:#ffffff;
      --muted:#6b7280;
    }
    *{box-sizing:border-box}
    body{margin:0;font-family:Inter,Segoe UI,Arial;background:var(--bg);color:#222}
    header{background:#7c5cff;color:#fff;padding:18px 22px;text-align:center;font-size:20px;font-weight:700}
    nav{background:#efe9ff;padding:10px 22px;display:flex;gap:12px;align-items:center}
    nav a{color:#5b4bd8;text-decoration:none;font-weight:600}
    .wrap{padding:22px}
    .grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:18px}
    .card{
      background:var(--card-bg);
      border-radius:12px;
      padding:18px;
      box-shadow:0 8px 22px rgba(16,24,40,0.06);
      transition:transform .15s ease;
      text-align:center;
    }
    .card:hover{transform:translateY(-6px)}
    .k{font-size:13px;color:var(--muted);margin-bottom:8px}
    .v{font-size:22px;font-weight:800;color:#111}
    .v.small{font-size:16px;font-weight:700}
    .positive{color:#00b894}
    .negative{color:#d63031}
    .row{display:flex;gap:14px;flex-wrap:wrap}
    @media (max-width:520px){ header{font-size:18px} }
    /* pastel accents for visual variety */
    .card:nth-child(1){background:linear-gradient(135deg,#fff7d6,#fff3c4)}
    .card:nth-child(2){background:linear-gradient(135deg,#ffdfe6,#ffecf3)}
    .card:nth-child(3){background:linear-gradient(135deg,#e6fff4,#dffbf0)}
    .card:nth-child(4){background:linear-gradient(135deg,#e8f8ff,#e3f3ff)}
    .card:nth-child(5){background:linear-gradient(135deg,#e9ecff,#eef0ff)}
    .card:nth-child(6){background:linear-gradient(135deg,#fbe6ff,#f6e8ff)}
    .card:nth-child(7){background:linear-gradient(135deg,#fff0f0,#fff6f6)}
    .card:nth-child(8){background:linear-gradient(135deg,#e8fffb,#e8fff6)}
</style>
</head>
<body>
<header>📊 Performance Dashboard</header>
<nav>
  <a href="dashboard.php">Dashboard</a>
  <a href="inventory.php">Inventory</a>
  <a href="performance.php">Performance</a>
  <a href="record_sale.php">Records</a>
</nav>

<div class="wrap">
  <div style="margin-bottom:14px;color:var(--muted)">Showing all-time performance for <strong><?php echo htmlspecialchars($_SESSION['username'] ?? 'You'); ?></strong></div>

  <div class="grid">
    <div class="card">
      <div class="k">Total Sales (transactions)</div>
      <div class="v"><?php echo number_format($total_sales); ?></div>
    </div>

    <div class="card">
      <div class="k">Total Items Sold</div>
      <div class="v"><?php echo number_format($total_items); ?></div>
    </div>

    <div class="card">
      <div class="k">Total Revenue (₹)</div>
      <div class="v">₹<?php echo number_format($total_revenue,2); ?></div>
    </div>

    <div class="card">
      <div class="k">Total Tax Collected (₹)</div>
      <div class="v">₹<?php echo number_format($total_tax,2); ?></div>
    </div>

    <div class="card">
      <div class="k">Total Cost (₹)</div>
      <div class="v">₹<?php echo number_format($total_cost,2); ?></div>
    </div>

    <div class="card">
      <div class="k">Total Profit (₹)</div>
      <div class="v <?php echo $total_profit>=0 ? 'positive' : 'negative'; ?>">₹<?php echo number_format($total_profit,2); ?></div>
    </div>

    <div class="card">
      <div class="k">Average Sale Value (₹)</div>
      <div class="v">₹<?php echo number_format($avg_sale,2); ?></div>
    </div>

    <div class="card">
      <div class="k">Highest Single Sale (₹)</div>
      <div class="v">₹<?php echo number_format($highest_sale,2); ?></div>
    </div>

    <div class="card">
      <div class="k">Top Product</div>
      <div class="v small"><?php echo htmlspecialchars($top_product); ?></div>
      <div style="margin-top:8px;color:var(--muted)"><?php echo number_format($top_qty); ?> units sold</div>
    </div>
  </div>
</div>
</body>
</html>
