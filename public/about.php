<?php
session_start();

include '../includes/header.php';
include '../includes/navbar.php';
?>

<style>
html,
body {
    margin: 0;
    padding: 0;
    background: #f2f4f8;
}

.about-page-wrapper {
    width: 100%;
    min-height: 100vh;
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 0 20px;
    margin: 0;
    box-sizing: border-box;
    background: #f2f4f8;
}

.about-card {
    width: 100%;
    max-width: 1100px;
    background: #ffffff;
    border-radius: 18px;
    padding: 50px 60px;
    box-shadow: 0 8px 28px rgba(0,0,0,0.08);
    text-align: center;
}

.about-title {
    font-family: "Poppins", sans-serif;
    font-size: 42px;
    font-weight: 700;
    color: #111827;
    margin-bottom: 25px;
    letter-spacing: -0.8px;
}

.about-text {
    font-family: "Poppins", Arial, sans-serif;
    font-size: 18px;
    line-height: 1.8;
    color: #4b5563;
    max-width: 850px;
    margin: 0 auto;
}

.about-button-row {
    margin-top: 40px;
    text-align: center;
}

.about-back-button {
    display: inline-block;
    background: #ffffff;
    color: #111827;
    border: 1px solid #d1d5db;
    padding: 10px 24px;
    border-radius: 999px;
    font-size: 14px;
    font-weight: 700;
    text-decoration: none;
    transition: 0.2s ease;
}

.about-back-button:hover {
    background: #111827;
    color: #ffffff;
    border-color: #111827;
}

@media (max-width: 768px) {

    .about-page-wrapper {
        align-items: center;
        padding: 0 12px;
    }

    .about-card {
        padding: 32px 22px;
        border-radius: 14px;
    }

    .about-title {
        font-size: 30px;
    }

    .about-text {
        font-size: 16px;
        line-height: 1.7;
    }

}
</style>

<main class="about-page-wrapper">

    <section class="about-card">

        <h1 class="about-title">
            About Whusup
        </h1>

        <div class="about-text">
            Social media has become a dominant force in people's lives over the last 20 years. 
            While connection is good, most current social media platforms are run by giant corporations 
            using complicated algorithms to hook you to the screen, while spamming you with ads and marketing. 
            Whusup does not contain any ads or spam and never will, uses simple but effective filtering, 
            and allows you to connect with friends and people you know.
            Your information here is secure and will never be used for marketing or spam. 
        </div>

        <div class="about-button-row">
            <a 
                href="javascript:history.back()" 
                class="about-back-button"
            >
                Back
            </a>
        </div>

    </section>

</main>

<?php
include '../includes/footer.php';
?>