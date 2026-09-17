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

function dashboardUrl(
    $page_value = 'social_feed',
    $filter_value = null,
    $sort_value = null
) {
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
    box-sizing: border-box;
}

.dashboard-welcome {
    margin-bottom: 16px;

    color: #6b7280;

    font-family: "Poppins", sans-serif;
    font-size: 20px;
    font-weight: 600;

    text-align: center;
}


/*
|--------------------------------------------------------------------------
| Dashboard controls
|--------------------------------------------------------------------------
*/

.dashboard-controls {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;

    gap: 24px;

    width: 100%;
    margin-bottom: 14px;
}


/*
|--------------------------------------------------------------------------
| Control groups
|--------------------------------------------------------------------------
*/

.dashboard-control-group {
    display: flex;
    flex-direction: column;

    gap: 7px;
}

.dashboard-control-label {
    padding-left: 3px;

    color: #9ca3af;

    font-family: "Poppins", sans-serif;
    font-size: 11px;
    font-weight: 700;

    letter-spacing: 0.06em;
    text-transform: uppercase;
}


/*
|--------------------------------------------------------------------------
| Primary actions
|--------------------------------------------------------------------------
*/

.dashboard-primary-actions {
    display: flex;
    align-items: center;

    gap: 9px;
}

.dashboard-primary-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;

    min-height: 40px;

    padding: 9px 20px;

    border-radius: 999px;

    font-family: "Poppins", sans-serif;
    font-size: 14px;
    font-weight: 700;

    text-decoration: none;
    white-space: nowrap;

    box-sizing: border-box;

    transition:
        background-color 0.18s ease,
        border-color 0.18s ease,
        color 0.18s ease,
        transform 0.18s ease,
        box-shadow 0.18s ease;
}


/*
 * Main content-creation action.
 */
.dashboard-post-button {
    border: 1px solid #b91c1c;

    background: #b91c1c;
    color: #ffffff;

    box-shadow:
        0 4px 10px rgba(185, 28, 28, 0.16);
}

.dashboard-post-button:hover {
    background: #991b1b;
    border-color: #991b1b;
    color: #ffffff;

    transform: translateY(-1px);

    box-shadow:
        0 6px 14px rgba(185, 28, 28, 0.2);
}


/*
 * Secondary navigation action.
 */
.dashboard-follow-button {
    border: 1px solid #cbd5e1;

    background: #ffffff;
    color: #374151;
}

.dashboard-follow-button:hover {
    background: #f8fafc;
    border-color: #9ca3af;
    color: #111827;

    transform: translateY(-1px);
}


/*
|--------------------------------------------------------------------------
| Feed filters
|--------------------------------------------------------------------------
*/

.dashboard-filter-group {
    align-items: center;
}

.dashboard-filter-group .dashboard-control-label {
    width: 100%;
    padding-left: 0;
    text-align: center;
}

.dashboard-filter-buttons {
    display: inline-flex;
    align-items: center;

    padding: 3px;

    border: 1px solid #d1d5db;
    border-radius: 999px;

    background: #f3f4f6;

    box-shadow:
        inset 0 1px 2px rgba(15, 23, 42, 0.04);
}

.dashboard-filter-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;

    min-height: 34px;

    padding: 7px 16px;

    border: 0;
    border-radius: 999px;

    background: transparent;
    color: #6b7280;

    font-family: "Poppins", sans-serif;
    font-size: 13px;
    font-weight: 700;

    text-decoration: none;
    white-space: nowrap;

    transition:
        background-color 0.18s ease,
        color 0.18s ease,
        box-shadow 0.18s ease;
}

.dashboard-filter-button:hover {
    color: #111827;
}

.dashboard-filter-button-active {
    background: #111827;
    color: #ffffff;

    box-shadow:
        0 2px 5px rgba(17, 24, 39, 0.18);
}

.dashboard-filter-button-active:hover {
    background: #111827;
    color: #ffffff;
}


/*
|--------------------------------------------------------------------------
| Divider
|--------------------------------------------------------------------------
*/

.dashboard-divider {
    width: 100%;
    height: 1px;

    margin: 0;

    border-radius: 999px;

    background: #e5e7eb;
}


/*
|--------------------------------------------------------------------------
| Keyboard focus
|--------------------------------------------------------------------------
*/

.dashboard-primary-button:focus-visible,
.dashboard-filter-button:focus-visible {
    outline: 3px solid rgba(59, 130, 246, 0.28);
    outline-offset: 2px;
}


/*
|--------------------------------------------------------------------------
| Mobile
|--------------------------------------------------------------------------
*/

@media (max-width: 700px) {

    .dashboard-top {
        max-width: none;
        width: 100%;

        margin: 12px auto 8px;
        padding: 0 10px;
    }

    .dashboard-welcome {
        margin-bottom: 13px;

        font-size: 18px;
    }

    .dashboard-controls {
        display: block;

        margin-bottom: 12px;
    }

    .dashboard-control-group {
        width: 100%;
    }

    .dashboard-primary-actions {
        width: 100%;
    }

    .dashboard-primary-button {
        flex: 1 1 0;

        min-width: 0;

        padding: 9px 12px;
    }

    .dashboard-filter-group {
        align-items: stretch;

        margin-top: 14px;
    }

    .dashboard-filter-buttons {
        display: flex;

        width: 100%;

        box-sizing: border-box;
    }

    .dashboard-filter-button {
        flex: 1 1 0;

        min-width: 0;

        padding: 7px 8px;

        font-size: 12.5px;
    }

    .dashboard-control-label {
        padding-left: 2px;
    }

}
</style>

<?php
if ($page === 'social_feed'):

    $first_name = explode(
        ' ',
        trim($_SESSION['user_name'] ?? 'User')
    )[0];
?>

    <div class="dashboard-top">

        <div class="dashboard-welcome">
            Welcome back <?= htmlspecialchars($first_name) ?>!
        </div>

        <div class="dashboard-controls">

            <!-- Primary navigation actions -->
            <div class="dashboard-control-group">

                <div class="dashboard-primary-actions">

                    <a
                        href="<?= htmlspecialchars(
                            dashboardUrl(
                                'post',
                                $feed_filter,
                                $sort
                            )
                        ) ?>"
                        class="dashboard-primary-button dashboard-post-button"
                    >
                        Post
                    </a>

                    <a
                        href="<?= htmlspecialchars(
                            dashboardUrl(
                                'manage_friends',
                                $feed_filter,
                                $sort
                            )
                        ) ?>"
                        class="dashboard-primary-button dashboard-follow-button"
                    >
                        Follow Friends
                    </a>

                </div>

            </div>


            <!-- Feed filtering -->
            <div class="dashboard-control-group dashboard-filter-group">

                <div
                    class="dashboard-filter-buttons"
                    aria-label="Filter posts"
                >

                    <a
                        href="<?= htmlspecialchars(
                            dashboardUrl(
                                'social_feed',
                                'all',
                                $sort
                            )
                        ) ?>"
                        class="
                            dashboard-filter-button
                            <?= $feed_filter === 'all'
                                ? 'dashboard-filter-button-active'
                                : ''
                            ?>
                        "
                        <?= $feed_filter === 'all'
                            ? 'aria-current="page"'
                            : ''
                        ?>
                    >
                        All Posts
                    </a>

                    <a
                        href="<?= htmlspecialchars(
                            dashboardUrl(
                                'social_feed',
                                'following',
                                $sort
                            )
                        ) ?>"
                        class="
                            dashboard-filter-button
                            <?= $feed_filter === 'following'
                                ? 'dashboard-filter-button-active'
                                : ''
                            ?>
                        "
                        <?= $feed_filter === 'following'
                            ? 'aria-current="page"'
                            : ''
                        ?>
                    >
                        Following
                    </a>

                    <a
                        href="<?= htmlspecialchars(
                            dashboardUrl(
                                'social_feed',
                                'my_posts',
                                $sort
                            )
                        ) ?>"
                        class="
                            dashboard-filter-button
                            <?= $feed_filter === 'my_posts'
                                ? 'dashboard-filter-button-active'
                                : ''
                            ?>
                        "
                        <?= $feed_filter === 'my_posts'
                            ? 'aria-current="page"'
                            : ''
                        ?>
                    >
                        My Posts
                    </a>

                </div>

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
        include '../includes/create_post.php';
        break;

    case 'social_feed':
    default:
        include '../includes/posts/feed.php';
        break;
}

?>

<?php
include '../includes/footer.php';
?>