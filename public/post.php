<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';

$awsConfig = require __DIR__ . '/../config/aws.php';
$s3 = $awsConfig['s3'] ?? null;
$s3Bucket = $awsConfig['bucket'] ?? null;

require_once __DIR__ . '/../includes/posts/post_helpers.php';
require_once __DIR__ . '/../includes/posts/post_queries.php';
require_once __DIR__ . '/../includes/posts/comment_tree.php';
require_once __DIR__ . '/../includes/posts/post_card.php';

$user_id = $_SESSION['user_id'] ?? null;
$is_logged_in = !empty($user_id);

$post_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($post_id <= 0) {
    http_response_code(404);
    $pageTitle = "Post Not Found | Whusup";
    include '../includes/header.php';
    include '../includes/navbar.php';
    echo '<main class="recent-posts-wrapper"><h1>Post not found</h1></main>';
    include '../includes/footer.php';
    exit;
}

$postData = loadSinglePostData($pdo, $post_id, $user_id);

$recent_posts = $postData['posts'];
$comments_by_post = $postData['comments_by_post'];
$post_images_by_post = $postData['post_images_by_post'];

if (empty($recent_posts)) {
    http_response_code(404);
    $pageTitle = "Post Not Found | Whusup";
    include '../includes/header.php';
    include '../includes/navbar.php';
    echo '<main class="recent-posts-wrapper"><h1>Post not found</h1></main>';
    include '../includes/footer.php';
    exit;
}

$post = $recent_posts[0];

$imageKeys = $post_images_by_post[(int)$post['id']] ?? [];
$firstImageUrl = !empty($imageKeys[0]) ? getS3ImageUrl($imageKeys[0]) : null;

$pageDescription = cleanMetaText($post['body']);

$pageTitle = !empty($post['tag'])
    ? cleanMetaText($post['tag'], 50) . " | Whusup Post"
    : cleanMetaText($post['body'], 60) . " | Whusup";

$postSlug = makePostSlug(!empty($post['tag']) ? $post['tag'] : $post['body']);
$canonicalUrl = "https://whusup.com/post/" . urlencode($post['id']) . "/" . rawurlencode($postSlug);

$redirect_url = $_GET['redirect'] ?? '/';

if (!is_string($redirect_url) || $redirect_url === '' || str_starts_with($redirect_url, 'http')) {
    $redirect_url = '/';
}

require __DIR__ . '/../includes/posts/delete_post_handler.php';

include '../includes/header.php';
?>

<title><?= htmlspecialchars($pageTitle) ?></title>
<meta name="description" content="<?= htmlspecialchars($pageDescription) ?>">
<link rel="canonical" href="<?= htmlspecialchars($canonicalUrl) ?>">

<meta property="og:title" content="<?= htmlspecialchars($pageTitle) ?>">
<meta property="og:description" content="<?= htmlspecialchars($pageDescription) ?>">
<meta property="og:url" content="<?= htmlspecialchars($canonicalUrl) ?>">
<meta property="og:type" content="article">

<?php if (!empty($firstImageUrl)): ?>
    <meta property="og:image" content="<?= htmlspecialchars($firstImageUrl) ?>">
<?php endif; ?>

<script type="application/ld+json">
<?= json_encode([
    "@context" => "https://schema.org",
    "@type" => "SocialMediaPosting",
    "headline" => !empty($post['tag']) ? $post['tag'] : cleanMetaText($post['body'], 80),
    "articleBody" => cleanMetaText($post['body'], 5000),
    "datePublished" => date('c', strtotime($post['created_at'])),
    "dateModified" => date('c', strtotime($post['created_at'])),
    "author" => [
        "@type" => "Person",
        "name" => ucwords($post['name'])
    ],
    "mainEntityOfPage" => [
        "@type" => "WebPage",
        "@id" => $canonicalUrl
    ],
    "url" => $canonicalUrl,
    "image" => !empty($firstImageUrl) ? [$firstImageUrl] : null,
    "publisher" => [
        "@type" => "Organization",
        "name" => "Whusup",
        "url" => "https://whusup.com"
    ]
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?>
</script>

<?php
include '../includes/navbar.php';
include '../includes/posts/post_styles.php';
?>

<main class="recent-posts-wrapper">
    <?= renderPostCards(
        $recent_posts,
        $comments_by_post,
        $post_images_by_post,
        $user_id,
        $is_logged_in,
        $redirect_url
    ) ?>
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