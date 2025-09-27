<?php
session_start();
header('Content-Type: application/json');
include 'db.php';
if (!isset($_SESSION['user_id'])) { echo json_encode([]); exit; }
$user_id = intval($_SESSION['user_id']);

$stmt = $conn->prepare("SELECT id, name, description, price, tax_percent, stock, image FROM products WHERE user_id = ? ORDER BY name");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$res = $stmt->get_result();
$rows = [];
while ($r = $res->fetch_assoc()) {
    $r['price'] = (float)$r['price'];
    $r['tax_percent'] = (float)$r['tax_percent'];
    $r['stock'] = (int)$r['stock'];
    // image already saved as uploads/<user_id>/<file>
    $rows[] = $r;
}
echo json_encode($rows);
?>
