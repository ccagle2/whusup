<?php
declare(strict_types=1);

/**
 * One-time Whusup post image WebP migration.
 * Run from CLI only:
 *   php scripts/convert_post_images_webp.php --limit=5
 *   php scripts/convert_post_images_webp.php --all
 *   php scripts/convert_post_images_webp.php --verify
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only\n");
}

set_time_limit(0);
ini_set('memory_limit', '256M');

$root = dirname(__DIR__);
require_once $root . '/vendor/autoload.php';
require_once $root . '/config/database.php';
$aws = require $root . '/config/aws.php';
$s3 = $aws['s3'] ?? null;
$bucket = $aws['bucket'] ?? null;
if (!$pdo instanceof PDO || !$s3 instanceof \Aws\S3\S3Client || !is_string($bucket) || $bucket === '') {
    fwrite(STDERR, "Database or S3 configuration unavailable.\n");
    exit(1);
}
if (!extension_loaded('gd') || !function_exists('imagewebp')) {
    fwrite(STDERR, "PHP GD with WebP support is required.\n");
    exit(1);
}

$options = getopt('', ['limit:', 'all', 'verify']);
$modes = (int)isset($options['limit']) + (int)isset($options['all']) + (int)isset($options['verify']);
if ($modes !== 1) {
    fwrite(STDERR, "Usage: php scripts/convert_post_images_webp.php --limit=5 | --all | --verify\n");
    exit(2);
}
$verifyOnly = isset($options['verify']);
$limit = null;
if (isset($options['limit'])) {
    $value = (string)$options['limit'];
    if (!ctype_digit($value) || (int)$value < 1) {
        fwrite(STDERR, "--limit must be a positive integer.\n");
        exit(2);
    }
    $limit = (int)$value;
}

function webpKeyForPost(string $key): ?string
{
    if (!preg_match('~^posts/([a-f0-9]{32})\.(?:jpe?g|png|webp|gif)$~i', $key, $matches)) {
        return null;
    }
    return 'posts/feed/' . strtolower($matches[1]) . '.webp';
}

function remoteExists(\Aws\S3\S3Client $s3, string $bucket, string $key): bool
{
    try {
        $s3->headObject(['Bucket' => $bucket, 'Key' => $key]);
        return true;
    } catch (\Aws\Exception\AwsException $e) {
        $status = $e->getStatusCode();
        if ($status === 404 || in_array($e->getAwsErrorCode(), ['NoSuchKey', 'NotFound'], true)) {
            return false;
        }
        throw $e;
    }
}

function convertImage(string $bytes): string
{
    $source = @imagecreatefromstring($bytes);
    if (!$source) {
        throw new RuntimeException('GD could not decode source image');
    }
    try {
        $width = imagesx($source);
        $height = imagesy($source);
        if ($width < 1 || $height < 1) {
            throw new RuntimeException('Invalid image dimensions');
        }
        $scale = min(1.0, 960 / max($width, $height));
        $newWidth = max(1, (int)round($width * $scale));
        $newHeight = max(1, (int)round($height * $scale));
        $resized = imagecreatetruecolor($newWidth, $newHeight);
        if (!$resized) {
            throw new RuntimeException('Unable to allocate output image');
        }
        try {
            $white = imagecolorallocate($resized, 255, 255, 255);
            imagefill($resized, 0, 0, $white);
            if (!imagecopyresampled($resized, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height)) {
                throw new RuntimeException('Resizing failed');
            }
            ob_start();
            $ok = imagewebp($resized, null, 80);
            $output = ob_get_clean();
            if (!$ok || !is_string($output) || $output === '') {
                throw new RuntimeException('WebP encoding failed');
            }
            return $output;
        } finally {
            imagedestroy($resized);
        }
    } finally {
        imagedestroy($source);
    }
}

try {
    $stmt = $pdo->query("SELECT image_key FROM post_images WHERE image_key IS NOT NULL AND image_key <> '' UNION SELECT image_key FROM posts WHERE image_key IS NOT NULL AND image_key <> '' ORDER BY image_key");
    $keys = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $total = count($keys);
    echo "Unique referenced images: {$total}\n";
    if ($limit !== null) {
        $keys = array_slice($keys, 0, $limit);
    }
    $stats = ['created' => 0, 'already_exists' => 0, 'verified' => 0, 'missing' => 0, 'unsupported' => 0, 'failed' => 0];
    foreach ($keys as $index => $key) {
        $key = (string)$key;
        $target = webpKeyForPost($key);
        $prefix = sprintf('[%d/%d] %s', $index + 1, count($keys), $key);
        if ($target === null) {
            echo "{$prefix} UNSUPPORTED KEY FORMAT\n";
            $stats['unsupported']++;
            continue;
        }
        try {
            if (remoteExists($s3, $bucket, $target)) {
                echo "{$prefix} " . ($verifyOnly ? 'VERIFIED' : 'ALREADY EXISTS') . "\n";
                $stats[$verifyOnly ? 'verified' : 'already_exists']++;
                continue;
            }
            if ($verifyOnly) {
                echo "{$prefix} MISSING {$target}\n";
                $stats['missing']++;
                continue;
            }
            $response = $s3->getObject(['Bucket' => $bucket, 'Key' => $key]);
            $bytes = (string)$response['Body'];
            $webp = convertImage($bytes);
            $s3->putObject([
                'Bucket' => $bucket,
                'Key' => $target,
                'Body' => $webp,
                'ContentType' => 'image/webp',
                'CacheControl' => 'public, max-age=31536000, immutable',
                'ServerSideEncryption' => 'AES256',
            ]);
            if (!remoteExists($s3, $bucket, $target)) {
                throw new RuntimeException('Uploaded WebP could not be verified');
            }
            echo "{$prefix} CREATED {$target} (" . strlen($bytes) . ' -> ' . strlen($webp) . " bytes)\n";
            $stats['created']++;
        } catch (Throwable $e) {
            echo "{$prefix} FAILED: {$e->getMessage()}\n";
            $stats['failed']++;
        }
    }
    echo "\nSummary: " . json_encode($stats, JSON_UNESCAPED_SLASHES) . "\n";
    exit(($stats['failed'] || $stats['unsupported'] || $stats['missing']) ? 1 : 0);
} catch (Throwable $e) {
    fwrite(STDERR, "Fatal: {$e->getMessage()}\n");
    exit(1);
}
