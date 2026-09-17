<?php

if (!function_exists('loadPostImagesForPosts')) {
    function loadPostImagesForPosts(PDO $pdo, array $posts): array {
        $post_images_by_post = [];

        if (empty($posts)) {
            return $post_images_by_post;
        }

        $post_ids = array_map('intval', array_column($posts, 'id'));
        $placeholders = implode(',', array_fill(0, count($post_ids), '?'));

        $imageStmt = $pdo->prepare("
            SELECT
                post_id,
                image_key
            FROM post_images
            WHERE post_id IN ($placeholders)
            ORDER BY post_id ASC, sort_order ASC, id ASC
        ");

        $imageStmt->execute($post_ids);

        foreach ($imageStmt->fetchAll(PDO::FETCH_ASSOC) as $image) {
            $post_images_by_post[(int) $image['post_id']][] = $image['image_key'];
        }

        foreach ($posts as $post) {
            $post_id = (int) $post['id'];

            if (
                empty($post_images_by_post[$post_id]) &&
                !empty($post['image_key'])
            ) {
                $post_images_by_post[$post_id][] = $post['image_key'];
            }
        }

        return $post_images_by_post;
    }
}

if (!function_exists('loadCommentsForPosts')) {
    function loadCommentsForPosts(PDO $pdo, array $posts, $user_id = null): array {
        $comments_by_post = [];

        if (empty($posts)) {
            return $comments_by_post;
        }

        $post_ids = array_map('intval', array_column($posts, 'id'));
        $placeholders = implode(',', array_fill(0, count($post_ids), '?'));

        $commentsStmt = $pdo->prepare("
            SELECT
                c.id,
                c.post_id,
                c.user_id,
                c.comment_body,
                c.parent_comment_id,
                c.created_at,
                u.name,
                up.profile_picture_url,

                (
                    SELECT COUNT(*)
                    FROM comment_likes cl
                    WHERE cl.comment_id = c.id
                ) AS like_count,

                CASE
                    WHEN ? IS NULL THEN 0
                    WHEN EXISTS (
                        SELECT 1
                        FROM comment_likes ucl
                        WHERE ucl.comment_id = c.id
                          AND ucl.user_id = ?
                    ) THEN 1
                    ELSE 0
                END AS user_liked

            FROM post_comments c

            JOIN users u
                ON u.id = c.user_id

            LEFT JOIN user_profiles up
                ON up.user_id = c.user_id

            WHERE c.post_id IN ($placeholders)

            ORDER BY c.created_at ASC
        ");

        $commentsStmt->execute(
            array_merge([$user_id, $user_id], $post_ids)
        );

        foreach ($commentsStmt->fetchAll(PDO::FETCH_ASSOC) as $comment) {
            $comments_by_post[(int) $comment['post_id']][] = $comment;
        }

        return $comments_by_post;
    }
}

if (!function_exists('hydratePostsForRenderer')) {
    function hydratePostsForRenderer(PDO $pdo, array $posts, $user_id = null): array {
        return [
            'posts' => $posts,
            'comments_by_post' => loadCommentsForPosts(
                $pdo,
                $posts,
                $user_id
            ),
            'post_images_by_post' => loadPostImagesForPosts(
                $pdo,
                $posts
            ),
        ];
    }
}

if (!function_exists('loadSinglePostData')) {
    function loadSinglePostData(PDO $pdo, int $post_id, $user_id = null): array {
        $postStmt = $pdo->prepare("
            SELECT
                p.id,
                p.user_id,
                p.body,
                p.tag,
                p.image_key,
                p.created_at,
                COALESCE(p.view_count, 0) AS view_count,
                u.name,
                up.profile_picture_url,
                up.bio,
                up.is_private,

                (
                    SELECT COUNT(*)
                    FROM post_likes pl
                    WHERE pl.post_id = p.id
                ) AS like_count,

                (
                    SELECT COUNT(*)
                    FROM post_comments pc
                    WHERE pc.post_id = p.id
                ) AS comment_count,

                CASE
                    WHEN :current_user_id IS NULL THEN 0
                    WHEN EXISTS (
                        SELECT 1
                        FROM post_likes upl
                        WHERE upl.post_id = p.id
                          AND upl.user_id = :liked_user_id
                    ) THEN 1
                    ELSE 0
                END AS user_liked

            FROM posts p

            JOIN users u
                ON u.id = p.user_id

            LEFT JOIN user_profiles up
                ON up.user_id = p.user_id

            WHERE p.id = :post_id

            LIMIT 1
        ");

        $postStmt->execute([
            ':current_user_id' => $user_id,
            ':liked_user_id' => $user_id,
            ':post_id' => $post_id,
        ]);

        $post = $postStmt->fetch(PDO::FETCH_ASSOC);

        if (!$post) {
            return [
                'posts' => [],
                'comments_by_post' => [],
                'post_images_by_post' => [],
            ];
        }

        return hydratePostsForRenderer($pdo, [$post], $user_id);
    }
}

if (!function_exists('loadTagPostData')) {
    function loadTagPostData(
        PDO $pdo,
        string $tag,
        $user_id = null,
        int $limit = 30
    ): array {
        $limit = max(1, min($limit, 100));

        $stmt = $pdo->prepare("
            SELECT
                p.id,
                p.user_id,
                p.body,
                p.tag,
                p.image_key,
                p.created_at,
                COALESCE(p.view_count, 0) AS view_count,
                u.name,
                up.profile_picture_url,
                up.bio,
                up.is_private,

                (
                    SELECT COUNT(*)
                    FROM post_likes pl
                    WHERE pl.post_id = p.id
                ) AS like_count,

                (
                    SELECT COUNT(*)
                    FROM post_comments pc
                    WHERE pc.post_id = p.id
                ) AS comment_count,

                CASE
                    WHEN :current_user_id IS NULL THEN 0
                    WHEN EXISTS (
                        SELECT 1
                        FROM post_likes upl
                        WHERE upl.post_id = p.id
                          AND upl.user_id = :liked_user_id
                    ) THEN 1
                    ELSE 0
                END AS user_liked

            FROM posts p

            JOIN users u
                ON u.id = p.user_id

            LEFT JOIN user_profiles up
                ON up.user_id = p.user_id

            WHERE p.tag = :tag

            ORDER BY p.created_at DESC

            LIMIT $limit
        ");

        $stmt->execute([
            ':current_user_id' => $user_id,
            ':liked_user_id' => $user_id,
            ':tag' => $tag,
        ]);

        return hydratePostsForRenderer(
            $pdo,
            $stmt->fetchAll(PDO::FETCH_ASSOC),
            $user_id
        );
    }
}

if (!function_exists('buildWholeWordSearchPattern')) {
    function buildWholeWordSearchPattern(string $query): string {
        $query = trim($query);

        if ($query === '') {
            return '';
        }

        /*
         * Escape regular-expression characters supplied by the user, then
         * require a non-word boundary (or the beginning/end of the value)
         * around the entire search phrase.
         *
         * Examples:
         *   sea       matches "sea", "the sea", and "sea-side"
         *   sea       does not match "season", "overseas", or "Sean"
         *   Dana Point matches the complete phrase "Dana Point"
         */
        $escapedQuery = preg_quote($query, '~');

        return '(^|[^[:alnum:]_])'
            . $escapedQuery
            . '([^[:alnum:]_]|$)';
    }
}

if (!function_exists('loadSearchPostData')) {
    function loadSearchPostData(
        PDO $pdo,
        string $query,
        $user_id = null,
        int $limit = 50
    ): array {
        $query = trim($query);
        $limit = max(1, min($limit, 100));

        if ($query === '') {
            return [
                'posts' => [],
                'comments_by_post' => [],
                'post_images_by_post' => [],
            ];
        }

        $stmt = $pdo->prepare("
            SELECT
                p.id,
                p.user_id,
                p.body,
                p.tag,
                p.image_key,
                p.created_at,
                COALESCE(p.view_count, 0) AS view_count,
                u.name,
                up.profile_picture_url,
                up.bio,
                up.is_private,

                (
                    SELECT COUNT(*)
                    FROM post_likes pl
                    WHERE pl.post_id = p.id
                ) AS like_count,

                (
                    SELECT COUNT(*)
                    FROM post_comments pc
                    WHERE pc.post_id = p.id
                ) AS comment_count,

                CASE
                    WHEN :current_user_id IS NULL THEN 0
                    WHEN EXISTS (
                        SELECT 1
                        FROM post_likes upl
                        WHERE upl.post_id = p.id
                          AND upl.user_id = :liked_user_id
                    ) THEN 1
                    ELSE 0
                END AS user_liked

            FROM posts p

            JOIN users u
                ON u.id = p.user_id

            LEFT JOIN user_profiles up
                ON up.user_id = p.user_id

            WHERE
                LOWER(p.body) REGEXP LOWER(:search_body_pattern)
                OR LOWER(p.tag) REGEXP LOWER(:search_tag_pattern)
                OR LOWER(u.name) REGEXP LOWER(:search_author_pattern)

            ORDER BY
                CASE
                    WHEN LOWER(p.tag) = LOWER(:exact_tag_query) THEN 0
                    WHEN LOWER(u.name) = LOWER(:exact_author_query) THEN 1
                    WHEN LOWER(p.tag) LIKE LOWER(:tag_starts_with) THEN 2
                    WHEN LOWER(u.name) LIKE LOWER(:author_starts_with) THEN 3
                    ELSE 4
                END,
                p.created_at DESC

            LIMIT $limit
        ");

        $wholeWordPattern = buildWholeWordSearchPattern($query);

        $stmt->execute([
            ':current_user_id' => $user_id,
            ':liked_user_id' => $user_id,
            ':search_body_pattern' => $wholeWordPattern,
            ':search_tag_pattern' => $wholeWordPattern,
            ':search_author_pattern' => $wholeWordPattern,
            ':exact_tag_query' => $query,
            ':exact_author_query' => $query,
            ':tag_starts_with' => $query . '%',
            ':author_starts_with' => $query . '%',
        ]);

        return hydratePostsForRenderer(
            $pdo,
            $stmt->fetchAll(PDO::FETCH_ASSOC),
            $user_id
        );
    }
}

if (!function_exists('loadSearchPeopleData')) {
    function loadSearchPeopleData(
        PDO $pdo,
        string $query,
        int $limit = 20
    ): array {
        $query = trim($query);
        $limit = max(1, min($limit, 50));

        if ($query === '') {
            return [];
        }

        $stmt = $pdo->prepare("
            SELECT
                u.id,
                u.name,
                up.profile_picture_url,
                up.bio,
                up.is_private

            FROM users u

            LEFT JOIN user_profiles up
                ON up.user_id = u.id

            WHERE LOWER(u.name) REGEXP LOWER(:search_name_pattern)

            ORDER BY
                CASE
                    WHEN LOWER(u.name) = LOWER(:exact_name) THEN 0
                    WHEN LOWER(u.name) LIKE LOWER(:starts_with) THEN 1
                    ELSE 2
                END,
                u.name ASC

            LIMIT $limit
        ");

        $stmt->execute([
            ':search_name_pattern' => buildWholeWordSearchPattern($query),
            ':exact_name' => $query,
            ':starts_with' => $query . '%',
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

if (!function_exists('loadSearchTopicData')) {
    function loadSearchTopicData(
        PDO $pdo,
        string $query,
        int $limit = 20
    ): array {
        $query = trim($query);
        $limit = max(1, min($limit, 50));

        if ($query === '') {
            return [];
        }

        $stmt = $pdo->prepare("
            SELECT
                MIN(TRIM(p.tag)) AS tag,
                COUNT(*) AS post_count

            FROM posts p

            WHERE p.tag IS NOT NULL
              AND TRIM(p.tag) <> ''
              AND LOWER(TRIM(p.tag)) REGEXP LOWER(:search_tag_pattern)

            GROUP BY LOWER(TRIM(p.tag))

            ORDER BY
                CASE
                    WHEN LOWER(TRIM(MIN(p.tag))) = LOWER(:search_tag_exact) THEN 0
                    WHEN LOWER(TRIM(MIN(p.tag))) LIKE LOWER(:search_tag_prefix) THEN 1
                    ELSE 2
                END,
                post_count DESC,
                tag ASC

            LIMIT $limit
        " );

        $stmt->execute([
            ':search_tag_pattern' => buildWholeWordSearchPattern($query),
            ':search_tag_exact' => $query,
            ':search_tag_prefix' => $query . '%',
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

if (!function_exists('findRealTagFromSlug')) {
    function findRealTagFromSlug(
        PDO $pdo,
        string $incomingTagOrSlug
    ): ?string {
        $incomingSlug = makePostSlug(
            urldecode(trim($incomingTagOrSlug))
        );

        if ($incomingSlug === '') {
            return null;
        }

        $tagLookupStmt = $pdo->query("
            SELECT DISTINCT tag
            FROM posts
            WHERE tag IS NOT NULL
              AND TRIM(tag) <> ''
        ");

        foreach (
            $tagLookupStmt->fetchAll(PDO::FETCH_COLUMN)
            as $candidateTag
        ) {
            if (makePostSlug($candidateTag) === $incomingSlug) {
                return $candidateTag;
            }
        }

        return null;
    }
}
