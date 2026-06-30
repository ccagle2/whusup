<?php
session_start();

require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'] ?? null;
$comment_id = isset($_POST['comment_id']) ? (int) $_POST['comment_id'] : 0;

if (!$user_id || $comment_id <= 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request.'
    ]);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT id
        FROM comment_likes
        WHERE comment_id = :comment_id
        AND user_id = :user_id
        LIMIT 1
    ");

    $stmt->execute([
        ':comment_id' => $comment_id,
        ':user_id' => $user_id
    ]);

    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        $stmt = $pdo->prepare("
            DELETE FROM comment_likes
            WHERE comment_id = :comment_id
            AND user_id = :user_id
        ");

        $stmt->execute([
            ':comment_id' => $comment_id,
            ':user_id' => $user_id
        ]);

        $liked = false;
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO comment_likes (comment_id, user_id)
            VALUES (:comment_id, :user_id)
        ");

        $stmt->execute([
            ':comment_id' => $comment_id,
            ':user_id' => $user_id
        ]);

        $liked = true;
    }

    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM comment_likes 
        WHERE comment_id = :comment_id
    ");

    $stmt->execute([
        ':comment_id' => $comment_id
    ]);

    echo json_encode([
        'success' => true,
        'liked' => $liked,
        'like_count' => (int) $stmt->fetchColumn()
    ]);
    exit;

} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
    exit;
}