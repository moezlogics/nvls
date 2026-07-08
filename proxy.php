<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Range, Content-Type');
header('Access-Control-Expose-Headers: Content-Range, Content-Length, Accept-Ranges, X-Cache');
header('Access-Control-Allow-Methods: GET, HEAD, OPTIONS');
header('X-Content-Type-Options: nosniff');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

define('PORTAL_APP', true);
require_once __DIR__ . '/config.php';

function proxy_clean_id($id) {
    return preg_replace('/[^a-zA-Z0-9_-]/', '', (string)$id);
}

function proxy_cache_dir() {
    $dir = defined('PDF_CACHE_DIR') ? PDF_CACHE_DIR : (__DIR__ . '/cache/pdf');
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    return $dir;
}

function proxy_pdf_path($id) {
    return proxy_cache_dir() . '/' . $id . '.pdf';
}

function proxy_rate_limit($id) {
    // Range requests are normal while reading — never throttle them
    if (!empty($_SERVER['HTTP_RANGE'])) {
        return true;
    }
    $limit = defined('PDF_RATE_LIMIT_PER_MIN') ? (int)PDF_RATE_LIMIT_PER_MIN : 30;
    if ($limit <= 0) {
        return true;
    }
    $ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '0';
    $ip = preg_replace('/[^a-zA-Z0-9\.:]/', '', $ip);
    $slot = (int)floor(time() / 60);
    $file = proxy_cache_dir() . '/rl_' . md5($ip . '|' . $slot) . '.txt';
    $count = is_file($file) ? (int)@file_get_contents($file) : 0;
    $count++;
    @file_put_contents($file, (string)$count, LOCK_EX);
    return $count <= $limit;
}

function proxy_resolve_cdn($id) {
    $cacheDir = __DIR__ . '/cache';
    if (!is_dir($cacheDir)) {
        @mkdir($cacheDir, 0775, true);
    }
    $cacheFile = $cacheDir . '/resolve_' . md5($id) . '.txt';

    if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < 10800)) {
        $cachedUrl = trim((string)@file_get_contents($cacheFile));
        if ($cachedUrl !== '') {
            return $cachedUrl;
        }
    }

    $url = "https://drive.usercontent.google.com/download?id={$id}&export=download&authuser=0&confirm=t";
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 10,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER         => false,
        CURLOPT_NOBODY         => false,
        CURLOPT_HTTPHEADER     => [
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            'Range: bytes=0-0',
        ],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_CONNECTTIMEOUT => 8,
    ]);
    curl_exec($ch);
    $finalUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    curl_close($ch);

    if ($finalUrl && (strpos($finalUrl, 'usercontent.google.com') !== false || strpos($finalUrl, 'googleusercontent.com') !== false)) {
        @file_put_contents($cacheFile, $finalUrl, LOCK_EX);
        return $finalUrl;
    }
    return null;
}

function proxy_send_local_pdf($path) {
    $size = filesize($path);
    $ttl = defined('PDF_CACHE_TTL') ? (int)PDF_CACHE_TTL : 2592000;
    $etag = '"' . md5_file($path) . '"';

    header('Content-Type: application/pdf');
    header('Accept-Ranges: bytes');
    header('Cache-Control: public, max-age=' . $ttl . ', s-maxage=' . $ttl);
    header('CDN-Cache-Control: max-age=' . $ttl);
    header('Cloudflare-CDN-Cache-Control: max-age=' . $ttl);
    header('ETag: ' . $etag);
    header('X-Cache: HIT');

    if (!empty($_SERVER['HTTP_IF_NONE_MATCH']) && trim($_SERVER['HTTP_IF_NONE_MATCH']) === $etag) {
        http_response_code(304);
        exit;
    }

    $start = 0;
    $end = $size - 1;
    if (!empty($_SERVER['HTTP_RANGE']) && preg_match('/bytes=(\d*)-(\d*)/', $_SERVER['HTTP_RANGE'], $m)) {
        if ($m[1] !== '') {
            $start = (int)$m[1];
        }
        if ($m[2] !== '') {
            $end = (int)$m[2];
        }
        if ($end >= $size) {
            $end = $size - 1;
        }
        if ($start > $end || $start >= $size) {
            http_response_code(416);
            header("Content-Range: bytes */{$size}");
            exit;
        }
        http_response_code(206);
        header("Content-Range: bytes {$start}-{$end}/{$size}");
    } else {
        http_response_code(200);
    }

    $length = $end - $start + 1;
    header('Content-Length: ' . $length);

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'HEAD') {
        exit;
    }

    while (ob_get_level()) {
        ob_end_clean();
    }

    $fp = fopen($path, 'rb');
    if (!$fp) {
        http_response_code(500);
        exit('Cache read failed');
    }
    fseek($fp, $start);
    $left = $length;
    while ($left > 0 && !feof($fp)) {
        $chunk = fread($fp, min(8192, $left));
        if ($chunk === false) {
            break;
        }
        echo $chunk;
        $left -= strlen($chunk);
        if (function_exists('flush')) {
            flush();
        }
    }
    fclose($fp);
    exit;
}

function proxy_download_to_cache($id, $streamUrl) {
    $path = proxy_pdf_path($id);
    $tmp = $path . '.part.' . getmypid();
    $lock = $path . '.lock';

    $lockFp = @fopen($lock, 'c');
    if ($lockFp) {
        flock($lockFp, LOCK_EX);
    }

    if (is_file($path) && filesize($path) > 1000) {
        if ($lockFp) {
            flock($lockFp, LOCK_UN);
            fclose($lockFp);
        }
        return $path;
    }

    $fp = fopen($tmp, 'wb');
    if (!$fp) {
        if ($lockFp) {
            flock($lockFp, LOCK_UN);
            fclose($lockFp);
        }
        return null;
    }

    $ch = curl_init($streamUrl);
    curl_setopt_array($ch, [
        CURLOPT_FILE           => $fp,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_TIMEOUT        => 180,
        CURLOPT_CONNECTTIMEOUT => 12,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        CURLOPT_HTTPHEADER     => ['Accept: application/pdf,*/*'],
    ]);
    $ok = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    fclose($fp);

    if (!$ok || $code >= 400 || !is_file($tmp) || filesize($tmp) < 1000) {
        @unlink($tmp);
        if ($lockFp) {
            flock($lockFp, LOCK_UN);
            fclose($lockFp);
        }
        return null;
    }

    @rename($tmp, $path);
    if ($lockFp) {
        flock($lockFp, LOCK_UN);
        fclose($lockFp);
    }
    @unlink($lock);
    return is_file($path) ? $path : null;
}

function proxy_stream_live($streamUrl) {
    if (strpos($streamUrl, 'usercontent.google.com') === false && strpos($streamUrl, 'googleusercontent.com') === false) {
        http_response_code(403);
        exit('Access denied');
    }

    $isHead = (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'HEAD');
    set_time_limit(120);
    $reqHeaders = ['User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'];
    if (!empty($_SERVER['HTTP_RANGE'])) {
        $reqHeaders[] = 'Range: ' . $_SERVER['HTTP_RANGE'];
    }

    $respContentType = '';
    $respContentLength = '';
    $respContentRange = '';
    $respAcceptRanges = '';
    $respStatusCode = 200;

    while (ob_get_level()) {
        ob_end_clean();
    }

    $ch = curl_init($streamUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => false,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_TIMEOUT        => 120,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_HTTPHEADER     => $reqHeaders,
        CURLOPT_NOBODY         => $isHead,
        CURLOPT_HEADERFUNCTION => function ($ch, $header) use (&$respContentType, &$respContentLength, &$respContentRange, &$respAcceptRanges, &$respStatusCode) {
            $len = strlen($header);
            $h = strtolower(trim($header));
            if (str_starts_with($h, 'http/')) {
                if (preg_match('/http\/[\d.]+ (\d+)/i', $h, $m)) {
                    $respStatusCode = (int)$m[1];
                }
            } elseif (str_starts_with($h, 'content-type:')) {
                $respContentType = trim(substr(trim($header), 13));
            } elseif (str_starts_with($h, 'content-length:')) {
                $respContentLength = trim(substr(trim($header), 15));
            } elseif (str_starts_with($h, 'content-range:')) {
                $respContentRange = trim(substr(trim($header), 14));
            } elseif (str_starts_with($h, 'accept-ranges:')) {
                $respAcceptRanges = trim(substr(trim($header), 15));
            }
            return $len;
        },
        CURLOPT_WRITEFUNCTION => function ($ch, $data) use (&$respContentType, &$respContentLength, &$respContentRange, &$respAcceptRanges, &$respStatusCode, $isHead) {
            static $headersSent = false;
            if (!$headersSent) {
                $headersSent = true;
                http_response_code($respStatusCode);
                header('Content-Type: ' . ($respContentType ?: 'application/pdf'));
                header('Accept-Ranges: ' . ($respAcceptRanges ?: 'bytes'));
                header('Cache-Control: public, max-age=86400, s-maxage=86400');
                header('X-Cache: MISS');
                if ($respContentLength !== '') {
                    header('Content-Length: ' . $respContentLength);
                }
                if ($respContentRange !== '') {
                    header('Content-Range: ' . $respContentRange);
                }
            }
            if ($isHead) {
                return strlen($data);
            }
            echo $data;
            if (function_exists('flush')) {
                flush();
            }
            return strlen($data);
        },
    ]);
    curl_exec($ch);
    curl_close($ch);
    exit;
}

if (isset($_GET['id']) && isset($_GET['resolve'])) {
    $id = proxy_clean_id($_GET['id']);
    if ($id === '' || strlen($id) < 10) {
        http_response_code(400);
        header('Content-Type: application/json');
        exit(json_encode(['error' => 'Invalid file ID']));
    }
    $finalUrl = proxy_resolve_cdn($id);
    header('Content-Type: application/json');
    header('Cache-Control: public, max-age=3600');
    if ($finalUrl) {
        echo json_encode(['url' => $finalUrl]);
    } else {
        http_response_code(502);
        echo json_encode(['error' => 'Could not resolve Google Drive URL. Make sure it is publicly shared.']);
    }
    exit;
}

if (isset($_GET['stream_url'])) {
    proxy_stream_live($_GET['stream_url']);
}

if (isset($_GET['warm']) && isset($_GET['id'])) {
    $id = proxy_clean_id($_GET['id']);
    if ($id === '' || strlen($id) < 10) {
        http_response_code(400);
        header('Content-Type: application/json');
        exit(json_encode(['error' => 'Invalid file ID']));
    }

    header('Content-Type: application/json');
    $path = proxy_pdf_path($id);
    $ttl = defined('PDF_CACHE_TTL') ? (int)PDF_CACHE_TTL : 2592000;
    $useDisk = !defined('PDF_DISK_CACHE') || PDF_DISK_CACHE;

    if ($useDisk && is_file($path) && filesize($path) > 1000 && (time() - filemtime($path) < $ttl)) {
        echo json_encode(['status' => 'cached']);
        exit;
    }

    $finalUrl = proxy_resolve_cdn($id);
    if (!$finalUrl) {
        http_response_code(502);
        echo json_encode(['error' => 'Could not resolve URL']);
        exit;
    }

    echo json_encode(['status' => 'warming']);
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    } else {
        ignore_user_abort(true);
        if (function_exists('session_write_close')) {
            session_write_close();
        }
    }

    if ($useDisk) {
        proxy_download_to_cache($id, $finalUrl);
    }
    exit;
}

if (isset($_GET['id']) && !isset($_GET['resolve'])) {
    $id = proxy_clean_id($_GET['id']);
    if ($id === '' || strlen($id) < 10) {
        http_response_code(400);
        exit('Invalid file ID');
    }

    if (!proxy_rate_limit($id)) {
        http_response_code(429);
        header('Retry-After: 60');
        exit('Too many requests');
    }

    $path = proxy_pdf_path($id);
    $ttl = defined('PDF_CACHE_TTL') ? (int)PDF_CACHE_TTL : 2592000;
    $useDisk = !defined('PDF_DISK_CACHE') || PDF_DISK_CACHE;

    if ($useDisk && is_file($path) && filesize($path) > 1000 && (time() - filemtime($path) < $ttl)) {
        proxy_send_local_pdf($path);
    }

    $finalUrl = proxy_resolve_cdn($id);
    if (!$finalUrl) {
        http_response_code(502);
        exit('Could not resolve Google Drive URL');
    }

    // Stream immediately with byte-range support — never block on full download
    proxy_stream_live($finalUrl);
}

http_response_code(400);
exit('Missing parameters.');
