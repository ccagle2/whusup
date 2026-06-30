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
        'message' => 'You must be logged in to delete comments.'
    ]);
    exit;
}

$comment_id = (int) ($_POST['comment_id'] ?? 0);

if ($comment_id <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid comment.'
    ]);
    exit;
}

try {
    $stmt = $pdo->prepare("
        DELETE FROM post_comments
        WHERE id = :comment_id
        AND user_id = :user_id
    ");

    $stmt->execute([
        ':comment_id' => $comment_id,
        ':user_id' => $user_id
    ]);

    if ($stmt->rowCount() === 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Comment not found or you do not have permission to delete it.'
        ]);
        exit;
    }

    echo json_encode([
        'success' => true,
        'comment_id' => $comment_id
    ]);
    exit;

} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Delete failed: ' . $e->getMessage()
    ]);
    exit;
}