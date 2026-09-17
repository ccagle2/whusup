<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';

$user_id = $_SESSION['user_id'] ?? null;
$is_logged_in = !empty($user_id);

/*
|--------------------------------------------------------------------------
| Fixed return destination
|--------------------------------------------------------------------------
|
| Do not use HTTP_REFERER or a user-provided redirect parameter.
|
*/

$returnUrl = $is_logged_in
    ? '/dashboard.php'
    : '/';

$feedback = '';
$feedbackError = '';
$feedbackSuccess = '';

/*
|--------------------------------------------------------------------------
| Helper functions
|--------------------------------------------------------------------------
*/

if (!function_exists('feedbackTextLength')) {
    function feedbackTextLength(string $text): int
    {
        return function_exists('mb_strlen')
            ? mb_strlen($text, 'UTF-8')
            : strlen($text);
    }
}

if (!function_exists('feedbackClientIp')) {
    function feedbackClientIp(): string
    {
        /*
         * REMOTE_ADDR is intentionally used instead of trusting arbitrary
         * forwarded headers supplied by the browser.
         */
        $remoteAddress = trim(
            (string) ($_SERVER['REMOTE_ADDR'] ?? '')
        );

        return $remoteAddress !== ''
            ? $remoteAddress
            : 'unknown';
    }
}

/*
|--------------------------------------------------------------------------
| Security configuration
|--------------------------------------------------------------------------
*/

$feedbackHashSalt = (string) ($env['FEEDBACK_HASH_SALT'] ?? '');

if ($feedbackHashSalt === '') {
    error_log('FEEDBACK_HASH_SALT is missing from the environment.');
}

/*
|--------------------------------------------------------------------------
| Flash success message
|--------------------------------------------------------------------------
*/

if (!empty($_SESSION['feedback_success_message'])) {
    $feedbackSuccess = (string) $_SESSION['feedback_success_message'];

    unset($_SESSION['feedback_success_message']);
}

/*
|--------------------------------------------------------------------------
| CSRF token
|--------------------------------------------------------------------------
*/

if (
    empty($_SESSION['feedback_csrf_token'])
    || !is_string($_SESSION['feedback_csrf_token'])
) {
    $_SESSION['feedback_csrf_token'] = bin2hex(
        random_bytes(32)
    );
}

/*
|--------------------------------------------------------------------------
| Form timing token
|--------------------------------------------------------------------------
|
| The form ID and creation time make automated instant submissions harder.
| A new form ID is generated after a successful submission.
|
*/

if (
    empty($_SESSION['feedback_form_id'])
    || empty($_SESSION['feedback_form_started_at'])
) {
    $_SESSION['feedback_form_id'] = bin2hex(
        random_bytes(16)
    );

    $_SESSION['feedback_form_started_at'] = time();
}

/*
|--------------------------------------------------------------------------
| Process feedback submission
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $feedback = trim(
        (string) ($_POST['feedback_text'] ?? '')
    );

    $submittedCsrfToken = (string) (
        $_POST['csrf_token']
        ?? ''
    );

    $submittedFormId = (string) (
        $_POST['form_id']
        ?? ''
    );

    $honeypot = trim(
        (string) ($_POST['website'] ?? '')
    );

    $sessionCsrfToken = (string) (
        $_SESSION['feedback_csrf_token']
        ?? ''
    );

    $sessionFormId = (string) (
        $_SESSION['feedback_form_id']
        ?? ''
    );

    $formStartedAt = (int) (
        $_SESSION['feedback_form_started_at']
        ?? 0
    );

    $elapsedSeconds = $formStartedAt > 0
        ? time() - $formStartedAt
        : 0;

    /*
     * Honeypot failures are handled generically so bots do not receive
     * useful information about which protection they triggered.
     */
    if ($honeypot !== '') {
        $feedbackError =
            'Unable to submit feedback. Please refresh the page and try again.';
    } elseif (
        $submittedCsrfToken === ''
        || $sessionCsrfToken === ''
        || !hash_equals(
            $sessionCsrfToken,
            $submittedCsrfToken
        )
    ) {
        $feedbackError =
            'Your form session expired. Please refresh the page and try again.';
    } elseif (
        $submittedFormId === ''
        || $sessionFormId === ''
        || !hash_equals(
            $sessionFormId,
            $submittedFormId
        )
    ) {
        $feedbackError =
            'Your form session expired. Please refresh the page and try again.';
    } elseif ($elapsedSeconds < 3) {
        $feedbackError =
            'Please take a moment to review your feedback before submitting it.';
    } elseif ($feedback === '') {
        $feedbackError =
            'Please enter your feedback.';
    } elseif (feedbackTextLength($feedback) > 500) {
        $feedbackError =
            'Feedback must be 500 characters or fewer.';
    } elseif ($feedbackHashSalt === '') {
        $feedbackError =
            'Feedback is temporarily unavailable. Please try again later.';
    } else {
        $clientIp = feedbackClientIp();
        $sessionId = session_id();

        $ipHash = hash_hmac(
            'sha256',
            $clientIp,
            $feedbackHashSalt
        );

        $sessionHash = hash_hmac(
            'sha256',
            $sessionId,
            $feedbackHashSalt
        );

        /*
         * Allow one successful submission per session every 30 seconds.
         */
        $lastSubmissionAt = (int) (
            $_SESSION['last_feedback_submission_at']
            ?? 0
        );

        if (
            $lastSubmissionAt > 0
            && time() - $lastSubmissionAt < 30
        ) {
            $feedbackError =
                'Please wait a moment before submitting more feedback.';
        } else {
            try {
                /*
                 * Allow no more than five submissions from the same IP or
                 * session during a rolling one-hour period.
                 */
                $rateLimitStmt = $pdo->prepare("
                    SELECT COUNT(*)
                    FROM feedback_submissions
                    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
                      AND (
                            ip_hash = :ip_hash
                            OR session_hash = :session_hash
                      )
                ");

                $rateLimitStmt->execute([
                    ':ip_hash' => $ipHash,
                    ':session_hash' => $sessionHash,
                ]);

                $recentSubmissionCount = (int) (
                    $rateLimitStmt->fetchColumn()
                );

                if ($recentSubmissionCount >= 5) {
                    $feedbackError =
                        'You have submitted several messages recently. Please try again later.';
                } else {
                    $userAgent = substr(
                        (string) (
                            $_SERVER['HTTP_USER_AGENT']
                            ?? ''
                        ),
                        0,
                        255
                    );

                    $insertStmt = $pdo->prepare("
                        INSERT INTO feedback_submissions (
                            user_id,
                            feedback_text,
                            ip_hash,
                            session_hash,
                            user_agent
                        ) VALUES (
                            :user_id,
                            :feedback_text,
                            :ip_hash,
                            :session_hash,
                            :user_agent
                        )
                    ");

                    if ($user_id === null) {
                        $insertStmt->bindValue(
                            ':user_id',
                            null,
                            PDO::PARAM_NULL
                        );
                    } else {
                        /*
                         * Whusup user IDs are stored as BINARY(16).
                         */
                        $insertStmt->bindValue(
                            ':user_id',
                            $user_id,
                            PDO::PARAM_STR
                        );
                    }

                    $insertStmt->bindValue(
                        ':feedback_text',
                        $feedback,
                        PDO::PARAM_STR
                    );

                    $insertStmt->bindValue(
                        ':ip_hash',
                        $ipHash,
                        PDO::PARAM_STR
                    );

                    $insertStmt->bindValue(
                        ':session_hash',
                        $sessionHash,
                        PDO::PARAM_STR
                    );

                    $insertStmt->bindValue(
                        ':user_agent',
                        $userAgent !== ''
                            ? $userAgent
                            : null,
                        $userAgent !== ''
                            ? PDO::PARAM_STR
                            : PDO::PARAM_NULL
                    );

                    $insertStmt->execute();

                    $_SESSION['last_feedback_submission_at'] =
                        time();

                    $_SESSION['feedback_success_message'] =
                        'Thank you. Your feedback has been submitted.';

                    /*
                     * Rotate the form identifiers after success.
                     */
                    $_SESSION['feedback_csrf_token'] = bin2hex(
                        random_bytes(32)
                    );

                    $_SESSION['feedback_form_id'] = bin2hex(
                        random_bytes(16)
                    );

                    $_SESSION['feedback_form_started_at'] =
                        time();

                    header(
                        'Location: /about.php',
                        true,
                        303
                    );

                    exit;
                }
            } catch (Throwable $exception) {
                error_log(
                    'Feedback submission failed: '
                    . $exception->getMessage()
                );

                $feedbackError =
                    'Feedback could not be submitted right now. Please try again later.';
            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| Refresh the form timer after an unsuccessful submission
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && $feedbackError !== ''
) {
    $_SESSION['feedback_form_id'] = bin2hex(
        random_bytes(16)
    );

    $_SESSION['feedback_form_started_at'] = time();
}

/*
|--------------------------------------------------------------------------
| Page metadata
|--------------------------------------------------------------------------
*/

$pageTitle = 'About Whusup';

$pageDescription =
    'Learn about Whusup and share feedback to help improve the community.';

$canonicalUrl = 'https://whusup.com/about.php';

include '../includes/header.php';
?>

<title><?= htmlspecialchars(
    $pageTitle,
    ENT_QUOTES,
    'UTF-8'
) ?></title>

<meta
    name="description"
    content="<?= htmlspecialchars(
        $pageDescription,
        ENT_QUOTES,
        'UTF-8'
    ) ?>"
>

<link
    rel="canonical"
    href="<?= htmlspecialchars(
        $canonicalUrl,
        ENT_QUOTES,
        'UTF-8'
    ) ?>"
>

<?php include '../includes/navbar.php'; ?>

<style>
html,
body {
    margin-bottom: 0;
}

.about-page {
    width: 100%;
    margin: 0;
    padding: 34px 16px 44px;
    background:
        radial-gradient(
            circle at top,
            rgba(229, 231, 235, 0.7),
            transparent 38%
        ),
        #f3f4f6;
    box-sizing: border-box;
}

.about-container {
    width: 100%;
    max-width: 820px;
    margin: 0 auto;
}

.about-card,
.feedback-card {
    width: 100%;
    border: 1px solid #e5e7eb;
    border-radius: 18px;
    background: #ffffff;
    box-shadow: 0 8px 26px rgba(17, 24, 39, 0.07);
    box-sizing: border-box;
}

.about-card {
    padding: 34px 38px;
    text-align: center;
}

.about-eyebrow {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 12px;
    padding: 6px 12px;
    border-radius: 999px;
    background: #f3f4f6;
    color: #6b7280;
    font-family: "Poppins", sans-serif;
    font-size: 12px;
    font-weight: 700;
    letter-spacing: 0.04em;
    text-transform: uppercase;
}

.about-title {
    margin: 0 0 14px;
    color: #111827;
    font-family: "Poppins", sans-serif;
    font-size: clamp(30px, 5vw, 42px);
    font-weight: 800;
    letter-spacing: -0.8px;
}

.about-text {
    max-width: 680px;
    margin: 0 auto;
    color: #4b5563;
    font-family: "Poppins", sans-serif;
    font-size: 16px;
    line-height: 1.75;
}

.feedback-card {
    margin-top: 18px;
    padding: 28px 30px;
}

.feedback-heading {
    margin: 0 0 6px;
    color: #111827;
    font-family: "Poppins", sans-serif;
    font-size: 21px;
    font-weight: 750;
}

.feedback-intro {
    margin: 0 0 18px;
    color: #6b7280;
    font-family: "Poppins", sans-serif;
    font-size: 14px;
    line-height: 1.55;
}

.feedback-form {
    margin: 0;
}

.feedback-textarea {
    display: block;
    width: 100%;
    min-height: 125px;
    padding: 14px 15px;
    border: 1px solid #d1d5db;
    border-radius: 14px;
    background: #ffffff;
    color: #111827;
    font-family: "Poppins", sans-serif;
    font-size: 14px;
    line-height: 1.55;
    resize: vertical;
    outline: none;
    box-sizing: border-box;
    transition:
        border-color 0.2s ease,
        box-shadow 0.2s ease;
}

.feedback-textarea:focus {
    border-color: #6b7280;
    box-shadow: 0 0 0 4px rgba(107, 114, 128, 0.12);
}

.feedback-form-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
    margin-top: 12px;
}

.feedback-character-count {
    color: #9ca3af;
    font-family: "Poppins", sans-serif;
    font-size: 12px;
    font-weight: 600;
}

.feedback-character-count.near-limit {
    color: #b45309;
}

.feedback-character-count.at-limit {
    color: #b91c1c;
}

.feedback-submit {
    padding: 9px 20px;
    border: 1px solid #111827;
    border-radius: 999px;
    background: #111827;
    color: #ffffff;
    font-family: "Poppins", sans-serif;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
    transition:
        background 0.2s ease,
        transform 0.2s ease;
}

.feedback-submit:hover {
    background: #374151;
    transform: translateY(-1px);
}

.feedback-submit:focus-visible {
    outline: 3px solid rgba(17, 24, 39, 0.2);
    outline-offset: 2px;
}

.feedback-submit:disabled {
    cursor: not-allowed;
    opacity: 0.55;
    transform: none;
}

.feedback-alert {
    margin-bottom: 16px;
    padding: 11px 13px;
    border-radius: 12px;
    font-family: "Poppins", sans-serif;
    font-size: 13px;
    line-height: 1.45;
}

.feedback-alert-success {
    border: 1px solid #bbf7d0;
    background: #f0fdf4;
    color: #166534;
}

.feedback-alert-error {
    border: 1px solid #fecaca;
    background: #fef2f2;
    color: #991b1b;
}

/*
 * Kept outside the visible viewport rather than using display:none so
 * simplistic bots are more likely to interact with it.
 */
.feedback-honeypot {
    position: absolute;
    top: auto;
    left: -10000px;
    width: 1px;
    height: 1px;
    overflow: hidden;
}

.about-button-row {
    margin-top: 18px;
    text-align: center;
}

.about-back-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 7px 10px;
    color: #6b7280;
    font-family: "Poppins", sans-serif;
    font-size: 13px;
    font-weight: 650;
    text-decoration: none;
    transition: color 0.2s ease;
}

.about-back-button:hover {
    color: #111827;
}

.about-back-button:focus-visible {
    border-radius: 8px;
    outline: 3px solid rgba(17, 24, 39, 0.15);
    outline-offset: 2px;
}

/*
|--------------------------------------------------------------------------
| Footer-spacing correction
|--------------------------------------------------------------------------
|
| Remove footer utility margins on this page so the gray area sits directly
| against the footer.
|
*/

.about-page ~ footer,
.about-page ~ .footer-custom,
body > footer,
body > .footer-custom {
    margin-top: 0 !important;
}

@media (max-width: 700px) {
    .about-page {
        padding: 24px 10px 32px;
    }

    .about-card {
        padding: 27px 20px;
        border-radius: 15px;
    }

    .feedback-card {
        padding: 23px 18px;
        border-radius: 15px;
    }

    .about-text {
        font-size: 15px;
        line-height: 1.68;
    }

    .feedback-form-footer {
        align-items: flex-end;
    }
}
</style>

<main class="about-page">
    <div class="about-container">

        <section class="about-card">
            <div class="about-eyebrow">
                Social media back to its roots
            </div>

            <h1 class="about-title">
                About Whusup
            </h1>

            <p class="about-text">
                Whusup is a social media site developed in the San Francisco
                Bay Area beginning in 2026. We are not a corporation, and
                there are no ads anywhere on the site. The content is driven
                entirely by the community. You can create an account, follow
                friends, and share content you find interesting or helpful.
                Our primary rules are simple: no pornography, no violence,
                and no threats. Everything else is free game and we highly promote 
                freedom of speech. Let's let the community decide what is true vs. what isn't.
                To start a topic or group discussion, add a
                tag to your post. Tags are searchable and make it easy to
                find related posts.
            </p>
        </section>

        <section class="feedback-card">
            <h2 class="feedback-heading">
                Share feedback
            </h2>

            <p class="feedback-intro">
                Something is not working well? Tell us about it, and we will
                try to fix or improve it.
            </p>

            <?php if ($feedbackSuccess !== ''): ?>
                <div
                    class="feedback-alert feedback-alert-success"
                    role="status"
                >
                    <?= htmlspecialchars(
                        $feedbackSuccess,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </div>
            <?php endif; ?>

            <?php if ($feedbackError !== ''): ?>
                <div
                    class="feedback-alert feedback-alert-error"
                    role="alert"
                >
                    <?= htmlspecialchars(
                        $feedbackError,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </div>
            <?php endif; ?>

            <form
                method="POST"
                action="/about.php"
                class="feedback-form"
            >
                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= htmlspecialchars(
                        (string) $_SESSION['feedback_csrf_token'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
                >

                <input
                    type="hidden"
                    name="form_id"
                    value="<?= htmlspecialchars(
                        (string) $_SESSION['feedback_form_id'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
                >

                <div
                    class="feedback-honeypot"
                    aria-hidden="true"
                >
                    <label for="feedbackWebsite">
                        Leave this field blank
                    </label>

                    <input
                        type="text"
                        id="feedbackWebsite"
                        name="website"
                        value=""
                        tabindex="-1"
                        autocomplete="off"
                    >
                </div>

                <label
                    for="feedbackText"
                    class="visually-hidden"
                >
                    Feedback
                </label>

                <textarea
                    id="feedbackText"
                    name="feedback_text"
                    class="feedback-textarea"
                    maxlength="500"
                    required
                    aria-describedby="feedbackCharacterCount"
                    placeholder="Your feedback..."
                ><?= htmlspecialchars(
                    $feedback,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?></textarea>

                <div class="feedback-form-footer">
                    <span
                        id="feedbackCharacterCount"
                        class="feedback-character-count"
                        aria-live="polite"
                    >
                        0 / 500
                    </span>

                    <button
                        type="submit"
                        id="feedbackSubmit"
                        class="feedback-submit"
                    >
                        Send feedback
                    </button>
                </div>
            </form>
        </section>

        <div class="about-button-row">
            <a
                href="<?= htmlspecialchars(
                    $returnUrl,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>"
                class="about-back-button"
            >
                ← Back
            </a>
        </div>

    </div>
</main>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const textarea = document.getElementById("feedbackText");
    const characterCount = document.getElementById(
        "feedbackCharacterCount"
    );
    const submitButton = document.getElementById(
        "feedbackSubmit"
    );

    if (!textarea || !characterCount || !submitButton) {
        return;
    }

    function updateCharacterCount() {
        const count = Array.from(textarea.value).length;

        characterCount.textContent = count + " / 500";

        characterCount.classList.toggle(
            "near-limit",
            count >= 450 && count < 500
        );

        characterCount.classList.toggle(
            "at-limit",
            count >= 500
        );

        submitButton.disabled =
            count === 0
            || count > 500;
    }

    textarea.addEventListener(
        "input",
        updateCharacterCount
    );

    updateCharacterCount();
});
</script>

<?php include '../includes/footer.php'; ?>