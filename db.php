<?php
if (defined('DB_BOOTSTRAP_DONE')) {
    global $conn, $site_settings, $base_path;
    return;
}
define('DB_BOOTSTRAP_DONE', true);

if (!defined('PORTAL_APP')) {
    define('PORTAL_APP', true);
}
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/app_cache.php';

app_boot_session();

$doc_root = rtrim(str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'])), '/');
$project_root = rtrim(str_replace('\\', '/', __DIR__), '/');
$base_path = str_replace($doc_root, '', $project_root);
$base_path = rtrim($base_path, '/');

if (php_sapi_name() !== 'cli') {
    $scheme = (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off') ? 'https' : 'http';
    $site_url = rtrim($scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $base_path, '/');
} else {
    $site_url = '';
}

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    error_log('DB Connection failed: ' . $conn->connect_error);
    if (php_sapi_name() !== 'cli') {
        http_response_code(503);
        die('<h1>Service Temporarily Unavailable</h1><p>Please try again later.</p>');
    }
    die('Database connection failed.');
}
$conn->set_charset('utf8mb4');

require_once __DIR__ . '/lib/schema_upgrade.php';
schema_upgrade($conn);

$site_settings = app_cache_get('settings:v1');
if (!is_array($site_settings)) {
    $settings_query = $conn->query("SELECT * FROM settings WHERE id = 1");
    if ($settings_query && $settings_query->num_rows > 0) {
        $site_settings = $settings_query->fetch_assoc();
        foreach (['footer_col1', 'footer_col2', 'footer_col3'] as $col) {
            if (!empty($site_settings[$col])) {
                $site_settings[$col] = str_replace(
                    ['href="index.php"', 'href="posts.php"', 'href="about.php"', 'href="contact.php"', 'href=\'index.php\'', 'href=\'posts.php\'', 'href=\'about.php\'', 'href=\'contact.php\''],
                    ['href="' . $base_path . '/"', 'href="' . $base_path . '/posts/"', 'href="' . $base_path . '/about/"', 'href="' . $base_path . '/contact/"', 'href="' . $base_path . '/"', 'href="' . $base_path . '/posts/"', 'href="' . $base_path . '/about/"', 'href="' . $base_path . '/contact/"'],
                    $site_settings[$col]
                );
            }
        }
    } else {
        $site_settings = [
            'site_name' => 'Portal',
            'seo_description' => '',
            'seo_keywords' => '',
            'permalink_structure' => 'plain',
            'maintenance_mode' => 0,
            'community_coming_soon' => 0,
            'shop_coming_soon' => 0,
        ];
    }
    app_cache_set('settings:v1', $site_settings, defined('SETTINGS_CACHE_TTL') ? SETTINGS_CACHE_TTL : 300);
}

if ($conn && php_sapi_name() !== 'cli' && is_array($site_settings)) {
    require_once __DIR__ . '/lib/coming_soon.php';
    coming_soon_ensure_preview_keys($conn, $site_settings);
}

if (php_sapi_name() !== 'cli' && !empty($site_settings['maintenance_mode']) && empty($_SESSION['admin_logged_in']) && strpos($_SERVER['SCRIPT_NAME'] ?? '', ADMIN_DIR . '/') === false) {
    http_response_code(503);
    die("
    <div style='text-align: center; margin-top: 15%; font-family: sans-serif; color: #333;'>
        <h1 style='font-size: 3rem; margin-bottom: 10px;'>We'll be back soon!</h1>
        <p style='font-size: 1.2rem; color: #666;'>Sorry for the inconvenience but we're performing some maintenance at the moment. We'll be back online shortly!</p>
    </div>
    ");
}

if (php_sapi_name() !== 'cli' && !headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
}

if (!function_exists('csrf_token')) {
function csrf_token() {
    app_boot_session();
    if (session_status() !== PHP_SESSION_ACTIVE) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }
    if (empty($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf_token'];
}

function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}

function csrf_verify() {
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!hash_equals(csrf_token(), $token)) {
        http_response_code(403);
        die('<h1>403 Forbidden</h1><p>Invalid security token. Please go back and try again.</p>');
    }
}

function create_slug($string) {
    $slug = preg_replace('/[^A-Za-z0-9-]+/', '-', strtolower($string));
    return trim($slug, '-');
}

function get_post_link($id, $slug) {
    global $site_settings, $base_path;
    if (($site_settings['permalink_structure'] ?? '') === 'postname' && !empty($slug)) {
        return $base_path . '/' . $slug . '/';
    }
    return $base_path . '/single.php?id=' . $id;
}

function get_category_link($slug) {
    global $site_settings, $base_path;
    if (($site_settings['permalink_structure'] ?? '') === 'postname' && !empty($slug)) {
        return $base_path . '/' . $slug . '/';
    }
    return $base_path . '/archive.php?category=' . $slug;
}

function get_writer_link($slug) {
    global $site_settings, $base_path;
    if (($site_settings['permalink_structure'] ?? '') === 'postname' && !empty($slug)) {
        return $base_path . '/' . $slug . '/';
    }
    return $base_path . '/archive.php?writer=' . $slug;
}

function get_page_link($slug) {
    global $site_settings, $base_path;
    if (($site_settings['permalink_structure'] ?? '') === 'postname' && !empty($slug)) {
        return $base_path . '/' . $slug . '/';
    }
    return $base_path . '/page.php?slug=' . $slug;
}

function hex2rgb($hex) {
    $hex = str_replace("#", "", $hex);
    if (strlen($hex) === 3) {
        $r = hexdec(substr($hex, 0, 1) . substr($hex, 0, 1));
        $g = hexdec(substr($hex, 1, 1) . substr($hex, 1, 1));
        $b = hexdec(substr($hex, 2, 1) . substr($hex, 2, 1));
    } else {
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
    }
    return "$r, $g, $b";
}

function clear_page_cache() {
    $cacheDir = __DIR__ . '/cache';
    if (is_dir($cacheDir)) {
        foreach (glob($cacheDir . '/*.html') ?: [] as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
        foreach (glob($cacheDir . '/obj_*.json') ?: [] as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
    }
    if (function_exists('app_cache_delete')) {
        app_cache_delete('settings:v1');
    }
}
}
