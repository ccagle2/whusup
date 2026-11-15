
<?php
/**
 * Whusup Groups Directory
 *
 * A group is automatically recognized when at least
 * two posts share the same normalized tag.
 *
 * No separate groups table or membership is required.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';

$is_logged_in = !empty($_SESSION['user_id']);

$pageTitle = 'Groups | Whusup';
$pageDescription = 'Explore groups and conversations on Whusup.';
$canonicalUrl = 'https://whusup.com/groups.php';

$ogTitle = $pageTitle;
$ogDescription = $pageDescription;
$ogUrl = $canonicalUrl;
$ogType = 'website';

$twitterTitle = $pageTitle;
$twitterDescription = $pageDescription;

// Directory pagination.
$groups_per_page = 24;

$requested_page = filter_input(
    INPUT_GET,
    'page',
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);

$current_page = $requested_page ?: 1;

// Protect against excessively large database offsets.
$current_page = min($current_page, 1000);

$groups_offset = ($current_page - 1) * $groups_per_page;

$groups = [];
$has_more_groups = false;
$groups_error = false;

try {
    /*
     * Load qualifying groups.
     *
     * - At least two posts per group.
     * - Case-insensitive grouping.
     * - Count distinct contributors.
     * - Show the most recent post date.
     * - Sort alphabetically.
     *
     * Fetch one additional group to detect the next page.
     */
    $sql = "
        SELECT
            MIN(TRIM(tag)) AS group_name,
            COUNT(*) AS post_count,
            COUNT(DISTINCT user_id) AS contributor_count,
            MAX(created_at) AS latest_post_at

        FROM posts

        WHERE tag IS NOT NULL
          AND TRIM(tag) <> ''

        GROUP BY LOWER(TRIM(tag))

        HAVING COUNT(*) >= 2

        ORDER BY
            group_name ASC

        LIMIT :group_limit OFFSET :group_offset
    ";

    $stmt = $pdo->prepare($sql);

    $stmt->bindValue(
        ':group_limit',
        $groups_per_page + 1,
        PDO::PARAM_INT
    );

    $stmt->bindValue(
        ':group_offset',
        $groups_offset,
        PDO::PARAM_INT
    );

    $stmt->execute();

    $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $has_more_groups = count($groups) > $groups_per_page;

    if ($has_more_groups) {
        $groups = array_slice($groups, 0, $groups_per_page);
    }

} catch (PDOException $e) {
    error_log(
        'Whusup groups directory error: ' . $e->getMessage()
    );

    $groups_error = true;
}

// Build links to the existing filtered feed.
$buildGroupUrl = static function (
    string $group_name
) use ($is_logged_in): string {

    $destination = $is_logged_in
        ? '/dashboard.php'
        : '/index.php';

    return $destination . '?' . http_build_query([
        'tag' => $group_name
    ]);
};

// Build directory pagination links.
$buildPageUrl = static function (int $page): string {
    return '/groups.php?' . http_build_query([
        'page' => $page
    ]);
};

include '../includes/header.php';
include '../includes/navbar.php';
?>

<style>
/* --------------------------------------------------
   Groups page layout
-------------------------------------------------- */

.whusup-groups-page {
    width: 100%;
    max-width: 1100px;
    margin: 28px auto 60px;
    padding: 0 20px;
    box-sizing: border-box;
}

.whusup-groups-header {
    margin-bottom: 26px;
    text-align: center;
}

.whusup-groups-title {
    margin: 0 0 8px;
    color: #111827;
    font-family: "Poppins", sans-serif;
    font-size: clamp(25px, 4vw, 34px);
    font-weight: 700;
    line-height: 1.3;
}

.whusup-groups-description {
    margin: 0;
    color: #6b7280;
    font-size: 14px;
    line-height: 1.6;
}

/* --------------------------------------------------
   Groups grid
-------------------------------------------------- */

.whusup-groups-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 16px;
}

/* --------------------------------------------------
   Group cards
-------------------------------------------------- */

.whusup-group-card {
    display: flex;
    align-items: flex-start;
    gap: 13px;

    min-width: 0;
    min-height: 130px;
    padding: 20px;

    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 16px;

    color: inherit;
    text-decoration: none;

    box-sizing: border-box;

    transition:
        border-color 0.16s ease,
        box-shadow 0.16s ease,
        transform 0.16s ease;
}

.whusup-group-card:hover {
    border-color: #cbd5e1;
    box-shadow: 0 6px 20px rgba(15, 23, 42, 0.07);
    transform: translateY(-2px);
    text-decoration: none;
}

.whusup-group-card:focus-visible {
    outline: 3px solid rgba(59, 130, 246, 0.35);
    outline-offset: 3px;
}

/* --------------------------------------------------
   Group icon
-------------------------------------------------- */

.whusup-group-icon {
    display: flex;
    align-items: center;
    justify-content: center;

    flex: 0 0 42px;
    width: 42px;
    height: 42px;

    background: #f3f4f6;
    border-radius: 12px;
    color: #374151;
}

.whusup-group-icon svg {
    width: 21px;
    height: 21px;

    fill: none;
    stroke: currentColor;
    stroke-width: 1.8;
    stroke-linecap: round;
    stroke-linejoin: round;
}

/* --------------------------------------------------
   Group text
-------------------------------------------------- */

.whusup-group-content {
    flex: 1;
    min-width: 0;
}

.whusup-group-name {
    margin: 1px 0 9px;

    color: #111827;
    font-family: "Poppins", sans-serif;
    font-size: 15px;
    font-weight: 700;
    line-height: 1.4;

    overflow-wrap: anywhere;
}

.whusup-group-stats {
    display: flex;
    flex-wrap: wrap;
    gap: 5px 12px;

    color: #6b7280;
    font-size: 12px;
    line-height: 1.5;
}

.whusup-group-activity {
    margin-top: 9px;
    color: #9ca3af;
    font-size: 11px;
    line-height: 1.5;
}

/* --------------------------------------------------
   Empty / error states
-------------------------------------------------- */

.whusup-groups-empty {
    padding: 45px 20px;
    text-align: center;
    color: #6b7280;
    font-size: 14px;
}

/* --------------------------------------------------
   Pagination
-------------------------------------------------- */

.whusup-groups-pagination {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 14px;
    margin-top: 30px;
}

.whusup-groups-page-link {
    display: inline-flex;
    justify-content: center;
    align-items: center;

    min-height: 40px;
    padding: 9px 18px;

    border: 1px solid #d1d5db;
    border-radius: 999px;

    background: #ffffff;
    color: #374151;

    font-size: 13px;
    font-weight: 700;
    text-decoration: none;
}

.whusup-groups-page-link:hover {
    background: #f3f4f6;
    color: #111827;
}

.whusup-groups-page-number {
    color: #6b7280;
    font-size: 12px;
}

/* Larger, more readable group card typography on desktop */

@media (min-width: 901px) {
    .whusup-group-name {
        font-size: 17px;
    }

    .whusup-group-stats {
        font-size: 14px;
    }

    .whusup-group-activity {
        font-size: 13px;
    }
}

/* --------------------------------------------------
   Tablet
-------------------------------------------------- */

@media (max-width: 900px) {
    .whusup-groups-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

/* --------------------------------------------------
   Mobile
-------------------------------------------------- */

@media (max-width: 600px) {
    .whusup-groups-page {
        margin-top: 18px;
        padding: 0 12px;
    }

    .whusup-groups-header {
        margin-bottom: 20px;
    }

    .whusup-groups-description {
        font-size: 15px;
    }

    .whusup-groups-grid {
        grid-template-columns: minmax(0, 1fr);
        gap: 10px;
    }

    .whusup-group-card {
        min-height: 108px;
        padding: 16px;
        border-radius: 14px;
    }

    .whusup-group-icon {
        flex-basis: 40px;
        width: 40px;
        height: 40px;
    }

    .whusup-group-name {
        font-size: 16px;
    }

    .whusup-group-stats {
        font-size: 13.5px;
    }

    .whusup-group-activity {
        font-size: 13px;
    }

    .whusup-groups-empty {
        font-size: 15px;
    }

    .whusup-groups-page-number {
        font-size: 13px;
    }

    .whusup-groups-page-link {
        font-size: 14px;
    }
}
</style>

<main class="whusup-groups-page">

    <header class="whusup-groups-header">
        <h1 class="whusup-groups-title">
            Explore Groups
        </h1>

        <p class="whusup-groups-description">
            Discover conversations and connect through shared interests.
        </p>
    </header>

    <?php if ($groups_error): ?>

        <div class="whusup-groups-empty">
            Groups are temporarily unavailable.
            Please try again later.
        </div>

    <?php elseif (empty($groups)): ?>

        <div class="whusup-groups-empty">
            <?php if ($current_page > 1): ?>
                No more groups to show.
            <?php else: ?>
                No groups available yet.
            <?php endif; ?>
        </div>

    <?php else: ?>

        <div class="whusup-groups-grid">

            <?php foreach ($groups as $group): ?>

                <?php
                $group_name = (string) $group['group_name'];
                $post_count = (int) $group['post_count'];
                $contributor_count = (int) $group['contributor_count'];

                $latest_post_at = $group['latest_post_at'];
                $formatted_date = '';

                if (!empty($latest_post_at)) {
                    $timestamp = strtotime($latest_post_at);

                    if ($timestamp !== false) {
                        $formatted_date = date('M j, Y', $timestamp);
                    }
                }

                $group_url = $buildGroupUrl($group_name);
                ?>

                <a
                    class="whusup-group-card"
                    href="<?= htmlspecialchars(
                        $group_url,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
                >

                    <span class="whusup-group-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24">
                            <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
                            <circle cx="9" cy="7" r="4"/>
                            <path d="M22 21v-2a4 4 0 0 0-3-3.87"/>
                            <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                        </svg>
                    </span>

                    <div class="whusup-group-content">

                        <h2 class="whusup-group-name">
                            <?= htmlspecialchars(
                                $group_name,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </h2>

                        <div class="whusup-group-stats">

                            <span>
                                <?= number_format($post_count) ?>
                                <?= $post_count === 1 ? 'post' : 'posts' ?>
                            </span>

                            <span>
                                <?= number_format($contributor_count) ?>
                                <?= $contributor_count === 1
                                    ? 'contributor'
                                    : 'contributors' ?>
                            </span>

                        </div>

                        <?php if ($formatted_date !== ''): ?>
                            <div class="whusup-group-activity">
                                Latest post:
                                <?= htmlspecialchars(
                                    $formatted_date,
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </div>
                        <?php endif; ?>

                    </div>

                </a>

            <?php endforeach; ?>

        </div>

    <?php endif; ?>

    <?php if (
        !$groups_error
        && ($current_page > 1 || $has_more_groups)
    ): ?>

        <nav
            class="whusup-groups-pagination"
            aria-label="Groups pagination"
        >

            <?php if ($current_page > 1): ?>
                <a
                    class="whusup-groups-page-link"
                    href="<?= htmlspecialchars(
                        $buildPageUrl($current_page - 1),
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
                >
                    Previous
                </a>
            <?php endif; ?>

            <span class="whusup-groups-page-number">
                Page <?= $current_page ?>
            </span>

            <?php if ($has_more_groups): ?>
                <a
                    class="whusup-groups-page-link"
                    href="<?= htmlspecialchars(
                        $buildPageUrl($current_page + 1),
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
                >
                    Next
                </a>
            <?php endif; ?>


        </nav>

    <?php endif; ?>

    <!-- Back to Feed -->
    <div style="text-align: center; margin-top: 32px;">
        <a
            class="whusup-groups-page-link"
            href="<?= $is_logged_in ? '/dashboard.php' : '/index.php' ?>"
        >
            &larr; Back to Feed
        </a>
    </div>

</main>

<?php
include '../includes/footer.php';
?>

