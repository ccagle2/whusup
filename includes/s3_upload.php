<?php

function uploadToS3(array $file, string $folder = 'posts'): array
{
    $config = require __DIR__ . '/../config/aws.php';

    $s3 = $config['s3'];
    $bucket = $config['bucket'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'Upload failed.'];
    }

    $maxSize = 10 * 1024 * 1024; // 10MB

    if ($file['size'] > $maxSize) {
        return ['success' => false, 'error' => 'File is too large. Max size is 10MB.'];
    }

    $mimeType = mime_content_type($file['tmp_name']);

    $allowedTypes = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
    ];

    if (!array_key_exists($mimeType, $allowedTypes)) {
        return ['success' => false, 'error' => 'Invalid file type.'];
    }

    $extension = $allowedTypes[$mimeType];

    $safeFolder = trim($folder, '/');
    $fileName = bin2hex(random_bytes(16)) . '.' . $extension;
    $key = $safeFolder . '/' . $fileName;

    try {
        $s3->putObject([
            'Bucket' => $bucket,
            'Key' => $key,
            'SourceFile' => $file['tmp_name'],
            'ContentType' => $mimeType,
            'ServerSideEncryption' => 'AES256',
        ]);

        return [
            'success' => true,
            'key' => $key,
            'mime_type' => $mimeType,
        ];

    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => 'S3 upload failed: ' . $e->getMessage(),
        ];
    }
}