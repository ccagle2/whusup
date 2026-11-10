<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/posts/post_helpers.php';

$baseUrl = 'https://whusup.com';

function xmlEscape($value) {
    return htmlspecialchars(
        (string) $value,
        ENT_XML1 | ENT_QUOTES,
        'UTF-8'
    );
}

header('Content-Type: application/xml; charset=UTF-8');

/*
 * Include title because post URLs now use the centralized slug priority:
 *
 * title -> body -> tag -> whusup-post
 */
$postsStmt = $pdo->query("
    SELECT
        id,
        title,
        body,
        tag,
        created_at
    FROM posts
    ORDER BY created_at DESC
");

$posts = $postsStmt->fetchAll(PDO::FETCH_ASSOC);

/*
 * Build tag sitemap entries separately.
 *
 * Tags continue using the generic makePostSlug() helper because tag URLs
 * are independent of the canonical post-slug rules.
 */
$tagsStmt = $pdo->query("
    SELECT
        tag,
        MAX(created_at) AS lastmod
    FROM posts
    WHERE tag IS NOT NULL
      AND TRIM(tag) <> ''
    GROUP BY tag
    ORDER BY tag ASC
");

$tags = $tagsStmt->fetchAll(PDO::FETCH_ASSOC);

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <url>
        <loc><?= xmlEscape($baseUrl . '/') ?></loc>
    </url>

    <url>
        <loc><?= xmlEscape($baseUrl . '/about.php') ?></loc>
    </url>

<?php foreach ($posts as $post): ?>
    <?php
        $slug = getPostSlug($post);

        $postUrl =
            $baseUrl .
            '/post/' .
            rawurlencode((string) $post['id']) .
            '/' .
            rawurlencode($slug);

        $lastmod = date(
            'Y-m-d',
            strtotime($post['created_at'])
        );
    ?>
    <url>
        <loc><?= xmlEscape($postUrl) ?></loc>
        <lastmod><?= xmlEscape($lastmod) ?></lastmod>
    </url>

<?php endforeach; ?>

<?php foreach ($tags as $tagRow): ?>
    <?php
        $tagSlug = makePostSlug($tagRow['tag']);

        $tagUrl =
            $baseUrl .
            '/tag/' .
            rawurlencode($tagSlug);

        $lastmod = date(
            'Y-m-d',
            strtotime($tagRow['lastmod'])
        );
    ?>
    <url>
        <loc><?= xmlEscape($tagUrl) ?></loc>
        <lastmod><?= xmlEscape($lastmod) ?></lastmod>
    </url>

<?php endforeach; ?>
</urlset>