<?php

if (!function_exists('getS3ImageUrl')) {
    function getS3ImageUrl($imageKey) {
        global $awsConfig;

        if (empty($imageKey)) {
            return null;
        }

        return rtrim($awsConfig['cloudfront_url'], '/') . '/' . ltrim($imageKey, '/');
    }
}

if (!function_exists('timeAgo')) {
    function timeAgo($datetime) {
        $time = strtotime($datetime);

        if ($time === false) {
            return "";
        }

        $diff = max(0, time() - $time);

        if ($diff < 60) {
            return "Just now";
        }

        $units = [
            "year" => 31536000,
            "month" => 2592000,
            "week" => 604800,
            "day" => 86400,
            "hour" => 3600,
            "minute" => 60,
        ];

        foreach ($units as $unit => $seconds) {
            if ($diff >= $seconds) {
                $value = (int) floor($diff / $seconds);
                return $value . " " . $unit . ($value === 1 ? "" : "s") . " ago";
            }
        }

        return "Just now";
    }
}

if (!function_exists('linkifyText')) {
    function linkifyText($text) {
        $escaped = htmlspecialchars(trim((string) $text), ENT_QUOTES, 'UTF-8');
        $pattern = '/(https?:\/\/[^\s<]+)/i';

        return nl2br(preg_replace_callback($pattern, function ($matches) {
            $url = $matches[0];

            $display = preg_replace('#^https?://#', '', $url);
            $display = rtrim($display, '/');

            if (strlen($display) > 40) {
                $display = substr($display, 0, 37) . '...';
            }

            return '<a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener noreferrer">' . htmlspecialchars($display, ENT_QUOTES, 'UTF-8') . '</a>';
        }, $escaped));
    }
}

if (!function_exists('cleanMetaText')) {
    function cleanMetaText($text, $maxLength = 155) {
        $text = trim(strip_tags((string) $text));
        $text = preg_replace('/\s+/', ' ', $text);

        if (strlen($text) > $maxLength) {
            $text = substr($text, 0, $maxLength - 3) . '...';
        }

        return $text;
    }
}

if (!function_exists('makePostSlug')) {
    function makePostSlug($text, $fallback = 'whusup-post') {
        $text = strtolower(trim((string) $text));
        $text = preg_replace('/[^a-z0-9]+/i', '-', $text);
        $text = trim($text, '-');

        if ($text === '') {
            $text = $fallback;
        }

        return substr($text, 0, 80);
    }
}

if (!function_exists('currentPostsUrl')) {
    function currentPostsUrl($sort_value) {
        global $tag_search;

        $base_url = strtok($_SERVER['REQUEST_URI'], '?');
        $query = [];

        if (isset($_GET['page'])) {
            $query['page'] = $_GET['page'];
        }

        if (isset($_GET['filter'])) {
            $query['filter'] = $_GET['filter'];
        }

        $query['sort'] = $sort_value;

        if (!empty($tag_search)) {
            $query['tag'] = $tag_search;
        }

        return $base_url . '?' . http_build_query($query);
    }
}

if (!function_exists('redirectToPosts')) {
    function redirectToPosts($url) {
        echo '<script>window.location.href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '";</script>';
        echo '<noscript><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '"></noscript>';
        exit;
    }
}

if (!function_exists('getDirectChildCount')) {
    function getDirectChildCount($comments, $parent_id) {
        $count = 0;

        foreach ($comments as $comment) {
            $comment_parent = $comment['parent_comment_id'] ?? null;

            if ((string) $comment_parent === (string) $parent_id) {
                $count++;
            }
        }

        return $count;
    }
}