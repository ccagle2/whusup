<?php
session_start();

require_once '../config/database.php';

$awsConfig = require __DIR__ . '/../config/aws.php';
$ses = $awsConfig['ses'];

$message = '';
$messageType = '';

/*
 * Lightweight signup anti-automation settings.
 *
 * These are intentionally conservative so normal users are unlikely
 * to encounter them while automated bursts are slowed or rejected.
 */
const SIGNUP_MIN_FORM_SECONDS = 3;
const SIGNUP_IP_LIMIT_15_MINUTES = 10;
const SIGNUP_IP_LIMIT_24_HOURS = 30;
const SIGNUP_EMAIL_LIMIT_60_MINUTES = 5;

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

function signup_rate_limit_reason(
    PDO $pdo,
    ?string $ipAddress,
    string $email
): ?string {

    try {

        if (!empty($ipAddress)) {

            $stmt = $pdo->prepare("
                SELECT
                    SUM(created_at >= (NOW() - INTERVAL 15 MINUTE)) AS attempts_15m,
                    SUM(created_at >= (NOW() - INTERVAL 24 HOUR)) AS attempts_24h
                FROM signup_log
                WHERE ip_address = :ip_address
                  AND created_at >= (NOW() - INTERVAL 24 HOUR)
            ");

            $stmt->execute([
                ':ip_address' => $ipAddress
            ]);

            $counts = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

            $attempts15m = (int) ($counts['attempts_15m'] ?? 0);
            $attempts24h = (int) ($counts['attempts_24h'] ?? 0);

            if ($attempts15m >= SIGNUP_IP_LIMIT_15_MINUTES) {
                return 'Too many signup attempts from this IP in 15 minutes.';
            }

            if ($attempts24h >= SIGNUP_IP_LIMIT_24_HOURS) {
                return 'Too many signup attempts from this IP in 24 hours.';
            }
        }

        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {

            $stmt = $pdo->prepare("
                SELECT COUNT(*)
                FROM signup_log
                WHERE email = :email
                  AND created_at >= (NOW() - INTERVAL 60 MINUTE)
            ");

            $stmt->execute([
                ':email' => $email
            ]);

            $emailAttempts = (int) $stmt->fetchColumn();

            if ($emailAttempts >= SIGNUP_EMAIL_LIMIT_60_MINUTES) {
                return 'Too many signup attempts for this email address.';
            }
        }

    } catch (PDOException $e) {

        /*
         * A logging/rate-limit lookup failure should not take signup offline.
         * Record it for server review and let normal validation continue.
         */
        error_log('Signup rate-limit lookup failed: ' . $e->getMessage());
    }

    return null;
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

/*
 * Generate an initial public display name from the verified signup name.
 *
 * display_name is UNIQUE in user_profiles. If the person's full name is
 * already in use, append a simple numeric suffix while keeping the value
 * within the 100-character application/database limit.
 */
function generate_unique_display_name(PDO $pdo, string $name): string {
    $baseName = trim(preg_replace('/\s+/', ' ', $name));

    if ($baseName === '') {
        throw new Exception('Could not generate display name.');
    }

    if (function_exists('mb_substr')) {
        $baseName = mb_substr($baseName, 0, 100, 'UTF-8');
    } else {
        $baseName = substr($baseName, 0, 100);
    }

    $candidate = $baseName;
    $suffixNumber = 2;

    $stmt = $pdo->prepare("
        SELECT 1
        FROM user_profiles
        WHERE display_name = :display_name
        LIMIT 1
    ");

    while (true) {
        $stmt->execute([
            ':display_name' => $candidate
        ]);

        if (!$stmt->fetchColumn()) {
            return $candidate;
        }

        $suffix = ' ' . $suffixNumber;

        $suffixLength = function_exists('mb_strlen')
            ? mb_strlen($suffix, 'UTF-8')
            : strlen($suffix);

        $baseLimit = 100 - $suffixLength;

        if (function_exists('mb_substr')) {
            $candidateBase = mb_substr($baseName, 0, $baseLimit, 'UTF-8');
        } else {
            $candidateBase = substr($baseName, 0, $baseLimit);
        }

        $candidate = rtrim($candidateBase) . $suffix;
        $suffixNumber++;

        if ($suffixNumber > 100000) {
            throw new Exception('Could not generate a unique display name.');
        }
    }
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

/*
 * A valid signup submission must first load this page so it can receive
 * a session-bound CSRF token and form-start timestamp.
 */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    $_SESSION['signup_csrf_token'] = bin2hex(random_bytes(32));
    $_SESSION['signup_form_started_at'] = time();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name = trim($_POST['name'] ?? '');
    $name = preg_replace('/\s+/', ' ', $name);

    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    /*
     * Anti-automation fields.
     */
    $submittedCsrfToken = $_POST['csrf_token'] ?? '';
    $honeypotValue = trim($_POST['website'] ?? '');

    $sessionCsrfToken = $_SESSION['signup_csrf_token'] ?? '';
    $formStartedAt = (int) ($_SESSION['signup_form_started_at'] ?? 0);

    $elapsedFormSeconds = $formStartedAt > 0
        ? time() - $formStartedAt
        : 0;

    $clientIpAddress = get_client_ip_address();

    $failureReason = null;

    /*
     * Validate anti-automation controls before performing DNS or account
     * creation work. Messages shown to users intentionally do not disclose
     * which bot signal was triggered.
     */
    if (
        !is_string($submittedCsrfToken)
        || $sessionCsrfToken === ''
        || !hash_equals($sessionCsrfToken, $submittedCsrfToken)
    ) {

        $failureReason = "Invalid signup CSRF token.";
        $message = "Your signup session expired. Please refresh the page and try again.";
        $messageType = "danger";

    } elseif ($honeypotValue !== '') {

        $failureReason = "Signup honeypot triggered.";
        $message = "We could not complete your registration. Please refresh the page and try again.";
        $messageType = "danger";

    } elseif (
        $formStartedAt <= 0
        || $elapsedFormSeconds < SIGNUP_MIN_FORM_SECONDS
    ) {

        $failureReason = "Signup submitted too quickly.";
        $message = "We could not complete your registration. Please wait a moment and try again.";
        $messageType = "danger";

    } elseif (
        ($rateLimitReason = signup_rate_limit_reason(
            $pdo,
            $clientIpAddress,
            $email
        )) !== null
    ) {

        $failureReason = $rateLimitReason;
        $message = "Too many signup attempts. Please wait and try again later.";
        $messageType = "danger";

    } elseif (empty($name) || empty($email) || empty($password) || empty($confirmPassword)) {

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
                    throw new Exception('Could not create user account.');
                }

                /*
                 * Every Whusup user must have exactly one matching profile.
                 *
                 * The signup form currently collects one full legal/account name.
                 * Keep users.name authoritative and initialize the structured
                 * profile fields conservatively as:
                 *   first_name = first name token
                 *   last_name  = everything after the first token
                 *
                 * The user can review/correct these later in My Account.
                 */
                $nameParts = preg_split('/\s+/', $name, 2, PREG_SPLIT_NO_EMPTY);

                $firstName = trim($nameParts[0] ?? '');
                $lastName = trim($nameParts[1] ?? '');

                if ($firstName === '' || $lastName === '') {
                    throw new Exception('Could not derive first and last name for profile.');
                }

                $initialDisplayName = generate_unique_display_name($pdo, $name);

                $stmt = $pdo->prepare("
                    INSERT INTO user_profiles (
                        user_id,
                        first_name,
                        last_name,
                        display_name,
                        is_private,
                        allow_email_notifications
                    )
                    VALUES (
                        :user_id,
                        :first_name,
                        :last_name,
                        :display_name,
                        0,
                        1
                    )
                ");

                $stmt->execute([
                    ':user_id' => $newUser['id'],
                    ':first_name' => $firstName,
                    ':last_name' => $lastName,
                    ':display_name' => $initialDisplayName
                ]);

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

/*
 * If this POST did not redirect after successful registration, issue a fresh
 * token/timestamp for the corrected form that is about to be rendered.
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $_SESSION['signup_csrf_token'] = bin2hex(random_bytes(32));
    $_SESSION['signup_form_started_at'] = time();
}

$signupCsrfToken = $_SESSION['signup_csrf_token'] ?? '';

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

.signup-honeypot {
    position: absolute !important;
    left: -10000px !important;
    top: auto !important;
    width: 1px !important;
    height: 1px !important;
    overflow: hidden !important;
    opacity: 0 !important;
    pointer-events: none !important;
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

                            <input
                                type="hidden"
                                name="csrf_token"
                                value="<?= htmlspecialchars($signupCsrfToken, ENT_QUOTES, 'UTF-8') ?>"
                            >

                            <div
                                class="signup-honeypot"
                                aria-hidden="true"
                            >
                                <label for="signupWebsite">
                                    Website
                                </label>

                                <input
                                    type="text"
                                    name="website"
                                    id="signupWebsite"
                                    value=""
                                    tabindex="-1"
                                    autocomplete="off"
                                >
                            </div>

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
                                    Full Name
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