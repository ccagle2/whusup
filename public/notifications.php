<?php

require_once '../includes/auth.php';
require_login();

require_once '../config/database.php';

$user_id = $_SESSION['user_id'];

$total_posts = 0;
$total_likes = 0;
$total_comments = 0;
$total_followers = 0;

$new_likes = 0;
$new_comments = 0;
$new_followers = 0;
$new_notifications = 0;

$last_like_date = null;
$last_comment_date = null;
$last_follower_date = null;
$last_read_at = null;

$error_message = "";

function time_ago($date) {
    if (empty($date)) {
        return "No activity yet";
    }

    $timestamp = strtotime($date);
    $diff = time() - $timestamp;

    if ($diff < 60) {
        return "Just now";
    }

    if ($diff < 3600) {
        $minutes = floor($diff / 60);
        return $minutes . " minute" . ($minutes !== 1 ? "s" : "") . " ago";
    }

    if ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . " hour" . ($hours !== 1 ? "s" : "") . " ago";
    }

    if ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . " day" . ($days !== 1 ? "s" : "") . " ago";
    }

    return date("M j, Y", $timestamp);
}

try {

    /*
        Get the previous read timestamp before updating it.
        This allows the "New Notifications" section to show what happened
        since the user's last notifications-page visit.
    */
    $stmt = $pdo->prepare("
        SELECT last_read_at
        FROM user_notification_reads
        WHERE user_id = :user_id
        LIMIT 1
    ");

    $stmt->execute([
        ':user_id' => $user_id
    ]);

    $last_read_at = $stmt->fetchColumn();

    if (!$last_read_at) {
        $last_read_at = '1970-01-01 00:00:00';
    }

    /*
        Overall Statistics
        Total Posts = posts created by the logged-in user.
    */
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM posts
        WHERE user_id = :user_id
    ");

    $stmt->execute([
        ':user_id' => $user_id
    ]);

    $total_posts = (int) $stmt->fetchColumn();

    /*
        Total Likes = all likes from other users on the logged-in user's posts.
        Last Like = most recent like from another user on any of the logged-in user's posts.
    */
    $stmt = $pdo->prepare("
        SELECT
            COUNT(*) AS total_likes,
            MAX(pl.created_at) AS last_like_date
        FROM post_likes pl
        INNER JOIN posts p
            ON p.id = pl.post_id
        WHERE p.user_id = :owner_user_id
        AND pl.user_id <> :actor_user_id
    ");

    $stmt->execute([
        ':owner_user_id' => $user_id,
        ':actor_user_id' => $user_id
    ]);

    $likes_data = $stmt->fetch(PDO::FETCH_ASSOC);

    $total_likes = (int) ($likes_data['total_likes'] ?? 0);
    $last_like_date = $likes_data['last_like_date'] ?? null;

    /*
        Total Comments = all comments from other users on the logged-in user's posts.
        Last Comment = most recent comment from another user on any of the logged-in user's posts.
    */
    $stmt = $pdo->prepare("
        SELECT
            COUNT(*) AS total_comments,
            MAX(pc.created_at) AS last_comment_date
        FROM post_comments pc
        INNER JOIN posts p
            ON p.id = pc.post_id
        WHERE p.user_id = :owner_user_id
        AND pc.user_id <> :actor_user_id
    ");

    $stmt->execute([
        ':owner_user_id' => $user_id,
        ':actor_user_id' => $user_id
    ]);

    $comments_data = $stmt->fetch(PDO::FETCH_ASSOC);

    $total_comments = (int) ($comments_data['total_comments'] ?? 0);
    $last_comment_date = $comments_data['last_comment_date'] ?? null;

    /*
        Followers = users following the logged-in user.
        Last New Follower = latest accepted friendship where the logged-in user is the followed user.
    */
    $stmt = $pdo->prepare("
        SELECT
            COUNT(*) AS total_followers,
            MAX(created_at) AS last_follower_date
        FROM friendships
        WHERE friend_id = :user_id
        AND user_id <> :actor_user_id
        AND status = 'accepted'
    ");

    $stmt->execute([
        ':user_id' => $user_id,
        ':actor_user_id' => $user_id
    ]);

    $followers_data = $stmt->fetch(PDO::FETCH_ASSOC);

    $total_followers = (int) ($followers_data['total_followers'] ?? 0);
    $last_follower_date = $followers_data['last_follower_date'] ?? null;

    /*
        New Notifications
        These match the navbar badge logic:
        - new non-user likes on the user's posts
        - new non-user comments on the user's posts
        - new followers
    */
    $stmt = $pdo->prepare("
        SELECT
            (
                SELECT COUNT(*)
                FROM post_likes pl
                INNER JOIN posts p
                    ON p.id = pl.post_id
                WHERE p.user_id = :likes_owner
                AND pl.user_id <> :likes_actor
                AND pl.created_at > :last_read_likes
            ) AS new_likes,

            (
                SELECT COUNT(*)
                FROM post_comments pc
                INNER JOIN posts p
                    ON p.id = pc.post_id
                WHERE p.user_id = :comments_owner
                AND pc.user_id <> :comments_actor
                AND pc.created_at > :last_read_comments
            ) AS new_comments,

            (
                SELECT COUNT(*)
                FROM friendships f
                WHERE f.friend_id = :followed_user
                AND f.user_id <> :follower_user
                AND f.status = 'accepted'
                AND f.created_at > :last_read_follows
            ) AS new_followers
    ");

    $stmt->execute([
        ':likes_owner' => $user_id,
        ':likes_actor' => $user_id,
        ':last_read_likes' => $last_read_at,

        ':comments_owner' => $user_id,
        ':comments_actor' => $user_id,
        ':last_read_comments' => $last_read_at,

        ':followed_user' => $user_id,
        ':follower_user' => $user_id,
        ':last_read_follows' => $last_read_at
    ]);

    $new_data = $stmt->fetch(PDO::FETCH_ASSOC);

    $new_likes = (int) ($new_data['new_likes'] ?? 0);
    $new_comments = (int) ($new_data['new_comments'] ?? 0);
    $new_followers = (int) ($new_data['new_followers'] ?? 0);
    $new_notifications = $new_likes + $new_comments + $new_followers;

    /*
        Mark notifications as read before rendering the navbar.
        This lets the bell count reset to zero after this page opens.
    */
    $stmt = $pdo->prepare("
        INSERT INTO user_notification_reads (user_id, last_read_at)
        VALUES (:user_id, NOW())
        ON DUPLICATE KEY UPDATE last_read_at = NOW()
    ");

    $stmt->execute([
        ':user_id' => $user_id
    ]);

} catch (PDOException $e) {
    $error_message = "Unable to load notifications: " . $e->getMessage();
}

include '../includes/header.php';
include '../includes/navbar.php';

?>

<style>
.notifications-page {
    width: 100%;
    min-height: 100vh;
    background: #f2f4f8;
    padding: 30px 20px 50px;
    box-sizing: border-box;
}

.notifications-card {
    width: 100%;
    max-width: 850px;
    margin: 0 auto;
    background: #ffffff;
    border-radius: 18px;
    padding: 34px;
    box-shadow: 0 8px 28px rgba(0,0,0,0.08);
}

.notifications-title {
    text-align: center;
    font-family: "Poppins", sans-serif;
    font-size: 30px;
    font-weight: 700;
    color: #111827;
    margin-bottom: 8px;
}

.notifications-subtitle {
    text-align: center;
    color: #6b7280;
    margin-bottom: 28px;
}

.notifications-section-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
    margin: 26px 0 14px;
    padding-bottom: 10px;
    border-bottom: 1px solid #e5e7eb;
}

.notifications-section-title {
    font-family: "Poppins", sans-serif;
    font-size: 17px;
    font-weight: 800;
    color: #111827;
    margin: 0;
}

.notifications-section-kicker {
    font-size: 12px;
    font-weight: 800;
    color: #6b7280;
    background: #f3f4f6;
    border: 1px solid #e5e7eb;
    border-radius: 999px;
    padding: 6px 11px;
    white-space: nowrap;
}

.notifications-section-description {
    color: #6b7280;
    font-size: 14px;
    margin: -4px 0 16px;
    line-height: 1.5;
}

.notifications-new-summary {
    background: #111827;
    color: #ffffff;
    border-radius: 16px;
    padding: 20px;
    margin-bottom: 22px;
    text-align: center;
}

.notifications-new-number {
    font-size: 38px;
    font-weight: 900;
    line-height: 1;
    margin-bottom: 6px;
}

.notifications-new-label {
    font-size: 14px;
    font-weight: 700;
    color: #d1d5db;
}

.notifications-mini-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 12px;
    margin-top: 16px;
}

.notifications-mini-stat {
    background: rgba(255,255,255,0.08);
    border: 1px solid rgba(255,255,255,0.12);
    border-radius: 12px;
    padding: 12px;
}

.notifications-mini-number {
    font-size: 22px;
    font-weight: 800;
}

.notifications-mini-label {
    font-size: 12px;
    color: #d1d5db;
    font-weight: 700;
}

.notifications-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
    margin-bottom: 24px;
}

.notification-stat {
    background: #f9fafb;
    border: 1px solid #e5e7eb;
    border-radius: 14px;
    padding: 22px;
    text-align: center;
}

.notification-number {
    font-size: 34px;
    font-weight: 800;
    color: #111827;
    margin-bottom: 6px;
}

.notification-label {
    font-size: 14px;
    color: #6b7280;
    font-weight: 700;
}

.notifications-activity {
    background: #f9fafb;
    border: 1px solid #e5e7eb;
    border-radius: 14px;
    padding: 20px;
    margin-top: 16px;
}

.activity-row {
    display: flex;
    justify-content: space-between;
    gap: 16px;
    padding: 12px 0;
    border-bottom: 1px solid #e5e7eb;
}

.activity-row:last-child {
    border-bottom: none;
}

.activity-label {
    font-weight: 700;
    color: #374151;
}

.activity-date {
    color: #6b7280;
    text-align: right;
}

.notifications-actions {
    display: flex;
    justify-content: center;
    margin-top: 28px;
}

.back-button {
    border: 1px solid #d1d5db;
    background: #ffffff;
    color: #374151;
    border-radius: 999px;
    padding: 10px 26px;
    font-size: 14px;
    font-weight: 700;
    cursor: pointer;
    text-decoration: none;
}

.back-button:hover {
    background: #f3f4f6;
}

.notifications-error {
    background: #fee2e2;
    color: #991b1b;
    padding: 12px;
    border-radius: 10px;
    text-align: center;
    font-weight: 700;
    margin-bottom: 18px;
}

@media (max-width: 700px) {
    .notifications-page {
        padding: 14px 10px 35px;
    }

    .notifications-card {
        padding: 22px;
        border-radius: 14px;
    }

    .notifications-grid,
    .notifications-mini-grid {
        grid-template-columns: 1fr;
    }

    .activity-row {
        flex-direction: column;
        gap: 4px;
    }

    .activity-date {
        text-align: left;
    }

    .notifications-section-header {
        align-items: flex-start;
        flex-direction: column;
        gap: 8px;
    }

    .notifications-section-kicker {
        white-space: normal;
    }

    .back-button {
        width: 100%;
        text-align: center;
    }
}
</style>

<main class="notifications-page">

    <section class="notifications-card">

        <h1 class="notifications-title">Notifications</h1>

        <div class="notifications-subtitle">
            View a summary of your account activity.
        </div>

        <?php if (!empty($error_message)): ?>
            <div class="notifications-error">
                <?= htmlspecialchars($error_message) ?>
            </div>
        <?php endif; ?>

        <div class="notifications-section-header">
            <h2 class="notifications-section-title">New Notifications</h2>
            <span class="notifications-section-kicker">Since your last visit</span>
        </div>

        <p class="notifications-section-description">
            These are recent likes, comments, and followers that happened since you last opened this page.
        </p>

        <div class="notifications-new-summary">
            <div class="notifications-new-number">
                <?= htmlspecialchars((string) $new_notifications) ?>
            </div>

            <div class="notifications-new-label">
                New notifications
            </div>

            <div class="notifications-mini-grid">
                <div class="notifications-mini-stat">
                    <div class="notifications-mini-number">
                        <?= htmlspecialchars((string) $new_likes) ?>
                    </div>
                    <div class="notifications-mini-label">New Likes</div>
                </div>

                <div class="notifications-mini-stat">
                    <div class="notifications-mini-number">
                        <?= htmlspecialchars((string) $new_comments) ?>
                    </div>
                    <div class="notifications-mini-label">New Comments</div>
                </div>

                <div class="notifications-mini-stat">
                    <div class="notifications-mini-number">
                        <?= htmlspecialchars((string) $new_followers) ?>
                    </div>
                    <div class="notifications-mini-label">New Followers</div>
                </div>
            </div>
        </div>

        <div class="notifications-section-header">
            <h2 class="notifications-section-title">Overall Statistics</h2>
            <span class="notifications-section-kicker">Lifetime account totals</span>
        </div>

        <p class="notifications-section-description">
            These totals show your posts, followers, and other users’ engagement with your posts.
        </p>

        <div class="notifications-grid">

            <div class="notification-stat">
                <div class="notification-number">
                    <?= htmlspecialchars((string) $total_posts) ?>
                </div>
                <div class="notification-label">Total Posts</div>
            </div>

            <div class="notification-stat">
                <div class="notification-number">
                    <?= htmlspecialchars((string) $total_likes) ?>
                </div>
                <div class="notification-label">Likes</div>
            </div>

            <div class="notification-stat">
                <div class="notification-number">
                    <?= htmlspecialchars((string) $total_comments) ?>
                </div>
                <div class="notification-label">Comments</div>
            </div>

            <div class="notification-stat">
                <div class="notification-number">
                    <?= htmlspecialchars((string) $total_followers) ?>
                </div>
                <div class="notification-label">Followers</div>
            </div>

        </div>

        <div class="notifications-section-header">
            <h2 class="notifications-section-title">Recent Activity</h2>
            <span class="notifications-section-kicker">Latest activity dates</span>
        </div>

        <p class="notifications-section-description">
            A quick look at the most recent like, comment, and follower activity on your account.
        </p>

        <div class="notifications-activity">

            <div class="activity-row">
                <div class="activity-label">Last Like</div>
                <div class="activity-date">
                    <?= htmlspecialchars(time_ago($last_like_date)) ?>
                </div>
            </div>

            <div class="activity-row">
                <div class="activity-label">Last Comment</div>
                <div class="activity-date">
                    <?= htmlspecialchars(time_ago($last_comment_date)) ?>
                </div>
            </div>

            <div class="activity-row">
                <div class="activity-label">Last New Follower</div>
                <div class="activity-date">
                    <?= htmlspecialchars(time_ago($last_follower_date)) ?>
                </div>
            </div>

        </div>

        <div class="notifications-actions">
            <button type="button" class="back-button" onclick="goBackOrDashboard()">
                Back
            </button>
        </div>

    </section>

</main>

<script>
function goBackOrDashboard() {
    if (document.referrer && document.referrer !== window.location.href) {
        window.history.back();
    } else {
        window.location.href = "/dashboard.php";
    }
}
</script>

<?php
include '../includes/footer.php';
?>
