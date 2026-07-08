<?php
/**
 * Image upload endpoint for the rich text editor (Summernote) and any AJAX uploader.
 * Saves the file to /images with AI-generated SEO metadata and returns JSON {url, alt, caption}.
 */
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../lib/image_seo.php';

if (!isset($_SESSION['admin_logged_in'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
}

// Summernote sends the file under "file"; accept a couple of common keys.
$file = $_FILES['file'] ?? $_FILES['media_file'] ?? $_FILES['image'] ?? null;
if (!$file) {
    http_response_code(400);
    echo json_encode(['error' => 'No file received']);
    exit;
}

$res = media_store_upload($conn, $file, __DIR__ . '/../images', 'images');
if (!$res['ok']) {
    http_response_code(400);
    echo json_encode(['error' => $res['error']]);
    exit;
}

global $base_path;
$scheme = (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off') ? 'https' : 'http';
$url = $scheme . '://' . $_SERVER['HTTP_HOST'] . $base_path . '/' . $res['path'];

echo json_encode([
    'url'         => $url,
    'path'        => $res['path'],
    'alt'         => $res['alt'],
    'caption'     => $res['caption'],
    'description' => $res['description'],
]);
