<?php
include '../db.php';
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: index.php");
    exit;
}

$msg = '';

// Handle Purge Cache
if (isset($_GET['purge_cache'])) {
    $cache_dir = '../cache/';
    if (is_dir($cache_dir)) {
        $files = glob($cache_dir . '*.html');
        foreach ($files as $file) {
            if (is_file($file)) unlink($file);
        }
        $msg = "<div class='alert alert-success mt-3 mb-0'><i class='fa-solid fa-broom'></i> Cache cleared successfully!</div>";
    }
}

// Get counts
$total            = $conn->query("SELECT COUNT(*) as c FROM posts")->fetch_assoc()['c'];
$pending_comments = $conn->query("SELECT COUNT(*) as c FROM comments WHERE status='pending'")->fetch_assoc()['c'];
$total_categories = $conn->query("SELECT COUNT(*) as c FROM categories")->fetch_assoc()['c'];
$total_pages      = ($r = $conn->query("SELECT COUNT(*) as c FROM pages")) ? $r->fetch_assoc()['c'] : 0;
$total_writers    = ($r = $conn->query("SELECT COUNT(*) as c FROM writers")) ? $r->fetch_assoc()['c'] : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta name="robots" content="noindex, nofollow">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background-color: #000000; font-family: 'Inter', sans-serif; }
        .featured-on  { background:#fbbf24!important; color:#7c2d00!important; border:none; }
        .featured-off { background:rgba(255,255,255,0.12)!important; color:#9ca3af!important; border:none; }
        .post-row:hover td { background:rgba(255,255,255,0.05)!important; }
        .thumb-sm { width:52px; height:40px; object-fit:cover; border-radius:0; flex-shrink:0; }
        #postSearch::placeholder { color:#6b7280; }
        #postSearch:focus { border-color:#6CC832!important; box-shadow:0 0 0 3px rgba(108,200,50,.2); outline:none; }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    
    <div class="main-content">
        <div>
            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
                <div>
                    <h2 class="fw-bold mb-0 text-white">Dashboard Overview</h2>
                    <p class="text-muted mb-0">Welcome back — here's what's happening on your site.</p>
                </div>
                <a href="?purge_cache=1" class="btn btn-warning fw-bold">
                    <i class="fa-solid fa-bolt me-2"></i> Purge All Cache
                </a>
            </div>
            <?php echo $msg; ?>

            <!-- Stats Cards -->
            <div class="row g-4">
                <?php
include '../db.php';
                $stats = [
                    // label, count, icon, bg-class, text-color, link, chip-bg
                    ['Total Posts', $total,            'fa-thumbtack', 'bg-primary', '#0e2105', 'manage.php',       'rgba(14,33,5,.14)'],
                    ['Categories',         $total_categories, 'fa-tags',           'bg-info',    '#ffffff', 'categories.php',   'rgba(255,255,255,.18)'],
                    ['Writers',            $total_writers,    'fa-pen-nib',        'bg-success', '#ffffff', 'writers.php',      'rgba(255,255,255,.18)'],
                    ['Pages',              $total_pages,      'fa-file-lines',     'bg-info',    '#ffffff', 'manage_pages.php', 'rgba(255,255,255,.18)'],
                    ['Pending Comments',   $pending_comments, 'fa-comments',       'bg-warning', '#3d2c00', 'comments.php',     'rgba(61,44,0,.14)'],
                ];
                foreach ($stats as $s):
                ?>
                <div class="col-sm-6 col-xl-3">
                    <a href="<?php echo $s[5]; ?>" class="text-decoration-none">
                        <div class="card <?php echo $s[3]; ?> border-0 shadow h-100" style="color:<?php echo $s[4]; ?>;">
                            <div class="card-body p-4 d-flex align-items-center justify-content-between">
                                <div>
                                    <div style="font-size:.92rem;font-weight:700;opacity:.85;color:<?php echo $s[4]; ?>;"><?php echo $s[0]; ?></div>
                                    <div class="fw-bold" style="font-size:2.1rem;line-height:1.2;color:<?php echo $s[4]; ?>;"><?php echo $s[1]; ?></div>
                                </div>
                                <span class="d-inline-flex align-items-center justify-content-center" style="width:54px;height:54px;border-radius:0;background:<?php echo $s[6]; ?>;color:<?php echo $s[4]; ?>;">
                                    <i class="fa-solid <?php echo $s[2]; ?> fs-3"></i>
                                </span>
                            </div>
                        </div>
                    </a>
                </div>
                <?php endforeach; ?>
            </div>
                
            <!-- Recent Additions -->
            <div class="mt-5">
                <h4 class="fw-bold mb-3 text-white">Recent Additions</h4>
                <div class="card border-0">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Title</th>
                                        <th>Category</th>
                                        <th>Posted Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
include '../db.php';
                                    $recents = $conn->query("SELECT s.id, s.title, c.name as category_name, s.posted_date FROM posts s LEFT JOIN categories c ON s.category_id = c.id ORDER BY s.id DESC LIMIT 5");
                                    if($recents && $recents->num_rows > 0) {
                                        while($r = $recents->fetch_assoc()){
                                            echo "<tr>
                                                <td>{$r['id']}</td>
                                                <td>".htmlspecialchars($r['title'])."</td>
                                                <td>".htmlspecialchars($r['category_name'] ?: 'Uncategorized')."</td>
                                                <td>".date('d M Y', strtotime($r['posted_date']))."</td>
                                            </tr>";
                                        }
                                    } else {
                                        echo "<tr><td colspan='4' class='text-center py-3' style='border-color:rgba(255,255,255,0.12);background:rgba(20,24,16,0.55);'>No recent posts</td></tr>";
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div><!-- /main-content -->


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
