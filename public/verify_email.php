<?php

require_once __DIR__ . '/../config/database.php';

$message = "";
$success = false;

$token = trim($_GET['token'] ?? '');

if ($token === '') {

    $message = "Invalid verification link.";

} else {

    $tokenHash = hash('sha256', $token);

    try {

        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            SELECT 
                email_verifications.user_id,
                email_verifications.expires_at,
                users.email,
                users.email_verified,
                CASE
                    WHEN email_verifications.expires_at > NOW()
                    THEN 1
                    ELSE 0
                END AS token_is_valid
            FROM email_verifications
            JOIN users ON users.id = email_verifications.user_id
            WHERE email_verifications.token_hash = :token_hash
            LIMIT 1
        ");

        $stmt->execute([
            ':token_hash' => $tokenHash
        ]);

        $verification = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$verification) {

            $pdo->rollBack();

            $message = "This verification link has already been used or has expired. If your account is already verified, you can log in.";

        } elseif ((int)$verification['email_verified'] === 1) {

            $pdo->commit();

            $success = true;
            $message = "Your email is verified. You can log in.";

        } elseif ((int)$verification['token_is_valid'] !== 1) {

            $stmt = $pdo->prepare("
                DELETE FROM email_verifications
                WHERE user_id = :user_id
            ");

            $stmt->execute([
                ':user_id' => $verification['user_id']
            ]);

            $pdo->commit();

            $message = "This verification link has expired. Please request a new verification email.";

        } else {

            $stmt = $pdo->prepare("
                UPDATE users
                SET email_verified = 1
                WHERE id = :user_id
            ");

            $stmt->execute([
                ':user_id' => $verification['user_id']
            ]);

            $pdo->commit();

            $success = true;
            $message = "Your email has been verified. You can now log in.";
        }

    } catch (PDOException $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        $message = "Verification failed. Please try again.";
    }
}

?>

<!DOCTYPE html>
<html>
<head>
    <title>Email Verification</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: #f2f4f8;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
        }

        .verify-card {
            background: #ffffff;
            padding: 32px;
            border-radius: 16px;
            max-width: 460px;
            width: 90%;
            text-align: center;
            box-shadow: 0 6px 20px rgba(0,0,0,0.08);
        }

        .verify-title {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 12px;
            color: #111827;
        }

        .verify-message {
            color: <?= $success ? '#166534' : '#991b1b' ?>;
            background: <?= $success ? '#dcfce7' : '#fee2e2' ?>;
            padding: 12px;
            border-radius: 10px;
            margin-bottom: 20px;
            line-height: 1.5;
        }

        .verify-button {
            display: inline-block;
            background: #111827;
            color: #ffffff;
            padding: 10px 22px;
            border-radius: 999px;
            text-decoration: none;
            font-weight: 700;
        }

        .verify-button:hover {
            background: #374151;
        }
    </style>
</head>
<body>

<div class="verify-card">
    <div class="verify-title">
        Email Verification
    </div>

    <div class="verify-message">
        <?= htmlspecialchars($message) ?>
    </div>

    <a href="/login.php" class="verify-button">
        Go to Login
    </a>
</div>

</body>
</html>