<?php
include '../db.php';
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: index.php");
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header("Location: writers.php");
    exit;
}

$msg = '';

if (isset($_GET['remove_profile_pic'])) {
    $conn->query("UPDATE writers SET profile_pic='' WHERE id=$id");
    clear_page_cache();
    header("Location: edit_writer.php?id=$id");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    
    $name = $conn->real_escape_string(trim($_POST['name']));
    $slug = $conn->real_escape_string(trim($_POST['slug']));
    $slug = preg_replace('/[^a-z0-9\-]/', '', strtolower(str_replace(' ', '-', $slug))); // format slug
    
    $raw_bio = $_POST['bio'];
    $clean_bio = preg_replace('/<(img|iframe|script)\b[^>]*>/i', '', $raw_bio);
    $bio = $conn->real_escape_string($clean_bio);
    
    $meta_title = $conn->real_escape_string(trim($_POST['meta_title']));
    $meta_description = $conn->real_escape_string(trim($_POST['meta_description']));
    $short_bio = $conn->real_escape_string(trim($_POST['short_bio']));

    // Fetch current profile_pic
    $current = $conn->query("SELECT profile_pic FROM writers WHERE id = $id")->fetch_assoc();
    $profile_pic = $current['profile_pic'] ?? '';

    // Handle Profile Pic Upload
    if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] == 0) {
        require_once '../lib/image_seo.php';
        $up = media_store_upload($conn, $_FILES['profile_pic'], __DIR__ . '/../images', 'images');
        if ($up['ok']) $profile_pic = $up['path'];
    }
    
    $query = "UPDATE writers SET 
              name='$name', slug='$slug', bio='$bio', short_bio='$short_bio', 
              meta_title='$meta_title', meta_description='$meta_description',
              profile_pic='$profile_pic' 
              WHERE id=$id";
              
    if ($conn->query($query) === TRUE) {
        clear_page_cache();
        $msg = "<div class='alert alert-success'><i class='fa-solid fa-check-circle'></i> Writer updated successfully!</div>";
    } else {
        if ($conn->errno == 1062) {
            $msg = "<div class='alert alert-danger'>Error: A writer with this name or slug already exists.</div>";
        } else {
            error_log("Database error: " . $conn->error);
            $msg = "<div class='alert alert-danger'>Something went wrong. Please try again.</div>";
        }
    }
}

// Fetch current writer data
$result = $conn->query("SELECT * FROM writers WHERE id = $id");
if (!$result || $result->num_rows == 0) {
    header("Location: writers.php");
    exit;
}
$row = $result->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta name="robots" content="noindex, nofollow">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Writer - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.css" rel="stylesheet">
    <style>
        .form-label { font-weight: 600; font-size: 0.85rem; color: #c5cad4; margin-bottom: 8px;}
        .title-input { font-size: 1.2rem; font-weight: 600; padding: 12px 15px; }
        .btn-publish { background: #6CC832; color: white; border: none; font-weight: 600; padding: 10px 20px; border-radius:0; width: 100%; margin-bottom: 10px; transition: 0.3s;}
        .btn-publish:hover { background: #54a626; color: white;}
        
        /* Summernote UI Correction for Dark Theme */
        .note-editor.note-frame { border: 1px solid rgba(255,255,255,0.12) !important; background: #111310 !important; }
        .note-editor .note-toolbar { background-color: #222522 !important; border-bottom: 1px solid rgba(255,255,255,0.12) !important; }
        .note-editor .note-editing-area .note-editable { background-color: #1a1d1a !important; color: #ffffff !important; min-height: 250px; }
        .note-editor .note-statusbar { background-color: #222522 !important; border-top: 1px solid rgba(255,255,255,0.12) !important; }
        .note-btn { background-color: transparent !important; color: #eef0f4 !important; border: 1px solid transparent !important; }
        .note-btn:hover, .note-btn.active { background-color: rgba(255,255,255,0.1) !important; color: #6CC832 !important; }
        .note-dropdown-menu { background-color: #1e221c !important; border: 1px solid rgba(255,255,255,0.15) !important; color: #ffffff !important; box-shadow: 0 8px 24px rgba(0,0,0,0.6) !important; }
        .note-dropdown-menu a.note-dropdown-item { color: #ffffff !important; }
        .note-dropdown-menu a.note-dropdown-item:hover { background-color: rgba(108,200,50,0.15) !important; color: #fff !important; }
        .note-color-palette { background-color: #1e221c !important; }
        .note-color-btn { border: 1px solid rgba(255,255,255,0.1) !important; }
        
        /* Modal Dialogs z-index and input text */
        .note-modal { z-index: 1060 !important; }
        .note-modal-backdrop { z-index: 1050 !important; background-color: rgba(0,0,0,0.6) !important; }
        .note-modal-content { background-color: #161815 !important; border: 1px solid rgba(108,200,50,0.3) !important; color: #ffffff !important; }
        .note-modal-header { border-bottom: 1px solid rgba(255,255,255,0.1) !important; color: #ffffff !important; }
        .note-modal-footer { border-top: 1px solid rgba(255,255,255,0.1) !important; }
        .note-modal-title { color: #ffffff !important; }
        .note-modal .form-control { background: rgba(255,255,255,0.05) !important; border: 1px solid rgba(255,255,255,0.1) !important; color: #ffffff !important; }
        .note-modal .form-label { color: #c5cad4 !important; }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <form action="" method="POST" enctype="multipart/form-data">
            <?php echo csrf_field(); ?>
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3 class="fw-bold mb-0 text-white">Edit Writer</h3>
                <a href="writers.php" class="btn btn-outline-secondary btn-sm"><i class="fa-solid fa-arrow-left me-2"></i> Back to Writers</a>
            </div>
            
            <?php echo $msg; ?>

            <div class="row">
                <div class="col-lg-8">
                    <div class="mb-4">
                        <label class="form-label">Writer Name *</label>
                        <input type="text" name="name" class="form-control title-input" value="<?php echo htmlspecialchars($row['name']); ?>" required>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Slug (URL) *</label>
                        <input type="text" name="slug" class="form-control" value="<?php echo htmlspecialchars($row['slug']); ?>" required>
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label">Biography / About</label>
                        <textarea name="bio" id="summernote" class="form-control"><?php echo htmlspecialchars($row['bio'] ?? ''); ?></textarea>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Short Biography</label>
                        <textarea name="short_bio" class="form-control" rows="3" placeholder="A brief 1-2 sentence description of the writer..."><?php echo htmlspecialchars($row['short_bio'] ?? ''); ?></textarea>
                    </div>

                    <!-- SEO Section -->
                    <div class="card mb-4">
                        <div class="card-header"><i class="fa-solid fa-search me-2"></i> SEO Meta Information</div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-label">Meta Title</label>
                                <input type="text" name="meta_title" class="form-control" value="<?php echo htmlspecialchars($row['meta_title'] ?? ''); ?>" placeholder="SEO Title (defaults to writer name if empty)">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Meta Description</label>
                                <textarea name="meta_description" class="form-control" rows="3" placeholder="SEO Description (snippets shown in Google search)"><?php echo htmlspecialchars($row['meta_description'] ?? ''); ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card mb-4">
                        <div class="card-header"><i class="fa-solid fa-paper-plane me-2"></i> Actions</div>
                        <div class="card-body">
                            <button type="submit" class="btn-publish"><i class="fa-solid fa-arrows-rotate me-2"></i> Save Changes</button>
                        </div>
                    </div>

                    <div class="card mb-4">
                        <div class="card-header"><i class="fa-solid fa-image me-2"></i> Profile Picture</div>
                        <div class="card-body">
                            <?php if(!empty($row['profile_pic'])): ?>
                                <img src="../<?php echo htmlspecialchars($row['profile_pic']); ?>" class="img-thumbnail w-100 mb-2" style="border-color: rgba(255,255,255,0.12); background: rgba(10,12,8,0.6);">
                                <a href="edit_writer.php?id=<?php echo $id; ?>&remove_profile_pic=1" class="btn btn-sm btn-danger w-100 mb-2" onclick="return confirm('Remove profile picture?');"><i class="fa-solid fa-trash me-1"></i> Remove Pic</a>
                            <?php endif; ?>
                            <input type="file" name="profile_pic" class="form-control" accept="image/*">
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.js"></script>
    <script>
        $(document).ready(function() {
            var summernoteConfig = {
                tabsize: 2, height: 250,
                dialogsInBody: true,
                dialogsFade: true,
                toolbar: [
                  ['style', ['style']],
                  ['font', ['bold', 'underline', 'clear']],
                  ['color', ['color']],
                  ['para', ['ul', 'ol', 'paragraph']],
                  ['insert', ['link', 'picture']],
                  ['view', ['fullscreen', 'codeview']]
                ]
            };
            $('#summernote').summernote($.extend({placeholder: 'Write writer biography here...'}, summernoteConfig));
        });
    </script>
</body>
</html>
