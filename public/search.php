<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';

$awsConfig = require __DIR__ . '/../config/aws.php';

require_once __DIR__ . '/../includes/posts/post_helpers.php';
require_once __DIR__ . '/../includes/posts/post_queries.php';
require_once __DIR__ . '/../includes/posts/comment_tree.php';
require_once __DIR__ . '/../includes/posts/post_card.php';

$user_id = $_SESSION['user_id'] ?? null;
$is_logged_in = !empty($user_id);

$q = trim($_GET['q'] ?? '');

$pageTitle = $q !== ''
    ? "Search: " . cleanMetaText($q, 50) . " | Whusup"
    : "Search Whusup";

$pageDescription = $q !== ''
    ? "Search results for " . cleanMetaText($q, 80) . " on Whusup."
    : "Search posts and tags on Whusup.";

$canonicalUrl = "https://whusup.com/search.php" .
    ($q !== '' ? "?q=" . urlencode($q) : "");

$postData = loadSearchPostData($pdo, $q, $user_id, 50);

$recent_posts = $postData['posts'];
$comments_by_post = $postData['comments_by_post'];
$post_images_by_post = $postData['post_images_by_post'];

$redirect_url = "/search.php" .
    ($q !== '' ? "?q=" . urlencode($q) : "");

require __DIR__ . '/../includes/posts/delete_post_handler.php';

include '../includes/header.php';
?>

<title><?= htmlspecialchars($pageTitle) ?></title>
<meta name="description" content="<?= htmlspecialchars($pageDescription) ?>">
<link rel="canonical" href="<?= htmlspecialchars($canonicalUrl) ?>">

<?php
include '../includes/navbar.php';
include '../includes/posts/post_styles.php';
?>

<main class="recent-posts-wrapper">

    <h1 class="whusup-page-title">
        Search Whusup
    </h1>

    <form method="GET"
          action="/search.php"
          class="whusup-search-form">

        <input
            type="search"
            name="q"
            value="<?= htmlspecialchars($q) ?>"
            placeholder="Search posts or tags..."
            class="whusup-search-input">

    </form>

    <?php if ($q === ''): ?>

        <div class="no-posts">
            Enter a search term to find posts.
        </div>

    <?php elseif (empty($recent_posts)): ?>

        <div class="no-posts">
            No posts found for “<?= htmlspecialchars($q) ?>”.
        </div>

    <?php else: ?>

        <div id="recent-posts-list">

            <?= renderPostCards(
                $recent_posts,
                $comments_by_post,
                $post_images_by_post,
                $user_id,
                $is_logged_in,
                $redirect_url
            ) ?>

        </div>

    <?php endif; ?>

</main>

<div class="image-lightbox"
     id="imageLightbox"
     aria-hidden="true"
     data-current-index="0">

    <button type="button"
            class="image-lightbox-close"
            id="imageLightboxClose"
            aria-label="Close image">
        ×
    </button>

    <button type="button"
            class="image-lightbox-arrow image-lightbox-prev"
            id="imageLightboxPrev"
            aria-label="Previous image">
        ‹
    </button>

    <div class="image-lightbox-content">
        <img src=""
             alt="Expanded image"
             class="image-lightbox-image"
             id="imageLightboxImage">
    </div>

    <button type="button"
            class="image-lightbox-arrow image-lightbox-next"
            id="imageLightboxNext"
            aria-label="Next image">
        ›
    </button>

    <div class="image-lightbox-dots"
         id="imageLightboxDots"
         aria-label="Image position">
    </div>

</div>

<?php
include '../includes/posts/post_scripts.php';
include '../includes/footer.php';
?>