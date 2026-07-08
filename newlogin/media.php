<?php
include '../db.php';
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: index.php");
    exit;
}
require_once '../lib/image_seo.php';

$msg = '';
$upload_dir = '../images/';

// Handle Upload (AI generates ALT + Caption + Description)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['media_file'])) {
    csrf_verify();
    $res = media_store_upload($conn, $_FILES['media_file'], __DIR__ . '/../images', 'images');
    if ($res['ok']) {
        $msg = "<div class='alert alert-success'><i class='fa-solid fa-check-circle'></i> Image uploaded — AI added ALT, caption &amp; description.</div>";
    } else {
        $msg = "<div class='alert alert-danger'>" . htmlspecialchars($res['error']) . "</div>";
    }
}

// Map stored SEO metadata by filename for the grid
ensure_media_table($conn);
$mediaMap = [];
if ($mres = $conn->query("SELECT * FROM media")) {
    while ($m = $mres->fetch_assoc()) $mediaMap[$m['filename']] = $m;
}

// Handle Delete
if (isset($_GET['delete'])) {
    $file_to_delete = basename($_GET['delete']);
    $file_path = $upload_dir . $file_to_delete;
    if (file_exists($file_path) && is_file($file_path)) {
        unlink($file_path);
        header("Location: media.php?deleted=1");
        exit;
    }
}

if (isset($_GET['deleted'])) {
    $msg = "<div class='alert alert-success'><i class='fa-solid fa-trash'></i> Image deleted successfully!</div>";
}

// Fetch Images
$images = [];
if (is_dir($upload_dir)) {
    if ($dh = opendir($upload_dir)) {
        while (($file = readdir($dh)) !== false) {
            if ($file != '.' && $file != '..' && is_file($upload_dir . $file)) {
                $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'])) {
                    $images[] = [
                        'name' => $file,
                        'path' => $upload_dir . $file,
                        'size' => filesize($upload_dir . $file),
                        'time' => filemtime($upload_dir . $file)
                    ];
                }
            }
        }
        closedir($dh);
    }
}

// Sort images by newest first
usort($images, function($a, $b) {
    return $b['time'] - $a['time'];
});

function formatBytes($bytes, $precision = 2) { 
    $units = array('B', 'KB', 'MB', 'GB', 'TB'); 
    $bytes = max($bytes, 0); 
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024)); 
    $pow = min($pow, count($units) - 1); 
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow]; 
}

$site_url_full = (isset($_SERVER['HTTPS']) ? "https" : "http") . "://$_SERVER[HTTP_HOST]" . $base_path . "/images/";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta name="robots" content="noindex, nofollow">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Media Library - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .media-card { background: rgba(20,24,16,0.55); border: 1px solid rgba(255,255,255,0.12); border-radius:0; overflow: hidden; transition: 0.3s; }
        .media-card:hover { transform: translateY(-3px); box-shadow: 0 5px 15px rgba(0,0,0,0.3); border-color: #6CC832;}
        .media-img-wrap { height: 160px; background: rgba(10,12,8,0.6); display: flex; align-items: center; justify-content: center; overflow: hidden; border-bottom: 1px solid rgba(255,255,255,0.12);}
        .media-img-wrap img { width: 100%; height: 100%; object-fit: cover; }
        .media-info { padding: 12px; }
        .media-name { font-size: 0.85rem; font-weight: 600; color: #ffffff; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-bottom: 4px; }
        .media-meta { font-size: 0.75rem; color: #c5cad4; margin-bottom: 10px; }
        .seo-meta { font-size: 0.72rem; color: #c5cad4; margin-bottom: 10px; max-height: 150px; overflow: auto; }
        .seo-meta p { margin: 0 0 5px; line-height: 1.45; }
        .seo-meta span { display: inline-block; font-size: 0.58rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.4px; color: #0e2105; background: #6CC832; padding: 0 5px; margin-right: 4px; }
        .seo-missing { color: #9ca3af; font-style: italic; font-size: 0.72rem; margin-bottom: 10px; }
        .btn-copy { background: rgba(255,255,255,0.12); color: #ffffff; border: 1px solid #495057; font-size: 0.75rem; transition: 0.2s;}
        .btn-copy:hover { background: #6CC832; color: white; border-color: #6CC832;}
        
        .upload-area { border: 2px dashed rgba(255,255,255,0.12); border-radius:0; padding: 40px; text-align: center; background: rgba(20,24,16,0.55); cursor: pointer; transition: 0.3s;}
        .upload-area:hover { border-color: #6CC832; background: #1e2229;}
        .upload-icon { font-size: 3rem; color: #6CC832; margin-bottom: 15px;}
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3 class="fw-bold mb-0 text-white"><i class="fa-regular fa-image text-primary me-2"></i> Media Library</h3>
            <span class="badge bg-primary fs-6"><?php echo count($images); ?> Files</span>
        </div>

        <?php echo $msg; ?>

        <!-- Upload Form -->
        <div class="card mb-5">
            <div class="card-body p-4">
                <form action="" method="POST" enctype="multipart/form-data" id="uploadForm">
                            <?php echo csrf_field(); ?>
                    <div class="upload-area" onclick="document.getElementById('fileInput').click()">
                        <i class="fa-solid fa-cloud-arrow-up upload-icon"></i>
                        <h5 class="text-white mb-2">Click to Upload Image</h5>
                        <p class="text-muted mb-0 small">Supports JPG, PNG, WEBP, GIF (Max 5MB)</p>
                    </div>
                    <input type="file" name="media_file" id="fileInput" class="d-none" accept="image/*" onchange="document.getElementById('uploadForm').submit()">
                </form>
            </div>
        </div>

        <!-- Media Grid -->
        <h5 class="fw-bold mb-4 text-white">Uploaded Files</h5>
        
        <div class="row g-4">
            <?php if(empty($images)): ?>
                <div class="col-12">
                    <div class="alert text-center py-5" style="background:rgba(20,24,16,0.55); border:1px solid rgba(255,255,255,0.12); color:#c5cad4;">
                        <i class="fa-regular fa-image fs-1 mb-3"></i><br>
                        No images found in the library. Upload your first image above.
                    </div>
                </div>
            <?php else: ?>
                <?php foreach($images as $img): $meta = $mediaMap[$img['name']] ?? null; ?>
                <div class="col-6 col-md-4 col-lg-3">
                    <div class="media-card">
                        <div class="media-img-wrap">
                            <img src="<?php echo $img['path']; ?>" alt="<?php echo htmlspecialchars($meta['alt_text'] ?? $img['name']); ?>" loading="lazy">
                        </div>
                        <div class="media-info">
                            <div class="media-name" title="<?php echo htmlspecialchars($img['name']); ?>"><?php echo htmlspecialchars($img['name']); ?></div>
                            <?php if($meta && ($meta['alt_text'] || $meta['caption'] || $meta['description'])): ?>
                            <div class="seo-meta">
                                <?php if(!empty($meta['alt_text'])): ?><p><span>ALT</span> <?php echo htmlspecialchars($meta['alt_text']); ?></p><?php endif; ?>
                                <?php if(!empty($meta['caption'])): ?><p><span>Caption</span> <?php echo htmlspecialchars($meta['caption']); ?></p><?php endif; ?>
                                <?php if(!empty($meta['description'])): ?><p><span>Desc</span> <?php echo htmlspecialchars($meta['description']); ?></p><?php endif; ?>
                            </div>
                            <?php else: ?>
                            <div class="seo-meta seo-missing"><i class="fa-solid fa-circle-info"></i> No AI metadata (older upload)</div>
                            <?php endif; ?>
                            <div class="media-meta d-flex justify-content-between">
                                <span><?php echo formatBytes($img['size']); ?></span>
                                <span><?php echo date('d M Y', $img['time']); ?></span>
                            </div>
                            <div class="d-flex gap-2">
                                <button class="btn btn-sm btn-copy flex-grow-1" onclick="copyToClipboard('<?php echo $site_url_full . htmlspecialchars($img['name']); ?>', this)">
                                    <i class="fa-regular fa-copy"></i> Copy
                                </button>
                                <a href="?delete=<?php echo urlencode($img['name']); ?>" class="btn btn-sm btn-danger px-2" onclick="return confirm('Are you sure you want to delete this image?');" title="Delete">
                                    <i class="fa-solid fa-trash"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function copyToClipboard(text, btn) {
            navigator.clipboard.writeText(text).then(function() {
                var originalHtml = btn.innerHTML;
                btn.innerHTML = '<i class="fa-solid fa-check"></i> Copied!';
                btn.classList.add('bg-success', 'text-white');
                btn.classList.remove('btn-copy');
                setTimeout(function() {
                    btn.innerHTML = originalHtml;
                    btn.classList.remove('bg-success', 'text-white');
                    btn.classList.add('btn-copy');
                }, 2000);
            }, function(err) {
                console.error('Could not copy text: ', err);
            });
        }
    </script>
</body>
</html>
