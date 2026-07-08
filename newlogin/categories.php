<?php
include '../db.php';
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: index.php");
    exit;
}

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    // Handle add category
    if (isset($_POST['add_cat'])) {
        $name = $conn->real_escape_string(trim($_POST['name']));
        if (!empty($name)) {
            $check = $conn->query("SELECT id FROM categories WHERE name='$name'");
            if ($check && $check->num_rows > 0) {
                $msg = "<div class='alert alert-danger'>Category already exists.</div>";
            } else {
                $slug = preg_replace('/[^a-z0-9\-]/', '', strtolower(str_replace(' ', '-', $name)));
                $conn->query("INSERT INTO categories (name, slug) VALUES ('$name', '$slug')");
                clear_page_cache();
                $msg = "<div class='alert alert-success'>Category added.</div>";
            }
        }
    }

    // Handle update category
    if (isset($_POST['update_cat'])) {
        $id = (int)$_POST['id'];
        $slug = $conn->real_escape_string(trim($_POST['slug']));
        $slug = preg_replace('/[^a-z0-9\-]/', '', strtolower(str_replace(' ', '-', $slug))); // ensure valid slug format
        $conn->query("UPDATE categories SET slug='$slug' WHERE id=$id");
        clear_page_cache();
        $msg = "<div class='alert alert-success'>Category slug updated.</div>";
    }
}

// Handle delete category
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    // Optional: could set posts category_id to NULL or handle safely
    $conn->query("DELETE FROM categories WHERE id=$id");
    clear_page_cache();
    header("Location: categories.php?deleted=1");
    exit;
}
if (isset($_GET['deleted'])) {
    $msg = "<div class='alert alert-warning'>Category removed.</div>";
}

// Handle clone category
if (isset($_GET['clone'])) {
    $id = (int)$_GET['clone'];
    $src = $conn->query("SELECT * FROM categories WHERE id=$id");
    if ($src && $src->num_rows > 0) {
        $r = $src->fetch_assoc();
        
        $baseName = $r['name'];
        $number = 1;
        if (preg_match('/^(.*?)\s+(\d+)$/', $baseName, $matches)) {
            $baseName = $matches[1];
            $number = (int)$matches[2] + 1;
        }

        do {
            $newName = $baseName . ' ' . $number;
            $newSlug = preg_replace('/[^a-z0-9\-]/', '', strtolower(str_replace(' ', '-', $newName)));
            
            $check = $conn->query("SELECT id FROM categories WHERE name='" . $conn->real_escape_string($newName) . "' OR slug='" . $conn->real_escape_string($newSlug) . "'");
            if ($check && $check->num_rows > 0) {
                $number++;
            } else {
                break;
            }
        } while (true);

        $newNameEsc = $conn->real_escape_string($newName);
        $newSlugEsc = $conn->real_escape_string($newSlug);
        $desc       = $conn->real_escape_string($r['description'] ?? '');
        $img        = $conn->real_escape_string($r['image'] ?? '');
        
        $conn->query("INSERT INTO categories (name, slug, description, image) VALUES ('$newNameEsc','$newSlugEsc','$desc','$img')");
        clear_page_cache();
        header("Location: categories.php?cloned=1");
        exit;
    }
}
if (isset($_GET['cloned'])) {
    $msg = "<div class='alert alert-success'>Category cloned successfully.</div>";
}

$items = $conn->query("SELECT * FROM categories ORDER BY name ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta name="robots" content="noindex, nofollow">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Categories - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3 class="fw-bold text-white mb-0">Manage Categories</h3>
        </div>

        <?php echo $msg; ?>

        <div class="row">
            <div class="col-md-4 mb-4">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white fw-bold">Add New Category</div>
                    <div class="card-body">
                        <form method="POST">
                            <?php echo csrf_field(); ?>
                            <div class="mb-3">
                                <label class="form-label">Category Name</label>
                                <input type="text" name="name" class="form-control" placeholder="e.g. Engineering" required>
                            </div>
                            <button type="submit" name="add_cat" class="btn btn-primary w-100">Add Category</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-8 mb-4">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white fw-bold">Current Categories</div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Name</th>
                                        <th>Slug (URL)</th>
                                        <th style="width:100px;">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if($items && $items->num_rows > 0): ?>
                                        <?php while($item = $items->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($item['name']); ?></td>
                                            <td><code>/<?php echo htmlspecialchars($item['slug'] ?? ''); ?>/</code></td>
                                            <td class="text-center">
                                                <div class="d-flex justify-content-center gap-1">
                                                    <a href="edit_category.php?id=<?php echo $item['id']; ?>" class="btn btn-sm btn-primary" title="Edit"><i class="fa-solid fa-pen"></i></a>
                                                    <a href="categories.php?clone=<?php echo $item['id']; ?>" class="btn btn-sm btn-success" title="Clone" onclick="return confirm('Clone this category?');"><i class="fa-solid fa-copy"></i></a>
                                                    <a href="categories.php?delete=<?php echo $item['id']; ?>" class="btn btn-sm btn-danger" title="Delete" onclick="return confirm('Remove this category? Posts in this category will not be deleted.');"><i class="fa-solid fa-trash"></i></a>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr><td colspan="3" class="text-center py-4">No categories found.</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
