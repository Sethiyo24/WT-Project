<?php
// 1. MUST BE THE FIRST EXECUTABLE LINE
session_start();

// 2. Clear all session variables by resetting the array
$_SESSION = array();

// 3. Delete the session cookie on the client side
// This is the CRITICAL step for a full logout!
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(), 
        '', 
        time() - 42000, // Set the expiration to a time in the past
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// 4. Destroy the session data on the server
session_destroy();

// 5. Redirect the user
header("Location: login.php");
exit(); 
?>