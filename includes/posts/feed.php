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
 * Load the top 10 tags by number of posts.
 * Group case-insensitively so capitalization variants count together.
 */
$trending_tags = [];

try {
    $trendingStmt = $pdo->query("
        SELECT
            MIN(TRIM(tag)) AS tag,
            COUNT(*) AS post_count,
            MAX(DATE(created_at)) AS latest_post_date
        FROM posts
        WHERE tag IS NOT NULL
          AND TRIM(tag) <> ''
        GROUP BY LOWER(TRIM(tag))
        ORDER BY
            post_count DESC,
            latest_post_date DESC,
            tag ASC
        LIMIT 10
    ");

    $trending_tags = $trendingStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Could not load trending tags: ' . $e->getMessage());
    $trending_tags = [];
}

/*
 * Build a same-page URL for selecting or clearing a trending tag.
 * Preserve the current sort and feed filter.
 */
$buildFeedTagUrl = static function (?string $tagValue) use ($sort, $feed_filter): string {
    $baseUrl = strtok($_SERVER['REQUEST_URI'], '?');
    $query = [];

    if (isset($_GET['page'])) {
        $query['page'] = $_GET['page'];
    }

    if ($feed_filter !== 'all') {
        $query['filter'] = $feed_filter;
    }

    if ($sort !== 'recent') {
        $query['sort'] = $sort;
    }

    if ($tagValue !== null && trim($tagValue) !== '') {
        $query['tag'] = trim($tagValue);
    }

    return $query
        ? $baseUrl . '?' . http_build_query($query)
        : $baseUrl;
};

include __DIR__ . '/post_styles.php';
?>

<style>
/*
 * Keep Recent, Popular, and Trending on one horizontal row and
 * make all three pills identical in width and alignment.
 */
.sort-button-row {
    display: flex;
    flex-direction: row;
    flex-wrap: nowrap;
    align-items: center;
    justify-content: center;
    gap: 10px;
}

.sort-button-row > .sort-button,
.sort-button-row > .trending-sort-control {
    flex: 0 0 100px;
    width: 100px;
    min-width: 100px;
    max-width: 100px;
}

.sort-button-row > .sort-button {
    box-sizing: border-box;

    display: inline-flex;
    align-items: center;
    justify-content: center;

    text-align: center;
    white-space: nowrap;
}

.trending-sort-control > .sort-button {
    width: 100%;
    min-width: 100%;
    box-sizing: border-box;

    display: inline-flex;
    align-items: center;
    justify-content: center;

    text-align: center;
    white-space: nowrap;
}

.trending-sort-control {
    position: relative;

    display: inline-flex;
    flex-direction: row;
    align-items: center;
    justify-content: center;
}

/*
 * Trending is an <a>, just like Recent and Popular, so it inherits
 * the exact same font rendering and .sort-button styling.
 */
.trending-sort-button {
    cursor: pointer;
}

/*
 * Trending dropdown.
 */
.trending-tag-menu {
    position: absolute;
    top: calc(100% + 10px);
    left: 50%;
    z-index: 30;

    width: min(310px, calc(100vw - 28px));
    padding: 8px;

    border: 1px solid #e5e7eb;
    border-radius: 16px;

    background: #ffffff;

    box-shadow:
        0 18px 45px rgba(15, 23, 42, 0.16),
        0 4px 12px rgba(15, 23, 42, 0.08);

    transform: translateX(-50%) translateY(-4px);

    opacity: 0;
    visibility: hidden;
    pointer-events: none;

    transition:
        opacity 0.16s ease,
        transform 0.16s ease,
        visibility 0.16s ease;
}

.trending-tag-menu.is-open {
    transform: translateX(-50%) translateY(0);
    opacity: 1;
    visibility: visible;
    pointer-events: auto;
}

.trending-tag-menu-title {
    padding: 7px 10px 8px;

    color: #6b7280;

    font-size: 11px;
    font-weight: 800;
    letter-spacing: 0.06em;
    text-transform: uppercase;
}

.trending-tag-list {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.trending-tag-link {
    display: flex;
    align-items: center;
    justify-content: space-between;

    gap: 14px;

    width: 100%;
    padding: 9px 10px;

    border-radius: 10px;

    color: #374151;
    text-decoration: none;

    font-size: 13px;
    font-weight: 700;

    box-sizing: border-box;
}

.trending-tag-link:hover,
.trending-tag-link.is-selected {
    background: #f3f4f6;
    color: #111827;
}

.trending-tag-name {
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.trending-tag-count {
    flex: 0 0 auto;

    color: #9ca3af;

    font-size: 12px;
    font-weight: 700;
}

.trending-tag-empty {
    padding: 10px;

    color: #6b7280;

    font-size: 13px;
    text-align: center;
}

.trending-tag-clear {
    display: block;

    margin-top: 6px;
    padding: 8px 10px 5px;

    border-top: 1px solid #f3f4f6;

    color: #6b7280;
    text-decoration: none;

    font-size: 12px;
    font-weight: 700;
    text-align: center;
}

.trending-tag-clear:hover {
    color: #111827;
}

/*
 * On mobile, pin the dropdown to the right side of Trending so the
 * dropdown expands leftward and stays inside the viewport.
 */
@media (max-width: 700px) {
    .sort-button-row {
        flex-wrap: nowrap;
        justify-content: center;
        gap: 8px;
    }

    .sort-button-row > .sort-button,
    .sort-button-row > .trending-sort-control {
        flex: 0 0 96px;
        width: 96px;
        min-width: 96px;
        max-width: 96px;
    }

    .trending-tag-menu {
        left: auto;
        right: 0;

        width: min(290px, calc(100vw - 20px));

        transform: translateY(-4px);
    }

    .trending-tag-menu.is-open {
        transform: translateY(0);
    }
}
</style>

<div class="recent-posts-wrapper">

    <div class="sort-button-row">
        <a
            href="<?= htmlspecialchars(currentPostsUrl('recent')) ?>"
            class="sort-button <?= $sort === 'recent' ? 'active' : '' ?>"
        >
            Recent
        </a>

        <a
            href="<?= htmlspecialchars(currentPostsUrl('popular')) ?>"
            class="sort-button <?= $sort === 'popular' ? 'active' : '' ?>"
        >
            Popular
        </a>

        <div class="trending-sort-control" id="trendingSortControl">
            <a
                href="#"
                class="sort-button trending-sort-button <?= $tag_search !== '' ? 'active' : '' ?>"
                id="trendingSortButton"
                role="button"
                aria-haspopup="true"
                aria-expanded="false"
                aria-controls="trendingTagMenu"
            >
                Trending
            </a>

            <div
                class="trending-tag-menu"
                id="trendingTagMenu"
                role="menu"
                aria-label="Trending tags"
            >
                <div class="trending-tag-menu-title">
                    Trending Tags
                </div>

                <?php if (!empty($trending_tags)): ?>
                    <div class="trending-tag-list">
                        <?php foreach ($trending_tags as $trendingTag): ?>
                            <?php
                                $trendingTagName = trim((string) ($trendingTag['tag'] ?? ''));
                                $trendingTagCount = (int) ($trendingTag['post_count'] ?? 0);

                                $isSelectedTrendingTag =
                                    $tag_search !== ''
                                    && strcasecmp($tag_search, $trendingTagName) === 0;
                            ?>

                            <?php if ($trendingTagName !== ''): ?>
                                <a
                                    href="<?= htmlspecialchars($buildFeedTagUrl($trendingTagName)) ?>"
                                    class="trending-tag-link <?= $isSelectedTrendingTag ? 'is-selected' : '' ?>"
                                    role="menuitem"
                                >
                                    <span class="trending-tag-name">
                                        <?= htmlspecialchars($trendingTagName) ?>
                                    </span>

                                    <span
                                        class="trending-tag-count"
                                        title="<?= htmlspecialchars($trendingTagCount) ?> posts"
                                    >
                                        <?= htmlspecialchars(number_format($trendingTagCount)) ?>
                                    </span>
                                </a>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="trending-tag-empty">
                        No trending tags yet.
                    </div>
                <?php endif; ?>

                <?php if ($tag_search !== ''): ?>
                    <a
                        href="<?= htmlspecialchars($buildFeedTagUrl(null)) ?>"
                        class="trending-tag-clear"
                    >
                        Clear tag filter
                    </a>
                <?php endif; ?>
            </div>
        </div>
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
                $is_logged_in
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

<script>
(function () {
    const control = document.getElementById("trendingSortControl");
    const button = document.getElementById("trendingSortButton");
    const menu = document.getElementById("trendingTagMenu");

    if (!control || !button || !menu) {
        return;
    }

    function setTrendingMenuOpen(isOpen) {
        menu.classList.toggle("is-open", isOpen);
        button.setAttribute("aria-expanded", isOpen ? "true" : "false");
    }

    button.addEventListener("click", function (event) {
        event.preventDefault();

        setTrendingMenuOpen(
            !menu.classList.contains("is-open")
        );
    });

    document.addEventListener("click", function (event) {
        if (!control.contains(event.target)) {
            setTrendingMenuOpen(false);
        }
    });

    document.addEventListener("keydown", function (event) {
        if (
            event.key === "Escape"
            && menu.classList.contains("is-open")
        ) {
            setTrendingMenuOpen(false);
            button.focus();
        }
    });
})();
</script>

<?php include __DIR__ . '/post_scripts.php'; ?>
