<?php
require_once __DIR__ . '/../config/database.php';

$baseUrl = 'https://whusup.com';

function xmlEscape($value) {
    return htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

function makePostSlug($text, $fallback = 'whusup-post') {
    $text = strtolower(trim((string) $text));
    $text = preg_replace('/[^a-z0-9]+/i', '-', $text);
    $text = trim($text, '-');

    return $text !== '' ? substr($text, 0, 80) : $fallback;
}

header('Content-Type: application/xml; charset=UTF-8');

$postsStmt = $pdo->query("
    SELECT id, body, tag, created_at
    FROM posts
    ORDER BY created_at DESC
");

$posts = $postsStmt->fetchAll(PDO::FETCH_ASSOC);

$tagsStmt = $pdo->query("
    SELECT tag, MAX(created_at) AS lastmod
    FROM posts
    WHERE tag IS NOT NULL AND TRIM(tag) <> ''
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
        $slugSource = !empty($post['tag']) ? $post['tag'] : $post['body'];
        $slug = makePostSlug($slugSource);
        $postUrl = $baseUrl . '/post/' . rawurlencode($post['id']) . '/' . rawurlencode($slug);
        $lastmod = date('Y-m-d', strtotime($post['created_at']));
    ?>
    <url>
        <loc><?= xmlEscape($postUrl) ?></loc>
        <lastmod><?= xmlEscape($lastmod) ?></lastmod>
    </url>

<?php endforeach; ?>

<?php foreach ($tags as $tagRow): ?>
    <?php
        $tagSlug = makePostSlug($tagRow['tag']);
        $tagUrl = $baseUrl . '/tag/' . rawurlencode($tagSlug);
        $lastmod = date('Y-m-d', strtotime($tagRow['lastmod']));
    ?>
    <url>
        <loc><?= xmlEscape($tagUrl) ?></loc>
        <lastmod><?= xmlEscape($lastmod) ?></lastmod>
    </url>

<?php endforeach; ?>
</urlset>