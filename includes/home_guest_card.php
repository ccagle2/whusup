<?php

// Hide the guest card when the user is logged in.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!empty($_SESSION['user_id'])) {
    return;
}
?>

<style>
.guest-hero-card {
    width: min(940px, calc(100% - 2rem));
    margin: 1.2rem auto 0.5rem;
    padding: 1.4rem 2.5rem;
    border-radius: 24px;
    background: linear-gradient(135deg, #111827, #1f2937);
    color: #ffffff;
    box-shadow: 0 18px 38px rgba(0, 0, 0, 0.17);
    box-sizing: border-box;
}

.guest-hero-main {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 2.5rem;
}

.guest-hero-copy {
    flex: 1 1 auto;
    min-width: 0;
}

.guest-hero-card h1 {
    margin: 0 0 0.45rem;
    font-size: clamp(1.9rem, 3vw, 2.6rem);
    font-weight: 800;
    line-height: 1.1;
}

.guest-hero-card p {
    max-width: 760px;
    margin: 0;
    color: #d1d5db;
    font-size: clamp(1rem, 1.5vw, 1.15rem);
    line-height: 1.5;
}

.guest-hero-actions {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 0.9rem;
    flex: 0 0 auto;
}

.guest-hero-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 140px;
    padding: 0.78rem 1.5rem;
    border-radius: 999px;
    font-weight: 700;
    text-decoration: none;
    transition:
        transform 0.2s ease,
        opacity 0.2s ease;
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

.guest-hero-share {
    margin-top: 0.8rem;
    text-align: right;
}

.guest-hero-share-link {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    color: rgba(255, 255, 255, 0.72);
    font-size: 0.95rem;
    font-weight: 600;
    text-decoration: none;
    transition: color 0.2s ease;
}

.guest-hero-share-link:hover {
    color: #ffffff;
}

@media (max-width: 760px) {
    .guest-hero-card {
        width: calc(100% - 2rem);
        padding: 1.5rem 1.4rem;
        text-align: center;
    }

    .guest-hero-main {
        display: block;
    }

    .guest-hero-card h1 {
        margin-bottom: 0.7rem;
    }

    .guest-hero-card p {
        margin: 0 auto 1.2rem;
    }

    .guest-hero-actions {
        justify-content: center;
        flex-wrap: wrap;
        gap: 0.75rem;
    }

    .guest-hero-share {
        margin-top: 1rem;
        text-align: center;
    }
}

@media (max-width: 576px) {
    .guest-hero-card {
        width: 100vw;
        max-width: none;

        /*
         * The site currently reserves about 66px globally for
         * the fixed navbar. The mobile navbar is shorter.
         * Pull only this card upward into that excess space.
         */
        margin-top: -18px;
        margin-bottom: 1rem;
        margin-left: calc(50% - 50vw);
        margin-right: calc(50% - 50vw);

        padding: 1.5rem;

        border-radius: 0;
        box-shadow: none;
    }

    .guest-hero-card h1 {
        margin-bottom: 0.7rem;
    }

    .guest-hero-card p {
        margin-bottom: 1.2rem;
        line-height: 1.5;
    }

    .guest-hero-actions {
        flex-direction: row;
        flex-wrap: nowrap;
        gap: 0.75rem;
    }

    .guest-hero-btn {
        flex: 1 1 0;
        min-width: 0;
        padding: 0.78rem 0.7rem;
    }

    .guest-hero-share {
        margin-top: 1rem;
    }
}

@media (max-width: 340px) {
    .guest-hero-actions {
        flex-direction: column;
    }

    .guest-hero-btn {
        width: 100%;
    }
}
</style>

<section class="guest-hero-card">
    <div class="guest-hero-main">
        <div class="guest-hero-copy">
            <h1>What's up?</h1>

            <p>
                Talk about anything.<br>
                Ad-free Social Media | No Corporate Oversight
            </p>
        </div>

        <div class="guest-hero-actions">
            <a href="/login.php" class="guest-hero-btn login">
                Log In
            </a>

            <a href="/signup.php" class="guest-hero-btn signup">
                Sign Up
            </a>
        </div>
    </div>

    <div class="guest-hero-share">
        <a
            href="sms:?&body=Check%20out%20Whusup%3A%20https%3A%2F%2Fwhusup.com"
            class="guest-hero-share-link"
        >
            Share Whusup →
        </a>
    </div>
</section>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const shareLink = document.querySelector(
        ".guest-hero-share-link"
    );

    if (!shareLink) {
        return;
    }

    const message =
        "I've been using Whusup and thought you might like it.\n" +
        "https://whusup.com";

    const separator =
        /iPhone|iPad|iPod/i.test(navigator.userAgent)
            ? "&"
            : "?";

    shareLink.href =
        "sms:" +
        separator +
        "body=" +
        encodeURIComponent(message);
});
</script>
