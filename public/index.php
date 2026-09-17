<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// If the user is already logged in, send them directly to their personalized feed.
if (!empty($_SESSION['user_id'])) {
    header('Location: /dashboard.php');
    exit;
}

include '../includes/header.php';
include '../includes/navbar.php';

include '../includes/home_guest_card.php';
include '../includes/posts/feed.php';

include '../includes/footer.php';
?>