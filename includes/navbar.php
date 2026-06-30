<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$is_logged_in = isset($_SESSION['user_id']);
?>

<style>
.custom-navbar {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    z-index: 9999;
    background: #ffffff;
    border-bottom: 1px solid #e5e7eb;
    box-shadow: 0 2px 8px rgba(0,0,0,0.06);
    padding: 6px 0;
}

.navbar-inner {
    width: 100%;
    padding: 0 18px;
}

.logo-brand {
    display: inline-flex;
    align-items: flex-end;
    gap: 4px;
    text-decoration: none;
    line-height: 1;
}

.logo-w {
    font-size: 3rem;
    font-weight: 900;
    font-family: "Bangers", "Poppins", sans-serif;
    color: #ffffff;
    -webkit-text-stroke: 2px #dc2626;
    letter-spacing: -2px;
    transform: rotate(-6deg);
    text-shadow:
        0 2px 0 #7f1d1d,
        0 4px 10px rgba(220, 38, 38, 0.35);
    transition: transform 0.2s ease;
}

.logo-social {
    font-size: 1.4rem;
    font-weight: 700;
    font-family: "Poppins", sans-serif;
    color: #111111;
    letter-spacing: -0.5px;
    margin-bottom: 6px;
}

.logo-brand:hover .logo-w {
    transform: rotate(-6deg) scale(1.05);
}

.navbar-search {
    width: 100%;
}

.navbar-search .form-control {
    width: 100%;
    border: 1px solid #d1d5db;
    font-size: 0.9rem;
}

.small-placeholder::placeholder {
    font-size: 0.85rem;
    opacity: 0.7;
}

.navbar-auth {
    flex-wrap: nowrap;
}

.navbar-mobile-actions {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-left: auto;
}

.nav-notification {
    position: relative;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 39px;
    height: 39px;
    border-radius: 999px;
    color: #111827;
    background: #f9fafb;
    border: 1px solid #e5e7eb;
    text-decoration: none;
    transition: 0.2s ease;
    flex-shrink: 0;
}

.nav-notification:hover {
    background: #e5e7eb;
    color: #111827;
    transform: translateY(-1px);
}

.nav-bell {
    width: 20px;
    height: 20px;
    display: inline-block;
}

.nav-bell svg {
    width: 20px;
    height: 20px;
    display: block;
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
    min-width: 19px;
    height: 19px;
    padding: 0 5px;
    border-radius: 999px;
    background: #dc2626;
    color: #ffffff;
    border: 2px solid #ffffff;
    font-size: 11px;
    font-weight: 800;
    line-height: 15px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    box-sizing: border-box;
}

.nav-notification-count.hidden {
    display: none;
}

.nav-modern-btn {
    display: inline-block;
    border-radius: 999px;
    padding: 7px 20px;
    font-size: 13px;
    font-weight: 700;
    text-decoration: none;
    transition: 0.2s ease;
    white-space: nowrap;
    border: 1px solid transparent;
}

.nav-btn-light {
    background: #f3f4f6;
    color: #374151;
    border-color: #d1d5db;
}

.nav-btn-light:hover {
    background: #e5e7eb;
    color: #111827;
}

.nav-btn-primary {
    background: #111827;
    color: #ffffff;
    border-color: #111827;
}

.nav-btn-primary:hover {
    background: #374151;
    color: #ffffff;
}

.nav-btn-danger {
    background: #850101;
    color: #ffffff;
    border-color: #850101;
}

.nav-btn-danger:hover {
    background: #6b0000;
    color: #ffffff;
}

.navbar-toggler {
    border: none;
    padding: 6px 8px;
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

@media (min-width: 992px) {
    .navbar-search {
        flex: 1;
        max-width: 600px;
        margin-left: 2rem;
        margin-right: 2rem;
    }
}

@media (max-width: 991px) {
    .navbar-inner {
        display: flex;
        align-items: center;
    }

    .logo-w {
        font-size: 2.4rem;
    }

    .logo-social {
        font-size: 1.15rem;
        margin-bottom: 4px;
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
        width: 100%;
        height: 100vh;
        background: #ffffff;
        z-index: 10000;
        padding: 90px 22px 30px;
        transition: right 0.28s ease;
        display: block !important;
        overflow-y: auto;
    }

    .navbar-collapse.show {
        right: 0;
    }

    .navbar-collapse::before {
        content: "Menu";
        position: absolute;
        top: 24px;
        left: 22px;
        font-family: "Poppins", sans-serif;
        font-size: 22px;
        font-weight: 700;
        color: #111827;
    }

    .mobile-menu-close {
        display: block;
        position: absolute;
        top: 18px;
        right: 24px;
        border: none;
        background: transparent;
        font-size: 34px;
        line-height: 1;
        color: #111827;
        cursor: pointer;
        padding: 0;
        z-index: 10001;
    }

    .navbar-search {
        max-width: none !important;
        width: 100% !important;
        margin: 0 0 22px !important;
    }

    .navbar-search .form-control {
        padding: 12px 16px;
        font-size: 15px;
    }

    .navbar-auth {
        width: 100%;
        display: flex !important;
        flex-direction: column !important;
        gap: 12px !important;
        margin-top: 0 !important;
    }

    .nav-modern-btn {
        width: 100%;
        text-align: center;
        padding: 12px 18px;
        font-size: 15px;
    }
}
</style>

<nav class="navbar navbar-expand-lg navbar-light custom-navbar">
    <div class="container-fluid navbar-inner">

        <a href="/" class="logo-brand">
            <span class="logo-w">W</span>
            <span class="logo-social">social</span>
        </a>

        <div class="navbar-mobile-actions">

            <?php if ($is_logged_in): ?>
                <a href="/notifications.php" class="nav-notification mobile-notification" title="Notifications" aria-label="Notifications">
                    <span class="nav-bell">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"></path>
                            <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                        </svg>
                    </span>

                    <span id="mobileNotificationCount" class="nav-notification-count hidden">
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

        <div class="collapse navbar-collapse" id="navbarSupportedContent">

            <button
                type="button"
                class="mobile-menu-close"
                data-bs-toggle="collapse"
                data-bs-target="#navbarSupportedContent"
                aria-label="Close navigation"
            >
                ×
            </button>

            <form class="navbar-search mx-lg-auto my-3 my-lg-0" role="search" method="GET" action="/dashboard.php">
                <input type="hidden" name="page" value="social_feed">
                <input type="hidden" name="sort" value="<?= htmlspecialchars($_GET['sort'] ?? 'recent') ?>">
                <input type="hidden" name="filter" value="<?= htmlspecialchars($_GET['filter'] ?? 'all') ?>">
            
                <input
                    class="form-control small-placeholder text-center rounded-pill"
                    type="search"
                    name="tag"
                    value="<?= htmlspecialchars($_GET['tag'] ?? '') ?>"
                    placeholder="Search Tags"
                    aria-label="Search tags"
                >
            </form>

            <div class="navbar-auth ms-lg-auto d-flex flex-row gap-2 justify-content-center mt-3 mt-lg-0">

                <?php if ($is_logged_in): ?>

                    <a href="/notifications.php" class="nav-notification desktop-notification" title="Notifications" aria-label="Notifications">
                        <span class="nav-bell">
                            <svg viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"></path>
                                <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                            </svg>
                        </span>

                        <span id="desktopNotificationCount" class="nav-notification-count hidden">
                            0
                        </span>
                    </a>

                    <a href="/dashboard.php" class="nav-modern-btn nav-btn-light">
                        Dashboard
                    </a>

                    <a href="/my_account.php" class="nav-modern-btn nav-btn-primary">
                        My Account
                    </a>

                    <a href="/logout.php" class="nav-modern-btn nav-btn-danger">
                        Log Out
                    </a>

                <?php else: ?>

                    <a href="/login.php" class="nav-modern-btn nav-btn-light">
                        Log In
                    </a>

                    <a href="/signup.php" class="nav-modern-btn nav-btn-primary">
                        Sign Up
                    </a>

                <?php endif; ?>

            </div>

        </div>
    </div>
</nav>

<?php if ($is_logged_in): ?>
<script>
document.addEventListener("DOMContentLoaded", function () {
    const desktopBadge = document.getElementById("desktopNotificationCount");
    const mobileBadge = document.getElementById("mobileNotificationCount");

    let lastNotificationFetch = 0;
    const minimumFetchGap = 30000;

    function updateNotificationBadges(count) {
        const safeCount = Number.isFinite(count) ? count : 0;
        const displayCount = safeCount > 99 ? "99+" : String(safeCount);

        [desktopBadge, mobileBadge].forEach(function (badge) {
            if (!badge) {
                return;
            }

            if (safeCount <= 0) {
                badge.classList.add("hidden");
                badge.textContent = "0";
            } else {
                badge.textContent = displayCount;
                badge.classList.remove("hidden");
            }
        });
    }

    function loadNotificationCount(force) {
        const now = Date.now();

        if (!force && now - lastNotificationFetch < minimumFetchGap) {
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
                throw new Error("Notification count request failed.");
            }

            return response.json();
        })
        .then(function (data) {
            if (data && data.success) {
                updateNotificationBadges(parseInt(data.count, 10) || 0);
            }
        })
        .catch(function (error) {
            console.error("Notification count error:", error);
        });
    }

    loadNotificationCount(true);

    window.addEventListener("focus", function () {
        loadNotificationCount(false);
    });

    document.addEventListener("visibilitychange", function () {
        if (!document.hidden) {
            loadNotificationCount(false);
        }
    });
});
</script>
<?php endif; ?>
