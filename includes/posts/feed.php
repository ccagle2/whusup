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
        $is_logged_in
    );
    return;
}

/*
 * Build same-page navigation links while preserving the selected group.
 * Do not preserve the offset when changing the sort mode.
 */
$buildFeedUrl = static function (string $targetSort, bool $clearTag = false) use ($feed_filter, $tag_search): string {
    $path = strtok($_SERVER['REQUEST_URI'] ?? '/index.php', '?') ?: '/index.php';
    $query = [];

    if (isset($_GET['page']) && is_string($_GET['page'])) {
        $query['page'] = $_GET['page'];
    }

    if ($feed_filter !== 'all') {
        $query['filter'] = $feed_filter;
    }

    if ($targetSort !== 'recent') {
        $query['sort'] = $targetSort;
    }

    if (!$clearTag && $tag_search !== '') {
        $query['tag'] = $tag_search;
    }

    return $path . ($query ? '?' . http_build_query($query) : '');
};

include __DIR__ . '/post_styles.php';
?>

<style>
/* All three feed navigation buttons share the same dimensions. */
.sort-button-row {
    display: flex;
    flex-direction: row;
    flex-wrap: nowrap;
    align-items: center;
    justify-content: center;
    gap: 10px;
}

.sort-button-row > .sort-button {
    flex: 0 0 100px;
    width: 100px;
    min-width: 100px;
    max-width: 100px;
    height: 44px;
    box-sizing: border-box;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    text-align: center;
    white-space: nowrap;
    text-decoration: none;
}

.groups-nav-icon {
    width: 16px;
    height: 16px;
    flex: 0 0 16px;
    fill: none;
    stroke: currentColor;
    stroke-width: 1.8;
    stroke-linecap: round;
    stroke-linejoin: round;
}

.group-filter-clear {
    margin-left: 10px;
    font-weight: 700;
    color: inherit;
    text-decoration: underline;
}

@media (max-width: 700px) {
    .sort-button-row { gap: 8px; }
    .sort-button-row > .sort-button {
        flex-basis: 96px;
        width: 96px;
        min-width: 96px;
        max-width: 96px;
    }
}
</style>

<div class="recent-posts-wrapper">

    <div class="sort-button-row">
        <a
            href="<?= htmlspecialchars($buildFeedUrl('recent'), ENT_QUOTES, 'UTF-8') ?>"
            class="sort-button <?= $sort === 'recent' ? 'active' : '' ?>"
        >
            Recent
        </a>

        <a
            href="<?= htmlspecialchars($buildFeedUrl('popular'), ENT_QUOTES, 'UTF-8') ?>"
            class="sort-button <?= $sort === 'popular' ? 'active' : '' ?>"
        >
            Trending
        </a>

        <a href="/groups.php" class="sort-button" aria-label="Explore groups">
            <svg class="groups-nav-icon" viewBox="0 0 24 24" aria-hidden="true">
                <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
                <circle cx="9" cy="7" r="4"/>
                <path d="M22 21v-2a4 4 0 0 0-3-3.87"/>
                <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
            </svg>
            Groups
        </a>
    </div>

    <?php if ($tag_search !== ''): ?>
        <div class="tag-filter-notice">
            Showing group: <strong><?= htmlspecialchars($tag_search, ENT_QUOTES, 'UTF-8') ?></strong>
            <a
                href="<?= htmlspecialchars($buildFeedUrl($sort, true), ENT_QUOTES, 'UTF-8') ?>"
                class="group-filter-clear"
            >Clear group</a>
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
                true // Initial page only; AJAX batches use the default false.
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
