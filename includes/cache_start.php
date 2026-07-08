<?php
if (!defined('PORTAL_APP')) {
    define('PORTAL_APP', true);
    require_once __DIR__ . '/../config.php';
}

$cache_time = 3600;
$skipHtmlCache = false;
if (session_status() === PHP_SESSION_ACTIVE) {
    $skipHtmlCache = !empty($_SESSION['admin_logged_in']) || !empty($_SESSION['member_id']);
} elseif (!empty($_COOKIE[session_name()])) {
    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }
    $skipHtmlCache = !empty($_SESSION['admin_logged_in']) || !empty($_SESSION['member_id']);
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET' && (!defined('PAGE_HTML_CACHE') || PAGE_HTML_CACHE) && !$skipHtmlCache) {
    $tracking_params = ['gclid', 'fbclid', 'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'amp'];
    $parsed_url = parse_url($_SERVER['REQUEST_URI'] ?? '/');
    $query = [];
    if (!empty($parsed_url['query'])) {
        parse_str($parsed_url['query'], $query);
        foreach ($tracking_params as $p) {
            unset($query[$p]);
        }
    }
    $query_str = !empty($query) ? '?' . http_build_query($query) : '';
    $page_url = ($_SERVER['HTTP_HOST'] ?? 'localhost') . ($parsed_url['path'] ?? '/') . $query_str;

    $cache_file = __DIR__ . '/../cache/' . md5($page_url) . '.html';

    if (file_exists($cache_file) && (time() - $cache_time < filemtime($cache_file))) {
        if (!headers_sent()) {
            header('X-Cache: PAGE-HIT');
            header('Cache-Control: public, max-age=60, s-maxage=300');
        }
        readfile($cache_file);
        exit;
    }

    ob_start();
}
?>
