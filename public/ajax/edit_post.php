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
$post_title = trim($_POST['title'] ?? '');
$post_body = trim($_POST['post_body'] ?? '');

if ($post_id <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid post.'
    ]);
    exit;
}

if ($post_body === '') {
    echo json_encode([
        'success' => false,
        'message' => 'Post cannot be empty.'
    ]);
    exit;
}

if (mb_strlen($post_title, 'UTF-8') > 200) {
    echo json_encode([
        'success' => false,
        'message' => 'Title cannot exceed 200 characters.'
    ]);
    exit;
}

if (mb_strlen($post_body, 'UTF-8') > 2000) {
    echo json_encode([
        'success' => false,
        'message' => 'Post cannot exceed 2000 characters.'
    ]);
    exit;
}

try {
    $stmt = $pdo->prepare("
        UPDATE posts
        SET title = :title,
            body = :body
        WHERE id = :post_id
        AND user_id = :user_id
    ");

    $stmt->execute([
        ':title' => $post_title !== '' ? $post_title : null,
        ':body' => $post_body,
        ':post_id' => $post_id,
        ':user_id' => $user_id
    ]);

    echo json_encode([
        'success' => true,
        'post_id' => $post_id,
        'title' => $post_title,
        'post_body' => $post_body
    ]);
    exit;

} catch (PDOException $e) {
    error_log('Edit post failed: ' . $e->getMessage());

    echo json_encode([
        'success' => false,
        'message' => 'Edit post failed.'
    ]);
    exit;
}
