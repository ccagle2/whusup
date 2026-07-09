<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/database.php';

$awsConfig = require __DIR__ . '/../../config/aws.php';
$s3 = $awsConfig['s3'] ?? null;
$s3Bucket = $awsConfig['bucket'] ?? null;

require_once __DIR__ . '/post_helpers.php';
require_once __DIR__ . '/comment_tree.php';
require_once __DIR__ . '/post_card.php';

$user_id = $_SESSION['user_id'] ?? null;
$is_logged_in = !empty($user_id);
$message = "";

$recent_posts_ajax_request = defined('RECENT_POSTS_AJAX_REQUEST') && RECENT_POSTS_AJAX_REQUEST;

$posts_per_page = 10;
$posts_offset = max(0, (int) ($_GET['offset'] ?? 0));
$posts_fetch_limit = $posts_per_page + 1;

$recent_posts_has_more = false;
$recent_posts_next_offset = $posts_offset;

$sort = $_GET['sort'] ?? 'recent';
$feed_filter = $_GET['filter'] ?? 'all';
$tag_search = trim($_GET['tag'] ?? '');

if (!in_array($sort, ['recent', 'popular'], true)) {
    $sort = 'recent';
}

if (!in_array($feed_filter, ['all', 'following', 'my_posts'], true)) {
    $feed_filter = 'all';
}

if (!$is_logged_in) {
    $feed_filter = 'all';
}

$redirect_url = currentPostsUrl($sort);

require __DIR__ . '/delete_post_handler.php';
require __DIR__ . '/feed_queries.php';

if ($recent_posts_ajax_request) {
    echo renderPostCards(
        $recent_posts,
        $comments_by_post,
        $post_images_by_post,
        $user_id,
        $is_logged_in,
        $redirect_url
    );
    return;
}

include __DIR__ . '/post_styles.php';
?>

<div class="recent-posts-wrapper">

    <div class="sort-button-row">
        <a href="<?= htmlspecialchars(currentPostsUrl('recent')) ?>" class="sort-button <?= $sort === 'recent' ? 'active' : '' ?>">
            Recent
        </a>

        <a href="<?= htmlspecialchars(currentPostsUrl('popular')) ?>" class="sort-button <?= $sort === 'popular' ? 'active' : '' ?>">
            Popular
        </a>
    </div>

    <?php if ($tag_search !== ''): ?>
        <div class="tag-filter-notice">
            Showing posts tagged with
            <strong><?= htmlspecialchars($tag_search) ?></strong>
        </div>
    <?php endif; ?>

    <?php if (!empty($message)): ?>
        <div class="recent-post-error">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <div
        id="recent-posts-list"
        data-next-offset="<?= htmlspecialchars($recent_posts_next_offset) ?>"
        data-has-more="<?= $recent_posts_has_more ? '1' : '0' ?>"
        data-sort="<?= htmlspecialchars($sort) ?>"
        data-filter="<?= htmlspecialchars($feed_filter) ?>"
        data-tag="<?= htmlspecialchars($tag_search) ?>"
    >

        <?php if (empty($recent_posts)): ?>

            <div class="no-posts">
                <?php if ($feed_filter === 'following'): ?>
                    No posts from people you follow yet.
                <?php elseif ($feed_filter === 'my_posts'): ?>
                    You have not posted yet.
                <?php else: ?>
                    No posts yet.
                <?php endif; ?>
            </div>

        <?php else: ?>

            <?= renderPostCards(
                $recent_posts,
                $comments_by_post,
                $post_images_by_post,
                $user_id,
                $is_logged_in,
                $redirect_url
            ) ?>

        <?php endif; ?>

    </div>

    <div id="recent-posts-loading" class="no-posts" style="display:none; margin-top: 14px;">
        Loading more posts...
    </div>

    <div id="recent-posts-end" class="no-posts" style="display:none; margin-top: 14px;">
        No more posts to show.
    </div>

</div>

<div class="image-lightbox" id="imageLightbox" aria-hidden="true" data-current-index="0">
    <button type="button" class="image-lightbox-close" id="imageLightboxClose" aria-label="Close image">
        ×
    </button>

    <button type="button" class="image-lightbox-arrow image-lightbox-prev" id="imageLightboxPrev" aria-label="Previous image">
        ‹
    </button>

    <div class="image-lightbox-content">
        <img src="" alt="Expanded image" class="image-lightbox-image" id="imageLightboxImage">
    </div>

    <button type="button" class="image-lightbox-arrow image-lightbox-next" id="imageLightboxNext" aria-label="Next image">
        ›
    </button>

    <div class="image-lightbox-dots" id="imageLightboxDots" aria-label="Image position"></div>
</div>

<?php include __DIR__ . '/post_scripts.php'; ?>