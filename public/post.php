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

$post_id = isset($_GET['id'])
    ? (int) $_GET['id']
    : 0;

/*
 * Return a proper 404 response when the requested post ID is invalid.
 */
if ($post_id <= 0) {
    http_response_code(404);

    $pageTitle = 'Post Not Found | Whusup';
    $pageDescription = 'The requested Whusup post could not be found.';
    $canonicalUrl = 'https://whusup.com/';

    $ogTitle = $pageTitle;
    $ogDescription = $pageDescription;
    $ogUrl = $canonicalUrl;
    $ogType = 'website';

    $robotsMeta = 'noindex, follow';

    include __DIR__ . '/../includes/header.php';
    include __DIR__ . '/../includes/navbar.php';

    echo '<main class="recent-posts-wrapper">';
    echo '<h1>Post not found</h1>';
    echo '</main>';

    include __DIR__ . '/../includes/footer.php';
    exit;
}

/*
 * Load the post, its comments, user information, and gallery images.
 */
$postData = loadSinglePostData(
    $pdo,
    $post_id,
    $user_id
);

$recent_posts = $postData['posts'] ?? [];
$comments_by_post = $postData['comments_by_post'] ?? [];
$post_images_by_post = $postData['post_images_by_post'] ?? [];

/*
 * Return a proper 404 response when the post no longer exists.
 */
if (empty($recent_posts)) {
    http_response_code(404);

    $pageTitle = 'Post Not Found | Whusup';
    $pageDescription = 'The requested Whusup post could not be found.';
    $canonicalUrl = 'https://whusup.com/';

    $ogTitle = $pageTitle;
    $ogDescription = $pageDescription;
    $ogUrl = $canonicalUrl;
    $ogType = 'website';

    $robotsMeta = 'noindex, follow';

    include __DIR__ . '/../includes/header.php';
    include __DIR__ . '/../includes/navbar.php';

    echo '<main class="recent-posts-wrapper">';
    echo '<h1>Post not found</h1>';
    echo '</main>';

    include __DIR__ . '/../includes/footer.php';
    exit;
}

$post = $recent_posts[0];

$postTag = trim((string) ($post['tag'] ?? ''));
$postBody = trim((string) ($post['body'] ?? ''));
$postAuthor = trim((string) ($post['name'] ?? 'Whusup user'));

/*
 * Build the clean, permanent URL for this post.
 *
 * Example:
 * https://whusup.com/post/195/gilroy-gardens
 */
$postSlugSource = $postTag !== ''
    ? $postTag
    : $postBody;

$postSlug = makePostSlug($postSlugSource);

$canonicalUrl =
    'https://whusup.com/post/' .
    rawurlencode((string) $post_id) .
    '/' .
    rawurlencode($postSlug);

/*
 * Use the first gallery image for social previews.
 */
$imageKeys = $post_images_by_post[$post_id] ?? [];
$imageKeys = array_values(array_filter($imageKeys));

$firstImageUrl = !empty($imageKeys[0])
    ? getS3ImageUrl($imageKeys[0])
    : null;

/*
 * Build a clean search-engine description from the post text.
 */
$pageDescription = cleanMetaText($postBody, 180);

if ($pageDescription === '') {
    $pageDescription = $postTag !== ''
        ? 'View this ' . $postTag . ' post and join the conversation on Whusup.'
        : 'View this post and join the conversation on Whusup.';
}

/*
 * Use the tag prominently in the browser title and social preview.
 *
 * Example:
 * Gilroy Gardens on Whusup
 */
if ($postTag !== '') {
    $cleanTag = cleanMetaText($postTag, 60);

    $pageTitle = $cleanTag . ' on Whusup';
    $ogTitle = $cleanTag . ' on Whusup';

    $ogDescription =
        'Check out this ' .
        $cleanTag .
        ' post shared on Whusup. ' .
        $pageDescription;
} else {
    $postTitleText = cleanMetaText($postBody, 70);

    $pageTitle = $postTitleText !== ''
        ? $postTitleText . ' | Whusup'
        : 'Post on Whusup';

    $ogTitle = $postTitleText !== ''
        ? $postTitleText
        : 'Post on Whusup';

    $ogDescription = $pageDescription;
}

/*
 * Prevent social descriptions from becoming excessively long.
 */
$ogDescription = cleanMetaText($ogDescription, 220);

$ogUrl = $canonicalUrl;
$ogType = 'article';

$ogImage = $firstImageUrl;

$ogImageAlt = $postTag !== ''
    ? $postTag . ' post shared on Whusup'
    : 'Post shared on Whusup';

$twitterCard = !empty($ogImage)
    ? 'summary_large_image'
    : 'summary';

$twitterTitle = $ogTitle;
$twitterDescription = $ogDescription;
$twitterImage = $ogImage;

/*
 * Tell search engines that this is an indexable post page.
 */
$robotsMeta = 'index, follow';

/*
 * Prepare publication dates for structured data.
 */
$postPublishedDate = !empty($post['created_at'])
    ? date('c', strtotime($post['created_at']))
    : null;

/*
 * Use updated_at when the query provides it.
 * Otherwise, use the original creation date.
 */
$postModifiedSource = !empty($post['updated_at'])
    ? $post['updated_at']
    : ($post['created_at'] ?? null);

$postModifiedDate = !empty($postModifiedSource)
    ? date('c', strtotime($postModifiedSource))
    : $postPublishedDate;

/*
 * Build Schema.org structured data for this post.
 */
$structuredData = [
    '@context' => 'https://schema.org',
    '@type' => 'SocialMediaPosting',

    'headline' => $postTag !== ''
        ? cleanMetaText($postTag, 110)
        : cleanMetaText($postBody, 110),

    'articleBody' => cleanMetaText($postBody, 5000),

    'datePublished' => $postPublishedDate,
    'dateModified' => $postModifiedDate,

    'author' => [
        '@type' => 'Person',
        'name' => ucwords($postAuthor),
    ],

    'mainEntityOfPage' => [
        '@type' => 'WebPage',
        '@id' => $canonicalUrl,
    ],

    'url' => $canonicalUrl,

    'publisher' => [
        '@type' => 'Organization',
        'name' => 'Whusup',
        'url' => 'https://whusup.com',
    ],
];

if (!empty($firstImageUrl)) {
    $structuredData['image'] = [$firstImageUrl];
}

/*
 * After deleting a post from its individual post page,
 * return the user to the main Whusup feed.
 *
 * This no longer reads or accepts a redirect query parameter.
 */
$redirect_url = '/';

/*
 * Process post deletion before any HTML is sent.
 */
require __DIR__ . '/../includes/posts/delete_post_handler.php';

/*
 * header.php consumes the SEO and social-preview variables defined above.
 */
include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';
include __DIR__ . '/../includes/posts/post_styles.php';
?>

<script type="application/ld+json">
<?= json_encode(
    $structuredData,
    JSON_UNESCAPED_SLASHES |
    JSON_UNESCAPED_UNICODE |
    JSON_PRETTY_PRINT
) ?>
</script>

<main class="recent-posts-wrapper">
    <?= renderPostCards(
        $recent_posts,
        $comments_by_post,
        $post_images_by_post,
        $user_id,
        $is_logged_in
    ) ?>
</main>

<div
    class="image-lightbox"
    id="imageLightbox"
    aria-hidden="true"
    data-current-index="0"
>
    <button
        type="button"
        class="image-lightbox-close"
        id="imageLightboxClose"
        aria-label="Close image"
    >
        ×
    </button>

    <button
        type="button"
        class="image-lightbox-arrow image-lightbox-prev"
        id="imageLightboxPrev"
        aria-label="Previous image"
    >
        ‹
    </button>

    <div class="image-lightbox-content">
        <img
            src=""
            alt="Expanded image"
            class="image-lightbox-image"
            id="imageLightboxImage"
        >
    </div>

    <button
        type="button"
        class="image-lightbox-arrow image-lightbox-next"
        id="imageLightboxNext"
        aria-label="Next image"
    >
        ›
    </button>

    <div
        class="image-lightbox-dots"
        id="imageLightboxDots"
        aria-label="Image position"
    ></div>
</div>

<?php
include __DIR__ . '/../includes/posts/post_scripts.php';
include __DIR__ . '/../includes/footer.php';
?>