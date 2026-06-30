<?php
session_start();

header('Content-Type: application/json');

require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'logged_in' => false
    ]);
    exit;
}

$user_id = $_SESSION['user_id'];
$post_id = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;

if ($post_id <= 0) {
    echo json_encode(['success' => false]);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT id
        FROM post_likes
        WHERE post_id = :post_id
        AND user_id = :user_id
        LIMIT 1
    ");

    $stmt->execute([
        ':post_id' => $post_id,
        ':user_id' => $user_id
    ]);

    $existing_like = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing_like) {
        $stmt = $pdo->prepare("
            DELETE FROM post_likes
            WHERE post_id = :post_id
            AND user_id = :user_id
        ");

        $stmt->execute([
            ':post_id' => $post_id,
            ':user_id' => $user_id
        ]);

        $liked = false;
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO post_likes (post_id, user_id)
            VALUES (:post_id, :user_id)
        ");

        $stmt->execute([
            ':post_id' => $post_id,
            ':user_id' => $user_id
        ]);

        $liked = true;
    }

    $stmt = $pdo->prepare("
        SELECT COUNT(*) AS like_count
        FROM post_likes
        WHERE post_id = :post_id
    ");

    $stmt->execute([
        ':post_id' => $post_id
    ]);

    $count = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'logged_in' => true,
        'liked' => $liked,
        'like_count' => (int) $count['like_count']
    ]);

} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}