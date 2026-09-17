<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';

$awsConfig = require __DIR__ . '/../config/aws.php';

require_once __DIR__ . '/../includes/posts/post_helpers.php';
require_once __DIR__ . '/../includes/posts/post_queries.php';
require_once __DIR__ . '/../includes/posts/comment_tree.php';
require_once __DIR__ . '/../includes/posts/post_card.php';

$user_id = $_SESSION['user_id'] ?? null;
$is_logged_in = !empty($user_id);

$q = trim((string) ($_GET['q'] ?? ''));

/*
|--------------------------------------------------------------------------
| Search safeguards
|--------------------------------------------------------------------------
*/

const WHUSUP_SEARCH_MIN_LENGTH = 3;
const WHUSUP_SEARCH_MAX_LENGTH = 100;
const WHUSUP_SEARCH_RATE_LIMIT = 20;
const WHUSUP_SEARCH_RATE_WINDOW = 60;

/**
 * Return the visitor IP used for lightweight search rate limiting.
 *
 * CloudFront commonly appends the viewer address to X-Forwarded-For. The
 * first valid address is therefore preferred, with REMOTE_ADDR as fallback.
 */
function getSearchClientIp(): string
{
    $forwardedFor = (string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '');

    if ($forwardedFor !== '') {
        foreach (explode(',', $forwardedFor) as $candidate) {
            $candidate = trim($candidate);

            if (filter_var($candidate, FILTER_VALIDATE_IP)) {
                return $candidate;
            }
        }
    }

    $remoteAddress = trim((string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));

    return filter_var($remoteAddress, FILTER_VALIDATE_IP)
        ? $remoteAddress
        : 'unknown';
}

/**
 * Apply a small, file-backed, per-IP rate limit.
 *
 * This is appropriate for the site's current single-server architecture and
 * requires no additional database table. The lock prevents simultaneous PHP
 * requests from corrupting the counter file.
 *
 * @return array{allowed: bool, retry_after: int}
 */
function checkSearchRateLimit(string $clientIp): array
{
    $now = time();
    $windowStart = $now - WHUSUP_SEARCH_RATE_WINDOW;
    $rateDirectory = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR)
        . DIRECTORY_SEPARATOR
        . 'whusup-search-rate-limit';

    if (!is_dir($rateDirectory)) {
        @mkdir($rateDirectory, 0700, true);
    }

    if (!is_dir($rateDirectory) || !is_writable($rateDirectory)) {
        // Fail open so a temporary filesystem issue never breaks search.
        return ['allowed' => true, 'retry_after' => 0];
    }

    $rateFile = $rateDirectory
        . DIRECTORY_SEPARATOR
        . hash('sha256', $clientIp)
        . '.json';

    $handle = @fopen($rateFile, 'c+');

    if ($handle === false) {
        return ['allowed' => true, 'retry_after' => 0];
    }

    $timestamps = [];

    try {
        if (!flock($handle, LOCK_EX)) {
            return ['allowed' => true, 'retry_after' => 0];
        }

        rewind($handle);
        $stored = stream_get_contents($handle);
        $decoded = $stored !== false && $stored !== ''
            ? json_decode($stored, true)
            : [];

        if (is_array($decoded)) {
            foreach ($decoded as $timestamp) {
                $timestamp = (int) $timestamp;

                if ($timestamp > $windowStart && $timestamp <= $now) {
                    $timestamps[] = $timestamp;
                }
            }
        }

        if (count($timestamps) >= WHUSUP_SEARCH_RATE_LIMIT) {
            $oldestTimestamp = min($timestamps);
            $retryAfter = max(
                1,
                WHUSUP_SEARCH_RATE_WINDOW - ($now - $oldestTimestamp)
            );

            return [
                'allowed' => false,
                'retry_after' => $retryAfter,
            ];
        }

        $timestamps[] = $now;

        rewind($handle);
        ftruncate($handle, 0);
        fwrite($handle, json_encode($timestamps, JSON_THROW_ON_ERROR));
        fflush($handle);

        return ['allowed' => true, 'retry_after' => 0];
    } catch (Throwable $exception) {
        error_log('Search rate-limit error: ' . $exception->getMessage());

        return ['allowed' => true, 'retry_after' => 0];
    } finally {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}

if (function_exists('mb_substr')) {
    $q = mb_substr($q, 0, WHUSUP_SEARCH_MAX_LENGTH, 'UTF-8');
    $queryLength = mb_strlen($q, 'UTF-8');
} else {
    $q = substr($q, 0, WHUSUP_SEARCH_MAX_LENGTH);
    $queryLength = strlen($q);
}

$searchError = '';
$searchIsValid = false;
$rateLimitRetryAfter = 0;

if ($q !== '' && $queryLength < WHUSUP_SEARCH_MIN_LENGTH) {
    $searchError = 'Please enter at least '
        . WHUSUP_SEARCH_MIN_LENGTH
        . ' characters.';
} elseif ($q !== '') {
    $rateLimit = checkSearchRateLimit(getSearchClientIp());

    if (!$rateLimit['allowed']) {
        $rateLimitRetryAfter = (int) $rateLimit['retry_after'];
        $searchError = 'Too many searches were submitted. Please try again in '
            . $rateLimitRetryAfter
            . ($rateLimitRetryAfter === 1 ? ' second.' : ' seconds.');

        http_response_code(429);
        header('Retry-After: ' . $rateLimitRetryAfter);
    } else {
        $searchIsValid = true;
    }
}

/*
|--------------------------------------------------------------------------
| Page metadata
|--------------------------------------------------------------------------
*/

$pageTitle = $searchIsValid
    ? 'Search: ' . cleanMetaText($q, 50) . ' | Whusup'
    : 'Search Whusup';

$pageDescription = $searchIsValid
    ? 'Search results for ' . cleanMetaText($q, 80) . ' on Whusup.'
    : 'Search topics, posts, and people on Whusup.';

$canonicalUrl = 'https://whusup.com/search.php'
    . ($searchIsValid ? '?q=' . urlencode($q) : '');

/*
|--------------------------------------------------------------------------
| Load capped people, topic, and post results
|--------------------------------------------------------------------------
*/

$peopleResults = [];
$topicResults = [];
$recent_posts = [];
$comments_by_post = [];
$post_images_by_post = [];

if ($searchIsValid) {
    // Result caps keep each public search request predictable and inexpensive.
    $peopleResults = loadSearchPeopleData($pdo, $q, 12);
    $topicResults = loadSearchTopicData($pdo, $q, 12);

    $postData = loadSearchPostData(
        $pdo,
        $q,
        $user_id,
        20
    );

    $recent_posts = $postData['posts'] ?? [];
    $comments_by_post = $postData['comments_by_post'] ?? [];
    $post_images_by_post = $postData['post_images_by_post'] ?? [];
}

$hasPeopleResults = !empty($peopleResults);
$hasTopicResults = !empty($topicResults);
$hasPostResults = !empty($recent_posts);
$hasAnyResults = $hasPeopleResults || $hasTopicResults || $hasPostResults;

/*
|--------------------------------------------------------------------------
| Delete-post redirect
|--------------------------------------------------------------------------
*/

$redirect_url = '/search.php' .
    ($q !== '' ? '?q=' . urlencode($q) : '');

require __DIR__ . '/../includes/posts/delete_post_handler.php';

include '../includes/header.php';
?>

<title><?= htmlspecialchars($pageTitle) ?></title>

<meta
    name="description"
    content="<?= htmlspecialchars($pageDescription) ?>"
>

<link
    rel="canonical"
    href="<?= htmlspecialchars($canonicalUrl) ?>"
>

<?php
include '../includes/navbar.php';
include '../includes/posts/post_styles.php';
?>

<style>
.search-results-section {
    margin-bottom: 24px;
}

.search-results-heading {
    margin: 0 0 12px;
    color: #111827;
    font-family: "Poppins", sans-serif;
    font-size: 1.1rem;
    font-weight: 800;
}

.people-results-grid {
    display: grid;
    grid-template-columns: repeat(
        auto-fill,
        minmax(210px, 1fr)
    );
    gap: 12px;
}

.people-result-card {
    display: flex;
    align-items: center;
    gap: 12px;
    min-width: 0;
    padding: 13px 14px;
    border: 1px solid #e5e7eb;
    border-radius: 14px;
    background: #ffffff;
    box-shadow: 0 3px 10px rgba(17, 24, 39, 0.05);
}

.people-result-avatar {
    width: 46px;
    height: 46px;
    flex: 0 0 46px;
    overflow: hidden;
    border: 2px solid #ffffff;
    border-radius: 50%;
    background: #e5e7eb;
    color: #4b5563;
    box-shadow: 0 2px 8px rgba(17, 24, 39, 0.12);

    display: flex;
    align-items: center;
    justify-content: center;

    font-family: "Poppins", sans-serif;
    font-size: 14px;
    font-weight: 800;
}

.people-result-avatar img {
    display: block;
    width: 100%;
    height: 100%;
    object-fit: cover;
    object-position: center;
}

.people-result-details {
    min-width: 0;
}

.people-result-name {
    overflow: hidden;
    margin: 0;
    color: #111827;
    font-family: "Poppins", sans-serif;
    font-size: 14px;
    font-weight: 750;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.people-result-label {
    margin-top: 2px;
    color: #9ca3af;
    font-family: "Poppins", sans-serif;
    font-size: 12px;
    font-weight: 600;
}

.topic-results-grid {
    display: grid;
    grid-template-columns: repeat(
        auto-fill,
        minmax(210px, 1fr)
    );
    gap: 12px;
}

.topic-result-card {
    display: flex;
    align-items: center;
    gap: 12px;
    min-width: 0;
    padding: 13px 14px;
    border: 1px solid #e5e7eb;
    border-radius: 14px;
    background: #ffffff;
    color: inherit;
    text-decoration: none;
    box-shadow: 0 3px 10px rgba(17, 24, 39, 0.05);
    transition: transform 140ms ease, box-shadow 140ms ease, border-color 140ms ease;
}

.topic-result-card:hover {
    color: inherit;
    text-decoration: none;
    transform: translateY(-1px);
    border-color: #d1d5db;
    box-shadow: 0 7px 18px rgba(17, 24, 39, 0.08);
}

.topic-result-card:focus {
    outline: none;
}

.topic-result-card:focus-visible {
    outline: 3px solid rgba(37, 99, 235, 0.25);
    outline-offset: 2px;
}

.topic-result-icon {
    width: 42px;
    height: 42px;
    flex: 0 0 42px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 12px;
    background: #f3f4f6;
    color: #374151;
    font-size: 20px;
}

.topic-result-details {
    min-width: 0;
}

.topic-result-name {
    overflow: hidden;
    margin: 0;
    color: #111827;
    font-family: "Poppins", sans-serif;
    font-size: 14px;
    font-weight: 750;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.topic-result-count {
    margin-top: 2px;
    color: #9ca3af;
    font-family: "Poppins", sans-serif;
    font-size: 12px;
    font-weight: 600;
}

.search-results-divider {
    height: 1px;
    margin: 22px 0;
    background: #e5e7eb;
}

@media (max-width: 576px) {
    .people-results-grid,
    .topic-results-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<main class="recent-posts-wrapper">

    <h1 class="whusup-page-title">
        <?php if ($q !== ''): ?>
            Search results for "<?= htmlspecialchars($q) ?>"
        <?php else: ?>
            Search Whusup
        <?php endif; ?>
    </h1>

    <form
        method="GET"
        action="/search.php"
        class="whusup-search-form"
        role="search"
    >
        <input
            type="search"
            name="q"
            value="<?= htmlspecialchars($q) ?>"
            placeholder="Find Anything"
            aria-label="Search topics, people and posts"
            class="whusup-search-input text-center"
            minlength="<?= WHUSUP_SEARCH_MIN_LENGTH ?>"
            maxlength="<?= WHUSUP_SEARCH_MAX_LENGTH ?>"
            autocomplete="off"
            enterkeyhint="search"
            required
        >
    </form>

    <?php if ($searchError !== ''): ?>

        <div class="no-posts" role="alert">
            <?= htmlspecialchars($searchError) ?>
        </div>

    <?php elseif ($q === ''): ?>

        <div class="no-posts">
            Enter at least <?= WHUSUP_SEARCH_MIN_LENGTH ?> characters to search
            topics, people, and posts.
        </div>

    <?php elseif (!$hasAnyResults): ?>

        <div class="no-posts">
            No people, topics, or posts found for
            “<?= htmlspecialchars($q) ?>”.
        </div>

    <?php else: ?>

        <?php if ($hasPeopleResults): ?>

            <section class="search-results-section">
                <h2 class="search-results-heading">
                    People
                </h2>

                <div class="people-results-grid">

                    <?php foreach ($peopleResults as $person): ?>

                        <?php
                        $personName = trim(
                            (string) ($person['name'] ?? '')
                        );

                        $nameParts = preg_split(
                            '/\s+/',
                            $personName,
                            -1,
                            PREG_SPLIT_NO_EMPTY
                        );

                        $initials = '';

                        if (!empty($nameParts)) {
                            $initials .= strtoupper(
                                substr($nameParts[0], 0, 1)
                            );

                            if (count($nameParts) > 1) {
                                $initials .= strtoupper(
                                    substr(
                                        $nameParts[count($nameParts) - 1],
                                        0,
                                        1
                                    )
                                );
                            }
                        }

                        $profileImageUrl =
                            !empty($person['profile_picture_url'])
                                ? getS3ImageUrl(
                                    $person['profile_picture_url']
                                )
                                : null;
                        ?>

                        <article class="people-result-card">

                            <div class="people-result-avatar">
                                <?php if ($profileImageUrl): ?>
                                    <img
                                        src="<?= htmlspecialchars($profileImageUrl) ?>"
                                        alt="<?= htmlspecialchars($personName) ?> profile picture"
                                        loading="lazy"
                                    >
                                <?php else: ?>
                                    <?= htmlspecialchars($initials) ?>
                                <?php endif; ?>
                            </div>

                            <div class="people-result-details">
                                <p class="people-result-name">
                                    <?= htmlspecialchars(
                                        ucwords($personName)
                                    ) ?>
                                </p>

                                <div class="people-result-label">
                                    Whusup member
                                </div>
                            </div>

                        </article>

                    <?php endforeach; ?>

                </div>
            </section>

        <?php endif; ?>

        <?php if ($hasPeopleResults && ($hasTopicResults || $hasPostResults)): ?>
            <div class="search-results-divider"></div>
        <?php endif; ?>

        <?php if ($hasTopicResults): ?>

            <section class="search-results-section">
                <h2 class="search-results-heading">
                    Topics
                </h2>

                <div class="topic-results-grid">

                    <?php foreach ($topicResults as $topic): ?>

                        <?php
                        $topicName = trim((string) ($topic['tag'] ?? ''));
                        $topicPostCount = (int) ($topic['post_count'] ?? 0);

                        if ($topicName === '') {
                            continue;
                        }
                        ?>

                        <a
                            href="/tag/<?= rawurlencode(makePostSlug($topicName)) ?>"
                            class="topic-result-card"
                            aria-label="View topic <?= htmlspecialchars($topicName, ENT_QUOTES, 'UTF-8') ?>"
                        >
                            <span class="topic-result-icon" aria-hidden="true">
                                #
                            </span>

                            <span class="topic-result-details">
                                <span class="topic-result-name">
                                    <?= htmlspecialchars($topicName) ?>
                                </span>

                                <span class="topic-result-count">
                                    <?= htmlspecialchars($topicPostCount) ?>
                                    <?= $topicPostCount === 1 ? 'post' : 'posts' ?>
                                </span>
                            </span>
                        </a>

                    <?php endforeach; ?>

                </div>
            </section>

        <?php endif; ?>

        <?php if ($hasTopicResults && $hasPostResults): ?>
            <div class="search-results-divider"></div>
        <?php endif; ?>

        <?php if ($hasPostResults): ?>

            <section class="search-results-section">
                <h2 class="search-results-heading">
                    Posts
                </h2>

                <div id="recent-posts-list">

                    <?= renderPostCards(
                        $recent_posts,
                        $comments_by_post,
                        $post_images_by_post,
                        $user_id,
                        $is_logged_in
                    ) ?>

                </div>
            </section>

        <?php elseif ($hasPeopleResults || $hasTopicResults): ?>

            <div class="no-posts">
                No matching posts were found.
            </div>

        <?php endif; ?>

    <?php endif; ?>

</main>

<div
    class="image-lightbox"
    id="imageLightbox"
    aria-hidden="true"
    data-current-index="0"
>
    <button
        type="button"
        class="image-lightbox-close"
        id="imageLightboxClose"
        aria-label="Close image"
    >
        ×
    </button>

    <button
        type="button"
        class="image-lightbox-arrow image-lightbox-prev"
        id="imageLightboxPrev"
        aria-label="Previous image"
    >
        ‹
    </button>

    <div class="image-lightbox-content">
        <img
            src=""
            alt="Expanded image"
            class="image-lightbox-image"
            id="imageLightboxImage"
        >
    </div>

    <button
        type="button"
        class="image-lightbox-arrow image-lightbox-next"
        id="imageLightboxNext"
        aria-label="Next image"
    >
        ›
    </button>

    <div
        class="image-lightbox-dots"
        id="imageLightboxDots"
        aria-label="Image position"
    ></div>
</div>

<?php
include '../includes/posts/post_scripts.php';
include '../includes/footer.php';
?>