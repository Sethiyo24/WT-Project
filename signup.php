<?php
session_start();
include 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $email === '' || $password === '') {
        $error = "Please fill all fields.";
    } else {
        // check email
        $stmt = $conn->prepare("SELECT id FROM user_pos WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $r = $stmt->get_result();
        if ($r->num_rows > 0) {
            $error = "Email already registered. Login instead.";
            $stmt->close();
        } else {
            $stmt->close();
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt2 = $conn->prepare("INSERT INTO user_pos (username, email, password) VALUES (?, ?, ?)");
            $stmt2->bind_param("sss", $username, $email, $hash);
            if ($stmt2->execute()) {
                $new_id = $stmt2->insert_id;
                $_SESSION['username'] = $username;
                $_SESSION['user_id'] = intval($new_id);
                header("Location: dashboard.php");
                exit;
            } else {
                $error = "Signup failed: " . $stmt2->error;
            }
            $stmt2->close();
        }
    }
}
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Signup</title>
<style>
body {
    margin:0;
    font-family: 'Segoe UI', Arial, sans-serif;
    background:#121212;
    color:#f1f1f1;
    display:flex;
    justify-content:center;
    align-items:center;
    height:100vh;
}
.container {
    background:#1e1e2f;
    padding:30px 40px;
    border-radius:12px;
    box-shadow:0 8px 24px rgba(0,0,0,0.6);
    width:360px;
}
h2 {
    color:#9b5de5;
    text-align:center;
    margin-bottom:20px;
}
input {
    width:100%;
    padding:10px;
    margin:8px 0;
    border-radius:6px;
    border:1px solid #333;
    background:#2a2a3d;
    color:#f1f1f1;
}
input:focus { border-color:#9b5de5; outline:none; box-shadow:0 0 5px rgba(155,93,229,0.5);}
button {
    width:100%;
    padding:10px;
    margin-top:10px;
    border:none;
    border-radius:6px;
    background:#9b5de5;
    color:#fff;
    font-weight:600;
    cursor:pointer;
}
button:hover { background:#00bbf9; }
p { text-align:center; margin-top:14px; }
p a { color:#00bbf9; text-decoration:none; font-weight:500; }
p a:hover { color:#9b5de5; }
.error { color:#f15bb5; margin-bottom:10px; text-align:center; }
</style>
</head>
<body>
<div class="container">
  <h2>Sign up (create your business)</h2>
  <?php if (!empty($error)) echo "<div class='error'>$error</div>"; ?>
  <form method="post" action="signup.php">
    <input name="username" placeholder="Business / Admin name" required>
    <input name="email" type="email" placeholder="Email" required>
    <input name="password" type="password" placeholder="Password" required>
    <button type="submit">Sign up & Start</button>
  </form>
  <p>Already registered? <a href="login.php">Login</a></p>
</div>
</body>
</html>

