<?php
// Connect this project to the MySQL database.
$host = "localhost";
$user = "root";
$pass = "";
$db   = "duyag_2c";

$conn = mysqli_connect($host, $user, $pass, $db);

if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

if (!mysqli_set_charset($conn, "utf8mb4")) {
    die("Could not set the database character set: " . mysqli_error($conn));
}
?>
