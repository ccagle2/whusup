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

.contact-page-wrapper {
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

.contact-card {
    width: 100%;
    max-width: 1100px;
    background: #ffffff;
    border-radius: 18px;
    padding: 50px 60px;
    box-shadow: 0 8px 28px rgba(0,0,0,0.08);
    text-align: center;
}

.contact-title {
    font-family: "Poppins", sans-serif;
    font-size: 42px;
    font-weight: 700;
    color: #111827;
    margin-bottom: 25px;
    letter-spacing: -0.8px;
}

.contact-text {
    font-family: "Poppins", Arial, sans-serif;
    font-size: 18px;
    line-height: 1.8;
    color: #4b5563;
    max-width: 850px;
    margin: 0 auto;
}

.contact-button-row {
    margin-top: 40px;
    display: flex;
    justify-content: center;
    gap: 12px;
    flex-wrap: wrap;
}

.contact-button {
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

.contact-button:hover {
    background: #111827;
    color: #ffffff;
    border-color: #111827;
}

.contact-primary-button {
    background: #111827;
    color: #ffffff;
    border-color: #111827;
}

.contact-primary-button:hover {
    background: #374151;
    border-color: #374151;
}

@media (max-width: 768px) {

    .contact-page-wrapper {
        align-items: center;
        padding: 0 12px;
    }

    .contact-card {
        padding: 32px 22px;
        border-radius: 14px;
    }

    .contact-title {
        font-size: 30px;
    }

    .contact-text {
        font-size: 16px;
        line-height: 1.7;
    }

    .contact-button-row {
        width: 100%;
        flex-wrap: nowrap;
    }

    .contact-button {
        flex: 1;
        text-align: center;
        padding: 10px 12px;
    }

}
</style>

<main class="contact-page-wrapper">

    <section class="contact-card">

        <h1 class="contact-title">
            Contact Whusup
        </h1>

        <div class="contact-text">
            Questions, feedback, partnership inquiries, or general comments are always welcome. 
            Feel free to reach out directly and someone from Whusup will get back to you.
        </div>

        <div class="contact-button-row">

            <a 
                href="mailto:caglenation@gmail.com?subject=Whusup%20Inquiry"
                class="contact-button contact-primary-button"
            >
                Contact
            </a>

            <a 
                href="javascript:history.back()" 
                class="contact-button"
            >
                Back
            </a>

        </div>

    </section>

</main>

<?php
include '../includes/footer.php';
?>