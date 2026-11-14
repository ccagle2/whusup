<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$is_logged_in = !empty($_SESSION['user_id']);

$returnUrl = $is_logged_in
    ? '/dashboard.php'
    : '/';

$stripePaymentLink =
    'https://buy.stripe.com/9B6aEWgY33gn7Yk69y48000';

$pageTitle = 'Support Whusup';

$pageDescription =
    'Support the continued development and operation of Whusup.';

$canonicalUrl = 'https://whusup.com/support.php';

include '../includes/header.php';
?>

<?php include '../includes/navbar.php'; ?>

<style>
html,
body {
    margin-bottom: 0;
}

.support-page {
    width: 100%;
    margin: 0;

    padding: 42px 16px 48px;

    background: #ffffff;

    box-sizing: border-box;
}

.support-container {
    width: 100%;
    max-width: 820px;

    margin: 0 auto;
}

/* =========================================================
   Introduction
   ========================================================= */

.support-intro {
    width: 100%;

    margin: 0;
    padding: 0;

    text-align: center;
}

.support-title {
    margin: 0 0 14px;

    color: #111827;

    font-family: "Poppins", sans-serif;
    font-size: clamp(32px, 5vw, 44px);
    font-weight: 800;

    letter-spacing: -0.9px;
}

.support-lead {
    max-width: 700px;

    margin: 0 auto;

    color: #4b5563;

    font-family: "Poppins", sans-serif;
    font-size: 17px;
    line-height: 1.7;
}

/* Allows multiple paragraphs if you add them later. */

.support-lead p {
    margin: 0 0 18px;
}

.support-lead p:last-child {
    margin-bottom: 0;
}

/* =========================================================
   Support action
   ========================================================= */

.support-action {
    margin-top: 30px;

    text-align: center;
}

.support-stripe-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;

    min-height: 48px;

    padding: 12px 28px;

    border: 1px solid #850101;
    border-radius: 999px;

    background: #850101;
    color: #ffffff;

    font-family: "Poppins", sans-serif;
    font-size: 15px;
    font-weight: 600;

    text-decoration: none;

    box-shadow:
        0 7px 18px rgba(133, 1, 1, 0.15);

    transition:
        background-color 0.2s ease,
        border-color 0.2s ease,
        transform 0.2s ease,
        box-shadow 0.2s ease;
}

.support-stripe-button:hover {
    background: #6b0000;
    border-color: #6b0000;

    color: #ffffff;

    transform: translateY(-1px);

    box-shadow:
        0 9px 22px rgba(107, 0, 0, 0.18);
}

.support-stripe-button:focus-visible {
    outline: 3px solid rgba(133, 1, 1, 0.18);
    outline-offset: 3px;
}

/* =========================================================
   Payment information
   ========================================================= */

.support-payment-note {
    max-width: 560px;

    margin: 14px auto 0;

    color: #6b7280;

    font-family: "Poppins", sans-serif;
    font-size: 12.5px;
    line-height: 1.6;
}

.support-disclaimer {
    max-width: 700px;

    margin: 28px auto 0;
    padding-top: 22px;

    border-top: 1px solid #e5e7eb;

    color: #6b7280;

    font-family: "Poppins", sans-serif;
    font-size: 12.5px;
    line-height: 1.65;

    text-align: center;
}

/* =========================================================
   Back link
   ========================================================= */

.support-button-row {
    margin-top: 20px;

    text-align: center;
}

.support-back-button {
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

.support-back-button:hover {
    color: #111827;
}

.support-back-button:focus-visible {
    border-radius: 8px;

    outline: 3px solid rgba(17, 24, 39, 0.15);
    outline-offset: 2px;
}

.support-page ~ footer,
.support-page ~ .footer-custom,
body > footer,
body > .footer-custom {
    margin-top: 0 !important;
}

/* =========================================================
   Mobile
   ========================================================= */

@media (max-width: 700px) {
    .support-page {
        padding: 30px 14px 34px;
    }

    .support-lead {
        font-size: 15.5px;
        line-height: 1.68;
    }

    .support-action {
        margin-top: 26px;
    }

    .support-stripe-button {
        width: 100%;
        max-width: 360px;
    }

    .support-disclaimer {
        margin-top: 24px;
        padding-top: 20px;
    }
}
</style>

<main class="support-page">
    <div class="support-container">

        <section class="support-intro">

            <h1 class="support-title">
                Support Whusup
            </h1>

            <div class="support-lead">

                <p>
                    Like what you see? Consider supporting us. 
                </p>
                <p>
                    We do not run ads or paid for content and are 100% supported by our user base.
                    All support goes toward running the site and feature enhancement.
                </p>

            </div>

        </section>

        <div class="support-action">

            <a
                href="<?= htmlspecialchars(
                    $stripePaymentLink,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>"
                class="support-stripe-button"
                rel="noopener noreferrer"
            >
                Support Whusup
            </a>

            <p class="support-payment-note">
                Payments are securely processed by Stripe.
                Whusup does not collect or store your payment
                card information.
            </p>

        </div>

        <div class="support-disclaimer">
            Contributions are voluntary and help support the
            operation and continued development of Whusup.
            Contributions do not provide ownership, equity,
            premium features, or special account privileges.
            Contributions are not represented as charitable
            donations or tax-deductible contributions.
        </div>

        <div class="support-button-row">

            <a
                href="<?= htmlspecialchars(
                    $returnUrl,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>"
                class="support-back-button"
            >
                ← Back
            </a>

        </div>

    </div>
</main>

<?php include '../includes/footer.php'; ?>