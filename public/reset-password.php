<?php
session_start();

require_once '../config/database.php';
require_once '../includes/auth_helpers.php';

include '../includes/header.php'; 
include '../includes/navbar.php'; 

$message = '';
$messageType = '';
$token = trim($_GET['token'] ?? $_POST['token'] ?? '');
$tokenRecord = null;
$tokenIsValid = false;

cleanup_expired_reset_tokens($pdo);

if (!empty($token)) {
    $tokenRecord = find_valid_reset_record_by_token($pdo, $token);
    $tokenIsValid = $tokenRecord !== null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (empty($token)) {
        $message = "Missing reset token.";
        $messageType = "danger";
    } elseif (!$tokenIsValid) {
        $message = "This password reset link is invalid or has expired.";
        $messageType = "danger";
    } elseif (empty($password) || empty($confirmPassword)) {
        $message = "Please fill in both password fields.";
        $messageType = "danger";
    } elseif ($password !== $confirmPassword) {
        $message = "Passwords do not match.";
        $messageType = "danger";
    } elseif (strlen($password) < 6) {
        $message = "Password must be at least 6 characters long.";
        $messageType = "danger";
    } else {
        update_user_password($pdo, (int)$tokenRecord['user_id'], $password);
        mark_reset_token_used($pdo, (int)$tokenRecord['id']);

        $_SESSION['success_message'] = "Your password has been reset. Please log in.";
        header("Location: login.php");
        exit();
    }
}
?>

<?php include '../includes/header.php'; ?>
<link rel="stylesheet" href="css/auth.css">

<div class="auth-page">
    <div class="container auth-wrapper d-flex align-items-center justify-content-center">
        <div class="row w-100 justify-content-center">
            <div class="col-12 col-sm-10 col-md-8 col-lg-5 col-xl-4">
                <div class="card auth-card">
                    <div class="card-body">
                        <div class="text-center mb-4">
                            <h2 class="auth-title">Reset Password</h2>
                            <p class="auth-subtitle">Create a new password for your account</p>
                        </div>

                        <?php if (!empty($message)): ?>
                            <div class="alert alert-<?php echo $messageType; ?> auth-alert">
                                <?php echo htmlspecialchars($message); ?>
                            </div>
                        <?php endif; ?>

                        <?php if (!$tokenIsValid): ?>
                            <div class="text-center">
                                <p class="text-muted mb-3">This reset link is invalid, missing, or expired.</p>
                                <a href="forgot-password.php" class="btn btn-primary auth-btn">Request New Link</a>
                            </div>
                        <?php else: ?>
                            <form method="POST" action="">
                                <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">

                                <div class="mb-3">
                                    <div class="form-floating auth-password-wrap">
                                        <input
                                            type="password"
                                            name="password"
                                            id="resetPassword"
                                            class="form-control auth-password-input"
                                            placeholder="New Password"
                                            required
                                        >
                                        <label for="resetPassword">New password</label>
                                        <button
                                            type="button"
                                            class="auth-toggle-btn"
                                            data-toggle-password="resetPassword"
                                            aria-label="Show password"
                                        >
                                            Show
                                        </button>
                                    </div>
                                </div>

                                <div class="mb-4">
                                    <div class="form-floating auth-password-wrap">
                                        <input
                                            type="password"
                                            name="confirm_password"
                                            id="resetConfirmPassword"
                                            class="form-control auth-password-input"
                                            placeholder="Confirm Password"
                                            required
                                        >
                                        <label for="resetConfirmPassword">Confirm new password</label>
                                        <button
                                            type="button"
                                            class="auth-toggle-btn"
                                            data-toggle-password="resetConfirmPassword"
                                            aria-label="Show password"
                                        >
                                            Show
                                        </button>
                                    </div>
                                </div>

                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary auth-btn">
                                        Reset Password
                                    </button>
                                </div>
                            </form>
                        <?php endif; ?>

                        <div class="text-center mt-4">
                            <a href="login.php" class="auth-link">Back to login</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="js/auth.js"></script>
<?php include '../includes/footer.php'; ?>