<?php
/**
 * content_brain.php — Single AI moderation brain (gpt-4o-mini).
 * Modes: comment | community_post
 */
if (!defined('PORTAL_APP')) {
    return;
}

require_once __DIR__ . '/ai_config.php';

function content_brain_enabled(): bool {
    return defined('CONTENT_BRAIN_ENABLED') && CONTENT_BRAIN_ENABLED
        && defined('OPENAI_API_KEY') && OPENAI_API_KEY !== ''
        && function_exists('curl_init');
}

function content_brain_has_link(string $text): bool {
    if ($text === '') {
        return false;
    }
    $patterns = [
        '~https?://~i',
        '~\bwww\.~i',
        '~\b[a-z0-9][-a-z0-9]*\.(com|net|org|io|co|pk|in|me|app|dev|xyz|info|biz|ly|to|link|online|site|club|edu|gov)\b~iu',
        '~\b(?:t\.me|wa\.me|bit\.ly|tinyurl|goo\.gl|telegram\.me)\b~iu',
    ];
    foreach ($patterns as $p) {
        if (preg_match($p, $text)) {
            return true;
        }
    }
    return false;
}

function content_brain_image_data_url(string $absPath, int $maxDim = 768): ?string {
    if (!is_file($absPath)) {
        return null;
    }
    $info = @getimagesize($absPath);
    if (!$info) {
        return null;
    }
    [$w, $h] = $info;
    $mime = $info['mime'] ?? 'image/jpeg';

    if (function_exists('imagecreatetruecolor') && ($w > $maxDim || $h > $maxDim)) {
        switch ($mime) {
            case 'image/jpeg': $src = @imagecreatefromjpeg($absPath); break;
            case 'image/png':  $src = @imagecreatefrompng($absPath); break;
            case 'image/gif':  $src = @imagecreatefromgif($absPath); break;
            case 'image/webp': $src = function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($absPath) : null; break;
            default: $src = null;
        }
        if ($src) {
            $scale = min($maxDim / $w, $maxDim / $h, 1);
            $nw = (int)max(1, round($w * $scale));
            $nh = (int)max(1, round($h * $scale));
            $dst = imagecreatetruecolor($nw, $nh);
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
            ob_start();
            imagejpeg($dst, null, 82);
            $bytes = ob_get_clean();
            imagedestroy($dst);
            imagedestroy($src);
            if ($bytes) {
                return 'data:image/jpeg;base64,' . base64_encode($bytes);
            }
        }
    }

    $raw = @file_get_contents($absPath);
    if (!$raw) {
        return null;
    }
    $b64 = base64_encode($raw);
    return 'data:' . $mime . ';base64,' . $b64;
}

function content_brain_prompt(string $mode): string {
    if ($mode === 'community_post') {
        return <<<'PROMPT'
You are the content moderation brain for a novels & quotes community (English and Urdu).
ALLOW and publish: novel excerpts, story text, poetry, literary quotes, reading thoughts — in English, Urdu script, or Roman Urdu.
BLOCK and reject: any URL/link or website mention, phone/WhatsApp/Telegram promotion, ads, spam, self-promotion, buy/sell, adult/sexual content, nudity, hate speech, threats, abusive/profane words (English, Urdu, Roman Urdu), violence, illegal content, completely off-topic spam.
For attached images: ALLOW images that show quotes, poetry, novel text, or harmless literary visuals. BLOCK images with adult/nudity, abusive text, promotional ads, watermarks with URLs, QR codes, or contact spam.
Return STRICT JSON only: {"allowed":true|false,"reason":"short user-friendly message if blocked, else empty string"}
PROMPT;
    }

    return <<<'PROMPT'
You are the comment moderation brain for a novel reading website (English and Urdu).
ALLOW: opinions, reviews, praise, criticism, emotions, discussion about the novel/story/characters — any respectful reader opinion.
BLOCK only: URLs/links, promotional spam, phone/contact spam, abusive/profane words (English, Urdu, Roman Urdu), sexual/adult content, hate speech, threats.
Do NOT block polite disagreement or negative opinions about the story.
Return STRICT JSON only: {"allowed":true|false,"reason":"short user-friendly message if blocked, else empty string"}
PROMPT;
}

function content_brain_openai(string $mode, string $text, array $imageAbsPaths): array {
    $model = defined('OPENAI_ALT_MODEL') ? OPENAI_ALT_MODEL : 'gpt-4o-mini';
    $userParts = [];

    $label = $mode === 'community_post' ? 'Community post' : 'Reader comment';
    $userParts[] = ['type' => 'text', 'text' => $label . " text:\n\"\"\"\n" . mb_substr($text, 0, 6000) . "\n\"\"\""];

    foreach ($imageAbsPaths as $path) {
        $dataUrl = content_brain_image_data_url($path);
        if ($dataUrl) {
            $userParts[] = ['type' => 'image_url', 'image_url' => ['url' => $dataUrl, 'detail' => 'low']];
        }
    }

    $payload = [
        'model' => $model,
        'temperature' => 0.1,
        'max_tokens' => 200,
        'response_format' => ['type' => 'json_object'],
        'messages' => [
            ['role' => 'system', 'content' => content_brain_prompt($mode)],
            ['role' => 'user', 'content' => $userParts],
        ],
    ];

    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . OPENAI_API_KEY,
        ],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT => 45,
    ]);
    $resp = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($resp === false || $code >= 400) {
        error_log('[content_brain] OpenAI error code=' . $code . ' body=' . substr((string)$resp, 0, 300));
        return ['ok' => false, 'reason' => 'Moderation service unavailable. Please try again in a moment.', 'api_error' => true];
    }

    $j = json_decode($resp, true);
    $content = $j['choices'][0]['message']['content'] ?? '';
    $parsed = json_decode($content, true);
    if (!is_array($parsed) || !isset($parsed['allowed'])) {
        return ['ok' => false, 'reason' => 'Moderation could not verify content. Please try again.', 'api_error' => true];
    }

    $allowed = (bool)$parsed['allowed'];
    $reason = trim((string)($parsed['reason'] ?? ''));
    if (!$allowed && $reason === '') {
        $reason = 'This content is not allowed on our platform.';
    }

    return ['ok' => $allowed, 'reason' => $reason, 'api_error' => false];
}

/**
 * @param string $mode  comment | community_post
 * @param string $text
 * @param string[] $imageAbsPaths absolute filesystem paths
 */
function content_brain_moderate(string $mode, string $text, array $imageAbsPaths = []): array {
    $mode = $mode === 'community_post' ? 'community_post' : 'comment';
    $text = trim($text);

    if ($text === '' && empty($imageAbsPaths)) {
        return ['ok' => false, 'reason' => 'Content cannot be empty.'];
    }

    if ($text !== '' && content_brain_has_link($text)) {
        return ['ok' => false, 'reason' => 'Links and URLs are not allowed.'];
    }

    if (!content_brain_enabled()) {
        if ($mode === 'community_post') {
            return ['ok' => false, 'reason' => 'Posting is temporarily unavailable. Please try again later.'];
        }
        return ['ok' => true, 'reason' => ''];
    }

    $result = content_brain_openai($mode, $text, $imageAbsPaths);

    if ($mode === 'comment' && !empty($result['api_error'])) {
        return ['ok' => true, 'reason' => ''];
    }

    return $result;
}
