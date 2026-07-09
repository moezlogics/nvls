<?php
/**
 * Per-feature coming soon gates (community / shop) with preview-key bypass.
 */

function coming_soon_features(): array {
    return ['community', 'shop'];
}

function coming_soon_setting_key(string $feature): string {
    return $feature . '_coming_soon';
}

function coming_soon_preview_key_setting(string $feature): string {
    return $feature . '_preview_key';
}

function coming_soon_cookie_name(string $feature): string {
    return 'cs_preview_' . $feature;
}

function coming_soon_is_enabled(string $feature): bool {
    global $site_settings;
    if (!in_array($feature, coming_soon_features(), true)) {
        return false;
    }
    return !empty($site_settings[coming_soon_setting_key($feature)]);
}

function coming_soon_generate_key(): string {
    return bin2hex(random_bytes(16));
}

function coming_soon_ensure_preview_keys(mysqli $conn, array &$site_settings): void {
    $updates = [];
    foreach (coming_soon_features() as $feature) {
        $col = coming_soon_preview_key_setting($feature);
        if (empty($site_settings[$col])) {
            $key = coming_soon_generate_key();
            $site_settings[$col] = $key;
            $updates[$col] = $key;
        }
    }
    if ($updates) {
        $parts = [];
        foreach ($updates as $col => $val) {
            $parts[] = "$col='" . $conn->real_escape_string($val) . "'";
        }
        $conn->query('UPDATE settings SET ' . implode(', ', $parts) . ' WHERE id=1');
        if (function_exists('app_cache_delete')) {
            app_cache_delete('settings:v1');
        }
        if (function_exists('app_cache_set') && defined('SETTINGS_CACHE_TTL')) {
            app_cache_set('settings:v1', $site_settings, SETTINGS_CACHE_TTL);
        }
    }
}

function coming_soon_admin_bypass(): bool {
    return !empty($_SESSION['admin_logged_in']);
}

function coming_soon_valid_preview_token(string $feature, ?string $token): bool {
    global $site_settings;
    if ($token === null || $token === '') {
        return false;
    }
    $stored = $site_settings[coming_soon_preview_key_setting($feature)] ?? '';
    return $stored !== '' && hash_equals($stored, $token);
}

function coming_soon_set_preview_cookie(string $feature, string $token): void {
    if (php_sapi_name() === 'cli' || headers_sent()) {
        return;
    }
    $secure = !empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off';
    setcookie(coming_soon_cookie_name($feature), $token, [
        'expires' => time() + 86400 * 90,
        'path' => '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function coming_soon_has_preview_access(string $feature): bool {
    if (!in_array($feature, coming_soon_features(), true)) {
        return false;
    }
    if (coming_soon_admin_bypass()) {
        return true;
    }
    $token = $_GET['preview'] ?? $_COOKIE[coming_soon_cookie_name($feature)] ?? '';
    if (!coming_soon_valid_preview_token($feature, $token)) {
        return false;
    }
    if (isset($_GET['preview']) && coming_soon_valid_preview_token($feature, $_GET['preview'])) {
        coming_soon_set_preview_cookie($feature, $_GET['preview']);
    }
    return true;
}

function coming_soon_preview_url(string $feature): string {
    global $site_url, $site_settings;
    $key = $site_settings[coming_soon_preview_key_setting($feature)] ?? '';
    if ($key === '') {
        return '';
    }
    $base = rtrim($site_url, '/');
    if ($feature === 'community') {
        return $base . '/community/?preview=' . urlencode($key);
    }
    return $base . '/shop/?preview=' . urlencode($key);
}

function coming_soon_gate(string $feature, bool $json = false): void {
    if (!coming_soon_is_enabled($feature)) {
        return;
    }
    if (coming_soon_has_preview_access($feature)) {
        return;
    }

    if ($json) {
        if (!headers_sent()) {
            http_response_code(503);
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store, no-cache, must-revalidate');
            header('Retry-After: 3600');
            header('X-Robots-Tag: noindex, nofollow');
        }
        echo json_encode([
            'status' => 'error',
            'error' => ucfirst($feature) . ' is coming soon.',
            'coming_soon' => true,
        ]);
        exit;
    }

    coming_soon_render($feature);
    exit;
}

function coming_soon_render(string $feature): void {
    global $site_settings, $site_url, $base_path;

    if (!headers_sent()) {
        http_response_code(503);
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Retry-After: 3600');
        header('X-Robots-Tag: noindex, nofollow');
    }

    $siteName = htmlspecialchars($site_settings['site_name'] ?? 'Portal', ENT_QUOTES, 'UTF-8');
    $primary = preg_match('/^#[0-9a-fA-F]{3,8}$/', $site_settings['theme_primary'] ?? '') ? $site_settings['theme_primary'] : '#249990';

    $logo = $site_settings['site_logo'] ?? '';
    $logoUrl = '';
    if ($logo) {
        $logoUrl = (preg_match('~^https?://~i', $logo))
            ? $logo
            : rtrim($site_url, '/') . '/' . ltrim($logo, '/');
    }

    $isCommunity = $feature === 'community';
    $title = $isCommunity ? 'Community' : 'Shop';
    $headline = 'Coming Soon';
    $subtitle = $isCommunity
        ? 'A place to share and connect with readers. Launching shortly.'
        : 'Printed books and more. Launching shortly.';
    $homeUrl = rtrim($site_url, '/') . '/';
    $cssUrl = rtrim($site_url, '/') . '/css/coming_soon.css';

    include __DIR__ . '/../includes/coming_soon_page.php';
}
