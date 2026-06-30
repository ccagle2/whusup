<?php

require_once '../includes/auth.php';
require_login();

require_once '../config/database.php';
require_once '../includes/s3_upload.php';

$awsConfig = require __DIR__ . '/../config/aws.php';
$s3 = $awsConfig['s3'];
$rekognition = $awsConfig['rekognition'] ?? null;
$s3Bucket = $awsConfig['bucket'];

include '../includes/header.php';
include '../includes/navbar.php';

$user_id = $_SESSION['user_id'];
$message = "";
$message_type = "";

function redirect_to_dashboard() {
    if (!headers_sent()) {
        header("Location: /dashboard.php");
        exit;
    }

    echo "<script>window.location.href = '/dashboard.php';</script>";
    exit;
}

function redirect_to_home() {
    if (!headers_sent()) {
        header("Location: /index.php");
        exit;
    }

    echo "<script>window.location.href = '/index.php';</script>";
    exit;
}

function getProfileImageUrl($imageKey) {
    global $awsConfig;

    if (empty($imageKey)) {
        return null;
    }

    return rtrim($awsConfig['cloudfront_url'], '/') . '/' . ltrim($imageKey, '/');
}

function fix_image_orientation($image, $filePath) {

    if (!function_exists('exif_read_data')) {
        return $image;
    }

    $exif = @exif_read_data($filePath);

    if (empty($exif['Orientation'])) {
        return $image;
    }

    switch ($exif['Orientation']) {

        case 3:
            return imagerotate($image, 180, 0);

        case 6:
            return imagerotate($image, -90, 0);

        case 8:
            return imagerotate($image, 90, 0);

        default:
            return $image;
    }
}

function compress_profile_image(array $file): array {

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'Profile image upload failed.'];
    }

    $maxOriginalSize = 10 * 1024 * 1024;

    if ($file['size'] > $maxOriginalSize) {
        return ['success' => false, 'error' => 'Profile image must be under 10MB.'];
    }

    $mimeType = mime_content_type($file['tmp_name']);

    $allowedTypes = [
        'image/jpeg',
        'image/png',
        'image/webp'
    ];

    if (!in_array($mimeType, $allowedTypes, true)) {
        return ['success' => false, 'error' => 'Only JPG, PNG, and WEBP images are allowed.'];
    }

    switch ($mimeType) {

        case 'image/jpeg':
            $sourceImage = imagecreatefromjpeg($file['tmp_name']);

            if ($sourceImage) {
                $sourceImage = fix_image_orientation($sourceImage, $file['tmp_name']);
            }

            break;

        case 'image/png':
            $sourceImage = imagecreatefrompng($file['tmp_name']);
            break;

        case 'image/webp':
            $sourceImage = imagecreatefromwebp($file['tmp_name']);
            break;

        default:
            return ['success' => false, 'error' => 'Unsupported image type.'];
    }

    if (!$sourceImage) {
        return ['success' => false, 'error' => 'Could not process image.'];
    }

    $width = imagesx($sourceImage);
    $height = imagesy($sourceImage);

    $squareSize = min($width, $height);

    $srcX = (int)(($width - $squareSize) / 2);
    $srcY = (int)(($height - $squareSize) / 2);

    $avatarSize = 600;

    $newImage = imagecreatetruecolor($avatarSize, $avatarSize);

    imagecopyresampled(
        $newImage,
        $sourceImage,
        0,
        0,
        $srcX,
        $srcY,
        $avatarSize,
        $avatarSize,
        $squareSize,
        $squareSize
    );

    $tempPath = tempnam(sys_get_temp_dir(), 'whusup_avatar_') . '.jpg';

    imagejpeg($newImage, $tempPath, 82);

    imagedestroy($sourceImage);
    imagedestroy($newImage);

    return [
        'success' => true,
        'tmp_name' => $tempPath,
        'name' => bin2hex(random_bytes(16)) . '.jpg',
        'type' => 'image/jpeg',
        'size' => filesize($tempPath),
        'error' => UPLOAD_ERR_OK
    ];
}


function imagePassesModeration($rekognition, $bucket, $imageKey): bool {

    if (!$rekognition) {
        throw new Exception('Image moderation is not configured.');
    }

    $result = $rekognition->detectModerationLabels([
        'Image' => [
            'S3Object' => [
                'Bucket' => $bucket,
                'Name' => $imageKey
            ]
        ],
        'MinConfidence' => 80
    ]);

    $blockedLabels = [
        'Explicit Nudity',
        'Nudity',
        'Sexual Activity',
        'Graphic Male Nudity',
        'Graphic Female Nudity',
        'Sexual Situations',
        'Violence',
        'Graphic Violence Or Gore',
        'Visually Disturbing',
        'Weapons',
        'Drugs',
        'Tobacco',
        'Alcohol',
        'Hate Symbols'
    ];

    foreach ($result['ModerationLabels'] as $label) {
        $name = $label['Name'] ?? '';
        $parent = $label['ParentName'] ?? '';

        if (
            in_array($name, $blockedLabels, true) ||
            in_array($parent, $blockedLabels, true)
        ) {
            return false;
        }
    }

    return true;
}

// Ensure profile row exists
$stmt = $pdo->prepare("
    INSERT IGNORE INTO user_profiles (user_id, display_name)
    VALUES (:user_id, :display_name)
");

$stmt->execute([
    ':user_id' => $user_id,
    ':display_name' => $_SESSION['user_name'] ?? null
]);

// Delete account
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_account'])) {

    try {

        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            SELECT profile_picture_url
            FROM user_profiles
            WHERE user_id = :user_id
            LIMIT 1
        ");

        $stmt->execute([
            ':user_id' => $user_id
        ]);

        $profileData = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!empty($profileData['profile_picture_url'])) {

            try {
                $s3->deleteObject([
                    'Bucket' => $s3Bucket,
                    'Key' => $profileData['profile_picture_url']
                ]);
            } catch (Exception $e) {
            }
        }

        $stmt = $pdo->prepare("
            DELETE FROM user_profiles
            WHERE user_id = :user_id
        ");

        $stmt->execute([
            ':user_id' => $user_id
        ]);

        $stmt = $pdo->prepare("
            DELETE FROM users
            WHERE id = :user_id
        ");

        $stmt->execute([
            ':user_id' => $user_id
        ]);

        $pdo->commit();

        session_unset();
        session_destroy();

        redirect_to_home();

    } catch (PDOException $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        $message = "Account deletion failed: " . $e->getMessage();
        $message_type = "danger";
    }
}

// Update profile
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['delete_account'])) {

    $phone = trim($_POST['phone'] ?? '');
    $phone_digits = preg_replace('/\D/', '', $phone);

    if ($phone !== '' && strlen($phone_digits) !== 10) {

        $message = "Please enter a valid 10-digit phone number.";
        $message_type = "danger";

    } else {

        $phone_for_db = $phone_digits !== ''
            ? '(' . substr($phone_digits, 0, 3) . ') ' . substr($phone_digits, 3, 3) . '-' . substr($phone_digits, 6)
            : '';

        try {

            $profileImageKey = null;

            $stmt = $pdo->prepare("
                SELECT profile_picture_url
                FROM user_profiles
                WHERE user_id = :user_id
                LIMIT 1
            ");

            $stmt->execute([
                ':user_id' => $user_id
            ]);

            $existingProfile = $stmt->fetch(PDO::FETCH_ASSOC);

            $profileImageKey = $existingProfile['profile_picture_url'] ?? null;

            if (!empty($_FILES['profile_picture']['name'])) {

                $compressedImage = compress_profile_image($_FILES['profile_picture']);

                if (!$compressedImage['success']) {

                    $message = $compressedImage['error'];
                    $message_type = "danger";

                } else {

                    $uploadResult = uploadToS3($compressedImage, 'profile_pictures');

                    if (file_exists($compressedImage['tmp_name'])) {
                        unlink($compressedImage['tmp_name']);
                    }

                    if (!$uploadResult['success']) {

                        $message = $uploadResult['error'];
                        $message_type = "danger";

                    } else {

                        $newProfileImageKey = $uploadResult['key'];

                        try {

                            if (!imagePassesModeration($rekognition, $s3Bucket, $newProfileImageKey)) {

                                $s3->deleteObject([
                                    'Bucket' => $s3Bucket,
                                    'Key' => $newProfileImageKey
                                ]);

                                $message = "This profile picture cannot be uploaded because it may violate community guidelines.";
                                $message_type = "danger";

                            } else {

                                if (!empty($profileImageKey)) {

                                    try {
                                        $s3->deleteObject([
                                            'Bucket' => $s3Bucket,
                                            'Key' => $profileImageKey
                                        ]);
                                    } catch (Exception $e) {
                                        error_log('Old profile image delete failed: ' . $e->getMessage());
                                    }
                                }

                                $profileImageKey = $newProfileImageKey;
                            }

                        } catch (Exception $e) {

                            try {
                                $s3->deleteObject([
                                    'Bucket' => $s3Bucket,
                                    'Key' => $newProfileImageKey
                                ]);
                            } catch (Exception $deleteException) {
                                error_log('Rejected profile image cleanup failed: ' . $deleteException->getMessage());
                            }

                            $message = "Image moderation failed. Please try again.";
                            $message_type = "danger";
                            error_log('Rekognition profile image moderation failed: ' . $e->getMessage());
                        }
                    }
                }
            }

            if ($message === "") {

                $stmt = $pdo->prepare("
                    UPDATE user_profiles
                    SET
                        first_name = :first_name,
                        last_name = :last_name,
                        display_name = :display_name,
                        bio = :bio,
                        website_url = :website_url,
                        phone = :phone,
                        birthday = :birthday,
                        job_title = :job_title,
                        company = :company,
                        school = :school,
                        city = :city,
                        location = :location,
                        profile_picture_url = :profile_picture_url,
                        is_private = :is_private,
                        allow_email_notifications = :allow_email_notifications,
                        allow_profile_search = :allow_profile_search
                    WHERE user_id = :user_id
                ");

                $stmt->execute([
                    ':first_name' => trim($_POST['first_name'] ?? ''),
                    ':last_name' => trim($_POST['last_name'] ?? ''),
                    ':display_name' => trim($_POST['display_name'] ?? ''),
                    ':bio' => trim($_POST['bio'] ?? ''),
                    ':website_url' => trim($_POST['website_url'] ?? ''),
                    ':phone' => $phone_for_db,
                    ':birthday' => !empty($_POST['birthday']) ? $_POST['birthday'] : null,
                    ':job_title' => trim($_POST['job_title'] ?? ''),
                    ':company' => trim($_POST['company'] ?? ''),
                    ':school' => trim($_POST['school'] ?? ''),
                    ':city' => trim($_POST['city'] ?? ''),
                    ':location' => trim($_POST['location'] ?? ''),
                    ':profile_picture_url' => $profileImageKey,
                    ':is_private' => isset($_POST['is_private']) ? 1 : 0,
                    ':allow_email_notifications' => isset($_POST['allow_email_notifications']) ? 1 : 0,
                    ':allow_profile_search' => isset($_POST['allow_profile_search']) ? 1 : 0,
                    ':user_id' => $user_id
                ]);

                $message = "Profile updated successfully.";
                $message_type = "success";
            }

        } catch (PDOException $e) {

            $message = "Profile update failed: " . $e->getMessage();
            $message_type = "danger";
        }
    }
}

// Fetch profile
$stmt = $pdo->prepare("
    SELECT *
    FROM user_profiles
    WHERE user_id = :user_id
    LIMIT 1
");

$stmt->execute([
    ':user_id' => $user_id
]);

$profile = $stmt->fetch(PDO::FETCH_ASSOC);

$profileImageUrl = !empty($profile['profile_picture_url'])
    ? getProfileImageUrl($profile['profile_picture_url'])
    : null;

?>

<style>
.account-page {
    width: 100%;
    min-height: 100vh;
    background: #f2f4f8;
    padding: 30px 20px 50px;
    box-sizing: border-box;
}

.account-card {
    width: 100%;
    max-width: 900px;
    margin: 0 auto;
    background: #ffffff;
    border-radius: 18px;
    padding: 34px;
    box-shadow: 0 8px 28px rgba(0,0,0,0.08);
}

.account-title {
    text-align: center;
    font-family: "Poppins", sans-serif;
    font-size: 30px;
    font-weight: 700;
    color: #111827;
    margin-bottom: 8px;
}

.account-subtitle {
    text-align: center;
    color: #6b7280;
    margin-bottom: 28px;
}

.account-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}

.account-field {
    display: flex;
    flex-direction: column;
}

.account-field.full {
    grid-column: 1 / -1;
}

.account-field label {
    font-size: 13px;
    font-weight: 700;
    color: #4b5563;
    margin-bottom: 6px;
}

.account-field input,
.account-field textarea {
    width: 100%;
    border: 1px solid #d1d5db;
    border-radius: 12px;
    padding: 11px 13px;
    font-size: 14px;
    box-sizing: border-box;
}

.account-field textarea {
    min-height: 120px;
    resize: vertical;
}

.account-field input:focus,
.account-field textarea:focus {
    border-color: #9ca3af;
    outline: none;
}

.coming-soon-box {
    grid-column: 1 / -1;
    background: #f3f4f6;
    border: 1px dashed #cbd5e1;
    border-radius: 14px;
    padding: 18px;
    color: #6b7280;
    text-align: center;
    font-weight: 700;
}

.account-options {
    grid-column: 1 / -1;
    display: grid;
    gap: 10px;
    margin-top: 8px;
}

.account-checkbox {
    display: flex;
    align-items: center;
    gap: 10px;
    color: #4b5563;
    font-weight: 600;
    font-size: 14px;
}

.account-actions {
    display: flex;
    justify-content: center;
    gap: 10px;
    margin-top: 28px;
}

.account-button {
    border: none;
    border-radius: 999px;
    padding: 10px 26px;
    font-size: 14px;
    font-weight: 700;
    cursor: pointer;
    text-decoration: none;
}

.save-button {
    background: #111827;
    color: #ffffff;
}

.back-button {
    background: #ffffff;
    color: #374151;
    border: 1px solid #d1d5db;
}

.account-message {
    text-align: center;
    padding: 10px;
    border-radius: 10px;
    margin-bottom: 18px;
    font-weight: 600;
}

.account-message.success {
    background: #dcfce7;
    color: #166534;
}

.account-message.danger {
    background: #fee2e2;
    color: #991b1b;
}

.delete-account-section {
    margin-top: 30px;
    padding-top: 24px;
    border-top: 1px solid #e5e7eb;
    text-align: center;
}

.delete-account-title {
    font-size: 16px;
    font-weight: 700;
    color: #991b1b;
    margin-bottom: 6px;
}

.delete-account-text {
    font-size: 13px;
    color: #6b7280;
    margin-bottom: 14px;
}

.delete-button {
    background: #dc2626;
    color: #ffffff;
}

.delete-button:hover {
    background: #991b1b;
}

@media (max-width: 700px) {
    .account-page {
        padding: 14px 10px 35px;
    }

    .account-card {
        padding: 22px;
        border-radius: 14px;
    }

    .account-grid {
        grid-template-columns: 1fr;
    }

    .account-actions {
        flex-direction: row;
    }

    .account-button {
        flex: 1;
        text-align: center;
        padding: 10px 12px;
    }
}

.profile-picture-section {
    display: flex;
    align-items: center;
    gap: 18px;
    flex-wrap: wrap;
}

.profile-picture-preview,
.profile-picture-placeholder {
    width: 92px;
    height: 92px;
    border-radius: 50%;
    object-fit: cover;
    object-position: center;
    border: 3px solid #e5e7eb;
    background: #f3f4f6;
    flex-shrink: 0;
}

.profile-picture-placeholder {
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 34px;
    font-weight: 700;
    color: #6b7280;
}

.profile-picture-upload-area {
    flex: 1;
    min-width: 240px;
}

.profile-picture-input {
    position: absolute;
    left: -9999px;
    width: 1px;
    height: 1px;
    opacity: 0;
}

.profile-picture-preview-hidden {
    display: none;
}

.profile-picture-upload-box {
    display: flex;
    flex-direction: column;
    justify-content: center;
    min-height: 72px;
    padding: 14px 16px;
    border: 1px dashed #9ca3af;
    border-radius: 14px;
    background: #f9fafb;
    cursor: pointer;
    transition: border-color 0.2s ease, background 0.2s ease, box-shadow 0.2s ease;
}

.profile-picture-upload-box:hover {
    border-color: #374151;
    background: #ffffff;
    box-shadow: 0 0 0 4px rgba(17,24,39,0.06);
}

.profile-picture-upload-title {
    color: #111827;
    font-size: 14px;
    font-weight: 800;
}

.profile-picture-upload-subtitle {
    color: #6b7280;
    font-size: 12px;
    font-weight: 600;
    margin-top: 3px;
}

.profile-picture-preview-actions {
    display: none;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
    margin-top: 10px;
}

.profile-picture-preview-actions.show {
    display: flex;
}

.profile-picture-small-button {
    border: 1px solid #d1d5db;
    background: #ffffff;
    color: #374151;
    border-radius: 999px;
    padding: 7px 14px;
    font-size: 12px;
    font-weight: 800;
    cursor: pointer;
}

.profile-picture-small-button:hover {
    background: #f3f4f6;
    color: #111827;
}

.profile-picture-helper {
    margin-top: 6px;
    font-size: 12px;
    color: #6b7280;
}

@media (max-width: 700px) {

    .profile-picture-preview,
    .profile-picture-placeholder {
        width: 74px;
        height: 74px;
    }

    .profile-picture-section {
        align-items: flex-start;
    }
}
</style>

<main class="account-page">

    <section class="account-card">

        <h1 class="account-title">My Account</h1>

        <div class="account-subtitle">
            Edit your profile details.
        </div>

        <?php if (!empty($message)): ?>
            <div class="account-message <?= htmlspecialchars($message_type) ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data">

            <div class="account-grid">

            <div class="account-field full">
            
                <label>Profile Picture</label>
            
                <div class="profile-picture-section">
            
                    <?php if (!empty($profileImageUrl)): ?>
            
                        <img
                            src="<?= htmlspecialchars($profileImageUrl) ?>"
                            alt="Profile Picture"
                            class="profile-picture-preview"
                            id="profilePicturePreview"
                        >
            
                    <?php else: ?>
            
                        <div class="profile-picture-placeholder" id="profilePicturePlaceholder">
                            <?= strtoupper(substr($profile['display_name'] ?? 'U', 0, 1)) ?>
                        </div>

                        <img
                            src=""
                            alt="Profile Picture Preview"
                            class="profile-picture-preview profile-picture-preview-hidden"
                            id="profilePicturePreview"
                        >
            
                    <?php endif; ?>
            
                    <div class="profile-picture-upload-area">
            
                        <input
                            type="file"
                            name="profile_picture"
                            accept="image/jpeg,image/png,image/webp"
                        >
            
                        <div class="profile-picture-helper">
                            JPG, PNG, or WEBP. Images are automatically optimized and scanned before being saved.
                        </div>
            
                    </div>
            
                </div>
            
            </div>

<div class="coming-soon-box">
    Cover photo uploads coming soon.
</div>

                <div class="account-field">
                    <label>First Name</label>
                    <input type="text" name="first_name" value="<?= htmlspecialchars($profile['first_name'] ?? '') ?>">
                </div>

                <div class="account-field">
                    <label>Last Name</label>
                    <input type="text" name="last_name" value="<?= htmlspecialchars($profile['last_name'] ?? '') ?>">
                </div>

                <div class="account-field full">
                    <label>Display Name</label>
                    <input type="text" name="display_name" value="<?= htmlspecialchars($profile['display_name'] ?? '') ?>">
                </div>

                <div class="account-field full">
                    <label>Bio</label>
                    <textarea name="bio"><?= htmlspecialchars($profile['bio'] ?? '') ?></textarea>
                </div>

                <div class="account-field">
                    <label>Website URL</label>
                    <input type="url" name="website_url" value="<?= htmlspecialchars($profile['website_url'] ?? '') ?>">
                </div>

                <div class="account-field">
                    <label>Phone</label>
                    <input
                        type="tel"
                        name="phone"
                        id="phoneInput"
                        maxlength="14"
                        placeholder="(555) 555-5555"
                        pattern="\([0-9]{3}\) [0-9]{3}-[0-9]{4}"
                        value="<?= htmlspecialchars($profile['phone'] ?? '') ?>"
                    >
                </div>

                <div class="account-field">
                    <label>Birthday</label>
                    <input type="date" name="birthday" value="<?= htmlspecialchars($profile['birthday'] ?? '') ?>">
                </div>

                <div class="account-field">
                    <label>Location</label>
                    <input type="text" name="location" value="<?= htmlspecialchars($profile['location'] ?? '') ?>">
                </div>

                <div class="account-field">
                    <label>Job Title</label>
                    <input type="text" name="job_title" value="<?= htmlspecialchars($profile['job_title'] ?? '') ?>">
                </div>

                <div class="account-field">
                    <label>Company</label>
                    <input type="text" name="company" value="<?= htmlspecialchars($profile['company'] ?? '') ?>">
                </div>

                <div class="account-field">
                    <label>School</label>
                    <input type="text" name="school" value="<?= htmlspecialchars($profile['school'] ?? '') ?>">
                </div>

                <div class="account-field">
                    <label>City</label>
                    <input type="text" name="city" value="<?= htmlspecialchars($profile['city'] ?? '') ?>">
                </div>

                <div class="account-options">

                    <label class="account-checkbox">
                        <input type="checkbox" name="is_private" <?= !empty($profile['is_private']) ? 'checked' : '' ?>>
                        Make my profile private
                    </label>

                    <label class="account-checkbox">
                        <input type="checkbox" name="allow_email_notifications" <?= !empty($profile['allow_email_notifications']) ? 'checked' : '' ?>>
                        Allow email notifications
                    </label>

                    <label class="account-checkbox">
                        <input type="checkbox" name="allow_profile_search" <?= !empty($profile['allow_profile_search']) ? 'checked' : '' ?>>
                        Allow my profile to appear in search
                    </label>

                </div>

            </div>

            <div class="account-actions">
                <button type="submit" class="account-button save-button">
                    Save Changes
                </button>

                <a href="/dashboard.php" class="account-button back-button">
                    Back
                </a>
            </div>

        </form>

        <form method="POST" class="delete-account-section">

            <div class="delete-account-title">
                Delete Account
            </div>

            <div class="delete-account-text">
                Permanently delete your account and profile information. This action cannot be undone.
            </div>

            <button
                type="submit"
                name="delete_account"
                value="1"
                class="account-button delete-button"
                onclick="return confirm('Are you sure you want to permanently delete your account? This action cannot be undone. Your account and profile information will be permanently removed.');"
            >
                Delete My Account
            </button>

        </form>

    </section>

</main>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const phoneInput = document.getElementById("phoneInput");

    if (phoneInput) {
        phoneInput.addEventListener("input", function () {
            let digits = phoneInput.value.replace(/\D/g, "").substring(0, 10);
            let formatted = digits;

            if (digits.length > 6) {
                formatted = "(" + digits.substring(0, 3) + ") " + digits.substring(3, 6) + "-" + digits.substring(6);
            } else if (digits.length > 3) {
                formatted = "(" + digits.substring(0, 3) + ") " + digits.substring(3);
            } else if (digits.length > 0) {
                formatted = "(" + digits;
            }

            phoneInput.value = formatted;
        });
    }

    const profileInput = document.getElementById("profilePictureInput");
    const profilePreview = document.getElementById("profilePicturePreview");
    const profilePlaceholder = document.getElementById("profilePicturePlaceholder");
    const previewActions = document.getElementById("profilePicturePreviewActions");
    const changeButton = document.getElementById("changeProfilePictureButton");
    const removeButton = document.getElementById("removeProfilePictureButton");

    function showPreview(file) {
        if (!profilePreview || !file) {
            return;
        }

        const previewUrl = URL.createObjectURL(file);

        profilePreview.src = previewUrl;
        profilePreview.classList.remove("profile-picture-preview-hidden");

        if (profilePlaceholder) {
            profilePlaceholder.style.display = "none";
        }

        if (previewActions) {
            previewActions.classList.add("show");
        }

        profilePreview.onload = function () {
            URL.revokeObjectURL(previewUrl);
        };
    }

    function clearSelectedPreview() {
        if (profileInput) {
            profileInput.value = "";
        }

        if (profilePreview) {
            const originalSrc = profilePreview.getAttribute("data-original-src");

            if (originalSrc) {
                profilePreview.src = originalSrc;
                profilePreview.classList.remove("profile-picture-preview-hidden");
            } else {
                profilePreview.src = "";
                profilePreview.classList.add("profile-picture-preview-hidden");

                if (profilePlaceholder) {
                    profilePlaceholder.style.display = "";
                }
            }
        }

        if (previewActions) {
            previewActions.classList.remove("show");
        }
    }

    if (profilePreview && profilePreview.getAttribute("src")) {
        profilePreview.setAttribute("data-original-src", profilePreview.getAttribute("src"));
    }

    if (profileInput) {
        profileInput.addEventListener("change", function () {
            const file = profileInput.files[0];

            if (!file) {
                clearSelectedPreview();
                return;
            }

            const allowedTypes = ["image/jpeg", "image/png", "image/webp"];
            const maxSize = 10 * 1024 * 1024;

            if (!allowedTypes.includes(file.type)) {
                alert("Only JPG, PNG, and WEBP images are allowed.");
                clearSelectedPreview();
                return;
            }

            if (file.size > maxSize) {
                alert("Profile image must be under 10MB.");
                clearSelectedPreview();
                return;
            }

            showPreview(file);
        });
    }

    if (changeButton && profileInput) {
        changeButton.addEventListener("click", function () {
            profileInput.click();
        });
    }

    if (removeButton) {
        removeButton.addEventListener("click", function () {
            clearSelectedPreview();
        });
    }
});
</script>

<?php
include '../includes/footer.php';
?>