<?php
if (!defined('PORTAL_APP')) {
    http_response_code(403);
    exit('Access denied.');
}

function app_redis() {
    static $redis = false;
    static $tried = false;
    if ($tried) {
        return $redis ?: null;
    }
    $tried = true;
    if (!defined('REDIS_ENABLED') || !REDIS_ENABLED || !class_exists('Redis')) {
        $redis = null;
        return null;
    }
    try {
        $r = new Redis();
        if (!$r->connect(REDIS_HOST, (int)REDIS_PORT, 0.4)) {
            $redis = null;
            return null;
        }
        if (defined('REDIS_PASSWORD') && REDIS_PASSWORD !== '') {
            $r->auth(REDIS_PASSWORD);
        }
        $redis = $r;
        return $redis;
    } catch (Throwable $e) {
        $redis = null;
        return null;
    }
}

function app_cache_get($key) {
    $full = (defined('REDIS_PREFIX') ? REDIS_PREFIX : '') . $key;
    $redis = app_redis();
    if ($redis) {
        try {
            $val = $redis->get($full);
            if ($val !== false && $val !== null) {
                $decoded = json_decode($val, true);
                return (json_last_error() === JSON_ERROR_NONE) ? $decoded : $val;
            }
        } catch (Throwable $e) {}
    }
    $file = __DIR__ . '/../cache/obj_' . md5($full) . '.json';
    if (is_file($file)) {
        $raw = @file_get_contents($file);
        $data = $raw ? json_decode($raw, true) : null;
        if (is_array($data) && isset($data['e'], $data['v']) && $data['e'] > time()) {
            return $data['v'];
        }
    }
    return null;
}

function app_cache_set($key, $value, $ttl = 300) {
    $full = (defined('REDIS_PREFIX') ? REDIS_PREFIX : '') . $key;
    $ttl = max(1, (int)$ttl);
    $redis = app_redis();
    if ($redis) {
        try {
            $redis->setex($full, $ttl, json_encode($value));
        } catch (Throwable $e) {}
    }
    $dir = __DIR__ . '/../cache';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    $file = $dir . '/obj_' . md5($full) . '.json';
    @file_put_contents($file, json_encode(['e' => time() + $ttl, 'v' => $value]), LOCK_EX);
}

function app_cache_delete($key) {
    $full = (defined('REDIS_PREFIX') ? REDIS_PREFIX : '') . $key;
    $redis = app_redis();
    if ($redis) {
        try {
            $redis->del($full);
        } catch (Throwable $e) {}
    }
    $file = __DIR__ . '/../cache/obj_' . md5($full) . '.json';
    if (is_file($file)) {
        @unlink($file);
    }
}

function app_need_session() {
    if (php_sapi_name() === 'cli') {
        return false;
    }
    if (!empty($_COOKIE[session_name()])) {
        return true;
    }
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    if (strpos($script, '/' . (defined('ADMIN_DIR') ? ADMIN_DIR : 'newlogin') . '/') !== false) {
        return true;
    }
    if (strpos($uri, '/community') !== false || strpos($script, 'community') !== false) {
        return true;
    }
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
        return true;
    }
    return false;
}

function app_boot_session() {
    if (php_sapi_name() === 'cli' || session_status() !== PHP_SESSION_NONE) {
        return;
    }
    if (!app_need_session()) {
        return;
    }
    ini_set('session.cookie_httponly', SESSION_HTTPONLY ? '1' : '0');
    ini_set('session.cookie_samesite', SESSION_SAMESITE);
    ini_set('session.cookie_secure', SESSION_SECURE ? '1' : '0');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.gc_maxlifetime', (string)SESSION_LIFETIME);
    session_start();
}
