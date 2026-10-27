<?php
require "config/database.php";

$message = "";
$verified = false;

if (isset($_GET["token"])) {
    $token = $_GET["token"];
} else {
    $token = "";
}

if (is_string($token) && preg_match('/^[a-f0-9]{64}$/', $token)) {
    $lookup = mysqli_prepare($conn, "SELECT id FROM users WHERE verification_token = ? AND email_verified = 0 LIMIT 1");
    mysqli_stmt_bind_param($lookup, "s", $token);
    mysqli_stmt_execute($lookup);
    mysqli_stmt_bind_result($lookup, $userId);
    $user = mysqli_stmt_fetch($lookup);
    mysqli_stmt_close($lookup);

    if ($user) {
        $update = mysqli_prepare($conn, "UPDATE users SET email_verified = 1, verification_token = NULL WHERE id = ? AND email_verified = 0");
        mysqli_stmt_bind_param($update, "i", $userId);
        $verified = mysqli_stmt_execute($update) && mysqli_stmt_affected_rows($update) === 1;
        mysqli_stmt_close($update);
    }
}

if ($verified) {
    $message = "Your email is verified. You can now log in.";
} else {
    $message = "This verification link is invalid, expired, or has already been used.";
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Email Verification</title>
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <script src="assets/js/app.js" defer></script>
</head>
<body class="login-page">
<main class="container auth-shell">
    <div class="card auth-card"><div class="card-body p-4 p-md-5">
        <span class="eyebrow">ACCOUNT SECURITY</span>
        <h1 class="h3 mt-2">Email verification</h1>
        <div class="alert <?php echo $verified ? "alert-success" : "alert-warning"; ?>"><?php echo htmlspecialchars($message, ENT_QUOTES, "UTF-8"); ?></div>
        <a href="index.php" class="btn btn-primary w-100">Go to login</a>
    </div></div>
</main>
</body>
</html>
