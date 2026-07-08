<?php
/**
 * ╔══════════════════════════════════════════════════════════════════╗
 * ║  MASTER CONFIGURATION — WEB PORTAL (TEMPLATE)                     ║
 * ║  Rename this file to config.php and fill in your credentials.     ║
 * ║  Keep config.php excluded from Git to prevent secret leaks.       ║
 * ╚══════════════════════════════════════════════════════════════════╝
 */

// Guard: prevent direct HTTP access
if (!defined('PORTAL_APP')) {
    http_response_code(403);
    exit('Access denied.');
}

// ── Database Credentials ────────────────────────────────────────────
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');           // Change this in production!
define('DB_NAME', 'novels_db');

// ── Admin Panel ─────────────────────────────────────────────────────
define('ADMIN_DIR', 'newlogin'); // The folder name for the admin panel

// ── OpenAI / AI Configuration ───────────────────────────────────────
define('OPENAI_API_KEY', ''); // Paste your OpenAI API Key here
define('OPENAI_ALT_MODEL', 'gpt-4o-mini');
define('AUTO_ALT_ENABLED', true);
define('AUTO_ALT_LANG', 'auto');
define('CONTENT_BRAIN_ENABLED', true);

// ── Google OAuth / Community Sign-In ────────────────────────────────────────
// Get your Client ID from: https://console.cloud.google.com/apis/credentials
// IMPORTANT: Add your live domain to Authorized JavaScript origins.
define('GOOGLE_CLIENT_ID', '');  // <-- Paste your Google OAuth Client ID here

// ── Session Security ────────────────────────────────────────────────
define('SESSION_LIFETIME', 3600);       // 1 hour
define('SESSION_SECURE', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
define('SESSION_HTTPONLY', true);
define('SESSION_SAMESITE', 'Strict');

// ── Performance / Caching ───────────────────────────────────────────
define('REDIS_ENABLED', true);
define('REDIS_HOST', '127.0.0.1');
define('REDIS_PORT', 6379);
define('REDIS_PASSWORD', '');
define('REDIS_PREFIX', 'novels:');
define('SETTINGS_CACHE_TTL', 300);
define('PAGE_HTML_CACHE', true);
define('PDF_DISK_CACHE', true);
define('PDF_CACHE_TTL', 2592000);
define('PDF_CACHE_DIR', __DIR__ . '/cache/pdf');
define('PDF_RATE_LIMIT_PER_MIN', 30);

// Cron HTTP secret (only needed if you trigger cron via URL instead of CLI)
define('CRON_SECRET', 'change-this-to-a-long-random-string');
