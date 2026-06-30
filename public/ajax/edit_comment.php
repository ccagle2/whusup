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
        'message' => 'You must be logged in to edit comments.'
    ]);
    exit;
}

$comment_id = (int) ($_POST['comment_id'] ?? 0);
$comment_body = trim($_POST['comment_body'] ?? '');

if ($comment_id <= 0 || $comment_body === '') {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid comment.'
    ]);
    exit;
}

try {
    $stmt = $pdo->prepare("
        UPDATE post_comments
        SET comment_body = :comment_body
        WHERE id = :comment_id
        AND user_id = :user_id
    ");

    $stmt->execute([
        ':comment_body' => $comment_body,
        ':comment_id' => $comment_id,
        ':user_id' => $user_id
    ]);

    if ($stmt->rowCount() === 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Comment not found or you do not have permission to edit it.'
        ]);
        exit;
    }

    echo json_encode([
        'success' => true,
        'comment_id' => $comment_id,
        'comment_body' => htmlspecialchars($comment_body, ENT_QUOTES, 'UTF-8')
    ]);
    exit;

} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Edit failed: ' . $e->getMessage()
    ]);
    exit;
}