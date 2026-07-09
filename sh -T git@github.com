config/database.php[36m:[m8[36m:[m$[1;31mpassword[m = $env['DB_PASS'];
config/database.php[36m:[m15[36m:[m        $[1;31mpassword[m,
config/mail.php[36m:[m5[36m:[mfunction send_[1;31mpassword[m_reset_email(string $toEmail, string $resetLink): bool
config/mail.php[36m:[m12[36m:[mWe received a request to reset your [1;31mpassword[m.
config/mail.php[36m:[m14[36m:[mUse the link below to choose a new [1;31mpassword[m:
config/mail.php[36m:[m20[36m:[mIf you did not request a [1;31mpassword[m reset, you can ignore this email.
includes/auth_helpers.php[36m:[m8[36m:[m    $stmt = $pdo->prepare("SELECT id, name, email, [1;31mpassword[m FROM users WHERE email = ? LIMIT 1");
includes/auth_helpers.php[36m:[m18[36m:[m        UPDATE [1;31mpassword[m_resets
includes/auth_helpers.php[36m:[m26[36m:[mfunction create_[1;31mpassword[m_reset_token(PDO $pdo, int $userId): string
includes/auth_helpers.php[36m:[m31[36m:[m    $tokenHash = [1;31mpassword[m_hash($rawToken, PASSWORD_DEFAULT);
includes/auth_helpers.php[36m:[m34[36m:[m        INSERT INTO [1;31mpassword[m_resets (user_id, token_hash, expires_at)
includes/auth_helpers.php[36m:[m42[36m:[mfunction build_[1;31mpassword[m_reset_link(string $rawToken): string
includes/auth_helpers.php[36m:[m44[36m:[m    return APP_URL . '/reset-[1;31mpassword[m.php?token=' . urlencode($rawToken);
includes/auth_helpers.php[36m:[m51[36m:[m        FROM [1;31mpassword[m_resets pr
includes/auth_helpers.php[36m:[m61[36m:[m        if ([1;31mpassword[m_verify($rawToken, $record['token_hash'])) {
includes/auth_helpers.php[36m:[m72[36m:[m        UPDATE [1;31mpassword[m_resets
includes/auth_helpers.php[36m:[m80[36m:[mfunction update_user_[1;31mpassword[m(PDO $pdo, int $userId, string $newPassword): void
includes/auth_helpers.php[36m:[m82[36m:[m    $newHash = [1;31mpassword[m_hash($newPassword, PASSWORD_DEFAULT);
includes/auth_helpers.php[36m:[m86[36m:[m        SET [1;31mpassword[m = ?
includes/auth_helpers.php[36m:[m95[36m:[m        DELETE FROM [1;31mpassword[m_resets
public/css/style.css[36m:[m125[36m:[m.auth-[1;31mpassword[m-input {
public/css/style.css[36m:[m134[36m:[m.auth-[1;31mpassword[m-input:focus {
public/css/style.css[36m:[m139[36m:[m.auth-[1;31mpassword[m-wrap {
public/css/style.css[36m:[m143[36m:[m.auth-[1;31mpassword[m-wrap .auth-[1;31mpassword[m-input {
public/forgot-password.php[36m:[m11[36m:[mfunction send_[1;31mpassword[m_reset_email_ses($ses, $toEmail, $resetLink) {
public/forgot-password.php[36m:[m13[36m:[m    $subject = 'Reset your Whusup [1;31mpassword[m';
public/forgot-password.php[36m:[m15[36m:[m    $plainText = "You requested a [1;31mpassword[m reset for your Whusup account.\n\n"
public/forgot-password.php[36m:[m16[36m:[m        . "Click the link below to reset your [1;31mpassword[m:\n\n"
public/forgot-password.php[36m:[m24[36m:[m                    Reset your [1;31mpassword[m
public/forgot-password.php[36m:[m28[36m:[m                    You requested a [1;31mpassword[m reset for your Whusup account.
public/forgot-password.php[36m:[m88[36m:[m                $rawToken = create_[1;31mpassword[m_reset_token($pdo, $user['id']);
public/forgot-password.php[36m:[m89[36m:[m                $resetLink = build_[1;31mpassword[m_reset_link($rawToken);
public/forgot-password.php[36m:[m91[36m:[m                send_[1;31mpassword[m_reset_email_ses($ses, $user['email'], $resetLink);
public/forgot-password.php[36m:[m98[36m:[m        $message = "If that email exists in our system, a [1;31mpassword[m reset link has been sent.";
public/forgot-password.php[36m:[m225[36m:[m                                Enter your email to reset your [1;31mpassword[m
public/js/auth.js[36m:[m2[36m:[m    const toggleButtons = document.querySelectorAll('[data-toggle-[1;31mpassword[m]');
public/js/auth.js[36m:[m6[36m:[m            const inputId = this.getAttribute('data-toggle-[1;31mpassword[m');
public/js/auth.js[36m:[m11[36m:[m            if (input.type === '[1;31mpassword[m') {
public/js/auth.js[36m:[m14[36m:[m                this.setAttribute('aria-label', 'Hide [1;31mpassword[m');
public/js/auth.js[36m:[m16[36m:[m                input.type = '[1;31mpassword[m';
public/js/auth.js[36m:[m18[36m:[m                this.setAttribute('aria-label', 'Show [1;31mpassword[m');
public/login.php[36m:[m18[36m:[m    $[1;31mpassword[m = $_POST['[1;31mpassword[m'] ?? '';
public/login.php[36m:[m20[36m:[m    if (empty($email) || empty($[1;31mpassword[m)) {
public/login.php[36m:[m34[36m:[m                    [1;31mpassword[m,
public/login.php[36m:[m45[36m:[m            if ($user && [1;31mpassword[m_verify($[1;31mpassword[m, $user['[1;31mpassword[m'])) {
public/login.php[36m:[m66[36m:[m                $message = "Invalid email or [1;31mpassword[m.";
public/login.php[36m:[m131[36m:[m.auth-[1;31mpassword[m-input {
public/login.php[36m:[m137[36m:[m.auth-[1;31mpassword[m-input:focus {
public/login.php[36m:[m142[36m:[m.auth-[1;31mpassword[m-wrap {
public/login.php[36m:[m281[36m:[m                                <div class="form-floating auth-[1;31mpassword[m-wrap">
public/login.php[36m:[m284[36m:[m                                        type="[1;31mpassword[m"
public/login.php[36m:[m285[36m:[m                                        name="[1;31mpassword[m"
public/login.php[36m:[m287[36m:[m                                        class="form-control auth-[1;31mpassword[m-input"
public/login.php[36m:[m299[36m:[m                                        data-toggle-[1;31mpassword[m="loginPassword"
public/login.php[36m:[m300[36m:[m                                        aria-label="Show [1;31mpassword[m"
public/login.php[36m:[m311[36m:[m                                <a href="forgot-[1;31mpassword[m.php" class="auth-link">
public/login.php[36m:[m312[36m:[m                                    Forgot [1;31mpassword[m?
public/reset-password.php[36m:[m24[36m:[m    $[1;31mpassword[m = $_POST['[1;31mpassword[m'] ?? '';
public/reset-password.php[36m:[m25[36m:[m    $confirmPassword = $_POST['confirm_[1;31mpassword[m'] ?? '';
public/reset-password.php[36m:[m31[36m:[m        $message = "This [1;31mpassword[m reset link is invalid or has expired.";
public/reset-password.php[36m:[m33[36m:[m    } elseif (empty($[1;31mpassword[m) || empty($confirmPassword)) {
public/reset-password.php[36m:[m34[36m:[m        $message = "Please fill in both [1;31mpassword[m fields.";
public/reset-password.php[36m:[m36[36m:[m    } elseif ($[1;31mpassword[m !== $confirmPassword) {
public/reset-password.php[36m:[m39[36m:[m    } elseif (strlen($[1;31mpassword[m) < 6) {
public/reset-password.php[36m:[m43[36m:[m        update_user_[1;31mpassword[m($pdo, (int)$tokenRecord['user_id'], $[1;31mpassword[m);
public/reset-password.php[36m:[m46[36m:[m        $_SESSION['success_message'] = "Your [1;31mpassword[m has been reset. Please log in.";
public/reset-password.php[36m:[m64[36m:[m                            <p class="auth-subtitle">Create a new [1;31mpassword[m for your account</p>
public/reset-password.php[36m:[m76[36m:[m                                <a href="forgot-[1;31mpassword[m.php" class="btn btn-primary auth-btn">Request New Link</a>
public/reset-password.php[36m:[m83[36m:[m                                    <div class="form-floating auth-[1;31mpassword[m-wrap">
public/reset-password.php[36m:[m85[36m:[m                                            type="[1;31mpassword[m"
public/reset-password.php[36m:[m86[36m:[m                                            name="[1;31mpassword[m"
public/reset-password.php[36m:[m88[36m:[m                                            class="form-control auth-[1;31mpassword[m-input"
public/reset-password.php[36m:[m92[36m:[m                                        <label for="resetPassword">New [1;31mpassword[m</label>
public/reset-password.php[36m:[m96[36m:[m                                            data-toggle-[1;31mpassword[m="resetPassword"
public/reset-password.php[36m:[m97[36m:[m                                            aria-label="Show [1;31mpassword[m"
public/reset-password.php[36m:[m105[36m:[m                                    <div class="form-floating auth-[1;31mpassword[m-wrap">
public/reset-password.php[36m:[m107[36m:[m                                            type="[1;31mpassword[m"
public/reset-password.php[36m:[m108[36m:[m                                            name="confirm_[1;31mpassword[m"
public/reset-password.php[36m:[m110[36m:[m                                            class="form-control auth-[1;31mpassword[m-input"
public/reset-password.php[36m:[m114[36m:[m                                        <label for="resetConfirmPassword">Confirm new [1;31mpassword[m</label>
public/reset-password.php[36m:[m118[36m:[m                                            data-toggle-[1;31mpassword[m="resetConfirmPassword"
public/reset-password.php[36m:[m119[36m:[m                                            aria-label="Show [1;31mpassword[m"
public/signup.php[36m:[m206[36m:[m    $[1;31mpassword[m = $_POST['[1;31mpassword[m'] ?? '';
public/signup.php[36m:[m207[36m:[m    $confirmPassword = $_POST['confirm_[1;31mpassword[m'] ?? '';
public/signup.php[36m:[m211[36m:[m    if (empty($name) || empty($email) || empty($[1;31mpassword[m) || empty($confirmPassword)) {
public/signup.php[36m:[m241[36m:[m    } elseif ($[1;31mpassword[m !== $confirmPassword) {
public/signup.php[36m:[m247[36m:[m    } elseif (strlen($[1;31mpassword[m) < 6) {
public/signup.php[36m:[m280[36m:[m                $hashedPassword = [1;31mpassword[m_hash($[1;31mpassword[m, PASSWORD_DEFAULT);
public/signup.php[36m:[m289[36m:[m                    INSERT INTO users (name, email, [1;31mpassword[m, email_verified)
public/signup.php[36m:[m410[36m:[m.auth-[1;31mpassword[m-input {
public/signup.php[36m:[m416[36m:[m.auth-[1;31mpassword[m-input:focus {
public/signup.php[36m:[m421[36m:[m.auth-[1;31mpassword[m-wrap {
public/signup.php[36m:[m587[36m:[m                                <div class="form-floating auth-[1;31mpassword[m-wrap">
public/signup.php[36m:[m590[36m:[m                                        type="[1;31mpassword[m"
public/signup.php[36m:[m591[36m:[m                                        name="[1;31mpassword[m"
public/signup.php[36m:[m593[36m:[m                                        class="form-control auth-[1;31mpassword[m-input"
public/signup.php[36m:[m606[36m:[m                                        data-toggle-[1;31mpassword[m="registerPassword"
public/signup.php[36m:[m607[36m:[m                                        aria-label="Show [1;31mpassword[m"
public/signup.php[36m:[m618[36m:[m                                <div class="form-floating auth-[1;31mpassword[m-wrap">
public/signup.php[36m:[m621[36m:[m                                        type="[1;31mpassword[m"
public/signup.php[36m:[m622[36m:[m                                        name="confirm_[1;31mpassword[m"
public/signup.php[36m:[m624[36m:[m                                        class="form-control auth-[1;31mpassword[m-input"
public/signup.php[36m:[m631[36m:[m                                        Confirm [1;31mpassword[m
public/signup.php[36m:[m637[36m:[m                                        data-toggle-[1;31mpassword[m="registerConfirmPassword"
public/signup.php[36m:[m638[36m:[m                                        aria-label="Show [1;31mpassword[m"
public/signup.php[36m:[m648[36m:[m                                Use at least 6 characters for your [1;31mpassword[m. Please enter your first and last name using letters only. You will need to verify your email before logging in.
sql/password_resets.sql[36m:[m1[36m:[mCREATE TABLE IF NOT EXISTS [1;31mpassword[m_resets (
sql/password_resets.sql[36m:[m11[36m:[m    CONSTRAINT fk_[1;31mpassword[m_resets_user
sql/users.sql[36m:[m10[36m:[m    [1;31mpassword[m VARCHAR(255) NOT NULL,
