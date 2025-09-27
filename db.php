<?php
// db.php - DB connection
$servername = "localhost";
$username = "root";
$password = "";
$database = "pos_db";

$conn = new mysqli($servername, $username, $password, $database);
if ($conn->connect_error) {
    die("DB connection failed: " . $conn->connect_error);
}
?>
