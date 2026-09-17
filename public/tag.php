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

$tagInput = trim($_GET['tag'] ?? '');

if ($tagInput === '') {
    http_response_code(404);
    $pageTitle = "Tag Not Found | Whusup";
    include '../includes/header.php';
    include '../includes/navbar.php';
    echo '<main class="recent-posts-wrapper"><h1>Tag not found</h1></main>';
    include '../includes/footer.php';
    exit;
}

$tag = findRealTagFromSlug($pdo, $tagInput);

if ($tag === null) {
    http_response_code(404);
    $pageTitle = "Tag Not Found | Whusup";
    include '../includes/header.php';
    include '../includes/navbar.php';
    echo '<main class="recent-posts-wrapper"><h1>Tag not found</h1></main>';
    include '../includes/footer.php';
    exit;
}

$tagSlug = makePostSlug($tag);

$postData = loadTagPostData($pdo, $tag, $user_id, 30);

$recent_posts = $postData['posts'];
$comments_by_post = $postData['comments_by_post'];
$post_images_by_post = $postData['post_images_by_post'];

$pageTitle = cleanMetaText($tag, 50) . " Posts | Whusup";
$pageDescription = "Posts tagged " . cleanMetaText($tag, 80) . " on Whusup.";
$canonicalUrl = "https://whusup.com/tag/" . rawurlencode($tagSlug);

$redirect_url = "/tag/" . rawurlencode($tagSlug);

require __DIR__ . '/../includes/posts/delete_post_handler.php';

include '../includes/header.php';
?>

<title><?= htmlspecialchars($pageTitle) ?></title>
<meta name="description" content="<?= htmlspecialchars($pageDescription) ?>">
<link rel="canonical" href="<?= htmlspecialchars($canonicalUrl) ?>">

<?php include '../includes/navbar.php'; ?>
<?php include '../includes/posts/post_styles.php'; ?>

<main class="recent-posts-wrapper">

    <h1 class="whusup-page-title">
        Posts tagged “<?= htmlspecialchars($tag) ?>”
    </h1>

    <?php if (empty($recent_posts)): ?>

        <div class="no-posts">
            No posts found for this tag.
        </div>

    <?php else: ?>

        <div id="recent-posts-list">
            <?= renderPostCards(
                $recent_posts,
                $comments_by_post,
                $post_images_by_post,
                $user_id,
                $is_logged_in
            ) ?>
        </div>

    <?php endif; ?>

</main>

<div class="image-lightbox" id="imageLightbox" aria-hidden="true" data-current-index="0">
    <button type="button" class="image-lightbox-close" id="imageLightboxClose" aria-label="Close image">×</button>
    <button type="button" class="image-lightbox-arrow image-lightbox-prev" id="imageLightboxPrev" aria-label="Previous image">‹</button>

    <div class="image-lightbox-content">
        <img src="" alt="Expanded image" class="image-lightbox-image" id="imageLightboxImage">
    </div>

    <button type="button" class="image-lightbox-arrow image-lightbox-next" id="imageLightboxNext" aria-label="Next image">›</button>
    <div class="image-lightbox-dots" id="imageLightboxDots" aria-label="Image position"></div>
</div>

<?php
include '../includes/posts/post_scripts.php';
include '../includes/footer.php';
?>