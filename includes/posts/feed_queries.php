<?php

// SORT + FILTER SQL
$where_parts = [];
$params = [
    ':user_id' => $user_id
];

if ($feed_filter === 'following' && $is_logged_in) {
    $where_parts[] = "
        EXISTS (
            SELECT 1
            FROM friendships
            WHERE friendships.user_id = :following_user_id
            AND friendships.friend_id = posts.user_id
            AND friendships.status = 'accepted'
        )
    ";

    $params[':following_user_id'] = $user_id;
}

if ($feed_filter === 'my_posts' && $is_logged_in) {
    $where_parts[] = "posts.user_id = :my_posts_user_id";
    $params[':my_posts_user_id'] = $user_id;
}

if ($tag_search !== '') {
    $where_parts[] = "posts.tag LIKE :tag_search";
    $params[':tag_search'] = '%' . $tag_search . '%';
}

$where_sql = "";

if (!empty($where_parts)) {
    $where_sql = "WHERE " . implode(" AND ", $where_parts);
}

if ($sort === 'popular') {
    $order_sql = "
        ORDER BY popularity_score DESC, latest_activity_at DESC
    ";
} else {
    $order_sql = "
        ORDER BY latest_activity_at DESC
    ";
}

// FETCH POSTS
try {
    $stmt = $pdo->prepare("
        SELECT 
            posts.id,
            posts.user_id,
            posts.body,
            posts.tag,
            posts.image_key,
            posts.created_at,
            users.name,
            user_profiles.profile_picture_url,

            COUNT(DISTINCT post_likes.id) AS like_count,
            COUNT(DISTINCT post_comments.id) AS comment_count,

            (
                COUNT(DISTINCT post_likes.id) + 
                COUNT(DISTINCT post_comments.id)
            ) AS popularity_score,

            GREATEST(
                posts.created_at,
                COALESCE(MAX(post_comments.created_at), posts.created_at)
            ) AS latest_activity_at,

            MAX(
                CASE 
                    WHEN post_likes.user_id = :user_id
                    THEN 1
                    ELSE 0
                END
            ) AS user_liked

        FROM posts

        JOIN users ON users.id = posts.user_id

        LEFT JOIN user_profiles ON user_profiles.user_id = posts.user_id

        LEFT JOIN post_likes ON post_likes.post_id = posts.id

        LEFT JOIN post_comments ON post_comments.post_id = posts.id

        $where_sql

        GROUP BY 
            posts.id,
            posts.user_id,
            posts.body,
            posts.tag,
            posts.image_key,
            posts.created_at,
            users.name,
            user_profiles.profile_picture_url

        $order_sql

        LIMIT :posts_limit OFFSET :posts_offset
    ");

    foreach ($params as $key => $value) {
        if ($value === null) {
            $stmt->bindValue($key, null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue($key, $value, PDO::PARAM_STR);
        }
    }

    $stmt->bindValue(':posts_limit', $posts_fetch_limit, PDO::PARAM_INT);
    $stmt->bindValue(':posts_offset', $posts_offset, PDO::PARAM_INT);
    $stmt->execute();

    $recent_posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $recent_posts_has_more = count($recent_posts) > $posts_per_page;

    if ($recent_posts_has_more) {
        $recent_posts = array_slice($recent_posts, 0, $posts_per_page);
    }

    $recent_posts_next_offset = $posts_offset + count($recent_posts);

} catch (PDOException $e) {
    $recent_posts = [];
    $message = "Could not load recent posts: " . $e->getMessage();
}

// FETCH COMMENTS FOR THESE POSTS
$comments_by_post = [];

if (!empty($recent_posts)) {
    $post_ids = array_column($recent_posts, 'id');
    $placeholders = implode(',', array_fill(0, count($post_ids), '?'));

    try {
        $sql = "
            SELECT 
                post_comments.id,
                post_comments.post_id,
                post_comments.user_id,
                post_comments.parent_comment_id,
                post_comments.comment_body,
                post_comments.created_at,
                users.name,
                COUNT(DISTINCT comment_likes.id) AS like_count,
                MAX(
                    CASE
                        WHEN comment_likes.user_id = ?
                        THEN 1
                        ELSE 0
                    END
                ) AS user_liked
            FROM post_comments

            JOIN users ON users.id = post_comments.user_id

            LEFT JOIN comment_likes ON comment_likes.comment_id = post_comments.id

            WHERE post_comments.post_id IN ($placeholders)

            GROUP BY
                post_comments.id,
                post_comments.post_id,
                post_comments.user_id,
                post_comments.parent_comment_id,
                post_comments.comment_body,
                post_comments.created_at,
                users.name

            ORDER BY post_comments.created_at ASC
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute(array_merge([$user_id], $post_ids));
        $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($comments as $comment) {
            $comments_by_post[$comment['post_id']][] = $comment;
        }

    } catch (PDOException $e) {
        $comments_by_post = [];
        error_log('Could not load comments: ' . $e->getMessage());
    }
}


// FETCH POST IMAGES FOR THESE POSTS
$post_images_by_post = [];

if (!empty($recent_posts)) {
    $post_ids = array_column($recent_posts, 'id');
    $placeholders = implode(',', array_fill(0, count($post_ids), '?'));

    try {
        $sql = "
            SELECT
                post_id,
                image_key,
                sort_order
            FROM post_images
            WHERE post_id IN ($placeholders)
            ORDER BY post_id ASC, sort_order ASC, id ASC
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($post_ids);
        $post_images = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($post_images as $image) {
            $post_images_by_post[$image['post_id']][] = $image['image_key'];
        }

    } catch (PDOException $e) {
        $post_images_by_post = [];
        error_log('Could not load post images: ' . $e->getMessage());
    }

    // Backward compatibility for older posts that still only have posts.image_key.
    foreach ($recent_posts as $post) {
        if (
            empty($post_images_by_post[$post['id']]) &&
            !empty($post['image_key'])
        ) {
            $post_images_by_post[$post['id']][] = $post['image_key'];
        }
    }
}
