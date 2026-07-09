<?php

if (!isset($redirect_url) || $redirect_url === '') {
    $redirect_url = '/';
}

// DELETE POST + ATTACHED S3 IMAGE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_post_id']) && $is_logged_in) {
    $post_id = (int) $_POST['delete_post_id'];

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

        if ($postToDelete) {
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

            foreach ($imageKeysToDelete as $deleteImageKey) {
                if (empty($deleteImageKey)) {
                    continue;
                }

                try {
                    $s3->deleteObject([
                        'Bucket' => $s3Bucket,
                        'Key' => $deleteImageKey
                    ]);
                } catch (Exception $e) {
                    error_log('S3 image delete failed: ' . $e->getMessage());
                }
            }

            $stmt = $pdo->prepare("
                DELETE FROM posts
                WHERE id = :post_id
                AND user_id = :user_id
            ");

            $stmt->execute([
                ':post_id' => $post_id,
                ':user_id' => $user_id
            ]);
        }

        redirectToPosts($redirect_url);

    } catch (PDOException $e) {
        $message = "Delete post failed: " . $e->getMessage();
    }
}
