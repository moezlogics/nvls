<?php
include '../db.php';
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: index.php");
    exit;
}

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    // Handle add writer
    if (isset($_POST['add_writer'])) {
        $name = $conn->real_escape_string(trim($_POST['name']));
        if (!empty($name)) {
            $check = $conn->query("SELECT id FROM writers WHERE name='$name'");
            if ($check && $check->num_rows > 0) {
                $msg = "<div class='alert alert-danger'>Writer already exists.</div>";
            } else {
                $slug = preg_replace('/[^a-z0-9\-]/', '', strtolower(str_replace(' ', '-', $name)));
                $conn->query("INSERT INTO writers (name, slug) VALUES ('$name', '$slug')");
                clear_page_cache();
                $msg = "<div class='alert alert-success'>Writer added.</div>";
            }
        }
    }

    // Handle update writer
    if (isset($_POST['update_writer'])) {
        $id = (int)$_POST['id'];
        $slug = $conn->real_escape_string(trim($_POST['slug']));
        $slug = preg_replace('/[^a-z0-9\-]/', '', strtolower(str_replace(' ', '-', $slug))); // ensure valid slug format
        $conn->query("UPDATE writers SET slug='$slug' WHERE id=$id");
        clear_page_cache();
        $msg = "<div class='alert alert-success'>Writer slug updated.</div>";
    }
}

// Handle delete writer
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $conn->query("DELETE FROM writers WHERE id=$id");
    clear_page_cache();
    header("Location: writers.php?deleted=1");
    exit;
}
if (isset($_GET['deleted'])) {
    $msg = "<div class='alert alert-warning'>Writer removed.</div>";
}

// Handle clone writer
if (isset($_GET['clone'])) {
    $id = (int)$_GET['clone'];
    $src = $conn->query("SELECT * FROM writers WHERE id=$id");
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
            
            $check = $conn->query("SELECT id FROM writers WHERE name='" . $conn->real_escape_string($newName) . "' OR slug='" . $conn->real_escape_string($newSlug) . "'");
            if ($check && $check->num_rows > 0) {
                $number++;
            } else {
                break;
            }
        } while (true);

        $newNameEsc = $conn->real_escape_string($newName);
        $newSlugEsc = $conn->real_escape_string($newSlug);
        $bio = $conn->real_escape_string($r['bio'] ?? '');
        $short_bio = $conn->real_escape_string($r['short_bio'] ?? '');
        $meta_title = $conn->real_escape_string($r['meta_title'] ?? '');
        $meta_description = $conn->real_escape_string($r['meta_description'] ?? '');
        
        $conn->query("INSERT INTO writers (name, slug, bio, short_bio, meta_title, meta_description) VALUES ('$newNameEsc', '$newSlugEsc', '$bio', '$short_bio', '$meta_title', '$meta_description')");
        clear_page_cache();
        header("Location: writers.php?cloned=1");
        exit;
    }
}
if (isset($_GET['cloned'])) {
    $msg = "<div class='alert alert-success'>Writer cloned successfully.</div>";
}

$items = $conn->query("SELECT * FROM writers ORDER BY name ASC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta name="robots" content="noindex, nofollow">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Writers - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3 class="fw-bold text-white mb-0">Manage Writers</h3>
        </div>

        <?php echo $msg; ?>

        <div class="row">
            <div class="col-md-4 mb-4">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white fw-bold">Add New Writer</div>
                    <div class="card-body">
                        <form method="POST">
                            <?php echo csrf_field(); ?>
                            <div class="mb-3">
                                <label class="form-label">Writer Name</label>
                                <input type="text" name="name" class="form-control" placeholder="e.g. Nemrah Ahmed" required>
                            </div>
                            <button type="submit" name="add_writer" class="btn btn-primary w-100">Add Writer</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-8 mb-4">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white fw-bold">Current Writers</div>
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
                                                    <a href="edit_writer.php?id=<?php echo $item['id']; ?>" class="btn btn-sm btn-primary" title="Edit"><i class="fa-solid fa-pen"></i></a>
                                                    <a href="writers.php?clone=<?php echo $item['id']; ?>" class="btn btn-sm btn-success" title="Clone" onclick="return confirm('Clone this writer?');"><i class="fa-solid fa-copy"></i></a>
                                                    <a href="writers.php?delete=<?php echo $item['id']; ?>" class="btn btn-sm btn-danger" title="Delete" onclick="return confirm('Remove this writer? Posts by this writer will not be deleted.');"><i class="fa-solid fa-trash"></i></a>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr><td colspan="3" class="text-center py-4">No writers found.</td></tr>
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
