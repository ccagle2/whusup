<?php
session_start();

require_once '../config/database.php';

$message = '';
$messageType = '';

if (isset($_SESSION['success_message'])) {
    $message = $_SESSION['success_message'];
    $messageType = 'success';
    unset($_SESSION['success_message']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $email = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {

        $message = "Please fill in all fields.";
        $messageType = "danger";

    } else {

        try {

            $stmt = $pdo->prepare("
                SELECT 
                    id, 
                    name, 
                    email, 
                    password,
                    email_verified
                FROM users
                WHERE email = ?
                LIMIT 1
            ");

            $stmt->execute([$email]);

            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password'])) {

                if ((int)($user['email_verified'] ?? 0) !== 1) {

                    $message = "Please verify your email before logging in. Check your inbox for the verification link.";
                    $messageType = "warning";

                } else {

                    session_regenerate_id(true);

                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_name'] = $user['name'];
                    $_SESSION['user_email'] = $user['email'];

                    header("Location: dashboard.php");
                    exit();
                }

            } else {

                $message = "Invalid email or password.";
                $messageType = "danger";
            }

        } catch (PDOException $e) {

            $message = "Login failed. Please try again.";
            $messageType = "danger";
        }
    }
}

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

.auth-page {
    width: 100%;
    min-height: 100vh;
    background: #f2f4f8;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0;
    margin: 0;
}

.auth-wrapper {
    width: 100%;
}

.auth-card {
    border: none;
    border-radius: 18px;
    box-shadow: 0 8px 28px rgba(0,0,0,0.08);
    padding: 10px;
}

.auth-title {
    font-family: "Poppins", sans-serif;
    font-size: 32px;
    font-weight: 700;
    color: #111827;
    margin-bottom: 6px;
}

.auth-subtitle {
    color: #6b7280;
    font-size: 15px;
}

.auth-alert {
    border-radius: 10px;
    font-size: 14px;
}

.auth-input,
.auth-password-input {
    border-radius: 12px !important;
    border: 1px solid #d1d5db;
}

.auth-input:focus,
.auth-password-input:focus {
    border-color: #9ca3af;
    box-shadow: none;
}

.auth-password-wrap {
    position: relative;
}

.auth-toggle-btn {
    position: absolute;
    top: 50%;
    right: 14px;
    transform: translateY(-50%);
    border: none;
    background: transparent;
    color: #6b7280;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
    z-index: 5;
}

.auth-btn {
    background: #111827;
    border-color: #111827;
    border-radius: 999px;
    padding: 11px;
    font-weight: 700;
    transition: 0.2s ease;
}

.auth-btn:hover {
    background: #374151;
    border-color: #374151;
}

.auth-link {
    color: #111827;
    text-decoration: none;
    font-weight: 600;
}

.auth-link:hover {
    text-decoration: underline;
}

.auth-divider {
    text-align: center;
    margin: 22px 0;
    color: #9ca3af;
    font-size: 14px;
    position: relative;
}

.auth-divider::before,
.auth-divider::after {
    content: "";
    position: absolute;
    top: 50%;
    width: 42%;
    height: 1px;
    background: #e5e7eb;
}

.auth-divider::before {
    left: 0;
}

.auth-divider::after {
    right: 0;
}

@media (max-width: 768px) {

    .auth-page {
        padding: 0 12px;
    }

    .auth-card {
        border-radius: 14px;
    }

    .auth-title {
        font-size: 28px;
    }

}
</style>

<div class="auth-page">

    <div class="container-fluid auth-wrapper d-flex align-items-center justify-content-center px-3">

        <div class="row w-100 justify-content-center">

            <div class="col-12 col-sm-10 col-md-8 col-lg-5 col-xl-4">

                <div class="card auth-card">

                    <div class="card-body">

                        <div class="text-center mb-4">

                            <h2 class="auth-title">
                                Welcome Back
                            </h2>

                            <p class="auth-subtitle">
                                Sign in to your account
                            </p>

                        </div>

                        <?php if (!empty($message)): ?>

                            <div class="alert alert-<?php echo htmlspecialchars($messageType); ?> auth-alert">
                                <?php echo htmlspecialchars($message); ?>
                            </div>

                        <?php endif; ?>

                        <form method="POST" action="">

                            <div class="form-floating mb-3">

                                <input
                                    type="email"
                                    name="email"
                                    id="loginEmail"
                                    class="form-control auth-input"
                                    placeholder="name@example.com"
                                    value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                                    required
                                >

                                <label for="loginEmail">
                                    Email address
                                </label>

                            </div>

                            <div class="mb-3 auth-floating-group">

                                <div class="form-floating auth-password-wrap">

                                    <input
                                        type="password"
                                        name="password"
                                        id="loginPassword"
                                        class="form-control auth-password-input"
                                        placeholder="Password"
                                        required
                                    >

                                    <label for="loginPassword">
                                        Password
                                    </label>

                                    <button
                                        type="button"
                                        class="auth-toggle-btn"
                                        data-toggle-password="loginPassword"
                                        aria-label="Show password"
                                    >
                                        Show
                                    </button>

                                </div>

                            </div>

                            <div class="d-flex justify-content-end mb-4">

                                <a href="forgot-password.php" class="auth-link">
                                    Forgot password?
                                </a>

                            </div>

                            <div class="d-grid">

                                <button type="submit" class="btn btn-primary auth-btn">
                                    Login
                                </button>

                            </div>

                        </form>

                        <div class="auth-divider">
                            or
                        </div>

                        <div class="text-center">

                            <span class="text-muted">
                                Don’t have an account?
                            </span>

                            <a href="signup.php" class="auth-link ms-1">
                                Sign up
                            </a>

                        </div>

                    </div>

                </div>

            </div>

        </div>

    </div>

</div>

<script src="js/auth.js"></script>

<?php include '../includes/footer.php'; ?>
