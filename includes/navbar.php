<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$is_logged_in = !empty($_SESSION['user_id']);
$is_search_page = basename($_SERVER['PHP_SELF'] ?? '') === 'search.php';

$logo_href = $is_logged_in
    ? '/dashboard.php'
    : '/';
?>

<style>
.custom-navbar {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    z-index: 9999;

    padding: 6px 0;

    background: #ffffff;
    border-bottom: 1px solid #e5e7eb;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
}

.navbar-inner {
    width: 100%;
    padding: 0 18px;
}

/* =========================================================
   Logo
   ========================================================= */

.logo-brand {
    display: inline-flex;
    align-items: flex-end;
    gap: 4px;

    line-height: 1;
    text-decoration: none;
}

.logo-w {
    display: inline-block;

    color: #ffffff;

    font-family: "Bangers", cursive;
    font-size: 3rem;
    font-weight: 400;
    font-style: normal;
    line-height: 0.9;
    letter-spacing: 0;

    -webkit-text-stroke: 1.7px #dc2626;
    paint-order: stroke fill;

    transform: rotate(-6deg);
    transform-origin: center;

    text-shadow:
        1px 2px 0 #7f1d1d,
        0 0 8px rgba(220, 38, 38, 0.35),
        0 5px 12px rgba(0, 0, 0, 0.18);

    -webkit-font-smoothing: antialiased;
    -moz-osx-font-smoothing: grayscale;

    transition: transform 0.2s ease;
}

.logo-social {
    margin-bottom: 6px;

    color: #111111;

    font-family: "Poppins", sans-serif;
    font-size: 1.4rem;
    font-weight: 700;
    letter-spacing: -0.5px;
}

.logo-brand:hover .logo-w {
    transform: rotate(-6deg) scale(1.05);
}

/* =========================================================
   Search
   ========================================================= */

.navbar-search {
    width: 100%;
}

.navbar-search .form-control {
    width: 100%;
    min-height: 42px;
    padding: 10px 18px;

    border: 1px solid #d1d5db;

    font-size: 0.95rem;
    line-height: 1.4;
}

.navbar-search .form-control::placeholder {
    font-size: 0.95rem;
    opacity: 0.7;
}

/* =========================================================
   Action area
   ========================================================= */

.navbar-auth {
    align-items: center;
    flex-wrap: nowrap;
}

.navbar-mobile-actions {
    display: flex;
    align-items: center;
    gap: 8px;

    margin-left: auto;
}

/* =========================================================
   Notifications
   ========================================================= */

.nav-notification {
    position: relative;

    display: inline-flex;
    align-items: center;
    justify-content: center;

    width: 39px;
    height: 39px;
    flex-shrink: 0;

    border: 1px solid #e5e7eb;
    border-radius: 999px;

    background: #f9fafb;
    color: #111827;

    text-decoration: none;

    transition:
        background-color 0.2s ease,
        color 0.2s ease,
        transform 0.2s ease;
}

.nav-notification:hover {
    background: #e5e7eb;
    color: #111827;
    transform: translateY(-1px);
}

.nav-bell {
    display: inline-block;
    width: 20px;
    height: 20px;
}

.nav-bell svg {
    display: block;
    width: 20px;
    height: 20px;
}

.nav-bell svg path {
    fill: none;
    stroke: #111827;
    stroke-width: 2;
    stroke-linecap: round;
    stroke-linejoin: round;
}

.nav-notification-count {
    position: absolute;
    top: -6px;
    right: -6px;

    display: inline-flex;
    align-items: center;
    justify-content: center;

    min-width: 19px;
    height: 19px;
    padding: 0 5px;

    border: 2px solid #ffffff;
    border-radius: 999px;

    background: #dc2626;
    color: #ffffff;

    font-size: 11px;
    font-weight: 800;
    line-height: 15px;

    box-sizing: border-box;
}

.nav-notification-count.hidden {
    display: none;
}

/* =========================================================
   Navbar buttons
   =========================================================
   Typography intentionally matches dashboard-action-button:
   no custom font-family and no forced line-height.
   ========================================================= */

.nav-modern-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;

    flex: 0 0 auto;

    min-height: 35px;
    padding: 7px 20px;

    border: 1px solid transparent;
    border-radius: 999px;

    font-size: 13px;
    font-weight: 700;

    text-align: center;
    text-decoration: none;
    white-space: nowrap;

    box-sizing: border-box;

    transition:
        background-color 0.2s ease,
        border-color 0.2s ease,
        color 0.2s ease,
        transform 0.2s ease;
}

.nav-modern-btn:hover {
    transform: translateY(-1px);
}

.nav-btn-light {
    background: #f3f4f6;
    color: #4b5563;
    border-color: #d1d5db;
}

.nav-btn-light:hover {
    background: #e5e7eb;
    color: #111827;
    border-color: #cbd5e1;
}

.nav-btn-primary {
    background: #111827;
    color: #ffffff;
    border-color: #111827;
}

.nav-btn-primary:hover {
    background: #374151;
    color: #ffffff;
    border-color: #374151;
}

.nav-btn-danger {
    background: #850101;
    color: #ffffff;
    border-color: #850101;
}

.nav-btn-danger:hover {
    background: #6b0000;
    color: #ffffff;
    border-color: #6b0000;
}

/* =========================================================
   About link
   ========================================================= */

.nav-about-link {
    display: inline-flex;
    align-items: center;
    justify-content: center;

    margin-left: 10px;
    padding: 7px 6px;

    color: #6b7280;

    font-size: 13px;
    font-weight: 600;

    text-decoration: none;
    white-space: nowrap;

    transition: color 0.2s ease;
}

.nav-about-link:hover {
    color: #111827;
}

/* =========================================================
   Mobile controls
   ========================================================= */

.navbar-toggler {
    padding: 6px 8px;

    border: none;
    box-shadow: none !important;
}

.navbar-toggler:focus {
    box-shadow: none;
}

.navbar-toggler-icon {
    width: 1.2rem;
    height: 1.2rem;
}

.mobile-menu-close {
    display: none;
}

.desktop-notification {
    display: inline-flex;
}

.mobile-notification {
    display: none;
}

/* =========================================================
   Desktop
   ========================================================= */

@media (min-width: 992px) {
    .navbar-search {
        flex: 1;
        max-width: 600px;

        margin-left: 2rem;
        margin-right: 2rem;
    }
}

/* =========================================================
   Mobile / tablet drawer
   ========================================================= */

@media (max-width: 991px) {
    .navbar-inner {
        display: flex;
        align-items: center;
    }

    .logo-w {
        font-size: 2.4rem;
        -webkit-text-stroke-width: 1.25px;
    }

    .logo-social {
        margin-bottom: 4px;
        font-size: 1.15rem;
    }

    .desktop-notification {
        display: none;
    }

    .mobile-notification {
        display: inline-flex;
    }

    .navbar-collapse {
        position: fixed;
        top: 0;
        right: -100%;

        display: block !important;

        width: 100%;
        height: 100vh;
        padding: 90px 22px 30px;

        background: #ffffff;

        overflow-y: auto;
        z-index: 10000;

        transition: right 0.28s ease;
    }

    .navbar-collapse.show {
        right: 0;
    }

    .navbar-collapse::before {
        content: "Menu";

        position: absolute;
        top: 24px;
        left: 22px;

        color: #111827;

        font-family: "Poppins", sans-serif;
        font-size: 22px;
        font-weight: 700;
    }

    .mobile-menu-close {
        position: absolute;
        top: 18px;
        right: 24px;

        display: block;

        padding: 0;

        border: none;
        background: transparent;
        color: #111827;

        font-size: 34px;
        line-height: 1;

        cursor: pointer;
        z-index: 10001;
    }

    .navbar-search {
        width: 100% !important;
        max-width: none !important;
        margin: 0 0 22px !important;
    }

    .navbar-search .form-control {
        min-height: 50px;
        padding: 14px 20px;

        font-size: 17px;
        line-height: 1.4;

        border-radius: 999px;
    }

    .navbar-search .form-control::placeholder {
        font-size: 17px;
        opacity: 0.72;
    }

    .navbar-auth {
        display: flex !important;
        flex-direction: column !important;
        align-items: stretch;

        width: 100%;
        gap: 12px !important;
        margin-top: 0 !important;
    }

    .nav-modern-btn {
        width: 100%;
        min-height: 45px;
        padding: 12px 18px;

        font-size: 15px;
        text-align: center;
    }

    .nav-about-link {
        width: 100%;
        min-height: 40px;
        margin-left: 0;
        padding: 9px 12px;

        font-size: 15px;
        text-align: center;
    }
}
</style>

<nav class="navbar navbar-expand-lg navbar-light custom-navbar">
    <div class="container-fluid navbar-inner">

        <a
            href="<?= htmlspecialchars($logo_href) ?>"
            class="logo-brand"
            aria-label="Whusup home"
        >
            <span class="logo-w">W</span>
            <span class="logo-social">social</span>
        </a>

        <div class="navbar-mobile-actions">

            <?php if ($is_logged_in): ?>

                <a
                    href="/notifications.php"
                    class="nav-notification mobile-notification"
                    title="Notifications"
                    aria-label="Notifications"
                >
                    <span class="nav-bell">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"></path>
                            <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                        </svg>
                    </span>

                    <span
                        id="mobileNotificationCount"
                        class="nav-notification-count hidden"
                    >
                        0
                    </span>
                </a>

            <?php endif; ?>

            <button
                class="navbar-toggler"
                type="button"
                data-bs-toggle="collapse"
                data-bs-target="#navbarSupportedContent"
                aria-controls="navbarSupportedContent"
                aria-expanded="false"
                aria-label="Toggle navigation"
            >
                <span class="navbar-toggler-icon"></span>
            </button>

        </div>

        <div
            class="collapse navbar-collapse"
            id="navbarSupportedContent"
        >

            <button
                type="button"
                class="mobile-menu-close"
                data-bs-toggle="collapse"
                data-bs-target="#navbarSupportedContent"
                aria-label="Close navigation"
            >
                ×
            </button>

            <?php if (!$is_search_page): ?>

                <form
                    class="navbar-search mx-lg-auto my-3 my-lg-0"
                    role="search"
                    method="GET"
                    action="/search.php"
                >
                <input
                    class="form-control text-center rounded-pill"
                    type="search"
                    name="q"
                    value=""
                    placeholder="Find Anything"
                    aria-label="Search topics, people and posts"
                    minlength="3"
                    maxlength="100"
                    autocomplete="off"
                    enterkeyhint="search"
                    required
                >
                </form>

            <?php endif; ?>

            <div
                class="navbar-auth ms-lg-auto d-flex flex-row gap-2 justify-content-center mt-3 mt-lg-0"
            >

                <?php if ($is_logged_in): ?>

                    <a
                        href="/notifications.php"
                        class="nav-notification desktop-notification"
                        title="Notifications"
                        aria-label="Notifications"
                    >
                        <span class="nav-bell">
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"></path>
                                <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                            </svg>
                        </span>

                        <span
                            id="desktopNotificationCount"
                            class="nav-notification-count hidden"
                        >
                            0
                        </span>
                    </a>

                    <a
                        href="/dashboard.php"
                        class="nav-modern-btn nav-btn-light"
                    >
                        Dashboard
                    </a>

                    <a
                        href="/my_account.php"
                        class="nav-modern-btn nav-btn-primary"
                    >
                        My Account
                    </a>

                    <a
                        href="/logout.php"
                        class="nav-modern-btn nav-btn-danger"
                    >
                        Log Out
                    </a>

                <?php else: ?>

                    <a
                        href="/login.php"
                        class="nav-modern-btn nav-btn-light"
                    >
                        Log In
                    </a>

                    <a
                        href="/signup.php"
                        class="nav-modern-btn nav-btn-primary"
                    >
                        Sign Up
                    </a>

                <?php endif; ?>

                <a
                    href="/about.php"
                    class="nav-about-link"
                >
                    About
                </a>

            </div>

        </div>
    </div>
</nav>

<?php if ($is_logged_in): ?>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const desktopBadge = document.getElementById(
        "desktopNotificationCount"
    );

    const mobileBadge = document.getElementById(
        "mobileNotificationCount"
    );

    let lastNotificationFetch = 0;
    const minimumFetchGap = 30000;

    function updateNotificationBadges(count) {
        const safeCount = Number.isFinite(count)
            ? count
            : 0;

        const displayCount = safeCount > 99
            ? "99+"
            : String(safeCount);

        [desktopBadge, mobileBadge].forEach(function (badge) {
            if (!badge) {
                return;
            }

            if (safeCount <= 0) {
                badge.classList.add("hidden");
                badge.textContent = "0";
                return;
            }

            badge.textContent = displayCount;
            badge.classList.remove("hidden");
        });
    }

    function loadNotificationCount(force) {
        const now = Date.now();

        if (
            !force &&
            now - lastNotificationFetch < minimumFetchGap
        ) {
            return;
        }

        lastNotificationFetch = now;

        fetch("/ajax/notification_count.php", {
            method: "GET",
            headers: {
                "Accept": "application/json"
            },
            cache: "no-store"
        })
        .then(function (response) {
            if (!response.ok) {
                throw new Error(
                    "Notification count request failed."
                );
            }

            return response.json();
        })
        .then(function (data) {
            if (data && data.success) {
                updateNotificationBadges(
                    parseInt(data.count, 10) || 0
                );
            }
        })
        .catch(function (error) {
            console.error(
                "Notification count error:",
                error
            );
        });
    }

    loadNotificationCount(true);

    window.addEventListener("focus", function () {
        loadNotificationCount(false);
    });

    document.addEventListener(
        "visibilitychange",
        function () {
            if (!document.hidden) {
                loadNotificationCount(false);
            }
        }
    );
});
</script>

<?php endif; ?>