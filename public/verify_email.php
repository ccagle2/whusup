<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';

$awsConfig = require __DIR__ . '/../config/aws.php';
$ses = $awsConfig['ses'] ?? null;

$message = '';
$success = false;
$showConfirmButton = false;
$isEmailChange = false;

function sendWelcomeEmail($ses, string $toEmail, string $name): void
{
    if (!$ses) {
        throw new Exception('SES is not configured.');
    }

    $fromEmail = 'noreply@whusup.com';
    $fromName = 'Whusup';

    $safeName = trim($name) !== '' ? trim($name) : 'there';

    $subject = 'Welcome to Whusup';

    $whusupUrl = 'https://whusup.com/dashboard.php';

    $textBody =
        "Hi {$safeName},\n\n" .
        "Welcome to Whusup! Your email has been verified and your account is ready.\n\n" .
        "Here are a few things you can do now:\n" .
        "- Choose your public display name\n" .
        "- Add a profile picture and bio\n" .
        "- Create your first post\n" .
        "- Find people to follow\n\n" .
        "Go to Whusup:\n" .
        $whusupUrl . "\n\n" .
        "Thanks for joining us,\n" .
        "Whusup";

    $htmlBody = '
        <div style="
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #111827;
            max-width: 560px;
            margin: 0 auto;
            padding: 24px;
        ">
            <h2 style="margin: 0 0 12px;">
                Welcome to Whusup
            </h2>

            <p>
                Hi ' . htmlspecialchars($safeName, ENT_QUOTES, 'UTF-8') . ',
            </p>

            <p>
                Your email has been verified and your Whusup account is ready.
            </p>

            <p>Here are a few things you can do now:</p>

            <ul style="padding-left: 22px;">
                <li>Choose your public display name</li>
                <li>Add a profile picture and bio</li>
                <li>Create your first post</li>
                <li>Find people to follow</li>
            </ul>

            <p style="margin: 26px 0;">
                <a
                    href="' . htmlspecialchars($whusupUrl, ENT_QUOTES, 'UTF-8') . '"
                    style="
                        background: #111827;
                        color: #ffffff;
                        padding: 12px 20px;
                        border-radius: 999px;
                        text-decoration: none;
                        font-weight: 700;
                        display: inline-block;
                    "
                >
                    Go to Whusup
                </a>
            </p>

            <p>
                Thanks for joining us,<br>
                Whusup
            </p>
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

if (empty($_SESSION['verify_email_csrf_token'])) {
    $_SESSION['verify_email_csrf_token'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['verify_email_csrf_token'];

$token = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? trim($_POST['token'] ?? '')
    : trim($_GET['token'] ?? '');

$tokenFormatIsValid = (
    $token !== ''
    && preg_match('/^[a-f0-9]{64}$/i', $token) === 1
);

if (!$tokenFormatIsValid) {
    $message = 'Invalid verification link.';
} else {

    $tokenHash = hash('sha256', $token);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        $submittedCsrfToken = $_POST['csrf_token'] ?? '';

        if (
            !is_string($submittedCsrfToken)
            || !hash_equals($csrfToken, $submittedCsrfToken)
        ) {
            $message = 'Your verification session has expired. Please open the verification link again.';
        } else {

            try {
                $pdo->beginTransaction();

                $stmt = $pdo->prepare("
                    SELECT
                        email_verifications.user_id,
                        email_verifications.expires_at,
                        email_verifications.verification_type,
                        users.name,
                        users.email,
                        users.pending_email,
                        users.email_verified
                    FROM email_verifications
                    JOIN users
                        ON users.id = email_verifications.user_id
                    WHERE email_verifications.token_hash = :token_hash
                    LIMIT 1
                    FOR UPDATE
                ");

                $stmt->execute([
                    ':token_hash' => $tokenHash
                ]);

                $verification = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$verification) {

                    $pdo->rollBack();
                    $message = 'This verification link has already been used or is no longer valid. If your account is already verified, you can log in.';

                } else {

                    $verificationType = $verification['verification_type'] ?? 'signup';
                    $isEmailChange = ($verificationType === 'email_change');

                    if (
                        empty($verification['expires_at'])
                        || strtotime($verification['expires_at']) <= time()
                    ) {

                        $deleteStmt = $pdo->prepare("
                            DELETE FROM email_verifications
                            WHERE user_id = :user_id
                        ");

                        $deleteStmt->execute([
                            ':user_id' => $verification['user_id']
                        ]);

                        $pdo->commit();

                        $message = 'This verification link has expired. Please request a new verification email.';

                    } elseif ($isEmailChange) {

                        $pendingEmail = strtolower(trim($verification['pending_email'] ?? ''));

                        if (
                            $pendingEmail === ''
                            || strlen($pendingEmail) > 255
                            || !filter_var($pendingEmail, FILTER_VALIDATE_EMAIL)
                        ) {

                            $deleteStmt = $pdo->prepare("
                                DELETE FROM email_verifications
                                WHERE user_id = :user_id
                            ");

                            $deleteStmt->execute([
                                ':user_id' => $verification['user_id']
                            ]);

                            $pdo->commit();
                            $message = 'This email-change request is no longer valid. Please return to My Account and request the change again.';

                        } else {

                            $duplicateStmt = $pdo->prepare("
                                SELECT id
                                FROM users
                                WHERE LOWER(email) = LOWER(:pending_email)
                                  AND id <> :user_id
                                LIMIT 1
                            ");

                            $duplicateStmt->execute([
                                ':pending_email' => $pendingEmail,
                                ':user_id' => $verification['user_id']
                            ]);

                            if ($duplicateStmt->fetch()) {

                                $deleteStmt = $pdo->prepare("
                                    DELETE FROM email_verifications
                                    WHERE user_id = :user_id
                                ");

                                $deleteStmt->execute([
                                    ':user_id' => $verification['user_id']
                                ]);

                                $clearPendingStmt = $pdo->prepare("
                                    UPDATE users
                                    SET pending_email = NULL
                                    WHERE id = :user_id
                                ");

                                $clearPendingStmt->execute([
                                    ':user_id' => $verification['user_id']
                                ]);

                                $pdo->commit();
                                $message = 'That email address is already associated with another account. Please return to My Account and choose a different email address.';

                            } else {

                                $updateStmt = $pdo->prepare("
                                    UPDATE users
                                    SET email = :pending_email,
                                        pending_email = NULL,
                                        email_verified = 1
                                    WHERE id = :user_id
                                ");

                                $updateStmt->execute([
                                    ':pending_email' => $pendingEmail,
                                    ':user_id' => $verification['user_id']
                                ]);

                                $deleteStmt = $pdo->prepare("
                                    DELETE FROM email_verifications
                                    WHERE user_id = :user_id
                                ");

                                $deleteStmt->execute([
                                    ':user_id' => $verification['user_id']
                                ]);

                                $pdo->commit();

                                unset($_SESSION['verify_email_csrf_token']);

                                $success = true;
                                $message = 'Your new email address has been verified and is now active on your account.';
                            }
                        }

                    } elseif ($verificationType === 'signup') {

                        if ((int) ($verification['email_verified'] ?? 0) === 1) {

                            $deleteStmt = $pdo->prepare("
                                DELETE FROM email_verifications
                                WHERE user_id = :user_id
                            ");

                            $deleteStmt->execute([
                                ':user_id' => $verification['user_id']
                            ]);

                            $pdo->commit();

                            $success = true;
                            $message = 'Your email is already verified. You can log in.';

                        } else {

                            $updateStmt = $pdo->prepare("
                                UPDATE users
                                SET email_verified = 1
                                WHERE id = :user_id
                                  AND email_verified = 0
                            ");

                            $updateStmt->execute([
                                ':user_id' => $verification['user_id']
                            ]);

                            $deleteStmt = $pdo->prepare("
                                DELETE FROM email_verifications
                                WHERE user_id = :user_id
                            ");

                            $deleteStmt->execute([
                                ':user_id' => $verification['user_id']
                            ]);

                            $pdo->commit();
                            
                            unset($_SESSION['verify_email_csrf_token']);
                            
                            /*
                             * The account is now permanently verified.
                             * Welcome-email delivery must never prevent successful verification.
                             */
                            try {
                                sendWelcomeEmail(
                                    $ses,
                                    (string) $verification['email'],
                                    (string) ($verification['name'] ?? '')
                                );
                            } catch (Exception $e) {
                                error_log('Welcome email failed: ' . $e->getMessage());
                            }
                            
                            $success = true;
                            $message = 'Your email has been verified. You can now log in.';
                        }

                    } else {

                        $pdo->rollBack();
                        $message = 'This verification request is not valid.';
                    }
                }

            } catch (PDOException $e) {

                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                error_log('Email verification failed: ' . $e->getMessage());
                $message = 'Verification failed. Please try again.';
            }
        }

    } else {

        try {
            $stmt = $pdo->prepare("
                SELECT
                    email_verifications.user_id,
                    email_verifications.expires_at,
                    email_verifications.verification_type,
                    users.name,
                    users.email,
                    users.pending_email,
                    users.email_verified
                FROM email_verifications
                JOIN users
                    ON users.id = email_verifications.user_id
                WHERE email_verifications.token_hash = :token_hash
                LIMIT 1
            ");

            $stmt->execute([
                ':token_hash' => $tokenHash
            ]);

            $verification = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$verification) {

                $message = 'This verification link has already been used or is no longer valid. If your account is already verified, you can log in.';

            } else {

                $verificationType = $verification['verification_type'] ?? 'signup';
                $isEmailChange = ($verificationType === 'email_change');

                if (
                    empty($verification['expires_at'])
                    || strtotime($verification['expires_at']) <= time()
                ) {

                    $message = 'This verification link has expired. Please request a new verification email.';

                } elseif ($isEmailChange) {

                    $pendingEmail = strtolower(trim($verification['pending_email'] ?? ''));

                    if (
                        $pendingEmail === ''
                        || strlen($pendingEmail) > 255
                        || !filter_var($pendingEmail, FILTER_VALIDATE_EMAIL)
                    ) {
                        $message = 'This email-change request is no longer valid. Please return to My Account and request the change again.';
                    } else {
                        $showConfirmButton = true;
                        $message = 'Your verification link is valid. Confirm below to change your account email to ' . $pendingEmail . '.';
                    }

                } elseif ($verificationType === 'signup') {

                    if ((int) ($verification['email_verified'] ?? 0) === 1) {
                        $success = true;
                        $message = 'Your email is already verified. You can log in.';
                    } else {
                        $showConfirmButton = true;
                        $message = 'Your verification link is valid. Confirm below to verify your email address.';
                    }

                } else {
                    $message = 'This verification request is not valid.';
                }
            }

        } catch (PDOException $e) {

            error_log('Email verification lookup failed: ' . $e->getMessage());
            $message = 'Verification failed. Please try again.';
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification | Whusup</title>
    <meta name="robots" content="noindex, nofollow">

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            background: #f2f4f8;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
        }

        .verify-card {
            width: 100%;
            max-width: 460px;
            padding: 32px;
            background: #ffffff;
            border-radius: 16px;
            text-align: center;
            box-shadow: 0 6px 20px rgba(0,0,0,0.08);
        }

        .verify-title {
            margin-bottom: 12px;
            color: #111827;
            font-size: 24px;
            font-weight: 700;
        }

        .verify-message {
            margin-bottom: 20px;
            padding: 12px;
            border-radius: 10px;
            color: <?= $success ? '#166534' : ($showConfirmButton ? '#374151' : '#991b1b') ?>;
            background: <?= $success ? '#dcfce7' : ($showConfirmButton ? '#f3f4f6' : '#fee2e2') ?>;
            line-height: 1.5;
        }

        .verify-actions {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 12px;
        }

        .verify-form {
            width: 100%;
            margin: 0;
        }

        .verify-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 150px;
            padding: 10px 22px;
            border: 0;
            border-radius: 999px;
            background: #111827;
            color: #ffffff;
            font: inherit;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
            transition: background-color 0.2s ease, transform 0.2s ease;
        }

        .verify-button:hover {
            background: #374151;
        }

        .verify-button:active {
            transform: scale(0.98);
        }

        .verify-button:focus-visible {
            outline: 3px solid rgba(59, 130, 246, 0.35);
            outline-offset: 3px;
        }

        .verify-secondary-link {
            color: #6b7280;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
        }

        .verify-secondary-link:hover {
            color: #111827;
            text-decoration: underline;
        }
    </style>
</head>
<body>

<div class="verify-card">

    <div class="verify-title">
        <?= $isEmailChange ? 'Confirm Email Change' : 'Email Verification' ?>
    </div>

    <div class="verify-message">
        <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
    </div>

    <div class="verify-actions">

        <?php if ($showConfirmButton): ?>

            <form
                method="POST"
                action="/verify_email.php"
                class="verify-form"
            >
                <input
                    type="hidden"
                    name="token"
                    value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>"
                >

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>"
                >

                <button
                    type="submit"
                    class="verify-button"
                >
                    <?= $isEmailChange ? 'Confirm Email Change' : 'Verify Email' ?>
                </button>
            </form>

            <a
                href="<?= $isEmailChange ? '/my_account.php' : '/login.php' ?>"
                class="verify-secondary-link"
            >
                <?= $isEmailChange ? 'Back to My Account' : 'Back to Login' ?>
            </a>

        <?php else: ?>

            <a
                href="<?= $isEmailChange ? '/my_account.php' : '/login.php' ?>"
                class="verify-button"
            >
                <?= $isEmailChange ? 'Go to My Account' : 'Go to Login' ?>
            </a>

        <?php endif; ?>

    </div>

</div>

</body>
</html>
