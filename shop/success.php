<?php
/**
 * shop/success.php — Order Success page.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../lib/coming_soon.php';
coming_soon_gate('shop');

$order_id = (int)($_GET['order_id'] ?? 0);
if ($order_id <= 0) {
    header("Location: index.php");
    exit;
}

// Fetch order details
$o_res = $conn->query("SELECT * FROM orders WHERE id = $order_id LIMIT 1");
if (!$o_res || $o_res->num_rows == 0) {
    header("Location: index.php");
    exit;
}

$order = $o_res->fetch_assoc();

// Fetch ordered items
$items_query = "
    SELECT oi.*, p.title AS product_title
    FROM order_items oi
    LEFT JOIN products p ON oi.product_id = p.id
    WHERE oi.order_id = $order_id
";
$itemsResult = $conn->query($items_query);

$custom_page_title = "Order Placed Successfully! - Bookstore";
$custom_page_description = "Your cash-on-delivery order has been successfully placed.";

include_once __DIR__ . '/../includes/header.php';
?>

<link rel="stylesheet" href="shop.css">

<div class="shop-container" style="max-width: 600px;">
    <div style="background:#fff; border:1px solid var(--shop-border); border-radius:var(--shop-radius); padding:24px; text-align:center; box-shadow:0 4px 6px rgba(0,0,0,0.02); margin-top:20px;">
        
        <div style="width:64px; height:64px; border-radius:50%; background:#d1fae5; color:#10b981; display:flex; align-items:center; justify-content:center; margin:0 auto 16px auto; font-size:2rem;">
            <i class="fa-solid fa-circle-check"></i>
        </div>
        
        <h3 style="font-size:1.4rem; font-weight:800; color:var(--shop-dark); margin:0 0 8px 0;">Order Placed Successfully!</h3>
        <p style="font-size:0.88rem; color:var(--shop-gray); margin:0 0 24px 0;">Thank you for shopping with us. Your cash-on-delivery order has been received and is being processed.</p>
        
        <!-- Order Invoice details -->
        <div style="text-align:left; border-top:1.5px dashed var(--shop-border); padding-top:16px; margin-bottom:20px;">
            <div style="display:flex; justify-content:space-between; font-size:0.85rem; margin-bottom:8px;">
                <span style="color:var(--shop-gray); font-weight:600;">Order ID</span>
                <span style="font-weight:700; color:var(--shop-dark);">#<?php echo $order['id']; ?></span>
            </div>
            <div style="display:flex; justify-content:space-between; font-size:0.85rem; margin-bottom:8px;">
                <span style="color:var(--shop-gray); font-weight:600;">Date</span>
                <span style="font-weight:700; color:var(--shop-dark);"><?php echo date('d M Y, h:i A', strtotime($order['created_at'])); ?></span>
            </div>
            <div style="display:flex; justify-content:space-between; font-size:0.85rem; margin-bottom:8px;">
                <span style="color:var(--shop-gray); font-weight:600;">Contact Phone</span>
                <span style="font-weight:700; color:var(--shop-dark);"><?php echo htmlspecialchars($order['phone']); ?></span>
            </div>
            <div style="display:flex; justify-content:space-between; font-size:0.85rem; margin-bottom:8px;">
                <span style="color:var(--shop-gray); font-weight:600;">Payment Method</span>
                <span style="font-weight:700; color:var(--shop-dark);"><?php echo htmlspecialchars($order['payment_method']); ?> (COD)</span>
            </div>
            <div style="display:flex; flex-direction:column; font-size:0.85rem; margin-bottom:8px; gap:2px;">
                <span style="color:var(--shop-gray); font-weight:600;">Shipping Address</span>
                <span style="font-weight:700; color:var(--shop-dark); line-height:1.4;"><?php echo htmlspecialchars($order['address']); ?></span>
            </div>
        </div>

        <!-- Ordered Items invoice block -->
        <div style="text-align:left; background:var(--shop-light); border-radius:8px; padding:12px; margin-bottom:24px;">
            <h4 style="margin:0 0 10px 0; font-size:0.85rem; font-weight:800; color:var(--shop-dark); text-transform:uppercase; letter-spacing:0.05em;">Items Summary</h4>
            
            <?php if ($itemsResult): ?>
                <?php while ($item = $itemsResult->fetch_assoc()): ?>
                    <div style="display:flex; justify-content:space-between; font-size:0.8rem; margin-bottom:6px;">
                        <span><?php echo htmlspecialchars($item['product_title']); ?> <strong style="color:var(--shop-secondary);">x<?php echo $item['quantity']; ?></strong></span>
                        <span style="font-weight:700;">Rs. <?php echo number_format($item['price'] * $item['quantity'], 2); ?></span>
                    </div>
                <?php endwhile; ?>
            <?php endif; ?>

            <div style="border-top:1px solid #e2e8f0; margin-top:8px; padding-top:8px; display:flex; justify-content:space-between; font-size:0.85rem; font-weight:800; color:var(--shop-primary);">
                <span>Total Amount Paid</span>
                <span>Rs. <?php echo number_format($order['total'], 2); ?></span>
            </div>
        </div>

        <a href="index.php" class="cart-btn-checkout" style="text-decoration:none; margin:0 auto; max-width:200px;">
            <i class="fa-solid fa-store"></i> Continue Shopping
        </a>
    </div>
</div>

<?php
include_once __DIR__ . '/../includes/footer.php';
?>
