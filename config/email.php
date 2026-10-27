<?php
function smtpRead($socket, array $expectedCodes)
{
    $response = "";

    do {
        $line = @fgets($socket, 515);
        if ($line === false) {
            throw new RuntimeException("SMTP server closed the connection.");
        }

        $response .= $line;
        $continue = isset($line[3]) && $line[3] === "-";
    } while ($continue);

    $code = (int) substr($line, 0, 3);
    if (!in_array($code, $expectedCodes, true)) {
        throw new RuntimeException("SMTP server rejected a mail command with code " . $code . ".");
    }

    return $response;
}

function smtpCommand($socket, $command, array $expectedCodes)
{
    smtpWrite($socket, $command . "\r\n");
    return smtpRead($socket, $expectedCodes);
}

function smtpWrite($socket, $data)
{
    $length = strlen($data);
    $written = 0;

    while ($written < $length) {
        $result = @fwrite($socket, substr($data, $written));
        if ($result === false || $result === 0) {
            throw new RuntimeException("Could not send an SMTP command.");
        }
        $written += $result;
    }
}

function sendVerificationEmail($email, $token)
{
    $mailSettings = require __DIR__ . "/mail.php";
    if (empty($mailSettings["host"]) || empty($mailSettings["username"]) ||
        empty($mailSettings["password"]) || empty($mailSettings["from_email"]) ||
        !filter_var($mailSettings["username"], FILTER_VALIDATE_EMAIL) ||
        !filter_var($mailSettings["from_email"], FILTER_VALIDATE_EMAIL) ||
        !filter_var($mailSettings["host"], FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) ||
        (int) $mailSettings["port"] < 1 || (int) $mailSettings["port"] > 65535) {
        error_log("Verification email was not sent: configure SMTP settings in config/mail.php.");
        return false;
    }
    if (!extension_loaded("openssl")) {
        error_log("Verification email was not sent: enable the PHP OpenSSL extension for SMTP TLS.");
        return false;
    }

    $isHttps = isset($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off";
    $scheme = $isHttps ? "https" : "http";

    if (isset($_SERVER["SCRIPT_NAME"])) {
        $basePath = rtrim(str_replace("\\", "/", dirname($_SERVER["SCRIPT_NAME"])), "/");
    } else {
        $basePath = "";
    }

    if (isset($_SERVER["HTTP_HOST"])) {
        $host = $_SERVER["HTTP_HOST"];
    } elseif (isset($_SERVER["SERVER_NAME"])) {
        $host = $_SERVER["SERVER_NAME"];
    } else {
        $host = "localhost";
    }

    if (!preg_match('/^[a-zA-Z0-9.-]+(?::[0-9]{1,5})?$/', $host)) {
        if (isset($_SERVER["SERVER_NAME"])) {
            $host = $_SERVER["SERVER_NAME"];
        } else {
            $host = "localhost";
        }
    }
    $verificationUrl = $scheme . "://" . $host . $basePath . "/verify.php?token=" . urlencode($token);

    $port = (int) $mailSettings["port"];
    $encryption = $mailSettings["encryption"];
    $target = ($encryption === "ssl" ? "ssl://" : "") . $mailSettings["host"] . ":" . $port;
    $socket = @stream_socket_client($target, $errorNumber, $errorMessage, 15, STREAM_CLIENT_CONNECT);
    if (!$socket) {
        error_log("Verification email SMTP connection failed: " . $errorMessage);
        return false;
    }

    stream_set_timeout($socket, 15);

    try {
        smtpRead($socket, [220]);
        smtpCommand($socket, "EHLO localhost", [250]);

        if ($encryption === "tls") {
            smtpCommand($socket, "STARTTLS", [220]);
            if (!@stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException("Could not enable TLS for SMTP.");
            }
            smtpCommand($socket, "EHLO localhost", [250]);
        } elseif ($encryption !== "ssl") {
            throw new RuntimeException("SMTP encryption must be tls or ssl.");
        }

        smtpCommand($socket, "AUTH LOGIN", [334]);
        smtpCommand($socket, base64_encode($mailSettings["username"]), [334]);
        smtpCommand($socket, base64_encode($mailSettings["password"]), [235]);
        smtpCommand($socket, "MAIL FROM:<" . $mailSettings["from_email"] . ">", [250]);
        smtpCommand($socket, "RCPT TO:<" . $email . ">", [250, 251]);
        smtpCommand($socket, "DATA", [354]);

        $subject = base64_encode("Verify your account");
        $fromNameText = str_replace(["\r", "\n"], "", $mailSettings["from_name"]);
        $fromName = "=?UTF-8?B?" . base64_encode($fromNameText) . "?=";
        $body = "Please verify your email address by opening this link:\n\n" .
            $verificationUrl . "\n\nIf you did not create this account, ignore this email.";
        $body = quoted_printable_encode($body);
        $body = preg_replace('/^\./m', '..', $body);
        $body = preg_replace('/\r\n|\r|\n/', "\r\n", $body);

        $message = "From: " . $fromName . " <" . $mailSettings["from_email"] . ">\r\n" .
            "To: <" . $email . ">\r\n" .
            "Subject: =?UTF-8?B?" . $subject . "?=\r\n" .
            "MIME-Version: 1.0\r\n" .
            "Content-Type: text/plain; charset=UTF-8\r\n" .
            "Content-Transfer-Encoding: quoted-printable\r\n\r\n" .
            $body . "\r\n.";

        smtpWrite($socket, $message . "\r\n");
        smtpRead($socket, [250]);
        smtpCommand($socket, "QUIT", [221]);
        fclose($socket);
        return true;
    } catch (RuntimeException $exception) {
        error_log("Verification email SMTP error: " . $exception->getMessage());
        fclose($socket);
        return false;
    }
}
