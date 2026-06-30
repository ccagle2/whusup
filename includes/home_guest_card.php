<?php
// Hide this card if the user is logged in
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!empty($_SESSION['user_id'])) {
    return;
}
?>

<style>
.guest-hero-card {
    max-width: 950px;
    margin: 2rem auto;
    padding: 2rem;
    border-radius: 24px;
    background: linear-gradient(135deg, #111827, #1f2937);
    color: #fff;
    box-shadow: 0 20px 45px rgba(0, 0, 0, 0.18);
    text-align: center;
}

.guest-hero-card h1 {
    font-size: clamp(1.8rem, 5vw, 3rem);
    font-weight: 800;
    margin-bottom: 1rem;
    line-height: 1.15;
}

.guest-hero-card p {
    font-size: clamp(1rem, 3vw, 1.2rem);
    color: #d1d5db;
    max-width: 650px;
    margin: 0 auto 1.75rem;
    line-height: 1.6;
}

.guest-hero-actions {
    display: flex;
    justify-content: center;
    gap: 1rem;
    flex-wrap: wrap;
}

.guest-hero-btn {
    display: inline-block;
    padding: 0.85rem 1.6rem;
    border-radius: 999px;
    font-weight: 700;
    text-decoration: none;
    transition: all 0.2s ease;
    min-width: 140px;
}

.guest-hero-btn.login {
    background: #ffffff;
    color: #111827;
}

.guest-hero-btn.signup {
    background: #dc2626;
    color: #ffffff;
}

.guest-hero-btn:hover {
    transform: translateY(-2px);
    opacity: 0.92;
}

@media (max-width: 576px) {
    .guest-hero-card {
        margin: 1rem;
        padding: 1.5rem;
        border-radius: 20px;
    }

    .guest-hero-actions {
        flex-direction: column;
        gap: 0.75rem;
    }

    .guest-hero-btn {
        width: 100%;
    }
}
</style>

<section class="guest-hero-card">
    <h1>Welcome to Whusup</h1>

    <p>
        No Ads. No spam. No profit driven algorithms.<br>
        Just you and your friends connecting.<br>
        Join us as we grow the platform.
    </p>

    <div class="guest-hero-actions">
        <a href="login.php" class="guest-hero-btn login">Log In</a>
        <a href="signup.php" class="guest-hero-btn signup">Sign Up</a>
    </div>
</section>