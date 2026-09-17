<?php

if (!function_exists('whusupSafeDeleteRedirect')) {
    function whusupSafeDeleteRedirect(?string $requestedUrl): string
    {
        $fallback = '/dashboard.php?filter=all&sort=recent';
        $requestedUrl = trim((string) $requestedUrl);

        if ($requestedUrl === '') {
            return $fallback;
        }

        $requestedUrl = html_entity_decode($requestedUrl, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        if ($requestedUrl[0] !== '/' || str_starts_with($requestedUrl, '//')) {
            return $fallback;
        }

        $path = parse_url($requestedUrl, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            return $fallback;
        }

        $normalizedPath = strtolower($path);
        $blockedFragments = [
            '/ajax/',
            '/public/ajax/',
            '/ajax/load_feed.php',
            '/recent_posts_scripts.php',
            '/public/recent_posts_scripts.php'
        ];

        foreach ($blockedFragments as $blockedFragment) {
            if (str_contains($normalizedPath, $blockedFragment)) {
                return $fallback;
            }
        }

        return $requestedUrl;
    }
}

$redirect_url = whusupSafeDeleteRedirect($redirect_url ?? null);

// DELETE POST + ATTACHED S3 IMAGES
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['delete_post_id'])
    && $is_logged_in
) {
    $post_id = (int) $_POST['delete_post_id'];

    if ($post_id <= 0) {
        $message = 'Invalid post ID.';
        return;
    }

    try {
        $stmt = $pdo->prepare("
            SELECT id, image_key
            FROM posts
            WHERE id = :post_id
              AND user_id = :user_id
            LIMIT 1
        ");

        $stmt->execute([
            ':post_id' => $post_id,
            ':user_id' => $user_id
        ]);

        $postToDelete = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$postToDelete) {
            $message = 'Post not found or you do not have permission to delete it.';
            return;
        }

        $imageKeysToDelete = [];

        try {
            $imageStmt = $pdo->prepare("
                SELECT image_key
                FROM post_images
                WHERE post_id = :post_id
            ");

            $imageStmt->execute([
                ':post_id' => $postToDelete['id']
            ]);

            $imageKeysToDelete = $imageStmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (PDOException $e) {
            error_log('Could not load post images for delete: ' . $e->getMessage());
        }

        if (empty($imageKeysToDelete) && !empty($postToDelete['image_key'])) {
            $imageKeysToDelete[] = $postToDelete['image_key'];
        }

        $imageKeysToDelete = array_values(array_unique(array_filter(
            $imageKeysToDelete,
            static fn ($key) => is_string($key) && trim($key) !== ''
        )));

        $deleteStmt = $pdo->prepare("
            DELETE FROM posts
            WHERE id = :post_id
              AND user_id = :user_id
        ");

        $deleteStmt->execute([
            ':post_id' => $post_id,
            ':user_id' => $user_id
        ]);

        if ($deleteStmt->rowCount() < 1) {
            $message = 'The post could not be deleted.';
            return;
        }

        foreach ($imageKeysToDelete as $deleteImageKey) {
            try {
                $s3->deleteObject([
                    'Bucket' => $s3Bucket,
                    'Key' => $deleteImageKey
                ]);
            } catch (Throwable $e) {
                error_log(
                    'S3 image delete failed for key "' .
                    $deleteImageKey .
                    '": ' .
                    $e->getMessage()
                );
            }
        }

        redirectToPosts($redirect_url);
        exit;

    } catch (PDOException $e) {
        error_log('Delete post failed: ' . $e->getMessage());
        $message = 'Delete post failed. Please try again.';
    }
}
