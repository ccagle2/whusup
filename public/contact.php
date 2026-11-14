
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pageTitle = 'Contact | Whusup';
$metaDescription = 'Contact Whusup about account concerns, technical problems, inappropriate content, or other issues requiring attention.';

include '../includes/header.php';
include '../includes/navbar.php';
?>

<style>
/* =========================================================
   Contact Page
   Clean, minimal layout consistent with Whusup
   ========================================================= */

.whusup-contact-page {
    width: 100%;
    max-width: 950px;
    margin: 0 auto;
    padding: 38px 24px 65px;
    box-sizing: border-box;
    background: transparent;
}

.whusup-contact-title {
    font-family: "Poppins", sans-serif;
    font-size: clamp(30px, 4vw, 40px);
    font-weight: 700;
    color: #111827;
    margin: 0 0 24px;
    letter-spacing: -0.6px;
}

.whusup-contact-text {
    font-family: "Poppins", Arial, sans-serif;
    font-size: 16px;
    line-height: 1.8;
    color: #4b5563;
    margin: 0 0 16px;
    max-width: 850px;
}

.whusup-contact-actions {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
    margin-top: 28px;
}

.whusup-contact-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 10px 24px;
    border: 1px solid #d1d5db;
    border-radius: 999px;
    background: transparent;
    color: #111827;
    font-family: "Poppins", Arial, sans-serif;
    font-size: 14px;
    font-weight: 700;
    text-decoration: none;
    transition:
        background 0.2s ease,
        color 0.2s ease,
        border-color 0.2s ease;
}

.whusup-contact-button:hover {
    background: #111827;
    color: #ffffff;
    border-color: #111827;
}

.whusup-contact-primary {
    background: #111827;
    color: #ffffff;
    border-color: #111827;
}

.whusup-contact-primary:hover {
    background: #374151;
    border-color: #374151;
}

@media (max-width: 700px) {
    .whusup-contact-page {
        padding: 26px 20px 45px;
    }

    .whusup-contact-text {
        font-size: 15px;
        line-height: 1.75;
    }

    .whusup-contact-actions {
        gap: 10px;
    }

    .whusup-contact-button {
        padding: 10px 20px;
    }
}
</style>

<main class="whusup-contact-page">

    <h1 class="whusup-contact-title">
        Contact Whusup
    </h1>

    <p class="whusup-contact-text">
        Have a question, concern, or issue with Whusup?
        We're here to help. Whether you're experiencing
        a technical problem, need assistance with your
        account, or want to report inappropriate content
        or a security concern, please feel free to
        reach out.
    </p>

    <p class="whusup-contact-text">
        We review messages and will do our best to
        respond to matters that require attention.
    </p>

    <div class="whusup-contact-actions">

        <a
            href="mailto:caglenation@gmail.com?subject=Whusup%20Inquiry"
            class="whusup-contact-button whusup-contact-primary"
        >
            Send Email
        </a>

        <a
            href="/index.php"
            class="whusup-contact-button"
        >
            Back to Home
        </a>

    </div>

</main>

<?php
include '../includes/footer.php';
?>
