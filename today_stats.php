<?php
session_start();
header('Content-Type: application/json');

// 1. Authentication Check
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

include 'db.php';
date_default_timezone_set('Asia/Kolkata');
$user_id = intval($_SESSION['user_id']);

// 2. Database Query (Same as the original dashboard.php query)
$today_sales = [];
$stmt = $conn->prepare("
    SELECT 
        s.total, s.tax_total, s.grand_total,
        SUM(si.qty * p.cost_price) AS cost_total, 
        GROUP_CONCAT(CONCAT(p.name,' x',si.qty) SEPARATOR ', ') AS items_sold
    FROM sales s
    LEFT JOIN sale_items si ON s.id = si.sale_id
    LEFT JOIN products p ON si.product_id = p.id
    WHERE s.user_id=? AND DATE(s.created_at)=CURDATE()
    GROUP BY s.id
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $today_sales[] = $row;
}
$stmt->close();
$conn->close();

// 3. Calculate Metrics
$today_total_sales = count($today_sales);
$today_revenue = 0;
$today_profit = 0;
$today_items = 0;

foreach ($today_sales as $s) {
    $today_revenue += $s['grand_total']; 
    // Profit = Grand Total - Tax Total - Cost Total
    $today_profit += ($s['grand_total'] - $s['tax_total'] - $s['cost_total']); 

    if (!empty($s['items_sold'])) {
        $items = explode(',', $s['items_sold']);
        foreach ($items as $it) {
            preg_match('/(.+) x(\d+)/', $it, $m);
            if (isset($m[2])) {
                $today_items += intval($m[2]);
            }
        }
    }
}

// 4. Output JSON
echo json_encode([
    'total_sales' => $today_total_sales,
    'revenue' => round($today_revenue, 2),
    'profit' => round($today_profit, 2),
    'items_sold' => $today_items
]);
?>