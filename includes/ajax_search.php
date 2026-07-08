<?php
/**
 * AJAX Live Search Endpoint
 * Returns JSON array of matching novels (by title or writer name).
 */
ob_start(); // Buffer all output — prevents any PHP notice/warning from corrupting JSON

error_reporting(0);
ini_set('display_errors', 0);

require_once __DIR__ . '/../db.php';

ob_clean(); // Discard any output that happened during db.php include

// Build $site_url — same logic as includes/header.php
$scheme   = (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off') ? 'https' : 'http';
$site_url = $scheme . '://' . $_SERVER['HTTP_HOST'] . $base_path;

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

$q = isset($_GET['q']) ? trim($_GET['q']) : '';

if (strlen($q) < 2) {
    echo json_encode([]);
    exit;
}

$safe = $conn->real_escape_string($q);

$sql = "SELECT s.id, s.title, s.slug, s.image, w.name AS writer_name
        FROM posts s
        LEFT JOIN writers w ON s.writer_id = w.id
        WHERE s.status = 'active'
          AND (
            s.title LIKE '%$safe%'
            OR w.name LIKE '%$safe%'
          )
        ORDER BY s.id DESC
        LIMIT 12";

$result = $conn->query($sql);

if (!$result) {
    echo json_encode([]);
    exit;
}

$rows = [];
while ($row = $result->fetch_assoc()) {
    $link = (($site_settings['permalink_structure'] ?? '') === 'postname' && !empty($row['slug']))
        ? $site_url . '/' . $row['slug'] . '/'
        : $site_url . '/single.php?id=' . $row['id'];

    $img = !empty($row['image'])
        ? $site_url . '/' . ltrim(preg_replace('/^\.\.\//', '', $row['image']), '/')
        : 'https://placehold.co/300x450/004d5e/fff?text=' . urlencode($row['title']);

    $rows[] = [
        'title'       => $row['title'],
        'writer_name' => $row['writer_name'] ?? 'Unknown',
        'link'        => $link,
        'img'         => $img,
    ];
}

echo json_encode($rows);

