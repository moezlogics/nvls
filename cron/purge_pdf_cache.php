<?php
/**
 * Purge PDF disk cache older than N days.
 *
 * CLI (recommended):
 *   php cron/purge_pdf_cache.php
 *   php cron/purge_pdf_cache.php --days=30
 *
 * HTTP (optional, requires secret):
 *   /cron/purge_pdf_cache.php?key=YOUR_CRON_SECRET
 */

$isCli = (php_sapi_name() === 'cli');

define('PORTAL_APP', true);
require_once dirname(__DIR__) . '/config.php';

$defaultDays = defined('PDF_CACHE_TTL') ? max(1, (int)ceil(PDF_CACHE_TTL / 86400)) : 30;
$days = $defaultDays;
$keyOk = false;

if ($isCli) {
    foreach ($argv as $arg) {
        if (strpos($arg, '--days=') === 0) {
            $days = max(1, (int)substr($arg, 7));
        }
    }
    $keyOk = true;
} else {
    $secret = defined('CRON_SECRET') ? (string)CRON_SECRET : '';
    $provided = (string)($_GET['key'] ?? '');
    if ($secret !== '' && hash_equals($secret, $provided)) {
        $keyOk = true;
    }
    if (isset($_GET['days'])) {
        $days = max(1, (int)$_GET['days']);
    }
    header('Content-Type: text/plain; charset=utf-8');
    header('X-Robots-Tag: noindex');
}

if (!$keyOk) {
    if (!$isCli) {
        http_response_code(403);
    }
    echo "Forbidden\n";
    exit(1);
}

$dir = defined('PDF_CACHE_DIR') ? PDF_CACHE_DIR : (dirname(__DIR__) . '/cache/pdf');
if (!is_dir($dir)) {
    echo "PDF cache dir missing: {$dir}\n";
    exit(0);
}

$cutoff = time() - ($days * 86400);
$deletedPdf = 0;
$deletedMisc = 0;
$freed = 0;
$errors = 0;

$iterator = new DirectoryIterator($dir);
foreach ($iterator as $file) {
    if ($file->isDot() || !$file->isFile()) {
        continue;
    }

    $name = $file->getFilename();
    $path = $file->getPathname();
    $mtime = $file->getMTime();
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));

    $isPdf = ($ext === 'pdf');
    $isTemp = (
        strpos($name, '.part.') !== false
        || substr($name, -5) === '.lock'
        || strpos($name, 'rl_') === 0
    );

    if (!$isPdf && !$isTemp) {
        continue;
    }

    if ($mtime > $cutoff && $isPdf) {
        continue;
    }

    // Temp/lock/rate-limit files: remove if older than 1 day, or always if older than cutoff
    if ($isTemp && $mtime > (time() - 86400) && $mtime > $cutoff) {
        continue;
    }

    $size = $file->getSize();
    if (@unlink($path)) {
        $freed += $size;
        if ($isPdf) {
            $deletedPdf++;
        } else {
            $deletedMisc++;
        }
    } else {
        $errors++;
    }
}

$mb = round($freed / 1048576, 2);
$msg = sprintf(
    "[%s] PDF cache purge done. days=%d pdf_deleted=%d misc_deleted=%d freed_mb=%s errors=%d dir=%s\n",
    date('c'),
    $days,
    $deletedPdf,
    $deletedMisc,
    $mb,
    $errors,
    $dir
);

echo $msg;

$logFile = dirname(__DIR__) . '/cache/pdf_purge.log';
@file_put_contents($logFile, $msg, FILE_APPEND | LOCK_EX);

exit($errors > 0 ? 2 : 0);
