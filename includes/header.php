<?php
if (session_status() === PHP_SESSION_NONE && !empty($_COOKIE[session_name()])) {
    session_start();
}
if (!isset($conn) || !isset($site_settings)) {
    require_once __DIR__ . '/../db.php';
}
global $site_settings, $row, $conn, $base_path;
if (!is_array($site_settings)) {
    $site_settings = ['site_name' => 'Kitab Nagri', 'seo_description' => '', 'seo_keywords' => '', 'theme_primary' => '#0f2c5c', 'theme_secondary' => '#1a56db', 'theme_hover' => '#fbbf24'];
}

$scheme = (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off') ? 'https' : 'http';
$site_url = rtrim($scheme . "://" . $_SERVER['HTTP_HOST'] . $base_path, '/');

if (empty($custom_meta_title) && !empty($custom_page_title)) {
    $custom_meta_title = $custom_page_title;
}
if (empty($custom_meta_description) && !empty($custom_page_description)) {
    $custom_meta_description = $custom_page_description;
}

if (empty($canonical_url)) {
    $req_path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $canonical_url = $scheme . "://" . $_SERVER['HTTP_HOST'] . $req_path;
}
$canonical_url = preg_replace('/\?.*$/', '', $canonical_url);
if (substr($canonical_url, -1) !== '/' && !preg_match('/\.[a-z0-9]{2,5}$/i', $canonical_url)) {
    if (($site_settings['permalink_structure'] ?? '') === 'postname') {
        $canonical_url .= '/';
    }
}

$og_image = '';

if (isset($is_homepage) && $is_homepage) {
    $page_title = !empty($site_settings['homepage_title']) 
        ? htmlspecialchars($site_settings['homepage_title']) 
        : htmlspecialchars(trim(($site_settings['site_name'] ?? 'Kitab Nagri') . (!empty($site_settings['tagline']) ? ' - ' . $site_settings['tagline'] : '')));
    $meta_desc = !empty($site_settings['homepage_description']) 
        ? htmlspecialchars($site_settings['homepage_description']) 
        : htmlspecialchars($site_settings['seo_description'] ?? '');
    $og_image = !empty($site_settings['homepage_featured_image']) 
        ? $site_settings['homepage_featured_image'] 
        : ($site_settings['site_logo'] ?? '');
} elseif (isset($is_single_post) && $is_single_post && is_array($row)) {
    $page_title = !empty($row['meta_title']) 
        ? htmlspecialchars($row['meta_title']) 
        : htmlspecialchars($row['title'] . ' - ' . $site_settings['site_name']);
    $meta_desc = !empty($row['meta_description']) 
        ? htmlspecialchars($row['meta_description']) 
        : htmlspecialchars(mb_substr(trim(preg_replace('/\s+/', ' ', strip_tags($row['description'] ?? ''))), 0, 155));
    $og_image = $row['image'] ?? '';
    $_datePublIso = !empty($row['posted_date']) ? date('c', strtotime($row['posted_date'])) : '';
    $_dateModIso  = !empty($row['updated_at']) ? date('c', strtotime($row['updated_at'])) : $_datePublIso;
} elseif (isset($is_custom_page) && $is_custom_page && is_array($row)) {
    $page_title = !empty($row['meta_title']) 
        ? htmlspecialchars($row['meta_title']) 
        : htmlspecialchars($row['title'] . ' - ' . $site_settings['site_name']);
    $meta_desc = !empty($row['meta_description']) 
        ? htmlspecialchars($row['meta_description']) 
        : htmlspecialchars(mb_substr(trim(preg_replace('/\s+/', ' ', strip_tags($row['content'] ?? ''))), 0, 155));
    $og_image = !empty($site_settings['homepage_featured_image']) 
        ? $site_settings['homepage_featured_image'] 
        : ($site_settings['site_logo'] ?? '');
    $_datePublIso = !empty($row['created_at']) ? date('c', strtotime($row['created_at'])) : '';
    $_dateModIso  = !empty($row['updated_at']) ? date('c', strtotime($row['updated_at'])) : $_datePublIso;
} elseif (!empty($custom_meta_title)) {
    $page_title = htmlspecialchars($custom_meta_title);
    $meta_desc = htmlspecialchars($custom_meta_description ?? ($site_settings['seo_description'] ?? ''));
    if (isset($catData) && !empty($catData['image'])) {
        $og_image = $catData['image'];
    } elseif (isset($writerData) && !empty($writerData['profile_pic'])) {
        $og_image = $writerData['profile_pic'];
    } else {
        $og_image = !empty($site_settings['homepage_featured_image']) 
            ? $site_settings['homepage_featured_image'] 
            : ($site_settings['site_logo'] ?? '');
    }
} else {
    $page_title = htmlspecialchars($site_settings['site_name'] ?? 'Kitab Nagri');
    $meta_desc = htmlspecialchars($site_settings['seo_description'] ?? '');
    $og_image = !empty($site_settings['homepage_featured_image']) 
        ? $site_settings['homepage_featured_image'] 
        : ($site_settings['site_logo'] ?? '');
}

$logo_width_mobile = $site_settings['logo_width_mobile'] ?? 'auto';
if (is_numeric($logo_width_mobile)) $logo_width_mobile .= 'px';
$logo_width_desktop = $site_settings['logo_width_desktop'] ?? 'auto';
if (is_numeric($logo_width_desktop)) $logo_width_desktop .= 'px';

// ── Build the navigation menu once (reused for desktop + mobile) ──
$nav_links = [];
if ($conn) {
    $menuItemsQuery = $conn->query("SELECT * FROM menu_items ORDER BY sort_order ASC");
    if ($menuItemsQuery && $menuItemsQuery->num_rows > 0) {
        while ($m = $menuItemsQuery->fetch_assoc()) {
            $url = $m['url'];
            if (!preg_match("~^(?:f|ht)tps?://~i", $url)) {
                if (($site_settings['permalink_structure'] ?? '') === 'postname' && !empty($url)) {
                    $clean_url = trim($url, '/');
                    if (!empty($clean_url) && strpos($clean_url, '.php') === false && strpos($clean_url, '#') === false && strpos($clean_url, '?') === false) {
                        $url = $clean_url . '/';
                    }
                }
                $url = $site_url . '/' . ltrim($url, '/');
            }
            $nav_links[] = ['label' => $m['label'], 'url' => $url];
        }
    }
}
// Fallback menu if none configured in admin
if (empty($nav_links)) {
    $nav_links[] = ['label' => 'Home', 'url' => $site_url . '/'];
    if ($conn) {
        $navCats = $conn->query("SELECT * FROM categories ORDER BY name ASC LIMIT 8");
        if ($navCats && $navCats->num_rows > 0) {
            while ($c = $navCats->fetch_assoc()) {
                $nav_links[] = ['label' => $c['name'], 'url' => get_category_link($c['slug'])];
            }
        }
    }
    $nav_links[] = ['label' => 'Shop', 'url' => $site_url . '/shop/'];
    $nav_links[] = ['label' => 'Community', 'url' => $site_url . '/community.php'];
    $nav_links[] = ['label' => 'About Us', 'url' => $site_url . '/about/'];
    $nav_links[] = ['label' => 'Contact', 'url' => $site_url . '/contact/'];
}
if (empty($_SESSION['admin_logged_in'])) {
    require_once dirname(__DIR__) . '/lib/coming_soon.php';
    $nav_links = array_values(array_filter($nav_links, function ($link) {
        global $site_settings;
        $url = strtolower($link['url'] ?? '');
        $label = strtolower($link['label'] ?? '');
        if (!empty($site_settings['shop_coming_soon']) && (strpos($url, '/shop') !== false || $label === 'shop')) {
            return coming_soon_has_preview_access('shop');
        }
        if (!empty($site_settings['community_coming_soon']) && (strpos($url, '/community') !== false || $label === 'community')) {
            return coming_soon_has_preview_access('community');
        }
        return true;
    }));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <meta name="description" content="<?php echo $meta_desc; ?>">
    <?php if(!empty($site_settings['seo_keywords'])): ?>
    <meta name="keywords" content="<?php echo htmlspecialchars($site_settings['seo_keywords']); ?>">
    <?php endif; ?>
    <?php
    if (!isset($meta_robots)) {
        $script_name = basename($_SERVER['SCRIPT_NAME'] ?? '');
        $has_filters = !empty($_GET['search']) || (!empty($_GET['page']) && (int)$_GET['page'] > 1);
        $is_404 = (http_response_code() === 404);
        if ($is_404 || $has_filters || $script_name === 'reader.php' || $script_name === 'community_post.php' || strpos($_SERVER['REQUEST_URI'] ?? '', '/store/') !== false) {
            $meta_robots = 'noindex, follow';
        } else {
            $meta_robots = 'index, follow';
        }
    }
    ?>
    <meta name="robots" content="<?php echo $meta_robots; ?>">
    <link rel="alternate" hreflang="en" href="<?php echo htmlspecialchars($canonical_url); ?>">
    <link rel="alternate" hreflang="x-default" href="<?php echo htmlspecialchars($canonical_url); ?>">

    <?php if (isset($is_single_post) && $is_single_post && !empty($row['writer_name'])): ?>
    <meta name="author" content="<?php echo htmlspecialchars($row['writer_name']); ?>">
    <?php endif; ?>

    <meta name="publisher" content="<?php echo htmlspecialchars($site_settings['site_name']); ?>">
    <?php if (!empty($site_settings['facebook_url'])): ?>
    <meta property="article:publisher" content="<?php echo htmlspecialchars($site_settings['facebook_url']); ?>">
    <link rel="publisher" href="<?php echo htmlspecialchars($site_settings['facebook_url']); ?>">
    <?php else: ?>
    <link rel="publisher" href="<?php echo $site_url; ?>/">
    <?php endif; ?>


    <!-- Canonical URL -->
    <link rel="canonical" href="<?php echo htmlspecialchars($canonical_url); ?>">

    <!-- Article Date Meta Tags -->
    <?php if (!empty($_datePublIso)): ?>
    <meta property="article:published_time" content="<?php echo $_datePublIso; ?>">
    <meta name="publish-date" content="<?php echo $_datePublIso; ?>">
    <?php endif; ?>
    <?php if (!empty($_dateModIso)): ?>
    <meta property="article:modified_time" content="<?php echo $_dateModIso; ?>">
    <meta property="og:updated_time" content="<?php echo $_dateModIso; ?>">
    <meta name="modified-date" content="<?php echo $_dateModIso; ?>">
    <?php endif; ?>

    <!-- Open Graph / Social SEO -->
    <meta property="og:site_name" content="<?php echo htmlspecialchars($site_settings['site_name']); ?>">
    <meta property="og:title" content="<?php echo $page_title; ?>">
    <meta property="og:description" content="<?php echo $meta_desc; ?>">
    <meta property="og:url" content="<?php echo htmlspecialchars($canonical_url); ?>">
    <meta property="og:type" content="<?php echo (isset($is_single_post) && $is_single_post) ? 'article' : 'website'; ?>">
    <?php if(!empty($og_image)): 
        $og_image_url = (strpos($og_image, 'http') === 0) 
            ? $og_image 
            : $site_url . '/' . ltrim(preg_replace('/^\.\.\//', '', $og_image), '/');
    ?>
    <meta property="og:image" content="<?php echo htmlspecialchars($og_image_url); ?>">
    <?php endif; ?>

    <!-- Twitter Card SEO -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?php echo $page_title; ?>">
    <meta name="twitter:description" content="<?php echo $meta_desc; ?>">
    <?php if(!empty($og_image_url)): ?>
    <meta name="twitter:image" content="<?php echo htmlspecialchars($og_image_url); ?>">
    <?php endif; ?>


    <?php
    if (!function_exists('_jse')) {
        function _jse(string $s): string { return addslashes(htmlspecialchars_decode($s, ENT_QUOTES)); }
    }
    if (!function_exists('_imgUrl')) {
        function _imgUrl(string $path, string $siteUrl): string {
            if (empty($path)) return '';
            if (strpos($path, 'http') === 0) return $path;
            return $siteUrl . '/' . ltrim(preg_replace('/^\.\.\//', '', $path), '/');
        }
    }
    $_sn = _jse($site_settings['site_name'] ?? 'Kitab Nagri');
    $_su = rtrim($site_url, '/');
    $_logoUrl = !empty($site_settings['site_logo']) ? _imgUrl($site_settings['site_logo'], $_su) : '';
    ?>

    <!-- 1. WebSite Schema — Every Page -->
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "WebSite",
      "@id": "<?php echo $_su; ?>/#website",
      "name": "<?php echo $_sn; ?>",
      "url": "<?php echo $_su; ?>/",
      "description": "<?php echo _jse($site_settings['homepage_description'] ?? $site_settings['seo_description'] ?? ''); ?>",
      "inLanguage": "en",
      "publisher": { "@id": "<?php echo $_su; ?>/#organization" }
    }
    </script>

    <!-- 2. Organization Schema — Every Page (used as publisher reference) -->
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "Organization",
      "@id": "<?php echo $_su; ?>/#organization",
      "name": "<?php echo $_sn; ?>",
      "url": "<?php echo $_su; ?>/",
      <?php if (!empty($_logoUrl)): ?>
      "logo": {
        "@type": "ImageObject",
        "url": "<?php echo $_logoUrl; ?>",
        "caption": "<?php echo $_sn; ?>"
      },
      <?php endif; ?>
      "sameAs": [
        <?php
        $sameAs = [];
        if (!empty($site_settings['facebook_url']))  $sameAs[] = '"' . _jse($site_settings['facebook_url']) . '"';
        if (!empty($site_settings['instagram_url'])) $sameAs[] = '"' . _jse($site_settings['instagram_url']) . '"';
        if (!empty($site_settings['tiktok_url']))    $sameAs[] = '"' . _jse($site_settings['tiktok_url']) . '"';
        if (!empty($site_settings['youtube_url']))   $sameAs[] = '"' . _jse($site_settings['youtube_url']) . '"';
        echo implode(",\n        ", $sameAs);
        ?>
      ]
      <?php if (!empty($site_settings['contact_email'])): ?>
      ,"contactPoint": {
        "@type": "ContactPoint",
        "email": "<?php echo _jse($site_settings['contact_email']); ?>",
        "contactType": "customer support",
        "availableLanguage": ["English"]
      }
      <?php endif; ?>
    }
    </script>

    <?php if (isset($is_single_post) && $is_single_post && is_array($row) && !empty($row['title'])):
        $_postImg   = !empty($row['image']) ? _imgUrl($row['image'], $_su) : '';
        $_writerId  = $_su . '/writer/' . _jse($row['writer_slug'] ?? '');
        $_postUrl   = htmlspecialchars($canonical_url);
        $_writerName = _jse($row['writer_name'] ?? $site_settings['site_name']);
    ?>

    <!-- 3. Book Schema — Single Novel Page -->
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "Book",
      "@id": "<?php echo $_postUrl; ?>#book",
      "name": "<?php echo _jse($row['title']); ?>",
      "url": "<?php echo $_postUrl; ?>",
      <?php if (!empty($_postImg)): ?>
      "image": "<?php echo $_postImg; ?>",
      <?php endif; ?>
      "description": "<?php echo _jse(substr(strip_tags($row['description'] ?? ''), 0, 300)); ?>",
      "inLanguage": "en",
      "bookFormat": "https://schema.org/EBook",
      <?php if (!empty($_datePublIso)): ?>
      "datePublished": "<?php echo $_datePublIso; ?>",
      <?php endif; ?>
      "author": {
        "@type": "Person",
        "@id": "<?php echo $_writerId; ?>/#author",
        "name": "<?php echo $_writerName; ?>"
      },
      "publisher": { "@id": "<?php echo $_su; ?>/#organization" }
      <?php if (!empty($row['file_type'])): ?>
      ,"potentialAction": {
        "@type": "ReadAction",
        "target": ["<?php echo $_postUrl; ?>"]
      }
      <?php endif; ?>
    }
    </script>

    <!-- 4. Article Schema — Single Novel Page (for crawlers that prefer Article) -->
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "Article",
      "@id": "<?php echo $_postUrl; ?>#article",
      "isPartOf": { "@id": "<?php echo $_su; ?>/#website" },
      "headline": "<?php echo _jse($page_title); ?>",
      <?php if (!empty($_postImg)): ?>
      "image": {
        "@type": "ImageObject",
        "url": "<?php echo $_postImg; ?>"
      },
      <?php endif; ?>
      <?php if (!empty($_datePublIso)): ?>
      "datePublished": "<?php echo $_datePublIso; ?>",
      "dateModified": "<?php echo $_dateModIso; ?>",
      <?php endif; ?>
      "author": {
        "@type": "Person",
        "@id": "<?php echo $_writerId; ?>/#author",
        "name": "<?php echo $_writerName; ?>"
      },
      "publisher": { "@id": "<?php echo $_su; ?>/#organization" },
      "mainEntityOfPage": { "@id": "<?php echo $_postUrl; ?>#article" },
      "articleSection": "<?php echo _jse($row['category'] ?? 'Novels'); ?>",
      "inLanguage": "en"
    }
    </script>

    <!-- 5. BreadcrumbList — Single Novel Page -->
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "BreadcrumbList",
      "itemListElement": [
        {
          "@type": "ListItem",
          "position": 1,
          "name": "Home",
          "item": "<?php echo $_su; ?>/"
        },
        <?php if (!empty($row['category'])): ?>
        {
          "@type": "ListItem",
          "position": 2,
          "name": "<?php echo _jse($row['category']); ?>",
          "item": "<?php echo get_category_link(strtolower(str_replace(' ', '-', $row['category'] ?? ''))); ?>"
        },
        {
          "@type": "ListItem",
          "position": 3,
          "name": "<?php echo _jse($row['title']); ?>",
          "item": "<?php echo $_postUrl; ?>"
        }
        <?php else: ?>
        {
          "@type": "ListItem",
          "position": 2,
          "name": "<?php echo _jse($row['title']); ?>",
          "item": "<?php echo $_postUrl; ?>"
        }
        <?php endif; ?>
      ]
    }
    </script>

    <?php endif; ?>

    <?php if (isset($is_homepage) && $is_homepage): ?>

    <!-- 6. WebPage Schema — Homepage -->
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "WebPage",
      "@id": "<?php echo $_su; ?>/#webpage",
      "url": "<?php echo $_su; ?>/",
      "name": "<?php echo _jse($page_title); ?>",
      "description": "<?php echo _jse($meta_desc); ?>",
      "isPartOf": { "@id": "<?php echo $_su; ?>/#website" },
      "about": { "@id": "<?php echo $_su; ?>/#organization" },
      "inLanguage": "en"
    }
    </script>

    <?php endif; ?>

    <?php if (!empty($catData) && isset($catData['name'])): 
        $_catUrl = htmlspecialchars($canonical_url);
    ?>

    <!-- 7. CollectionPage Schema — Category Archive -->
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "CollectionPage",
      "@id": "<?php echo $_catUrl; ?>#collectionpage",
      "url": "<?php echo $_catUrl; ?>",
      "name": "<?php echo _jse($catData['name']); ?> Novels - <?php echo $_sn; ?>",
      "description": "<?php echo _jse($catData['description'] ?? 'Explore novels in ' . $catData['name']); ?>",
      "isPartOf": { "@id": "<?php echo $_su; ?>/#website" },
      "inLanguage": "en"
    }
    </script>

    <!-- 7b. BreadcrumbList — Category Archive -->
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "BreadcrumbList",
      "itemListElement": [
        { "@type": "ListItem", "position": 1, "name": "Home", "item": "<?php echo $_su; ?>/" },
        { "@type": "ListItem", "position": 2, "name": "<?php echo _jse($catData['name']); ?>", "item": "<?php echo $_catUrl; ?>" }
      ]
    }
    </script>

    <?php endif; ?>

    <?php if (!empty($writerData) && isset($writerData['name'])):
        $_writerUrl = htmlspecialchars($canonical_url);
    ?>

    <!-- 8. Person + ProfilePage Schema — Writer Archive -->
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "ProfilePage",
      "@id": "<?php echo $_writerUrl; ?>#profilepage",
      "url": "<?php echo $_writerUrl; ?>",
      "name": "<?php echo _jse($writerData['name']); ?> - Novels",
      "description": "<?php echo _jse(substr(strip_tags($writerData['bio'] ?? ''), 0, 250)); ?>",
      "mainEntity": {
        "@type": "Person",
        "@id": "<?php echo $_writerUrl; ?>/#author",
        "name": "<?php echo _jse($writerData['name']); ?>",
        "description": "<?php echo _jse(substr(strip_tags($writerData['bio'] ?? ''), 0, 250)); ?>",
        "url": "<?php echo $_writerUrl; ?>",
        "worksFor": { "@id": "<?php echo $_su; ?>/#organization" }
      },
      "isPartOf": { "@id": "<?php echo $_su; ?>/#website" },
      "inLanguage": "en"
    }
    </script>

    <!-- 8b. BreadcrumbList — Writer Archive -->
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "BreadcrumbList",
      "itemListElement": [
        { "@type": "ListItem", "position": 1, "name": "Home", "item": "<?php echo $_su; ?>/" },
        { "@type": "ListItem", "position": 2, "name": "<?php echo _jse($writerData['name']); ?>", "item": "<?php echo $_writerUrl; ?>" }
      ]
    }
    </script>

    <?php endif; ?>

    <?php if (isset($is_custom_page) && $is_custom_page && is_array($row) && !empty($row['title'])): 
        $_pageUrl = htmlspecialchars($canonical_url);
    ?>

    <!-- 9. WebPage Schema — Custom Static Page -->
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "WebPage",
      "@id": "<?php echo $_pageUrl; ?>#webpage",
      "url": "<?php echo $_pageUrl; ?>",
      "name": "<?php echo _jse($row['title']); ?> - <?php echo $_sn; ?>",
      "description": "<?php echo _jse(substr(strip_tags($row['content'] ?? ''), 0, 250)); ?>",
      "isPartOf": { "@id": "<?php echo $_su; ?>/#website" },
      "inLanguage": "en"
    }
    </script>

    <!-- 9b. BreadcrumbList — Custom Static Page -->
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "BreadcrumbList",
      "itemListElement": [
        { "@type": "ListItem", "position": 1, "name": "Home", "item": "<?php echo $_su; ?>/" },
        { "@type": "ListItem", "position": 2, "name": "<?php echo _jse($row['title']); ?>", "item": "<?php echo $_pageUrl; ?>" }
      ]
    }
    </script>

    <?php endif; ?>



    <?php if(!empty($site_settings['site_favicon'])):
        $faviconPath = $site_url . '/' . htmlspecialchars(preg_replace('/^\.\.\//', '', $site_settings['site_favicon']));
    ?>
    <link rel="icon" href="<?php echo $faviconPath; ?>" type="image/x-icon">
    <link rel="shortcut icon" href="<?php echo $faviconPath; ?>" type="image/x-icon">
    <link rel="apple-touch-icon" href="<?php echo $faviconPath; ?>">
    <?php endif; ?>

    <!-- Google Search Console Verification -->
    <?php if(!empty($site_settings['search_console'])): ?>
    <?php echo $site_settings['search_console']; ?>
    <?php endif; ?>

    <!-- Google Analytics Tracking -->
    <?php if(!empty($site_settings['google_analytics'])): ?>
    <?php echo $site_settings['google_analytics']; ?>
    <?php endif; ?>

    <!-- LaraPush Integration -->
    <?php if(!empty($site_settings['larapush_code'])): ?>
    <?php echo $site_settings['larapush_code']; ?>
    <?php endif; ?>

    <!-- LCP Image Preload -->
    <?php if (!empty($lcp_image_url)): 
        $lcp_full_url = (strpos($lcp_image_url, 'http') === 0) 
            ? $lcp_image_url 
            : $site_url . '/' . ltrim(preg_replace('/^\.\.\//', '', $lcp_image_url), '/');
    ?>
    <link rel="preload" as="image" href="<?php echo htmlspecialchars($lcp_full_url); ?>" fetchpriority="high">
    <?php endif; ?>

    <?php
    $_css_tailwind_v = @filemtime(__DIR__ . '/../css/tailwind.css') ?: '1';
    $_css_style_v = @filemtime(__DIR__ . '/../css/style.css') ?: '1';
    $_font_base = $site_url . '/css/fonts';
    ?>
    <link rel="preload" as="font" type="font/woff2" href="<?php echo $_font_base; ?>/outfit-latin-400-normal.woff2" crossorigin>
    <link rel="preload" as="font" type="font/woff2" href="<?php echo $_font_base; ?>/outfit-latin-600-normal.woff2" crossorigin>
    <link rel="preload" as="font" type="font/woff2" href="<?php echo $_font_base; ?>/outfit-latin-700-normal.woff2" crossorigin>
    <style>
    @font-face{font-family:"Outfit";font-style:normal;font-weight:400;font-display:block;src:url("<?php echo $_font_base; ?>/outfit-latin-400-normal.woff2") format("woff2")}
    @font-face{font-family:"Outfit";font-style:normal;font-weight:500;font-display:block;src:url("<?php echo $_font_base; ?>/outfit-latin-500-normal.woff2") format("woff2")}
    @font-face{font-family:"Outfit";font-style:normal;font-weight:600;font-display:block;src:url("<?php echo $_font_base; ?>/outfit-latin-600-normal.woff2") format("woff2")}
    @font-face{font-family:"Outfit";font-style:normal;font-weight:700;font-display:block;src:url("<?php echo $_font_base; ?>/outfit-latin-700-normal.woff2") format("woff2")}
    @font-face{font-family:"Outfit";font-style:normal;font-weight:800;font-display:block;src:url("<?php echo $_font_base; ?>/outfit-latin-800-normal.woff2") format("woff2")}
    :root {
        --theme-primary: <?php echo htmlspecialchars($site_settings['theme_primary'] ?? '#0f2c5c'); ?>;
        --theme-primary-rgb: <?php echo hex2rgb($site_settings['theme_primary'] ?? '#0f2c5c'); ?>;
        --theme-secondary: <?php echo htmlspecialchars($site_settings['theme_secondary'] ?? '#1a56db'); ?>;
        --theme-secondary-rgb: <?php echo hex2rgb($site_settings['theme_secondary'] ?? '#1a56db'); ?>;
        --theme-hover: <?php echo htmlspecialchars($site_settings['theme_hover'] ?? '#fbbf24'); ?>;
        --font-sans: "Outfit", ui-sans-serif, system-ui, sans-serif;
    }
    .logo-img{width:<?php echo htmlspecialchars($logo_width_mobile); ?>!important;height:auto!important;display:block!important;margin:0!important}
    @media (min-width:1024px){.logo-img{width:<?php echo htmlspecialchars($logo_width_desktop); ?>!important}}
    body{position:relative;background-color:#fff!important;font-family:var(--font-sans)!important;color:#2c3e50;font-size:1.02rem;line-height:1.7;overflow-x:hidden}
    h1,h2,h3,h4,h5,h6{font-family:var(--font-sans)!important;font-weight:600!important;color:#1e293b}
    body::before{content:'';position:absolute;top:0;left:0;right:0;height:1000px;z-index:-1;pointer-events:none;background:radial-gradient(1200px 800px at 50% 0%,rgba(var(--theme-primary-rgb),.22),rgba(var(--theme-primary-rgb),.05) 45%,transparent 70%),radial-gradient(900px 700px at 85% 50%,rgba(var(--theme-secondary-rgb),.14),transparent 60%)}
    @media (max-width:768px){body{padding-bottom:80px!important}}
    </style>
    <link rel="stylesheet" href="<?php echo $site_url; ?>/css/tailwind.css?v=<?php echo $_css_tailwind_v; ?>">
    <link rel="stylesheet" href="<?php echo $site_url; ?>/css/style.css?v=<?php echo $_css_style_v; ?>">
    <link rel="stylesheet" href="<?php echo $site_url; ?>/vendor/fontawesome/css/all.min.css" media="print" onload="this.media='all'">
    <noscript><link rel="stylesheet" href="<?php echo $site_url; ?>/vendor/fontawesome/css/all.min.css"></noscript>
</head>
<body class="bg-white font-sans text-slate-800 antialiased<?php echo !empty($body_class_extra) ? ' ' . htmlspecialchars($body_class_extra) : ''; ?>">
<?php if (isset($_SESSION['member_id'])): ?>
    <?php include_once __DIR__ . '/edit_profile_modal.php'; ?>
<?php endif; ?>

<header class="sticky top-0 z-50 border-b border-white/10 transition-colors duration-300" style="background-color: var(--theme-primary) !important;">
    <div class="global-container">
        <div class="flex items-center justify-between py-1.5 md:py-2 gap-4">

            <!-- Left: Mobile hamburger (hidden on desktop) -->
            <div class="flex items-center shrink-0 lg:hidden">
                <button type="button" aria-label="Open menu"
                        class="text-white hover:text-white/80 transition-colors"
                        onclick="toggleMobileMenu(true)">
                    <i class="fa-solid fa-bars text-xl"></i>
                </button>
            </div>

            <!-- Logo: center on mobile, left on desktop -->
            <div class="flex flex-grow justify-center lg:flex-grow-0 lg:justify-start">
                <a href="<?php echo $site_url; ?>/" class="flex items-center">
                    <?php if(!empty($site_settings['site_logo'])): ?>
                        <img src="<?php echo $site_url; ?>/<?php echo htmlspecialchars(preg_replace('/^\.\.\//', '', $site_settings['site_logo'])); ?>"
                             alt="<?php echo htmlspecialchars($site_settings['site_name']); ?>" class="logo-img object-contain">
                    <?php else: ?>
                        <span class="text-lg md:text-xl font-bold tracking-tight text-white">
                            <?php echo htmlspecialchars($site_settings['site_name']); ?>
                        </span>
                    <?php endif; ?>
                </a>
            </div>

            <!-- Right side: Search + Nav (desktop only) | Search icon (mobile only) -->
            <div class="flex items-center shrink-0 gap-3 ml-auto">

                <!-- Desktop: Flutter-style search pill -->
                <div id="desktopSearchWrap" class="items-center" style="display:none;">
                    <form action="<?php echo $site_url; ?>/search/" method="GET" id="desktopSearchForm" style="display:flex;align-items:center;">
                        <div style="position:relative;display:flex;align-items:center;">
                            <button type="submit" style="position:absolute;left:10px;top:50%;transform:translateY(-50%);background:none;border:none;padding:0;cursor:pointer;color:rgba(255,255,255,0.6);z-index:1;">
                                <i class="fa-solid fa-magnifying-glass" style="font-size:13px;"></i>
                            </button>
                            <input type="text" name="search" id="desktopSearch"
                                   placeholder="Search novels..."
                                   autocomplete="off"
                                   style="height:36px;padding-left:34px;padding-right:16px;font-size:0.875rem;border-radius:9999px;border:1px solid rgba(255,255,255,0.25);background:rgba(255,255,255,0.12);color:#fff;outline:none;width:160px;transition:width 0.3s ease,background 0.3s ease,border-color 0.3s ease;"
                                   onfocus="this.style.width='220px';this.style.background='rgba(255,255,255,0.18)';this.style.borderColor='rgba(255,255,255,0.45)';"
                                   onblur="this.style.width='160px';this.style.background='rgba(255,255,255,0.12)';this.style.borderColor='rgba(255,255,255,0.25)';">
                        </div>
                    </form>
                </div>
                <script>
                (function(){
                    var el = document.getElementById('desktopSearchWrap');
                    if(el && window.innerWidth >= 1024) { el.style.display = 'flex'; }
                    window.addEventListener('resize', function(){
                        if(el) el.style.display = window.innerWidth >= 1024 ? 'flex' : 'none';
                    });
                })();
                </script>


                <!-- Desktop: Nav items flush right -->
                <nav class="hidden lg:block">
                    <ul class="flex items-center gap-0.5">
                        <?php foreach ($nav_links as $link):
                            $link_url = $link['url'];
                            if (!preg_match('/^(http|https|#)/', $link_url)) {
                                $link_url = $site_url . '/' . ltrim($link_url, '/');
                            }
                        ?>
                        <li>
                            <a href="<?php echo htmlspecialchars($link_url); ?>"
                               class="px-3.5 py-2 rounded-full text-sm font-medium text-white/80 hover:text-white hover:bg-white/10 transition-all whitespace-nowrap">
                                <?php echo htmlspecialchars($link['label']); ?>
                            </a>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </nav>

                <!-- Mobile: Search icon only -->
                <button type="button" aria-label="Search"
                        class="lg:hidden text-white hover:text-white/80 transition-colors"
                        onclick="openMobileSearch()">
                    <i class="fa-solid fa-magnifying-glass text-xl"></i>
                </button>

            </div>
        </div>
    </div>


    <!-- Mobile Search Overlay (fullscreen, white glass, native-app style) -->
    <div id="mobileSearchBar"
         style="display:none;position:fixed;inset:0;z-index:200;
                background:rgba(255,255,255,0.97);
                backdrop-filter:blur(20px);-webkit-backdrop-filter:blur(20px);
                flex-direction:column;align-items:stretch;overflow-y:auto;">

        <!-- Top bar -->
        <div style="display:flex;align-items:center;gap:10px;padding:10px 14px;
                    border-bottom:1px solid #e8edf2;background:#fff;
                    position:sticky;top:0;z-index:10;box-shadow:0 1px 6px rgba(0,0,0,0.06);">

            <!-- Back arrow -->
            <button type="button" aria-label="Close search" onclick="closeMobileSearch()"
                    style="background:none;border:none;padding:8px;cursor:pointer;
                           color:#475569;flex-shrink:0;display:flex;align-items:center;
                           justify-content:center;border-radius:50%;transition:background 0.2s;"
                    onmouseover="this.style.background='#f1f5f9'"
                    onmouseout="this.style.background='none'">
                <i class="fa-solid fa-arrow-left" style="font-size:17px;"></i>
            </button>

            <!-- Search form -->
            <form id="mobileSearchForm" action="<?php echo $site_url; ?>/search/" method="GET"
                  style="flex:1;display:flex;align-items:center;"
                  onsubmit="closeMobileSearch();">
                <div style="position:relative;width:100%;display:flex;align-items:center;">
                    <i class="fa-solid fa-magnifying-glass"
                       style="position:absolute;left:13px;color:#94a3b8;font-size:13px;pointer-events:none;"></i>
                    <input type="text" name="search" id="mobileSearchInput"
                           placeholder="Search novels, authors..."
                           autocomplete="off"
                           style="width:100%;height:44px;padding:0 44px 0 38px;font-size:0.95rem;
                                  border-radius:12px;border:1.5px solid #e2e8f0;
                                  background:#f8fafc;color:#1e293b;outline:none;
                                  font-family:inherit;transition:border-color 0.2s,background 0.2s;"
                           onfocus="this.style.borderColor='var(--theme-secondary)';this.style.background='#fff';"
                           onblur="this.style.borderColor='#e2e8f0';this.style.background='#f8fafc';"
                           oninput="mobileSearchLive(this.value);">
                    <!-- Clear button -->
                    <button type="button" id="mobileSearchClear"
                            onclick="document.getElementById('mobileSearchInput').value='';
                                     this.style.display='none';
                                     document.getElementById('mobileSearchResults').innerHTML='';
                                     document.getElementById('mobileSearchHint').style.display='block';
                                     document.getElementById('mobileSearchInput').focus();"
                            style="display:none;position:absolute;right:10px;top:50%;
                                   transform:translateY(-50%);background:#e2e8f0;border:none;
                                   border-radius:50%;width:22px;height:22px;align-items:center;
                                   justify-content:center;cursor:pointer;color:#64748b;font-size:11px;">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
            </form>
        </div>

        <!-- Hint text -->
        <div id="mobileSearchHint" style="padding:28px 16px 0;color:#94a3b8;font-size:0.82rem;text-align:center;">
            <i class="fa-solid fa-magnifying-glass" style="font-size:2rem;color:#e2e8f0;display:block;margin-bottom:10px;"></i>
            Type to search novels or authors
        </div>

        <!-- AJAX Results: 3-col book card grid -->
        <div id="mobileSearchResults"
             style="padding:12px 14px 80px;display:grid;grid-template-columns:repeat(3,1fr);gap:12px;"></div>
    </div>

    <script>
    var _mobileSearchTimer = null;

    function openMobileSearch() {
        var el = document.getElementById('mobileSearchBar');
        el.style.display = 'flex';
        setTimeout(function(){ document.getElementById('mobileSearchInput').focus(); }, 80);
        document.body.style.overflow = 'hidden';
    }
    function closeMobileSearch() {
        document.getElementById('mobileSearchBar').style.display = 'none';
        document.body.style.overflow = '';
    }
    document.addEventListener('keydown', function(e){
        if(e.key === 'Escape') closeMobileSearch();
    });

    function mobileSearchLive(val) {
        var clear = document.getElementById('mobileSearchClear');
        var hint  = document.getElementById('mobileSearchHint');
        var res   = document.getElementById('mobileSearchResults');
        clear.style.display = val ? 'flex' : 'none';

        clearTimeout(_mobileSearchTimer);
        if (val.length < 2) {
            res.innerHTML = '';
            hint.style.display = 'block';
            return;
        }
        hint.style.display = 'none';
        res.innerHTML = '<div style="grid-column:1/-1;text-align:center;padding:24px 0;color:#94a3b8;font-size:0.85rem;"><i class="fa-solid fa-spinner fa-spin"></i> Searching...</div>';

        _mobileSearchTimer = setTimeout(function(){
            fetch('<?php echo $site_url; ?>/includes/ajax_search.php?q=' + encodeURIComponent(val))
                .then(function(r){ return r.json(); })
                .then(function(data){
                    if (!data.length) {
                        res.innerHTML = '<div style="grid-column:1/-1;text-align:center;padding:32px 0;color:#94a3b8;font-size:0.85rem;"><i class="fa-solid fa-book-open" style="font-size:1.8rem;color:#e2e8f0;display:block;margin-bottom:10px;"></i>No results found</div>';
                        return;
                    }
                    var html = '';
                    data.forEach(function(item){
                        html += '<a href="' + item.link + '" onclick="closeMobileSearch();" '
                              + 'style="display:flex;flex-direction:column;text-decoration:none;color:inherit;">'
                              + '<div style="aspect-ratio:2/3;border-radius:8px;overflow:hidden;background:#f1f5f9;margin-bottom:6px;box-shadow:0 2px 8px rgba(0,0,0,0.08);">'
                              + '<img src="' + item.img + '" alt="' + item.title.replace(/"/g,'&quot;') + '" '
                              + 'style="width:100%;height:100%;object-fit:cover;" loading="lazy">'
                              + '</div>'
                              + '<p style="font-size:0.72rem;font-weight:600;color:#1e293b;line-height:1.3;margin:0 0 2px;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;">'
                              + item.title + '</p>'
                              + '<p style="font-size:0.65rem;color:#64748b;margin:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">'
                              + item.writer_name + '</p>'
                              + '</a>';
                    });
                    res.innerHTML = html;
                })
                .catch(function(){ res.innerHTML = '<div style="grid-column:1/-1;text-align:center;padding:24px;color:#f87171;font-size:0.85rem;">Search failed. Try again.</div>'; });
        }, 350);
    }
    </script>


    <!-- Mobile Sidebar Menu Drawer (WordPress Style) -->
    <div id="mobileMenuDrawer" class="fixed inset-0 z-[100] hidden" role="dialog" aria-modal="true">
        <!-- Backdrop overlay -->
        <div id="mobileMenuBackdrop" class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm opacity-0 transition-opacity duration-300" onclick="toggleMobileMenu(false)"></div>
        
        <!-- Drawer Panel -->
        <div id="mobileMenuPanel" class="fixed top-0 left-0 bottom-0 w-72 max-w-[80vw] bg-white shadow-2xl flex flex-col z-50 transform -translate-x-full transition-transform duration-300 ease-out" style="background-color: #ffffff !important;">
            <!-- Header area of drawer -->
            <div class="flex items-center justify-between px-5 py-4 border-b border-slate-100" style="border-color: #f1f5f9 !important;">
                <div class="flex items-center gap-2">
                    <span class="text-base font-bold tracking-tight text-slate-900" style="color: #1e293b !important;">
                        Menu
                    </span>
                </div>
                <!-- Close button -->
                <button type="button" aria-label="Close menu" class="h-8 w-8 rounded-full flex items-center justify-center text-slate-500 hover:text-slate-900 hover:bg-slate-100 transition-colors" onclick="toggleMobileMenu(false)" style="color: #64748b !important;">
                    <i class="fa-solid fa-xmark text-lg"></i>
                </button>
            </div>
            
            <!-- Drawer content navigation links -->
            <nav class="flex-grow overflow-y-auto py-4 px-3">
                <ul class="space-y-1">
                    <?php foreach ($nav_links as $link): 
                        $link_url = $link['url'];
                        if (!preg_match('/^(http|https|#)/', $link_url)) {
                            $link_url = $site_url . '/' . ltrim($link_url, '/');
                        }
                    ?>
                    <li>
                        <a href="<?php echo htmlspecialchars($link_url); ?>"
                           class="flex items-center px-4 py-3 rounded-xl text-sm font-semibold text-slate-700 hover:text-indigo-600 hover:bg-slate-50 transition-all" style="color: #334155 !important;">
                            <?php echo htmlspecialchars($link['label']); ?>
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </nav>
            
            <!-- Drawer footer area -->
            <div class="p-5 border-t border-slate-100 mt-auto bg-slate-50/50" style="border-color: #f1f5f9 !important; background-color: #f8fafc !important;">
                <p class="text-xs text-slate-400 text-center">
                    &copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($site_settings['site_name']); ?>
                </p>
            </div>
        </div>
    </div>

    <script>
        function toggleMobileMenu(isOpen) {
            const drawer = document.getElementById('mobileMenuDrawer');
            const backdrop = document.getElementById('mobileMenuBackdrop');
            const panel = document.getElementById('mobileMenuPanel');
            
            if (isOpen) {
                drawer.classList.remove('hidden');
                drawer.offsetHeight; // force reflow
                backdrop.classList.remove('opacity-0');
                backdrop.classList.add('opacity-100');
                panel.classList.remove('-translate-x-full');
                panel.classList.add('translate-x-0');
                document.body.style.overflow = 'hidden';
            } else {
                backdrop.classList.remove('opacity-100');
                backdrop.classList.add('opacity-0');
                panel.classList.remove('translate-x-0');
                panel.classList.add('-translate-x-full');
                document.body.style.overflow = '';
                setTimeout(() => {
                    if (panel.classList.contains('-translate-x-full')) {
                        drawer.classList.add('hidden');
                    }
                }, 300);
            }
        }

        // SPA Navigation Custom Router
        document.addEventListener("DOMContentLoaded", () => {
            // Create Top Progress loading bar
            const progress = document.createElement('div');
            progress.id = 'spa-progress';
            progress.style.cssText = "position:fixed;top:0;left:0;height:3px;background:linear-gradient(to right, #4f46e5, #3b82f6);z-index:99999;width:0%;transition:width 0.4s ease, opacity 0.4s ease;opacity:0;pointer-events:none;";
            document.body.appendChild(progress);

            function showLoading() {
                progress.style.opacity = "1";
                progress.style.width = "0%";
                setTimeout(() => { progress.style.width = "75%"; }, 10);
            }

            function hideLoading() {
                progress.style.width = "100%";
                setTimeout(() => {
                    progress.style.opacity = "0";
                    setTimeout(() => { progress.style.width = "0%"; }, 400);
                }, 150);
            }

            const appContainer = document.getElementById('app-container');

            function syncSeoHead(doc) {
                document.title = doc.title || document.title;

                const seoSelectors = [
                    'meta[name="description"]',
                    'meta[name="keywords"]',
                    'meta[name="robots"]',
                    'meta[name="author"]',
                    'meta[name="publish-date"]',
                    'meta[name="modified-date"]',
                    'link[rel="canonical"]',
                    'link[rel="alternate"]',
                    'meta[property^="og:"]',
                    'meta[property^="article:"]',
                    'meta[name^="twitter:"]',
                    'meta[name="publisher"]',
                    'link[rel="publisher"]'
                ];
                seoSelectors.forEach(selector => {
                    document.querySelectorAll(selector).forEach(el => el.remove());
                    doc.querySelectorAll(selector).forEach(newEl => {
                        document.head.appendChild(document.importNode(newEl, true));
                    });
                });

                document.querySelectorAll('script[type="application/ld+json"]').forEach(el => el.remove());
                doc.querySelectorAll('script[type="application/ld+json"]').forEach(newEl => {
                    document.head.appendChild(document.importNode(newEl, true));
                });
            }

            function loadPage(url, push = true) {
                showLoading();
                if (appContainer) appContainer.style.opacity = '0.3';

                fetch(url, { credentials: 'same-origin' })
                    .then(res => {
                        if (res.status === 404) {
                            window.location.href = url;
                            return null;
                        }
                        if (!res.ok) throw new Error("Could not load page");
                        return res.text();
                    })
                    .then(html => {
                        if (html === null) return;
                        const parser = new DOMParser();
                        const doc = parser.parseFromString(html, 'text/html');

                        syncSeoHead(doc);

                        const newContent = doc.getElementById('app-container');
                        if (newContent && appContainer) {
                            appContainer.innerHTML = newContent.innerHTML;

                            const scripts = appContainer.querySelectorAll('script');
                            scripts.forEach(script => {
                                const newScript = document.createElement('script');
                                Array.from(script.attributes).forEach(attr => newScript.setAttribute(attr.name, attr.value));
                                newScript.innerHTML = script.innerHTML;
                                script.parentNode.replaceChild(newScript, script);
                            });
                        }

                        if (push) {
                            history.pushState({ url }, '', url);
                        }

                        window.scrollTo({ top: 0, behavior: 'smooth' });
                        toggleMobileMenu(false);

                        const searchBar = document.getElementById('mobileSearchBar');
                        if (searchBar && searchBar.style.display !== 'none') {
                            searchBar.style.display = 'none';
                            document.body.style.overflow = '';
                        }

                        hideLoading();
                        if (appContainer) appContainer.style.opacity = '1';
                        bindAll();
                    })
                    .catch(err => {
                        console.error("SPA failed, fallback navigation:", err);
                        window.location.href = url;
                    });
            }

            function bindAll() {
                // Intercept dynamic links
                document.querySelectorAll('a').forEach(a => {
                    if (a.dataset.spaBound) return;
                    a.dataset.spaBound = "true";

                    const href = a.getAttribute('href');
                    if (!href) return;

                    // Skip anchor/hashes, external links, admin area, mailto, tel, file downloads
                    if (
                        href.startsWith('#') || 
                        (href.includes('//') && !href.includes(window.location.hostname)) ||
                        href.includes('newlogin/') || 
                        href.includes('shop/') || 
                        href.includes('javascript:') ||
                        href.startsWith('mailto:') ||
                        href.startsWith('tel:') ||
                        a.getAttribute('target') === '_blank' ||
                        /\.(pdf|zip|rar|gz|tar|docx|xlsx|txt|mp3|mp4|png|jpg|jpeg|gif|epub|mobi)$/i.test(href)
                    ) {
                        return;
                    }

                    a.addEventListener('click', (e) => {
                        e.preventDefault();
                        const targetUrl = new URL(a.href).href;
                        loadPage(targetUrl);
                    });
                });

                // Intercept search forms (GET requests)
                document.querySelectorAll('form').forEach(form => {
                    if (form.dataset.spaBound) return;
                    form.dataset.spaBound = "true";

                    const action = form.getAttribute('action');
                    if (!action || form.getAttribute('method')?.toUpperCase() !== 'GET') return;
                    if (action.includes('newlogin/')) return;

                    form.addEventListener('submit', (e) => {
                        e.preventDefault();
                        const formData = new FormData(form);
                        const params = new URLSearchParams();
                        
                        for (const [key, value] of formData.entries()) {
                            if (value.toString().trim() !== '') {
                                params.set(key, value.toString());
                            }
                        }

                        // Construct action URL
                        const actionUrl = new URL(action, window.location.origin);
                        actionUrl.search = params.toString();

                        loadPage(actionUrl.href);
                    });
                });
            }

            // Popstate navigation (back / forward buttons)
            window.addEventListener('popstate', () => {
                loadPage(window.location.href, false);
            });

            bindAll();
        });
    </script>
</header>
<main id="app-container" class="transition-opacity duration-300">
