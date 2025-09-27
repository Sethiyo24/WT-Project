<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
include 'db.php';

$user_id = intval($_SESSION['user_id']);

// sanitize
function s($v){ return trim($v); }

$message = '';

// image upload helper (stores in uploads/<user_id>/)
function handleImageUpload($file_field, $old_path = null, $user_id=0) {
    if (!isset($_FILES[$file_field]) || $_FILES[$file_field]['error'] == UPLOAD_ERR_NO_FILE) {
        return $old_path;
    }
    $file = $_FILES[$file_field];
    if ($file['error'] !== UPLOAD_ERR_OK) return $old_path;

    $allowed = ['jpg','jpeg','png','gif','webp'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed)) return $old_path;

    $uploadsDirRoot = __DIR__ . '/uploads';
    if (!is_dir($uploadsDirRoot)) mkdir($uploadsDirRoot, 0777, true);

    $userDir = $uploadsDirRoot . '/' . $user_id;
    if (!is_dir($userDir)) mkdir($userDir, 0777, true);

    $newName = time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $target = $userDir . '/' . $newName;
    if (move_uploaded_file($file['tmp_name'], $target)) {
        if ($old_path && file_exists(__DIR__ . '/' . $old_path) && strpos($old_path, 'uploads/') === 0) {
            @unlink(__DIR__ . '/' . $old_path);
        }
        return 'uploads/' . $user_id . '/' . $newName;
    }
    return $old_path;
}

// Handle form submit (add or update)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $name = s($_POST['name'] ?? '');
    $description = s($_POST['description'] ?? '');
    $price = floatval($_POST['price'] ?? 0);
    $cost_price = floatval($_POST['cost_price'] ?? 0);
    $tax_percent = floatval($_POST['tax_percent'] ?? 0);
    $stock = intval($_POST['stock'] ?? 0);

    if ($action === 'add') {
        if ($name === '' || $price < 0) {
            $message = "Please provide valid name and price.";
        } else {
            $image_path = handleImageUpload('image', null, $user_id);
            $sql = "INSERT INTO products (user_id, name, description, price, cost_price, tax_percent, stock, image)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("issdddis", $user_id, $name, $description, $price, $cost_price, $tax_percent, $stock, $image_path);
            if ($stmt->execute()) $message = "Product added.";
            else $message = "Error: " . $stmt->error;
            $stmt->close();
        }
    } elseif ($action === 'update' && isset($_POST['prod_id'])) {
        $prod_id = intval($_POST['prod_id']);
        $stmt0 = $conn->prepare("SELECT image FROM products WHERE id=? AND user_id=?");
        $stmt0->bind_param("ii", $prod_id, $user_id);
        $stmt0->execute();
        $r0 = $stmt0->get_result();
        if ($r0->num_rows === 0) {
            $message = "Product not found or unauthorized.";
            $stmt0->close();
        } else {
            $row0 = $r0->fetch_assoc();
            $old_image = $row0['image'];
            $stmt0->close();
            $image_path = handleImageUpload('image', $old_image, $user_id);
            $sql = "UPDATE products SET name=?, description=?, price=?, cost_price=?, tax_percent=?, stock=?, image=? WHERE id=? AND user_id=?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssdddisii", $name, $description, $price, $cost_price, $tax_percent, $stock, $image_path, $prod_id, $user_id);
            if ($stmt->execute()) $message = "Product updated.";
            else $message = "Error: " . $stmt->error;
            $stmt->close();
        }
    }
}

// Handle delete
if (isset($_GET['delete'])) {
    $del_id = intval($_GET['delete']);

    $check = $conn->prepare("
      SELECT COUNT(si.id) AS cnt
      FROM sale_items si
      JOIN sales s ON si.sale_id = s.id
      WHERE si.product_id = ? AND s.user_id = ?
    ");
    $check->bind_param("ii", $del_id, $user_id);
    $check->execute();
    $cres = $check->get_result()->fetch_assoc();
    $check->close();

    if ($cres['cnt'] > 0) {
        $message = "Cannot delete — product has sales records.";
    } else {
        $stmt = $conn->prepare("SELECT image FROM products WHERE id=? AND user_id=?");
        $stmt->bind_param("ii", $del_id, $user_id);
        $stmt->execute();
        $r = $stmt->get_result();
        if ($r->num_rows) {
            $row = $r->fetch_assoc();
            if ($row['image'] && file_exists(__DIR__ . '/' . $row['image'])) {
                @unlink(__DIR__ . '/' . $row['image']);
            }
        }
        $stmt->close();

        $del = $conn->prepare("DELETE FROM products WHERE id=? AND user_id=?");
        $del->bind_param("ii", $del_id, $user_id);
        if ($del->execute()) $message = "Product deleted.";
        else $message = "Delete failed: " . $del->error;
        $del->close();
    }
    header("Location: inventory.php?msg=" . urlencode($message));
    exit();
}

// Fetch products
$stmt = $conn->prepare("SELECT * FROM products WHERE user_id = ? ORDER BY id ASC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$prodRes = $stmt->get_result();

$msgFromGet = isset($_GET['msg']) ? $_GET['msg'] : '';
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8"><title>Inventory</title>
<style>
body{font-family:Arial,sans-serif;background:#f4f6f9;padding:18px;margin:0 ;background-color: #A7D9F5;}
h1{margin-bottom:10px;color:#222;font-size:28px}
#header{display: flex; justify-content:space-between;}
a.dashboard-btn{display:inline-block;padding:8px 14px;background:#2575fc;color:#fff;text-decoration:none;border-radius:6px;font-weight:600;margin-bottom:16px}
.wrap{display:flex;gap:18px;flex-wrap:wrap}
.panel{background:#fff;padding:14px;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,0.08)}
.form-panel{flex:0 0 360px}
.list-panel{flex:1;overflow-x:auto}
input,textarea{width:96%;padding:8px;margin:6px 0;border-radius:6px;border:1px solid #ddd;font-size:14px}
button{padding:8px 12px;border:none;border-radius:6px;background:#28a745;color:#fff;font-weight:600;cursor:pointer}
button:hover{opacity:0.9}
table{width:100%;border-collapse:collapse;margin-top:10px;font-size:14px}
th,td{padding:8px;border:1px solid #eee;text-align:left;vertical-align:middle}
th{background:#fdf6e3;color:#333}
img{width:64px;height:64px;object-fit:cover;border-radius:6px}
small{font-size:12px;color:#555}
.actions a{text-decoration:none;margin-right:6px}
@media(max-width:900px){.wrap{flex-direction:column}.form-panel{width:100%}}
</style>
</head>
<body>
<div id="header"><h1>Inventory — <?php echo htmlspecialchars($_SESSION['username']); ?></h1>
<a href="dashboard.php" class="dashboard-btn" style="padding-top:1%;">Go to Dashboard</a></div>


<?php if ($message || $msgFromGet) echo "<div style='background:#eef6ff;padding:8px;border-radius:6px;margin-bottom:12px'>" . htmlspecialchars($message ?: $msgFromGet) . "</div>"; ?>

<div class="wrap">
  <div class="panel form-panel">
    <?php
      $edit_mode=false; $edit_data=null;
      if (isset($_GET['edit'])) {
        $eid = intval($_GET['edit']);
        $s2 = $conn->prepare("SELECT * FROM products WHERE id=? AND user_id=?");
        $s2->bind_param("ii",$eid,$user_id);
        $s2->execute();
        $res2 = $s2->get_result();
        if ($res2->num_rows) { $edit_mode=true; $edit_data=$res2->fetch_assoc(); }
        $s2->close();
      }
    ?>
    <h3><?php echo $edit_mode ? 'Edit Product' : 'Add Product'; ?></h3>
    <form method="post" enctype="multipart/form-data">
      <input type="hidden" name="action" value="<?php echo $edit_mode ? 'update' : 'add'; ?>">
      <?php if ($edit_mode) echo '<input type="hidden" name="prod_id" value="'.intval($edit_data['id']).'">'; ?>
      <label>Name</label>
      <input name="name" required value="<?php echo $edit_mode ? htmlspecialchars($edit_data['name']) : ''; ?>">
      <label>Description</label>
      <textarea name="description"><?php echo $edit_mode ? htmlspecialchars($edit_data['description']) : ''; ?></textarea>
      <label>Price</label>
      <input type="number" step="0.01" name="price" required value="<?php echo $edit_mode ? htmlspecialchars($edit_data['price']) : '0.00'; ?>">
      <label>Cost Price</label>
      <input type="number" step="0.01" name="cost_price" required value="<?php echo $edit_mode ? htmlspecialchars($edit_data['cost_price']) : '0.00'; ?>">
      <label>Tax %</label>
      <input type="number" step="0.01" name="tax_percent" required value="<?php echo $edit_mode ? htmlspecialchars($edit_data['tax_percent']) : '0.00'; ?>">
      <label>Stock</label>
      <input type="number" name="stock" required value="<?php echo $edit_mode ? intval($edit_data['stock']) : 0; ?>">
      <label>Image</label>
      <input type="file" name="image" accept="image/*" onchange="previewImg(event)">
      <div style="margin-top:8px">
        <?php if ($edit_mode && $edit_data['image']) { ?>
          <div class="small">Current:</div>
          <img id="imgPreview" src="<?php echo htmlspecialchars($edit_data['image']); ?>">
        <?php } else { ?>
          <img id="imgPreview" src="" style="display:none">
        <?php } ?>
      </div>
      <div style="margin-top:10px">
        <button type="submit"><?php echo $edit_mode ? 'Update' : 'Add'; ?></button>
        <?php if ($edit_mode) echo '<a href="inventory.php" style="margin-left:8px">Cancel</a>'; ?>
      </div>
    </form>
  </div>

  <div class="panel list-panel">
    <h3>Your Products</h3>
    <table>
      <thead><tr><th>#</th><th>Image</th><th>Name</th><th>Price</th><th>Stock</th><th>Actions</th></tr></thead>
      <tbody>
        <?php $i=1; while($p=$prodRes->fetch_assoc()){ ?>
          <tr>
            <td><?php echo $i++; ?></td>
            <td><?php if($p['image']) echo '<img src="'.htmlspecialchars($p['image']).'">'; ?></td>
            <td><?php echo htmlspecialchars($p['name']); ?></td>
            <td><?php echo number_format($p['price'],2); ?></td>
            <td><?php echo intval($p['stock']); ?></td>
            <td class="actions">
              <a href="?edit=<?php echo intval($p['id']); ?>">Edit</a>
              <a href="?delete=<?php echo intval($p['id']); ?>" onclick="return confirm('Delete this product?');" style="color:red">Delete</a>
            </td>
          </tr>
        <?php } ?>
        <?php if($prodRes->num_rows===0) echo '<tr><td colspan="6" style="text-align:center;color:#777">No products found.</td></tr>'; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
function previewImg(e){
    const img = document.getElementById('imgPreview');
    if(e.target.files && e.target.files[0]){
        img.src = URL.createObjectURL(e.target.files[0]);
        img.style.display='inline-block';
    }
}
</script>

</body>
</html>
