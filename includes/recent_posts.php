<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';

$awsConfig = require __DIR__ . '/../config/aws.php';
$s3 = $awsConfig['s3'];
$s3Bucket = $awsConfig['bucket'];

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

if (!in_array($sort, ['recent', 'popular'], true)) {
    $sort = 'recent';
}

if (!in_array($feed_filter, ['all', 'following', 'my_posts'], true)) {
    $feed_filter = 'all';
}

$tag_search = trim($_GET['tag'] ?? '');

if (!$is_logged_in) {
    $feed_filter = 'all';
}

function getS3ImageUrl($imageKey) {
    global $awsConfig;

    if (empty($imageKey)) {
        return null;
    }

    return rtrim($awsConfig['cloudfront_url'], '/') . '/' . ltrim($imageKey, '/');
}

function currentPostsUrl($sort_value) {
    global $tag_search;
    $base_url = strtok($_SERVER['REQUEST_URI'], '?');
    $query = [];

    if (isset($_GET['page'])) {
        $query['page'] = $_GET['page'];
    }

    if (isset($_GET['filter'])) {
        $query['filter'] = $_GET['filter'];
    }

    $query['sort'] = $sort_value;

    if ($tag_search !== '') {
        $query['tag'] = $tag_search;
    }

    return $base_url . '?' . http_build_query($query);
}

$redirect_url = currentPostsUrl($sort);

function redirectToPosts($url) {
    echo '<script>window.location.href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '";</script>';
    echo '<noscript><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '"></noscript>';
    exit;
}

function timeAgo($datetime) {
    $time = strtotime($datetime);

    if ($time === false) {
        return "";
    }

    $diff = max(0, time() - $time);

    if ($diff < 60) {
        return "Just now";
    }

    $units = [
        "year" => 31536000,
        "month" => 2592000,
        "week" => 604800,
        "day" => 86400,
        "hour" => 3600,
        "minute" => 60,
    ];

    foreach ($units as $unit => $seconds) {
        if ($diff >= $seconds) {
            $value = (int) floor($diff / $seconds);
            return $value . " " . $unit . ($value === 1 ? "" : "s") . " ago";
        }
    }

    return "Just now";
}
function linkifyText($text) {
    $escaped = htmlspecialchars(trim((string) $text), ENT_QUOTES, 'UTF-8');
    $pattern = '/(https?:\/\/[^\s<]+)/i';

    return nl2br(preg_replace_callback($pattern, function ($matches) {
        $url = $matches[0];

        $display = preg_replace('#^https?://#', '', $url);
        $display = rtrim($display, '/');

        if (strlen($display) > 40) {
            $display = substr($display, 0, 37) . '...';
        }

        return '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener noreferrer">' . htmlspecialchars($display, ENT_QUOTES, 'UTF-8') . '</a>';
    }, $escaped));
}

function getDirectChildCount($comments, $parent_id) {
    $count = 0;

    foreach ($comments as $comment) {
        $comment_parent = $comment['parent_comment_id'] ?? null;

        if ((string) $comment_parent === (string) $parent_id) {
            $count++;
        }
    }

    return $count;
}

function renderCommentTree($comments, $parent_id, $post_id, $user_id, $is_logged_in) {
    foreach ($comments as $comment) {
        $comment_parent = $comment['parent_comment_id'] ?? null;

        if ((string) $comment_parent !== (string) $parent_id) {
            continue;
        }

        $comment_id = (int) $comment['id'];
        $is_comment_owner = $is_logged_in && $comment['user_id'] === $user_id;
        $is_reply = !empty($comment['parent_comment_id']);
        $child_count = getDirectChildCount($comments, $comment_id);
        $comment_like_count = (int) ($comment['like_count'] ?? 0);
        $comment_user_liked = !empty($comment['user_liked']);
        ?>

        <div 
            class="comment-box <?= $is_reply ? 'comment-reply' : '' ?>" 
            data-comment-id="<?= htmlspecialchars($comment_id) ?>"
            data-parent-comment-id="<?= htmlspecialchars($comment['parent_comment_id'] ?? '') ?>"
        >
            <div class="comment-name <?= !$is_logged_in ? 'blurred-name' : '' ?>">
                <?= htmlspecialchars(ucwords($comment['name'])) ?>
            </div>

            <div class="comment-date">
                <?= htmlspecialchars(timeAgo($comment['created_at'])) ?>
            </div>

            <div class="comment-body">
                <?= linkifyText($comment['comment_body']) ?>
            </div>

            <div class="comment-owner-actions">
                <span
                    class="like-indicator comment-like-indicator ajax-comment-like-indicator <?= $comment_user_liked ? 'liked' : '' ?> <?= $is_logged_in ? 'can-like' : 'cannot-like' ?>"
                    data-comment-id="<?= htmlspecialchars($comment_id) ?>"
                    data-logged-in="<?= $is_logged_in ? '1' : '0' ?>"
                    title="<?= $is_logged_in ? 'Like comment' : 'Log in to like comments' ?>"
                >
                    <span class="like-icon">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M7 10v10H4V10h3zm3 10h7.4c.9 0 1.7-.6 1.9-1.5l1.4-6.2c.3-1.2-.6-2.3-1.9-2.3h-5.1l.8-3.8c.1-.6-.1-1.2-.5-1.6L13.2 4 9 9.1V20z"/>
                        </svg>
                    </span>
                    <span class="like-count comment-like-count"><?= htmlspecialchars($comment_like_count) ?></span>
                </span>

                <?php if ($is_logged_in): ?>
                    <button
                        type="button"
                        class="comment-action-link comment-reply-link reply-toggle-button"
                        data-target="reply-form-<?= htmlspecialchars($comment_id) ?>"
                    >
                        Reply
                    </button>
                <?php endif; ?>

                <?php if ($is_comment_owner): ?>
                    <button 
                        type="button" 
                        class="comment-action-link comment-edit-link edit-comment-toggle-button"
                        data-target="edit-comment-<?= htmlspecialchars($comment_id) ?>"
                    >
                        Edit
                    </button>

                    <button 
                        type="button" 
                        class="comment-action-link comment-delete-link ajax-delete-comment-button"
                        data-comment-id="<?= htmlspecialchars($comment_id) ?>"
                    >
                        Delete
                    </button>
                <?php endif; ?>
            </div>

            <?php if ($is_comment_owner): ?>
                <div id="edit-comment-<?= htmlspecialchars($comment_id) ?>" class="edit-comment-section">
                    <form class="edit-comment-form ajax-edit-comment-form">
                        <input type="hidden" name="comment_id" value="<?= htmlspecialchars($comment_id) ?>">
                        <textarea name="comment_body"><?= htmlspecialchars($comment['comment_body']) ?></textarea>

                        <button type="submit" class="recent-post-button small-action-button">
                            Save
                        </button>
                    </form>
                </div>
            <?php endif; ?>

            <?php if ($is_logged_in): ?>
                <div id="reply-form-<?= htmlspecialchars($comment_id) ?>" class="reply-form-section">
                    <form class="comment-form ajax-add-comment-form">
                        <input type="hidden" name="post_id" value="<?= htmlspecialchars($post_id) ?>">
                        <input type="hidden" name="parent_comment_id" value="<?= htmlspecialchars($comment_id) ?>">

                        <textarea name="comment_body" placeholder="Write a reply..."></textarea>

                        <button type="submit" class="recent-post-button small-action-button">
                            Reply
                        </button>

                        <div class="ajax-comment-message"></div>
                    </form>
                </div>
            <?php endif; ?>

            <?php if ($child_count > 0): ?>
                <button
                    type="button"
                    class="reply-collapse-toggle"
                    data-target="comment-children-<?= htmlspecialchars($comment_id) ?>"
                    data-count="<?= htmlspecialchars($child_count) ?>"
                >
                    View <?= htmlspecialchars($child_count) ?> repl<?= $child_count === 1 ? 'y' : 'ies' ?>
                </button>

                <div id="comment-children-<?= htmlspecialchars($comment_id) ?>" class="comment-children collapsed-replies">
                    <?php renderCommentTree($comments, $comment_id, $post_id, $user_id, $is_logged_in); ?>
                </div>
            <?php else: ?>
                <div id="comment-children-<?= htmlspecialchars($comment_id) ?>" class="comment-children"></div>
            <?php endif; ?>
        </div>

        <?php
    }
}

// DELETE POST + ATTACHED S3 IMAGE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_post_id']) && $is_logged_in) {
    $post_id = (int) $_POST['delete_post_id'];

    try {
        $stmt = $pdo->prepare("
            SELECT id, image_key
            FROM posts
            WHERE id = :post_id
            AND user_id = :user_id
            LIMIT 1
        ");

        $stmt->execute([
            ':post_id' => $post_id,
            ':user_id' => $user_id
        ]);

        $postToDelete = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($postToDelete) {
            $imageKeysToDelete = [];

            try {
                $imageStmt = $pdo->prepare("
                    SELECT image_key
                    FROM post_images
                    WHERE post_id = :post_id
                ");

                $imageStmt->execute([
                    ':post_id' => $postToDelete['id']
                ]);

                $imageKeysToDelete = $imageStmt->fetchAll(PDO::FETCH_COLUMN);
            } catch (PDOException $e) {
                error_log('Could not load post images for delete: ' . $e->getMessage());
            }

            if (empty($imageKeysToDelete) && !empty($postToDelete['image_key'])) {
                $imageKeysToDelete[] = $postToDelete['image_key'];
            }

            foreach ($imageKeysToDelete as $deleteImageKey) {
                if (empty($deleteImageKey)) {
                    continue;
                }

                try {
                    $s3->deleteObject([
                        'Bucket' => $s3Bucket,
                        'Key' => $deleteImageKey
                    ]);
                } catch (Exception $e) {
                    error_log('S3 image delete failed: ' . $e->getMessage());
                }
            }

            $stmt = $pdo->prepare("
                DELETE FROM posts
                WHERE id = :post_id
                AND user_id = :user_id
            ");

            $stmt->execute([
                ':post_id' => $post_id,
                ':user_id' => $user_id
            ]);
        }

        redirectToPosts($redirect_url);

    } catch (PDOException $e) {
        $message = "Delete post failed: " . $e->getMessage();
    }
}

// SORT + FILTER SQL
$where_parts = [];
$params = [
    ':user_id' => $user_id
];

if ($feed_filter === 'following' && $is_logged_in) {
    $where_parts[] = "
        EXISTS (
            SELECT 1
            FROM friendships
            WHERE friendships.user_id = :following_user_id
            AND friendships.friend_id = posts.user_id
            AND friendships.status = 'accepted'
        )
    ";

    $params[':following_user_id'] = $user_id;
}

if ($feed_filter === 'my_posts' && $is_logged_in) {
    $where_parts[] = "posts.user_id = :my_posts_user_id";
    $params[':my_posts_user_id'] = $user_id;
}

if ($tag_search !== '') {
    $where_parts[] = "posts.tag LIKE :tag_search";
    $params[':tag_search'] = '%' . $tag_search . '%';
}

$where_sql = "";

if (!empty($where_parts)) {
    $where_sql = "WHERE " . implode(" AND ", $where_parts);
}

if ($sort === 'popular') {
    $order_sql = "
        ORDER BY popularity_score DESC, latest_activity_at DESC
    ";
} else {
    $order_sql = "
        ORDER BY latest_activity_at DESC
    ";
}

// FETCH POSTS
try {
    $stmt = $pdo->prepare("
        SELECT 
            posts.id,
            posts.user_id,
            posts.body,
            posts.tag,
            posts.image_key,
            posts.created_at,
            users.name,
            user_profiles.profile_picture_url,

            COUNT(DISTINCT post_likes.id) AS like_count,
            COUNT(DISTINCT post_comments.id) AS comment_count,

            (
                COUNT(DISTINCT post_likes.id) + 
                COUNT(DISTINCT post_comments.id)
            ) AS popularity_score,

            GREATEST(
                posts.created_at,
                COALESCE(MAX(post_comments.created_at), posts.created_at)
            ) AS latest_activity_at,

            MAX(
                CASE 
                    WHEN post_likes.user_id = :user_id
                    THEN 1
                    ELSE 0
                END
            ) AS user_liked

        FROM posts

        JOIN users ON users.id = posts.user_id

        LEFT JOIN user_profiles ON user_profiles.user_id = posts.user_id

        LEFT JOIN post_likes ON post_likes.post_id = posts.id

        LEFT JOIN post_comments ON post_comments.post_id = posts.id

        $where_sql

        GROUP BY 
            posts.id,
            posts.user_id,
            posts.body,
            posts.tag,
            posts.image_key,
            posts.created_at,
            users.name,
            user_profiles.profile_picture_url

        $order_sql

        LIMIT :posts_limit OFFSET :posts_offset
    ");

    foreach ($params as $key => $value) {
        if ($value === null) {
            $stmt->bindValue($key, null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue($key, $value, PDO::PARAM_STR);
        }
    }

    $stmt->bindValue(':posts_limit', $posts_fetch_limit, PDO::PARAM_INT);
    $stmt->bindValue(':posts_offset', $posts_offset, PDO::PARAM_INT);
    $stmt->execute();

    $recent_posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $recent_posts_has_more = count($recent_posts) > $posts_per_page;

    if ($recent_posts_has_more) {
        $recent_posts = array_slice($recent_posts, 0, $posts_per_page);
    }

    $recent_posts_next_offset = $posts_offset + count($recent_posts);

} catch (PDOException $e) {
    $recent_posts = [];
    $message = "Could not load recent posts: " . $e->getMessage();
}

// FETCH COMMENTS FOR THESE POSTS
$comments_by_post = [];

if (!empty($recent_posts)) {
    $post_ids = array_column($recent_posts, 'id');
    $placeholders = implode(',', array_fill(0, count($post_ids), '?'));

    try {
        $sql = "
            SELECT 
                post_comments.id,
                post_comments.post_id,
                post_comments.user_id,
                post_comments.parent_comment_id,
                post_comments.comment_body,
                post_comments.created_at,
                users.name,
                COUNT(DISTINCT comment_likes.id) AS like_count,
                MAX(
                    CASE
                        WHEN comment_likes.user_id = ?
                        THEN 1
                        ELSE 0
                    END
                ) AS user_liked
            FROM post_comments

            JOIN users ON users.id = post_comments.user_id

            LEFT JOIN comment_likes ON comment_likes.comment_id = post_comments.id

            WHERE post_comments.post_id IN ($placeholders)

            GROUP BY
                post_comments.id,
                post_comments.post_id,
                post_comments.user_id,
                post_comments.parent_comment_id,
                post_comments.comment_body,
                post_comments.created_at,
                users.name

            ORDER BY post_comments.created_at ASC
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute(array_merge([$user_id], $post_ids));
        $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($comments as $comment) {
            $comments_by_post[$comment['post_id']][] = $comment;
        }

    } catch (PDOException $e) {
        $comments_by_post = [];
        error_log('Could not load comments: ' . $e->getMessage());
    }
}


// FETCH POST IMAGES FOR THESE POSTS
$post_images_by_post = [];

if (!empty($recent_posts)) {
    $post_ids = array_column($recent_posts, 'id');
    $placeholders = implode(',', array_fill(0, count($post_ids), '?'));

    try {
        $sql = "
            SELECT
                post_id,
                image_key,
                sort_order
            FROM post_images
            WHERE post_id IN ($placeholders)
            ORDER BY post_id ASC, sort_order ASC, id ASC
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($post_ids);
        $post_images = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($post_images as $image) {
            $post_images_by_post[$image['post_id']][] = $image['image_key'];
        }

    } catch (PDOException $e) {
        $post_images_by_post = [];
        error_log('Could not load post images: ' . $e->getMessage());
    }

    // Backward compatibility for older posts that still only have posts.image_key.
    foreach ($recent_posts as $post) {
        if (
            empty($post_images_by_post[$post['id']]) &&
            !empty($post['image_key'])
        ) {
            $post_images_by_post[$post['id']][] = $post['image_key'];
        }
    }
}


function renderRecentPostCards($recent_posts, $comments_by_post, $post_images_by_post, $user_id, $is_logged_in, $redirect_url) {
    ob_start();
?>
        <?php foreach ($recent_posts as $post): ?>

            <?php
                $post_id = (int) $post['id'];
                $comment_count = (int) $post['comment_count'];
                $is_owner = $is_logged_in && $post['user_id'] === $user_id;

                $name_parts = explode(' ', trim($post['name']));
                $first_initial = strtoupper(substr($name_parts[0], 0, 1));
                $last_initial = '';

                if (count($name_parts) > 1) {
                    $last_initial = strtoupper(substr(end($name_parts), 0, 1));
                }

                $first_letter = $first_initial . $last_initial;

                $profileImageUrl = !empty($post['profile_picture_url'])
                    ? getS3ImageUrl($post['profile_picture_url'])
                    : null;
            ?>

            <div class="recent-post-card" data-post-id="<?= htmlspecialchars($post_id) ?>">

                <div class="profile-emblem <?= !$is_logged_in ? 'blurred-name' : '' ?>">
                    <?php if (!empty($profileImageUrl)): ?>
                        <img
                            src="<?= htmlspecialchars($profileImageUrl) ?>"
                            alt="Profile picture"
                            class="profile-emblem-image <?= $is_logged_in ? 'profile-expandable' : '' ?>"
                            data-full-image="<?= $is_logged_in ? htmlspecialchars($profileImageUrl) : '' ?>"
                            loading="lazy"
                        >
                    <?php else: ?>
                        <?= htmlspecialchars($first_letter) ?>
                    <?php endif; ?>
                </div>

                <div class="recent-post-author <?= !$is_logged_in ? 'blurred-name' : '' ?>">
                    <?= htmlspecialchars(ucwords($post['name'])) ?>
                </div>

                <div class="recent-post-date">
                    <?= htmlspecialchars(timeAgo($post['created_at'])) ?>
                </div>

                <div class="recent-post-body">
                    <?= linkifyText($post['body']) ?>
                </div>

                <?php
                    $postImageKeys = $post_images_by_post[$post_id] ?? [];
                    $postImageKeys = array_slice(array_values(array_filter($postImageKeys)), 0, 3);
                ?>

                <?php if (!empty($postImageKeys)): ?>
                    <div
                        class="recent-post-image-carousel <?= count($postImageKeys) > 1 ? 'has-multiple-images' : 'has-single-image' ?>"
                        data-post-id="<?= htmlspecialchars($post_id) ?>"
                        data-current-index="0"
                    >
                        <div class="recent-post-image-stage">
                            <div class="recent-post-image-track">
                                <?php foreach ($postImageKeys as $imageIndex => $postImageKey): ?>
                                    <?php $postImageUrl = getS3ImageUrl($postImageKey); ?>

                                    <?php if (!empty($postImageUrl)): ?>
                                        <div class="recent-post-image-slide">
                                            <div class="recent-post-image-frame">
                                                <img
                                                    src="<?= htmlspecialchars($postImageUrl) ?>"
                                                    alt="Post image <?= htmlspecialchars($imageIndex + 1) ?>"
                                                    class="recent-post-image <?= !$is_logged_in ? 'blurred-post-image' : '' ?>"
                                                    data-post-id="<?= htmlspecialchars($post_id) ?>"
                                                    data-image-index="<?= htmlspecialchars($imageIndex) ?>"
                                                    data-full-image="<?= htmlspecialchars($postImageUrl) ?>"
                                                    loading="lazy"
                                                >
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>

                            <?php if (count($postImageKeys) > 1): ?>
                                <button
                                    type="button"
                                    class="post-image-carousel-arrow post-image-carousel-prev"
                                    aria-label="Previous image"
                                >
                                    ‹
                                </button>

                                <button
                                    type="button"
                                    class="post-image-carousel-arrow post-image-carousel-next"
                                    aria-label="Next image"
                                >
                                    ›
                                </button>

                                <div class="post-image-carousel-dots" aria-label="Image position">
                                    <?php foreach ($postImageKeys as $dotIndex => $unusedImageKey): ?>
                                        <button
                                            type="button"
                                            class="post-image-carousel-dot <?= $dotIndex === 0 ? 'active' : '' ?>"
                                            data-dot-index="<?= htmlspecialchars($dotIndex) ?>"
                                            aria-label="Show image <?= htmlspecialchars($dotIndex + 1) ?>"
                                        ></button>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($is_owner): ?>
                    <div class="post-owner-actions">
                        <button 
                            type="button" 
                            class="comment-action-link comment-edit-link edit-toggle-button"
                            data-target="edit-post-<?= htmlspecialchars($post_id) ?>"
                        >
                            Edit
                        </button>

                        <form 
                            method="POST" 
                            action="<?= htmlspecialchars($redirect_url) ?>" 
                            class="post-delete-form"
                        >
                            <input type="hidden" name="delete_post_id" value="<?= htmlspecialchars($post_id) ?>">

                            <button 
                                type="submit" 
                                class="comment-action-link comment-delete-link"
                                onclick="return confirm('Are you sure you want to delete this post?');"
                            >
                                Delete
                            </button>
                        </form>
                    </div>

                    <div id="edit-post-<?= htmlspecialchars($post_id) ?>" class="edit-post-section">
                        <form class="edit-post-form ajax-edit-post-form">
                            <input type="hidden" name="post_id" value="<?= htmlspecialchars($post_id) ?>">

                            <textarea name="post_body"><?= htmlspecialchars($post['body']) ?></textarea>

                            <button type="submit" class="recent-post-button">
                                Save Post
                            </button>

                            <div class="ajax-comment-message"></div>
                        </form>
                    </div>
                <?php endif; ?>

                <div class="recent-post-footer">
                    <div class="recent-post-actions">

                        <span
                            class="like-indicator ajax-like-indicator <?= $post['user_liked'] ? 'liked' : '' ?> <?= $is_logged_in ? 'can-like' : 'cannot-like' ?>"
                            data-post-id="<?= htmlspecialchars($post_id) ?>"
                            data-logged-in="<?= $is_logged_in ? '1' : '0' ?>"
                        >
                            <span class="like-icon">
                                <svg viewBox="0 0 24 24" aria-hidden="true">
                                    <path d="M7 10v10H4V10h3zm3 10h7.4c.9 0 1.7-.6 1.9-1.5l1.4-6.2c.3-1.2-.6-2.3-1.9-2.3h-5.1l.8-3.8c.1-.6-.1-1.2-.5-1.6L13.2 4 9 9.1V20z"/>
                                </svg>
                            </span>

                            <span class="like-count">
                                <?= htmlspecialchars($post['like_count']) ?>
                            </span>
                        </span>

                        <span class="comment-icon-toggle" data-target="comments-<?= htmlspecialchars($post_id) ?>" title="Show comments">
                            <span class="comment-icon">💬</span>
                            <span class="comment-count"><?= htmlspecialchars($comment_count) ?></span>
                        </span>

                    </div>

                    <?php if (!empty($post['tag'])): ?>
                        <div class="post-tag">
                            <?= htmlspecialchars($post['tag']) ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div id="comments-<?= htmlspecialchars($post_id) ?>" class="comments-section">

                    <p class="no-comments-text" style="<?= $comment_count === 0 ? '' : 'display:none;' ?>">
                        No comments yet.
                    </p>

                    <div class="comment-list">
                        <?php renderCommentTree($comments_by_post[$post_id] ?? [], null, $post_id, $user_id, $is_logged_in); ?>
                    </div>

                    <?php if ($is_logged_in): ?>
                        <form class="comment-form ajax-add-comment-form">
                            <input type="hidden" name="post_id" value="<?= htmlspecialchars($post_id) ?>">
                            <input type="hidden" name="parent_comment_id" value="">

                            <textarea name="comment_body" placeholder="Write a comment..."></textarea>

                            <button type="submit" class="recent-post-button">
                                Comment
                            </button>

                            <div class="ajax-comment-message"></div>
                        </form>
                    <?php else: ?>
                        <p class="login-message">
                            <a href="login.php">Log in</a> to like or comment.
                        </p>
                    <?php endif; ?>

                </div>

            </div>

        <?php endforeach; ?>
<?php
    return ob_get_clean();
}

if ($recent_posts_ajax_request) {
    echo renderRecentPostCards($recent_posts, $comments_by_post, $post_images_by_post, $user_id, $is_logged_in, $redirect_url);
    return;
}

?>
<style>
:root {
    --whusup-image-radius: 18px;
}

.recent-posts-wrapper {
    max-width: 900px;
    margin: 8px auto 40px;
    padding: 0 20px;
    box-sizing: border-box;
}

.sort-button-row {
    display: flex;
    justify-content: center;
    gap: 10px;
    margin-bottom: 22px;
    flex-wrap: wrap;
}

.sort-button {
    display: inline-block;
    background: #ffffff;
    color: #111827;
    border: 1px solid #111827;
    padding: 9px 24px;
    border-radius: 999px;
    font-size: 14px;
    font-weight: 700;
    text-decoration: none;
    transition: 0.2s ease;
}

.sort-button:hover,
.sort-button.active {
    background: #111827;
    color: #ffffff;
}

.tag-filter-notice {
    max-width: 680px;
    margin: -8px auto 18px;
    padding: 10px 16px;
    border-radius: 999px;
    background: #f9fafb;
    border: 1px solid #e5e7eb;
    color: #4b5563;
    font-size: 14px;
    font-weight: 600;
    text-align: center;
}

.recent-post-card {
    position: relative;
    background: #ffffff;
    border-radius: 14px;
    padding: 18px 20px;
    margin-bottom: 18px;
    box-shadow: 0 4px 14px rgba(0,0,0,0.08);
    text-align: left;
}

.profile-emblem {
    position: absolute;
    top: 18px;
    right: 18px;
    width: 56px;
    height: 56px;
    border-radius: 50%;
    background: #e5e7eb;
    color: #4b5563;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    font-weight: 800;
    font-family: "Poppins", sans-serif;
    box-shadow: 0 4px 14px rgba(0,0,0,0.14);
    overflow: hidden;
    border: 3px solid #ffffff;
}

.profile-emblem-image {
    width: 100%;
    height: 100%;
    display: block;
    object-fit: cover;
    object-position: center;
}

.profile-expandable {
    cursor: zoom-in;
}

.blurred-name {
    filter: blur(5px);
    user-select: none;
    pointer-events: none;
}

.recent-post-author {
    font-weight: bold;
    font-size: 18px;
    margin-bottom: 6px;
    color: #111827;
    padding-right: 76px;
}

.recent-post-date {
    color: #666;
    font-size: 13px;
    margin-bottom: 14px;
    padding-right: 76px;
}

.recent-post-body {
    line-height: 1.6;
    color: #333;
    margin-bottom: 12px;
    text-align: left;
    word-break: break-word;
}

.recent-post-image-carousel {
    width: 100%;
    margin: 10px 0 13px;
}

.recent-post-image-stage {
    position: relative;
    width: 100%;
    min-height: 120px;
    overflow: hidden;
    touch-action: pan-y;
}

.recent-post-image-carousel.has-multiple-images .recent-post-image-stage,
.recent-post-image-carousel.has-single-image .recent-post-image-stage {
    background: transparent;
    overflow: hidden;
}

.recent-post-image-track {
    display: flex;
    width: 100%;
    transition: transform 0.28s ease;
    will-change: transform;
}

.recent-post-image-slide {
    min-width: 100%;
    width: 100%;
    max-height: 420px;
    flex: 0 0 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0;
    box-sizing: border-box;
    background: transparent;
}

.post-image-carousel-arrow {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    width: 32px;
    height: 46px;
    border: none;
    border-radius: 0;
    background: transparent;
    color: rgba(17, 24, 39, 0.72);
    font-size: 42px;
    font-weight: 500;
    line-height: 1;
    cursor: pointer;
    z-index: 3;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: color 0.2s ease, transform 0.2s ease, opacity 0.2s ease;
    opacity: 0.72;
    text-shadow: 0 1px 3px rgba(255,255,255,0.9);
}

.post-image-carousel-arrow:hover {
    color: rgba(17, 24, 39, 0.96);
    transform: translateY(-50%) scale(1.08);
    opacity: 1;
}

.post-image-carousel-prev {
    left: 4px;
}

.post-image-carousel-next {
    right: 4px;
}

.post-image-carousel-dots {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 7px;
    margin-top: 6px;
}

.post-image-carousel-dot {
    width: 7px;
    height: 7px;
    border: none;
    border-radius: 999px;
    padding: 0;
    background: #d1d5db;
    cursor: pointer;
    transition: background 0.2s ease, transform 0.2s ease, width 0.2s ease;
}

.post-image-carousel-dot.active {
    width: 18px;
    background: #111827;
}

.post-image-carousel-dot:hover {
    background: #6b7280;
}

.recent-post-image-frame {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    max-width: 100%;
    max-height: 420px;
    border-radius: var(--whusup-image-radius);
    overflow: hidden;
    background: transparent;
    line-height: 0;
    -webkit-mask-image: -webkit-radial-gradient(white, black);
}

.recent-post-image {
    display: block;
    width: auto;
    height: auto;
    max-width: 100%;
    max-height: 420px;
    border-radius: 0;
    border: none;
    background: transparent;
    object-fit: contain;
    object-position: center;
    cursor: zoom-in;
}

.recent-post-image.blurred-post-image {
    filter: blur(14px);
    transform: scale(1.015);
    user-select: none;
    pointer-events: none;
}

.recent-post-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    border-top: 1px solid #e5e7eb;
    padding-top: 11px;
    margin-top: 6px;
}

.post-tag {
    display: flex;
    align-items: center;
    justify-content: center;
    min-width: 80px;
    font-size: 14px;
    color: #9ca3af;
    font-weight: 700;
    text-align: center;
    white-space: nowrap;
    flex-shrink: 0;
}

.recent-post-actions,
.owner-action-row,
.post-owner-actions {
    display: flex;
    align-items: center;
    gap: 14px;
    flex-wrap: wrap;
}

.owner-action-row,
.post-owner-actions {
    margin-bottom: 15px;
}

.post-delete-form {
    display: flex;
    align-items: center;
    margin: 0;
    padding: 0;
}

.like-indicator {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    border: none;
    background: transparent;
    color: #111827;
    font-size: 14px;
    font-weight: 700;
}

.like-indicator.can-like {
    cursor: pointer;
}

.like-indicator.cannot-like {
    cursor: default;
}

.like-icon svg {
    width: 22px;
    height: 22px;
    display: block;
}

.like-icon svg path {
    fill: #ffffff;
    stroke: #111827;
    stroke-width: 2;
    stroke-linejoin: round;
}

.like-indicator.liked .like-icon svg path {
    fill: #111827;
    stroke: #111827;
}

.recent-post-button,
.edit-toggle-button,
.delete-button {
    border: none;
    padding: 9px 18px;
    border-radius: 999px;
    cursor: pointer;
    font-size: 14px;
    font-weight: bold;
}

.recent-post-button {
    background: #1d4ed8;
    color: white;
}

.edit-toggle-button {
    background: #6b7280;
    color: white;
}

.delete-button {
    background: #dc2626;
    color: white;
}

.small-action-button {
    padding: 7px 15px;
    font-size: 12px;
}

.comment-icon-toggle {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    color: #111827;
    font-size: 15px;
    font-weight: 700;
    cursor: pointer;
    user-select: none;
    transition: opacity 0.2s ease, transform 0.2s ease;
}

.comment-icon-toggle:hover {
    opacity: 0.7;
    transform: translateY(-1px);
}

.comment-icon {
    font-size: 20px;
    line-height: 1;
}

.comments-section {
    display: none;
    margin-top: 14px;
    border-top: 1px solid #e5e7eb;
    padding-top: 15px;
}

.comments-section.open {
    display: block;
}

.comment-list {
    margin-top: 10px;
}

.no-comments-text {
    margin-top: 10px;
    color: #777;
    font-size: 14px;
}

.comment-box {
    background: #f8fafc;
    padding: 14px;
    border-radius: 14px;
    margin-top: 12px;
    border: 1px solid #e5e7eb;
    transition: background 0.2s ease;
}

.comment-box:hover {
    background: #f1f5f9;
}

.comment-reply {
    margin-left: 34px;
    margin-top: 10px;
    border-left: 3px solid #dbeafe;
}

.comment-name {
    font-weight: 700;
    color: #111827;
    font-size: 14px;
}

.comment-date {
    color: #9ca3af;
    font-size: 11px;
    margin-top: 2px;
    margin-bottom: 8px;
}

.comment-body {
    color: #374151;
    line-height: 1.55;
    font-size: 14px;
    margin-bottom: 10px;
    word-break: break-word;
}

.comment-owner-actions {
    display: flex;
    align-items: center;
    gap: 14px;
    margin-top: 8px;
    flex-wrap: wrap;
}

.comment-owner-actions .like-indicator {
    font-size: 12px;
    padding: 0;
}

.comment-owner-actions .like-icon svg {
    width: 18px;
    height: 18px;
}

.comment-like-indicator.can-like:hover {
    transform: translateY(-1px);
}

.comment-action-link {
    background: none;
    border: none;
    padding: 0;
    font-size: 12px;
    font-weight: 700;
    cursor: pointer;
    transition: opacity 0.2s ease;
}

.comment-action-link:hover {
    opacity: 0.7;
}

.comment-edit-link {
    color: #2563eb;
}

.comment-delete-link {
    color: #dc2626;
}

.comment-reply-link {
    color: #4b5563;
}

.reply-collapse-toggle {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    margin-top: 12px;
    background: #ffffff;
    border: 1px solid #dbeafe;
    color: #2563eb;
    padding: 7px 13px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 800;
    cursor: pointer;
    transition: background 0.2s ease, transform 0.2s ease, border-color 0.2s ease;
}

.reply-collapse-toggle::before {
    content: "↳";
    font-weight: 900;
}

.reply-collapse-toggle:hover {
    background: #eff6ff;
    border-color: #bfdbfe;
    transform: translateY(-1px);
}

.collapsed-replies {
    display: none;
}

.collapsed-replies.open {
    display: block;
}

.recent-post-body a,
.comment-body a {
    color: #2563eb;
    text-decoration: none;
    font-weight: 500;
    word-break: break-word;
}

.comment-form,
.edit-post-form,
.edit-comment-form {
    margin-top: 15px;
}

.comment-form textarea,
.edit-post-form textarea,
.edit-comment-form textarea {
    width: 100%;
    min-height: 75px;
    margin-top: 8px;
    padding: 10px;
    border-radius: 10px;
    border: 1px solid #d1d5db;
    resize: vertical;
    box-sizing: border-box;
    font-family: inherit;
    font-size: 14px;
}

.edit-post-section,
.edit-comment-section,
.reply-form-section {
    display: none;
    margin-top: 12px;
    padding: 12px;
    background: #ffffff;
    border-radius: 12px;
    border: 1px solid #e5e7eb;
}

.edit-post-section.open,
.edit-comment-section.open,
.reply-form-section.open {
    display: block;
}

.no-posts,
.login-message {
    text-align: center;
    color: #777;
}

.recent-post-error,
.ajax-comment-message {
    color: #b91c1c;
    background: #fee2e2;
    padding: 8px;
    border-radius: 8px;
    margin-top: 10px;
    font-size: 13px;
}

.ajax-comment-message {
    display: none;
}


.image-lightbox {
    position: fixed;
    inset: 0;
    z-index: 20000;
    background: rgba(17, 24, 39, 0.92);
    display: none;
    align-items: center;
    justify-content: center;
    padding: 28px;
    box-sizing: border-box;
    touch-action: pan-y;
}

.image-lightbox.open {
    display: flex;
}

.image-lightbox-content {
    position: relative;
    max-width: 96vw;
    max-height: 90vh;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: var(--whusup-image-radius);
    overflow: hidden;
    line-height: 0;
    -webkit-mask-image: -webkit-radial-gradient(white, black);
}

.image-lightbox-image {
    display: block;
    width: auto;
    height: auto;
    max-width: 96vw;
    max-height: 90vh;
    object-fit: contain;
    border-radius: 0;
    box-shadow: 0 18px 60px rgba(0,0,0,0.42);
    background: transparent;
}

.image-lightbox-close {
    position: fixed;
    top: 18px;
    right: 22px;
    width: 42px;
    height: 42px;
    border: none;
    border-radius: 999px;
    background: rgba(255,255,255,0.14);
    color: #ffffff;
    font-size: 32px;
    line-height: 1;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    transition: background 0.2s ease, transform 0.2s ease;
    z-index: 20003;
}

.image-lightbox-close:hover {
    background: rgba(255,255,255,0.24);
    transform: scale(1.04);
}

.image-lightbox-arrow {
    position: fixed;
    top: 50%;
    transform: translateY(-50%);
    width: 44px;
    height: 58px;
    border: none;
    background: transparent;
    color: rgba(255,255,255,0.86);
    font-size: 56px;
    font-weight: 300;
    line-height: 1;
    cursor: pointer;
    z-index: 20002;
    display: none;
    align-items: center;
    justify-content: center;
    text-shadow: 0 2px 8px rgba(0,0,0,0.35);
    transition: opacity 0.2s ease, transform 0.2s ease;
}

.image-lightbox-arrow:hover {
    opacity: 1;
    transform: translateY(-50%) scale(1.06);
}

.image-lightbox-prev {
    left: 20px;
}

.image-lightbox-next {
    right: 20px;
}

.image-lightbox.has-multiple .image-lightbox-arrow {
    display: inline-flex;
}

.image-lightbox-dots {
    position: fixed;
    left: 50%;
    bottom: 22px;
    transform: translateX(-50%);
    display: none;
    align-items: center;
    justify-content: center;
    gap: 8px;
    z-index: 20002;
}

.image-lightbox.has-multiple .image-lightbox-dots {
    display: flex;
}

.image-lightbox-dot {
    width: 8px;
    height: 8px;
    border: none;
    border-radius: 999px;
    padding: 0;
    background: rgba(255,255,255,0.42);
    cursor: pointer;
    transition: background 0.2s ease, width 0.2s ease;
}

.image-lightbox-dot.active {
    width: 20px;
    background: #ffffff;
}

body.lightbox-open {
    overflow: hidden;
}


@media (min-width: 701px) {
    /*
        Desktop layout-shift fix, including logged-out blurred images:
        The OUTER carousel/stage/slide reserves a stable 420px area before lazy images load.
        The INNER image frame stays shrink-wrapped to the real image so the visible photo itself
        keeps modern rounded corners instead of only the larger empty stage being rounded.
        Mobile keeps its original portrait-photo behavior below.
    */
    .recent-post-image-carousel {
        min-height: 439px; /* 420px image area + dots/bottom spacing buffer */
    }

    .recent-post-image-stage {
        position: relative;
        width: 100%;
        height: 420px;
        min-height: 420px;
        max-height: 420px;
        overflow: hidden;
        background: transparent;
        border-radius: var(--whusup-image-radius);
    }

    .recent-post-image-track {
        height: 420px;
    }

    .recent-post-image-slide {
        height: 420px;
        min-height: 420px;
        max-height: 420px;
        align-items: center;
        justify-content: center;
        overflow: hidden;
    }

    .recent-post-image-frame {
        display: inline-flex;
        width: auto;
        height: auto;
        max-width: 100%;
        max-height: 420px;
        align-items: center;
        justify-content: center;
        border-radius: var(--whusup-image-radius) !important;
        overflow: hidden !important;
        background: transparent;
        line-height: 0;
        -webkit-mask-image: -webkit-radial-gradient(white, black);
    }

    .recent-post-image {
        display: block;
        width: auto;
        height: auto;
        max-width: 100%;
        max-height: 420px;
        object-fit: contain;
        object-position: center;
        border-radius: var(--whusup-image-radius) !important;
    }

    .recent-post-image.blurred-post-image {
        border-radius: var(--whusup-image-radius) !important;
    }
}

@media (max-width: 700px) {
    .recent-posts-wrapper {
        max-width: none;
        width: 100%;
        margin: 12px auto;
        padding: 0 10px;
    }

    .recent-post-card {
        border-radius: 14px;
        padding: 18px;
        margin-bottom: 18px;
    }

    .profile-emblem {
        top: 14px;
        right: 14px;
        width: 48px;
        height: 48px;
        font-size: 15px;
    }

    .sort-button {
        flex: 1;
        text-align: center;
    }

    .recent-post-footer {
        align-items: center;
    }

    .comment-reply {
        margin-left: 18px;
    }

    .recent-post-image-carousel {
        min-height: 0;
    }

    .recent-post-image-stage {
        height: auto;
        min-height: 120px;
        max-height: none;
    }

    .recent-post-image-track {
        height: auto;
    }

    .recent-post-image {
        width: auto;
        height: auto;
        max-height: 340px;
    }

    .recent-post-image-slide {
        height: auto;
        min-height: 0;
        max-height: 340px;
    }

    .recent-post-image-frame {
        height: auto;
        min-height: 0;
        max-height: 340px;
    }

    /*
        Mobile portrait-photo upgrade:
        JavaScript adds these classes only when the actual uploaded image is portrait-oriented.
        Portrait photos fill the mobile card width, keep their aspect ratio, and are clipped
        only if they would make the post card excessively tall. The lightbox still shows
        the full uncropped original.
    */
    .recent-post-image-slide.mobile-portrait-slide {
        max-height: none;
        align-items: stretch;
    }

    .recent-post-image-frame.mobile-portrait-frame {
        display: block;
        width: 100%;
        max-width: 100%;
        max-height: min(560px, 78vh);
        overflow: hidden;
        border-radius: var(--whusup-image-radius);
        background: transparent;
    }

    .recent-post-image.mobile-portrait-image {
        display: block;
        width: 100%;
        height: auto;
        max-width: 100%;
        max-height: none;
        object-fit: contain;
        object-position: center;
        border-radius: inherit;
    }

    .post-image-carousel-arrow {
        width: 30px;
        height: 42px;
        font-size: 38px;
    }

    .post-image-carousel-prev {
        left: 2px;
    }

    .post-image-carousel-next {
        right: 2px;
    }

    .image-lightbox {
        padding: 16px;
    }

    .image-lightbox-image {
        max-width: 96vw;
        max-height: 86vh;
    }

    .image-lightbox-content {
        max-width: 96vw;
        max-height: 86vh;
    }

    .image-lightbox-arrow {
        width: 36px;
        height: 50px;
        font-size: 46px;
    }

    .image-lightbox-prev {
        left: 8px;
    }

    .image-lightbox-next {
        right: 8px;
    }

    .image-lightbox-close {
        top: 12px;
        right: 12px;
    }
}




/*
    Desktop inline image layout — single source of truth.
    This reserves the same media height before lazy-loaded images arrive, so cards
    do not grow while scrolling. Mobile portrait/full-width behavior remains in
    the max-width:700px rules above.
*/
@media (min-width: 701px) {
    .recent-post-card .recent-post-image-carousel {
        width: 100% !important;
        height: 440px !important;
        min-height: 440px !important;
        max-height: 440px !important;
        margin: 10px 0 13px !important;
        overflow: hidden !important;
    }

    .recent-post-card .recent-post-image-stage {
        position: relative !important;
        width: 100% !important;
        height: 440px !important;
        min-height: 440px !important;
        max-height: 440px !important;
        overflow: hidden !important;
        background: transparent !important;
        border-radius: var(--whusup-image-radius) !important;
    }

    .recent-post-card .recent-post-image-track {
        display: flex !important;
        width: 100% !important;
        height: 420px !important;
        min-height: 420px !important;
        max-height: 420px !important;
        transition: transform 0.28s ease !important;
        will-change: transform !important;
    }

    .recent-post-card .recent-post-image-slide {
        flex: 0 0 100% !important;
        min-width: 100% !important;
        width: 100% !important;
        height: 420px !important;
        min-height: 420px !important;
        max-height: 420px !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        overflow: hidden !important;
        background: transparent !important;
    }

    .recent-post-card .recent-post-image-frame {
        display: block !important;
        width: 100% !important;
        height: 420px !important;
        min-height: 420px !important;
        max-height: 420px !important;
        overflow: hidden !important;
        border-radius: var(--whusup-image-radius) !important;
        background: transparent !important;
        line-height: 0 !important;
        -webkit-mask-image: -webkit-radial-gradient(white, black) !important;
        mask-image: radial-gradient(white, black) !important;
    }

    .recent-post-card .recent-post-image,
    .recent-post-card .recent-post-image.blurred-post-image {
        display: block !important;
        width: 100% !important;
        height: 100% !important;
        min-height: 420px !important;
        max-height: 420px !important;
        object-fit: cover !important;
        object-position: center !important;
        border-radius: 0 !important;
        background: transparent !important;
    }

    .recent-post-card .recent-post-image.blurred-post-image {
        filter: blur(14px) !important;
        transform: scale(1.015) !important;
        user-select: none !important;
        pointer-events: none !important;
    }

    .recent-post-card .post-image-carousel-dots {
        height: 14px !important;
        min-height: 14px !important;
        max-height: 14px !important;
        margin-top: 6px !important;
    }
}



/*
    FINAL desktop media-frame override.
    Purpose:
    1) Reserve desktop image space before lazy images load, preventing card growth.
    2) Preserve original image aspect ratio with object-fit: contain, preventing portrait images from being squeezed.
    3) Keep modern rounded clipping on desktop for both logged-in and logged-out blurred images.
    Mobile rules remain untouched.
*/
@media (min-width: 701px) {
    .recent-post-card .recent-post-image-carousel {
        width: 100% !important;
        height: 440px !important;
        min-height: 440px !important;
        max-height: 440px !important;
        margin: 10px 0 13px !important;
        overflow: hidden !important;
    }

    .recent-post-card .recent-post-image-stage {
        position: relative !important;
        width: 100% !important;
        height: 420px !important;
        min-height: 420px !important;
        max-height: 420px !important;
        overflow: hidden !important;
        background: transparent !important;
        border-radius: var(--whusup-image-radius) !important;
        -webkit-mask-image: -webkit-radial-gradient(white, black) !important;
        mask-image: radial-gradient(white, black) !important;
    }

    .recent-post-card .recent-post-image-track {
        display: flex !important;
        width: 100% !important;
        height: 420px !important;
        min-height: 420px !important;
        max-height: 420px !important;
        transition: transform 0.28s ease !important;
        will-change: transform !important;
    }

    .recent-post-card .recent-post-image-slide {
        flex: 0 0 100% !important;
        min-width: 100% !important;
        width: 100% !important;
        height: 420px !important;
        min-height: 420px !important;
        max-height: 420px !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        overflow: hidden !important;
        background: transparent !important;
    }

    .recent-post-card .recent-post-image-frame {
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        width: 100% !important;
        height: 420px !important;
        min-height: 420px !important;
        max-height: 420px !important;
        overflow: hidden !important;
        border-radius: var(--whusup-image-radius) !important;
        background: transparent !important;
        line-height: 0 !important;
        -webkit-mask-image: -webkit-radial-gradient(white, black) !important;
        mask-image: radial-gradient(white, black) !important;
    }

    .recent-post-card .recent-post-image,
    .recent-post-card .recent-post-image.blurred-post-image {
        display: block !important;
        width: auto !important;
        height: auto !important;
        max-width: 100% !important;
        max-height: 420px !important;
        min-width: 0 !important;
        min-height: 0 !important;
        object-fit: contain !important;
        object-position: center !important;
        border-radius: var(--whusup-image-radius) !important;
        background: transparent !important;
        cursor: zoom-in !important;
    }

    .recent-post-card .recent-post-image.blurred-post-image {
        filter: blur(14px) !important;
        transform: scale(1.015) !important;
        user-select: none !important;
        pointer-events: none !important;
    }

    .recent-post-card .post-image-carousel-dots {
        height: 14px !important;
        min-height: 14px !important;
        max-height: 14px !important;
        margin-top: 6px !important;
    }
}

</style>

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

    <div id="recent-posts-list"
         data-next-offset="<?= htmlspecialchars($recent_posts_next_offset) ?>"
         data-has-more="<?= $recent_posts_has_more ? '1' : '0' ?>"
         data-sort="<?= htmlspecialchars($sort) ?>"
         data-filter="<?= htmlspecialchars($feed_filter) ?>"
         data-tag="<?= htmlspecialchars($tag_search) ?>">

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

        <?= renderRecentPostCards($recent_posts, $comments_by_post, $post_images_by_post, $user_id, $is_logged_in, $redirect_url) ?>

    <?php endif; ?>

    </div>

    <div id="recent-posts-loading" class="no-posts" style="display:none; margin-top: 14px;">Loading more posts...</div>
    <div id="recent-posts-end" class="no-posts" style="display:none; margin-top: 14px;">No more posts to show.</div>

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
document.addEventListener("DOMContentLoaded", function () {

    function markPostImageOrientation(image) {
        if (!image) {
            return;
        }

        const frame = image.closest(".recent-post-image-frame");
        const slide = image.closest(".recent-post-image-slide");

        function applyOrientationClasses() {
            if (!image.naturalWidth || !image.naturalHeight) {
                return;
            }

            const isPortrait = image.naturalHeight > image.naturalWidth;

            image.classList.toggle("mobile-portrait-image", isPortrait);

            if (frame) {
                frame.classList.toggle("mobile-portrait-frame", isPortrait);
            }

            if (slide) {
                slide.classList.toggle("mobile-portrait-slide", isPortrait);
            }
        }

        if (image.complete && image.naturalWidth) {
            applyOrientationClasses();
        } else {
            image.addEventListener("load", applyOrientationClasses, { once: true });
        }
    }

    document.querySelectorAll(".recent-post-image").forEach(markPostImageOrientation);

    function updatePostImageCarousel(carousel, nextIndex) {
        const track = carousel.querySelector(".recent-post-image-track");
        const slides = Array.from(carousel.querySelectorAll(".recent-post-image-slide"));
        const dots = Array.from(carousel.querySelectorAll(".post-image-carousel-dot"));

        if (!track || !slides.length) {
            return;
        }

        const normalizedIndex = ((nextIndex % slides.length) + slides.length) % slides.length;

        track.style.transform = "translateX(-" + (normalizedIndex * 100) + "%)";
        carousel.dataset.currentIndex = String(normalizedIndex);

        dots.forEach(function (dot, index) {
            dot.classList.toggle("active", index === normalizedIndex);
        });
    }

    function bindPostImageCarousel(carousel) {
        if (!carousel || carousel.dataset.carouselBound === "1") {
            return;
        }

        carousel.dataset.carouselBound = "1";

        const prevButton = carousel.querySelector(".post-image-carousel-prev");
        const nextButton = carousel.querySelector(".post-image-carousel-next");
        const dots = Array.from(carousel.querySelectorAll(".post-image-carousel-dot"));

        dots.forEach(function (dot) {
            dot.addEventListener("click", function (event) {
                event.preventDefault();
                event.stopPropagation();
                const dotIndex = parseInt(dot.dataset.dotIndex, 10) || 0;
                updatePostImageCarousel(carousel, dotIndex);
            });
        });

        let touchStartX = 0;
        let touchStartY = 0;
        let touchEndX = 0;
        let touchEndY = 0;

        if (prevButton) {
            prevButton.addEventListener("click", function (event) {
                event.preventDefault();
                event.stopPropagation();
                const currentIndex = parseInt(carousel.dataset.currentIndex, 10) || 0;
                updatePostImageCarousel(carousel, currentIndex - 1);
            });
        }

        if (nextButton) {
            nextButton.addEventListener("click", function (event) {
                event.preventDefault();
                event.stopPropagation();
                const currentIndex = parseInt(carousel.dataset.currentIndex, 10) || 0;
                updatePostImageCarousel(carousel, currentIndex + 1);
            });
        }

        carousel.addEventListener("touchstart", function (event) {
            if (!event.touches || event.touches.length === 0) {
                return;
            }

            touchStartX = event.touches[0].clientX;
            touchStartY = event.touches[0].clientY;
            touchEndX = touchStartX;
            touchEndY = touchStartY;
        }, { passive: true });

        carousel.addEventListener("touchmove", function (event) {
            if (!event.touches || event.touches.length === 0) {
                return;
            }

            touchEndX = event.touches[0].clientX;
            touchEndY = event.touches[0].clientY;
        }, { passive: true });

        carousel.addEventListener("touchend", function () {
            const deltaX = touchEndX - touchStartX;
            const deltaY = touchEndY - touchStartY;

            if (Math.abs(deltaX) < 45 || Math.abs(deltaX) < Math.abs(deltaY)) {
                return;
            }

            const currentIndex = parseInt(carousel.dataset.currentIndex, 10) || 0;

            if (deltaX < 0) {
                updatePostImageCarousel(carousel, currentIndex + 1);
            } else {
                updatePostImageCarousel(carousel, currentIndex - 1);
            }
        });
    }

    document.querySelectorAll(".recent-post-image-carousel").forEach(bindPostImageCarousel);


    const imageLightbox = document.getElementById("imageLightbox");
    const imageLightboxImage = document.getElementById("imageLightboxImage");
    const imageLightboxClose = document.getElementById("imageLightboxClose");
    const imageLightboxPrev = document.getElementById("imageLightboxPrev");
    const imageLightboxNext = document.getElementById("imageLightboxNext");
    const imageLightboxDots = document.getElementById("imageLightboxDots");

    let lightboxImages = [];
    let lightboxIndex = 0;
    let lightboxTouchStartX = 0;
    let lightboxTouchEndX = 0;

    function renderLightboxDots() {
        if (!imageLightboxDots) {
            return;
        }

        imageLightboxDots.innerHTML = "";

        lightboxImages.forEach(function (_, index) {
            const dot = document.createElement("button");
            dot.type = "button";
            dot.className = "image-lightbox-dot" + (index === lightboxIndex ? " active" : "");
            dot.setAttribute("aria-label", "Show image " + (index + 1));

            dot.addEventListener("click", function () {
                updateImageLightbox(index);
            });

            imageLightboxDots.appendChild(dot);
        });
    }

    function updateImageLightbox(nextIndex) {
        if (!imageLightbox || !imageLightboxImage || !lightboxImages.length) {
            return;
        }

        lightboxIndex = ((nextIndex % lightboxImages.length) + lightboxImages.length) % lightboxImages.length;
        imageLightboxImage.src = lightboxImages[lightboxIndex];

        imageLightbox.classList.toggle("has-multiple", lightboxImages.length > 1);
        imageLightbox.dataset.currentIndex = String(lightboxIndex);

        renderLightboxDots();
    }

    function openImageLightbox(images, startIndex) {
        if (!imageLightbox || !imageLightboxImage) {
            return;
        }

        lightboxImages = Array.isArray(images) ? images.filter(Boolean) : [images].filter(Boolean);

        if (!lightboxImages.length) {
            return;
        }

        lightboxIndex = startIndex || 0;
        imageLightbox.classList.add("open");
        imageLightbox.setAttribute("aria-hidden", "false");
        document.body.classList.add("lightbox-open");

        updateImageLightbox(lightboxIndex);
    }

    function closeImageLightbox() {
        if (!imageLightbox || !imageLightboxImage) {
            return;
        }

        imageLightbox.classList.remove("open", "has-multiple");
        imageLightbox.setAttribute("aria-hidden", "true");
        imageLightboxImage.src = "";
        document.body.classList.remove("lightbox-open");
        lightboxImages = [];
        lightboxIndex = 0;

        if (imageLightboxDots) {
            imageLightboxDots.innerHTML = "";
        }
    }

    function bindPostImageLightbox(image) {
        if (!image || image.dataset.lightboxBound === "1") {
            return;
        }

        image.dataset.lightboxBound = "1";

        image.addEventListener("click", function () {
            const carousel = image.closest(".recent-post-image-carousel");
            const allImages = carousel
                ? Array.from(carousel.querySelectorAll(".recent-post-image")).map(function (img) {
                    return img.dataset.fullImage || img.currentSrc || img.src;
                }).filter(Boolean)
                : [image.dataset.fullImage || image.currentSrc || image.src].filter(Boolean);

            const imageIndex = parseInt(image.dataset.imageIndex, 10) || 0;
            openImageLightbox(allImages, imageIndex);
        });
    }

    function bindProfileLightbox(image) {
        if (!image || image.dataset.lightboxBound === "1") {
            return;
        }

        image.dataset.lightboxBound = "1";

        image.addEventListener("click", function (event) {
            event.stopPropagation();

            openImageLightbox([
                image.dataset.fullImage || image.currentSrc || image.src
            ], 0);
        });
    }

    document.querySelectorAll(".recent-post-image").forEach(bindPostImageLightbox);
    document.querySelectorAll(".profile-expandable").forEach(bindProfileLightbox);

    if (imageLightboxPrev) {
        imageLightboxPrev.addEventListener("click", function (event) {
            event.stopPropagation();
            updateImageLightbox(lightboxIndex - 1);
        });
    }

    if (imageLightboxNext) {
        imageLightboxNext.addEventListener("click", function (event) {
            event.stopPropagation();
            updateImageLightbox(lightboxIndex + 1);
        });
    }

    if (imageLightboxClose) {
        imageLightboxClose.addEventListener("click", closeImageLightbox);
    }

    if (imageLightbox) {
        imageLightbox.addEventListener("click", function (event) {
            if (event.target === imageLightbox) {
                closeImageLightbox();
            }
        });

        imageLightbox.addEventListener("touchstart", function (event) {
            if (!event.touches || event.touches.length === 0) {
                return;
            }

            lightboxTouchStartX = event.touches[0].clientX;
            lightboxTouchEndX = lightboxTouchStartX;
        }, { passive: true });

        imageLightbox.addEventListener("touchmove", function (event) {
            if (!event.touches || event.touches.length === 0) {
                return;
            }

            lightboxTouchEndX = event.touches[0].clientX;
        }, { passive: true });

        imageLightbox.addEventListener("touchend", function () {
            const deltaX = lightboxTouchEndX - lightboxTouchStartX;

            if (Math.abs(deltaX) < 45 || lightboxImages.length <= 1) {
                return;
            }

            if (deltaX < 0) {
                updateImageLightbox(lightboxIndex + 1);
            } else {
                updateImageLightbox(lightboxIndex - 1);
            }
        });
    }

    document.addEventListener("keydown", function (event) {
        if (!imageLightbox || !imageLightbox.classList.contains("open")) {
            return;
        }

        if (event.key === "Escape") {
            closeImageLightbox();
        }

        if (event.key === "ArrowLeft" && lightboxImages.length > 1) {
            updateImageLightbox(lightboxIndex - 1);
        }

        if (event.key === "ArrowRight" && lightboxImages.length > 1) {
            updateImageLightbox(lightboxIndex + 1);
        }
    });

    function escapeHtml(text) {
        const div = document.createElement("div");
        div.textContent = text ?? "";
        return div.innerHTML;
    }

    function linkifyEscapedText(text) {
        const escaped = escapeHtml(text);

        return escaped
            .replace(/(https?:\/\/[^\s<]+)/gi, function (url) {
                let display = url.replace(/^https?:\/\//i, "").replace(/\/$/, "");

                if (display.length > 40) {
                    display = display.substring(0, 37) + "...";
                }

                return '<a href="' + url + '" target="_blank" rel="noopener noreferrer">' + display + '</a>';
            })
            .replace(/\n/g, "<br>");
    }

    function showAjaxMessage(form, message) {
        const box = form.querySelector(".ajax-comment-message");

        if (!box) return;

        box.textContent = message;
        box.style.display = "block";
    }

    function updateCommentCount(postCard, amount) {
        const countSpan = postCard.querySelector(".comment-count");

        if (!countSpan) return;

        const currentCount = parseInt(countSpan.textContent, 10) || 0;
        const newCount = Math.max(0, currentCount + amount);

        countSpan.textContent = newCount;

        const noCommentsText = postCard.querySelector(".no-comments-text");

        if (noCommentsText) {
            noCommentsText.style.display = newCount === 0 ? "" : "none";
        }
    }

    function getReplyButtonText(count, isOpen) {
        const label = count === 1 ? "reply" : "replies";
        return isOpen ? "Hide replies" : "View " + count + " " + label;
    }

    function refreshReplyToggle(button, count, isOpen) {
        button.dataset.count = String(count);
        button.textContent = getReplyButtonText(count, isOpen);
    }

    function attachReplyCollapseToggle(button) {
        if (!button || button.dataset.bound === "1") return;

        button.dataset.bound = "1";

        button.addEventListener("click", function () {
            const targetId = button.dataset.target;
            const section = document.getElementById(targetId);

            if (!section) return;

            section.classList.toggle("open");

            const isOpen = section.classList.contains("open");
            const count = parseInt(button.dataset.count, 10) || section.children.length || 0;

            refreshReplyToggle(button, count, isOpen);
        });
    }

    function ensureReplyContainer(parentCommentBox) {
        const parentCommentId = parentCommentBox.dataset.commentId;
        let children = parentCommentBox.querySelector(":scope > .comment-children");

        if (!children) {
            children = document.createElement("div");
            children.id = "comment-children-" + parentCommentId;
            children.className = "comment-children collapsed-replies";
            parentCommentBox.appendChild(children);
        }

        let toggle = parentCommentBox.querySelector(":scope > .reply-collapse-toggle");

        if (!toggle) {
            toggle = document.createElement("button");
            toggle.type = "button";
            toggle.className = "reply-collapse-toggle";
            toggle.dataset.target = children.id;
            toggle.dataset.count = "0";
            toggle.textContent = "View replies";
            parentCommentBox.insertBefore(toggle, children);
            attachReplyCollapseToggle(toggle);
        }

        children.classList.add("open");
        children.classList.add("collapsed-replies");

        const newCount = children.querySelectorAll(":scope > .comment-box").length + 1;
        refreshReplyToggle(toggle, newCount, true);

        return children;
    }

    function attachReplyToggle(button) {
        if (!button || button.dataset.bound === "1") return;

        button.dataset.bound = "1";

        button.addEventListener("click", function () {
            const targetId = button.dataset.target;
            const section = document.getElementById(targetId);

            if (!section) return;

            section.classList.toggle("open");
        });
    }

    function attachEditToggle(button) {
        if (!button || button.dataset.bound === "1") return;

        button.dataset.bound = "1";

        button.addEventListener("click", function () {
            const targetId = button.getAttribute("data-target");
            const section = document.getElementById(targetId);

            if (!section) return;

            section.classList.toggle("open");

            button.textContent = section.classList.contains("open")
                ? "Cancel"
                : "Edit";
        });
    }

    function attachCommentLikeIndicator(indicator) {
        if (!indicator || indicator.dataset.bound === "1") return;

        indicator.dataset.bound = "1";

        indicator.addEventListener("click", function () {
            const loggedIn = indicator.dataset.loggedIn === "1";

            if (!loggedIn) {
                return;
            }

            const commentId = indicator.dataset.commentId;
            const countSpan = indicator.querySelector(".comment-like-count");

            fetch("ajax/toggle_comment_like.php", {
                method: "POST",
                headers: {
                    "Content-Type": "application/x-www-form-urlencoded"
                },
                body: "comment_id=" + encodeURIComponent(commentId)
            })
            .then(response => response.json())
            .then(function (data) {
                if (!data.success) {
                    console.error("Comment like failed:", data);
                    return;
                }

                if (countSpan) {
                    countSpan.textContent = data.like_count;
                }

                if (data.liked) {
                    indicator.classList.add("liked");
                } else {
                    indicator.classList.remove("liked");
                }
            })
            .catch(function (error) {
                console.error("Comment like error:", error);
            });
        });
    }

    function attachEditPostForm(form) {
        if (!form || form.dataset.bound === "1") return;

        form.dataset.bound = "1";

        form.addEventListener("submit", function (event) {
            event.preventDefault();

            const postCard = form.closest(".recent-post-card");
            const postId = form.querySelector('input[name="post_id"]').value;
            const textarea = form.querySelector('textarea[name="post_body"]');
            const postBody = textarea.value.trim();

            if (postBody === "") {
                alert("Post cannot be empty.");
                return;
            }

            fetch("ajax/edit_post.php", {
                method: "POST",
                headers: {
                    "Content-Type": "application/x-www-form-urlencoded"
                },
                body:
                    "post_id=" + encodeURIComponent(postId) +
                    "&post_body=" + encodeURIComponent(postBody)
            })
            .then(response => response.json())
            .then(function (data) {
                if (!data.success) {
                    alert(data.message || "Edit post failed.");
                    return;
                }

                const postBodyDiv = postCard.querySelector(".recent-post-body");
                const editSection = form.closest(".edit-post-section");
                const editButton = postCard.querySelector(".edit-toggle-button");

                postBodyDiv.innerHTML = linkifyEscapedText(postBody);
                editSection.classList.remove("open");

                if (editButton) {
                    editButton.textContent = "Edit";
                }
            })
            .catch(function (error) {
                console.error("Edit post error:", error);
                alert("Edit post failed.");
            });
        });
    }

    function attachEditCommentForm(form) {
        if (!form || form.dataset.bound === "1") return;

        form.dataset.bound = "1";

        form.addEventListener("submit", function (event) {
            event.preventDefault();

            const commentId = form.querySelector('input[name="comment_id"]').value;
            const textarea = form.querySelector('textarea[name="comment_body"]');
            const commentBody = textarea.value.trim();

            if (commentBody === "") {
                alert("Comment cannot be empty.");
                return;
            }

            fetch("ajax/edit_comment.php", {
                method: "POST",
                headers: {
                    "Content-Type": "application/x-www-form-urlencoded"
                },
                body:
                    "comment_id=" + encodeURIComponent(commentId) +
                    "&comment_body=" + encodeURIComponent(commentBody)
            })
            .then(response => response.json())
            .then(function (data) {
                if (!data.success) {
                    alert(data.message || "Edit failed.");
                    return;
                }

                const commentBox = form.closest(".comment-box");
                const commentBodyDiv = commentBox.querySelector(".comment-body");
                const editSection = form.closest(".edit-comment-section");
                const toggleButton = commentBox.querySelector(".edit-comment-toggle-button");

                commentBodyDiv.innerHTML = linkifyEscapedText(commentBody);
                editSection.classList.remove("open");

                if (toggleButton) {
                    toggleButton.textContent = "Edit";
                }
            })
            .catch(function (error) {
                console.error("Edit comment error:", error);
                alert("Edit failed.");
            });
        });
    }

    function attachDeleteCommentButton(button) {
        if (!button || button.dataset.bound === "1") return;

        button.dataset.bound = "1";

        button.addEventListener("click", function () {
            if (!confirm("Are you sure you want to delete this comment?")) {
                return;
            }

            const commentId = button.dataset.commentId;
            const commentBox = button.closest(".comment-box");
            const parentCommentBox = commentBox.parentElement?.closest(".comment-box");
            const postCard = button.closest(".recent-post-card");

            fetch("ajax/delete_comment.php", {
                method: "POST",
                headers: {
                    "Content-Type": "application/x-www-form-urlencoded"
                },
                body: "comment_id=" + encodeURIComponent(commentId)
            })
            .then(response => response.json())
            .then(function (data) {
                if (!data.success) {
                    alert(data.message || "Delete failed.");
                    return;
                }

                const nestedCount = commentBox.querySelectorAll(".comment-box").length;
                const removedCount = nestedCount + 1;

                commentBox.remove();
                updateCommentCount(postCard, -removedCount);

                if (parentCommentBox) {
                    const children = parentCommentBox.querySelector(":scope > .comment-children");
                    const toggle = parentCommentBox.querySelector(":scope > .reply-collapse-toggle");

                    if (children && toggle) {
                        const childCount = children.querySelectorAll(":scope > .comment-box").length;

                        if (childCount <= 0) {
                            toggle.remove();
                            children.classList.remove("collapsed-replies", "open");
                        } else {
                            refreshReplyToggle(toggle, childCount, children.classList.contains("open"));
                        }
                    }
                }
            })
            .catch(function (error) {
                console.error("Delete comment error:", error);
                alert("Delete failed.");
            });
        });
    }

    function createCommentBoxHtml(commentId, postId, parentCommentId, commentBody, name) {
        return `
            <div class="comment-name">${escapeHtml(name)}</div>

            <div class="comment-date">Just now</div>

            <div class="comment-body">${linkifyEscapedText(commentBody)}</div>

            <div class="comment-owner-actions">
                <span
                    class="like-indicator comment-like-indicator ajax-comment-like-indicator can-like"
                    data-comment-id="${commentId}"
                    data-logged-in="1"
                    title="Like comment"
                >
                    <span class="like-icon">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M7 10v10H4V10h3zm3 10h7.4c.9 0 1.7-.6 1.9-1.5l1.4-6.2c.3-1.2-.6-2.3-1.9-2.3h-5.1l.8-3.8c.1-.6-.1-1.2-.5-1.6L13.2 4 9 9.1V20z"/>
                        </svg>
                    </span>
                    <span class="like-count comment-like-count">0</span>
                </span>

                <button
                    type="button"
                    class="comment-action-link comment-reply-link reply-toggle-button"
                    data-target="reply-form-${commentId}"
                >
                    Reply
                </button>

                <button 
                    type="button" 
                    class="comment-action-link comment-edit-link edit-comment-toggle-button"
                    data-target="edit-comment-${commentId}"
                >
                    Edit
                </button>

                <button 
                    type="button" 
                    class="comment-action-link comment-delete-link ajax-delete-comment-button"
                    data-comment-id="${commentId}"
                >
                    Delete
                </button>
            </div>

            <div id="edit-comment-${commentId}" class="edit-comment-section">
                <form class="edit-comment-form ajax-edit-comment-form">
                    <input type="hidden" name="comment_id" value="${commentId}">
                    <textarea name="comment_body">${escapeHtml(commentBody)}</textarea>

                    <button type="submit" class="recent-post-button small-action-button">
                        Save
                    </button>
                </form>
            </div>

            <div id="reply-form-${commentId}" class="reply-form-section">
                <form class="comment-form ajax-add-comment-form">
                    <input type="hidden" name="post_id" value="${postId}">
                    <input type="hidden" name="parent_comment_id" value="${commentId}">

                    <textarea name="comment_body" placeholder="Write a reply..."></textarea>

                    <button type="submit" class="recent-post-button small-action-button">
                        Reply
                    </button>

                    <div class="ajax-comment-message"></div>
                </form>
            </div>

            <div id="comment-children-${commentId}" class="comment-children"></div>
        `;
    }

    function bindCommentBox(commentBox) {
        attachCommentLikeIndicator(commentBox.querySelector(".ajax-comment-like-indicator"));
        attachReplyToggle(commentBox.querySelector(".reply-toggle-button"));
        attachEditToggle(commentBox.querySelector(".edit-comment-toggle-button"));
        attachEditCommentForm(commentBox.querySelector(".ajax-edit-comment-form"));
        attachDeleteCommentButton(commentBox.querySelector(".ajax-delete-comment-button"));
        attachAddCommentForm(commentBox.querySelector(".ajax-add-comment-form"));
        attachReplyCollapseToggle(commentBox.querySelector(".reply-collapse-toggle"));
    }

    function attachAddCommentForm(form) {
        if (!form || form.dataset.bound === "1") return;

        form.dataset.bound = "1";

        form.addEventListener("submit", function (event) {
            event.preventDefault();

            const postCard = form.closest(".recent-post-card");
            const parentCommentBox = form.closest(".comment-box");
            const postId = form.querySelector('input[name="post_id"]').value;
            const parentCommentId = form.querySelector('input[name="parent_comment_id"]')?.value || "";
            const textarea = form.querySelector('textarea[name="comment_body"]');
            const commentBody = textarea.value.trim();

            if (commentBody === "") {
                showAjaxMessage(form, "Comment cannot be empty.");
                return;
            }

            fetch("ajax/add_comment.php", {
                method: "POST",
                headers: {
                    "Content-Type": "application/x-www-form-urlencoded"
                },
                body:
                    "post_id=" + encodeURIComponent(postId) +
                    "&comment_body=" + encodeURIComponent(commentBody) +
                    "&parent_comment_id=" + encodeURIComponent(parentCommentId)
            })
            .then(response => response.json())
            .then(function (data) {
                if (!data.success) {
                    showAjaxMessage(form, data.message || "Comment failed.");
                    return;
                }

                const commentId = data.comment_id;
                const isReply = parentCommentId !== "";

                const commentBox = document.createElement("div");
                commentBox.className = isReply ? "comment-box comment-reply" : "comment-box";
                commentBox.dataset.commentId = commentId;
                commentBox.dataset.parentCommentId = parentCommentId;
                commentBox.innerHTML = createCommentBoxHtml(commentId, postId, parentCommentId, commentBody, data.name || "You");

                if (isReply && parentCommentBox) {
                    const children = ensureReplyContainer(parentCommentBox);
                    children.appendChild(commentBox);

                    const replySection = form.closest(".reply-form-section");

                    if (replySection) {
                        replySection.classList.remove("open");
                    }
                } else {
                    const commentList = postCard.querySelector(".comment-list");
                    commentList.appendChild(commentBox);
                }

                bindCommentBox(commentBox);

                textarea.value = "";

                const messageBox = form.querySelector(".ajax-comment-message");
                if (messageBox) {
                    messageBox.style.display = "none";
                    messageBox.textContent = "";
                }

                updateCommentCount(postCard, 1);
            })
            .catch(function (error) {
                console.error("Add comment error:", error);
                showAjaxMessage(form, "Comment failed.");
            });
        });
    }

    function attachPostLikeIndicator(indicator) {
        if (!indicator || indicator.dataset.bound === "1") return;

        indicator.dataset.bound = "1";

        indicator.addEventListener("click", function () {
            const loggedIn = indicator.dataset.loggedIn === "1";

            if (!loggedIn) {
                return;
            }

            const postId = indicator.dataset.postId;
            const countSpan = indicator.querySelector(".like-count");

            fetch("ajax/toggle_like.php", {
                method: "POST",
                headers: {
                    "Content-Type": "application/x-www-form-urlencoded"
                },
                body: "post_id=" + encodeURIComponent(postId)
            })
            .then(response => response.json())
            .then(function (data) {
                if (!data.success) {
                    console.error("Like failed:", data);
                    return;
                }

                countSpan.textContent = data.like_count;

                if (data.liked) {
                    indicator.classList.add("liked");
                } else {
                    indicator.classList.remove("liked");
                }
            })
            .catch(function (error) {
                console.error("Like error:", error);
            });
        });
    }

    document.querySelectorAll(".ajax-like-indicator").forEach(attachPostLikeIndicator);

    function attachCommentIconToggle(button) {
        if (!button || button.dataset.bound === "1") return;

        button.dataset.bound = "1";

        button.addEventListener("click", function () {
            const targetId = button.getAttribute("data-target");
            const section = document.getElementById(targetId);

            if (!section) return;

            section.classList.toggle("open");
        });
    }

    document.querySelectorAll(".comment-icon-toggle").forEach(attachCommentIconToggle);

    function initializeRecentPostCard(postCard) {
        if (!postCard) return;


        postCard.querySelectorAll(".recent-post-image").forEach(function (image) {
            markPostImageOrientation(image);
            bindPostImageLightbox(image);
        });

        postCard.querySelectorAll(".recent-post-image-carousel").forEach(bindPostImageCarousel);
        postCard.querySelectorAll(".profile-expandable").forEach(bindProfileLightbox);
        postCard.querySelectorAll(".ajax-like-indicator").forEach(attachPostLikeIndicator);
        postCard.querySelectorAll(".comment-icon-toggle").forEach(attachCommentIconToggle);
        postCard.querySelectorAll(".reply-collapse-toggle").forEach(attachReplyCollapseToggle);
        postCard.querySelectorAll(".reply-toggle-button").forEach(attachReplyToggle);
        postCard.querySelectorAll(".edit-toggle-button").forEach(attachEditToggle);
        postCard.querySelectorAll(".edit-comment-toggle-button").forEach(attachEditToggle);
        postCard.querySelectorAll(".ajax-comment-like-indicator").forEach(attachCommentLikeIndicator);
        postCard.querySelectorAll(".ajax-edit-post-form").forEach(attachEditPostForm);
        postCard.querySelectorAll(".ajax-edit-comment-form").forEach(attachEditCommentForm);
        postCard.querySelectorAll(".ajax-delete-comment-button").forEach(attachDeleteCommentButton);
        postCard.querySelectorAll(".ajax-add-comment-form").forEach(attachAddCommentForm);
    }

    function initializeInfiniteScroll() {
        const postsList = document.getElementById("recent-posts-list");
        const loadingBox = document.getElementById("recent-posts-loading");
        const endBox = document.getElementById("recent-posts-end");

        if (!postsList) return;

        let isLoading = false;
        let hasMore = postsList.dataset.hasMore === "1";

        function setLoading(isActive) {
            isLoading = isActive;
            if (loadingBox) {
                loadingBox.style.display = isActive ? "block" : "none";
            }
        }

        function maybeShowEnd() {
            if (!hasMore && endBox && postsList.querySelectorAll(".recent-post-card").length > 0) {
                endBox.style.display = "block";
            }
        }

        function loadMorePosts() {
            if (isLoading || !hasMore) return;

            const scrollPosition = window.innerHeight + window.scrollY;
            const triggerPosition = document.documentElement.scrollHeight - 700;

            if (scrollPosition < triggerPosition) return;

            setLoading(true);

            const params = new URLSearchParams();
            params.set("offset", postsList.dataset.nextOffset || "0");
            params.set("sort", postsList.dataset.sort || "recent");
            params.set("filter", postsList.dataset.filter || "all");

            if (postsList.dataset.tag) {
                params.set("tag", postsList.dataset.tag);
            }

            fetch("ajax/load_more_posts.php?" + params.toString(), {
                method: "GET",
                headers: {
                    "Accept": "application/json"
                }
            })
            .then(function (response) {
                return response.json();
            })
            .then(function (data) {
                if (!data.success) {
                    console.error("Load more posts failed:", data);
                    hasMore = false;
                    postsList.dataset.hasMore = "0";
                    maybeShowEnd();
                    return;
                }

                const temp = document.createElement("div");
                temp.innerHTML = data.html || "";

                const newCards = Array.from(temp.querySelectorAll(".recent-post-card"));

                newCards.forEach(function (card) {
                    postsList.appendChild(card);
                    initializeRecentPostCard(card);
                });

                postsList.dataset.nextOffset = String(data.next_offset || postsList.querySelectorAll(".recent-post-card").length);
                hasMore = data.has_more === true || data.has_more === 1 || data.has_more === "1";
                postsList.dataset.hasMore = hasMore ? "1" : "0";

                maybeShowEnd();
            })
            .catch(function (error) {
                console.error("Load more posts error:", error);
            })
            .finally(function () {
                setLoading(false);
            });
        }

        window.addEventListener("scroll", loadMorePosts, { passive: true });
        window.addEventListener("resize", loadMorePosts);
        loadMorePosts();
    }

    document.querySelectorAll(".reply-collapse-toggle").forEach(attachReplyCollapseToggle);
    document.querySelectorAll(".reply-toggle-button").forEach(attachReplyToggle);
    document.querySelectorAll(".edit-toggle-button").forEach(attachEditToggle);
    document.querySelectorAll(".edit-comment-toggle-button").forEach(attachEditToggle);
    document.querySelectorAll(".ajax-comment-like-indicator").forEach(attachCommentLikeIndicator);
    document.querySelectorAll(".ajax-edit-post-form").forEach(attachEditPostForm);
    document.querySelectorAll(".ajax-edit-comment-form").forEach(attachEditCommentForm);
    document.querySelectorAll(".ajax-delete-comment-button").forEach(attachDeleteCommentButton);
    document.querySelectorAll(".ajax-add-comment-form").forEach(attachAddCommentForm);

    document.querySelectorAll(".recent-post-card").forEach(initializeRecentPostCard);
    window.addEventListener("resize", function () {
    });

    initializeInfiniteScroll();
});
</script>
