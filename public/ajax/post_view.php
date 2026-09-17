<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

require_once __DIR__ . '/../../config/database.php';

/*
|--------------------------------------------------------------------------
| Response helper
| Note: ChatGPT recommends a cleanup of post views older than 30 days - revisit this 
|--------------------------------------------------------------------------
*/

function sendViewResponse(
    bool $success,
    array $data = [],
    int $statusCode = 200
): never {
    http_response_code($statusCode);

    echo json_encode(
        array_merge(
            ['success' => $success],
            $data
        ),
        JSON_UNESCAPED_SLASHES
        | JSON_UNESCAPED_UNICODE
    );

    exit;
}

/*
|--------------------------------------------------------------------------
| UUID helpers
|--------------------------------------------------------------------------
*/

function isValidUuid(string $uuid): bool
{
    return preg_match(
        '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
        $uuid
    ) === 1;
}

function generateUuidV4(): string
{
    $bytes = random_bytes(16);

    /*
     * Set UUID version 4 and RFC 4122 variant bits.
     */
    $bytes[6] = chr(
        (ord($bytes[6]) & 0x0f) | 0x40
    );

    $bytes[8] = chr(
        (ord($bytes[8]) & 0x3f) | 0x80
    );

    $hex = bin2hex($bytes);

    return sprintf(
        '%s-%s-%s-%s-%s',
        substr($hex, 0, 8),
        substr($hex, 8, 4),
        substr($hex, 12, 4),
        substr($hex, 16, 4),
        substr($hex, 20, 12)
    );
}

/*
|--------------------------------------------------------------------------
| Guest visitor cookie
|--------------------------------------------------------------------------
*/

function getGuestVisitorId(): string
{
    $cookieName = 'whusup_visitor_id';

    $existingVisitorId = trim(
        (string) ($_COOKIE[$cookieName] ?? '')
    );

    if (
        $existingVisitorId !== ''
        && isValidUuid($existingVisitorId)
    ) {
        return strtolower($existingVisitorId);
    }

    $visitorId = generateUuidV4();

    /*
     * The cookie is server-managed. JavaScript does not need to access it.
     */
    setcookie(
        $cookieName,
        $visitorId,
        [
            'expires' => time() + (365 * 24 * 60 * 60),
            'path' => '/',
            'secure' => !empty($_SERVER['HTTPS'])
                && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,
            'samesite' => 'Lax',
        ]
    );

    $_COOKIE[$cookieName] = $visitorId;

    return $visitorId;
}

/*
|--------------------------------------------------------------------------
| Request validation
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendViewResponse(
        false,
        ['message' => 'Method not allowed.'],
        405
    );
}

$contentType = strtolower(
    trim((string) ($_SERVER['CONTENT_TYPE'] ?? ''))
);

$requestData = $_POST;

if (str_contains($contentType, 'application/json')) {
    $rawBody = file_get_contents('php://input');

    $decodedBody = is_string($rawBody)
        ? json_decode($rawBody, true)
        : null;

    if (is_array($decodedBody)) {
        $requestData = $decodedBody;
    }
}

$postId = isset($requestData['post_id'])
    ? (int) $requestData['post_id']
    : 0;

if ($postId <= 0) {
    sendViewResponse(
        false,
        ['message' => 'Invalid post ID.'],
        422
    );
}

$userId = $_SESSION['user_id'] ?? null;
$isLoggedIn = is_string($userId) && $userId !== '';

if ($isLoggedIn && !isValidUuid($userId)) {
    sendViewResponse(
        false,
        ['message' => 'Invalid session user ID.'],
        401
    );
}

$visitorId = $isLoggedIn
    ? null
    : getGuestVisitorId();

/*
|--------------------------------------------------------------------------
| Record the view
|--------------------------------------------------------------------------
|
| A post row lock prevents simultaneous requests from counting the same
| viewer twice before either transaction completes.
|
| A view is counted at most once per viewer, per post, during any rolling
| 24-hour period.
|
*/

try {
    $pdo->beginTransaction();

    /*
     * Lock this post briefly while the duplicate-view check and counter
     * update are performed.
     */
    $postStmt = $pdo->prepare("
        SELECT id, user_id, view_count
        FROM posts
        WHERE id = :post_id
        LIMIT 1
        FOR UPDATE
    ");

    $postStmt->execute([
        ':post_id' => $postId,
    ]);

    $post = $postStmt->fetch(PDO::FETCH_ASSOC);

    if (!$post) {
        $pdo->rollBack();

        sendViewResponse(
            false,
            ['message' => 'Post not found.'],
            404
        );
    }

    /*
     * Recommended behavior: do not count a logged-in author viewing their
     * own post. Remove this block later if self-views should count.
     */
    if (
        $isLoggedIn
        && isset($post['user_id'])
        && (string) $post['user_id'] === $userId
    ) {
        $pdo->commit();

        sendViewResponse(
            true,
            [
                'counted' => false,
                'reason' => 'owner_view',
                'view_count' => (int) $post['view_count'],
            ]
        );
    }

    if ($isLoggedIn) {
        $recentViewStmt = $pdo->prepare("
            SELECT id
            FROM post_views
            WHERE post_id = :post_id
              AND user_id = :user_id
              AND viewed_at >= NOW() - INTERVAL 24 HOUR
            LIMIT 1
        ");

        $recentViewStmt->execute([
            ':post_id' => $postId,
            ':user_id' => $userId,
        ]);
    } else {
        $recentViewStmt = $pdo->prepare("
            SELECT id
            FROM post_views
            WHERE post_id = :post_id
              AND visitor_id = :visitor_id
              AND viewed_at >= NOW() - INTERVAL 24 HOUR
            LIMIT 1
        ");

        $recentViewStmt->execute([
            ':post_id' => $postId,
            ':visitor_id' => $visitorId,
        ]);
    }

    $alreadyCounted = (bool) $recentViewStmt->fetchColumn();

    if ($alreadyCounted) {
        $pdo->commit();

        sendViewResponse(
            true,
            [
                'counted' => false,
                'reason' => 'recent_view',
                'view_count' => (int) $post['view_count'],
            ]
        );
    }

    $insertViewStmt = $pdo->prepare("
        INSERT INTO post_views (
            post_id,
            user_id,
            visitor_id,
            viewed_at
        ) VALUES (
            :post_id,
            :user_id,
            :visitor_id,
            NOW()
        )
    ");

    $insertViewStmt->bindValue(
        ':post_id',
        $postId,
        PDO::PARAM_INT
    );

    if ($isLoggedIn) {
        $insertViewStmt->bindValue(
            ':user_id',
            $userId,
            PDO::PARAM_STR
        );

        $insertViewStmt->bindValue(
            ':visitor_id',
            null,
            PDO::PARAM_NULL
        );
    } else {
        $insertViewStmt->bindValue(
            ':user_id',
            null,
            PDO::PARAM_NULL
        );

        $insertViewStmt->bindValue(
            ':visitor_id',
            $visitorId,
            PDO::PARAM_STR
        );
    }

    $insertViewStmt->execute();

    $updateCountStmt = $pdo->prepare("
        UPDATE posts
        SET view_count = view_count + 1
        WHERE id = :post_id
    ");

    $updateCountStmt->execute([
        ':post_id' => $postId,
    ]);

    $newViewCount = (int) $post['view_count'] + 1;

    $pdo->commit();

    sendViewResponse(
        true,
        [
            'counted' => true,
            'view_count' => $newViewCount,
        ]
    );

} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log(
        'Post view recording failed: '
        . $exception->getMessage()
    );

    sendViewResponse(
        false,
        ['message' => 'Unable to record this view.'],
        500
    );
}