<?php
/**
 * AI Image SEO — OpenAI Vision (gpt-4o-mini)
 *
 * Whenever an image is uploaded anywhere on the site, this reads the image AND
 * its filename, then generates proper ALT text, Caption and Description
 * (English or Urdu — auto-detected). Metadata is stored in the `media` table.
 *
 * Design goals (Google Images best practices + user spec):
 *   - Descriptive, keyword-rich, non-spammy ALT / caption / description
 *   - Original filename is preserved in the stored name + the DB id is appended
 *     for uniqueness/dedup  ->  e.g.  novel-cover-2026-12.webp
 *   - Non-blocking: if the AI call fails, the upload still succeeds (sensible fallbacks)
 */

require_once __DIR__ . '/ai_config.php';

/* ------------------------------------------------------------------ */
/* DB: media table (auto-created on first use)                         */
/* ------------------------------------------------------------------ */
function ensure_media_table($conn) {
    static $done = false;
    if ($done) return;
    $conn->query("CREATE TABLE IF NOT EXISTS media (
        id INT AUTO_INCREMENT PRIMARY KEY,
        filename VARCHAR(255) NOT NULL,
        original_name VARCHAR(255),
        path VARCHAR(500),
        alt_text VARCHAR(300),
        caption VARCHAR(300),
        description TEXT,
        mime VARCHAR(100),
        filesize INT,
        width INT,
        height INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_filename (filename),
        KEY idx_path (path)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $done = true;
}

/** Fetch stored SEO metadata for an image by its relative path (e.g. images/foo-12.webp). */
function get_media_by_path($conn, $path) {
    if (empty($path)) return null;
    ensure_media_table($conn);
    $path = ltrim(preg_replace('#^\.\./#', '', $path), '/');
    $stmt = $conn->prepare("SELECT * FROM media WHERE path = ? OR filename = ? LIMIT 1");
    $base = basename($path);
    $stmt->bind_param('ss', $path, $base);
    $stmt->execute();
    $res = $stmt->get_result();
    return $res ? $res->fetch_assoc() : null;
}

/* ------------------------------------------------------------------ */
/* Helpers                                                             */
/* ------------------------------------------------------------------ */
function _seo_trunc($text, $max) {
    $clean = trim(preg_replace('/\s+/u', ' ', $text));
    $clean = trim($clean, "\"' ");
    if (mb_strlen($clean) <= $max) return $clean;
    return rtrim(mb_substr($clean, 0, $max - 1)) . '…';
}

/** Downscale to a small JPEG data: URL for cheaper/faster vision inference (falls back to raw bytes). */
function _seo_image_data_url($absPath, $maxDim = 1024) {
    $info = @getimagesize($absPath);
    if (!$info) return null;
    [$w, $h] = $info;
    $mime = $info['mime'] ?? '';
    $src = null;
    if (function_exists('imagecreatetruecolor')) {
        switch ($mime) {
            case 'image/jpeg': $src = @imagecreatefromjpeg($absPath); break;
            case 'image/png':  $src = @imagecreatefrompng($absPath); break;
            case 'image/gif':  $src = @imagecreatefromgif($absPath); break;
            case 'image/webp': if (function_exists('imagecreatefromwebp')) $src = @imagecreatefromwebp($absPath); break;
        }
    }
    if (!$src) {
        // Fallback: send original bytes (works for any supported type, just larger)
        $data = @file_get_contents($absPath);
        if ($data === false) return null;
        return 'data:' . $mime . ';base64,' . base64_encode($data);
    }
    $scale = min(1, $maxDim / max($w, $h));
    $nw = max(1, (int)round($w * $scale));
    $nh = max(1, (int)round($h * $scale));
    $dst = imagecreatetruecolor($nw, $nh);
    $white = imagecolorallocate($dst, 255, 255, 255);
    imagefilledrectangle($dst, 0, 0, $nw, $nh, $white);
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
    ob_start(); imagejpeg($dst, null, 85); $jpg = ob_get_clean();
    imagedestroy($src); imagedestroy($dst);
    return 'data:image/jpeg;base64,' . base64_encode($jpg);
}

/* ------------------------------------------------------------------ */
/* Core: ask gpt-4o-mini to read the image + filename -> alt/caption/description */
/* ------------------------------------------------------------------ */
function generate_image_seo($absPath, $originalFilename = '') {
    $fail = ['alt' => null, 'caption' => null, 'description' => null];
    if (!AUTO_ALT_ENABLED || !defined('OPENAI_API_KEY') || !OPENAI_API_KEY) return $fail;
    if (!function_exists('curl_init')) return $fail;

    $dataUrl = _seo_image_data_url($absPath);
    if (!$dataUrl) return $fail;

    $hint = $originalFilename ?: basename($absPath);

    $lang = strtolower(AUTO_ALT_LANG);
    if (strpos($lang, 'ur') === 0)      $langInstruction = "Write the alt, caption and description in natural Urdu (Urdu script).";
    elseif (strpos($lang, 'en') === 0)  $langInstruction = "Write the alt, caption and description in clear English.";
    else $langInstruction = "Language: if the image's visible text or subject is Urdu, write everything in natural Urdu (Urdu script); otherwise write in clear English.";

    $system = "You are an expert SEO copywriter for a blog & story portal. "
        . "Carefully analyse the actual image content and also read the provided filename hint. "
        . "Return STRICT JSON with exactly these keys: alt, caption, description. "
        . "alt: concise, descriptive, keyword-rich alternative text UNDER 125 characters describing exactly what is visible "
        . "(people, university/campus, country/flag, documents, logos, subject). No filler such as 'image of', 'photo of'. "
        . "caption: one short, engaging sentence (max 160 chars) to display beneath the image. "
        . "description: 2-3 informative sentences (max 450 chars) describing the image and its relevance to the article/post. "
        . "Write naturally, never keyword-stuff, and do NOT invent specific facts you cannot actually see. "
        . $langInstruction;

    $payload = [
        'model' => OPENAI_ALT_MODEL,
        'temperature' => 0.3,
        'max_tokens' => 500,
        'response_format' => ['type' => 'json_object'],
        'messages' => [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => [
                ['type' => 'text', 'text' => "Generate SEO image metadata (alt, caption, description) for this image. Original filename hint: \"$hint\"."],
                ['type' => 'image_url', 'image_url' => ['url' => $dataUrl, 'detail' => 'auto']],
            ]],
        ],
    ];

    $attempts = 0;
    do {
        $attempts++;
        $ch = curl_init('https://api.openai.com/v1/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . OPENAI_API_KEY],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_TIMEOUT => 50,
        ]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $cerr = curl_error($ch);
        curl_close($ch);

        if ($resp !== false && $code < 400) break;            // success
        if (!in_array($code, [429, 500, 502, 503]) || $attempts >= 2) {
            error_log("[image_seo] OpenAI failed (code=$code) $cerr " . substr((string)$resp, 0, 300));
            return $fail;
        }
        usleep(1500000); // 1.5s before one retry
    } while ($attempts < 2);

    $j = json_decode($resp, true);
    $content = $j['choices'][0]['message']['content'] ?? '';
    if (!$content) return $fail;
    $parsed = json_decode($content, true);
    if (!is_array($parsed)) return $fail;

    return [
        'alt'         => isset($parsed['alt'])         ? _seo_trunc($parsed['alt'], 125)         : null,
        'caption'     => isset($parsed['caption'])     ? _seo_trunc($parsed['caption'], 160)     : null,
        'description' => isset($parsed['description']) ? _seo_trunc($parsed['description'], 450) : null,
    ];
}

/* ------------------------------------------------------------------ */
/* High-level upload handler used by every upload point                */
/* ------------------------------------------------------------------ */
/**
 * Validate + store an uploaded image with SEO metadata.
 *
 * @param mysqli $conn
 * @param array  $file          one entry of $_FILES (e.g. $_FILES['image_file'])
 * @param string $uploadDirAbs  absolute folder to move into (e.g. __DIR__.'/../images')
 * @param string $relDir        relative web path prefix stored in DB (e.g. 'images')
 * @return array ['ok'=>bool, 'error'=>?, 'id','filename','path','alt','caption','description']
 */
function media_store_upload($conn, $file, $uploadDirAbs, $relDir = 'images') {
    if (!isset($file) || $file['error'] !== 0) {
        return ['ok' => false, 'error' => 'No file or upload error.'];
    }
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed)) {
        return ['ok' => false, 'error' => 'Invalid file format. Allowed: ' . implode(', ', $allowed)];
    }

    ensure_media_table($conn);

    // Build a clean base slug from the ORIGINAL filename (kept in the final name)
    $base = pathinfo($file['name'], PATHINFO_FILENAME);
    $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $base));
    $slug = trim($slug, '-');
    if ($slug === '') $slug = 'image';

    // Reserve an id first so we can append it to the filename for uniqueness
    $origEsc = $conn->real_escape_string($file['name']);
    $conn->query("INSERT INTO media (filename, original_name, path) VALUES ('pending-" . uniqid() . "', '$origEsc', '')");
    $id = $conn->insert_id;

    $filename = $slug . '-' . $id . '.' . $ext;
    $absPath  = rtrim($uploadDirAbs, '/\\') . '/' . $filename;
    $relPath  = trim($relDir, '/') . '/' . $filename;

    if (!@move_uploaded_file($file['tmp_name'], $absPath)) {
        $conn->query("DELETE FROM media WHERE id = " . (int)$id);
        return ['ok' => false, 'error' => 'Failed to save file (check folder permissions).'];
    }

    // Dimensions / mime
    $w = $h = null; $mime = '';
    if ($ext !== 'svg' && ($info = @getimagesize($absPath))) {
        $w = $info[0]; $h = $info[1]; $mime = $info['mime'] ?? '';
    }
    $size = @filesize($absPath) ?: 0;

    // AI metadata (skip SVG — not a raster image the vision model can read well)
    $seo = ['alt' => null, 'caption' => null, 'description' => null];
    if ($ext !== 'svg') {
        $seo = generate_image_seo($absPath, $file['name']);
    }
    // Sensible fallback alt from the (human-readable) filename
    if (empty($seo['alt'])) {
        $seo['alt'] = ucwords(trim(str_replace('-', ' ', $slug)));
    }

    $stmt = $conn->prepare("UPDATE media SET filename=?, path=?, alt_text=?, caption=?, description=?, mime=?, filesize=?, width=?, height=? WHERE id=?");
    $stmt->bind_param('ssssssiiii', $filename, $relPath, $seo['alt'], $seo['caption'], $seo['description'], $mime, $size, $w, $h, $id);
    $stmt->execute();

    return [
        'ok' => true,
        'id' => $id,
        'filename' => $filename,
        'path' => $relPath,
        'alt' => $seo['alt'],
        'caption' => $seo['caption'],
        'description' => $seo['description'],
    ];
}
