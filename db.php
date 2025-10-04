<?php

$servername = "localhost";
$db_user = "root";       // RENAMED VARIABLE!
$db_password = "";       // ALSO RENAME TO AVOID FUTURE ISSUES!
$database = "pos_db";

$conn = new mysqli($servername, $db_user, $db_password, $database); // Use new variables here
if ($conn->connect_error) {
    die("DB connection failed: " . $conn->connect_error);
}
