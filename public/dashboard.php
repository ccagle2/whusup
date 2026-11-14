<?php

require_once '../includes/auth.php';
require_once '../config/database.php';

require_login();

$pageTitle = 'Dashboard | Whusup';
$canonicalUrl = 'https://whusup.com/dashboard.php';
$robotsMeta = 'noindex, follow';

include '../includes/header.php';
include '../includes/navbar.php';

$page = $_GET['page'] ?? 'social_feed';
$sort = $_GET['sort'] ?? 'recent';
$feed_filter = $_GET['filter'] ?? 'all';

$dashboard_display_name = '';

if (!empty($_SESSION['user_id'])) {
    $displayNameStmt = $pdo->prepare("
        SELECT display_name
        FROM user_profiles
        WHERE user_id = :user_id
        LIMIT 1
    ");

    $displayNameStmt->execute([
        ':user_id' => $_SESSION['user_id']
    ]);

    $dashboard_display_name = trim(
        (string) $displayNameStmt->fetchColumn()
    );
}

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
    margin: 24px auto 18px;
    padding: 18px 20px;

    background: #f2f2f2;
    border: 1px solid #dedede;
    border-radius: 16px;

    box-shadow:
        0 1px 2px rgba(15, 23, 42, 0.03),
        0 6px 18px rgba(15, 23, 42, 0.035);

    box-sizing: border-box;
}

.dashboard-welcome {
    margin: 0 0 16px;
    color: #6b7280;
    font-family: "Poppins", sans-serif;
    font-size: 20px;
    font-weight: 500;
    line-height: 1.4;
    text-align: center;
}

.dashboard-welcome-name {
    color: #374151;
    font-weight: 700;
}

/*
|--------------------------------------------------------------------------
| Mobile / tablet dashboard search
|--------------------------------------------------------------------------
*/

.dashboard-mobile-search {
    display: none;
}

.dashboard-mobile-search .form-control {
    width: 100%;
    min-height: 46px;
    padding: 11px 18px;

    border: 1px solid #d1d5db;
    border-radius: 999px;

    background: #ffffff;
    color: #111827;

    font-size: 15px;
    line-height: 1.4;

    box-sizing: border-box;
}

.dashboard-mobile-search .form-control::placeholder {
    color: #6b7280;
    opacity: 0.8;
}

.dashboard-mobile-search .form-control:focus {
    border-color: #9ca3af;
    box-shadow: 0 0 0 3px rgba(17, 24, 39, 0.06);
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
    width: 130px;

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
    border: 1px solid #B94B53;

    background: #B94B53;
    color: #ffffff;

    box-shadow:
        0 4px 10px rgba(185, 75, 83, 0.16);
}

.dashboard-post-button:hover {
    background: #A63E47;
    border-color: #A63E47;
    color: #ffffff;

    transform: translateY(-1px);

    box-shadow:
        0 6px 14px rgba(185, 75, 83, 0.2);
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
    background: #f2f2f2;
    border-color: #9ca3af;
    color: #111827;

    transform: translateY(-1px);
}

/* Small, consistent icons for the two primary actions. */
.dashboard-primary-button .dashboard-action-icon {
    display: block;
    width: 17px;
    height: 17px;
    flex: 0 0 17px;
    fill: none;
    stroke: currentColor;
    stroke-width: 2;
    stroke-linecap: round;
    stroke-linejoin: round;
}

.dashboard-primary-button {
    gap: 8px;
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
| Mobile / tablet
|--------------------------------------------------------------------------
*/

@media (max-width: 991px) {

    .dashboard-mobile-search {
        display: block;
        width: 100%;
        margin-bottom: 16px;
    }

}

/*
|--------------------------------------------------------------------------
| Mobile
|--------------------------------------------------------------------------
*/

@media (max-width: 700px) {

    .dashboard-top {
        max-width: none;
        width: calc(100% - 20px);

        margin: 12px auto 10px;
        padding: 14px 12px;

        border-radius: 14px;
    }

    .dashboard-welcome {
        display: none;
    }

    .dashboard-controls {
        display: block;

        margin-bottom: 0;
    }

    .dashboard-control-group {
        width: 100%;
    }

    .dashboard-primary-actions {
        width: 100%;
    }

    .dashboard-primary-button {
        flex: 1 1 0;
        width: auto;

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

/*
|--------------------------------------------------------------------------
| Small mobile - full-width dashboard controls
|--------------------------------------------------------------------------
*/

@media (max-width: 576px) {

    .dashboard-top {
        width: 100vw;
        max-width: none;

        /*
         * Match the mobile guest card positioning.
         * The site reserves slightly more vertical space for the
         * fixed navbar than the mobile navbar actually requires.
         */
        margin-top: -18px;
        margin-bottom: 1rem;
        margin-left: calc(50% - 50vw);
        margin-right: calc(50% - 50vw);

        padding: 14px 12px;

        border-left: 0;
        border-right: 0;
        border-radius: 0;

        box-shadow: none;
    }

}

</style>

<?php
if ($page === 'social_feed'):
?>

    <div class="dashboard-top">

        <?php if ($dashboard_display_name !== ''): ?>
            <div class="dashboard-welcome">
                Welcome,
                <span class="dashboard-welcome-name">
                    <?= htmlspecialchars(
                        $dashboard_display_name,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </span>
            </div>
        <?php endif; ?>
        
        <form
            class="dashboard-mobile-search"
            role="search"
            method="GET"
            action="/search.php"
        >
            <input
                class="form-control text-center"
                type="search"
                name="q"
                value=""
                placeholder="Find Anything"
                aria-label="Search topics, people and posts"
                minlength="3"
                maxlength="100"
                autocomplete="off"
                enterkeyhint="search"
                required
            >
        </form>
        
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
                        <svg class="dashboard-action-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                            <path d="M12 20h9" />
                            <path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L9 17l-4 1 1-4Z" />
                        </svg>
                        <span>Post</span>
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
                        <svg class="dashboard-action-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                            <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />
                            <circle cx="9" cy="7" r="4" />
                            <path d="M22 21v-2a4 4 0 0 0-3-3.87" />
                            <path d="M16 3.13a4 4 0 0 1 0 7.75" />
                        </svg>
                        <span>Follow</span>
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