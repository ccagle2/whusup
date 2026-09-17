<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

require_once __DIR__ . '/../../config/database.php';

$user_id = $_SESSION['user_id'] ?? null;

if (empty($user_id)) {
    echo json_encode([
        'success' => false,
        'message' => 'You must be logged in.'
    ]);
    exit;
}

$friend_id = trim($_POST['friend_id'] ?? '');

if ($friend_id === '' || $friend_id === $user_id) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid user.'
    ]);
    exit;
}

try {

    /*
     * Add the follow relationship.
     *
     * INSERT IGNORE preserves the behavior of the existing endpoint:
     * if the relationship already exists, no duplicate row is created.
     */
    $stmt = $pdo->prepare("
        INSERT IGNORE INTO friendships
            (user_id, friend_id, status)
        VALUES
            (:user_id, :friend_id, 'accepted')
    ");

    $stmt->execute([
        ':user_id' => $user_id,
        ':friend_id' => $friend_id
    ]);

    /*
     * Get the target user's current follower count directly from
     * the database after the follow operation.
     *
     * This uses the same follower-count logic as manage_friends.php:
     * only accepted follows from verified users are counted.
     */
    $countStmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM friendships f
        JOIN users follower_users
            ON follower_users.id = f.user_id
        WHERE f.friend_id = :friend_id
        AND f.status = 'accepted'
        AND follower_users.email_verified = 1
    ");

    $countStmt->execute([
        ':friend_id' => $friend_id
    ]);

    $follower_count = (int) $countStmt->fetchColumn();

    echo json_encode([
        'success' => true,
        'friend_id' => $friend_id,
        'follower_count' => $follower_count
    ]);

} catch (PDOException $e) {

    error_log('Follow friend failed: ' . $e->getMessage());

    echo json_encode([
        'success' => false,
        'message' => 'Could not follow user.'
    ]);
}

exit;