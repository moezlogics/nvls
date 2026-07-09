<?php
/**
 * shop/checkout.php — Checkout page.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../lib/coming_soon.php';
coming_soon_gate('shop');

// Redirect if cart is empty
if (empty($_SESSION['shop_cart'])) {
    header("Location: index.php");
    exit;
}

$cart_items = [];
$subtotal = 0.00;
$shipping = 150.00; // Flat shipping rate

// Load product details for items in cart
$ids = array_map('intval', array_keys($_SESSION['shop_cart']));
$ids_str = implode(',', $ids);

$query = "
    SELECT p.* 
    FROM products p
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
        
        $cart_items[$p['id']] = $p;
    }
}

// Re-check empty state in case items are inactive
if (empty($cart_items)) {
    $_SESSION['shop_cart'] = [];
    header("Location: index.php");
    exit;
}

// Free shipping threshold
if ($subtotal >= 2000) {
    $shipping = 0.00;
}
$grand_total = $subtotal + $shipping;

// Initialize form values from logged in member
$name = '';
$email = '';
$phone = '';
$address = '';
$member_id = null;

if (isset($_SESSION['member_id'])) {
    $member_id = (int)$_SESSION['member_id'];
    $m_res = $conn->query("SELECT * FROM members WHERE id = $member_id LIMIT 1");
    if ($m_res && $m_res->num_rows > 0) {
        $member = $m_res->fetch_assoc();
        $name = $member['name'] ?? '';
        $email = $member['email'] ?? '';
        $phone = $member['phone'] ?? '';
        // check if address exists in members table (otherwise empty)
        $address = $member['address'] ?? '';
    }
}

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    
    if (empty($name) || empty($email) || empty($phone) || empty($address)) {
        $err = 'Please fill all billing and shipping fields.';
    } else {
        // Start database transaction
        $conn->begin_transaction();
        try {
            // 1. Re-validate stock levels for each item
            foreach ($cart_items as $pid => $item) {
                $qty = $item['quantity'];
                
                // Query fresh stock from DB
                $stock_chk = $conn->query("SELECT stock, title FROM products WHERE id = $pid LIMIT 1");
                $product = $stock_chk->fetch_assoc();
                
                if ((int)$product['stock'] < $qty) {
                    throw new Exception('Stock error: Only ' . $product['stock'] . ' copies of "' . $product['title'] . '" are available. Please adjust your cart.');
                }
            }
            
            // 2. Insert into orders table
            $stmt = $conn->prepare("INSERT INTO orders (member_id, name, email, phone, address, subtotal, shipping, total, payment_method, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'COD', 'pending')");
            $stmt->bind_param("issssddd", $member_id, $name, $email, $phone, $address, $subtotal, $shipping, $grand_total);
            if (!$stmt->execute()) {
                throw new Exception('Failed to save order record.');
            }
            $order_id = $conn->insert_id;
            $stmt->close();
            
            // 3. Insert into order_items and decrement stock levels
            $item_stmt = $conn->prepare("INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
            $stock_stmt = $conn->prepare("UPDATE products SET stock = stock - ? WHERE id = ?");
            
            foreach ($cart_items as $pid => $item) {
                $qty = $item['quantity'];
                $price = $item['price_used'];
                
                $item_stmt->bind_param("iiid", $order_id, $pid, $qty, $price);
                if (!$item_stmt->execute()) {
                    throw new Exception('Failed to save order item details.');
                }
                
                $stock_stmt->bind_param("ii", $qty, $pid);
                if (!$stock_stmt->execute()) {
                    throw new Exception('Failed to update product stock level.');
                }
            }
            
            $item_stmt->close();
            $stock_stmt->close();
            
            // Commit transaction
            $conn->commit();
            
            // Clear cart
            $_SESSION['shop_cart'] = [];
            
            // Redirect to success page
            header("Location: success.php?order_id=" . $order_id);
            exit;
            
        } catch (Exception $e) {
            $conn->rollback();
            $err = $e->getMessage();
        }
    }
}

$custom_page_title = "Checkout - Bookstore";
$custom_page_description = "Complete your order and select Cash on Delivery shipping.";
$meta_robots = 'noindex, nofollow';

include_once __DIR__ . '/../includes/header.php';
?>

<link rel="stylesheet" href="shop.css">

<div class="shop-container">
    <div style="margin-bottom:16px;">
        <a href="cart.php" style="text-decoration:none; color:var(--shop-secondary); font-size:0.88rem; font-weight:700; display:inline-flex; align-items:center; gap:6px;">
            <i class="fa-solid fa-arrow-left"></i> Return to Cart
        </a>
    </div>

    <h3 class="shop-section-title">
        <i class="fa-solid fa-cash-register"></i> Checkout
    </h3>

    <?php if (!empty($err)): ?>
        <div class="shop-alert shop-alert-err">
            <i class="fa-solid fa-triangle-exclamation"></i> <?php echo htmlspecialchars($err); ?>
        </div>
    <?php endif; ?>

    <form action="" method="POST">
        <div class="checkout-grid">
            <!-- Billing & Shipping Details Form -->
            <div class="checkout-billing">
                <h4 style="margin:0 0 16px 0; font-weight:800; color:var(--shop-dark);">Shipping Address</h4>
                
                <div class="shop-form-group">
                    <label class="shop-form-label">Full Name *</label>
                    <input type="text" name="name" class="shop-form-control" value="<?php echo htmlspecialchars($name); ?>" required placeholder="e.g. Muhammad Ali">
                </div>
                
                <div class="row">
                    <div class="col-md-6 shop-form-group">
                        <label class="shop-form-label">Email Address *</label>
                        <input type="email" name="email" class="shop-form-control" value="<?php echo htmlspecialchars($email); ?>" required placeholder="e.g. ali@email.com">
                    </div>
                    <div class="col-md-6 shop-form-group">
                        <label class="shop-form-label">Phone Number *</label>
                        <input type="tel" name="phone" class="shop-form-control" value="<?php echo htmlspecialchars($phone); ?>" required placeholder="e.g. 03001234567">
                    </div>
                </div>
                
                <div class="shop-form-group">
                    <label class="shop-form-label">Complete Shipping Address *</label>
                    <textarea name="address" class="shop-form-control" rows="3" required placeholder="House number, Street name, Area, City, Province..." style="resize:none;"><?php echo htmlspecialchars($address); ?></textarea>
                </div>

                <div style="margin-top:20px; padding:12px; background:#eff6ff; border:1px solid #bfdbfe; border-radius:8px; display:flex; align-items:center; gap:8px;">
                    <i class="fa-solid fa-truck" style="color:var(--shop-secondary);"></i>
                    <span style="font-size:0.8rem; color:#1e40af; font-weight:600;">Payment Method: <strong>Cash on Delivery (COD)</strong></span>
                </div>
            </div>
            
            <!-- Order Items Summary Box -->
            <div class="checkout-summary">
                <h4 style="margin:0 0 16px 0; font-weight:800; color:var(--shop-dark);">Items Ordered</h4>
                
                <div style="max-height:200px; overflow-y:auto; border-bottom:1px solid var(--shop-border); margin-bottom:16px;">
                    <?php foreach ($cart_items as $item): ?>
                        <div style="display:flex; justify-content:space-between; font-size:0.85rem; margin-bottom:10px; padding-right:6px;">
                            <span style="white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:200px;">
                                <?php echo htmlspecialchars($item['title']); ?> <strong style="color:var(--shop-secondary);">x<?php echo $item['quantity']; ?></strong>
                            </span>
                            <span style="font-weight:700;">Rs. <?php echo number_format($item['item_total'], 2); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="cart-summary-row">
                    <span>Subtotal</span>
                    <span>Rs. <?php echo number_format($subtotal, 2); ?></span>
                </div>
                
                <div class="cart-summary-row">
                    <span>Shipping</span>
                    <span><?php echo ($shipping > 0) ? 'Rs. ' . number_format($shipping, 2) : 'Free'; ?></span>
                </div>
                
                <div class="cart-summary-row cart-summary-total">
                    <span>Total</span>
                    <span>Rs. <?php echo number_format($grand_total, 2); ?></span>
                </div>
                
                <button type="submit" class="cart-btn-checkout" style="border:none;">
                    <i class="fa-solid fa-truck-fast"></i> Place Order (COD)
                </button>
            </div>
        </div>
    </form>
</div>

<?php
include_once __DIR__ . '/../includes/footer.php';
?>
