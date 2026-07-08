<?php
require_once 'includes/cache_start.php';
require_once 'db.php';

// Force trailing slash redirect if permalink structure is postname
if (($site_settings['permalink_structure'] ?? '') === 'postname') {
    $request_uri = $_SERVER['REQUEST_URI'];
    $parsed_url = parse_url($request_uri);
    $path = $parsed_url['path'] ?? '';
    
    if (!empty($path) && substr($path, -1) !== '/') {
        $redirect_url = $path . '/';
        if (isset($parsed_url['query'])) {
            $redirect_url .= '?' . $parsed_url['query'];
        }
        header("HTTP/1.1 301 Moved Permanently");
        header("Location: " . $redirect_url);
        exit;
    }
}

if (isset($_GET['slug']) && !empty($_GET['slug'])) {
    $slug = $conn->real_escape_string($_GET['slug']);
    
    if ($slug === 'posts' || $slug === 'posts.php') {
        header("Location: " . $base_path . "/");
        exit;
    }
    
    // 1. Check if it's a category
    $catCheck = $conn->query("SELECT id FROM categories WHERE slug = '$slug'");
    if ($catCheck && $catCheck->num_rows > 0) {
        $_GET['category'] = $slug; // Pass the slug to archive.php
        require 'archive.php';
        exit;
    }
    
    // 1b. Check if it's a writer
    $writerCheck = $conn->query("SELECT id FROM writers WHERE slug = '$slug'");
    if ($writerCheck && $writerCheck->num_rows > 0) {
        $_GET['writer'] = $slug; // Pass the slug to archive.php
        require 'archive.php';
        exit;
    }
    
    // 2. Check if it's a post
    $postCheck = $conn->query("SELECT id FROM posts WHERE slug = '$slug'");
    if ($postCheck && $postCheck->num_rows > 0) {
        require 'single.php';
        exit;
    }
    
    // 3. Check if it's a page
    $pageCheck = $conn->query("SELECT id FROM pages WHERE slug = '$slug'");
    if ($pageCheck && $pageCheck->num_rows > 0) {
        require 'page.php';
        exit;
    }
    
    // 4. Fallback 404
    http_response_code(404);
    require '404.php';
    exit;
} else {
    header("Location: " . $base_path . "/");
    exit;
}
