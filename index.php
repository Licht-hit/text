<?php
session_start();
require "config/database.php";

if (!isset($_SESSION["csrf_token"])) {
    // This token helps reject login forms submitted from another website.
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

if (isset($_SESSION["user_id"])) {
    header("Location: dashboard.php");
    exit;
}

$error = "";
$login = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Check the form token before processing the login.
    if (!isset($_POST["csrf_token"]) || !is_string($_POST["csrf_token"]) ||
        !hash_equals($_SESSION["csrf_token"], $_POST["csrf_token"])) {
        $error = "Invalid form request. Reload the page and try again.";
    } else {
        if (isset($_POST["login"]) && is_string($_POST["login"])) {
            $login = trim($_POST["login"]);
        } else {
            $login = "";
        }

        if (isset($_POST["password"]) && is_string($_POST["password"])) {
            $password = $_POST["password"];
        } else {
            $password = "";
        }

        // Placeholders keep the login text separate from the SQL query.
        $statement = mysqli_prepare($conn, "SELECT id, full_name, password, role, email_verified FROM users WHERE username = ? OR email = ? LIMIT 1");
        mysqli_stmt_bind_param($statement, "ss", $login, $login);
        mysqli_stmt_execute($statement);
        mysqli_stmt_bind_result($statement, $userId, $fullNameResult, $passwordHash, $roleResult, $emailVerified);
        $user = null;
        if (mysqli_stmt_fetch($statement)) {
            $user = [
                "id" => $userId,
                "full_name" => $fullNameResult,
                "password" => $passwordHash,
                "role" => $roleResult,
                "email_verified" => $emailVerified
            ];
        }
        mysqli_stmt_close($statement);

        if ($user && password_verify($password, $user["password"])) {
            if (!(int) $user["email_verified"]) {
                $error = "Please verify your email address before logging in.";
            } else {
                session_regenerate_id(true);
                $_SESSION["user_id"] = $user["id"];
                $_SESSION["full_name"] = $user["full_name"];
                $_SESSION["role"] = $user["role"];
                header("Location: dashboard.php");
                exit;
            }
        } else {
            $error = "Invalid username/email or password.";
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login - Account Portal</title>
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <script src="assets/js/app.js" defer></script>
</head>
<body class="login-page">
<div class="container">
    <div class="login-box">
        <div class="card"><div class="card-body p-4">
            <span class="eyebrow">WELCOME</span>
            <h1 class="h3 mt-2">Sign in to your account</h1>
            <p class="text-muted">Use your username or email and password.</p>

            <?php if ($error !== ""): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error, ENT_QUOTES, "UTF-8"); ?></div>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION["csrf_token"], ENT_QUOTES, "UTF-8"); ?>">
                <div class="mb-3">
                    <label class="form-label" for="login">Username or email</label>
                    <input id="login" type="text" name="login" class="form-control" value="<?php echo htmlspecialchars($login, ENT_QUOTES, "UTF-8"); ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="password">Password</label>
                    <input id="password" type="password" name="password" class="form-control" required>
                </div>
                <button class="btn btn-primary w-100" type="submit">Login</button>
            </form>
            <p class="text-center mt-3 mb-0">New here? <a href="register.php">Create an account</a></p>
        </div></div>
    </div>
</div>
</body>
</html>
