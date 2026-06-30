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

$friend_id = $_POST['friend_id'] ?? '';

if ($friend_id === '') {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid user.'
    ]);
    exit;
}

try {

    $stmt = $pdo->prepare("
        DELETE FROM friendships
        WHERE user_id = :user_id
        AND friend_id = :friend_id
    ");

    $stmt->execute([
        ':user_id' => $user_id,
        ':friend_id' => $friend_id
    ]);

    echo json_encode([
        'success' => true,
        'friend_id' => $friend_id
    ]);

} catch (PDOException $e) {

    echo json_encode([
        'success' => false,
        'message' => 'Could not unfollow user.'
    ]);
}