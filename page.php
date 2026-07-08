<?php
require_once 'includes/cache_start.php';
require_once 'db.php';

if (!isset($_GET['slug']) || empty($_GET['slug'])) {
    header("Location: " . $base_path . "/");
    exit;
}

$slug = $conn->real_escape_string($_GET['slug']);

$status_condition = isset($_SESSION['admin_logged_in']) ? "" : " AND status='publish'";
$query = "SELECT * FROM pages WHERE slug = '$slug' $status_condition";
$result = $conn->query($query);

if (!$result || $result->num_rows == 0) {
    http_response_code(404);
    require '404.php';
    exit;
}

$row = $result->fetch_assoc();

// Page SEO defaults handled dynamically in includes/header.php

// Redirect to pretty URL if accessed directly via page.php
$scheme = (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off') ? 'https' : 'http';
if (($site_settings['permalink_structure'] ?? '') === 'postname' && !empty($row['slug'])) {
    if (strpos($_SERVER['REQUEST_URI'], 'page.php') !== false) {
        $pretty_url = $scheme . '://' . $_SERVER['HTTP_HOST'] . get_page_link($row['slug']);
        header("HTTP/1.1 301 Moved Permanently");
        header("Location: " . $pretty_url);
        exit;
    }
}

// Canonical = this page's permalink
$canonical_url = ($site_settings['permalink_structure'] ?? '') === 'postname'
    ? $scheme . '://' . $_SERVER['HTTP_HOST'] . $base_path . '/' . $row['slug'] . '/'
    : $scheme . '://' . $_SERVER['HTTP_HOST'] . $base_path . '/page.php?slug=' . $row['slug'];

$is_custom_page = true;
include 'includes/header.php';
?>

<div class="border-b border-slate-100 bg-white/40 backdrop-blur-md py-12">
    <div class="global-container text-center">
        <h1 class="text-2xl md:text-4xl font-extrabold text-slate-900"><?php echo htmlspecialchars($row['title']); ?></h1>
    </div>
</div>

<div class="global-container py-12">
    <div class="p-1 md:p-3">
        <div class="prose prose-slate max-w-none article-content">
            <?php echo $row['content']; ?>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
<?php include 'includes/cache_end.php'; ?>
