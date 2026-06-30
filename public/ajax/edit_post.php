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
        'message' => 'You must be logged in to edit posts.'
    ]);
    exit;
}

$post_id = (int) ($_POST['post_id'] ?? 0);
$post_body = trim($_POST['post_body'] ?? '');

if ($post_id <= 0 || $post_body === '') {
    echo json_encode([
        'success' => false,
        'message' => 'Post cannot be empty.'
    ]);
    exit;
}

try {
    $stmt = $pdo->prepare("
        UPDATE posts
        SET body = :body
        WHERE id = :post_id
        AND user_id = :user_id
    ");

    $stmt->execute([
        ':body' => $post_body,
        ':post_id' => $post_id,
        ':user_id' => $user_id
    ]);

    echo json_encode([
        'success' => true,
        'post_id' => $post_id,
        'post_body' => htmlspecialchars($post_body, ENT_QUOTES, 'UTF-8')
    ]);
    exit;

} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Edit post failed.'
    ]);
    exit;
}