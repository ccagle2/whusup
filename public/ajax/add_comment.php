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
        'message' => 'You must be logged in to comment.'
    ]);
    exit;
}

$post_id = (int) ($_POST['post_id'] ?? 0);
$comment_body = trim($_POST['comment_body'] ?? '');

$parent_comment_id = isset($_POST['parent_comment_id']) && $_POST['parent_comment_id'] !== ''
    ? (int) $_POST['parent_comment_id']
    : null;

if ($post_id <= 0 || $comment_body === '') {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid comment.'
    ]);
    exit;
}

try {
    if ($parent_comment_id !== null) {
        $stmt = $pdo->prepare("
            SELECT id
            FROM post_comments
            WHERE id = :parent_comment_id
            AND post_id = :post_id
            LIMIT 1
        ");

        $stmt->execute([
            ':parent_comment_id' => $parent_comment_id,
            ':post_id' => $post_id
        ]);

        if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
            echo json_encode([
                'success' => false,
                'message' => 'Parent comment not found.'
            ]);
            exit;
        }
    }

    $stmt = $pdo->prepare("
        INSERT INTO post_comments 
            (post_id, user_id, parent_comment_id, comment_body)
        VALUES 
            (:post_id, :user_id, :parent_comment_id, :comment_body)
    ");

    $stmt->execute([
        ':post_id' => $post_id,
        ':user_id' => $user_id,
        ':parent_comment_id' => $parent_comment_id,
        ':comment_body' => $comment_body
    ]);

    $comment_id = $pdo->lastInsertId();

    $stmt = $pdo->prepare("
        SELECT name
        FROM users
        WHERE id = :user_id
        LIMIT 1
    ");

    $stmt->execute([
        ':user_id' => $user_id
    ]);

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'comment_id' => $comment_id,
        'post_id' => $post_id,
        'parent_comment_id' => $parent_comment_id,
        'comment_body' => htmlspecialchars($comment_body, ENT_QUOTES, 'UTF-8'),
        'name' => htmlspecialchars(ucwords($user['name'] ?? 'User'), ENT_QUOTES, 'UTF-8'),
        'created_at' => 'Just now'
    ]);
    exit;

} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Comment failed: ' . $e->getMessage()
    ]);
    exit;
}