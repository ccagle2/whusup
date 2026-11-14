
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pageTitle = 'Privacy Policy | Whusup';
$metaDescription = 'Learn how Whusup handles public content, protects private account information, and respects user privacy.';

include '../includes/header.php';
include '../includes/navbar.php';
?>

<style>
.whusup-privacy-page {
    width: 100%;
    max-width: 950px;
    margin: 0 auto;
    padding: 38px 24px 65px;
    box-sizing: border-box;
    background: transparent;
}

.whusup-privacy-page h1 {
    font-family: "Poppins", sans-serif;
    font-size: clamp(30px, 4vw, 40px);
    font-weight: 700;
    color: #111827;
    margin: 0 0 24px;
    letter-spacing: -0.6px;
}

.whusup-privacy-page h2 {
    font-family: "Poppins", sans-serif;
    font-size: 20px;
    font-weight: 700;
    color: #111827;
    margin: 30px 0 10px;
}

.whusup-privacy-page p {
    font-family: "Poppins", Arial, sans-serif;
    font-size: 16px;
    line-height: 1.8;
    color: #4b5563;
    margin: 0 0 16px;
}

.whusup-privacy-page a {
    color: #b91c1c;
}

.whusup-privacy-back {
    display: inline-block;
    margin-top: 20px;
    padding: 10px 24px;
    border: 1px solid #d1d5db;
    border-radius: 999px;
    color: #111827 !important;
    background: transparent;
    font-size: 14px;
    font-weight: 700;
    text-decoration: none;
    transition: background 0.2s ease, color 0.2s ease;
}

.whusup-privacy-back:hover {
    background: #111827;
    color: #ffffff !important;
}

@media (max-width: 700px) {
    .whusup-privacy-page {
        padding: 26px 20px 45px;
    }

    .whusup-privacy-page h2 {
        font-size: 18px;
    }

    .whusup-privacy-page p {
        font-size: 15px;
        line-height: 1.75;
    }
}
</style>

<main class="whusup-privacy-page">

    <h1>Privacy Policy</h1>

    <p>
        Whusup is a public social media platform built around
        sharing ideas, photos, experiences, and conversations.
        We believe in an open internet where people can discover
        interesting content without sacrificing the privacy
        of their personal account information.
    </p>

    <h2>Public Content</h2>

    <p>
        Whusup is designed to be publicly accessible. Posts,
        photos, comments, and certain profile information may
        be viewed by anyone, including people who do not have
        a Whusup account. Public content may also be discovered
        and indexed by search engines such as Google.
    </p>

    <p>
        Please keep this in mind when deciding what information
        to share. Information you voluntarily include in a
        public post, comment, or profile may be visible to others.
    </p>

    <h2>Private Account Information</h2>

    <p>
        Information provided for account registration and
        management, such as your email address and password,
        is not displayed publicly as part of your Whusup profile.
        We use account information to operate the platform,
        authenticate users, maintain account security, and
        communicate essential account-related information.
    </p>

    <h2>Security of Your Information</h2>

    <p>
        We take reasonable measures to protect personal
        information from unauthorized access, disclosure,
        or misuse. These measures include security controls
        designed to protect account credentials and restrict
        access to private information.
    </p>

    <p>
        No internet-based service can guarantee absolute
        security, but protecting user information is an
        important part of how we operate Whusup.
    </p>

    <h2>No Sale of Personal Information or Advertising</h2>

    <p>
        Whusup does not sell personal information, share
        personal information with advertisers for targeted
        advertising, or use account information to send
        promotional marketing messages.
    </p>

    <p>
        Our focus is on providing a social platform for
        real people and genuine conversations, not on
        monetizing personal information.
    </p>

    <h2>Service Providers</h2>

    <p>
        Whusup uses technology and infrastructure providers
        to operate and secure the platform. These services
        may process limited information as necessary for
        functions such as hosting, storing uploaded images,
        delivering account emails, and protecting the
        platform from misuse.
    </p>

    <h2>Questions About Privacy</h2>

    <p>
        If you have questions about this policy or how
        Whusup handles personal information, please contact
        us through our
        <a href="/about.php">About page</a>.
    </p>

    <a href="/index.php" class="whusup-privacy-back">
        Back to Home
    </a>

</main>

<?php
include '../includes/footer.php';
?>
