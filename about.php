<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/includes/static_page.php';

$scheme = (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off') ? 'https' : 'http';
$site_url_base = rtrim($scheme . '://' . $_SERVER['HTTP_HOST'] . $base_path, '/');
$siteName = $site_settings['site_name'] ?? 'Kitab Nagri';

$aboutTitle = trim($site_settings['about_page_title'] ?? '') ?: 'About Us';
$aboutSubtitle = trim($site_settings['about_page_subtitle'] ?? '') ?: 'Your ultimate source for reading premium novels, stories, and books.';
$aboutContent = trim($site_settings['about_page_content'] ?? '');

$custom_meta_title = trim($site_settings['about_meta_title'] ?? '') ?: ('About Us - ' . $siteName);
$custom_meta_description = trim($site_settings['about_meta_description'] ?? '') ?: ('Learn about ' . $siteName . ' and our mission to share quality novels and stories.');
$canonical_url = $site_url_base . '/about/';

include 'includes/header.php';
?>

<section class="static-hero">
    <div class="global-container static-hero__inner">
        <h1 class="static-hero__title"><?php echo htmlspecialchars($aboutTitle); ?></h1>
        <p class="static-hero__subtitle"><?php echo htmlspecialchars($aboutSubtitle); ?></p>
    </div>
</section>

<section class="global-container static-body">
    <div class="static-card static-card--wide">
        <?php if ($aboutContent !== ''): ?>
            <div class="static-prose"><?php echo $aboutContent; ?></div>
        <?php else: ?>
            <div class="static-prose">
                <h2>Our Mission</h2>
                <p>At <?php echo htmlspecialchars($siteName); ?>, our core mission is to provide high-quality novels, stories, and series across a variety of genres.</p>
                <p>We believe in spreading the joy of reading and supporting talented writers. Our platform is dedicated to curating and providing updated, readable, and accessible novels for readers around the globe.</p>
            </div>
        <?php endif; ?>
        <div class="static-cta">
            <a href="<?php echo $site_url; ?>/" class="btn-brand">Start Exploring Novels</a>
        </div>
    </div>
</section>

<?php include 'includes/footer.php'; ?>
