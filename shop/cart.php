<?php
/**
 * shop/cart.php — Shopping Cart page.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../lib/coming_soon.php';
coming_soon_gate('shop');

$cart_items = [];
$subtotal = 0.00;
$shipping = 150.00; // Flat shipping rate

if (!empty($_SESSION['shop_cart'])) {
    $ids = array_map('intval', array_keys($_SESSION['shop_cart']));
    $ids_str = implode(',', $ids);
    
    $query = "
        SELECT p.*, w.name AS writer_name 
        FROM products p
        LEFT JOIN posts po ON p.post_id = po.id
        LEFT JOIN writers w ON po.writer_id = w.id
        WHERE p.id IN ($ids_str) AND p.status = 'active'
    ";
    $res = $conn->query($query);
    if ($res) {
        while ($p = $res->fetch_assoc()) {
            $qty = (int)$_SESSION['shop_cart'][$p['id']];
            $price = ($p['sale_price'] !== null) ? (float)$p['sale_price'] : (float)$p['price'];
            $item_total = $price * $qty;
            
            $subtotal += $item_total;
            
            $p['quantity'] = $qty;
            $p['price_used'] = $price;
            $p['item_total'] = $item_total;
            $p['image_url'] = !empty($p['image']) ? '../' . ltrim($p['image'], '/') : 'https://placehold.co/300x450/004d5e/fff?text=Novel';
            
            $cart_items[] = $p;
        }
    }
}

// Free shipping over Rs. 2000
if ($subtotal >= 2000 || $subtotal == 0) {
    $shipping = 0.00;
}
$grand_total = $subtotal + $shipping;

$custom_page_title = "Shopping Cart - Bookstore";
$custom_page_description = "Review items in your shopping cart before checking out.";

include_once __DIR__ . '/../includes/header.php';
?>

<link rel="stylesheet" href="shop.css">

<div class="shop-container">
    <h3 class="shop-section-title">
        <i class="fa-solid fa-shopping-cart"></i> Shopping Cart
    </h3>

    <?php if (!empty($cart_items)): ?>
        <div class="checkout-grid">
            <!-- Cart Items List -->
            <div class="checkout-billing" style="padding:0;">
                <div class="cart-table-card" style="margin:0; border:none;">
                    <?php foreach ($cart_items as $item): ?>
                        <div class="cart-item" id="cart-item-<?php echo $item['id']; ?>">
                            <img src="<?php echo htmlspecialchars($item['image_url']); ?>" class="cart-item-image" alt="<?php echo htmlspecialchars($item['title']); ?>">
                            
                            <div class="cart-item-info">
                                <a href="product.php?id=<?php echo $item['id']; ?>" class="cart-item-title">
                                    <?php echo htmlspecialchars($item['title']); ?>
                                </a>
                                <?php if (!empty($item['writer_name'])): ?>
                                    <div style="font-size:0.75rem; color:var(--shop-gray); margin-bottom:4px;">
                                        By <?php echo htmlspecialchars($item['writer_name']); ?>
                                    </div>
                                <?php endif; ?>
                                <span class="cart-item-price">Rs. <?php echo number_format($item['price_used'], 2); ?></span>
                            </div>
                            
                            <div class="cart-item-qty-actions">
                                <div class="sp-details-qty" style="height:34px;">
                                    <button type="button" class="sp-details-qty-btn" style="width:28px;" onclick="updateQty(<?php echo $item['id']; ?>, -1)">-</button>
                                    <input type="number" id="qty-<?php echo $item['id']; ?>" class="sp-details-qty-input" style="width:34px; font-size:0.85rem;" value="<?php echo $item['quantity']; ?>" readonly>
                                    <button type="button" class="sp-details-qty-btn" style="width:28px;" onclick="updateQty(<?php echo $item['id']; ?>, 1, <?php echo $item['stock']; ?>)">+</button>
                                </div>
                                
                                <div style="font-weight:700; font-size:0.88rem; width:80px; text-align:right;" id="item-total-<?php echo $item['id']; ?>">
                                    Rs. <?php echo number_format($item['item_total'], 2); ?>
                                </div>
                                
                                <button type="button" class="cart-item-remove" onclick="removeItem(<?php echo $item['id']; ?>)">
                                    <i class="fa-solid fa-trash-can"></i>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Cart Summary Card -->
            <div class="checkout-summary">
                <h4 style="margin:0 0 16px 0; font-weight:800; color:var(--shop-dark);">Order Summary</h4>
                
                <div class="cart-summary-row">
                    <span>Subtotal</span>
                    <span id="summary-subtotal">Rs. <?php echo number_format($subtotal, 2); ?></span>
                </div>
                
                <div class="cart-summary-row">
                    <span>Shipping</span>
                    <span id="summary-shipping"><?php echo ($shipping > 0) ? 'Rs. ' . number_format($shipping, 2) : 'Free'; ?></span>
                </div>
                
                <?php if ($shipping > 0): ?>
                    <p style="font-size:0.75rem; color:var(--shop-accent); margin:-6px 0 16px 0; font-weight:600;">Add Rs. <?php echo number_format(2000 - $subtotal, 2); ?> more for Free Shipping!</p>
                <?php endif; ?>

                <div class="cart-summary-row cart-summary-total">
                    <span>Total</span>
                    <span id="summary-total">Rs. <?php echo number_format($grand_total, 2); ?></span>
                </div>
                
                <a href="checkout.php" class="cart-btn-checkout">
                    <i class="fa-solid fa-credit-card"></i> Proceed to Checkout
                </a>
                <a href="index.php" style="display:block; text-align:center; margin-top:12px; font-size:0.85rem; font-weight:700; color:var(--shop-secondary); text-decoration:none;">
                    Continue Shopping
                </a>
            </div>
        </div>
    <?php else: ?>
        <div class="shop-empty-state">
            <div class="shop-empty-icon"><i class="fa-solid fa-shopping-bag"></i></div>
            <h4 class="shop-empty-title">Your cart is empty</h4>
            <p class="shop-empty-text">Looks like you haven't added any books to your cart yet.</p>
            <a href="index.php" class="sp-submit" style="text-decoration:none; margin:0 auto; display:inline-flex;">Start Shopping</a>
        </div>
    <?php endif; ?>
</div>

<!-- AJAX Toast Notification -->
<div id="shopToast" style="position:fixed; bottom:20px; right:20px; background:#1e293b; color:#fff; padding:12px 18px; border-radius:8px; font-size:0.85rem; font-weight:600; display:none; align-items:center; gap:8px; box-shadow:0 4px 12px rgba(0,0,0,0.15); z-index:9999; transition:opacity 0.2s;">
    <i class="fa-solid fa-circle-check" style="color:var(--shop-accent);"></i> <span id="shopToastMessage">Item removed!</span>
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
    }, 2000);
}

function updateQty(productId, amount, stockLimit) {
    const input = document.getElementById('qty-' + productId);
    let val = parseInt(input.value) || 1;
    val += amount;
    
    if (val < 1) val = 1;
    if (stockLimit && val > stockLimit) {
        showToast('Only ' + stockLimit + ' copies in stock.', true);
        return;
    }
    
    input.value = val;
    
    // Call AJAX update
    const formData = new FormData();
    formData.append('action', 'update');
    formData.append('product_id', productId);
    formData.append('quantity', val);
    
    fetch('cart_action.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            // Reload page to update subtotals cleanly, or update DOM dynamically
            // Page reload is simple and preserves PHP state changes (like shipping thresholds!)
            window.location.reload();
        } else {
            showToast(data.message, true);
        }
    })
    .catch(err => {
        console.error(err);
        showToast('Connection error.', true);
    });
}

function removeItem(productId) {
    if (!confirm('Are you sure you want to remove this item?')) return;
    
    const formData = new FormData();
    formData.append('action', 'remove');
    formData.append('product_id', productId);
    
    fetch('cart_action.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            // Remove element from DOM or reload page
            window.location.reload();
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
