<?php

require_once '../includes/auth.php';
require_login();

require_once '../config/database.php';
require_once '../includes/s3_upload.php';

$awsConfig = require __DIR__ . '/../config/aws.php';
$s3 = $awsConfig['s3'];
$ses = $awsConfig['ses'] ?? null;
$rekognition = $awsConfig['rekognition'] ?? null;
$s3Bucket = $awsConfig['bucket'];

include '../includes/header.php';
include '../includes/navbar.php';

$user_id = $_SESSION['user_id'];
$message = "";
$message_type = "";

function fetchAccountData(PDO $pdo, $user_id): array {
    $stmt = $pdo->prepare("
        SELECT name, email, pending_email, email_verified
        FROM users
        WHERE id = :user_id
        LIMIT 1
    ");

    $stmt->execute([
        ':user_id' => $user_id
    ]);

    return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
}

function splitAccountName(string $fullName): array {
    $fullName = trim(preg_replace('/\\s+/u', ' ', $fullName));

    if ($fullName === '') {
        return ['', ''];
    }

    $parts = preg_split('/\\s+/u', $fullName, 2);

    return [
        trim((string) ($parts[0] ?? '')),
        trim((string) ($parts[1] ?? ''))
    ];
}

function accountNamePartIsValid(string $value): bool {
    $value = trim(preg_replace('/\\s+/u', ' ', $value));

    if ($value === '') {
        return false;
    }

    $length = function_exists('mb_strlen')
        ? mb_strlen($value, 'UTF-8')
        : strlen($value);

    if ($length < 2 || $length > 100) {
        return false;
    }

    if (!preg_match("/^[\\p{L}\\p{M}][\\p{L}\\p{M} .'’\\-]*$/u", $value)) {
        return false;
    }

    preg_match_all('/\\p{L}/u', $value, $letters);

    return count($letters[0]) >= 2;
}

function accountEmailIsDisposable(string $email): bool {
    $blockedDomains = [
        'mailinator.com',
        '10minutemail.com',
        'guerrillamail.com',
        'tempmail.com',
        'temp-mail.org',
        'yopmail.com',
        'trashmail.com',
        'fakeinbox.com',
        'getnada.com',
        'dispostable.com',
        'maildrop.cc'
    ];

    $atPosition = strrpos($email, '@');

    if ($atPosition === false) {
        return true;
    }

    $domain = strtolower(substr($email, $atPosition + 1));

    return in_array($domain, $blockedDomains, true);
}

function accountEmailDomainHasMx(string $email): bool {
    $atPosition = strrpos($email, '@');

    if ($atPosition === false) {
        return false;
    }

    $domain = substr($email, $atPosition + 1);

    if ($domain === '') {
        return false;
    }

    return checkdnsrr($domain, 'MX');
}

function sendEmailChangeVerificationEmail($ses, string $toEmail, string $name, string $verificationLink): void {
    if (!$ses) {
        throw new Exception('Email service is not configured.');
    }

    $fromEmail = 'noreply@whusup.com';
    $fromName = 'Whusup';
    $safeName = trim($name) !== '' ? trim($name) : 'there';

    $subject = 'Verify your new Whusup email address';

    $textBody = "Hi {$safeName},\n\n" .
        "You requested to change the email address on your Whusup account. Please confirm the new address by opening this link:\n\n" .
        $verificationLink . "\n\n" .
        "This link expires in 24 hours. Your current email address will remain active until the new address is verified.\n\n" .
        "If you did not request this change, you can ignore this email.";

    $htmlBody = '
        <div style="font-family: Arial, sans-serif; line-height: 1.6; color: #111827; max-width: 560px; margin: 0 auto; padding: 24px;">
            <h2 style="margin: 0 0 12px;">Verify your new email address</h2>
            <p>Hi ' . htmlspecialchars($safeName, ENT_QUOTES, 'UTF-8') . ',</p>
            <p>You requested to change the email address on your Whusup account. Please verify this new address to complete the change.</p>
            <p style="margin: 24px 0;">
                <a href="' . htmlspecialchars($verificationLink, ENT_QUOTES, 'UTF-8') . '" style="background: #111827; color: #ffffff; padding: 12px 20px; border-radius: 999px; text-decoration: none; font-weight: 700; display: inline-block;">
                    Verify New Email
                </a>
            </p>
            <p>If the button does not work, copy and paste this link into your browser:</p>
            <p style="word-break: break-all;">
                <a href="' . htmlspecialchars($verificationLink, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($verificationLink, ENT_QUOTES, 'UTF-8') . '</a>
            </p>
            <p>This link expires in 24 hours. Your current email address will remain active until the new address is verified.</p>
            <p style="color: #6b7280; font-size: 13px;">If you did not request this change, you can ignore this email.</p>
        </div>
    ';

    $ses->sendEmail([
        'Source' => $fromName . ' <' . $fromEmail . '>',
        'Destination' => [
            'ToAddresses' => [$toEmail]
        ],
        'Message' => [
            'Subject' => [
                'Data' => $subject,
                'Charset' => 'UTF-8'
            ],
            'Body' => [
                'Text' => [
                    'Data' => $textBody,
                    'Charset' => 'UTF-8'
                ],
                'Html' => [
                    'Data' => $htmlBody,
                    'Charset' => 'UTF-8'
                ]
            ]
        ]
    ]);
}

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
    INSERT IGNORE INTO user_profiles (user_id, display_name, allow_email_notifications)
    VALUES (:user_id, :display_name, 1)
");

$stmt->execute([
    ':user_id' => $user_id,
    ':display_name' => $_SESSION['user_name'] ?? null
]);

$account = fetchAccountData($pdo, $user_id);

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

    $firstName = trim(preg_replace('/\\s+/u', ' ', $_POST['first_name'] ?? ''));
    $lastName = trim(preg_replace('/\\s+/u', ' ', $_POST['last_name'] ?? ''));
    $fullName = trim($firstName . ' ' . $lastName);

    $displayName = trim(preg_replace('/\\s+/u', ' ', $_POST['display_name'] ?? ''));
    $displayNameLength = function_exists('mb_strlen')
        ? mb_strlen($displayName, 'UTF-8')
        : strlen($displayName);

    $bio = trim($_POST['bio'] ?? '');
    $bio_length = function_exists('mb_strlen')
        ? mb_strlen($bio, 'UTF-8')
        : strlen($bio);

    $websiteUrl = trim($_POST['website_url'] ?? '');
    $location = trim(preg_replace('/\\s+/u', ' ', $_POST['location'] ?? ''));
    $country = trim(preg_replace('/\\s+/u', ' ', $_POST['country'] ?? ''));
    $jobTitle = trim(preg_replace('/\\s+/u', ' ', $_POST['job_title'] ?? ''));
    $company = trim(preg_replace('/\\s+/u', ' ', $_POST['company'] ?? ''));
    $school = trim(preg_replace('/\\s+/u', ' ', $_POST['school'] ?? ''));

    $textLength = static function (string $value): int {
        return function_exists('mb_strlen')
            ? mb_strlen($value, 'UTF-8')
            : strlen($value);
    };

    $phone = trim($_POST['phone'] ?? '');
    $phone_digits = preg_replace('/\\D/', '', $phone);

    $currentEmail = strtolower(trim($account['email'] ?? ''));
    $requestedEmail = strtolower(trim($_POST['email'] ?? $currentEmail));
    $emailChangeRequested = ($requestedEmail !== $currentEmail);

    if (!accountNamePartIsValid($firstName)) {

        $message = "Please enter a valid first name using letters and standard name punctuation.";
        $message_type = "danger";

    } elseif (!accountNamePartIsValid($lastName)) {

        $message = "Please enter a valid last name using letters and standard name punctuation.";
        $message_type = "danger";

    } elseif ($displayName === '' || $displayNameLength > 100) {

        $message = "Display Name is required and must be 100 characters or less.";
        $message_type = "danger";

    } elseif ($bio_length > 500) {

        $message = "Bio must be 500 characters or less.";
        $message_type = "danger";

    } elseif ($textLength($websiteUrl) > 255) {

        $message = "Website URL must be 255 characters or less.";
        $message_type = "danger";

    } elseif ($websiteUrl !== '' && !filter_var($websiteUrl, FILTER_VALIDATE_URL)) {

        $message = "Please enter a valid Website URL, including http:// or https://.";
        $message_type = "danger";

    } elseif ($textLength($location) > 150) {

        $message = "City / State must be 150 characters or less.";
        $message_type = "danger";

    } elseif ($textLength($country) > 100) {

        $message = "Country must be 100 characters or less.";
        $message_type = "danger";

    } elseif ($textLength($jobTitle) > 100) {

        $message = "Job Title must be 100 characters or less.";
        $message_type = "danger";

    } elseif ($textLength($company) > 100) {

        $message = "Company must be 100 characters or less.";
        $message_type = "danger";

    } elseif ($textLength($school) > 150) {

        $message = "School must be 150 characters or less.";
        $message_type = "danger";

    } elseif ($phone !== '' && strlen($phone_digits) !== 10) {

        $message = "Please enter a valid 10-digit phone number.";
        $message_type = "danger";

    } elseif ($requestedEmail === '' || strlen($requestedEmail) > 255 || !filter_var($requestedEmail, FILTER_VALIDATE_EMAIL)) {

        $message = "Please enter a valid email address.";
        $message_type = "danger";

    } elseif ($emailChangeRequested && accountEmailIsDisposable($requestedEmail)) {

        $message = "Please use a permanent email address.";
        $message_type = "danger";

    } elseif ($emailChangeRequested && !accountEmailDomainHasMx($requestedEmail)) {

        $message = "Please enter a working email address.";
        $message_type = "danger";

    } else {

        $phone_for_db = $phone_digits !== ''
            ? '(' . substr($phone_digits, 0, 3) . ') ' . substr($phone_digits, 3, 3) . '-' . substr($phone_digits, 6)
            : '';

        $newProfileImageKey = null;
        $existingProfileImageKey = null;

        try {

            // Display names are public identities and must be unique.
            // The database UNIQUE constraint remains the final protection against race conditions.
            $displayNameCheckStmt = $pdo->prepare("
                SELECT user_id
                FROM user_profiles
                WHERE display_name = :display_name
                  AND user_id <> :user_id
                LIMIT 1
            ");

            $displayNameCheckStmt->execute([
                ':display_name' => $displayName,
                ':user_id' => $user_id
            ]);

            if ($displayNameCheckStmt->fetch()) {
                $message = "That display name is already in use. Please choose another one.";
                $message_type = "danger";
            }

            if ($message === "" && $emailChangeRequested) {
                $emailCheckStmt = $pdo->prepare("
                    SELECT id
                    FROM users
                    WHERE LOWER(email) = :email
                      AND id <> :user_id
                    LIMIT 1
                ");

                $emailCheckStmt->execute([
                    ':email' => $requestedEmail,
                    ':user_id' => $user_id
                ]);

                if ($emailCheckStmt->fetch()) {
                    $message = "An account with that email already exists.";
                    $message_type = "danger";
                }
            }

            if ($message === "") {
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
                $existingProfileImageKey = $existingProfile['profile_picture_url'] ?? null;
            }

            if ($message === "" && !empty($_FILES['profile_picture']['name'])) {

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

                                $newProfileImageKey = null;
                                $message = "This profile picture cannot be uploaded because it may violate community guidelines.";
                                $message_type = "danger";
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

                            $newProfileImageKey = null;
                            $message = "Image moderation failed. Please try again.";
                            $message_type = "danger";
                            error_log('Rekognition profile image moderation failed: ' . $e->getMessage());
                        }
                    }
                }
            }

            if ($message === "") {

                $profileImageKey = $newProfileImageKey ?: $existingProfileImageKey;

                try {
                    $pdo->beginTransaction();

                    // users.name is the authoritative full account/legal name.
                    $userNameStmt = $pdo->prepare("
                        UPDATE users
                        SET name = :name
                        WHERE id = :user_id
                    ");

                    $userNameStmt->execute([
                        ':name' => $fullName,
                        ':user_id' => $user_id
                    ]);

                    // Keep structured first/last values synchronized for future profile/account use.
                    $profileStmt = $pdo->prepare("
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
                            location = :location,
                            country = :country,
                            profile_picture_url = :profile_picture_url,
                            is_private = :is_private,
                            allow_email_notifications = :allow_email_notifications
                        WHERE user_id = :user_id
                    ");

                    $profileStmt->execute([
                        ':first_name' => $firstName,
                        ':last_name' => $lastName,
                        ':display_name' => $displayName,
                        ':bio' => $bio,
                        ':website_url' => $websiteUrl,
                        ':phone' => $phone_for_db,
                        ':birthday' => !empty($_POST['birthday']) ? $_POST['birthday'] : null,
                        ':job_title' => $jobTitle,
                        ':company' => $company,
                        ':school' => $school,
                        ':location' => $location,
                        ':country' => $country,
                        ':profile_picture_url' => $profileImageKey,
                        ':is_private' => isset($_POST['is_private']) ? 1 : 0,
                        ':allow_email_notifications' => isset($_POST['allow_email_notifications']) ? 1 : 0,
                        ':user_id' => $user_id
                    ]);

                    if ($profileStmt->rowCount() === 0) {
                        $profileExistsStmt = $pdo->prepare("
                            SELECT 1
                            FROM user_profiles
                            WHERE user_id = :user_id
                            LIMIT 1
                        ");
                        $profileExistsStmt->execute([':user_id' => $user_id]);

                        if (!$profileExistsStmt->fetchColumn()) {
                            throw new RuntimeException('Profile record is missing for this account.');
                        }
                    }

                    if ($emailChangeRequested) {
                        $rawToken = bin2hex(random_bytes(32));
                        $tokenHash = hash('sha256', $rawToken);
                        $expiresAt = date('Y-m-d H:i:s', time() + (24 * 60 * 60));
                        $verificationLink = 'https://whusup.com/verify_email.php?token=' . urlencode($rawToken);

                        $pendingStmt = $pdo->prepare("
                            UPDATE users
                            SET pending_email = :pending_email
                            WHERE id = :user_id
                        ");

                        $pendingStmt->execute([
                            ':pending_email' => $requestedEmail,
                            ':user_id' => $user_id
                        ]);

                        $verificationStmt = $pdo->prepare("
                            INSERT INTO email_verifications (
                                user_id,
                                token_hash,
                                expires_at,
                                verification_type
                            )
                            VALUES (
                                :user_id,
                                :token_hash,
                                :expires_at,
                                'email_change'
                            )
                            ON DUPLICATE KEY UPDATE
                                token_hash = VALUES(token_hash),
                                expires_at = VALUES(expires_at),
                                verification_type = VALUES(verification_type),
                                created_at = CURRENT_TIMESTAMP
                        ");

                        $verificationStmt->execute([
                            ':user_id' => $user_id,
                            ':token_hash' => $tokenHash,
                            ':expires_at' => $expiresAt
                        ]);

                        sendEmailChangeVerificationEmail(
                            $ses,
                            $requestedEmail,
                            $fullName,
                            $verificationLink
                        );
                    }

                    $pdo->commit();

                    // Keep the session's account name synchronized with users.name.
                    $_SESSION['user_name'] = $fullName;

                    // Delete the old image only after the database successfully points to the new one.
                    if ($newProfileImageKey && !empty($existingProfileImageKey)) {
                        try {
                            $s3->deleteObject([
                                'Bucket' => $s3Bucket,
                                'Key' => $existingProfileImageKey
                            ]);
                        } catch (Exception $e) {
                            error_log('Old profile image delete failed: ' . $e->getMessage());
                        }
                    }

                    if ($emailChangeRequested) {
                        $message = "Profile updated successfully. We sent a verification link to your new email address. Your current email will remain active until the new address is verified.";
                    } else {
                        $message = "Profile updated successfully.";
                    }

                    $message_type = "success";

                } catch (Throwable $updateException) {

                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }

                    if ($newProfileImageKey) {
                        try {
                            $s3->deleteObject([
                                'Bucket' => $s3Bucket,
                                'Key' => $newProfileImageKey
                            ]);
                        } catch (Exception $deleteException) {
                            error_log('Failed profile image cleanup after database rollback: ' . $deleteException->getMessage());
                        }
                    }

                    // MariaDB duplicate-key protection remains authoritative even if two users
                    // attempt to claim the same display name at nearly the same moment.
                    if ($updateException instanceof PDOException && $updateException->getCode() === '23000') {
                        $message = "That display name is already in use. Please choose another one.";
                    } else {
                        $message = "Profile update failed. Please try again.";
                        error_log('Profile update failed: ' . $updateException->getMessage());
                    }

                    $message_type = "danger";
                }
            }

        } catch (Throwable $e) {

            if ($newProfileImageKey) {
                try {
                    $s3->deleteObject([
                        'Bucket' => $s3Bucket,
                        'Key' => $newProfileImageKey
                    ]);
                } catch (Exception $deleteException) {
                    error_log('Failed profile image cleanup after profile error: ' . $deleteException->getMessage());
                }
            }

            $message = "Profile update failed. Please try again.";
            $message_type = "danger";
            error_log('Profile update preparation failed: ' . $e->getMessage());
        }
    }
}

$account = fetchAccountData($pdo, $user_id);

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

$profile = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

$displayNameValue = trim((string) ($profile['display_name'] ?? ''));

if ($displayNameValue === '') {
    $displayNameValue = trim((string) ($account['name'] ?? ''));
}

[$firstNameValue, $lastNameValue] = splitAccountName((string) ($account['name'] ?? ''));

$profileImageUrl = !empty($profile['profile_picture_url'])
    ? getProfileImageUrl($profile['profile_picture_url'])
    : null;

?>

<style>
.account-page {
    width: 100%;
    min-height: 100vh;
    background: #ffffff;
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

.account-section {
    margin-top: 28px;
}

.account-section:first-of-type {
    margin-top: 0;
}

.account-section-header {
    margin-bottom: 16px;
    padding-bottom: 12px;
    border-bottom: 1px solid #e5e7eb;
}

.account-section-title {
    margin: 0 0 5px;
    color: #111827;
    font-family: "Poppins", sans-serif;
    font-size: 18px;
    font-weight: 700;
}

.account-section-note {
    margin: 0;
    color: #6b7280;
    font-size: 13px;
    line-height: 1.55;
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

.account-field-helper {
    margin-top: 6px;
    font-size: 12px;
    line-height: 1.45;
    color: #6b7280;
}

.account-field-helper strong {
    color: #374151;
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

.bio-character-info {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    margin-top: 6px;
    font-size: 12px;
    color: #6b7280;
}

#bioCharacterCounter {
    white-space: nowrap;
    font-weight: 600;
}

.account-field input:focus,
.account-field textarea:focus {
    border-color: #9ca3af;
    outline: none;
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

            <section class="account-section">

                <div class="account-section-header">
                    <h2 class="account-section-title">Public Information</h2>
                    <p class="account-section-note">
                        Information you add here may be shown with your profile and bio on Whusup.
                        You can keep your bio and public information private using the privacy option below.
                    </p>
                </div>

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
                                    <?= strtoupper(substr($displayNameValue !== '' ? $displayNameValue : 'U', 0, 1)) ?>
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
                                    id="profilePictureInput"
                                    accept="image/jpeg,image/png,image/webp"
                                >

                                <div class="profile-picture-helper">
                                    JPG, PNG, or WEBP. Images are automatically optimized and scanned before being saved.
                                </div>

                            </div>

                        </div>

                    </div>

                    <div class="account-field full">
                        <label>Display Name</label>
                        <input
                            type="text"
                            name="display_name"
                            maxlength="100"
                            value="<?= htmlspecialchars($displayNameValue, ENT_QUOTES, 'UTF-8') ?>"
                        >
                    </div>

                    <div class="account-field full">
                        <label>Bio</label>
                        <textarea name="bio" id="bioInput" maxlength="500"><?= htmlspecialchars($profile['bio'] ?? '') ?></textarea>
                        <div class="bio-character-info">
                            <span>Maximum 500 characters.</span>
                            <span id="bioCharacterCounter">0 / 500</span>
                        </div>
                    </div>

                    <div class="account-field">
                        <label>Website URL</label>
                        <input
                            type="url"
                            name="website_url"
                            maxlength="255"
                            placeholder="https://example.com"
                            value="<?= htmlspecialchars($profile['website_url'] ?? '') ?>"
                        >
                    </div>

                    <div class="account-field">
                        <label>City / State</label>
                        <input
                            type="text"
                            name="location"
                            maxlength="150"
                            placeholder="San Jose, CA"
                            value="<?= htmlspecialchars($profile['location'] ?? '') ?>"
                        >
                    </div>

                    <div class="account-field">
                        <label>Country</label>
                        <input
                            type="text"
                            name="country"
                            maxlength="100"
                            placeholder="United States"
                            value="<?= htmlspecialchars($profile['country'] ?? '') ?>"
                        >
                    </div>

                    <div class="account-field">
                        <label>Job Title</label>
                        <input
                            type="text"
                            name="job_title"
                            maxlength="100"
                            value="<?= htmlspecialchars($profile['job_title'] ?? '') ?>"
                        >
                    </div>

                    <div class="account-field">
                        <label>Company</label>
                        <input
                            type="text"
                            name="company"
                            maxlength="100"
                            value="<?= htmlspecialchars($profile['company'] ?? '') ?>"
                        >
                    </div>

                    <div class="account-field">
                        <label>School</label>
                        <input
                            type="text"
                            name="school"
                            maxlength="150"
                            value="<?= htmlspecialchars($profile['school'] ?? '') ?>"
                        >
                    </div>

                </div>

            </section>

            <section class="account-section">

                <div class="account-section-header">
                    <h2 class="account-section-title">Private Details</h2>
                    <p class="account-section-note">
                        This information will never be displayed publicly on your Whusup profile.
                        It is kept for account management and future account verification, security, or recovery features.
                    </p>
                </div>

                <div class="account-grid">

                    <div class="account-field">
                        <label>First Name</label>
                        <input type="text" name="first_name" value="<?= htmlspecialchars($firstNameValue, ENT_QUOTES, 'UTF-8') ?>">
                    </div>

                    <div class="account-field">
                        <label>Last Name</label>
                        <input type="text" name="last_name" value="<?= htmlspecialchars($lastNameValue, ENT_QUOTES, 'UTF-8') ?>">
                    </div>

                    <div class="account-field full">
                        <label>Email</label>
                        <input
                            type="email"
                            name="email"
                            maxlength="255"
                            autocomplete="email"
                            value="<?= htmlspecialchars($account['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                            required
                        >
                        <div class="account-field-helper">
                            Changing your email requires verification. Your current email remains active until the new address is verified.
                            <?php if (!empty($account['pending_email'])): ?>
                                <br><strong>Pending verification:</strong>
                                <?= htmlspecialchars($account['pending_email'], ENT_QUOTES, 'UTF-8') ?>
                            <?php endif; ?>
                        </div>
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

                </div>

            </section>

            <div class="account-options">

                <label class="account-checkbox">
                    <input
                        type="checkbox"
                        name="is_private"
                        <?= ((int) ($profile['is_private'] ?? 0) === 1) ? 'checked' : '' ?>
                    >
                    Keep my bio and public information private
                </label>

                <label class="account-checkbox">
                    <input
                        type="checkbox"
                        name="allow_email_notifications"
                        <?= ((int) ($profile['allow_email_notifications'] ?? 1) === 1) ? 'checked' : '' ?>
                    >
                    Allow email notifications
                </label>

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
    const bioInput = document.getElementById("bioInput");
    const bioCharacterCounter = document.getElementById("bioCharacterCounter");

    function updateBioCharacterCounter() {
        if (!bioInput || !bioCharacterCounter) {
            return;
        }

        const characterCount = Array.from(bioInput.value).length;
        bioCharacterCounter.textContent = characterCount + " / 500";
    }

    if (bioInput) {
        updateBioCharacterCounter();
        bioInput.addEventListener("input", updateBioCharacterCounter);
    }

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