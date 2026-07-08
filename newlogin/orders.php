<?php
/**
 * newlogin/orders.php — Admin order management.
 */
include '../db.php';
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: index.php");
    exit;
}

$msg = '';

// Handle order status update
if (isset($_POST['update_status'])) {
    csrf_verify();
    $order_id = (int)$_POST['order_id'];
    $status = $conn->real_escape_string($_POST['status']);
    
    $query = "UPDATE orders SET status='$status' WHERE id=$order_id";
    if ($conn->query($query)) {
        clear_page_cache();
        $msg = "<div class='alert alert-success'>Order #$order_id status updated to '$status'.</div>";
    } else {
        $msg = "<div class='alert alert-danger'>Error updating order: " . $conn->error . "</div>";
    }
}

// Fetch all orders
$ordersQuery = $conn->query("
    SELECT * 
    FROM orders 
    ORDER BY id DESC
");

// Build order items array to load details easily
$orderItems = [];
$items_res = $conn->query("
    SELECT oi.*, p.title AS product_title 
    FROM order_items oi
    LEFT JOIN products p ON oi.product_id = p.id
");
if ($items_res) {
    while ($item = $items_res->fetch_assoc()) {
        $orderItems[$item['order_id']][] = $item;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta name="robots" content="noindex, nofollow">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Orders - Bookstore Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .card { background-color: #1e221c !important; border: 1px solid rgba(255,255,255,0.12) !important; color: #ffffff !important; }
        .card-header { border-bottom: 1px solid rgba(255,255,255,0.12) !important; font-weight:700; background:rgba(0,0,0,0.2) !important;}
        .table { color: #ffffff !important; }
        .table-hover tbody tr:hover { background-color: rgba(255,255,255,0.05) !important; }
        .order-details-box { background: rgba(0,0,0,0.2); padding: 12px; border-radius: 6px; border: 1px solid rgba(255,255,255,0.05); }
    </style>
</head>
<body class="bg-dark text-white">
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <h3 class="fw-bold mb-4 text-white"><i class="fa-solid fa-truck-fast me-2"></i> Bookstore Orders</h3>

        <?php echo $msg; ?>

        <!-- Orders Table Card -->
        <div class="card">
            <div class="card-header">All Orders (<?php echo ($ordersQuery) ? $ordersQuery->num_rows : 0; ?>)</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-dark table-hover table-striped mb-0 align-middle">
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Date</th>
                                <th>Customer Details</th>
                                <th>Shipping Address</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Items Summary</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($ordersQuery && $ordersQuery->num_rows > 0): ?>
                                <?php while ($o = $ordersQuery->fetch_assoc()): 
                                    $o_id = $o['id'];
                                    $items = $orderItems[$o_id] ?? [];
                                ?>
                                    <tr>
                                        <td><strong>#<?php echo $o_id; ?></strong></td>
                                        <td style="font-size:0.82rem; white-space:nowrap;"><?php echo date('d M Y, h:i A', strtotime($o['created_at'])); ?></td>
                                        <td>
                                            <div style="font-size:0.85rem; font-weight:700;"><?php echo htmlspecialchars($o['name']); ?></div>
                                            <div style="font-size:0.75rem; color:#a1a1aa;"><?php echo htmlspecialchars($o['phone']); ?></div>
                                            <div style="font-size:0.75rem; color:#a1a1aa;"><?php echo htmlspecialchars($o['email']); ?></div>
                                        </td>
                                        <td style="font-size:0.82rem; max-width:200px;"><?php echo htmlspecialchars($o['address']); ?></td>
                                        <td style="white-space:nowrap;">
                                            <div style="font-size:0.85rem; font-weight:700;">Rs. <?php echo number_format($o['total'], 2); ?></div>
                                            <div style="font-size:0.72rem; color:#a1a1aa;">Ship: <?php echo ($o['shipping'] > 0) ? 'Rs. ' . number_format($o['shipping'], 0) : 'Free'; ?></div>
                                        </td>
                                        <td>
                                            <?php 
                                            $st = $o['status'];
                                            if ($st == 'pending') echo '<span class="badge bg-warning text-dark">Pending</span>';
                                            elseif ($st == 'processing') echo '<span class="badge bg-info">Processing</span>';
                                            elseif ($st == 'completed') echo '<span class="badge bg-success">Completed</span>';
                                            else echo '<span class="badge bg-danger">Cancelled</span>';
                                            ?>
                                        </td>
                                        <td>
                                            <div class="order-details-box" style="font-size:0.78rem;">
                                                <?php foreach ($items as $item): ?>
                                                    <div class="mb-1">
                                                        - <?php echo htmlspecialchars($item['product_title']); ?> 
                                                        <strong class="text-warning">x<?php echo $item['quantity']; ?></strong> 
                                                        <span style="color:#a1a1aa;">(Rs.<?php echo number_format($item['price'], 0); ?>)</span>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <!-- Update Status Form -->
                                            <form action="" method="POST" class="d-flex align-items-center gap-1" style="margin:0;">
                                                <?php echo csrf_field(); ?>
                                                <input type="hidden" name="order_id" value="<?php echo $o_id; ?>">
                                                <select name="status" class="form-select form-select-sm bg-secondary text-white border-0" style="width:110px;">
                                                    <option value="pending" <?php echo ($st == 'pending') ? 'selected' : ''; ?>>Pending</option>
                                                    <option value="processing" <?php echo ($st == 'processing') ? 'selected' : ''; ?>>Processing</option>
                                                    <option value="completed" <?php echo ($st == 'completed') ? 'selected' : ''; ?>>Completed</option>
                                                    <option value="cancelled" <?php echo ($st == 'cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                                                </select>
                                                <button type="submit" name="update_status" class="btn btn-primary btn-sm"><i class="fa-solid fa-save"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="text-center py-4 text-muted">No orders placed yet.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
