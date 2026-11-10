<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/posts/post_helpers.php';
require_once __DIR__ . '/../../includes/email/email_helpers.php';

$user_id = $_SESSION['user_id'] ?? null;

if (empty($user_id)) {
    echo json_encode([
        'success' => false,
        'message' => 'You must be logged in to comment.'
    ]);
    exit;
}

$post_id = (int) ($_POST['post_id'] ?? 0);

$comment_body = trim(
    (string) ($_POST['comment_body'] ?? '')
);

$parent_comment_id =
    isset($_POST['parent_comment_id'])
    && $_POST['parent_comment_id'] !== ''
        ? (int) $_POST['parent_comment_id']
        : null;

if ($post_id <= 0 || $comment_body === '') {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid comment.'
    ]);
    exit;
}


/*
|--------------------------------------------------------------------------
| Notification state
|--------------------------------------------------------------------------
|
| Email is sent only after the database transaction commits successfully.
|
*/

$notificationType = null; // 'main_comment' or 'first_reply'

$post = null;
$commenter = null;
$recipient = null;
$comment_id = null;


try {

    /*
    |--------------------------------------------------------------------------
    | Begin transaction
    |--------------------------------------------------------------------------
    |
    | Locking the post row ensures that two comment requests for the same
    | previously-uncommented post cannot both decide that they are the
    | first comment.
    |
    */

    $pdo->beginTransaction();


    /*
    |--------------------------------------------------------------------------
    | Load and lock the post
    |--------------------------------------------------------------------------
    |
    | We also load the information needed for a possible first-comment
    | notification while the transaction is active.
    |
    */

    $stmt = $pdo->prepare("
        SELECT
            p.id,
            p.user_id AS post_owner_id,
            p.title,
            p.body,
            p.tag,

            u.email,

            up.display_name AS owner_display_name,

            COALESCE(
                up.allow_email_notifications,
                0
            ) AS allow_email_notifications

        FROM posts p

        INNER JOIN users u
            ON u.id = p.user_id

        LEFT JOIN user_profiles up
            ON up.user_id = p.user_id

        WHERE p.id = :post_id

        LIMIT 1

        FOR UPDATE
    ");

    $stmt->execute([
        ':post_id' => $post_id
    ]);

    $post = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$post) {
        $pdo->rollBack();

        echo json_encode([
            'success' => false,
            'message' => 'Post not found.'
        ]);

        exit;
    }


    /*
     * The post row is locked for this transaction, serializing comment
     * creation on the same post and preventing duplicate first-reply emails.
     */
    $parentComment = null;
    $mainCommentCountBefore = null;
    $parentAlreadyHasReplies = false;

    if ($parent_comment_id !== null) {
        $stmt = $pdo->prepare("
            SELECT c.id, c.user_id, c.parent_comment_id,
                   u.email, up.display_name,
                   COALESCE(up.allow_email_notifications, 0) AS allow_email_notifications
            FROM post_comments c
            JOIN users u ON u.id = c.user_id
            LEFT JOIN user_profiles up ON up.user_id = c.user_id
            WHERE c.id = :parent_comment_id AND c.post_id = :post_id
            LIMIT 1
        ");
        $stmt->execute([
            ':parent_comment_id' => $parent_comment_id,
            ':post_id' => $post_id
        ]);
        $parentComment = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$parentComment) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'Parent comment not found.']);
            exit;
        }

        // Only a direct reply to a MAIN comment can trigger an email.
        if ($parentComment['parent_comment_id'] === null) {
            $stmt = $pdo->prepare("
                SELECT EXISTS(
                    SELECT 1 FROM post_comments
                    WHERE parent_comment_id = :parent_comment_id
                    LIMIT 1
                )
            ");
            $stmt->execute([':parent_comment_id' => $parent_comment_id]);
            $parentAlreadyHasReplies = (bool) $stmt->fetchColumn();
        }
    } else {
        // Count only top-level comments, not replies. Count self-comments too.
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM post_comments
            WHERE post_id = :post_id AND parent_comment_id IS NULL
        ");
        $stmt->execute([':post_id' => $post_id]);
        $mainCommentCountBefore = (int) $stmt->fetchColumn();
    }


    /*
    |--------------------------------------------------------------------------
    | Insert the new comment
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare("
        INSERT INTO post_comments
            (
                post_id,
                user_id,
                parent_comment_id,
                comment_body
            )
        VALUES
            (
                :post_id,
                :user_id,
                :parent_comment_id,
                :comment_body
            )
    ");

    $stmt->execute([
        ':post_id' => $post_id,
        ':user_id' => $user_id,
        ':parent_comment_id' => $parent_comment_id,
        ':comment_body' => $comment_body
    ]);

    $comment_id = $pdo->lastInsertId();


    /*
    |--------------------------------------------------------------------------
    | Load commenter's public Display Name
    |--------------------------------------------------------------------------
    |
    | Public-facing comments and notification emails should use
    | user_profiles.display_name, not the private/account name in users.name.
    |
    */

    $stmt = $pdo->prepare("
        SELECT display_name, profile_picture_url
        FROM user_profiles
        WHERE user_id = :user_id
        LIMIT 1
    ");

    $stmt->execute([
        ':user_id' => $user_id
    ]);

    $commenter = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$commenter) {
        $commenter = [
            'display_name' => 'User'
        ];
    }


    /*
     * Select notification eligibility while the post lock is held.
     * An email failure never changes the comment insert result.
     */
    $commenterId = (string) $user_id;

    if ($parent_comment_id === null) {
        if (
            $mainCommentCountBefore < 3
            && (string) $post['post_owner_id'] !== $commenterId
            && (int) $post['allow_email_notifications'] === 1
            && filter_var((string) $post['email'], FILTER_VALIDATE_EMAIL)
        ) {
            $notificationType = 'main_comment';
            $recipient = [
                'email' => trim((string) $post['email']),
                'display_name' => trim((string) ($post['owner_display_name'] ?? ''))
            ];
        }
    } elseif (
        $parentComment !== null
        && $parentComment['parent_comment_id'] === null
        && !$parentAlreadyHasReplies
        && (string) $parentComment['user_id'] !== $commenterId
        && (int) $parentComment['allow_email_notifications'] === 1
        && filter_var((string) $parentComment['email'], FILTER_VALIDATE_EMAIL)
    ) {
        $notificationType = 'first_reply';
        $recipient = [
            'email' => trim((string) $parentComment['email']),
            'display_name' => trim((string) ($parentComment['display_name'] ?? ''))
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | Commit comment
    |--------------------------------------------------------------------------
    |
    | From this point forward the comment is permanent. Email delivery is
    | deliberately kept outside this transaction.
    |
    */

    $pdo->commit();


} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log(
        'Add comment failed: '
        . $e->getMessage()
    );

    echo json_encode([
        'success' => false,
        'message' => 'Unable to add your comment right now.'
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| Send eligible comment notification
|--------------------------------------------------------------------------
|
| Notification delivery happens only after the comment transaction has
| committed. An AWS/SES failure must never make a successfully saved
| comment appear to have failed.
|
*/

if (
    $notificationType !== null
    && $recipient !== null
    && $commenter !== null
    && $post !== null
) {
    try {

        /*
         * Load AWS only when an email actually needs to be sent.
         *
         * This avoids creating AWS clients on ordinary comments that do
         * not qualify for a notification.
         */

        $awsConfig = require __DIR__ . '/../../config/aws.php';

        $ses = $awsConfig['ses'] ?? null;

        if (!$ses) {
            throw new RuntimeException(
                'AWS SES client is unavailable.'
            );
        }


        /*
         * Build the post array expected by the reusable email helper.
         */

        $emailPost = [
            'id' => (int) $post['id'],
            'title' => $post['title'] ?? null,
            'body' => $post['body'] ?? '',
            'tag' => $post['tag'] ?? ''
        ];


        /*
         * Send the notification.
         */

        sendCommentActivityNotification(
            $ses,
            $notificationType,
            $recipient,
            $commenter,
            $emailPost,
            [
                'comment_body' => $comment_body
            ]
        );

    } catch (Throwable $e) {

        /*
         * The comment has already been saved successfully.
         *
         * Log notification failures for investigation, but never return
         * an error to the commenter.
         */

        error_log(
            'Comment notification email failed for post '
            . $post_id
            . ': '
            . $e->getMessage()
        );
    }
}


/*
|--------------------------------------------------------------------------
| Return AJAX response
|--------------------------------------------------------------------------
*/

// Match the existing comment tree avatar source for immediately rendered comments.
$commentProfileImageUrl = null;
$profilePictureKey = trim((string) ($commenter['profile_picture_url'] ?? ''));
if ($profilePictureKey !== '') {
    try {
        // The same media URL helper used when PHP renders the comment tree.
        $awsConfig = $awsConfig ?? require __DIR__ . '/../../config/aws.php';
        $commentProfileImageUrl = getS3ImageUrl($profilePictureKey);
    } catch (Throwable $e) {
        error_log('Comment avatar URL failed: ' . $e->getMessage());
    }
}

echo json_encode([
    'success' => true,
    'comment_id' => $comment_id,
    'post_id' => $post_id,
    'parent_comment_id' => $parent_comment_id,

    'comment_body' => htmlspecialchars(
        $comment_body,
        ENT_QUOTES,
        'UTF-8'
    ),

    'name' => htmlspecialchars(
        $commenter['display_name'] ?? 'User',
        ENT_QUOTES,
        'UTF-8'
    ),

    'profile_image_url' => $commentProfileImageUrl,
    'created_at' => 'Just now'
]);

exit;