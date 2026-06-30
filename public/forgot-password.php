<?php
session_start();

require_once '../config/database.php';
require_once '../config/aws.php';
require_once '../includes/auth_helpers.php';

$message = '';
$messageType = '';

function send_password_reset_email_ses($ses, $toEmail, $resetLink) {
    $fromEmail = 'noreply@whusup.com';
    $subject = 'Reset your Whusup password';

    $plainText = "You requested a password reset for your Whusup account.\n\n"
        . "Click the link below to reset your password:\n\n"
        . $resetLink . "\n\n"
        . "If you did not request this, you can safely ignore this email.";

    $htmlBody = '
        <div style="font-family: Arial, sans-serif; background:#f2f4f8; padding:32px;">
            <div style="max-width:520px; margin:0 auto; background:#ffffff; border-radius:18px; padding:34px; box-shadow:0 8px 28px rgba(0,0,0,0.08); text-align:center;">
                <h2 style="font-size:28px; color:#111827; margin:0 0 8px; font-weight:700;">
                    Reset your password
                </h2>

                <p style="color:#6b7280; font-size:15px; line-height:1.6; margin:0 0 26px;">
                    You requested a password reset for your Whusup account.
                </p>

                <a href="' . htmlspecialchars($resetLink, ENT_QUOTES, 'UTF-8') . '"
                   style="display:inline-block; background:#111827; color:#ffffff; padding:12px 26px; border-radius:999px; text-decoration:none; font-weight:700;">
                    Reset Password
                </a>

                <p style="color:#6b7280; font-size:13px; line-height:1.5; margin:26px 0 0;">
                    If you did not request this, you can safely ignore this email.
                </p>
            </div>
        </div>
    ';

    $ses->sendEmail([
        'Source' => $fromEmail,
        'Destination' => [
            'ToAddresses' => [$toEmail],
        ],
        'Message' => [
            'Subject' => [
                'Data' => $subject,
                'Charset' => 'UTF-8',
            ],
            'Body' => [
                'Text' => [
                    'Data' => $plainText,
                    'Charset' => 'UTF-8',
                ],
                'Html' => [
                    'Data' => $htmlBody,
                    'Charset' => 'UTF-8',
                ],
            ],
        ],
    ]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim($_POST['email'] ?? ''));

    if (empty($email)) {
        $message = "Please enter your email address.";
        $messageType = "danger";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Please enter a valid email address.";
        $messageType = "danger";

    } else {
        cleanup_expired_reset_tokens($pdo);

        $user = find_user_by_email($pdo, $email);

        if ($user) {
            try {
                $awsConfig = require '../config/aws.php';
                $ses = $awsConfig['ses'];

                $rawToken = create_password_reset_token($pdo, $user['id']);
                $resetLink = build_password_reset_link($rawToken);

                send_password_reset_email_ses($ses, $user['email'], $resetLink);

            } catch (Exception $e) {
                error_log('Password reset email failed: ' . $e->getMessage());
            }
        }

        $message = "If that email exists in our system, a password reset link has been sent.";
        $messageType = "success";
    }
}

include '../includes/header.php';
include '../includes/navbar.php';
?>

<style>
html,
body {
    margin: 0;
    padding: 0;
    background: #f2f4f8;
}

.auth-page {
    width: 100%;
    min-height: 100vh;
    background: #f2f4f8;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 96px 0 28px;
    margin: 0;
    box-sizing: border-box;
}

.auth-wrapper {
    width: 100%;
}

.auth-card {
    border: none;
    border-radius: 18px;
    box-shadow: 0 8px 28px rgba(0,0,0,0.08);
    padding: 10px;
}

.auth-title {
    font-family: "Poppins", sans-serif;
    font-size: 32px;
    font-weight: 700;
    color: #111827;
    margin-bottom: 6px;
}

.auth-subtitle {
    color: #6b7280;
    font-size: 15px;
}

.auth-alert {
    border-radius: 10px;
    font-size: 14px;
}

.auth-input {
    border-radius: 12px !important;
    border: 1px solid #d1d5db;
}

.auth-input:focus {
    border-color: #9ca3af;
    box-shadow: none;
}

.auth-btn {
    background: #111827;
    border-color: #111827;
    border-radius: 999px;
    padding: 11px;
    font-weight: 700;
    transition: 0.2s ease;
}

.auth-btn:hover {
    background: #374151;
    border-color: #374151;
}

.auth-link {
    color: #111827;
    text-decoration: none;
    font-weight: 600;
}

.auth-link:hover {
    text-decoration: underline;
}

@media (max-width: 768px) {
    .auth-page {
        padding: 92px 12px 28px;
        align-items: flex-start;
    }

    .auth-card {
        border-radius: 14px;
    }

    .auth-title {
        font-size: 28px;
    }
}
</style>

<div class="auth-page">

    <div class="container-fluid auth-wrapper d-flex align-items-center justify-content-center px-3">

        <div class="row w-100 justify-content-center">

            <div class="col-12 col-sm-10 col-md-8 col-lg-5 col-xl-4">

                <div class="card auth-card">

                    <div class="card-body">

                        <div class="text-center mb-4">

                            <h2 class="auth-title">
                                Forgot Password
                            </h2>

                            <p class="auth-subtitle">
                                Enter your email to reset your password
                            </p>

                        </div>

                        <?php if (!empty($message)): ?>
                            <div class="alert alert-<?= htmlspecialchars($messageType) ?> auth-alert">
                                <?= htmlspecialchars($message) ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="">

                            <div class="form-floating mb-4">

                                <input
                                    type="email"
                                    name="email"
                                    id="forgotEmail"
                                    class="form-control auth-input"
                                    placeholder="name@example.com"
                                    value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                                    required
                                >

                                <label for="forgotEmail">
                                    Email address
                                </label>

                            </div>

                            <div class="d-grid">

                                <button type="submit" class="btn btn-primary auth-btn">
                                    Send Reset Link
                                </button>

                            </div>

                        </form>

                        <div class="text-center mt-4">

                            <a href="login.php" class="auth-link">
                                Back to login
                            </a>

                        </div>

                    </div>

                </div>

            </div>

        </div>

    </div>

</div>

<script src="js/auth.js"></script>

<?php include '../includes/footer.php'; ?>