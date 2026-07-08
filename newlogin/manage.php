<?php
include '../db.php';
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: index.php");
    exit;
}

// Pagination setup
$limit = 20;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Status filter
$filter_status = isset($_GET['status']) ? $conn->real_escape_string($_GET['status']) : 'all';

// Counts for the top links
$countAll = $conn->query("SELECT COUNT(*) as c FROM posts")->fetch_assoc()['c'];
$countActive = $conn->query("SELECT COUNT(*) as c FROM posts WHERE status='active'")->fetch_assoc()['c'];
$countDraft = $conn->query("SELECT COUNT(*) as c FROM posts WHERE status='draft'")->fetch_assoc()['c'];

// Build Query
$where = "WHERE 1=1";
if ($filter_status != 'all') {
    $where .= " AND s.status='$filter_status'";
}

if (isset($_GET['s']) && !empty($_GET['s'])) {
    $s = $conn->real_escape_string($_GET['s']);
    $where .= " AND s.title LIKE '%$s%'";
}

$query = "SELECT s.*, c.name as category_name, w.name as writer_name FROM posts s 
          LEFT JOIN categories c ON s.category_id = c.id 
          LEFT JOIN writers w ON s.writer_id = w.id 
          $where ORDER BY s.id DESC LIMIT $offset, $limit";
$result = $conn->query($query);

// Handle Actions (Delete/Draft/Publish)
if (isset($_GET['action']) && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    if ($_GET['action'] == 'active') {
        $conn->query("UPDATE posts SET status='active' WHERE id=$id");
        header("Location: manage.php?status=" . $filter_status);
        exit;
    } elseif ($_GET['action'] == 'draft') {
        $conn->query("UPDATE posts SET status='draft' WHERE id=$id");
        header("Location: manage.php?status=" . $filter_status);
        exit;
    } elseif ($_GET['action'] == 'delete') {
        $conn->query("DELETE FROM posts WHERE id=$id");
        header("Location: manage.php?status=" . $filter_status);
        exit;
    } elseif ($_GET['action'] == 'clone') {
        $src = $conn->query("SELECT * FROM posts WHERE id=$id");
        if ($src && $src->num_rows > 0) {
            $r = $src->fetch_assoc();
            
            $baseTitle = $r['title'];
            $number = 1;
            if (preg_match('/^(.*?)\s+(\d+)$/', $baseTitle, $matches)) {
                $baseTitle = $matches[1];
                $number = (int)$matches[2] + 1;
            }

            do {
                $newTitle = $baseTitle . ' ' . $number;
                $newSlug = preg_replace('/[^a-z0-9\-]/', '', strtolower(str_replace(' ', '-', $newTitle)));
                
                $check = $conn->query("SELECT id FROM posts WHERE title='" . $conn->real_escape_string($newTitle) . "' OR slug='" . $conn->real_escape_string($newSlug) . "'");
                if ($check && $check->num_rows > 0) {
                    $number++;
                } else {
                    break;
                }
            } while (true);

            $newTitleEsc = $conn->real_escape_string($newTitle);
            $newSlugEsc = $conn->real_escape_string($newSlug);
            
            $category_id = (int)$r['category_id'];
            $additional_categories = $conn->real_escape_string($r['additional_categories'] ?? '');
            $writer_id = $r['writer_id'] !== null ? (int)$r['writer_id'] : 'NULL';
            $description = $conn->real_escape_string($r['description'] ?? '');
            $image = $conn->real_escape_string($r['image'] ?? '');
            $read_online_url = $conn->real_escape_string($r['read_online_url'] ?? '');
            $download_url = $conn->real_escape_string($r['download_url'] ?? '');
            $file_size = $conn->real_escape_string($r['file_size'] ?? '');
            $file_type = $conn->real_escape_string($r['file_type'] ?? '');
            $status = 'draft'; // Start as draft
            
            $query = "INSERT INTO posts (title, slug, category_id, additional_categories, writer_id, description, image, read_online_url, download_url, file_size, file_type, status) 
                      VALUES ('$newTitleEsc', '$newSlugEsc', $category_id, '$additional_categories', $writer_id, '$description', '$image', '$read_online_url', '$download_url', '$file_size', '$file_type', '$status')";
            $conn->query($query);
        }
        header("Location: manage.php?status=" . $filter_status);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta name="robots" content="noindex, nofollow">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Posts - Admin</title>
    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        .table-card { background: rgba(20,24,16,0.55); border-radius:0; border: 1px solid rgba(255,255,255,0.12); padding: 25px;}
        
        .status-links a { color: #c5cad4; text-decoration: none; margin-right: 15px; font-weight: 500; }
        .status-links a.active { color: #6CC832; font-weight: 700; border-bottom: 2px solid #6CC832; padding-bottom: 5px;}
        .status-links a:hover { color: #ffffff; }
        
        .table { color: #ffffff; border-color: rgba(255,255,255,0.12); }
        .table th { background: rgba(10,12,8,0.6) !important; border-bottom: 1px solid rgba(255,255,255,0.12) !important; font-size: 0.85rem; text-transform: uppercase; color: #c5cad4; }
        .table td { vertical-align: middle; border-color: rgba(255,255,255,0.12); background: rgba(20,24,16,0.55); }
        .table-hover tbody tr:hover td { background-color: #22252a !important; color: #fff !important; }
        
        .post-title { font-weight: 600; color: #ffffff; text-decoration: none; font-size: 1rem;}
        .post-title:hover { color: #6CC832; text-decoration: underline;}
        
        .row-actions { font-size: 0.85rem; margin-top: 5px; opacity: 0; transition: 0.2s;}
        tr:hover .row-actions { opacity: 1; }
        .row-actions a { color: #6CC832; text-decoration: none; margin-right: 10px;}
        .row-actions a:hover { text-decoration: underline; }
        .row-actions .text-danger { color: #dc3545 !important; }
        .row-actions .text-warning { color: #ffc107 !important; }
        
        .form-control-search { background: rgba(10,12,8,0.6); border: 1px solid rgba(255,255,255,0.12); color: #ffffff; }
        .form-control-search:focus { background: rgba(10,12,8,0.6); color: #fff; border-color: #6CC832; box-shadow: 0 0 0 0.25rem rgba(108,200,50,0.25);}
    </style>
</head>
<body>
    
    <?php include 'sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3 class="fw-bold mb-0 text-white"><i class="fa-solid fa-thumbtack text-primary me-2"></i> Manage Posts</h3>
            <a href="add.php" class="btn btn-primary fw-bold"><i class="fa-solid fa-plus me-2"></i> Add New Post</a>
        </div>
        
        <div class="table-card">
            
            <div class="d-flex justify-content-between align-items-center mb-4">
                <!-- Status Filters -->
                <div class="status-links">
                    <a href="manage.php?status=all" class="<?php echo $filter_status=='all' ? 'active' : ''; ?>">All <span style="color:#6c757d;">(<?php echo $countAll; ?>)</span></a>
                    <a href="manage.php?status=active" class="<?php echo $filter_status=='active' ? 'active' : ''; ?>">Active <span style="color:#6c757d;">(<?php echo $countActive; ?>)</span></a>
                    <a href="manage.php?status=draft" class="<?php echo $filter_status=='draft' ? 'active' : ''; ?>">Draft <span style="color:#6c757d;">(<?php echo $countDraft; ?>)</span></a>
                </div>
                
                <!-- Search -->
                <form method="GET" class="d-flex">
                    <input type="hidden" name="status" value="<?php echo htmlspecialchars($filter_status); ?>">
                    <input type="search" name="s" class="form-control form-control-sm form-control-search me-2" placeholder="Search posts..." value="<?php echo isset($_GET['s']) ? htmlspecialchars($_GET['s']) : ''; ?>">
                    <button type="submit" class="btn btn-sm btn-outline-primary">Search</button>
                </form>
            </div>
            
            <div class="table-responsive">
                <table class="table table-hover align-middle border-top">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Category</th>
                            <th>Writer</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
include '../db.php';
                        if($result->num_rows > 0) {
                            while($row = $result->fetch_assoc()) {
                        ?>
                        <tr>
                            <td>
                                <a href="edit.php?id=<?php echo $row['id']; ?>" class="post-title"><?php echo htmlspecialchars($row['title']); ?></a>
                                
                                <div class="row-actions">
                                    <a href="edit.php?id=<?php echo $row['id']; ?>"><i class="fa-solid fa-pen"></i> Edit</a>
                                    
                                    <?php if($row['status'] == 'active'): ?>
                                        <a href="manage.php?action=draft&id=<?php echo $row['id']; ?>" class="text-warning"><i class="fa-solid fa-file-lines"></i> Set to Draft</a>
                                    <?php else: ?>
                                        <a href="manage.php?action=active&id=<?php echo $row['id']; ?>" class="text-success"><i class="fa-solid fa-check-circle"></i> Publish</a>
                                    <?php endif; ?>
                                    
                                    <a href="manage.php?action=clone&id=<?php echo $row['id']; ?>&status=<?php echo htmlspecialchars($filter_status); ?>" class="text-info" onclick="return confirm('Clone this post as a draft?');"><i class="fa-solid fa-copy"></i> Clone</a>
                                    
                                    <a href="manage.php?action=delete&id=<?php echo $row['id']; ?>" class="text-danger" onclick="return confirm('Delete permanently?');"><i class="fa-solid fa-trash"></i> Delete</a>
                                    
                                    <a href="..<?php echo get_post_link($row['id'], $row['slug']); ?>" target="_blank"><i class="fa-solid fa-eye"></i> View</a>
                                </div>
                            </td>
                            <td><?php echo htmlspecialchars($row['category_name'] ?: 'Uncategorized'); ?></td>
                            <td><?php echo htmlspecialchars($row['writer_name'] ?: 'N/A'); ?></td>
                            <td>
                                <?php 
                                if($row['status'] == 'active') echo '<span class="badge bg-success bg-opacity-10 text-success border border-success"><i class="fa-solid fa-check-circle me-1"></i> Active</span>';
                                else echo '<span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary"><i class="fa-solid fa-file-lines me-1"></i> Draft</span>';
                                ?>
                            </td>
                            <td>
                                <div class="text-muted small"><?php echo date('d M Y, h:i A', strtotime($row['posted_date'])); ?></div>
                            </td>
                        </tr>
                        <?php
include '../db.php';
                            }
                        } else {
                            echo "<tr><td colspan='5' class='text-center py-4 text-muted'>No posts found.</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
            
        </div>
    </div>
</body>
</html>
