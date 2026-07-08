<?php
/**
 * shop/cart_action.php — AJAX controller for shopping cart operations.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../lib/coming_soon.php';
coming_soon_gate('shop', true);

header('Content-Type: application/json');

if (!isset($_SESSION['shop_cart'])) {
    $_SESSION['shop_cart'] = [];
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// Helper function to calculate total items and total price in cart
function get_cart_summary($conn) {
    $count = 0;
    $total = 0.00;
    
    if (!empty($_SESSION['shop_cart'])) {
        $ids = array_map('intval', array_keys($_SESSION['shop_cart']));
        $ids_str = implode(',', $ids);
        
        $res = $conn->query("SELECT id, price, sale_price FROM products WHERE id IN ($ids_str) AND status = 'active'");
        if ($res) {
            while ($p = $res->fetch_assoc()) {
                $qty = (int)$_SESSION['shop_cart'][$p['id']];
                $price = ($p['sale_price'] !== null) ? (float)$p['sale_price'] : (float)$p['price'];
                
                $count += $qty;
                $total += ($price * $qty);
            }
        }
    }
    
    return [
        'count' => $count,
        'total' => $total,
        'total_formatted' => 'Rs. ' . number_format($total, 2)
    ];
}

switch ($action) {
    case 'add':
        $product_id = (int)($_POST['product_id'] ?? 0);
        $qty = (int)($_POST['quantity'] ?? 1);
        
        if ($product_id <= 0 || $qty <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid product or quantity.']);
            exit;
        }
        
        // Check if product exists and has stock
        $res = $conn->query("SELECT id, stock, title FROM products WHERE id = $product_id AND status = 'active' LIMIT 1");
        if (!$res || $res->num_rows == 0) {
            echo json_encode(['success' => false, 'message' => 'Product not found.']);
            exit;
        }
        
        $product = $res->fetch_assoc();
        $current_in_cart = $_SESSION['shop_cart'][$product_id] ?? 0;
        $new_qty = $current_in_cart + $qty;
        
        if ($new_qty > (int)$product['stock']) {
            echo json_encode(['success' => false, 'message' => 'Only ' . $product['stock'] . ' copies available. You have ' . $current_in_cart . ' in cart.']);
            exit;
        }
        
        $_SESSION['shop_cart'][$product_id] = $new_qty;
        $summary = get_cart_summary($conn);
        
        echo json_encode([
            'success' => true,
            'message' => 'Added to cart!',
            'cart_count' => $summary['count'],
            'total_price' => $summary['total_formatted']
        ]);
        break;

    case 'update':
        $product_id = (int)($_POST['product_id'] ?? 0);
        $qty = (int)($_POST['quantity'] ?? 1);
        
        if ($product_id <= 0 || $qty <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid product or quantity.']);
            exit;
        }
        
        // Check if product exists and check stock
        $res = $conn->query("SELECT id, stock FROM products WHERE id = $product_id AND status = 'active' LIMIT 1");
        if (!$res || $res->num_rows == 0) {
            echo json_encode(['success' => false, 'message' => 'Product not found.']);
            exit;
        }
        
        $product = $res->fetch_assoc();
        if ($qty > (int)$product['stock']) {
            echo json_encode(['success' => false, 'message' => 'Only ' . $product['stock'] . ' copies in stock.']);
            exit;
        }
        
        $_SESSION['shop_cart'][$product_id] = $qty;
        $summary = get_cart_summary($conn);
        
        echo json_encode([
            'success' => true,
            'message' => 'Cart updated.',
            'cart_count' => $summary['count'],
            'total_price' => $summary['total_formatted']
        ]);
        break;

    case 'remove':
        $product_id = (int)($_POST['product_id'] ?? 0);
        
        if (isset($_SESSION['shop_cart'][$product_id])) {
            unset($_SESSION['shop_cart'][$product_id]);
        }
        
        $summary = get_cart_summary($conn);
        
        echo json_encode([
            'success' => true,
            'message' => 'Item removed.',
            'cart_count' => $summary['count'],
            'total_price' => $summary['total_formatted']
        ]);
        break;

    case 'clear':
        $_SESSION['shop_cart'] = [];
        echo json_encode([
            'success' => true,
            'message' => 'Cart cleared.',
            'cart_count' => 0,
            'total_price' => 'Rs. 0.00'
        ]);
        break;

    case 'summary':
    default:
        $summary = get_cart_summary($conn);
        echo json_encode([
            'success' => true,
            'cart_count' => $summary['count'],
            'total_price' => $summary['total_formatted']
        ]);
        break;
}
