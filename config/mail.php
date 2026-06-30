<?php

require_once __DIR__ . '/app.php';

function send_password_reset_email(string $toEmail, string $resetLink): bool
{
    $subject = APP_NAME . ' Password Reset';

    $message = "
Hello,

We received a request to reset your password.

Use the link below to choose a new password:

$resetLink

This link will expire in " . RESET_TOKEN_EXPIRY_MINUTES . " minutes.

If you did not request a password reset, you can ignore this email.

Thanks,
" . APP_NAME;

    $headers = [];
    $headers[] = "MIME-Version: 1.0";
    $headers[] = "Content-type: text/plain; charset=UTF-8";
    $headers[] = "From: no-reply@" . ($_SERVER['HTTP_HOST'] ?? 'localhost');

    return mail($toEmail, $subject, $message, implode("\r\n", $headers));
}