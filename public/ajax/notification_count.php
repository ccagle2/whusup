<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

require_once __DIR__ . '/../../config/database.php';

if (empty($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'count' => 0,
        'message' => 'Not logged in.'
    ]);
    exit;
}

$user_id = $_SESSION['user_id'];

try {
    $stmt = $pdo->prepare("
        SELECT COALESCE(last_read_at, '1970-01-01 00:00:00')
        FROM user_notification_reads
        WHERE user_id = :user_id
        LIMIT 1
    ");

    $stmt->execute([
        ':user_id' => $user_id
    ]);

    $lastReadAt = $stmt->fetchColumn();

    if (!$lastReadAt) {
        $lastReadAt = '1970-01-01 00:00:00';
    }

    $stmt = $pdo->prepare("
        SELECT
            (
                SELECT COUNT(*)
                FROM post_likes pl
                JOIN posts p ON p.id = pl.post_id
                WHERE p.user_id = :user_id_likes_owner
                AND pl.user_id != :user_id_likes_actor
                AND pl.created_at > :last_read_likes
            )
            +
            (
                SELECT COUNT(*)
                FROM post_comments pc
                JOIN posts p ON p.id = pc.post_id
                WHERE p.user_id = :user_id_comments_owner
                AND pc.user_id != :user_id_comments_actor
                AND pc.created_at > :last_read_comments
            )
            +
            (
                SELECT COUNT(*)
                FROM friendships f
                WHERE f.friend_id = :user_id_followed
                AND f.user_id != :user_id_follower
                AND f.status = 'accepted'
                AND f.created_at > :last_read_follows
            )
        AS notification_count
    ");

    $stmt->execute([
        ':user_id_likes_owner' => $user_id,
        ':user_id_likes_actor' => $user_id,
        ':last_read_likes' => $lastReadAt,

        ':user_id_comments_owner' => $user_id,
        ':user_id_comments_actor' => $user_id,
        ':last_read_comments' => $lastReadAt,

        ':user_id_followed' => $user_id,
        ':user_id_follower' => $user_id,
        ':last_read_follows' => $lastReadAt
    ]);

    $count = (int) $stmt->fetchColumn();

    echo json_encode([
        'success' => true,
        'count' => $count
    ]);
    exit;

} catch (PDOException $e) {
    error_log('Notification count failed: ' . $e->getMessage());

    echo json_encode([
        'success' => false,
        'count' => 0,
        'message' => 'Could not load notification count.'
    ]);
    exit;
}