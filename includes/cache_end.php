<?php
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET' && isset($cache_file)) {
    $status = http_response_code();
    if ($status === false || (int)$status === 200) {
        $cached_content = ob_get_contents();
        if ($cached_content !== false && $cached_content !== '') {
            $file = @fopen($cache_file, 'w');
            if ($file) {
                fwrite($file, $cached_content);
                fclose($file);
            }
            if (!headers_sent()) {
                header('X-Cache: PAGE-MISS');
                header('Cache-Control: public, max-age=60, s-maxage=300');
            }
        }
    }
    if (ob_get_level() > 0) {
        ob_end_flush();
    }
}
?>
