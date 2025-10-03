<?php
include 'db.php';

$sql = "UPDATE sale_items si
JOIN sales s ON si.sale_id = s.id
SET si.user_id = s.user_id
WHERE si.user_id IS NULL OR si.user_id = 0";

if ($conn->query($sql)) {
    echo "✅ sale_items.user_id fixed successfully!";
} else {
    echo "❌ Error: " . $conn->error;
}
?>
