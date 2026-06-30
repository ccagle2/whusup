<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

define('RECENT_POSTS_AJAX_REQUEST', true);

try {
    ob_start();
    require __DIR__ . '/../../includes/recent_posts.php';
    $html = ob_get_clean();

    echo json_encode([
        'success' => true,
        'html' => $html,
        'has_more' => !empty($recent_posts_has_more),
        'next_offset' => isset($recent_posts_next_offset) ? (int) $recent_posts_next_offset : 0,
    ]);
} catch (Throwable $e) {
    if (ob_get_level() > 0) {
        ob_end_clean();
    }

    error_log('load_more_posts.php failed: ' . $e->getMessage());

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Could not load more posts.'
    ]);
}
