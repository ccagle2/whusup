<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$is_logged_in = !empty($_SESSION['user_id']);

$returnUrl = $is_logged_in
    ? '/dashboard.php'
    : '/';

$pageTitle = 'Thank You | Whusup';

$pageDescription =
    'Thank you for supporting Whusup.';

$canonicalUrl =
    'https://whusup.com/support-thanks.php';

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

<meta name="robots" content="noindex,follow">

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

.support-thanks-page {
    width: 100%;
    margin: 0;
    padding: 38px 16px 48px;

    background: #ffffff;

    box-sizing: border-box;
}

.support-thanks-container {
    width: 100%;
    max-width: 720px;
    margin: 0 auto;
}

.support-thanks-card {
    width: 100%;
    padding: 50px 46px;

    border: 1px solid #e5e7eb;
    border-radius: 20px;

    background: #ffffff;

    box-shadow:
        0 10px 30px rgba(17, 24, 39, 0.07);

    text-align: center;
    box-sizing: border-box;
}

.support-thanks-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;

    width: 58px;
    height: 58px;
    margin-bottom: 20px;

    border-radius: 999px;

    background: #f0fdf4;
    color: #166534;

    font-family: "Poppins", sans-serif;
    font-size: 28px;
    font-weight: 800;
}

.support-thanks-title {
    margin: 0 0 12px;

    color: #111827;

    font-family: "Poppins", sans-serif;
    font-size: clamp(30px, 5vw, 42px);
    font-weight: 800;
    letter-spacing: -0.8px;
}

.support-thanks-text {
    max-width: 570px;
    margin: 0 auto;

    color: #4b5563;

    font-family: "Poppins", sans-serif;
    font-size: 16px;
    line-height: 1.75;
}

.support-thanks-actions {
    margin-top: 28px;
}

.support-thanks-home {
    display: inline-flex;
    align-items: center;
    justify-content: center;

    min-height: 44px;
    padding: 10px 24px;

    border: 1px solid #111827;
    border-radius: 999px;

    background: #111827;
    color: #ffffff;

    font-family: "Poppins", sans-serif;
    font-size: 14px;
    font-weight: 700;

    text-decoration: none;

    transition:
        background-color 0.2s ease,
        border-color 0.2s ease,
        transform 0.2s ease;
}

.support-thanks-home:hover {
    background: #374151;
    border-color: #374151;
    color: #ffffff;

    transform: translateY(-1px);
}

.support-thanks-page ~ footer,
.support-thanks-page ~ .footer-custom,
body > footer,
body > .footer-custom {
    margin-top: 0 !important;
}

@media (max-width: 700px) {
    .support-thanks-page {
        padding: 24px 10px 34px;
    }

    .support-thanks-card {
        padding: 38px 20px;
        border-radius: 16px;
    }

    .support-thanks-text {
        font-size: 15px;
    }

    .support-thanks-home {
        width: 100%;
    }
}
</style>

<main class="support-thanks-page">
    <div class="support-thanks-container">

        <section class="support-thanks-card">

            <div
                class="support-thanks-icon"
                aria-hidden="true"
            >
                ✓
            </div>

            <h1 class="support-thanks-title">
                Thank you for supporting Whusup.
            </h1>

            <p class="support-thanks-text">
                Your support helps with the operation and continued
                development of Whusup. We appreciate you being part
                of the community and helping us continue building
                the platform.
            </p>

            <div class="support-thanks-actions">
                <a
                    href="<?= htmlspecialchars(
                        $returnUrl,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
                    class="support-thanks-home"
                >
                    <?= $is_logged_in
                        ? 'Return to Whusup'
                        : 'Return Home' ?>
                </a>
            </div>

        </section>

    </div>
</main>

<?php include '../includes/footer.php'; ?>