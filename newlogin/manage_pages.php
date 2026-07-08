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
$countAll = $conn->query("SELECT COUNT(*) as c FROM pages")->fetch_assoc()['c'];
$countPub = $conn->query("SELECT COUNT(*) as c FROM pages WHERE status='publish'")->fetch_assoc()['c'];
$countDraft = $conn->query("SELECT COUNT(*) as c FROM pages WHERE status='draft'")->fetch_assoc()['c'];
$countTrash = $conn->query("SELECT COUNT(*) as c FROM pages WHERE status='trash'")->fetch_assoc()['c'];

// Build Query
$where = "WHERE 1=1";
if ($filter_status != 'all') {
    $where .= " AND status='$filter_status'";
}

if (isset($_GET['s']) && !empty($_GET['s'])) {
    $s = $conn->real_escape_string($_GET['s']);
    $where .= " AND title LIKE '%$s%'";
}

$query = "SELECT * FROM pages $where ORDER BY id DESC LIMIT $offset, $limit";
$result = $conn->query($query);

// Handle Delete (Trash/Restore)
if (isset($_GET['action']) && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    if ($_GET['action'] == 'trash') {
        $conn->query("UPDATE pages SET status='trash' WHERE id=$id");
        header("Location: manage_pages.php?status=" . $filter_status);
        exit;
    } elseif ($_GET['action'] == 'restore') {
        $conn->query("UPDATE pages SET status='draft' WHERE id=$id");
        header("Location: manage_pages.php?status=" . $filter_status);
        exit;
    } elseif ($_GET['action'] == 'delete') {
        $conn->query("DELETE FROM pages WHERE id=$id");
        header("Location: manage_pages.php?status=trash");
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
    <title>Manage Pages - Admin</title>
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
        
        .form-control-search { background: rgba(10,12,8,0.6); border: 1px solid rgba(255,255,255,0.12); color: #ffffff; }
        .form-control-search:focus { background: rgba(10,12,8,0.6); color: #fff; border-color: #6CC832; box-shadow: 0 0 0 0.25rem rgba(108,200,50,0.25);}
    </style>
</head>
<body>
    
    <?php include 'sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3 class="fw-bold mb-0 text-white"><i class="fa-regular fa-file-lines text-primary me-2"></i> Manage Pages</h3>
            <a href="add_page.php" class="btn btn-primary fw-bold"><i class="fa-solid fa-plus me-2"></i> Add New Page</a>
        </div>
        
        <div class="table-card">
            
            <div class="d-flex justify-content-between align-items-center mb-4">
                <!-- Status Filters -->
                <div class="status-links">
                    <a href="manage_pages.php?status=all" class="<?php echo $filter_status=='all' ? 'active' : ''; ?>">All <span style="color:#6c757d;">(<?php echo $countAll; ?>)</span></a>
                    <a href="manage_pages.php?status=publish" class="<?php echo $filter_status=='publish' ? 'active' : ''; ?>">Published <span style="color:#6c757d;">(<?php echo $countPub; ?>)</span></a>
                    <a href="manage_pages.php?status=draft" class="<?php echo $filter_status=='draft' ? 'active' : ''; ?>">Draft <span style="color:#6c757d;">(<?php echo $countDraft; ?>)</span></a>
                    <a href="manage_pages.php?status=trash" class="<?php echo $filter_status=='trash' ? 'active' : ''; ?>">Trash <span style="color:#6c757d;">(<?php echo $countTrash; ?>)</span></a>
                </div>
                
                <!-- Search -->
                <form method="GET" class="d-flex">
                    <input type="hidden" name="status" value="<?php echo htmlspecialchars($filter_status); ?>">
                    <input type="search" name="s" class="form-control form-control-sm form-control-search me-2" placeholder="Search pages..." value="<?php echo isset($_GET['s']) ? htmlspecialchars($_GET['s']) : ''; ?>">
                    <button type="submit" class="btn btn-sm btn-outline-primary">Search</button>
                </form>
            </div>
            
            <div class="table-responsive">
                <table class="table table-hover align-middle border-top">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Slug</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th class="text-end">SEO Status</th>
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
                                <a href="edit_page.php?id=<?php echo $row['id']; ?>" class="post-title"><?php echo htmlspecialchars($row['title']); ?></a>
                                
                                <div class="row-actions">
                                    <?php if($row['status'] == 'trash'): ?>
                                        <a href="manage_pages.php?action=restore&id=<?php echo $row['id']; ?>"><i class="fa-solid fa-trash-arrow-up"></i> Restore</a>
                                        <a href="manage_pages.php?action=delete&id=<?php echo $row['id']; ?>" class="text-danger" onclick="return confirm('Delete permanently? This cannot be undone.');"><i class="fa-solid fa-trash"></i> Delete Permanently</a>
                                    <?php else: ?>
                                        <a href="edit_page.php?id=<?php echo $row['id']; ?>"><i class="fa-solid fa-pen"></i> Edit</a>
                                        <a href="manage_pages.php?action=trash&id=<?php echo $row['id']; ?>" class="text-danger"><i class="fa-solid fa-trash"></i> Trash</a>
                                        <a href="..<?php echo get_page_link($row['slug']); ?>" target="_blank"><i class="fa-solid fa-eye"></i> View</a>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td><span class="text-muted small">/<?php echo htmlspecialchars($row['slug']); ?></span></td>
                            <td>
                                <?php 
                                if($row['status'] == 'publish') echo '<span class="badge bg-success bg-opacity-10 text-success border border-success"><i class="fa-solid fa-check-circle me-1"></i> Published</span>';
                                elseif($row['status'] == 'draft') echo '<span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary"><i class="fa-solid fa-file-lines me-1"></i> Draft</span>';
                                else echo '<span class="badge bg-danger bg-opacity-10 text-danger border border-danger"><i class="fa-solid fa-trash me-1"></i> Trash</span>';
                                ?>
                            </td>
                            <td>
                                <div class="small fw-bold"><?php echo ($row['status'] == 'publish') ? 'Published' : 'Last Modified'; ?></div>
                                <div class="text-muted small"><?php echo date('d M Y, h:i A', strtotime($row['updated_at'])); ?></div>
                            </td>
                            <td class="text-end">
                                <span class="badge bg-secondary bg-opacity-10 text-secondary border">Needs SEO</span>
                            </td>
                        </tr>
                        <?php
include '../db.php';
                            }
                        } else {
                            echo "<tr><td colspan='5' class='text-center py-4 text-muted'>No pages found in this section.</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
            
        </div>
    </div>
</body>
</html>
