<?php

require_once '../includes/auth.php';
require_login();

require_once '../config/database.php';
require_once '../includes/s3_upload.php';

$awsConfig = require '../config/aws.php';

$rekognition = $awsConfig['rekognition'] ?? null;
$s3 = $awsConfig['s3'] ?? null;
$s3Bucket = $awsConfig['bucket'] ?? null;

$message = "";
$user_id = $_SESSION['user_id'];
$user_first_name = "";

if (empty($_SESSION['post_form_token'])) {
    $_SESSION['post_form_token'] = bin2hex(random_bytes(32));
}

$post_form_token = $_SESSION['post_form_token'];

try {
    $nameStmt = $pdo->prepare("SELECT name FROM users WHERE id = :user_id LIMIT 1");
    $nameStmt->execute([':user_id' => $user_id]);
    $user = $nameStmt->fetch(PDO::FETCH_ASSOC);

    if (!empty($user['name'])) {
        $nameParts = explode(' ', trim($user['name']));
        $user_first_name = $nameParts[0];
    }
} catch (PDOException $e) {
    $user_first_name = "";
}

$blocked_words = [
    'viagra', 'casino', 'porn', 'xxx', 'scam', 'hack',
    'malware', 'phishing', 'crypto giveaway', 'free money'
];

$blocked_link_patterns = [
    '/bit\.ly/i',
    '/tinyurl\.com/i',
    '/t\.co/i',
    '/goo\.gl/i',
    '/is\.gd/i',
    '/free-money/i',
    '/giveaway/i',
    '/adult/i'
];

function contains_blocked_content($text, $blocked_words, $blocked_link_patterns) {
    $lowerText = mb_strtolower($text, 'UTF-8');

    foreach ($blocked_words as $word) {
        $word = mb_strtolower(trim($word), 'UTF-8');

        // Match whole phrase/word, not substrings inside other words.
        $pattern = '/(?<![\p{L}\p{N}_])' . preg_quote($word, '/') . '(?![\p{L}\p{N}_])/iu';

        if (preg_match($pattern, $lowerText)) {
            return true;
        }
    }

    foreach ($blocked_link_patterns as $pattern) {
        if (preg_match($pattern, $text)) {
            return true;
        }
    }

    return false;
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

function compress_uploaded_image(array $file): array {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'Image upload failed.'];
    }

    $maxOriginalSize = 10 * 1024 * 1024;

    if ($file['size'] > $maxOriginalSize) {
        return ['success' => false, 'error' => 'Image must be 10MB or smaller.'];
    }

    if (!function_exists('imagecreatefromjpeg')) {
        return ['success' => false, 'error' => 'Image compression is not available. PHP GD is missing.'];
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

    $maxWidth = 1600;
    $maxHeight = 1600;

    $ratio = min($maxWidth / $width, $maxHeight / $height, 1);

    $newWidth = (int) round($width * $ratio);
    $newHeight = (int) round($height * $ratio);

    $newImage = imagecreatetruecolor($newWidth, $newHeight);

    // Fill with white first so transparent PNG/WEBP images do not turn black when converted to JPG.
    $white = imagecolorallocate($newImage, 255, 255, 255);
    imagefill($newImage, 0, 0, $white);

    imagecopyresampled(
        $newImage,
        $sourceImage,
        0,
        0,
        0,
        0,
        $newWidth,
        $newHeight,
        $width,
        $height
    );

    $tempPath = tempnam(sys_get_temp_dir(), 'whusup_img_') . '.jpg';

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

function imagePassesModeration($rekognition, $bucket, $imageKey): array
{
    if (!$rekognition || empty($bucket) || empty($imageKey)) {
        return [
            'success' => false,
            'passed' => false,
            'message' => 'Image moderation is not configured.'
        ];
    }

    try {
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
            'Graphic Male Nudity',
            'Graphic Female Nudity',
            'Sexual Activity',
            'Illustrated Explicit Nudity',
            'Adult Toys',

            'Suggestive',
            'Female Swimwear Or Underwear',
            'Male Swimwear Or Underwear',
            'Partial Nudity',
            'Barechested Male',
            'Revealing Clothes',
            'Sexual Situations',

            'Violence',
            'Graphic Violence',
            'Physical Violence',
            'Weapon Violence',
            'Weapons',

            'Visually Disturbing',
            'Emaciated Bodies',
            'Corpses',
            'Hanging',
            'Air Crash',
            'Explosions And Blasts',

            'Drugs',
            'Drug Products',
            'Drug Use',
            'Pills',

            'Tobacco',
            'Alcohol',
            'Gambling',

            'Hate Symbols',
            'Nazi Party',
            'White Supremacy'
        ];

        foreach ($result['ModerationLabels'] as $label) {
            $name = $label['Name'] ?? '';
            $parent = $label['ParentName'] ?? '';
            $confidence = (float) ($label['Confidence'] ?? 0);

            if (
                $confidence >= 80 &&
                (
                    in_array($name, $blockedLabels, true) ||
                    in_array($parent, $blockedLabels, true)
                )
            ) {
                return [
                    'success' => true,
                    'passed' => false,
                    'message' => 'This image cannot be uploaded because it may violate community guidelines.'
                ];
            }
        }

        return [
            'success' => true,
            'passed' => true,
            'message' => ''
        ];

    } catch (Exception $e) {
        error_log('Rekognition moderation failed: ' . $e->getMessage());

        return [
            'success' => false,
            'passed' => false,
            'message' => 'Image moderation failed. Please try another image or try again later.'
        ];
    }
}

function delete_s3_object_if_exists($s3, $bucket, $imageKey): void
{
    if (!$s3 || empty($bucket) || empty($imageKey)) {
        return;
    }

    try {
        $s3->deleteObject([
            'Bucket' => $bucket,
            'Key' => $imageKey
        ]);
    } catch (Exception $e) {
        error_log('S3 cleanup failed: ' . $e->getMessage());
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $submitted_token = $_POST['post_form_token'] ?? '';
    $session_token = $_SESSION['post_form_token'] ?? '';

    if (
        empty($submitted_token) ||
        empty($session_token) ||
        !hash_equals($session_token, $submitted_token)
    ) {
        $message = "This post was already submitted or the form expired. Please try again.";
    } else {
        unset($_SESSION['post_form_token']);
    }

    $body = trim($_POST['post_body'] ?? '');
    $tag = trim($_POST['tag'] ?? '');
    $combinedContent = $body . ' ' . $tag;
    $uploadedImageKeys = [];

    if ($message !== '') {
        // Keep the existing message from the duplicate/expired form-token check.
    } elseif ($body === '') {
        $message = "Post cannot be empty.";
    } elseif (mb_strlen($body) > 500) {
        $message = "Post cannot exceed 500 characters.";
    } elseif (mb_strlen($tag) > 30) {
        $message = "Tag cannot exceed 30 characters.";
    } elseif (contains_blocked_content($combinedContent, $blocked_words, $blocked_link_patterns)) {
        $message = "Your post contains content or links that are not allowed.";
    } else {

        $postImages = $_FILES['post_images'] ?? null;
        $selectedImages = [];

        if (
            is_array($postImages) &&
            isset($postImages['name']) &&
            is_array($postImages['name'])
        ) {
            foreach ($postImages['name'] as $index => $name) {
                $error = $postImages['error'][$index] ?? UPLOAD_ERR_NO_FILE;

                if (empty($name) && $error === UPLOAD_ERR_NO_FILE) {
                    continue;
                }

                $selectedImages[] = [
                    'name' => $postImages['name'][$index] ?? '',
                    'type' => $postImages['type'][$index] ?? '',
                    'tmp_name' => $postImages['tmp_name'][$index] ?? '',
                    'error' => $error,
                    'size' => $postImages['size'][$index] ?? 0
                ];
            }
        }

        if (count($selectedImages) > 3) {
            $message = "You can upload up to 3 photos per post.";
        }

        if ($message === '' && !empty($selectedImages)) {
            foreach ($selectedImages as $selectedImage) {
                $compressedImage = compress_uploaded_image($selectedImage);

                if (!$compressedImage['success']) {
                    $message = $compressedImage['error'];
                    break;
                }

                $uploadResult = uploadToS3($compressedImage, 'posts');

                if (file_exists($compressedImage['tmp_name'])) {
                    unlink($compressedImage['tmp_name']);
                }

                if (!$uploadResult['success']) {
                    $message = $uploadResult['error'];
                    break;
                }

                $imageKey = $uploadResult['key'];
                $uploadedImageKeys[] = $imageKey;

                $moderationResult = imagePassesModeration($rekognition, $s3Bucket, $imageKey);

                if (!$moderationResult['success'] || !$moderationResult['passed']) {
                    $message = $moderationResult['message'] ?: 'One of your images cannot be uploaded.';
                    break;
                }
            }

            if ($message !== '') {
                foreach ($uploadedImageKeys as $cleanupImageKey) {
                    delete_s3_object_if_exists($s3, $s3Bucket, $cleanupImageKey);
                }

                $uploadedImageKeys = [];
            }
        }

        if ($message === '') {
            try {
                $pdo->beginTransaction();

                $primaryImageKey = $uploadedImageKeys[0] ?? null;

                $stmt = $pdo->prepare("
                    INSERT INTO posts (
                        user_id,
                        tag,
                        body,
                        image_key,
                        created_at
                    )
                    VALUES (
                        :user_id,
                        :tag,
                        :body,
                        :image_key,
                        NOW()
                    )
                ");

                $stmt->execute([
                    ':user_id' => $user_id,
                    ':tag' => $tag !== '' ? $tag : null,
                    ':body' => $body,
                    ':image_key' => $primaryImageKey
                ]);

                $postId = $pdo->lastInsertId();

                if (!empty($uploadedImageKeys)) {
                    $imageStmt = $pdo->prepare("
                        INSERT INTO post_images (
                            post_id,
                            image_key,
                            sort_order,
                            created_at
                        )
                        VALUES (
                            :post_id,
                            :image_key,
                            :sort_order,
                            NOW()
                        )
                    ");

                    foreach ($uploadedImageKeys as $sortOrder => $uploadedImageKey) {
                        $imageStmt->execute([
                            ':post_id' => $postId,
                            ':image_key' => $uploadedImageKey,
                            ':sort_order' => $sortOrder
                        ]);
                    }
                }

                $pdo->commit();

                if (!headers_sent()) {
                    header("Location: /dashboard.php");
                    exit;
                }

                echo "<script>window.location.href = '/dashboard.php';</script>";
                exit;

            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                foreach ($uploadedImageKeys as $cleanupImageKey) {
                    delete_s3_object_if_exists($s3, $s3Bucket, $cleanupImageKey);
                }

                error_log('Post insert failed: ' . $e->getMessage());
                $message = "Post failed. Please try again.";
            }
        }
    }
}

if (empty($_SESSION['post_form_token'])) {
    $_SESSION['post_form_token'] = bin2hex(random_bytes(32));
}

$post_form_token = $_SESSION['post_form_token'];

$post_placeholder = "What's on your mind" . ($user_first_name !== "" ? " " . $user_first_name : "") . "?";

?>

<style>
.post-page-wrapper {
    width: 100%;
    max-width: 900px;
    margin: 32px auto 40px;
    padding: 0 20px;
    box-sizing: border-box;
}

.post-card {
    background: #ffffff;
    border-radius: 14px;
    padding: 26px;
    box-shadow: 0 4px 14px rgba(0,0,0,0.08);
}

.post-title {
    text-align: center;
    font-family: "Poppins", sans-serif;
    font-size: 24px;
    font-weight: 700;
    color: #111827;
    margin-bottom: 8px;
}

.post-subtitle {
    text-align: center;
    color: #6b7280;
    font-size: 14px;
    margin-bottom: 22px;
}

.post-textarea {
    width: 100%;
    min-height: 160px;
    padding: 14px;
    border: 1px solid #d1d5db;
    border-radius: 12px;
    resize: vertical;
    box-sizing: border-box;
    font-family: inherit;
    font-size: 15px;
    line-height: 1.6;
    outline: none;
}

.post-textarea:focus,
.post-tag-input:focus {
    border-color: #374151;
}

.post-tag-wrapper,
.post-image-wrapper {
    margin-top: 18px;
}

.post-tag-label,
.post-image-label {
    display: block;
    font-size: 13px;
    font-weight: 700;
    color: #4b5563;
    margin-bottom: 6px;
}

.post-tag-input {
    width: 100%;
    padding: 12px 14px;
    border: 1px solid #d1d5db;
    border-radius: 12px;
    box-sizing: border-box;
    font-size: 14px;
    outline: none;
}

.post-image-input {
    position: absolute;
    left: -9999px;
    width: 1px;
    height: 1px;
    opacity: 0;
}

.post-image-upload-box {
    width: 100%;
    min-height: 150px;
    border: 1px dashed #9ca3af;
    border-radius: 14px;
    background: #f9fafb;
    box-sizing: border-box;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 14px;
    cursor: pointer;
    transition: 0.2s ease;
    overflow: hidden;
}

.post-image-upload-box:hover {
    border-color: #374151;
    background: #ffffff;
    box-shadow: 0 0 0 4px rgba(17,24,39,0.06);
}

.post-image-upload-placeholder {
    text-align: center;
    color: #6b7280;
    font-size: 14px;
    font-weight: 700;
    line-height: 1.5;
}

.post-image-upload-placeholder strong {
    display: block;
    color: #111827;
    font-size: 15px;
    margin-bottom: 4px;
}

.post-image-preview-grid {
    display: none;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 10px;
    width: 100%;
}

.post-image-preview-item {
    position: relative;
    width: 100%;
    aspect-ratio: 1 / 1;
    border-radius: 14px;
    overflow: hidden;
    background: #f3f4f6;
    border: 1px solid #e5e7eb;
}

.post-image-preview-item img {
    width: 100%;
    height: 100%;
    display: block;
    object-fit: cover;
    object-position: center;
}

.post-image-remove-one {
    position: absolute;
    top: 7px;
    right: 7px;
    width: 26px;
    height: 26px;
    border: none;
    border-radius: 999px;
    background: rgba(17,24,39,0.82);
    color: #ffffff;
    font-size: 17px;
    font-weight: 900;
    line-height: 1;
    cursor: pointer;
}

.post-image-upload-box.has-preview {
    background: #ffffff;
    border-style: solid;
    border-color: #e5e7eb;
}

.post-image-upload-box.has-preview .post-image-upload-placeholder {
    display: none;
}

.post-image-upload-box.has-preview .post-image-preview-grid {
    display: grid;
}

.post-image-preview-actions {
    display: none;
    align-items: center;
    justify-content: center;
    gap: 10px;
    margin-top: 10px;
}

.post-image-preview-actions.show {
    display: flex;
}

.post-image-small-button {
    border: 1px solid #d1d5db;
    background: #ffffff;
    color: #374151;
    border-radius: 999px;
    padding: 7px 14px;
    font-size: 12px;
    font-weight: 800;
    cursor: pointer;
}

.post-image-small-button:hover {
    background: #f3f4f6;
    color: #111827;
}

.post-tag-helper,
.post-image-helper {
    margin-top: 6px;
    font-size: 12px;
    color: #6b7280;
}

.character-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    margin-top: 10px;
    font-size: 13px;
}

.character-count {
    color: #6b7280;
}

.character-warning {
    color: #dc2626;
    font-weight: 700;
    display: none;
}

.post-actions {
    display: flex;
    justify-content: center;
    gap: 10px;
    margin-top: 22px;
    flex-wrap: wrap;
}

.post-submit-button,
.post-cancel-button {
    display: inline-block;
    border: none;
    padding: 10px 26px;
    border-radius: 999px;
    font-size: 14px;
    font-weight: 700;
    text-decoration: none;
    cursor: pointer;
}

.post-submit-button {
    background: #374151;
    color: #ffffff;
}

.post-submit-button:hover {
    background: #111827;
}

.post-submit-button:disabled {
    background: #9ca3af;
    cursor: not-allowed;
    opacity: 0.85;
}

.post-cancel-button {
    background: #ffffff;
    color: #374151;
    border: 1px solid #d1d5db;
}

.post-cancel-button:hover {
    background: #f3f4f6;
}

.post-error {
    color: #b91c1c;
    background: #fee2e2;
    padding: 10px;
    border-radius: 8px;
    text-align: center;
    margin-bottom: 15px;
}

@media (max-width: 700px) {
    .post-page-wrapper {
        margin: 18px auto 30px;
        padding: 0 10px;
    }

    .post-card {
        padding: 18px;
        border-radius: 10px;
    }

    .post-title {
        font-size: 21px;
    }

    .post-actions {
        flex-direction: row;
        flex-wrap: nowrap;
    }

    .post-submit-button,
    .post-cancel-button {
        flex: 1;
        text-align: center;
        padding: 10px 12px;
    }

    .post-image-preview-grid {
        gap: 7px;
    }

    .post-image-remove-one {
        width: 24px;
        height: 24px;
        font-size: 16px;
    }
}
</style>

<div class="post-page-wrapper">
    <div class="post-card">

        <div class="post-title">Create a Post</div>

        <div class="post-subtitle">
            Share something in 500 characters or less. You can also add up to 3 optional photos.
        </div>

        <?php if (!empty($message)): ?>
            <div class="post-error">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <form method="POST" id="postForm" enctype="multipart/form-data">
            <input
                type="hidden"
                name="post_form_token"
                value="<?= htmlspecialchars($post_form_token) ?>"
            >

            <textarea
                name="post_body"
                id="postBody"
                class="post-textarea"
                maxlength="600"
                placeholder="<?= htmlspecialchars($post_placeholder) ?>"
            ><?= htmlspecialchars($_POST['post_body'] ?? '') ?></textarea>

            <div class="character-row">
                <span class="character-warning" id="characterWarning">
                    Post is over 500 characters.
                </span>

                <span class="character-count" id="characterCount">
                    0 / 500
                </span>
            </div>

            <div class="post-tag-wrapper">
                <label class="post-tag-label">Tag</label>

                <input
                    type="text"
                    name="tag"
                    id="tagInput"
                    class="post-tag-input"
                    maxlength="30"
                    placeholder="Example: Sports, Technology, Music"
                    value="<?= htmlspecialchars($_POST['tag'] ?? '') ?>"
                >

                <div class="character-row">
                    <span class="post-tag-helper">
                        Tags must be 30 characters or less.
                    </span>

                    <span class="character-count" id="tagCharacterCount">
                        0 / 30
                    </span>
                </div>
            </div>

            <div class="post-image-wrapper">
                <label class="post-image-label" for="postImages">
                    Optional Photos
                </label>

                <input
                    type="file"
                    name="post_images[]"
                    id="postImages"
                    class="post-image-input"
                    accept="image/jpeg,image/png,image/webp"
                    multiple
                >

                <label for="postImages" class="post-image-upload-box" id="postImageUploadBox">
                    <div class="post-image-upload-placeholder">
                        <strong>Choose up to 3 photos</strong>
                        Tap or click to preview your images before posting.
                    </div>

                    <div class="post-image-preview-grid" id="postImagePreviewGrid"></div>
                </label>

                <div class="post-image-preview-actions" id="postImagePreviewActions">
                    <button type="button" class="post-image-small-button" id="changeImageButton">
                        Change Photos
                    </button>

                    <button type="button" class="post-image-small-button" id="removeImageButton">
                        Remove Photos
                    </button>
                </div>

                <div class="post-image-helper">
                    Upload up to 3 JPG, PNG, or WEBP photos. Max size: 10MB each. Images are compressed and checked before posting.
                </div>
            </div>

            <div class="post-actions">
                <button
                    type="submit"
                    class="post-submit-button"
                    id="postSubmitButton"
                >
                    Post
                </button>

                <a href="dashboard.php" class="post-cancel-button">
                    Cancel
                </a>
            </div>

        </form>

    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const postBody = document.getElementById("postBody");
    const tagInput = document.getElementById("tagInput");
    const postImages = document.getElementById("postImages");
    const postForm = document.getElementById("postForm");
    const postImageUploadBox = document.getElementById("postImageUploadBox");
    const postImagePreviewGrid = document.getElementById("postImagePreviewGrid");
    const postImagePreviewActions = document.getElementById("postImagePreviewActions");
    const changeImageButton = document.getElementById("changeImageButton");
    const removeImageButton = document.getElementById("removeImageButton");

    const characterCount = document.getElementById("characterCount");
    const tagCharacterCount = document.getElementById("tagCharacterCount");

    const characterWarning = document.getElementById("characterWarning");
    const submitButton = document.getElementById("postSubmitButton");

    function updateCharacterCount() {
        const bodyLength = postBody.value.length;
        const tagLength = tagInput.value.length;

        characterCount.textContent = bodyLength + " / 500";
        tagCharacterCount.textContent = tagLength + " / 30";

        if (bodyLength > 500 || tagLength > 30) {
            characterWarning.style.display = bodyLength > 500 ? "inline" : "none";
            characterCount.style.color = bodyLength > 500 ? "#dc2626" : "#6b7280";
            tagCharacterCount.style.color = tagLength > 30 ? "#dc2626" : "#6b7280";
            submitButton.disabled = true;
        } else {
            characterWarning.style.display = "none";
            characterCount.style.color = "#6b7280";
            tagCharacterCount.style.color = "#6b7280";
            submitButton.disabled = false;
        }
    }

    function syncInputFiles(files) {
        if (!postImages) {
            return;
        }

        const dataTransfer = new DataTransfer();

        files.forEach(function (file) {
            dataTransfer.items.add(file);
        });

        postImages.files = dataTransfer.files;
    }

    function clearImagePreview() {
        if (postImagePreviewGrid) {
            postImagePreviewGrid.innerHTML = "";
        }

        if (postImageUploadBox) {
            postImageUploadBox.classList.remove("has-preview");
        }

        if (postImagePreviewActions) {
            postImagePreviewActions.classList.remove("show");
        }
    }

    function renderImagePreviews(files) {
        clearImagePreview();

        if (!files.length || !postImagePreviewGrid) {
            return;
        }

        files.forEach(function (file, index) {
            const item = document.createElement("div");
            item.className = "post-image-preview-item";

            const image = document.createElement("img");
            image.alt = "Selected image preview";

            const removeButton = document.createElement("button");
            removeButton.type = "button";
            removeButton.className = "post-image-remove-one";
            removeButton.textContent = "×";
            removeButton.setAttribute("aria-label", "Remove photo");

            const previewUrl = URL.createObjectURL(file);
            image.src = previewUrl;

            image.onload = function () {
                URL.revokeObjectURL(previewUrl);
            };

            removeButton.addEventListener("click", function (event) {
                event.preventDefault();
                event.stopPropagation();

                const currentFiles = Array.from(postImages.files || []);
                currentFiles.splice(index, 1);
                syncInputFiles(currentFiles);
                renderImagePreviews(currentFiles);
            });

            item.appendChild(image);
            item.appendChild(removeButton);
            postImagePreviewGrid.appendChild(item);
        });

        if (postImageUploadBox) {
            postImageUploadBox.classList.add("has-preview");
        }

        if (postImagePreviewActions) {
            postImagePreviewActions.classList.add("show");
        }
    }

    if (postImages) {
        postImages.addEventListener("change", function () {
            const selectedFiles = Array.from(postImages.files || []);

            clearImagePreview();

            if (!selectedFiles.length) {
                return;
            }

            const allowedTypes = ["image/jpeg", "image/png", "image/webp"];
            const maxSize = 10 * 1024 * 1024;

            if (selectedFiles.length > 3) {
                alert("You can upload up to 3 photos per post.");
                postImages.value = "";
                return;
            }

            for (const file of selectedFiles) {
                if (!allowedTypes.includes(file.type)) {
                    alert("Only JPG, PNG, and WEBP images are allowed.");
                    postImages.value = "";
                    return;
                }

                if (file.size > maxSize) {
                    alert("Each image must be 10MB or smaller.");
                    postImages.value = "";
                    return;
                }
            }

            renderImagePreviews(selectedFiles);
        });
    }

    if (changeImageButton && postImages) {
        changeImageButton.addEventListener("click", function () {
            postImages.click();
        });
    }

    if (removeImageButton && postImages) {
        removeImageButton.addEventListener("click", function () {
            postImages.value = "";
            clearImagePreview();
        });
    }

    postBody.addEventListener("input", updateCharacterCount);
    tagInput.addEventListener("input", updateCharacterCount);

    if (postForm) {
        postForm.addEventListener("submit", function (event) {
            const bodyLength = postBody.value.trim().length;
            const tagLength = tagInput.value.trim().length;

            if (bodyLength === 0 || bodyLength > 500 || tagLength > 30) {
                updateCharacterCount();
                return;
            }

            if (submitButton.disabled) {
                event.preventDefault();
                return;
            }

            submitButton.disabled = true;
            submitButton.textContent = "Posting...";
        });
    }

    updateCharacterCount();
});
</script>
