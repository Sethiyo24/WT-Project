<?php
session_start();
include 'db.php';

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $email === '' || $password === '') {
        $error = "Please fill all fields.";
    } else {
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
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title>Sign Up - POS System</title>
<style>
  :root{
    --bg:#121212;
    --panel:#1e1e2f;
    --muted:#bdbdbd;
    --accent:#9b5de5;
    --cta-start:#ffd166;
    --cta-end:#9b5de5;
  }
  *{box-sizing:border-box;}
  body{
    margin:0;
    min-height:100vh;
    display:flex;
    align-items:center;
    justify-content:center;
    background:linear-gradient(180deg,var(--bg),#0f0f14);
    color:#f1f1f1;
    font-family:"Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
  }

  .container{
    width:480px;
    max-width:94%;
    background: linear-gradient(180deg, rgba(255,255,255,0.04), rgba(255,255,255,0.05));
    border-radius:20px;
    padding:50px 40px;
    box-shadow:0 12px 40px rgba(0,0,0,0.7);
    border:1px solid rgba(255,255,255,0.05);
    backdrop-filter: blur(8px);
    animation: pop .45s ease both;
  }
  @keyframes pop{
    from{opacity:0; transform:translateY(-20px) scale(.96);}
    to{opacity:1; transform:translateY(0) scale(1);}
  }

  .brand{
    text-align:center;
    margin-bottom:20px;
  }
  .brand h1{
    font-size:36px;
    margin:0;
    color:#ffd6e8;
    font-weight:700;
  }
  .subtitle{
    text-align:center;
    color:var(--muted);
    font-size:18px;
    margin-bottom:28px;
  }

  .error{
    background: rgba(241,91,181,0.1);
    color:#f15bb5;
    border:1px solid rgba(241,91,181,0.25);
    padding:16px;
    border-radius:10px;
    margin-bottom:20px;
    text-align:center;
    font-size:18px;
    font-weight:600;
  }

  .form-group input{
    width:100%;
    padding:18px 20px;
    margin-bottom:20px;
    border-radius:12px;
    border:2px solid rgba(255,255,255,0.08);
    background:var(--panel);
    color:#f1f1f1;
    font-size:18px;
  }
  .form-group input::placeholder{ color:#aaa; font-size:17px; }
  .form-group input:focus{
    outline:none;
    border-color:var(--accent);
    box-shadow:0 0 12px rgba(155,93,229,0.6);
  }

  .btn{
    width:100%;
    padding:20px;
    border-radius:12px;
    border:none;
    background:linear-gradient(90deg,var(--cta-start), var(--cta-end));
    color:#121212;
    font-weight:800;
    font-size:20px;
    cursor:pointer;
    box-shadow:0 12px 28px rgba(155,93,229,0.25);
    transition:transform .18s ease, box-shadow .18s ease;
  }
  .btn:hover{
    transform:translateY(-4px);
    box-shadow:0 20px 50px rgba(0,0,0,0.6);
  }

  .meta{
    text-align:center;
    margin-top:20px;
    color:var(--muted);
    font-size:16px;
  }
  .meta a{ color:var(--accent); text-decoration:none; font-weight:700; font-size:17px; }
  .meta a:hover{ text-decoration:underline; }
</style>
</head>
<body>
  <div class="container">
    <div class="brand">
      <h1>POS System</h1>
    </div>
    <div class="subtitle">Create your business account to get started</div>

    <?php if (!empty($error)) echo "<div class='error'>".htmlspecialchars($error, ENT_QUOTES)."</div>"; ?>

    <form method="post" action="signup.php" autocomplete="off">
      <div class="form-group">
        <input name="username" placeholder="Business / Admin name" required value="<?php echo htmlspecialchars($_POST['username'] ?? '', ENT_QUOTES); ?>">
        <input name="email" type="email" placeholder="Email" required value="<?php echo htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES); ?>">
        <input name="password" type="password" placeholder="Password" required>
      </div>
      <button class="btn" type="submit">Sign up & Start</button>
    </form>

    <div class="meta">
      Already registered? <a href="login.php">Login</a>
    </div>
  </div>
</body>
</html>
