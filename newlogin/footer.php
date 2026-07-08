<?php
include '../db.php';
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: index.php");
    exit;
}
if ($_SESSION['admin_role'] != 'main_admin') {
    header("Location: dashboard.php");
    exit;
}

// Fetch current settings
$settings_query = $conn->query("SELECT footer_col1, footer_col2, footer_col3 FROM settings WHERE id = 1");
$site_settings = $settings_query->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    csrf_verify();
    $col1 = $conn->real_escape_string($_POST['footer_col1']);
    $col2 = $conn->real_escape_string($_POST['footer_col2']);
    $col3 = $conn->real_escape_string($_POST['footer_col3']);

    $query = "UPDATE settings SET footer_col1='$col1', footer_col2='$col2', footer_col3='$col3' WHERE id=1";
    
    if ($conn->query($query) === TRUE) {
        $msg = "<div class='alert alert-success'><i class='fa-solid fa-check-circle'></i> Footer content updated successfully!</div>";
        $site_settings['footer_col1'] = $_POST['footer_col1'];
        $site_settings['footer_col2'] = $_POST['footer_col2'];
        $site_settings['footer_col3'] = $_POST['footer_col3'];
    } else {
        error_log("Database error: " . $conn->error); $msg = "<div class='alert alert-danger'><i class='fa-solid fa-circle-xmark me-2'></i> Something went wrong. Please try again.</div>";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta name="robots" content="noindex, nofollow">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Footer - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Summernote Editor -->
    <link href="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.css" rel="stylesheet">
    
    <style>
        body { background-color: #f4f6f9; font-family: 'Inter', sans-serif; }
        .note-editor .note-editing-area .note-editable { background-color: #fff; }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <h2 class="fw-bold mb-4">Manage Footer</h2>
        <p class="text-muted mb-4">Customize the 3 columns of your website's footer. You can add text, links, social icons, or images.</p>

        <?php if(isset($msg)) echo $msg; ?>

        <form action="" method="POST">
                            <?php echo csrf_field(); ?>
            <div class="row">
                <!-- Column 1 -->
                <div class="col-md-4 mb-4">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-header bg-white fw-bold">Column 1 (Left)</div>
                        <div class="card-body">
                            <textarea name="footer_col1" class="summernote"><?php echo htmlspecialchars($site_settings['footer_col1'] ?? ''); ?></textarea>
                        </div>
                    </div>
                </div>
                
                <!-- Column 2 -->
                <div class="col-md-4 mb-4">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-header bg-white fw-bold">Column 2 (Center)</div>
                        <div class="card-body">
                            <textarea name="footer_col2" class="summernote"><?php echo htmlspecialchars($site_settings['footer_col2'] ?? ''); ?></textarea>
                        </div>
                    </div>
                </div>
                
                <!-- Column 3 -->
                <div class="col-md-4 mb-4">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-header bg-white fw-bold">Column 3 (Right)</div>
                        <div class="card-body">
                            <textarea name="footer_col3" class="summernote"><?php echo htmlspecialchars($site_settings['footer_col3'] ?? ''); ?></textarea>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="mt-2 text-end">
                <button type="submit" class="btn btn-primary px-5 py-2 fw-bold"><i class="fa-solid fa-save me-2"></i> Save Footer Content</button>
            </div>
        </form>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.js"></script>
    <script>
        $(document).ready(function() {
            $('.summernote').summernote({
                height: 250,
                toolbar: [
                    ['style', ['style']],
                    ['font', ['bold', 'underline', 'clear']],
                    ['color', ['color']],
                    ['para', ['ul', 'ol', 'paragraph']],
                    ['table', ['table']],
                    ['insert', ['link', 'picture', 'video']],
                    ['view', ['fullscreen', 'codeview', 'help']]
                ],
                callbacks: {
                    onImageUpload: function(files){
                        var editor = this;
                        var fd = new FormData(); fd.append('file', files[0]);
                        fetch('upload_image.php', {method:'POST', body:fd})
                          .then(function(r){return r.json();}).then(function(d){
                             if(d.url){ $(editor).summernote('insertImage', d.url, function($img){ $img.attr('alt', d.alt||''); }); }
                             else { alert('Image upload failed: ' + (d.error||'error')); }
                          }).catch(function(){ alert('Image upload error.'); });
                    }
                }
            });
        });
    </script>
</body>
</html>
