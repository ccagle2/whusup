<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';

$awsConfig = require __DIR__ . '/../config/aws.php';

if (!isset($_SESSION['user_id'])) {
    echo '<script>window.location.href="/login.php";</script>';
    exit;
}

$user_id = $_SESSION['user_id'];
$message = "";


/*
|--------------------------------------------------------------------------
| Helper functions
|--------------------------------------------------------------------------
*/

function getFriendProfileImageUrl($imageKey)
{
    global $awsConfig;

    if (empty($imageKey)) {
        return null;
    }

    return rtrim($awsConfig['cloudfront_url'], '/')
        . '/'
        . ltrim($imageKey, '/');
}


function getInitials($name)
{
    $name = trim((string) $name);

    if ($name === '') {
        return 'U';
    }

    $parts = preg_split('/\s+/', $name);

    $first = strtoupper(substr($parts[0], 0, 1));
    $last = '';

    if (count($parts) > 1) {
        $last = strtoupper(substr(end($parts), 0, 1));
    }

    return $first . $last;
}


function formatFollowerCount($count)
{
    $count = (int) $count;

    return $count
        . ' follower'
        . ($count === 1 ? '' : 's');
}


/*
|--------------------------------------------------------------------------
| Current friends / Following
|--------------------------------------------------------------------------
*/

try {

    $stmt = $pdo->prepare("
        SELECT
            users.id,
            users.name,
            user_profiles.profile_picture_url,
            user_profiles.bio,

            (
                SELECT COUNT(*)
                FROM friendships f

                JOIN users follower_users
                    ON follower_users.id = f.user_id

                WHERE f.friend_id = users.id
                AND f.status = 'accepted'
                AND follower_users.email_verified = 1
            ) AS follower_count

        FROM friendships

        JOIN users
            ON users.id = friendships.friend_id

        LEFT JOIN user_profiles
            ON user_profiles.user_id = users.id

        WHERE friendships.user_id = :user_id
        AND friendships.status = 'accepted'
        AND users.email_verified = 1

        ORDER BY users.name ASC
    ");

    $stmt->execute([
        ':user_id' => $user_id
    ]);

    $friends = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    error_log(
        'Manage friends following query failed: '
        . $e->getMessage()
    );

    $friends = [];
    $message = "Could not load friends.";
}


/*
|--------------------------------------------------------------------------
| Initial available users
|--------------------------------------------------------------------------
*/

$available_users = [];
$initial_limit = 20;

try {

    $stmt = $pdo->prepare("
        SELECT
            users.id,
            users.name,
            user_profiles.profile_picture_url,
            user_profiles.bio,

            (
                SELECT COUNT(*)
                FROM friendships f

                JOIN users follower_users
                    ON follower_users.id = f.user_id

                WHERE f.friend_id = users.id
                AND f.status = 'accepted'
                AND follower_users.email_verified = 1
            ) AS follower_count

        FROM users

        LEFT JOIN user_profiles
            ON user_profiles.user_id = users.id

        WHERE users.id != :current_user_id

        AND users.email_verified = 1

        AND NOT EXISTS (
            SELECT 1
            FROM friendships
            WHERE friendships.user_id = :friendship_user_id
            AND friendships.friend_id = users.id
        )

        ORDER BY users.name ASC

        LIMIT :limit
    ");

    $stmt->bindValue(
        ':current_user_id',
        $user_id
    );

    $stmt->bindValue(
        ':friendship_user_id',
        $user_id
    );

    $stmt->bindValue(
        ':limit',
        $initial_limit,
        PDO::PARAM_INT
    );

    $stmt->execute();

    $available_users =
        $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    error_log(
        'Manage friends available query failed: '
        . $e->getMessage()
    );

    $available_users = [];
    $message = "Could not load available users.";
}

?>


<style>

/* =========================================================
   Page wrapper
   ========================================================= */

.friends-wrapper {
    width: 100%;
    max-width: 1040px;
    margin: 24px auto 44px;
    padding: 0 20px;
    box-sizing: border-box;
}


/* =========================================================
   Main cards
   ========================================================= */

.friends-card {
    background: #ffffff;
    border-radius: 18px;
    padding: 24px;
    margin-bottom: 22px;
    box-shadow: 0 8px 24px rgba(0,0,0,0.07);
    border: 1px solid #eef2f7;
}


/* =========================================================
   Hero
   ========================================================= */

.friends-hero-card {
    position: relative;
    overflow: hidden;
    text-align: center;
    border-radius: 22px;
    padding: 30px 24px;
    margin-bottom: 22px;
    color: #ffffff;

    background:
        radial-gradient(
            circle at top left,
            rgba(37,99,235,0.42),
            transparent 34%
        ),
        linear-gradient(
            135deg,
            #111827 0%,
            #1f2937 48%,
            #0f172a 100%
        );

    box-shadow:
        0 14px 34px rgba(15,23,42,0.24);

    border:
        1px solid rgba(255,255,255,0.08);
}


.friends-hero-card::after {
    content: "";
    position: absolute;
    inset: auto -60px -90px auto;

    width: 220px;
    height: 220px;

    border-radius: 50%;

    background:
        rgba(255,255,255,0.07);

    pointer-events: none;
}


.friends-title {
    position: relative;
    z-index: 1;

    font-size: 28px;
    font-weight: 900;

    margin: 0 0 8px;

    font-family:
        "Poppins",
        sans-serif;

    color: #ffffff;

    letter-spacing: -0.02em;
}


.friends-subtitle {
    position: relative;
    z-index: 1;

    margin: 0 auto 20px;

    color: #d1d5db;

    font-size: 14px;
    line-height: 1.55;

    max-width: 660px;
}


/* =========================================================
   Section headings
   ========================================================= */

.section-heading-row {
    display: flex;
    justify-content: space-between;
    align-items: center;

    gap: 14px;

    margin-bottom: 16px;

    flex-wrap: wrap;
}


.section-title {
    font-size: 21px;
    font-weight: 800;

    color: #111827;

    font-family:
        "Poppins",
        sans-serif;
}


.section-meta {
    font-size: 13px;

    color: #6b7280;

    font-weight: 700;
}


/* =========================================================
   Messages
   ========================================================= */

.message,
.ajax-message {
    color: #b91c1c;

    background: #fee2e2;

    padding: 10px 12px;

    border-radius: 12px;

    text-align: center;

    margin-bottom: 15px;

    font-weight: 700;

    font-size: 14px;
}


.ajax-message {
    display: none;
}


/* =========================================================
   Search
   ========================================================= */

.friend-search-wrap {
    position: relative;

    width: 100%;

    margin-bottom: 18px;
}


.friend-search-input {
    width: 100%;

    padding:
        13px 46px 13px 18px;

    border:
        1px solid #d1d5db;

    border-radius: 999px;

    font-size: 15px;

    outline: none;

    box-sizing: border-box;

    background: #f9fafb;

    transition:
        border-color 0.2s ease,
        box-shadow 0.2s ease,
        background 0.2s ease;
}


.friend-search-input:focus {
    border-color: #111827;

    background: #ffffff;

    box-shadow:
        0 0 0 4px rgba(17,24,39,0.08);
}


.friend-search-icon {
    position: absolute;

    right: 17px;

    top: 50%;

    transform:
        translateY(-50%);

    color: #6b7280;

    font-size: 18px;

    pointer-events: none;
}


/* =========================================================
   People grid
   ========================================================= */

.people-grid {
    display: grid;

    grid-template-columns:
        repeat(2, minmax(0, 1fr));

    gap: 14px;
    
    align-items: start;
}


/* =========================================================
   Person card
   ========================================================= */

.person-card {
    display: grid;

    grid-template-columns:
        auto minmax(0, 1fr) auto;

    align-items: start;

    column-gap: 14px;
    row-gap: 0;

    background: #ffffff;

    border:
        1px solid #e5e7eb;

    border-radius: 18px;

    padding: 15px;

    transition:
        transform 0.18s ease,
        box-shadow 0.18s ease,
        opacity 0.18s ease,
        border-color 0.18s ease;

    min-width: 0;
}


.person-card:hover {
    transform:
        translateY(-2px);

    box-shadow:
        0 10px 24px rgba(0,0,0,0.08);

    border-color:
        #d1d5db;
}


.person-card.is-moving {
    opacity: 0.48;

    transform:
        translateY(2px);
}


/* =========================================================
   Profile picture
   ========================================================= */

.person-avatar-button {
    width: 58px;
    height: 58px;

    flex-shrink: 0;

    margin: 0;
    padding: 0;

    border: none;
    border-radius: 50%;

    background: transparent;

    cursor: pointer;

    display: block;

    appearance: none;
    -webkit-appearance: none;
}


.person-avatar,
.person-avatar-placeholder {
    width: 58px;
    height: 58px;

    border-radius: 50%;

    flex-shrink: 0;

    border: 3px solid #ffffff;

    box-shadow:
        0 4px 13px rgba(0,0,0,0.14);

    background: #e5e7eb;

    box-sizing: border-box;
}


.person-avatar {
    object-fit: cover;
    object-position: center;

    display: block;

    transition:
        transform 0.18s ease,
        box-shadow 0.18s ease;
}


.person-avatar-button:hover .person-avatar,
.person-avatar-button:focus-visible .person-avatar {
    transform:
        scale(1.04);

    box-shadow:
        0 5px 16px rgba(0,0,0,0.20);
}


.person-avatar-button:focus-visible {
    outline:
        3px solid rgba(29,78,216,0.28);

    outline-offset: 3px;
}


.person-avatar-placeholder {
    display: flex;

    align-items: center;
    justify-content: center;

    color: #4b5563;

    font-weight: 900;

    font-size: 18px;

    font-family:
        "Poppins",
        sans-serif;
}


/* =========================================================
   Person text / bio
   ========================================================= */

.person-main {
    min-width: 0;
}


.person-name {
    color: #111827;

    font-weight: 800;

    font-size: 15px;

    line-height: 1.25;

    overflow: hidden;

    text-overflow: ellipsis;

    white-space: nowrap;
}


.person-name-button {
    display: block;

    width: 100%;

    margin: 0;
    padding: 0;

    border: none;

    background: transparent;

    text-align: left;

    font-family: inherit;

    cursor: pointer;

    appearance: none;
    -webkit-appearance: none;
}


.person-name-button:hover {
    color: #1d4ed8;
}


.person-name-button:focus-visible {
    outline:
        2px solid rgba(29,78,216,0.35);

    outline-offset: 3px;

    border-radius: 4px;
}


.person-followers {
    margin-top: 4px;

    color: #6b7280;

    font-size: 13px;

    font-weight: 700;
}


.person-bio {
    grid-column: 1 / -1;

    width: 100%;

    margin-top: 12px;

    padding-top: 5px;

    border-top:
        1px solid #eef2f7;

    color: #4b5563;

    font-size: 14px;

    line-height: 1.5;

    font-weight: 500;

    white-space: pre-line;

    overflow-wrap: anywhere;

    text-align: left;
}


.person-bio[hidden] {
    display: none !important;
}


/* =========================================================
   Follow / unfollow buttons
   ========================================================= */

.friend-button {
    border: none;

    padding: 9px 17px;

    border-radius: 999px;

    cursor: pointer;

    font-size: 13px;

    font-weight: 800;

    white-space: nowrap;

    transition:
        background 0.2s ease,
        opacity 0.2s ease,
        transform 0.2s ease;

    flex-shrink: 0;
    align-self: center;
}


.friend-button:hover {
    transform:
        translateY(-1px);
}


.friend-button:disabled {
    opacity: 0.65;

    cursor: not-allowed;

    transform: none;
}


.add-button {
    background: #1d4ed8;

    color: #ffffff;
}


.add-button:hover {
    background: #1e40af;
}


.remove-button {
    background: #dc2626;

    color: #ffffff;
}


.remove-button:hover {
    background: #b91c1c;
}


/* =========================================================
   Back button
   ========================================================= */

.back-button {
    position: relative;

    z-index: 1;

    display: inline-block;

    background: #ffffff;

    color: #111827;

    border:
        1px solid rgba(255,255,255,0.28);

    padding: 9px 22px;

    border-radius: 999px;

    font-size: 14px;

    font-weight: 800;

    text-decoration: none;

    box-shadow:
        0 8px 18px rgba(0,0,0,0.18);

    transition:
        background 0.2s ease,
        color 0.2s ease,
        transform 0.2s ease,
        box-shadow 0.2s ease;
}


.back-button:hover {
    background: #e5e7eb;

    color: #111827;

    transform:
        translateY(-1px);

    box-shadow:
        0 10px 22px rgba(0,0,0,0.22);
}


/* =========================================================
   Empty / loading states
   ========================================================= */

.empty-text {
    text-align: center;

    color: #777;

    background: #f9fafb;

    border:
        1px dashed #d1d5db;

    border-radius: 16px;

    padding: 22px;

    margin: 0;

    font-weight: 700;
}


.loading-row {
    display: none;

    text-align: center;

    color: #6b7280;

    font-size: 13px;

    font-weight: 800;

    padding: 16px 0 4px;
}


.loading-row.show {
    display: block;
}


.end-of-list {
    display: none;

    text-align: center;

    color: #9ca3af;

    font-size: 13px;

    font-weight: 800;

    padding: 16px 0 4px;
}


.end-of-list.show {
    display: block;
}


/* =========================================================
   Profile picture modal
   ========================================================= */

.profile-image-modal {
    position: fixed;

    inset: 0;

    z-index: 20000;

    display: none;

    align-items: center;
    justify-content: center;

    padding: 24px;

    background:
        rgba(0,0,0,0.82);

    box-sizing: border-box;
}


.profile-image-modal.show {
    display: flex;
}


.profile-image-dialog {
    position: relative;

    display: flex;

    align-items: center;
    justify-content: center;

    max-width: 94vw;
    max-height: 90vh;
}


.profile-image-large {
    display: block;

    max-width: min(720px, 90vw);
    max-height: 85vh;

    width: auto;
    height: auto;

    object-fit: contain;

    border-radius: 18px;

    box-shadow:
        0 20px 55px rgba(0,0,0,0.45);

    background: #111827;
}


.profile-image-close {
    position: absolute;

    top: -18px;
    right: -18px;

    width: 40px;
    height: 40px;

    display: flex;

    align-items: center;
    justify-content: center;

    border:
        2px solid rgba(255,255,255,0.8);

    border-radius: 50%;

    background:
        rgba(17,24,39,0.92);

    color: #ffffff;

    font-size: 25px;
    line-height: 1;

    cursor: pointer;

    z-index: 1;
}


.profile-image-close:hover {
    background: #ffffff;

    color: #111827;
}


body.profile-image-modal-open {
    overflow: hidden;
}


/* =========================================================
   Mobile
   ========================================================= */

@media (max-width: 760px) {

    .friends-wrapper {
        max-width: none;

        width: 100%;

        margin: 12px auto 32px;

        padding: 0 10px;
    }


    .friends-hero-card,
    .friends-card {
        padding: 17px;

        border-radius: 16px;

        margin-bottom: 16px;
    }


    .friends-hero-card {
        padding: 24px 17px;
    }


    .friends-title {
        font-size: 24px;
    }


    .people-grid {
        grid-template-columns: 1fr;

        gap: 11px;
    }


    .person-card {
        border-radius: 16px;

        padding: 13px;

        column-gap: 12px;
        row-gap: 0;
    }


    .person-avatar-button,
    .person-avatar,
    .person-avatar-placeholder {
        width: 52px;

        height: 52px;
    }


    .person-avatar-placeholder {
        font-size: 16px;
    }


    .friend-button {
        padding: 8px 14px;

        font-size: 12px;
    }


    .section-heading-row {
        align-items: flex-start;
    }


    .profile-image-modal {
        padding: 18px;
    }


    .profile-image-large {
        max-width: 92vw;

        max-height: 80vh;

        border-radius: 14px;
    }


    .profile-image-close {
        top: -16px;

        right: -8px;
    }
}


@media (max-width: 420px) {

    .person-card {
        align-items: flex-start;
    }


    .person-main {
        padding-top: 3px;
    }


    .friend-button {
        padding: 8px 12px;
    }
}

</style>


<div class="friends-wrapper">

    <div class="friends-hero-card">

        <h1 class="friends-title">
            Follow Friends
        </h1>

        <p class="friends-subtitle">
            Find people to follow, manage who you already follow,
            and discover new accounts.
        </p>

        <a
            href="dashboard.php?page=social_feed"
            class="back-button"
        >
            Back to Feed
        </a>

    </div>


    <?php if (!empty($message)): ?>

        <div class="message">
            <?= htmlspecialchars($message) ?>
        </div>

    <?php endif; ?>


    <div
        id="ajax-message"
        class="ajax-message"
    ></div>


    <!-- =====================================================
         FOLLOWING
         ===================================================== -->

    <div class="friends-card">

        <div class="section-heading-row">

            <div class="section-title">
                Following
            </div>

            <div
                class="section-meta"
                id="following-count"
            >
                <?= count($friends) ?> following
            </div>

        </div>


        <p
            id="following-empty"
            class="empty-text"
            style="<?= empty($friends) ? '' : 'display:none;' ?>"
        >
            You are not following anyone yet.
        </p>


        <div
            id="following-list"
            class="people-grid"
        >

            <?php foreach ($friends as $friend): ?>

                <?php

                $friendName =
                    ucwords((string) $friend['name']);

                $friendImageKey =
                    $friend['profile_picture_url'] ?? '';

                $friendImageUrl =
                    getFriendProfileImageUrl(
                        $friendImageKey
                    );

                $friendFollowers =
                    (int) ($friend['follower_count'] ?? 0);

                $friendBio =
                    trim((string) ($friend['bio'] ?? ''));

                ?>

                <div
                    class="person-card"
                    data-user-id="<?= htmlspecialchars($friend['id']) ?>"
                    data-user-name="<?= htmlspecialchars($friendName) ?>"
                    data-profile-picture-url="<?= htmlspecialchars($friendImageKey) ?>"
                    data-follower-count="<?= htmlspecialchars((string) $friendFollowers) ?>"
                    data-bio="<?= htmlspecialchars($friendBio, ENT_QUOTES, 'UTF-8') ?>"
                >

                    <?php if (!empty($friendImageUrl)): ?>

                        <button
                            type="button"
                            class="person-avatar-button"
                            data-image-url="<?= htmlspecialchars($friendImageUrl) ?>"
                            data-user-name="<?= htmlspecialchars($friendName) ?>"
                            aria-label="View <?= htmlspecialchars($friendName) ?> profile picture"
                        >

                            <img
                                src="<?= htmlspecialchars($friendImageUrl) ?>"
                                alt="<?= htmlspecialchars($friendName) ?> profile picture"
                                class="person-avatar"
                                loading="lazy"
                            >

                        </button>

                    <?php else: ?>

                        <div
                            class="person-avatar-placeholder"
                            aria-hidden="true"
                        >
                            <?= htmlspecialchars(
                                getInitials($friendName)
                            ) ?>
                        </div>

                    <?php endif; ?>


                    <div class="person-main">

                        <button
                            type="button"
                            class="person-name person-name-button"
                            aria-expanded="false"
                        >
                            <?= htmlspecialchars($friendName) ?>
                        </button>

                        <div class="person-followers">
                            <?= htmlspecialchars(
                                formatFollowerCount(
                                    $friendFollowers
                                )
                            ) ?>
                        </div>

                    </div>


                    <button
                        type="button"
                        class="friend-button remove-button ajax-unfollow-button"
                        data-user-id="<?= htmlspecialchars($friend['id']) ?>"
                        data-user-name="<?= htmlspecialchars($friendName) ?>"
                        data-profile-picture-url="<?= htmlspecialchars($friendImageKey) ?>"
                        data-follower-count="<?= htmlspecialchars((string) $friendFollowers) ?>"
                    >
                        Unfollow
                    </button>


                    <div
                        class="person-bio"
                        hidden
                    >
                        <?= htmlspecialchars(
                            $friendBio !== ''
                                ? $friendBio
                                : 'No bio yet.'
                        ) ?>
                    </div>

                </div>

            <?php endforeach; ?>

        </div>

    </div>


    <!-- =====================================================
         AVAILABLE USERS
         ===================================================== -->

    <div class="friends-card">

        <div class="section-heading-row">

            <div class="section-title">
                Available
            </div>

            <div
                class="section-meta"
                id="available-status"
            >
                Search or scroll to discover people
            </div>

        </div>


        <div class="friend-search-wrap">

            <input
                type="search"
                id="friend-search-input"
                class="friend-search-input"
                placeholder="Find friends"
                autocomplete="off"
            >

            <span class="friend-search-icon">
                ⌕
            </span>

        </div>


        <p
            id="available-empty"
            class="empty-text"
            style="<?= empty($available_users) ? '' : 'display:none;' ?>"
        >
            No users available to follow.
        </p>


        <div
            id="available-list"
            class="people-grid"
        >

            <?php foreach ($available_users as $person): ?>

                <?php

                $personName =
                    ucwords((string) $person['name']);

                $personImageKey =
                    $person['profile_picture_url'] ?? '';

                $personImageUrl =
                    getFriendProfileImageUrl(
                        $personImageKey
                    );

                $personFollowers =
                    (int) ($person['follower_count'] ?? 0);

                $personBio =
                    trim((string) ($person['bio'] ?? ''));

                ?>

                <div
                    class="person-card"
                    data-user-id="<?= htmlspecialchars($person['id']) ?>"
                    data-user-name="<?= htmlspecialchars($personName) ?>"
                    data-profile-picture-url="<?= htmlspecialchars($personImageKey) ?>"
                    data-follower-count="<?= htmlspecialchars((string) $personFollowers) ?>"
                    data-bio="<?= htmlspecialchars($personBio, ENT_QUOTES, 'UTF-8') ?>"
                >

                    <?php if (!empty($personImageUrl)): ?>

                        <button
                            type="button"
                            class="person-avatar-button"
                            data-image-url="<?= htmlspecialchars($personImageUrl) ?>"
                            data-user-name="<?= htmlspecialchars($personName) ?>"
                            aria-label="View <?= htmlspecialchars($personName) ?> profile picture"
                        >

                            <img
                                src="<?= htmlspecialchars($personImageUrl) ?>"
                                alt="<?= htmlspecialchars($personName) ?> profile picture"
                                class="person-avatar"
                                loading="lazy"
                            >

                        </button>

                    <?php else: ?>

                        <div
                            class="person-avatar-placeholder"
                            aria-hidden="true"
                        >
                            <?= htmlspecialchars(
                                getInitials($personName)
                            ) ?>
                        </div>

                    <?php endif; ?>


                    <div class="person-main">

                        <button
                            type="button"
                            class="person-name person-name-button"
                            aria-expanded="false"
                        >
                            <?= htmlspecialchars($personName) ?>
                        </button>

                        <div class="person-followers">
                            <?= htmlspecialchars(
                                formatFollowerCount(
                                    $personFollowers
                                )
                            ) ?>
                        </div>

                    </div>


                    <button
                        type="button"
                        class="friend-button add-button ajax-follow-button"
                        data-user-id="<?= htmlspecialchars($person['id']) ?>"
                        data-user-name="<?= htmlspecialchars($personName) ?>"
                        data-profile-picture-url="<?= htmlspecialchars($personImageKey) ?>"
                        data-follower-count="<?= htmlspecialchars((string) $personFollowers) ?>"
                    >
                        Follow
                    </button>


                    <div
                        class="person-bio"
                        hidden
                    >
                        <?= htmlspecialchars(
                            $personBio !== ''
                                ? $personBio
                                : 'No bio yet.'
                        ) ?>
                    </div>

                </div>

            <?php endforeach; ?>

        </div>


        <div
            id="available-loading"
            class="loading-row"
        >
            Loading more people...
        </div>


        <div
            id="available-end"
            class="end-of-list"
        >
            No more people to show.
        </div>


        <div
            id="available-sentinel"
            style="height: 1px;"
        ></div>

    </div>

</div>


<!-- =========================================================
     PROFILE PICTURE MODAL
     ========================================================= -->

<div
    id="profile-image-modal"
    class="profile-image-modal"
    aria-hidden="true"
>

    <div
        class="profile-image-dialog"
        role="dialog"
        aria-modal="true"
        aria-label="Profile picture"
    >

        <button
            type="button"
            id="profile-image-close"
            class="profile-image-close"
            aria-label="Close profile picture"
        >
            &times;
        </button>

        <img
            id="profile-image-large"
            class="profile-image-large"
            src=""
            alt="Profile picture"
        >

    </div>

</div>


<script>

document.addEventListener(
    "DOMContentLoaded",
    function () {

        /*
        |--------------------------------------------------------------------------
        | Element references
        |--------------------------------------------------------------------------
        */

        const followingList =
            document.getElementById(
                "following-list"
            );

        const availableList =
            document.getElementById(
                "available-list"
            );

        const followingEmpty =
            document.getElementById(
                "following-empty"
            );

        const availableEmpty =
            document.getElementById(
                "available-empty"
            );

        const ajaxMessage =
            document.getElementById(
                "ajax-message"
            );

        const followingCount =
            document.getElementById(
                "following-count"
            );

        const availableStatus =
            document.getElementById(
                "available-status"
            );

        const searchInput =
            document.getElementById(
                "friend-search-input"
            );

        const loadingRow =
            document.getElementById(
                "available-loading"
            );

        const endRow =
            document.getElementById(
                "available-end"
            );

        const sentinel =
            document.getElementById(
                "available-sentinel"
            );


        /*
        |--------------------------------------------------------------------------
        | Profile image modal references
        |--------------------------------------------------------------------------
        */

        const profileImageModal =
            document.getElementById(
                "profile-image-modal"
            );

        const profileImageLarge =
            document.getElementById(
                "profile-image-large"
            );

        const profileImageClose =
            document.getElementById(
                "profile-image-close"
            );


        /*
        |--------------------------------------------------------------------------
        | Configuration / state
        |--------------------------------------------------------------------------
        */

        const cloudfrontBaseUrl =
            <?= json_encode(
                rtrim(
                    $awsConfig['cloudfront_url'],
                    '/'
                )
            ) ?>;

        const pageSize = 20;

        let availableOffset =
            <?= (int) count($available_users) ?>;

        let currentSearch = "";

        let isLoading = false;

        let hasMore =
            <?= count($available_users) >= $initial_limit
                ? 'true'
                : 'false' ?>;

        let searchTimer = null;


        /*
        |--------------------------------------------------------------------------
        | Utility helpers
        |--------------------------------------------------------------------------
        */

        function escapeHtml(text) {

            const div =
                document.createElement("div");

            div.textContent =
                text ?? "";

            return div.innerHTML;
        }


        function getInitials(name) {

            const parts =
                String(name || "U")
                    .trim()
                    .split(/\s+/)
                    .filter(Boolean);

            if (parts.length === 0) {
                return "U";
            }

            const first =
                parts[0]
                    .charAt(0)
                    .toUpperCase();

            const last =
                parts.length > 1
                    ? parts[
                        parts.length - 1
                    ]
                        .charAt(0)
                        .toUpperCase()
                    : "";

            return first + last;
        }


        function formatFollowerCount(count) {

            const n =
                parseInt(count, 10) || 0;

            return n
                + " follower"
                + (n === 1 ? "" : "s");
        }


        function getProfileImageUrl(key) {

            if (!key) {
                return "";
            }

            return cloudfrontBaseUrl
                + "/"
                + String(key)
                    .replace(/^\/+/, "");
        }


        function showMessage(message) {

            if (!ajaxMessage) {
                return;
            }

            ajaxMessage.textContent =
                message;

            ajaxMessage.style.display =
                "block";
        }


        function hideMessage() {

            if (!ajaxMessage) {
                return;
            }

            ajaxMessage.textContent = "";

            ajaxMessage.style.display =
                "none";
        }


        /*
        |--------------------------------------------------------------------------
        | Empty state / Following total
        |--------------------------------------------------------------------------
        */

        function updateEmptyStates() {

            if (
                followingEmpty
                && followingList
            ) {

                followingEmpty.style.display =
                    followingList.querySelector(
                        ".person-card"
                    )
                        ? "none"
                        : "";
            }


            if (
                availableEmpty
                && availableList
            ) {

                availableEmpty.style.display =
                    availableList.querySelector(
                        ".person-card"
                    )
                        ? "none"
                        : "";
            }


            if (
                followingCount
                && followingList
            ) {

                const count =
                    followingList
                        .querySelectorAll(
                            ".person-card"
                        )
                        .length;

                followingCount.textContent =
                    count + " following";
            }
        }


        /*
        |--------------------------------------------------------------------------
        | Create a person card
        |--------------------------------------------------------------------------
        */

        function createPersonCard(
            user,
            mode
        ) {

            const userId =
                user.id || "";

            const userName =
                user.name || "User";

            const profilePictureUrl =
                user.profile_picture_url || "";

            const followerCount =
                parseInt(
                    user.follower_count,
                    10
                ) || 0;

            const bio =
                String(
                    user.bio || ""
                ).trim();

            const imageUrl =
                getProfileImageUrl(
                    profilePictureUrl
                );


            const card =
                document.createElement(
                    "div"
                );

            card.className =
                "person-card";

            card.dataset.userId =
                userId;

            card.dataset.userName =
                userName;

            card.dataset.profilePictureUrl =
                profilePictureUrl;

            card.dataset.followerCount =
                String(followerCount);

            card.dataset.bio =
                bio;


            /*
             * Avatar
             */

            let avatarHtml;

            if (imageUrl) {

                avatarHtml = `
                    <button
                        type="button"
                        class="person-avatar-button"
                        data-image-url="${escapeHtml(imageUrl)}"
                        data-user-name="${escapeHtml(userName)}"
                        aria-label="View ${escapeHtml(userName)} profile picture"
                    >
                        <img
                            src="${escapeHtml(imageUrl)}"
                            alt="${escapeHtml(userName)} profile picture"
                            class="person-avatar"
                            loading="lazy"
                        >
                    </button>
                `;

            } else {

                avatarHtml = `
                    <div
                        class="person-avatar-placeholder"
                        aria-hidden="true"
                    >
                        ${escapeHtml(
                            getInitials(userName)
                        )}
                    </div>
                `;
            }


            /*
             * Follow / unfollow button
             */

            const isFollowing =
                mode === "following";

            const buttonClass =
                isFollowing
                    ? "remove-button ajax-unfollow-button"
                    : "add-button ajax-follow-button";

            const buttonText =
                isFollowing
                    ? "Unfollow"
                    : "Follow";


            /*
             * Bio text
             */

            const bioText =
                bio !== ""
                    ? bio
                    : "No bio yet.";


            card.innerHTML = `

                ${avatarHtml}

                <div class="person-main">

                    <button
                        type="button"
                        class="person-name person-name-button"
                        aria-expanded="false"
                    >
                        ${escapeHtml(userName)}
                    </button>

                    <div class="person-followers">
                        ${escapeHtml(
                            formatFollowerCount(
                                followerCount
                            )
                        )}
                    </div>

                </div>

                <button
                    type="button"
                    class="friend-button ${buttonClass}"
                    data-user-id="${escapeHtml(userId)}"
                    data-user-name="${escapeHtml(userName)}"
                    data-profile-picture-url="${escapeHtml(profilePictureUrl)}"
                    data-follower-count="${escapeHtml(String(followerCount))}"
                >
                    ${buttonText}
                </button>

                <div
                    class="person-bio"
                    hidden
                >${escapeHtml(bioText)}</div>
            `;


            if (isFollowing) {

                attachUnfollowButton(
                    card.querySelector(
                        ".ajax-unfollow-button"
                    )
                );

            } else {

                attachFollowButton(
                    card.querySelector(
                        ".ajax-follow-button"
                    )
                );
            }


            return card;
        }


        /*
        |--------------------------------------------------------------------------
        | Build user object from existing card/button
        |--------------------------------------------------------------------------
        */

        function createUserObjectFromButton(
            button
        ) {

            const row =
                button.closest(
                    ".person-card"
                );

            return {

                id:
                    button.dataset.userId
                    || row?.dataset.userId
                    || "",

                name:
                    button.dataset.userName
                    || row?.dataset.userName
                    || "User",

                profile_picture_url:
                    button.dataset.profilePictureUrl
                    || row?.dataset.profilePictureUrl
                    || "",

                follower_count:
                    parseInt(
                        button.dataset.followerCount
                        || row?.dataset.followerCount,
                        10
                    ) || 0,

                bio:
                    row?.dataset.bio
                    || ""
            };
        }


        /*
        |--------------------------------------------------------------------------
        | Profile image modal
        |--------------------------------------------------------------------------
        */

        function openProfileImage(
            imageUrl,
            userName
        ) {

            if (
                !profileImageModal
                || !profileImageLarge
                || !imageUrl
            ) {
                return;
            }

            profileImageLarge.src =
                imageUrl;

            profileImageLarge.alt =
                (userName || "User")
                + " profile picture";

            profileImageModal.classList.add(
                "show"
            );

            profileImageModal.setAttribute(
                "aria-hidden",
                "false"
            );

            document.body.classList.add(
                "profile-image-modal-open"
            );

            if (profileImageClose) {
                profileImageClose.focus();
            }
        }


        function closeProfileImage() {

            if (
                !profileImageModal
                || !profileImageLarge
            ) {
                return;
            }

            profileImageModal.classList.remove(
                "show"
            );

            profileImageModal.setAttribute(
                "aria-hidden",
                "true"
            );

            document.body.classList.remove(
                "profile-image-modal-open"
            );

            profileImageLarge.src = "";
        }


        /*
        |--------------------------------------------------------------------------
        | Click delegation:
        | profile pictures + names/bios
        |--------------------------------------------------------------------------
        */

        document.addEventListener(
            "click",
            function (event) {

                /*
                 * Profile picture click
                 */

                const avatarButton =
                    event.target.closest(
                        ".person-avatar-button"
                    );

                if (avatarButton) {

                    const imageUrl =
                        avatarButton.dataset.imageUrl
                        || "";

                    const userName =
                        avatarButton.dataset.userName
                        || "User";

                    openProfileImage(
                        imageUrl,
                        userName
                    );

                    return;
                }


                /*
                 * Name click -> toggle bio
                 */

                const nameButton =
                    event.target.closest(
                        ".person-name-button"
                    );

                if (nameButton) {

                    const row =
                        nameButton.closest(
                            ".person-card"
                        );

                    if (!row) {
                        return;
                    }

                    const bio =
                        row.querySelector(
                            ".person-bio"
                        );

                    if (!bio) {
                        return;
                    }

                    const willOpen =
                        bio.hidden;

                    bio.hidden =
                        !willOpen;

                    nameButton.setAttribute(
                        "aria-expanded",
                        willOpen
                            ? "true"
                            : "false"
                    );

                    return;
                }
            }
        );


        /*
         * Modal close button
         */

        if (profileImageClose) {

            profileImageClose.addEventListener(
                "click",
                closeProfileImage
            );
        }


        /*
         * Click outside enlarged image to close
         */

        if (profileImageModal) {

            profileImageModal.addEventListener(
                "click",
                function (event) {

                    if (
                        event.target
                        === profileImageModal
                    ) {
                        closeProfileImage();
                    }
                }
            );
        }


        /*
         * Escape key closes profile image
         */

        document.addEventListener(
            "keydown",
            function (event) {

                if (
                    event.key === "Escape"
                    && profileImageModal
                    && profileImageModal.classList.contains(
                        "show"
                    )
                ) {
                    closeProfileImage();
                }
            }
        );


        /*
        |--------------------------------------------------------------------------
        | Available users search URL
        |--------------------------------------------------------------------------
        */

        function getAjaxUrl() {

            const params =
                new URLSearchParams();

            params.set(
                "offset",
                String(availableOffset)
            );

            if (currentSearch !== "") {

                params.set(
                    "friend_search",
                    currentSearch
                );
            }

            return "/ajax/search_available_friends.php?"
                + params.toString();
        }


        /*
        |--------------------------------------------------------------------------
        | Loading states
        |--------------------------------------------------------------------------
        */

        function setLoadingState(show) {

            isLoading = show;

            if (loadingRow) {

                loadingRow.classList.toggle(
                    "show",
                    show
                );
            }
        }


        function setEndState(show) {

            if (endRow) {

                endRow.classList.toggle(
                    "show",
                    show
                );
            }
        }


        /*
        |--------------------------------------------------------------------------
        | Load available users
        |--------------------------------------------------------------------------
        */

        function loadAvailableUsers(
            { reset = false } = {}
        ) {

            if (isLoading) {
                return;
            }

            if (
                !hasMore
                && !reset
            ) {
                return;
            }


            if (reset) {

                availableOffset = 0;

                hasMore = true;

                availableList.innerHTML = "";

                setEndState(false);

                updateEmptyStates();
            }


            setLoadingState(true);

            hideMessage();


            fetch(
                getAjaxUrl(),
                {
                    method: "GET",

                    headers: {
                        "Accept":
                            "application/json"
                    },

                    credentials:
                        "same-origin"
                }
            )

            .then(function (response) {

                if (!response.ok) {

                    throw new Error(
                        "HTTP "
                        + response.status
                    );
                }

                return response.json();
            })

            .then(function (data) {

                if (!data.success) {

                    showMessage(
                        data.message
                        || "Search failed."
                    );

                    hasMore = false;

                    return;
                }


                const users =
                    Array.isArray(data.users)
                        ? data.users
                        : [];


                users.forEach(
                    function (user) {

                        availableList.appendChild(
                            createPersonCard(
                                user,
                                "available"
                            )
                        );
                    }
                );


                availableOffset +=
                    users.length;


                hasMore =
                    users.length >= pageSize;


                if (availableStatus) {

                    if (
                        currentSearch !== ""
                    ) {

                        availableStatus.textContent =
                            "Showing results for “"
                            + currentSearch
                            + "”";

                    } else {

                        availableStatus.textContent =
                            "Search or scroll to discover people";
                    }
                }


                updateEmptyStates();


                if (
                    !hasMore
                    && users.length === 0
                    && availableOffset > 0
                ) {

                    setEndState(true);

                } else if (
                    !hasMore
                    && users.length > 0
                ) {

                    setEndState(true);
                }
            })

            .catch(function (error) {

                console.error(
                    "Available friends search error:",
                    error
                );

                showMessage(
                    "Search returned an invalid response. "
                    + "Make sure "
                    + "/ajax/search_available_friends.php "
                    + "exists and returns JSON only."
                );

                hasMore = false;
            })

            .finally(function () {

                setLoadingState(false);
            });
        }


        /*
        |--------------------------------------------------------------------------
        | Follow
        |--------------------------------------------------------------------------
        */

        function attachFollowButton(
            button
        ) {

            if (
                !button
                || button.dataset.bound === "1"
            ) {
                return;
            }


            button.dataset.bound = "1";


            button.addEventListener(
                "click",
                function () {

                    hideMessage();


                    const user =
                        createUserObjectFromButton(
                            button
                        );

                    const row =
                        button.closest(
                            ".person-card"
                        );


                    if (!row) {
                        return;
                    }


                    button.disabled = true;

                    button.textContent =
                        "Following...";

                    row.classList.add(
                        "is-moving"
                    );


                    fetch(
                        "/ajax/follow_friend.php",
                        {
                            method: "POST",

                            headers: {
                                "Content-Type":
                                    "application/x-www-form-urlencoded"
                            },

                            credentials:
                                "same-origin",

                            body:
                                "friend_id="
                                + encodeURIComponent(
                                    user.id
                                )
                        }
                    )

                    .then(function (response) {

                        if (!response.ok) {

                            throw new Error(
                                "HTTP "
                                + response.status
                            );
                        }

                        return response.json();
                    })

                    .then(function (data) {

                        if (!data.success) {

                            button.disabled =
                                false;

                            button.textContent =
                                "Follow";

                            row.classList.remove(
                                "is-moving"
                            );

                            showMessage(
                                data.message
                                || "Could not follow user."
                            );

                            return;
                        }


                        /*
                         * Use authoritative follower count
                         * returned by follow_friend.php.
                         */

                        const returnedCount =
                            parseInt(
                                data.follower_count,
                                10
                            );

                        if (
                            Number.isFinite(
                                returnedCount
                            )
                        ) {

                            user.follower_count =
                                returnedCount;

                        } else {

                            user.follower_count =
                                user.follower_count
                                + 1;
                        }


                        /*
                         * Removing a user from the Available
                         * result set reduces the server-side
                         * offset by one.
                         *
                         * This prevents infinite scrolling
                         * from skipping the next user.
                         */

                        if (
                            currentSearch === ""
                            && availableOffset > 0
                        ) {

                            availableOffset =
                                Math.max(
                                    0,
                                    availableOffset - 1
                                );
                        }


                        row.remove();


                        followingList.appendChild(
                            createPersonCard(
                                user,
                                "following"
                            )
                        );


                        updateEmptyStates();
                    })

                    .catch(function (error) {

                        console.error(
                            "Follow error:",
                            error
                        );

                        button.disabled =
                            false;

                        button.textContent =
                            "Follow";

                        row.classList.remove(
                            "is-moving"
                        );

                        showMessage(
                            "Could not follow user."
                        );
                    });
                }
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Unfollow
        |--------------------------------------------------------------------------
        */

        function attachUnfollowButton(
            button
        ) {

            if (
                !button
                || button.dataset.bound === "1"
            ) {
                return;
            }


            button.dataset.bound = "1";


            button.addEventListener(
                "click",
                function () {

                    if (
                        !confirm(
                            "Unfollow this person?"
                        )
                    ) {
                        return;
                    }


                    hideMessage();


                    const user =
                        createUserObjectFromButton(
                            button
                        );

                    const row =
                        button.closest(
                            ".person-card"
                        );


                    if (!row) {
                        return;
                    }


                    button.disabled = true;

                    button.textContent =
                        "Removing...";

                    row.classList.add(
                        "is-moving"
                    );


                    fetch(
                        "/ajax/unfollow_friend.php",
                        {
                            method: "POST",

                            headers: {
                                "Content-Type":
                                    "application/x-www-form-urlencoded"
                            },

                            credentials:
                                "same-origin",

                            body:
                                "friend_id="
                                + encodeURIComponent(
                                    user.id
                                )
                        }
                    )

                    .then(function (response) {

                        if (!response.ok) {

                            throw new Error(
                                "HTTP "
                                + response.status
                            );
                        }

                        return response.json();
                    })

                    .then(function (data) {

                        if (!data.success) {

                            button.disabled =
                                false;

                            button.textContent =
                                "Unfollow";

                            row.classList.remove(
                                "is-moving"
                            );

                            showMessage(
                                data.message
                                || "Could not unfollow user."
                            );

                            return;
                        }


                        /*
                         * Use authoritative follower count
                         * returned by unfollow_friend.php.
                         */

                        const returnedCount =
                            parseInt(
                                data.follower_count,
                                10
                            );

                        if (
                            Number.isFinite(
                                returnedCount
                            )
                        ) {

                            user.follower_count =
                                returnedCount;

                        } else {

                            user.follower_count =
                                Math.max(
                                    0,
                                    user.follower_count - 1
                                );
                        }


                        row.remove();


                        /*
                         * Preserve the existing behavior:
                         * immediately put the user back into
                         * Available when no search is active.
                         */

                        if (
                            currentSearch === ""
                        ) {

                            availableList.prepend(
                                createPersonCard(
                                    user,
                                    "available"
                                )
                            );

                            /*
                             * Because we inserted one user
                             * into the beginning of the
                             * currently loaded Available set,
                             * increase the next server offset.
                             */

                            availableOffset += 1;
                        }


                        updateEmptyStates();
                    })

                    .catch(function (error) {

                        console.error(
                            "Unfollow error:",
                            error
                        );

                        button.disabled =
                            false;

                        button.textContent =
                            "Unfollow";

                        row.classList.remove(
                            "is-moving"
                        );

                        showMessage(
                            "Could not unfollow user."
                        );
                    });
                }
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Friend search
        |--------------------------------------------------------------------------
        */

        if (searchInput) {

            searchInput.addEventListener(
                "input",
                function () {

                    window.clearTimeout(
                        searchTimer
                    );


                    searchTimer =
                        window.setTimeout(
                            function () {

                                currentSearch =
                                    searchInput
                                        .value
                                        .trim();

                                loadAvailableUsers({
                                    reset: true
                                });

                            },
                            300
                        );
                }
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Infinite scroll
        |--------------------------------------------------------------------------
        */

        if (
            sentinel
            && "IntersectionObserver" in window
        ) {

            const observer =
                new IntersectionObserver(
                    function (entries) {

                        entries.forEach(
                            function (entry) {

                                if (
                                    entry.isIntersecting
                                ) {

                                    loadAvailableUsers();
                                }
                            }
                        );

                    },
                    {
                        root: null,

                        rootMargin:
                            "150px",

                        threshold: 0
                    }
                );


            observer.observe(
                sentinel
            );

        } else {

            window.addEventListener(
                "scroll",
                function () {

                    if (
                        (
                            window.innerHeight
                            + window.scrollY
                        )
                        >=
                        document.body.offsetHeight
                        - 500
                    ) {

                        loadAvailableUsers();
                    }
                }
            );
        }


        /*
        |--------------------------------------------------------------------------
        | Bind initial Follow / Unfollow buttons
        |--------------------------------------------------------------------------
        */

        document
            .querySelectorAll(
                ".ajax-follow-button"
            )
            .forEach(
                attachFollowButton
            );


        document
            .querySelectorAll(
                ".ajax-unfollow-button"
            )
            .forEach(
                attachUnfollowButton
            );


        /*
        |--------------------------------------------------------------------------
        | Initial state
        |--------------------------------------------------------------------------
        */

        updateEmptyStates();


        if (!hasMore) {
            setEndState(false);
        }
    }
);

</script>