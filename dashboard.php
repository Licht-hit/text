<?php
session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: index.php");
    exit;
}

$dashboards = [
    "admin" => "admin/dashboard.php",
    "staff" => "staff/dashboard.php",
    "student" => "student/dashboard.php"
];

$role = isset($_SESSION["role"]) ? $_SESSION["role"] : "";
if (!isset($dashboards[$role])) {
    require "config/database.php";
    $lookup = mysqli_prepare($conn, "SELECT role, full_name FROM users WHERE id = ? LIMIT 1");
    mysqli_stmt_bind_param($lookup, "i", $_SESSION["user_id"]);
    mysqli_stmt_execute($lookup);
    mysqli_stmt_bind_result($lookup, $role, $fullName);
    $found = mysqli_stmt_fetch($lookup);
    mysqli_stmt_close($lookup);

    if (!$found || !isset($dashboards[$role])) {
        session_destroy();
        header("Location: index.php");
        exit;
    }

    $_SESSION["role"] = $role;
    $_SESSION["full_name"] = $fullName;
}

header("Location: " . $dashboards[$role]);
exit;
?>
