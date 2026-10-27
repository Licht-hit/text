<?php
session_start();
require "config/database.php";
require "config/email.php";

if (!isset($_SESSION["csrf_token"])) {
    // This token helps reject registration forms submitted from another website.
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$error = "";
$message = "";
$fullName = "";
$username = "";
$email = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Check the form token before processing registration details.
    if (!isset($_POST["csrf_token"]) || !is_string($_POST["csrf_token"]) ||
        !hash_equals($_SESSION["csrf_token"], $_POST["csrf_token"])) {
        $error = "Invalid form request. Reload the page and try again.";
    } else {
        if (isset($_POST["full_name"]) && is_string($_POST["full_name"])) {
            $fullName = trim($_POST["full_name"]);
        } else {
            $fullName = "";
        }

        if (isset($_POST["username"]) && is_string($_POST["username"])) {
            $username = trim($_POST["username"]);
        } else {
            $username = "";
        }

        if (isset($_POST["email"]) && is_string($_POST["email"])) {
            $email = strtolower(trim($_POST["email"]));
        } else {
            $email = "";
        }

        if (isset($_POST["password"]) && is_string($_POST["password"])) {
            $password = $_POST["password"];
        } else {
            $password = "";
        }

        if ($fullName === "" || strlen($fullName) > 100 ||
            !preg_match('/^[a-zA-Z0-9_.-]{3,50}$/', $username) ||
            strlen($email) > 255 || !filter_var($email, FILTER_VALIDATE_EMAIL) ||
            strlen($password) < 8) {
            $error = "Enter a name, a username of 3-50 letters/numbers/dots/underscores/hyphens, a valid email, and a password with at least 8 characters.";
        } else {
            // Check whether this username or email already belongs to an account.
            $lookup = mysqli_prepare($conn, "SELECT id, email, email_verified FROM users WHERE username = ? OR email = ? LIMIT 1");
            mysqli_stmt_bind_param($lookup, "ss", $username, $email);
            mysqli_stmt_execute($lookup);
            mysqli_stmt_bind_result($lookup, $existingId, $existingEmail, $existingVerified);
            $existing = null;
            if (mysqli_stmt_fetch($lookup)) {
                $existing = [
                    "id" => $existingId,
                    "email" => $existingEmail,
                    "email_verified" => $existingVerified
                ];
            }
            mysqli_stmt_close($lookup);

            if ($existing) {
                if (!(int) $existing["email_verified"] && strtolower($existing["email"]) === strtolower($email)) {
                    $token = bin2hex(random_bytes(32));
                    $update = mysqli_prepare($conn, "UPDATE users SET verification_token = ? WHERE id = ?");
                    mysqli_stmt_bind_param($update, "si", $token, $existing["id"]);
                    mysqli_stmt_execute($update);
                    mysqli_stmt_close($update);
                    $message = sendVerificationEmail($email, $token)
                        ? "A new verification email has been sent. Check your inbox."
                        : "The account is waiting for verification, but email delivery failed. Check config/mail.php and the PHP error log, then submit again to resend.";
                } else {
                    $error = "That username or email is already in use.";
                }
            } else {
                // Save a password hash, not the original password.
                $token = bin2hex(random_bytes(32));
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $insert = mysqli_prepare($conn, "INSERT INTO users (full_name, username, email, password, role, email_verified, verification_token) VALUES (?, ?, ?, ?, 'staff', 0, ?)");
                mysqli_stmt_bind_param($insert, "sssss", $fullName, $username, $email, $hashedPassword, $token);

                try {
                    mysqli_stmt_execute($insert);
                    $message = sendVerificationEmail($email, $token)
                        ? "Your account was created. Check your email and open the verification link before logging in."
                        : "Your account was created but email delivery failed. Check config/mail.php and the PHP error log, then submit this form again with the same email to resend.";
                    $fullName = "";
                    $username = "";
                    $email = "";
                } catch (mysqli_sql_exception $exception) {
                    if ($exception->getCode() === 1062) {
                        $error = "That username or email is already in use.";
                    } else {
                        error_log("Account registration failed: " . $exception->getMessage());
                        $error = "Could not create the account. Check the database setup and try again.";
                    }
                }
                mysqli_stmt_close($insert);
            }
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Create Account</title>
    <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <script src="assets/js/app.js" defer></script>
</head>
<body class="login-page">
<main class="container auth-shell">
    <div class="card auth-card"><div class="card-body p-4 p-md-5">
        <span class="eyebrow">NEW ACCOUNT</span>
        <h1 class="h3 mt-2">Create your account</h1>
        <p class="text-muted">You must verify your email before logging in.</p>

        <?php if ($error !== ""): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error, ENT_QUOTES, "UTF-8"); ?></div>
        <?php endif; ?>
        <?php if ($message !== ""): ?>
            <div class="alert alert-info"><?php echo htmlspecialchars($message, ENT_QUOTES, "UTF-8"); ?></div>
        <?php endif; ?>

        <form method="POST" class="mt-4">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION["csrf_token"], ENT_QUOTES, "UTF-8"); ?>">
            <div class="mb-3">
                <label class="form-label" for="full_name">Full name</label>
                <input id="full_name" type="text" name="full_name" class="form-control" maxlength="100" value="<?php echo htmlspecialchars($fullName, ENT_QUOTES, "UTF-8"); ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label" for="username">Username</label>
                <input id="username" type="text" name="username" class="form-control" maxlength="50" pattern="[A-Za-z0-9_.-]{3,50}" value="<?php echo htmlspecialchars($username, ENT_QUOTES, "UTF-8"); ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label" for="email">Email</label>
                <input id="email" type="email" name="email" class="form-control" maxlength="255" value="<?php echo htmlspecialchars($email, ENT_QUOTES, "UTF-8"); ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label" for="password">Password (at least 8 characters)</label>
                <input id="password" type="password" name="password" class="form-control" minlength="8" required>
            </div>
            <button type="submit" class="btn btn-primary w-100">Create account</button>
        </form>
        <p class="mt-4 mb-0 text-center">Already registered? <a href="index.php">Sign in</a></p>
    </div></div>
</main>
</body>
</html>
