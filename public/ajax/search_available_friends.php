<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

require_once __DIR__ . '/../../config/database.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Not logged in.'
    ]);
    exit;
}

$user_id = $_SESSION['user_id'];

$search = trim($_GET['friend_search'] ?? '');
$offset = max(0, (int)($_GET['offset'] ?? 0));
$limit = 20;

try {

    $search_sql = '';
    $params = [
        ':current_user_id' => $user_id,
        ':friendship_user_id' => $user_id,
        ':limit' => $limit,
        ':offset' => $offset
    ];

    if ($search !== '') {
        $search_sql = 'AND users.name LIKE :search';
        $params[':search'] = '%' . $search . '%';
    }

    $sql = "
        SELECT
            users.id,
            users.name,

            user_profiles.profile_picture_url,

            (
                SELECT COUNT(*)
                FROM friendships f
                JOIN users follower_users
                    ON follower_users.id = f.user_id
                WHERE f.friend_id = users.id
                AND f.status = 'accepted'
                AND follower_users.email_verified = 1
            ) AS follower_count

        FROM users

        LEFT JOIN user_profiles
            ON user_profiles.user_id = users.id

        WHERE users.id != :current_user_id

        AND users.email_verified = 1

        AND NOT EXISTS (
            SELECT 1
            FROM friendships
            WHERE friendships.user_id = :friendship_user_id
            AND friendships.friend_id = users.id
        )

        $search_sql

        ORDER BY users.name ASC

        LIMIT :limit OFFSET :offset
    ";

    $stmt = $pdo->prepare($sql);

    foreach ($params as $key => $value) {

        if ($key === ':limit' || $key === ':offset') {
            $stmt->bindValue($key, (int)$value, PDO::PARAM_INT);
        } else {
            $stmt->bindValue($key, $value);
        }
    }

    $stmt->execute();

    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'users' => $users,
        'count' => count($users)
    ]);

} catch (PDOException $e) {

    error_log('Available friends search failed: ' . $e->getMessage());

    echo json_encode([
        'success' => false,
        'message' => 'Could not load available users.'
    ]);
}

exit;
