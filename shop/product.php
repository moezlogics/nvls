<?php
/**
 * shop/product.php — Product Detail page.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../lib/coming_soon.php';
coming_soon_gate('shop');

$product_id = (int)($_GET['id'] ?? 0);
if ($product_id <= 0) {
    header("Location: index.php");
    exit;
}

// Fetch product with writer and post details
$query = "
    SELECT p.*, w.name AS writer_name, po.slug AS novel_slug 
    FROM products p
    LEFT JOIN posts po ON p.post_id = po.id
    LEFT JOIN writers w ON po.writer_id = w.id
    WHERE p.id = $product_id AND p.status = 'active'
    LIMIT 1
";
$res = $conn->query($query);
if (!$res || $res->num_rows == 0) {
    header("Location: index.php");
    exit;
}

$p = $res->fetch_assoc();

require_once __DIR__ . '/../lib/shop_seo.php';
$shop_seo_ctx = shop_seo_product_context($p, $site_settings, $site_url);
$is_shop_product = true;
$canonical_url = $shop_seo_ctx['url'];
$lcp_image_url = $p['image'] ?? '';

$original_price = (float)$p['price'];
$sale_price = $p['sale_price'] !== null ? (float)$p['sale_price'] : null;
$has_discount = ($sale_price !== null && $sale_price < $original_price);

// Fallback image logic
$img_url = 'https://placehold.co/300x450/004d5e/fff?text=Novel';
if (!empty($p['image'])) {
    $img_url = '../' . ltrim($p['image'], '/');
}

$discount_pct = 0;
if ($has_discount) {
    $discount_pct = round((($original_price - $sale_price) / $original_price) * 100);
}

// Gallery images array
$gallery = !empty($p['gallery_images']) ? explode(',', $p['gallery_images']) : [];

// Count unique items in cart for the floating badge
$cart_count = 0;
if (!empty($_SESSION['shop_cart'])) {
    $cart_count = array_sum($_SESSION['shop_cart']);
}

// Body extra class to trigger Nginx mobile header hiding
$body_class_extra = 'page-shop-product';

include_once __DIR__ . '/../includes/header.php';
?>

<link rel="stylesheet" href="shop.css">

<!-- Minimal Mobile Header (displayed only on mobile when main header is hidden) -->
<div class="sp-mobile-nav">
    <a href="index.php" class="sp-mobile-nav-btn"><i class="fa-solid fa-arrow-left"></i></a>
    <span class="sp-mobile-nav-title"><?php echo htmlspecialchars($p['title']); ?></span>
    <a href="<?php echo htmlspecialchars($site_url); ?>/" class="sp-mobile-nav-btn"><i class="fa-solid fa-house"></i></a>
</div>

<div class="shop-container">
    <div style="margin-bottom:12px;" class="hidden lg:block">
        <a href="index.php" style="text-decoration:none; color:var(--shop-gray); font-size:0.8rem; font-weight:700; display:inline-flex; align-items:center; gap:4px;">
            <i class="fa-solid fa-arrow-left"></i> Back to Shop
        </a>
    </div>

    <div class="sp-details-container">
        <!-- Main Image and Gallery thumbnails -->
        <div class="sp-details-image-wrap">
            <img src="<?php echo htmlspecialchars($img_url); ?>" id="mainProductImg" class="sp-details-image" alt="<?php echo htmlspecialchars($p['title']); ?>">
            
            <?php if (!empty($gallery) || !empty($p['image'])): ?>
                <div class="sp-gallery-grid">
                    <?php if (!empty($p['image'])): ?>
                        <img src="<?php echo htmlspecialchars($img_url); ?>" class="sp-gallery-thumb active" onclick="changeProductImage(this.src, this)">
                    <?php endif; ?>
                    <?php foreach ($gallery as $g_img): 
                        if (empty(trim($g_img))) continue;
                        $g_url = '../' . ltrim($g_img, '/');
                    ?>
                        <img src="<?php echo htmlspecialchars($g_url); ?>" class="sp-gallery-thumb" onclick="changeProductImage(this.src, this)">
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="sp-details-info">
            <h2 class="sp-details-title"><?php echo htmlspecialchars($p['title']); ?></h2>
            
            <?php if ((int)$p['stock'] > 0): ?>
                <span class="sp-details-stock-badge sp-details-stock-in">
                    <i class="fa-solid fa-circle-check"></i> Only <?php echo $p['stock']; ?> left in stock
                </span>
            <?php else: ?>
                <span class="sp-details-stock-badge sp-details-stock-out">
                    <i class="fa-solid fa-circle-xmark"></i> Out of Stock
                </span>
            <?php endif; ?>

            <div class="sp-details-price-box">
                <?php if ($has_discount): ?>
                    <span class="sp-details-price">Rs. <?php echo number_format($sale_price, 2); ?></span>
                    <span class="sp-details-old-price">Rs. <?php echo number_format($original_price, 2); ?></span>
                    <span style="margin-left:auto; background:var(--shop-danger); color:#fff; font-size:0.7rem; font-weight:700; padding:2px 6px; border-radius:4px;">-<?php echo $discount_pct; ?>% OFF</span>
                <?php else: ?>
                    <span class="sp-details-price">Rs. <?php echo number_format($original_price, 2); ?></span>
                <?php endif; ?>
            </div>

            <div class="sp-details-description">
                <p style="margin:0;"><?php echo nl2br(htmlspecialchars($p['description'] ?: 'No description available for this book.')); ?></p>
            </div>

            <div class="sp-details-actions">
                <?php if ((int)$p['stock'] > 0): ?>
                    <div class="sp-details-qty">
                        <button type="button" class="sp-details-qty-btn" onclick="adjustQty(-1)">-</button>
                        <input type="number" id="qtyInput" class="sp-details-qty-input" value="1" min="1" max="<?php echo $p['stock']; ?>" readonly>
                        <button type="button" class="sp-details-qty-btn" onclick="adjustQty(1)">+</button>
                    </div>
                    <button type="button" class="sp-details-btn-cart" onclick="addCurrentToCart()">
                        <i class="fa-solid fa-cart-plus"></i> Add to Cart
                    </button>
                <?php endif; ?>

                <?php if (!empty($p['post_id']) && !empty($p['novel_slug'])): 
                    $novel_link = $site_url . get_post_link($p['post_id'], $p['novel_slug']);
                ?>
                    <a href="<?php echo htmlspecialchars($novel_link); ?>" class="sp-details-btn-read">
                        <i class="fa-solid fa-book-open-reader"></i> Read Free Online
                    </a>
                <?php endif; ?>
            </div>
            
            <!-- Video Story Player -->
            <?php if (!empty($p['video_url'])): 
                $video_src = '../' . ltrim($p['video_url'], '/');
            ?>
                <div style="border-top: 1px solid var(--shop-border); padding-top: 16px; margin-top: 16px;">
                    <h4 style="margin: 0 0 10px 0; font-size: 0.85rem; font-weight: 700; text-transform: uppercase; color: var(--shop-gray);">Video Review / Trailer</h4>
                    <div class="sp-video-container">
                        <video class="sp-video-player" src="<?php echo htmlspecialchars($video_src); ?>" controls loop muted autoplay playsinline></video>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Floating Mobile Cart Button -->
<a href="cart.php" id="floatingCartBtn" style="position:fixed; bottom:20px; right:20px; width:56px; height:56px; border-radius:50%; background:var(--shop-primary); color:#fff; display:flex; align-items:center; justify-content:center; box-shadow:0 4px 10px rgba(0,0,0,0.3); text-decoration:none; z-index:999; transition:transform 0.2s;" onmouseover="this.style.transform='scale(1.05)';" onmouseout="this.style.transform='scale(1)';">
    <i class="fa-solid fa-shopping-bag" style="font-size:1.4rem;"></i>
    <span id="floatingCartBadge" style="position:absolute; top:-3px; right:-3px; background:var(--shop-danger); color:#fff; font-size:0.7rem; font-weight:700; width:20px; height:20px; border-radius:50%; display:<?php echo $cart_count > 0 ? 'flex' : 'none'; ?>; align-items:center; justify-content:center; border:2px solid #fff;"><?php echo $cart_count; ?></span>
</a>

<!-- AJAX Toast Notification -->
<div id="shopToast" style="position:fixed; bottom:90px; right:20px; background:#1e293b; color:#fff; padding:12px 18px; border-radius:8px; font-size:0.85rem; font-weight:600; display:none; align-items:center; gap:8px; box-shadow:0 4px 12px rgba(0,0,0,0.15); z-index:9999; transition:opacity 0.2s;">
    <i class="fa-solid fa-circle-check" style="color:var(--shop-accent);"></i> <span id="shopToastMessage">Item added!</span>
</div>

<script>
const maxStock = <?php echo (int)$p['stock']; ?>;

function adjustQty(amount) {
    const input = document.getElementById('qtyInput');
    let val = parseInt(input.value) || 1;
    val += amount;
    if (val < 1) val = 1;
    if (val > maxStock) val = maxStock;
    input.value = val;
}

function changeProductImage(src, thumbEl) {
    document.getElementById('mainProductImg').src = src;
    document.querySelectorAll('.sp-gallery-thumb').forEach(t => t.classList.remove('active'));
    thumbEl.classList.add('active');
}

function showToast(message, isError = false) {
    const toast = document.getElementById('shopToast');
    const toastMsg = document.getElementById('shopToastMessage');
    const toastIcon = toast.querySelector('i');
    
    toastMsg.textContent = message;
    if (isError) {
        toastIcon.className = "fa-solid fa-circle-xmark";
        toastIcon.style.color = "var(--shop-danger)";
    } else {
        toastIcon.className = "fa-solid fa-circle-check";
        toastIcon.style.color = "var(--shop-accent)";
    }
    
    toast.style.display = 'flex';
    toast.style.opacity = '1';
    
    setTimeout(() => {
        toast.style.opacity = '0';
        setTimeout(() => { toast.style.display = 'none'; }, 200);
    }, 2500);
}

function addCurrentToCart() {
    const qty = parseInt(document.getElementById('qtyInput').value) || 1;
    const productId = <?php echo $p['id']; ?>;
    
    const formData = new FormData();
    formData.append('action', 'add');
    formData.append('product_id', productId);
    formData.append('quantity', qty);
    
    fetch('cart_action.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            showToast(data.message);
            // Update badge
            const badge = document.getElementById('floatingCartBadge');
            badge.textContent = data.cart_count;
            badge.style.display = data.cart_count > 0 ? 'flex' : 'none';
            
            // Pulse animation
            const btn = document.getElementById('floatingCartBtn');
            btn.style.transform = 'scale(1.15)';
            setTimeout(() => { btn.style.transform = 'scale(1)'; }, 200);
        } else {
            showToast(data.message, true);
        }
    })
    .catch(err => {
        console.error(err);
        showToast('Connection error.', true);
    });
}
</script>

<?php
include_once __DIR__ . '/../includes/footer.php';
?>
