<?php
session_start();
header('Content-Type: application/json');
include 'db.php';

// require login
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success'=>false,'error'=>'Not authenticated']);
    exit;
}

$user_id = intval($_SESSION['user_id']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success'=>false, 'error'=>'Method not allowed']);
    exit;
}

$items_json = $_POST['items'] ?? '';
$total = floatval($_POST['total'] ?? 0);
$tax_total = floatval($_POST['tax_total'] ?? 0);
$grand_total = floatval($_POST['grand_total'] ?? 0);
$items = json_decode($items_json, true);

if (!is_array($items) || count($items) == 0) {
    echo json_encode(['success'=>false,'error'=>'Cart empty']);
    exit;
}

mysqli_begin_transaction($conn);

// ... [Start of add_sale.php code] ...

mysqli_begin_transaction($conn);

try {
    // 1. INSERT SALE HEADER (temporarily without the user_bill_number)
    $stmt = $conn->prepare("INSERT INTO sales (user_id, total, tax_total, grand_total) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("iddd", $user_id, $total, $tax_total, $grand_total);
    if (!$stmt->execute()) throw new Exception('Failed insert sale: ' . $stmt->error);
    $sale_id = $stmt->insert_id; // Store the GLOBAL ID for safety/rollback
    $stmt->close();

    // 2. CALCULATE NEXT USER BILL NUMBER
    // Get the maximum user_bill_number used by this user so far
    $max_stmt = $conn->prepare("SELECT MAX(user_bill_number) AS max_num FROM sales WHERE user_id = ?");
    $max_stmt->bind_param("i", $user_id);
    $max_stmt->execute();
    $max_result = $max_stmt->get_result();
    $max_row = $max_result->fetch_assoc();
    $max_stmt->close();
    
    // Calculate the new bill number (if max_num is NULL, start at 1)
    $user_bill_number = intval($max_row['max_num']) + 1;

    // 3. UPDATE THE SALE RECORD with the new user_bill_number
    $update_stmt = $conn->prepare("UPDATE sales SET user_bill_number = ? WHERE id = ?");
    $update_stmt->bind_param("ii", $user_bill_number, $sale_id);
    if (!$update_stmt->execute()) throw new Exception('Failed to update bill number: ' . $update_stmt->error);
    $update_stmt->close();


    // 4. PROCESS SALE ITEMS AND STOCK UPDATES (Keep your existing stock logic)
    foreach ($items as $it) {
        $pid = intval($it['id']);
        $qty = intval($it['qty']);
        $price = floatval($it['price']);
        $tax_amt = floatval($it['tax_amt']);

        // lock product row and ensure it belongs to this user
        $sel = $conn->prepare("SELECT stock, user_id FROM products WHERE id = ? FOR UPDATE");
        $sel->bind_param("i", $pid);
        $sel->execute();
        $res = $sel->get_result();
        if ($res->num_rows == 0) throw new Exception('Product not found: ' . $pid);
        $row = $res->fetch_assoc();
        $sel->close();

        if (intval($row['user_id']) !== $user_id) throw new Exception('Unauthorized product access: ' . $pid);
        $current_stock = intval($row['stock']);
        if ($qty > $current_stock) throw new Exception('Not enough stock for product id ' . $pid);

        // insert sale item WITH user_id
        $stmt2 = $conn->prepare("INSERT INTO sale_items (sale_id, product_id, user_id, qty, price, tax_amount) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt2->bind_param("iiiidd", $sale_id, $pid, $user_id, $qty, $price, $tax_amt);
        if (!$stmt2->execute()) throw new Exception('Failed to insert sale item: ' . $stmt2->error);
        $stmt2->close();

        // update stock
        $new_stock = $current_stock - $qty;
        $up = $conn->prepare("UPDATE products SET stock = ? WHERE id = ? AND user_id = ?");
        $up->bind_param("iii", $new_stock, $pid, $user_id);
        if (!$up->execute()) throw new Exception('Failed to update stock: ' . $up->error);
        $up->close();
    }

    // 5. COMMIT AND RETURN THE USER-SPECIFIC BILL NUMBER
    mysqli_commit($conn);
    // Return the user-specific bill number
    echo json_encode(['success'=>true,'sale_id'=>$user_bill_number]); 
    
} catch (Exception $e) {
    mysqli_rollback($conn);
    echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}
// ... [End of add_sale.php code] ...
?>

