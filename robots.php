<?php
require_once __DIR__ . '/db.php';

header('Content-Type: text/plain; charset=utf-8');
header('X-Robots-Tag: noindex');
header('Cache-Control: public, max-age=3600');

$scheme = (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off') ? 'https' : 'http';
$site_url = rtrim($scheme . '://' . $_SERVER['HTTP_HOST'] . $base_path, '/');

echo "User-agent: *\n";
echo "Allow: /\n\n";

echo "Disallow: /newlogin/\n";
echo "Disallow: /includes/\n";
echo "Disallow: /lib/\n";
echo "Disallow: /cache/\n";
echo "Disallow: /vendor/\n";
echo "Disallow: /node_modules/\n";
echo "Disallow: /proxy.php\n";
echo "Disallow: /reader.php\n";
echo "Disallow: /community_api.php\n";
echo "Disallow: /community_auth.php\n";
echo "Disallow: /*?search=\n";
echo "Disallow: /*?page=\n\n";

echo "Sitemap: {$site_url}/sitemap.xml\n";
