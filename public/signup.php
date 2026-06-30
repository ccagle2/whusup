<?php
session_start();

require_once '../config/database.php';

$awsConfig = require __DIR__ . '/../config/aws.php';
$ses = $awsConfig['ses'];

$message = '';
$messageType = '';

function get_client_ip_address(): ?string {
    if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        return trim($_SERVER['HTTP_CF_CONNECTING_IP']);
    }

    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $parts = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim($parts[0]);
    }

    return $_SERVER['REMOTE_ADDR'] ?? null;
}

function log_signup_attempt(PDO $pdo, string $name, string $email, int $success = 0, ?string $failureReason = null): void {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO signup_log (
                name,
                email,
                ip_address,
                user_agent,
                success,
                failure_reason
            )
            VALUES (
                :name,
                :email,
                :ip_address,
                :user_agent,
                :success,
                :failure_reason
            )
        ");

        $stmt->execute([
            ':name' => $name !== '' ? $name : null,
            ':email' => $email !== '' ? $email : null,
            ':ip_address' => get_client_ip_address(),
            ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            ':success' => $success,
            ':failure_reason' => $failureReason
        ]);

    } catch (Exception $e) {
        error_log('Signup log failed: ' . $e->getMessage());
    }
}

function is_valid_real_name(string $name): bool {
    $name = trim($name);
    $name = preg_replace('/\s+/', ' ', $name);

    if (strlen($name) < 3 || strlen($name) > 60) {
        return false;
    }

    if (preg_match('/https?:\/\/|www\.|@/i', $name)) {
        return false;
    }

    if (preg_match('/(.)\1{3,}/', $name)) {
        return false;
    }

    if (!preg_match("/^[a-zA-Z\s'\-]+$/", $name)) {
        return false;
    }

    $lettersOnly = preg_replace('/[^a-zA-Z]/', '', $name);

    if (strlen($lettersOnly) < 3) {
        return false;
    }

    $parts = preg_split('/\s+/', $name, -1, PREG_SPLIT_NO_EMPTY);

    if (count($parts) < 2) {
        return false;
    }

    foreach ($parts as $part) {
        if (strlen($part) < 2) {
            return false;
        }

        if (strlen($part) > 24) {
            return false;
        }

        if (preg_match('/[A-Z]{5,}/', $part)) {
            return false;
        }

        if (preg_match('/[a-z][A-Z][a-z][A-Z]/', $part)) {
            return false;
        }
    }

    return true;
}

function is_disposable_email(string $email): bool {
    $blockedDomains = [
        'mailinator.com',
        '10minutemail.com',
        'guerrillamail.com',
        'tempmail.com',
        'temp-mail.org',
        'yopmail.com',
        'trashmail.com',
        'fakeinbox.com',
        'getnada.com',
        'dispostable.com',
        'maildrop.cc'
    ];

    $domain = strtolower(substr(strrchr($email, "@"), 1));

    return in_array($domain, $blockedDomains, true);
}

function email_domain_has_mx(string $email): bool {
    $domain = substr(strrchr($email, "@"), 1);

    if (!$domain) {
        return false;
    }

    return checkdnsrr($domain, 'MX');
}

function sendVerificationEmail($ses, string $toEmail, string $name, string $verificationLink): void {
    $fromEmail = 'noreply@whusup.com';
    $fromName = 'Whusup';

    $safeName = trim($name) !== '' ? trim($name) : 'there';

    $subject = 'Verify your Whusup account';

    $textBody = "Hi {$safeName},\n\n" .
        "Thanks for signing up for Whusup. Please verify your email address by opening this link:\n\n" .
        $verificationLink . "\n\n" .
        "This link expires in 24 hours.\n\n" .
        "If you did not create a Whusup account, you can ignore this email.";

    $htmlBody = '
        <div style="font-family: Arial, sans-serif; line-height: 1.6; color: #111827; max-width: 560px; margin: 0 auto; padding: 24px;">
            <h2 style="margin: 0 0 12px;">Verify your Whusup account</h2>
            <p>Hi ' . htmlspecialchars($safeName, ENT_QUOTES, 'UTF-8') . ',</p>
            <p>Thanks for signing up for Whusup. Please verify your email address to activate your account.</p>
            <p style="margin: 24px 0;">
                <a href="' . htmlspecialchars($verificationLink, ENT_QUOTES, 'UTF-8') . '" style="background: #111827; color: #ffffff; padding: 12px 20px; border-radius: 999px; text-decoration: none; font-weight: 700; display: inline-block;">
                    Verify Email
                </a>
            </p>
            <p>If the button does not work, copy and paste this link into your browser:</p>
            <p style="word-break: break-all;">
                <a href="' . htmlspecialchars($verificationLink, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($verificationLink, ENT_QUOTES, 'UTF-8') . '</a>
            </p>
            <p>This link expires in 24 hours.</p>
            <p style="color: #6b7280; font-size: 13px;">If you did not create a Whusup account, you can ignore this email.</p>
        </div>
    ';

    $ses->sendEmail([
        'Source' => $fromName . ' <' . $fromEmail . '>',
        'Destination' => [
            'ToAddresses' => [$toEmail]
        ],
        'Message' => [
            'Subject' => [
                'Data' => $subject,
                'Charset' => 'UTF-8'
            ],
            'Body' => [
                'Text' => [
                    'Data' => $textBody,
                    'Charset' => 'UTF-8'
                ],
                'Html' => [
                    'Data' => $htmlBody,
                    'Charset' => 'UTF-8'
                ]
            ]
        ]
    ]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name = trim($_POST['name'] ?? '');
    $name = preg_replace('/\s+/', ' ', $name);

    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    $failureReason = null;

    if (empty($name) || empty($email) || empty($password) || empty($confirmPassword)) {

        $failureReason = "All fields are required.";
        $message = "All fields are required.";
        $messageType = "danger";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $failureReason = "Invalid email address.";
        $message = "Please enter a valid email address.";
        $messageType = "danger";

    } elseif (!is_valid_real_name($name)) {

        $failureReason = "Invalid name.";
        $message = "Please enter your first and last name using letters only.";
        $messageType = "danger";

    } elseif (is_disposable_email($email)) {

        $failureReason = "Disposable email.";
        $message = "Please use a permanent email address.";
        $messageType = "danger";

    } elseif (!email_domain_has_mx($email)) {

        $failureReason = "Email domain has no MX record.";
        $message = "Please enter a working email address.";
        $messageType = "danger";

    } elseif ($password !== $confirmPassword) {

        $failureReason = "Passwords do not match.";
        $message = "Passwords do not match.";
        $messageType = "danger";

    } elseif (strlen($password) < 6) {

        $failureReason = "Password too short.";
        $message = "Password must be at least 6 characters long.";
        $messageType = "danger";
    }

    if ($failureReason !== null) {

        log_signup_attempt($pdo, $name, $email, 0, $failureReason);

    } else {

        try {

            $stmt = $pdo->prepare("
                SELECT id
                FROM users
                WHERE email = ?
                LIMIT 1
            ");

            $stmt->execute([$email]);

            if ($stmt->fetch()) {

                log_signup_attempt($pdo, $name, $email, 0, 'Email already exists.');

                $message = "An account with that email already exists.";
                $messageType = "danger";

            } else {

                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $rawToken = bin2hex(random_bytes(32));
                $tokenHash = hash('sha256', $rawToken);
                $expiresAt = date('Y-m-d H:i:s', time() + (24 * 60 * 60));
                $verificationLink = 'https://whusup.com/verify_email.php?token=' . urlencode($rawToken);

                $pdo->beginTransaction();

                $stmt = $pdo->prepare("
                    INSERT INTO users (name, email, password, email_verified)
                    VALUES (?, ?, ?, 0)
                ");

                $stmt->execute([
                    $name,
                    $email,
                    $hashedPassword
                ]);

                $stmt = $pdo->prepare("
                    SELECT id
                    FROM users
                    WHERE email = ?
                    LIMIT 1
                ");

                $stmt->execute([$email]);
                $newUser = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$newUser || empty($newUser['id'])) {
                    throw new Exception('Could not create verification record.');
                }

                $stmt = $pdo->prepare("
                    INSERT INTO email_verifications (user_id, token_hash, expires_at)
                    VALUES (:user_id, :token_hash, :expires_at)
                    ON DUPLICATE KEY UPDATE
                        token_hash = VALUES(token_hash),
                        expires_at = VALUES(expires_at),
                        created_at = CURRENT_TIMESTAMP
                ");

                $stmt->execute([
                    ':user_id' => $newUser['id'],
                    ':token_hash' => $tokenHash,
                    ':expires_at' => $expiresAt
                ]);

                sendVerificationEmail($ses, $email, $name, $verificationLink);

                $pdo->commit();

                log_signup_attempt($pdo, $name, $email, 1, null);

                $_SESSION['success_message'] = "Registration successful. Please check your email to verify your account before logging in.";

                header("Location: login.php");
                exit();
            }

        } catch (Exception $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log('Signup failed: ' . $e->getMessage());

            log_signup_attempt($pdo, $name, $email, 0, 'Registration exception.');

            $message = "Registration failed. Please try again.";
            $messageType = "danger";
        }
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

.auth-input,
.auth-password-input {
    border-radius: 12px !important;
    border: 1px solid #d1d5db;
}

.auth-input:focus,
.auth-password-input:focus {
    border-color: #9ca3af;
    box-shadow: none;
}

.auth-password-wrap {
    position: relative;
}

.auth-toggle-btn {
    position: absolute;
    top: 50%;
    right: 14px;
    transform: translateY(-50%);
    border: none;
    background: transparent;
    color: #6b7280;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
    z-index: 5;
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

.auth-helper-text {
    color: #6b7280;
    font-size: 13px;
}

.auth-divider {
    text-align: center;
    margin: 22px 0;
    color: #9ca3af;
    font-size: 14px;
    position: relative;
}

.auth-divider::before,
.auth-divider::after {
    content: "";
    position: absolute;
    top: 50%;
    width: 42%;
    height: 1px;
    background: #e5e7eb;
}

.auth-divider::before {
    left: 0;
}

.auth-divider::after {
    right: 0;
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

            <div class="col-12 col-sm-10 col-md-8 col-lg-6 col-xl-5">

                <div class="card auth-card">

                    <div class="card-body">

                        <div class="text-center mb-4">

                            <h2 class="auth-title">
                                Create Account
                            </h2>

                            <p class="auth-subtitle">
                                Sign up to get started
                            </p>

                        </div>

                        <?php if (!empty($message)): ?>

                            <div class="alert alert-<?php echo htmlspecialchars($messageType, ENT_QUOTES, 'UTF-8'); ?> auth-alert">
                                <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
                            </div>

                        <?php endif; ?>

                        <form method="POST" action="">

                            <div class="form-floating mb-3">

                                <input
                                    type="text"
                                    name="name"
                                    id="registerName"
                                    class="form-control auth-input"
                                    placeholder="Full Name"
                                    value="<?= htmlspecialchars($_POST['name'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                    minlength="3"
                                    maxlength="60"
                                    pattern="[A-Za-z\s'\-]+"
                                    required
                                >

                                <label for="registerName">
                                    Full name
                                </label>

                            </div>

                            <div class="form-floating mb-3">

                                <input
                                    type="email"
                                    name="email"
                                    id="registerEmail"
                                    class="form-control auth-input"
                                    placeholder="name@example.com"
                                    value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                    required
                                >

                                <label for="registerEmail">
                                    Email address
                                </label>

                            </div>

                            <div class="mb-3">

                                <div class="form-floating auth-password-wrap">

                                    <input
                                        type="password"
                                        name="password"
                                        id="registerPassword"
                                        class="form-control auth-password-input"
                                        placeholder="Password"
                                        minlength="6"
                                        required
                                    >

                                    <label for="registerPassword">
                                        Password
                                    </label>

                                    <button
                                        type="button"
                                        class="auth-toggle-btn"
                                        data-toggle-password="registerPassword"
                                        aria-label="Show password"
                                    >
                                        Show
                                    </button>

                                </div>

                            </div>

                            <div class="mb-3">

                                <div class="form-floating auth-password-wrap">

                                    <input
                                        type="password"
                                        name="confirm_password"
                                        id="registerConfirmPassword"
                                        class="form-control auth-password-input"
                                        placeholder="Confirm Password"
                                        minlength="6"
                                        required
                                    >

                                    <label for="registerConfirmPassword">
                                        Confirm password
                                    </label>

                                    <button
                                        type="button"
                                        class="auth-toggle-btn"
                                        data-toggle-password="registerConfirmPassword"
                                        aria-label="Show password"
                                    >
                                        Show
                                    </button>

                                </div>

                            </div>

                            <div class="mb-4 auth-helper-text">
                                Use at least 6 characters for your password. Please enter your first and last name using letters only. You will need to verify your email before logging in.
                            </div>

                            <div class="d-grid">

                                <button type="submit" class="btn btn-primary auth-btn">
                                    Sign Up
                                </button>

                            </div>

                        </form>

                        <div class="auth-divider">
                            or
                        </div>

                        <div class="text-center">

                            <span class="text-muted">
                                Already have an account?
                            </span>

                            <a href="login.php" class="auth-link ms-1">
                                Login
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