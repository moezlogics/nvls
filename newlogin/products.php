<?php
/**
 * newlogin/products.php — Admin product management.
 */
include '../db.php';
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: index.php");
    exit;
}
require_once '../lib/image_seo.php';

$msg = '';

// Handle add product
if (isset($_POST['add_product'])) {
    csrf_verify();
    $title = $conn->real_escape_string(trim($_POST['title']));
    $description = $conn->real_escape_string(trim($_POST['description']));
    $price = (float)$_POST['price'];
    $sale_price = !empty($_POST['sale_price']) ? (float)$_POST['sale_price'] : 'NULL';
    $stock = (int)$_POST['stock'];
    $post_id = !empty($_POST['post_id']) ? (int)$_POST['post_id'] : 'NULL';
    $status = $conn->real_escape_string($_POST['status']);
    $meta_title = $conn->real_escape_string(trim($_POST['meta_title']));
    $meta_description = $conn->real_escape_string(trim($_POST['meta_description']));
    
    // Main Image Upload
    $image = '';
    if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] == 0) {
        $up = media_store_upload($conn, $_FILES['image_file'], __DIR__ . '/../images', 'images');
        if ($up['ok']) {
            $image = $up['path'];
        }
    }

    // Gallery Images Upload
    $gallery_paths = [];
    if (isset($_FILES['gallery_files']) && is_array($_FILES['gallery_files']['name'])) {
        for ($i = 0; $i < count($_FILES['gallery_files']['name']); $i++) {
            if ($_FILES['gallery_files']['error'][$i] == 0) {
                $file = [
                    'name' => $_FILES['gallery_files']['name'][$i],
                    'type' => $_FILES['gallery_files']['type'][$i],
                    'tmp_name' => $_FILES['gallery_files']['tmp_name'][$i],
                    'error' => $_FILES['gallery_files']['error'][$i],
                    'size' => $_FILES['gallery_files']['size'][$i]
                ];
                $up = media_store_upload($conn, $file, __DIR__ . '/../images', 'images');
                if ($up['ok']) {
                    $gallery_paths[] = $up['path'];
                }
            }
        }
    }
    $gallery_images = !empty($gallery_paths) ? implode(',', $gallery_paths) : '';

    // Video Upload
    $video_url = '';
    if (isset($_FILES['video_file']) && $_FILES['video_file']['error'] == 0) {
        $up = media_store_upload($conn, $_FILES['video_file'], __DIR__ . '/../images', 'images');
        if ($up['ok']) {
            $video_url = $up['path'];
        }
    }
    
    $query = "INSERT INTO products (post_id, title, description, price, sale_price, stock, image, gallery_images, video_url, status, meta_title, meta_description) 
              VALUES ($post_id, '$title', '$description', $price, $sale_price, $stock, 
                      " . ($image ? "'$image'" : "NULL") . ", 
                      " . ($gallery_images ? "'$gallery_images'" : "NULL") . ", 
                      " . ($video_url ? "'$video_url'" : "NULL") . ", 
                      '$status', 
                      " . ($meta_title ? "'$meta_title'" : "NULL") . ", 
                      " . ($meta_description ? "'$meta_description'" : "NULL") . ")";
              
    if ($conn->query($query)) {
        clear_page_cache();
        $msg = "<div class='alert alert-success'>Product created successfully!</div>";
    } else {
        $msg = "<div class='alert alert-danger'>Error creating product: " . $conn->error . "</div>";
    }
}

// Handle edit product
if (isset($_POST['edit_product'])) {
    csrf_verify();
    $id = (int)$_POST['id'];
    $title = $conn->real_escape_string(trim($_POST['title']));
    $description = $conn->real_escape_string(trim($_POST['description']));
    $price = (float)$_POST['price'];
    $sale_price = !empty($_POST['sale_price']) ? (float)$_POST['sale_price'] : 'NULL';
    $stock = (int)$_POST['stock'];
    $post_id = !empty($_POST['post_id']) ? (int)$_POST['post_id'] : 'NULL';
    $status = $conn->real_escape_string($_POST['status']);
    $meta_title = $conn->real_escape_string(trim($_POST['meta_title']));
    $meta_description = $conn->real_escape_string(trim($_POST['meta_description']));
    
    // Main Image Upload
    $image_query = "";
    if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] == 0) {
        $up = media_store_upload($conn, $_FILES['image_file'], __DIR__ . '/../images', 'images');
        if ($up['ok']) {
            $image_path = $conn->real_escape_string($up['path']);
            $image_query = ", image='$image_path'";
        }
    }

    // Gallery Images Upload
    $gallery_query = "";
    $gallery_paths = [];
    if (isset($_FILES['gallery_files']) && is_array($_FILES['gallery_files']['name']) && $_FILES['gallery_files']['error'][0] == 0) {
        for ($i = 0; $i < count($_FILES['gallery_files']['name']); $i++) {
            if ($_FILES['gallery_files']['error'][$i] == 0) {
                $file = [
                    'name' => $_FILES['gallery_files']['name'][$i],
                    'type' => $_FILES['gallery_files']['type'][$i],
                    'tmp_name' => $_FILES['gallery_files']['tmp_name'][$i],
                    'error' => $_FILES['gallery_files']['error'][$i],
                    'size' => $_FILES['gallery_files']['size'][$i]
                ];
                $up = media_store_upload($conn, $file, __DIR__ . '/../images', 'images');
                if ($up['ok']) {
                    $gallery_paths[] = $up['path'];
                }
            }
        }
        if (!empty($gallery_paths)) {
            $gallery_images = $conn->real_escape_string(implode(',', $gallery_paths));
            $gallery_query = ", gallery_images='$gallery_images'";
        }
    }

    // Video Upload
    $video_query = "";
    if (isset($_FILES['video_file']) && $_FILES['video_file']['error'] == 0) {
        $up = media_store_upload($conn, $_FILES['video_file'], __DIR__ . '/../images', 'images');
        if ($up['ok']) {
            $video_path = $conn->real_escape_string($up['path']);
            $video_query = ", video_url='$video_path'";
        }
    }
    
    $query = "UPDATE products SET 
              post_id=$post_id, title='$title', description='$description', price=$price, 
              sale_price=$sale_price, stock=$stock, status='$status', 
              meta_title=" . ($meta_title ? "'$meta_title'" : "NULL") . ", 
              meta_description=" . ($meta_description ? "'$meta_description'" : "NULL") . "
              $image_query $gallery_query $video_query 
              WHERE id=$id";
              
    if ($conn->query($query)) {
        clear_page_cache();
        $msg = "<div class='alert alert-success'>Product updated successfully!</div>";
    } else {
        $msg = "<div class='alert alert-danger'>Error updating product: " . $conn->error . "</div>";
    }
}

// Handle delete product
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    if ($conn->query("DELETE FROM products WHERE id=$id")) {
        clear_page_cache();
        header("Location: products.php?deleted=1");
        exit;
    }
}
if (isset($_GET['deleted'])) {
    $msg = "<div class='alert alert-warning'>Product deleted successfully.</div>";
}

// Fetch all products
$productsQuery = $conn->query("
    SELECT p.*, po.title AS novel_title 
    FROM products p
    LEFT JOIN posts po ON p.post_id = po.id
    ORDER BY p.id DESC
");

// Fetch novels list for dropdown linking
$novelsList = [];
$n_res = $conn->query("SELECT id, title FROM posts ORDER BY title ASC");
if ($n_res) {
    while ($n = $n_res->fetch_assoc()) {
        $novelsList[] = $n;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta name="robots" content="noindex, nofollow">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Products - Bookstore Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .form-label { font-weight: 600; font-size: 0.85rem; color: #c5cad4; margin-bottom: 8px;}
        .card { background-color: #1e221c !important; border: 1px solid rgba(255,255,255,0.12) !important; color: #ffffff !important; }
        .card-header { border-bottom: 1px solid rgba(255,255,255,0.12) !important; font-weight:700; background:rgba(0,0,0,0.2) !important;}
        .table { color: #ffffff !important; }
        .table-hover tbody tr:hover { background-color: rgba(255,255,255,0.05) !important; }
        .product-img { width: 44px; height: 66px; object-fit: cover; border-radius: 4px; border: 1px solid rgba(255,255,255,0.1); }
    </style>
</head>
<body class="bg-dark text-white">
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3 class="fw-bold mb-0 text-white"><i class="fa-solid fa-store me-2"></i> Bookstore Products</h3>
            <button class="btn btn-success btn-sm" onclick="showAddForm()"><i class="fa-solid fa-plus me-1"></i> Add New Product</button>
        </div>

        <?php echo $msg; ?>

        <!-- Product Add / Edit Form Card (hidden by default) -->
        <div class="card mb-4 d-none" id="productFormCard">
            <div class="card-header" id="formHeader">Add New Product</div>
            <div class="card-body">
                <form action="" method="POST" enctype="multipart/form-data" id="productForm">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="id" id="productId">
                    <input type="hidden" name="add_product" id="submitAction" value="1">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Product Title *</label>
                            <input type="text" name="title" id="productTitle" class="form-control bg-secondary text-white border-0" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Linked Novel (Optional)</label>
                            <select name="post_id" id="productPostId" class="form-select bg-secondary text-white border-0">
                                <option value="">Select Novel to Link</option>
                                <?php foreach ($novelsList as $nov): ?>
                                    <option value="<?php echo $nov['id']; ?>"><?php echo htmlspecialchars($nov['title']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Product Description</label>
                        <textarea name="description" id="productDescription" class="form-control bg-secondary text-white border-0" rows="3"></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Price (Rs.) *</label>
                            <input type="number" step="0.01" name="price" id="productPrice" class="form-control bg-secondary text-white border-0" required min="0">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Sale Price (Rs.)</label>
                            <input type="number" step="0.01" name="sale_price" id="productSalePrice" class="form-control bg-secondary text-white border-0" min="0">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Stock Level *</label>
                            <input type="number" name="stock" id="productStock" class="form-control bg-secondary text-white border-0" required min="0">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Status</label>
                            <select name="status" id="productStatus" class="form-select bg-secondary text-white border-0">
                                <option value="active">Active</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">SEO Meta Title (Optional)</label>
                            <input type="text" name="meta_title" id="productMetaTitle" class="form-control bg-secondary text-white border-0" placeholder="Meta title for Google search results">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">SEO Meta Description (Optional)</label>
                            <textarea name="meta_description" id="productMetaDescription" class="form-control bg-secondary text-white border-0" rows="1" placeholder="Meta description for Google search results"></textarea>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Cover Image File</label>
                            <input type="file" name="image_file" class="form-control bg-secondary text-white border-0" accept="image/*">
                            <small class="text-muted">If blank, it falls back to linked novel image.</small>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Gallery Images (Multiple)</label>
                            <input type="file" name="gallery_files[]" class="form-control bg-secondary text-white border-0" accept="image/*" multiple>
                            <small class="text-muted">Upload secondary slides for the product page.</small>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Product Video File</label>
                            <input type="file" name="video_file" class="form-control bg-secondary text-white border-0" accept="video/*">
                            <small class="text-muted">Upload product trailer video / story.</small>
                        </div>
                    </div>
                    
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-success" id="formSubmitBtn">Save Product</button>
                        <button type="button" class="btn btn-outline-light" onclick="hideForm()">Cancel</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Products List Card -->
        <div class="card">
            <div class="card-header">All Products (<?php echo ($productsQuery) ? $productsQuery->num_rows : 0; ?>)</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-dark table-hover table-striped mb-0 align-middle">
                        <thead>
                            <tr>
                                <th>Cover</th>
                                <th>Title</th>
                                <th>Linked Novel</th>
                                <th>Price</th>
                                <th>Sale Price</th>
                                <th>Stock</th>
                                <th>Status</th>
                                <th>Media</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($productsQuery && $productsQuery->num_rows > 0): ?>
                                <?php while ($p = $productsQuery->fetch_assoc()): 
                                    $img = !empty($p['image']) ? '../' . $p['image'] : 'https://placehold.co/100x150/004d5e/fff?text=Novel';
                                    $gallery_count = !empty($p['gallery_images']) ? count(explode(',', $p['gallery_images'])) : 0;
                                    $has_video = !empty($p['video_url']);
                                ?>
                                    <tr>
                                        <td><img src="<?php echo htmlspecialchars($img); ?>" class="product-img" alt=""></td>
                                        <td><strong><?php echo htmlspecialchars($p['title']); ?></strong></td>
                                        <td><span class="text-info"><?php echo $p['novel_title'] ? htmlspecialchars($p['novel_title']) : 'Not Linked'; ?></span></td>
                                        <td>Rs. <?php echo number_format($p['price'], 2); ?></td>
                                        <td><?php echo ($p['sale_price'] !== null) ? 'Rs. ' . number_format($p['sale_price'], 2) : '<span class="text-muted">-</span>'; ?></td>
                                        <td><?php echo $p['stock']; ?></td>
                                        <td>
                                            <?php if ($p['status'] == 'active'): ?>
                                                <span class="badge bg-success">Active</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <small class="text-muted d-block">Slides: <?php echo $gallery_count; ?></small>
                                            <?php if ($has_video): ?>
                                                <span class="badge bg-primary text-xs"><i class="fa-solid fa-video me-1"></i> Video</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button class="btn btn-primary btn-sm me-1" onclick='editProduct(<?php echo json_encode($p); ?>)'><i class="fa-solid fa-pen-to-square"></i></button>
                                            <a href="products.php?delete=<?php echo $p['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this product?')"><i class="fa-solid fa-trash-can"></i></a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="9" class="text-center py-4 text-muted">No products configured yet. Click Add Product to start.</td>
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
    <script>
    function showAddForm() {
        $('#productForm')[0].reset();
        $('#productId').val('');
        $('#submitAction').attr('name', 'add_product');
        $('#formHeader').text('Add New Product');
        $('#formSubmitBtn').text('Add Product').removeClass('btn-primary').addClass('btn-success');
        $('#productFormCard').removeClass('d-none');
        // Scroll to form
        document.getElementById('productFormCard').scrollIntoView({ behavior: 'smooth' });
    }
    
    function hideForm() {
        $('#productFormCard').addClass('d-none');
    }
    
    function editProduct(product) {
        showAddForm();
        $('#productId').val(product.id);
        $('#submitAction').attr('name', 'edit_product');
        $('#formHeader').text('Edit Product: ' + product.title);
        $('#formSubmitBtn').text('Update Product').removeClass('btn-success').addClass('btn-primary');
        
        // Fill form fields
        $('#productTitle').val(product.title);
        $('#productPostId').val(product.post_id || '');
        $('#productDescription').val(product.description);
        $('#productPrice').val(product.price);
        $('#productSalePrice').val(product.sale_price || '');
        $('#productStock').val(product.stock);
        $('#productStatus').val(product.status);
        $('#productMetaTitle').val(product.meta_title || '');
        $('#productMetaDescription').val(product.meta_description || '');
    }
    </script>
</body>
</html>
