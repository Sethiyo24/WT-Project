<?php
session_start();
include 'db.php';

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $conn->prepare("SELECT id, username, password FROM user_pos WHERE email = ?");
    if ($stmt) {
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($res->num_rows === 0) {
            $error = "No account found with this email.";
        } else {
            $user = $res->fetch_assoc();
            if (password_verify($password, $user['password'])) {
                session_regenerate_id(true);
                $_SESSION['username'] = $user['username'];
                $_SESSION['user_id'] = intval($user['id']);
                header("Location: dashboard.php");
                exit;
            } else {
                $error = "Wrong password.";
            }
        }
        $stmt->close();
    } else {
        $error = "Database error. Please try again later.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title>Login - POS System</title>
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
    <div class="subtitle">Login to continue — Manage inventory & reports</div>

    <?php if (!empty($error)) echo "<div class='error'>".htmlspecialchars($error, ENT_QUOTES)."</div>"; ?>

    <form method="post" action="login.php" autocomplete="off">
      <div class="form-group">
        <input type="email" name="email" placeholder="Email" required value="<?php echo htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES); ?>">
        <input type="password" name="password" placeholder="Password" required>
      </div>
      <button class="btn" type="submit">Login</button>
    </form>

    <div class="meta">
      No account? <a href="signup.php">Sign up</a>
    </div>
  </div>
</body>
</html>
