<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.html");
    exit();
}

include 'db.php';
date_default_timezone_set('Asia/Kolkata'); // India time
$user_id = intval($_SESSION['user_id']);

// --- 1. Fetch all sales for this user ---
$sales = [];
$res = $conn->prepare("
    SELECT s.id, s.total, s.tax_total, s.grand_total, s.created_at,
           GROUP_CONCAT(CONCAT(p.name,' x',si.qty) SEPARATOR ', ') AS items_sold
    FROM sales s
    LEFT JOIN sale_items si ON s.id = si.sale_id
    LEFT JOIN products p ON si.product_id = p.id
    WHERE s.user_id=?
    GROUP BY s.id
    ORDER BY s.created_at DESC
");
$res->bind_param("i",$user_id);
$res->execute();
$result = $res->get_result();
while($row = $result->fetch_assoc()) {
    $sales[] = $row;
}
$res->close();

// --- 2. Calculate summary metrics ---

// Total revenue (excluding tax)
$total_revenue = 0;
$total_profit = 0;
$total_tax = 0;
$total_items = 0;
$top_product = [];
foreach ($sales as $s) {
    $total_revenue += $s['total'];
    $total_tax += $s['tax_total'];
    $total_profit += ($s['total'] - $s['tax_total']); // Profit excluding tax
    // Parse items_sold for total items and top product
    if (!empty($s['items_sold'])) {
        $items = explode(',', $s['items_sold']);
        foreach($items as $it) {
            preg_match('/(.+) x(\d+)/',$it,$m);
            if(isset($m[1],$m[2])){
                $name = trim($m[1]);
                $qty = intval($m[2]);
                $total_items += $qty;
                if(isset($top_product[$name])) $top_product[$name] += $qty;
                else $top_product[$name] = $qty;
            }
        }
    }
}

// Top product
$top_product_name = 'N/A';
$top_product_qty = 0;
foreach($top_product as $k=>$v){
    if($v > $top_product_qty){
        $top_product_name = $k;
        $top_product_qty = $v;
    }
}

// --- 3. Aggregate sales by time period ---

// Daily
$stmt = $conn->prepare("
    SELECT COALESCE(SUM(grand_total),0) 
    FROM sales 
    WHERE user_id=? AND DATE(created_at)=CURDATE()
");
$stmt->bind_param("i",$user_id);
$stmt->execute();
$stmt->bind_result($daily_sales);
$stmt->fetch();
$stmt->close();

// Weekly
$stmt = $conn->prepare("
    SELECT COALESCE(SUM(grand_total),0) 
    FROM sales 
    WHERE user_id=? AND YEARWEEK(created_at,1)=YEARWEEK(CURDATE(),1)
");
$stmt->bind_param("i",$user_id);
$stmt->execute();
$stmt->bind_result($weekly_sales);
$stmt->fetch();
$stmt->close();

// Monthly
$stmt = $conn->prepare("
    SELECT COALESCE(SUM(grand_total),0) 
    FROM sales 
    WHERE user_id=? AND MONTH(created_at)=MONTH(CURDATE()) AND YEAR(created_at)=YEAR(CURDATE())
");
$stmt->bind_param("i",$user_id);
$stmt->execute();
$stmt->bind_result($monthly_sales);
$stmt->fetch();
$stmt->close();

// Yearly
$stmt = $conn->prepare("
    SELECT COALESCE(SUM(grand_total),0) 
    FROM sales 
    WHERE user_id=? AND YEAR(created_at)=YEAR(CURDATE())
");
$stmt->bind_param("i",$user_id);
$stmt->execute();
$stmt->bind_result($yearly_sales);
$stmt->fetch();
$stmt->close();

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8" />
<title>Sales Records</title>
<meta name="viewport" content="width=device-width,initial-scale=1" />
<style>
:root { --primary:#6C5B7B; --secondary:#C06C84; --accent:#F67280; --bg:#F8B195; --card:#FFF; }
body{font-family:Inter,Segoe UI,Arial; margin:0;padding:20px; background:var(--bg);}
h1,h2,h3{margin:0 0 10px 0;}
header{display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;}
header a{color:#fff; background:var(--primary); padding:6px 12px; border-radius:8px; text-decoration:none;}
.panel{background:var(--card); padding:16px; border-radius:12px; margin-bottom:20px; box-shadow:0 4px 12px rgba(0,0,0,0.1);}
table{width:100%;border-collapse:collapse;margin-top:12px;}
th,td{padding:10px;border:1px solid #eee;text-align:left;}
th{background:var(--secondary); color:#fff;}
td img{width:50px; height:50px; object-fit:cover; border-radius:6px;}
.summary{display:flex; flex-wrap:wrap; gap:12px;}
.summary div{flex:1; background:#fff; padding:14px; border-radius:10px; text-align:center; box-shadow:0 4px 10px rgba(0,0,0,0.08);}
</style>
</head>
<body>

<header>
<h1>Sales Records</h1>
<a href="dashboard.php">Dashboard</a>
</header>

<div class="summary">
    <div><strong>Total Revenue:</strong> ₹<?php echo number_format($total_revenue,2); ?></div>
    <div><strong>Total Profit:</strong> ₹<?php echo number_format($total_profit,2); ?></div>
    <div><strong>Total Tax:</strong> ₹<?php echo number_format($total_tax,2); ?></div>
    <div><strong>Total Items Sold:</strong> <?php echo $total_items; ?></div>
    <div><strong>Top Product:</strong> <?php echo htmlspecialchars($top_product_name); ?> (<?php echo $top_product_qty; ?>)</div>
    <div><strong>Daily Sales:</strong> ₹<?php echo number_format($daily_sales,2); ?></div>
    <div><strong>Weekly Sales:</strong> ₹<?php echo number_format($weekly_sales,2); ?></div>
    <div><strong>Monthly Sales:</strong> ₹<?php echo number_format($monthly_sales,2); ?></div>
    <div><strong>Yearly Sales:</strong> ₹<?php echo number_format($yearly_sales,2); ?></div>
</div>

<div class="panel">
<h2>All Sales</h2>
<table>
<thead>
<tr>
<th>#</th>
<th>Items Sold</th>
<th>Total Amount (₹)</th>
<th>Tax (₹)</th>
<th>Grand Total (₹)</th>
<th>Date & Time</th>
</tr>
</thead>
<tbody>
<?php if(count($sales)==0){ ?>
<tr><td colspan="6" style="text-align:center;color:#666;">No sales yet.</td></tr>
<?php }else{ 
foreach($sales as $s){ ?>
<tr>
<td><?php echo intval($s['id']); ?></td>
<td><?php echo htmlspecialchars($s['items_sold'] ?? ''); ?></td>
<td>₹<?php echo number_format($s['total'],2); ?></td>
<td>₹<?php echo number_format($s['tax_total'],2); ?></td>
<td>₹<?php echo number_format($s['grand_total'],2); ?></td>
<td><?php echo $s['created_at']; ?></td>
</tr>
<?php } } ?>
</tbody>
</table>
</div>

</body>
</html>
