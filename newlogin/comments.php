<?php
include '../db.php';
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: index.php");
    exit;
}

// Handle comment status update or deletion
if (isset($_GET['action']) && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $action = $_GET['action'];
    
    if ($action == 'approve') {
        $conn->query("UPDATE comments SET status='approved' WHERE id=$id");
        $msg = "<div class='alert alert-success'>Comment approved successfully!</div>";
    } elseif ($action == 'unapprove') {
        $conn->query("UPDATE comments SET status='pending' WHERE id=$id");
        $msg = "<div class='alert alert-warning'>Comment unapproved!</div>";
    } elseif ($action == 'delete') {
        $conn->query("DELETE FROM comments WHERE id=$id");
        $msg = "<div class='alert alert-danger'>Comment deleted successfully!</div>";
    }
}

// Pagination logic
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Filter logic
$status_filter = isset($_GET['status']) ? $conn->real_escape_string($_GET['status']) : '';
$where = "WHERE 1=1";
if (!empty($status_filter)) {
    $where .= " AND c.status='$status_filter'";
}

$countQuery = $conn->query("SELECT COUNT(id) as total FROM comments c $where");
$totalRecords = $countQuery->fetch_assoc()['total'];
$totalPages = ceil($totalRecords / $limit);

$query = "SELECT c.*, s.title as post_title, s.slug as post_slug FROM comments c LEFT JOIN posts s ON c.post_id = s.id $where ORDER BY c.id DESC LIMIT $offset, $limit";
$result = $conn->query($query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta name="robots" content="noindex, nofollow">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Comments - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #f4f6f9; font-family: 'Inter', sans-serif; }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="fw-bold mb-0">Manage Comments</h2>
        </div>

        <?php if(isset($msg)) echo $msg; ?>

        <!-- Filters -->
        <div class="card shadow-sm border-0 mb-4 p-3 bg-white">
            <form action="" method="GET" class="d-flex align-items-center gap-3">
                <select name="status" class="form-select w-auto">
                    <option value="">All Comments</option>
                    <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending Approval</option>
                    <option value="approved" <?php echo $status_filter == 'approved' ? 'selected' : ''; ?>>Approved</option>
                </select>
                <button type="submit" class="btn btn-primary">Filter</button>
            </form>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th style="width: 30%;">Comment</th>
                                <th>Post</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
include '../db.php';
                            if ($result && $result->num_rows > 0) {
                                while($row = $result->fetch_assoc()) {
                            ?>
                            <tr>
                                <td><?php echo $row['id']; ?></td>
                                <td><?php echo htmlspecialchars($row['name']); ?></td>
                                <td><?php echo htmlspecialchars($row['email']); ?></td>
                                <td><small><?php echo nl2br(htmlspecialchars($row['message'])); ?></small></td>
                                <td><a href="../single.php?slug=<?php echo htmlspecialchars($row['post_slug']); ?>" target="_blank" class="text-decoration-none"><small><?php echo htmlspecialchars($row['post_title']); ?></small></a></td>
                                <td>
                                    <?php if($row['status'] == 'approved'): ?>
                                    <span class="badge bg-success">Approved</span>
                                    <?php else: ?>
                                    <span class="badge bg-warning text-dark">Pending</span>
                                    <?php endif; ?>
                                </td>
                                <td><small><?php echo date('d M Y, h:i A', strtotime($row['created_at'])); ?></small></td>
                                <td>
                                    <?php if($row['status'] == 'pending'): ?>
                                    <a href="?action=approve&id=<?php echo $row['id']; ?>" class="btn btn-sm btn-success" title="Approve"><i class="fa-solid fa-check"></i></a>
                                    <?php else: ?>
                                    <a href="?action=unapprove&id=<?php echo $row['id']; ?>" class="btn btn-sm btn-secondary" title="Unapprove"><i class="fa-solid fa-xmark"></i></a>
                                    <?php endif; ?>
                                    <a href="?action=delete&id=<?php echo $row['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this comment?');" title="Delete"><i class="fa-solid fa-trash"></i></a>
                                </td>
                            </tr>
                            <?php
include '../db.php';
                                }
                            } else {
                                echo "<tr><td colspan='8' class='text-center py-4 text-muted'>No comments found.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <?php if ($totalPages > 1): ?>
        <nav class="mt-4">
            <ul class="pagination justify-content-center">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                    <a class="page-link" href="?page=<?php echo $i; ?>&status=<?php echo htmlspecialchars($status_filter); ?>"><?php echo $i; ?></a>
                </li>
                <?php endfor; ?>
            </ul>
        </nav>
        <?php endif; ?>

    </div>
</body>
</html>
