<?php
include '../db.php';
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: index.php");
    exit;
}

if (isset($_GET['action']) && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $action = $_GET['action'];

    if ($action === 'read') {
        $conn->query("UPDATE contact_queries SET status='read' WHERE id=$id");
        $msg = "<div class='alert alert-success'>Message marked as read.</div>";
    } elseif ($action === 'archive') {
        $conn->query("UPDATE contact_queries SET status='archived' WHERE id=$id");
        $msg = "<div class='alert alert-warning'>Message archived.</div>";
    } elseif ($action === 'unarchive') {
        $conn->query("UPDATE contact_queries SET status='read' WHERE id=$id");
        $msg = "<div class='alert alert-info'>Message restored.</div>";
    } elseif ($action === 'delete') {
        $conn->query("DELETE FROM contact_queries WHERE id=$id");
        $msg = "<div class='alert alert-danger'>Message deleted.</div>";
    }
}

$limit = 15;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $limit;

$status_filter = isset($_GET['status']) ? $conn->real_escape_string($_GET['status']) : '';
$where = "WHERE 1=1";
if ($status_filter !== '' && in_array($status_filter, ['new', 'read', 'archived'], true)) {
    $where .= " AND status='$status_filter'";
}

$countQuery = $conn->query("SELECT COUNT(id) as total FROM contact_queries $where");
$totalRecords = $countQuery ? (int)$countQuery->fetch_assoc()['total'] : 0;
$totalPages = max(1, (int)ceil($totalRecords / $limit));

$query = "SELECT * FROM contact_queries $where ORDER BY id DESC LIMIT $offset, $limit";
$result = $conn->query($query);

$viewId = isset($_GET['view']) ? (int)$_GET['view'] : 0;
$viewRow = null;
if ($viewId > 0) {
    $vq = $conn->query("SELECT * FROM contact_queries WHERE id=$viewId LIMIT 1");
    if ($vq && $vq->num_rows > 0) {
        $viewRow = $vq->fetch_assoc();
        if ($viewRow['status'] === 'new') {
            $conn->query("UPDATE contact_queries SET status='read' WHERE id=$viewId");
            $viewRow['status'] = 'read';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta name="robots" content="noindex, nofollow">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Messages - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <h2 class="fw-bold mb-0">Contact Messages</h2>
            <a href="page_content.php?tab=contact" class="btn btn-outline-primary btn-sm"><i class="fa-solid fa-pen me-1"></i> Edit Contact Page</a>
        </div>

        <?php if (isset($msg)) echo $msg; ?>

        <?php if ($viewRow): ?>
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fa-solid fa-envelope-open me-2"></i><?php echo htmlspecialchars($viewRow['subject']); ?></span>
                <a href="contact_messages.php" class="btn btn-sm btn-secondary">Back to list</a>
            </div>
            <div class="card-body">
                <div class="row g-3 mb-3">
                    <div class="col-md-4"><strong>Name:</strong> <?php echo htmlspecialchars($viewRow['name']); ?></div>
                    <div class="col-md-4"><strong>Email:</strong> <a href="mailto:<?php echo htmlspecialchars($viewRow['email']); ?>"><?php echo htmlspecialchars($viewRow['email']); ?></a></div>
                    <div class="col-md-4"><strong>Date:</strong> <?php echo date('d M Y, h:i A', strtotime($viewRow['created_at'])); ?></div>
                </div>
                <div class="p-3 rounded" style="background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.1);">
                    <?php echo nl2br(htmlspecialchars($viewRow['message'])); ?>
                </div>
                <div class="mt-3 d-flex gap-2 flex-wrap">
                    <?php if ($viewRow['status'] !== 'archived'): ?>
                    <a href="?action=archive&id=<?php echo $viewRow['id']; ?>" class="btn btn-sm btn-warning"><i class="fa-solid fa-box-archive"></i> Archive</a>
                    <?php else: ?>
                    <a href="?action=unarchive&id=<?php echo $viewRow['id']; ?>" class="btn btn-sm btn-info"><i class="fa-solid fa-rotate-left"></i> Restore</a>
                    <?php endif; ?>
                    <a href="?action=delete&id=<?php echo $viewRow['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this message permanently?');"><i class="fa-solid fa-trash"></i> Delete</a>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="card shadow-sm border-0 mb-4 p-3">
            <form action="" method="GET" class="d-flex align-items-center gap-3 flex-wrap">
                <select name="status" class="form-select w-auto">
                    <option value="">All Messages</option>
                    <option value="new" <?php echo $status_filter === 'new' ? 'selected' : ''; ?>>New</option>
                    <option value="read" <?php echo $status_filter === 'read' ? 'selected' : ''; ?>>Read</option>
                    <option value="archived" <?php echo $status_filter === 'archived' ? 'selected' : ''; ?>>Archived</option>
                </select>
                <button type="submit" class="btn btn-primary">Filter</button>
            </form>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Subject</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result && $result->num_rows > 0): ?>
                                <?php while ($row = $result->fetch_assoc()): ?>
                                <tr class="<?php echo $row['status'] === 'new' ? 'table-active' : ''; ?>">
                                    <td><?php echo (int)$row['id']; ?></td>
                                    <td><?php echo htmlspecialchars($row['name']); ?></td>
                                    <td><?php echo htmlspecialchars($row['email']); ?></td>
                                    <td><small><?php echo htmlspecialchars(mb_substr($row['subject'], 0, 50)); ?></small></td>
                                    <td>
                                        <?php if ($row['status'] === 'new'): ?>
                                        <span class="badge bg-danger">New</span>
                                        <?php elseif ($row['status'] === 'read'): ?>
                                        <span class="badge bg-success">Read</span>
                                        <?php else: ?>
                                        <span class="badge bg-secondary">Archived</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><small><?php echo date('d M Y, h:i A', strtotime($row['created_at'])); ?></small></td>
                                    <td>
                                        <a href="?view=<?php echo (int)$row['id']; ?>" class="btn btn-sm btn-primary" title="View"><i class="fa-solid fa-eye"></i></a>
                                        <a href="?action=delete&id=<?php echo (int)$row['id']; ?>" class="btn btn-sm btn-danger" title="Delete" onclick="return confirm('Delete this message?');"><i class="fa-solid fa-trash"></i></a>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="7" class="text-center py-4 text-muted">No contact messages yet.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php if ($totalPages > 1): ?>
            <div class="card-footer">
                <nav>
                    <ul class="pagination mb-0 justify-content-center">
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?><?php echo $status_filter ? '&status=' . urlencode($status_filter) : ''; ?>"><?php echo $i; ?></a>
                        </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
