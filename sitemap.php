<?php
require_once __DIR__ . '/db.php';

header('Content-Type: application/xml; charset=utf-8');
header('Cache-Control: public, max-age=3600');

$scheme = (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off') ? 'https' : 'http';
$site_host_url = $scheme . '://' . $_SERVER['HTTP_HOST'];
$site_url = rtrim($site_host_url . $base_path, '/');

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

$static_pages = [
    '/' => ['1.0', 'daily'],
    '/categories/' => ['0.8', 'weekly'],
    '/about/' => ['0.5', 'monthly'],
    '/contact/' => ['0.5', 'monthly'],
    '/community/' => ['0.6', 'daily'],
];

foreach ($static_pages as $route => $meta) {
    echo "  <url>\n";
    echo "    <loc>" . htmlspecialchars($site_url . $route) . "</loc>\n";
    echo "    <changefreq>{$meta[1]}</changefreq>\n";
    echo "    <priority>{$meta[0]}</priority>\n";
    echo "  </url>\n";
}

$categories = $conn->query("SELECT slug FROM categories WHERE slug IS NOT NULL AND slug <> '' ORDER BY id DESC");
if ($categories) {
    while ($cat = $categories->fetch_assoc()) {
        $loc = $site_host_url . get_category_link($cat['slug']);
        echo "  <url>\n";
        echo "    <loc>" . htmlspecialchars($loc) . "</loc>\n";
        echo "    <changefreq>weekly</changefreq>\n";
        echo "    <priority>0.7</priority>\n";
        echo "  </url>\n";
    }
}

$writers = $conn->query("SELECT slug FROM writers WHERE slug IS NOT NULL AND slug <> '' ORDER BY id DESC");
if ($writers) {
    while ($writer = $writers->fetch_assoc()) {
        $loc = $site_host_url . get_writer_link($writer['slug']);
        echo "  <url>\n";
        echo "    <loc>" . htmlspecialchars($loc) . "</loc>\n";
        echo "    <changefreq>weekly</changefreq>\n";
        echo "    <priority>0.7</priority>\n";
        echo "  </url>\n";
    }
}

$posts = $conn->query("SELECT id, slug, posted_date, updated_at FROM posts WHERE status = 'active' ORDER BY id DESC");
if ($posts) {
    while ($post = $posts->fetch_assoc()) {
        $loc = $site_host_url . get_post_link($post['id'], $post['slug']);
        $lastmodDate = (!empty($post['updated_at']) && $post['updated_at'] !== '0000-00-00 00:00:00')
            ? $post['updated_at']
            : $post['posted_date'];
        $lastmod = $lastmodDate ? date('c', strtotime($lastmodDate)) : '';
        echo "  <url>\n";
        echo "    <loc>" . htmlspecialchars($loc) . "</loc>\n";
        if ($lastmod) {
            echo "    <lastmod>{$lastmod}</lastmod>\n";
        }
        echo "    <changefreq>weekly</changefreq>\n";
        echo "    <priority>0.8</priority>\n";
        echo "  </url>\n";
    }
}

$pages = $conn->query("SELECT slug, updated_at FROM pages WHERE status = 'publish' AND slug IS NOT NULL AND slug <> '' ORDER BY id DESC");
if ($pages) {
    while ($page = $pages->fetch_assoc()) {
        $loc = $site_host_url . get_page_link($page['slug']);
        $lastmod = !empty($page['updated_at']) ? date('c', strtotime($page['updated_at'])) : '';
        echo "  <url>\n";
        echo "    <loc>" . htmlspecialchars($loc) . "</loc>\n";
        if ($lastmod) {
            echo "    <lastmod>{$lastmod}</lastmod>\n";
        }
        echo "    <changefreq>monthly</changefreq>\n";
        echo "    <priority>0.6</priority>\n";
        echo "  </url>\n";
    }
}

echo '</urlset>' . "\n";
