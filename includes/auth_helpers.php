<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';

function find_user_by_email(PDO $pdo, string $email): ?array
{
    $stmt = $pdo->prepare("SELECT id, name, email, password FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    return $user ?: null;
}

function invalidate_existing_reset_tokens(PDO $pdo, int $userId): void
{
    $stmt = $pdo->prepare("
        UPDATE password_resets
        SET used_at = NOW()
        WHERE user_id = ?
          AND used_at IS NULL
    ");
    $stmt->execute([$userId]);
}

function create_password_reset_token(PDO $pdo, int $userId): string
{
    invalidate_existing_reset_tokens($pdo, $userId);

    $rawToken = bin2hex(random_bytes(32));
    $tokenHash = password_hash($rawToken, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare("
        INSERT INTO password_resets (user_id, token_hash, expires_at)
        VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? MINUTE))
    ");
    $stmt->execute([$userId, $tokenHash, RESET_TOKEN_EXPIRY_MINUTES]);

    return $rawToken;
}

function build_password_reset_link(string $rawToken): string
{
    return APP_URL . '/reset-password.php?token=' . urlencode($rawToken);
}

function find_valid_reset_record_by_token(PDO $pdo, string $rawToken): ?array
{
    $stmt = $pdo->prepare("
        SELECT pr.id, pr.user_id, pr.token_hash, pr.expires_at, pr.used_at, u.email, u.name
        FROM password_resets pr
        INNER JOIN users u ON u.id = pr.user_id
        WHERE pr.used_at IS NULL
          AND pr.expires_at > NOW()
        ORDER BY pr.id DESC
    ");
    $stmt->execute();
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($records as $record) {
        if (password_verify($rawToken, $record['token_hash'])) {
            return $record;
        }
    }

    return null;
}

function mark_reset_token_used(PDO $pdo, int $resetId): void
{
    $stmt = $pdo->prepare("
        UPDATE password_resets
        SET used_at = NOW()
        WHERE id = ?
          AND used_at IS NULL
    ");
    $stmt->execute([$resetId]);
}

function update_user_password(PDO $pdo, int $userId, string $newPassword): void
{
    $newHash = password_hash($newPassword, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare("
        UPDATE users
        SET password = ?
        WHERE id = ?
    ");
    $stmt->execute([$newHash, $userId]);
}

function cleanup_expired_reset_tokens(PDO $pdo): void
{
    $stmt = $pdo->prepare("
        DELETE FROM password_resets
        WHERE (used_at IS NOT NULL) OR (expires_at <= NOW())
    ");
    $stmt->execute();
}