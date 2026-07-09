<?php
/**
 * shop/index.php — Bookstore Home page.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../lib/coming_soon.php';
require_once __DIR__ . '/../lib/shop_seo.php';
coming_soon_gate('shop');

// Fetch active products
$search_query = "";
$search_term = trim($_GET['search'] ?? '');
if (!empty($search_term)) {
    $search_escaped = $conn->real_escape_string($search_term);
    $search_query = " AND (p.title LIKE '%$search_escaped%' OR p.description LIKE '%$search_escaped%')";
}

$query = "
    SELECT p.*, w.name AS writer_name, po.slug AS novel_slug 
    FROM products p
    LEFT JOIN posts po ON p.post_id = po.id
    LEFT JOIN writers w ON po.writer_id = w.id
    WHERE p.status = 'active' $search_query
    ORDER BY p.id DESC
";
$productsResult = $conn->query($query);

$shop_list_products = [];
if ($productsResult) {
    while ($row = $productsResult->fetch_assoc()) {
        $shop_list_products[] = $row;
    }
}

// Count unique items in cart for the floating badge
$cart_count = 0;
if (!empty($_SESSION['shop_cart'])) {
    $cart_count = array_sum($_SESSION['shop_cart']);
}

$is_shop_index = true;
$shop_index_seo = shop_seo_index_context($site_settings, $site_url, $search_term !== '');
$canonical_url = $shop_index_seo['url'];
if (!empty($shop_index_seo['is_search'])) {
    $meta_robots = $shop_index_seo['robots'];
}

$body_class_extra = 'page-shop-index';

include_once __DIR__ . '/../includes/header.php';
?>

<link rel="stylesheet" href="shop.css">

<!-- Shop Hero Section -->
<div class="shop-container">
    <div style="background: linear-gradient(135deg, var(--shop-primary), var(--shop-secondary)); border-radius: var(--shop-radius); padding: 24px 20px; color: #fff; margin-bottom: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.05);">
        <h2 style="font-size:1.5rem; font-weight:800; margin:0 0 6px 0; font-family:var(--shop-font); letter-spacing:-0.02em;">Bookstore</h2>
        <p style="font-size:0.88rem; opacity:0.9; margin:0; line-height:1.5;">Buy premium printed copies of your favorite novels directly to your doorstep. Cash on Delivery is available all over Pakistan!</p>
    </div>

    <!-- Search and Filters -->
    <div style="margin-bottom: 20px; display:flex; gap:8px;">
        <form action="" method="GET" style="display:flex; flex-grow:1; gap:6px; margin:0;">
            <input type="text" name="search" class="shop-form-control" placeholder="Search books..." value="<?php echo htmlspecialchars($search_term); ?>" style="height:38px;">
            <button type="submit" class="sp-submit" style="height:38px; font-size:0.85rem; border-radius:6px;">
                <i class="fa-solid fa-magnifying-glass me-1"></i> Search
            </button>
        </form>
    </div>

    <h3 class="shop-section-title">
        <i class="fa-solid fa-book"></i> <?php echo !empty($search_term) ? 'Search Results' : 'Latest Arrivals'; ?>
    </h3>

    <?php if (!empty($shop_list_products)): ?>
        <div class="shop-grid">
            <?php foreach ($shop_list_products as $p): 
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
            ?>
                <div class="shop-card">
                    <?php if ($has_discount): ?>
                        <div class="shop-card-badge">-<?php echo $discount_pct; ?>% OFF</div>
                    <?php endif; ?>
                    
                    <a href="product.php?id=<?php echo $p['id']; ?>" class="shop-card-image-wrap">
                        <img src="<?php echo htmlspecialchars($img_url); ?>" class="shop-card-image" alt="<?php echo htmlspecialchars($p['title']); ?>" loading="lazy">
                    </a>
                    
                    <div class="shop-card-body">
                        <a href="product.php?id=<?php echo $p['id']; ?>" class="shop-card-title">
                            <?php echo htmlspecialchars($p['title']); ?>
                        </a>

                        <div class="shop-card-price-row">
                            <?php if ($has_discount): ?>
                                <span class="shop-card-price">Rs. <?php echo number_format($sale_price, 2); ?></span>
                                <span class="shop-card-old-price">Rs. <?php echo number_format($original_price, 2); ?></span>
                            <?php else: ?>
                                <span class="shop-card-price">Rs. <?php echo number_format($original_price, 2); ?></span>
                            <?php endif; ?>
                        </div>

                        <?php if ((int)$p['stock'] > 0): ?>
                            <button type="button" class="shop-card-btn" onclick="addToCart(<?php echo $p['id']; ?>, event)">
                                <i class="fa-solid fa-cart-plus"></i> Add to Cart
                            </button>
                        <?php else: ?>
                            <button type="button" class="shop-card-btn" style="background:#fee2e2; color:#991b1b; cursor:not-allowed;" disabled>
                                <i class="fa-solid fa-triangle-exclamation"></i> Out of Stock
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="shop-empty-state">
            <div class="shop-empty-icon"><i class="fa-solid fa-book-open"></i></div>
            <h4 class="shop-empty-title">No books found</h4>
            <p class="shop-empty-text">We couldn't find any books matching your search. Try looking for other titles!</p>
            <a href="index.php" class="sp-submit" style="text-decoration:none; margin:0 auto; display:inline-flex;">View All Books</a>
        </div>
    <?php endif; ?>
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

function addToCart(productId, event) {
    if (event) {
        event.preventDefault();
        event.stopPropagation();
    }
    
    const formData = new FormData();
    formData.append('action', 'add');
    formData.append('product_id', productId);
    formData.append('quantity', 1);
    
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
