<?php

require_once '../includes/auth.php';
require_login();

include '../includes/header.php';
include '../includes/navbar.php';

$page = $_GET['page'] ?? 'social_feed';
$sort = $_GET['sort'] ?? 'recent';
$feed_filter = $_GET['filter'] ?? 'all';

if (!in_array($page, ['social_feed', 'manage_friends', 'post'], true)) {
    $page = 'social_feed';
}

if (!in_array($sort, ['recent', 'popular'], true)) {
    $sort = 'recent';
}

if (!in_array($feed_filter, ['all', 'following', 'my_posts'], true)) {
    $feed_filter = 'all';
}

function dashboardUrl($page_value = 'social_feed', $filter_value = null, $sort_value = null) {
    $query = [
        'page' => $page_value
    ];

    if ($filter_value !== null && $filter_value !== 'all') {
        $query['filter'] = $filter_value;
    }

    if ($sort_value !== null && $sort_value !== 'recent') {
        $query['sort'] = $sort_value;
    }

    return 'dashboard.php?' . http_build_query($query);
}

?>

<style>
.dashboard-top {
    width: 100%;
    max-width: 900px;
    margin: 24px auto 10px;
    padding: 0 20px;
    text-align: center;
    box-sizing: border-box;
}

.dashboard-welcome {
    font-family: "Poppins", sans-serif;
    font-size: 20px;
    font-weight: 600;
    color: #6b7280;
    margin-bottom: 14px;
}

.dashboard-button-scroll {
    width: 100%;
    overflow-x: auto;
    overflow-y: hidden;
    -webkit-overflow-scrolling: touch;
    scrollbar-width: thin;
    padding-bottom: 8px;
    margin-bottom: 12px;
}

.dashboard-button-scroll::-webkit-scrollbar {
    height: 6px;
}

.dashboard-button-scroll::-webkit-scrollbar-track {
    background: #f3f4f6;
    border-radius: 999px;
}

.dashboard-button-scroll::-webkit-scrollbar-thumb {
    background: #d1d5db;
    border-radius: 999px;
}

.dashboard-button-row {
    width: max-content;
    min-width: 100%;
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 10px;
    flex-wrap: nowrap;
    box-sizing: border-box;
}

.dashboard-divider {
    width: 100%;
    height: 1px;
    background: #e5e7eb;
    border-radius: 999px;
    margin-bottom: 0;
}

.dashboard-action-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex: 0 0 auto;
    background: #f3f4f6;
    color: #4b5563;
    border: 1px solid #d1d5db;
    padding: 9px 24px;
    border-radius: 999px;
    font-size: 14px;
    font-weight: 700;
    text-decoration: none;
    transition: 0.2s ease;
    text-align: center;
    box-sizing: border-box;
    white-space: nowrap;
}

.dashboard-action-button:hover {
    background: #e5e7eb;
    color: #111827;
    border-color: #cbd5e1;
}

.dashboard-action-button-active {
    background: #111827;
    color: #ffffff;
    border-color: #111827;
}

.dashboard-action-button-active:hover {
    background: #374151;
    color: #ffffff;
    border-color: #374151;
}

.dashboard-post-button {
    background: #fef2f2;
    color: #850101;
    border-color: #850101;
}

.dashboard-post-button:hover {
    background: #fecaca;
    color: #6b0000;
    border-color: #6b0000;
}

.dashboard-follow-button {
    background: #fffbeb;
    color: #b45309;
    border-color: #b45309;
}

.dashboard-follow-button:hover {
    background: #fde68a;
    color: #92400e;
    border-color: #92400e;
}

@media (max-width: 700px) {

    .dashboard-top {
        max-width: none;
        width: 100%;
        margin: 12px auto 8px;
        padding: 0 10px;
    }

    .dashboard-button-row {
        justify-content: flex-start;
        gap: 8px;
        padding: 0 2px;
    }

    .dashboard-action-button {
        padding: 9px 18px;
        font-size: 13px;
    }

}
</style>

<?php 
if ($page === 'social_feed'): 
$first_name = explode(' ', trim($_SESSION['user_name'] ?? 'User'))[0];
?>
    
    <div class="dashboard-top">

        <div class="dashboard-welcome">
            Welcome back <?= htmlspecialchars($first_name) ?>!
        </div>

        <div class="dashboard-button-scroll" aria-label="Dashboard actions and filters">
            <div class="dashboard-button-row">

                <a 
                    href="<?= htmlspecialchars(dashboardUrl('post', $feed_filter, $sort)) ?>" 
                    class="dashboard-action-button dashboard-post-button"
                >
                    Post
                </a>

                <a 
                    href="<?= htmlspecialchars(dashboardUrl('manage_friends', $feed_filter, $sort)) ?>" 
                    class="dashboard-action-button dashboard-follow-button"
                >
                    Find Friends
                </a>

                <a 
                    href="<?= htmlspecialchars(dashboardUrl('social_feed', 'all', $sort)) ?>"
                    class="dashboard-action-button <?= $feed_filter === 'all' ? 'dashboard-action-button-active' : '' ?>"
                >
                    All Posts
                </a>

                <a 
                    href="<?= htmlspecialchars(dashboardUrl('social_feed', 'following', $sort)) ?>"
                    class="dashboard-action-button <?= $feed_filter === 'following' ? 'dashboard-action-button-active' : '' ?>"
                >
                    Following
                </a>

                <a 
                    href="<?= htmlspecialchars(dashboardUrl('social_feed', 'my_posts', $sort)) ?>"
                    class="dashboard-action-button <?= $feed_filter === 'my_posts' ? 'dashboard-action-button-active' : '' ?>"
                >
                    My Posts
                </a>

            </div>
        </div>

        <div class="dashboard-divider"></div>

    </div>

<?php endif; ?>

<?php

switch ($page) {

    case 'manage_friends':
        include '../includes/manage_friends.php';
        break;

    case 'post':
        include '../includes/post.php';
        break;

    case 'social_feed':
    default:
        include '../includes/recent_posts.php';
        break;
}

?>

<?php
include '../includes/footer.php';
?>
