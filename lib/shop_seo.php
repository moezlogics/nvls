<?php
/**
 * Shop SEO helpers — meta context, Open Graph product tags, Schema.org (Product, Store, ItemList).
 */

function shop_abs_url(string $path, string $site_url): string {
    if ($path === '') {
        return '';
    }
    if (preg_match('~^https?://~i', $path)) {
        return $path;
    }
    $path = preg_replace('#^(\.\./)+#', '', $path);
    return rtrim($site_url, '/') . '/' . ltrim($path, '/');
}

function shop_product_effective_price(array $p): float {
    $price = (float)($p['price'] ?? 0);
    $sale = isset($p['sale_price']) && $p['sale_price'] !== null ? (float)$p['sale_price'] : null;
    if ($sale !== null && $sale < $price) {
        return $sale;
    }
    return $price;
}

function shop_product_availability_schema(int $stock): string {
    return $stock > 0
        ? 'https://schema.org/InStock'
        : 'https://schema.org/OutOfStock';
}

function shop_product_images(array $p, string $site_url): array {
    $images = [];
    if (!empty($p['image'])) {
        $images[] = shop_abs_url($p['image'], $site_url);
    }
    if (!empty($p['gallery_images'])) {
        foreach (explode(',', $p['gallery_images']) as $img) {
            $img = trim($img);
            if ($img !== '') {
                $url = shop_abs_url($img, $site_url);
                if (!in_array($url, $images, true)) {
                    $images[] = $url;
                }
            }
        }
    }
    return $images;
}

function shop_seo_product_context(array $p, array $site_settings, string $site_url): array {
    $siteName = $site_settings['site_name'] ?? 'Portal';
    $productId = (int)($p['id'] ?? 0);
    $title = trim($p['title'] ?? 'Product');
    $writer = trim($p['writer_name'] ?? '');

    $metaTitle = trim($p['meta_title'] ?? '');
    if ($metaTitle === '') {
        $metaTitle = $title . ' | Buy Printed Book | ' . $siteName;
    }

    $descSource = trim($p['meta_description'] ?? '');
    if ($descSource === '') {
        $descSource = trim(strip_tags($p['description'] ?? ''));
        if ($descSource === '') {
            $descSource = 'Buy printed copy of ' . $title . '. Cash on delivery available across Pakistan.';
        }
    }
    $metaDescription = mb_substr(preg_replace('/\s+/', ' ', $descSource), 0, 160);

    $images = shop_product_images($p, $site_url);
    $primaryImage = $images[0] ?? '';
    $price = shop_product_effective_price($p);
    $originalPrice = (float)($p['price'] ?? 0);
    $stock = (int)($p['stock'] ?? 0);
    $url = rtrim($site_url, '/') . '/shop/product.php?id=' . $productId;

    return [
        'id' => $productId,
        'title' => $metaTitle,
        'description' => $metaDescription,
        'product_name' => $title,
        'url' => $url,
        'images' => $images,
        'primary_image' => $primaryImage,
        'price' => $price,
        'original_price' => $originalPrice,
        'currency' => 'PKR',
        'stock' => $stock,
        'availability' => shop_product_availability_schema($stock),
        'brand' => $writer !== '' ? $writer : $siteName,
        'sku' => 'BOOK-' . $productId,
        'writer' => $writer,
    ];
}

function shop_seo_index_context(array $site_settings, string $site_url, bool $isSearch = false): array {
    $siteName = $site_settings['site_name'] ?? 'Portal';
    $url = rtrim($site_url, '/') . '/shop/';

    if ($isSearch) {
        return [
            'title' => 'Search Books | ' . $siteName . ' Shop',
            'description' => 'Search printed novels and books at ' . $siteName . '.',
            'url' => $url,
            'robots' => 'noindex, follow',
            'is_search' => true,
        ];
    }

    return [
        'title' => 'Bookstore | Buy Printed Novels | ' . $siteName,
        'description' => 'Browse and buy premium printed copies of Urdu novels and books. Cash on delivery available across Pakistan.',
        'url' => $url,
        'robots' => 'index, follow',
        'is_search' => false,
    ];
}

function shop_seo_json_escape(string $value): string {
    return addslashes(htmlspecialchars_decode($value, ENT_QUOTES));
}

function shop_seo_render_product_schema(array $ctx, string $orgId): void {
    $imagesJson = '';
    if (!empty($ctx['images'])) {
        if (count($ctx['images']) === 1) {
            $imagesJson = '"image": "' . shop_seo_json_escape($ctx['images'][0]) . '",';
        } else {
            $imgs = array_map(fn($u) => '"' . shop_seo_json_escape($u) . '"', $ctx['images']);
            $imagesJson = '"image": [' . implode(', ', $imgs) . '],';
        }
    }

    $offerExtras = '';
    if ($ctx['price'] < $ctx['original_price']) {
        $offerExtras = ',
        "priceValidUntil": "' . date('Y-m-d', strtotime('+1 year')) . '"';
    }

    echo '<script type="application/ld+json">' . "\n";
    echo '{' . "\n";
    echo '  "@context": "https://schema.org",' . "\n";
    echo '  "@type": "Product",' . "\n";
    echo '  "@id": "' . shop_seo_json_escape($ctx['url']) . '#product",' . "\n";
    echo '  "name": "' . shop_seo_json_escape($ctx['product_name']) . '",' . "\n";
    echo '  "description": "' . shop_seo_json_escape($ctx['description']) . '",' . "\n";
    echo '  ' . $imagesJson . "\n";
    echo '  "sku": "' . shop_seo_json_escape($ctx['sku']) . '",' . "\n";
    echo '  "url": "' . shop_seo_json_escape($ctx['url']) . '",' . "\n";
    echo '  "brand": {' . "\n";
    echo '    "@type": "Brand",' . "\n";
    echo '    "name": "' . shop_seo_json_escape($ctx['brand']) . '"' . "\n";
    echo '  }';
    if (!empty($ctx['writer'])) {
        echo ',' . "\n";
        echo '  "author": {' . "\n";
        echo '    "@type": "Person",' . "\n";
        echo '    "name": "' . shop_seo_json_escape($ctx['writer']) . '"' . "\n";
        echo '  }';
    }
    echo ',' . "\n";
    echo '  "offers": {' . "\n";
    echo '    "@type": "Offer",' . "\n";
    echo '    "url": "' . shop_seo_json_escape($ctx['url']) . '",' . "\n";
    echo '    "priceCurrency": "' . shop_seo_json_escape($ctx['currency']) . '",' . "\n";
    echo '    "price": "' . number_format($ctx['price'], 2, '.', '') . '",' . "\n";
    echo '    "availability": "' . shop_seo_json_escape($ctx['availability']) . '",' . "\n";
    echo '    "itemCondition": "https://schema.org/NewCondition",' . "\n";
    echo '    "seller": { "@id": "' . shop_seo_json_escape($orgId) . '" }' . $offerExtras . "\n";
    echo '  }' . "\n";
    echo '}' . "\n";
    echo '</script>' . "\n";
}

function shop_seo_render_product_webpage_schema(array $ctx, string $websiteId): void {
    $imgLine = '';
    if (!empty($ctx['primary_image'])) {
        $imgLine = '  "primaryImageOfPage": "' . shop_seo_json_escape($ctx['primary_image']) . '",' . "\n";
    }

    echo '<script type="application/ld+json">' . "\n";
    echo '{' . "\n";
    echo '  "@context": "https://schema.org",' . "\n";
    echo '  "@type": "WebPage",' . "\n";
    echo '  "@id": "' . shop_seo_json_escape($ctx['url']) . '#webpage",' . "\n";
    echo '  "url": "' . shop_seo_json_escape($ctx['url']) . '",' . "\n";
    echo '  "name": "' . shop_seo_json_escape($ctx['title']) . '",' . "\n";
    echo '  "description": "' . shop_seo_json_escape($ctx['description']) . '",' . "\n";
    echo '  "isPartOf": { "@id": "' . shop_seo_json_escape($websiteId) . '" },' . "\n";
    echo $imgLine;
    echo '  "inLanguage": "en"' . "\n";
    echo '}' . "\n";
    echo '</script>' . "\n";
}

function shop_seo_render_breadcrumb_schema(string $siteUrl, string $pageUrl, string $pageName, string $section = 'Shop'): void {
    $home = rtrim($siteUrl, '/') . '/';
    $shop = rtrim($siteUrl, '/') . '/shop/';
    echo '<script type="application/ld+json">' . "\n";
    echo '{' . "\n";
    echo '  "@context": "https://schema.org",' . "\n";
    echo '  "@type": "BreadcrumbList",' . "\n";
    echo '  "itemListElement": [' . "\n";
    echo '    { "@type": "ListItem", "position": 1, "name": "Home", "item": "' . shop_seo_json_escape($home) . '" },' . "\n";
    echo '    { "@type": "ListItem", "position": 2, "name": "' . shop_seo_json_escape($section) . '", "item": "' . shop_seo_json_escape($shop) . '" },' . "\n";
    echo '    { "@type": "ListItem", "position": 3, "name": "' . shop_seo_json_escape($pageName) . '", "item": "' . shop_seo_json_escape($pageUrl) . '" }' . "\n";
    echo '  ]' . "\n";
    echo '}' . "\n";
    echo '</script>' . "\n";
}

function shop_seo_render_store_schema(array $site_settings, string $site_url): void {
    $siteName = shop_seo_json_escape($site_settings['site_name'] ?? 'Portal');
    $shopUrl = rtrim($site_url, '/') . '/shop/';
    $orgId = rtrim($site_url, '/') . '/#organization';

    echo '<script type="application/ld+json">' . "\n";
    echo '{' . "\n";
    echo '  "@context": "https://schema.org",' . "\n";
    echo '  "@type": "Store",' . "\n";
    echo '  "@id": "' . shop_seo_json_escape($shopUrl) . '#store",' . "\n";
    echo '  "name": "' . $siteName . ' Bookstore",' . "\n";
    echo '  "url": "' . shop_seo_json_escape($shopUrl) . '",' . "\n";
    echo '  "description": "Printed novels and books with cash on delivery.",' . "\n";
    echo '  "priceRange": "PKR",' . "\n";
    echo '  "parentOrganization": { "@id": "' . shop_seo_json_escape($orgId) . '" },' . "\n";
    echo '  "inLanguage": "en"' . "\n";
    echo '}' . "\n";
    echo '</script>' . "\n";
}

function shop_seo_render_itemlist_schema(array $products, string $site_url): void {
    if (empty($products)) {
        return;
    }

    $items = [];
    $pos = 1;
    foreach ($products as $p) {
        $id = (int)($p['id'] ?? 0);
        if ($id <= 0) {
            continue;
        }
        $url = rtrim($site_url, '/') . '/shop/product.php?id=' . $id;
        $name = shop_seo_json_escape($p['title'] ?? 'Product');
        $price = number_format(shop_product_effective_price($p), 2, '.', '');
        $stock = (int)($p['stock'] ?? 0);
        $availability = shop_seo_json_escape(shop_product_availability_schema($stock));
        $image = '';
        if (!empty($p['image'])) {
            $image = shop_seo_json_escape(shop_abs_url($p['image'], $site_url));
        }

        $item = '{ "@type": "ListItem", "position": ' . $pos . ', "item": { "@type": "Product", "name": "' . $name . '", "url": "' . shop_seo_json_escape($url) . '"';
        if ($image !== '') {
            $item .= ', "image": "' . $image . '"';
        }
        $item .= ', "offers": { "@type": "Offer", "priceCurrency": "PKR", "price": "' . $price . '", "availability": "' . $availability . '" } } }';
        $items[] = $item;
        $pos++;
        if ($pos > 20) {
            break;
        }
    }

    if (empty($items)) {
        return;
    }

    echo '<script type="application/ld+json">' . "\n";
    echo '{' . "\n";
    echo '  "@context": "https://schema.org",' . "\n";
    echo '  "@type": "ItemList",' . "\n";
    echo '  "itemListElement": [' . "\n";
    echo '    ' . implode(",\n    ", $items) . "\n";
    echo '  ]' . "\n";
    echo '}' . "\n";
    echo '</script>' . "\n";
}
