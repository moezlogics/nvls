<?php
include '../db.php';
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: index.php");
    exit;
}
if ($_SESSION['admin_role'] != 'main_admin') {
    header("Location: dashboard.php");
    exit;
}

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    // Handle add menu item
    if (isset($_POST['add_menu'])) {
        $label = $conn->real_escape_string($_POST['label']);
        $url = $conn->real_escape_string($_POST['url']);
        $sort = (int)$_POST['sort_order'];
        $conn->query("INSERT INTO menu_items (label, url, sort_order) VALUES ('$label', '$url', $sort)");
        $msg = "<div class='alert alert-success'>Menu item added successfully.</div>";
        
        // Purge Cache if exists
        $cache_dir = '../cache/';
        if(is_dir($cache_dir)){
            array_map('unlink', glob("$cache_dir/*.*"));
        }
    }

    // Handle update menu items
    if (isset($_POST['update_menu'])) {
        if (isset($_POST['items']) && is_array($_POST['items'])) {
            foreach ($_POST['items'] as $id => $data) {
                $label = $conn->real_escape_string($data['label']);
                $url = $conn->real_escape_string($data['url']);
                $sort = (int)$data['sort_order'];
                $id = (int)$id;
                $conn->query("UPDATE menu_items SET label='$label', url='$url', sort_order=$sort WHERE id=$id");
            }
            $msg = "<div class='alert alert-success'>Menu updated successfully.</div>";
            
            // Purge Cache if exists
            $cache_dir = '../cache/';
            if(is_dir($cache_dir)){
                array_map('unlink', glob("$cache_dir/*.*"));
            }
        }
    }
}

// Handle delete menu item
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $conn->query("DELETE FROM menu_items WHERE id=$id");
    
    // Purge Cache if exists
    $cache_dir = '../cache/';
    if(is_dir($cache_dir)){
        array_map('unlink', glob("$cache_dir/*.*"));
    }
    
    header("Location: menu.php?deleted=1");
    exit;
}
if (isset($_GET['deleted'])) {
    $msg = "<div class='alert alert-warning'>Menu item deleted.</div>";
}

$items = $conn->query("SELECT * FROM menu_items ORDER BY sort_order ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta name="robots" content="noindex, nofollow">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Menu - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f4f6f9; }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="fw-bold">Manage Menu Manager</h2>
        </div>

        <?php echo $msg; ?>

        <div class="row">
            <!-- Add New Item Form -->
            <div class="col-md-4 mb-4">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white fw-bold">Add New Menu Item</div>
                    <div class="card-body">
                        <form method="POST">
                            <?php echo csrf_field(); ?>
                            <div class="mb-3">
                                <label class="form-label">Menu Label</label>
                                <input type="text" name="label" class="form-control" placeholder="e.g. Stories" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">URL / Link</label>
                                <input type="text" name="url" class="form-control" placeholder="e.g. posts.php?category=5" required>
                                <small class="text-muted">Relative links (e.g. <code>about.php</code>) or absolute (e.g. <code>https://...</code>)</small>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Sort Order</label>
                                <input type="number" name="sort_order" class="form-control" value="0">
                            </div>
                            <button type="submit" name="add_menu" class="btn btn-primary w-100">Add Item</button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Manage Existing Items -->
            <div class="col-md-8 mb-4">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white fw-bold">Current Menu Items</div>
                    <div class="card-body p-0">
                        <form method="POST">
                            <?php echo csrf_field(); ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Label</th>
                                            <th>URL</th>
                                            <th style="width:100px;">Sort</th>
                                            <th style="width:80px;">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if($items && $items->num_rows > 0): ?>
                                            <?php while($item = $items->fetch_assoc()): ?>
                                            <tr>
                                                <td>
                                                    <input type="text" name="items[<?php echo $item['id']; ?>][label]" class="form-control" value="<?php echo htmlspecialchars($item['label']); ?>" required>
                                                </td>
                                                <td>
                                                    <input type="text" name="items[<?php echo $item['id']; ?>][url]" class="form-control" value="<?php echo htmlspecialchars($item['url']); ?>" required>
                                                </td>
                                                <td>
                                                    <input type="number" name="items[<?php echo $item['id']; ?>][sort_order]" class="form-control text-center" value="<?php echo $item['sort_order']; ?>">
                                                </td>
                                                <td class="text-center">
                                                    <a href="menu.php?delete=<?php echo $item['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this menu item?');"><i class="fa-solid fa-trash"></i></a>
                                                </td>
                                            </tr>
                                            <?php endwhile; ?>
                                        <?php else: ?>
                                            <tr><td colspan="4" class="text-center py-4">No menu items found.</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php if($items && $items->num_rows > 0): ?>
                            <div class="card-footer bg-white text-end">
                                <button type="submit" name="update_menu" class="btn btn-success"><i class="fa-solid fa-save me-2"></i> Save Changes</button>
                            </div>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
