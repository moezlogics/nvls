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

$settings_query = $conn->query("SELECT about_page_title, about_page_subtitle, about_meta_title, about_meta_description, about_page_content,
    contact_page_title, contact_page_subtitle, contact_meta_title, contact_meta_description, contact_page_content
    FROM settings WHERE id = 1");
$site_settings = $settings_query ? $settings_query->fetch_assoc() : [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $activeTab = ($_POST['save_tab'] ?? 'about') === 'contact' ? 'contact' : 'about';

    if ($activeTab === 'about') {
        $fields = ['about_page_title', 'about_page_subtitle', 'about_meta_title', 'about_meta_description', 'about_page_content'];
    } else {
        $fields = ['contact_page_title', 'contact_page_subtitle', 'contact_meta_title', 'contact_meta_description', 'contact_page_content'];
    }

    $sets = [];
    $vals = [];
    foreach ($fields as $f) {
        $sets[] = "$f = ?";
        $vals[] = $_POST[$f] ?? '';
    }
    $vals[] = 1;

    $sql = "UPDATE settings SET " . implode(', ', $sets) . " WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $types = str_repeat('s', count($fields)) . 'i';
    $stmt->bind_param($types, ...$vals);

    if ($stmt->execute()) {
        clear_page_cache();
        $msg = "<div class='alert alert-success'><i class='fa-solid fa-check-circle'></i> Page content updated successfully!</div>";
        foreach ($fields as $f) {
            $site_settings[$f] = $_POST[$f] ?? '';
        }
    } else {
        error_log("Database error: " . $conn->error);
        $msg = "<div class='alert alert-danger'><i class='fa-solid fa-circle-xmark me-2'></i> Something went wrong. Please try again.</div>";
    }
    $stmt->close();
}

$activeTab = ($_GET['tab'] ?? 'about') === 'contact' ? 'contact' : 'about';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta name="robots" content="noindex, nofollow">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About & Contact Pages - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.css" rel="stylesheet">
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <h2 class="fw-bold mb-2">About & Contact Pages</h2>
        <p class="text-muted mb-4">Edit page content, headings, and SEO meta tags. Contact info and social links come from <a href="settings.php">Platform Settings</a>.</p>

        <?php if (isset($msg)) echo $msg; ?>

        <ul class="nav nav-tabs mb-4">
            <li class="nav-item">
                <a class="nav-link <?php echo $activeTab === 'about' ? 'active' : ''; ?>" href="?tab=about"><i class="fa-solid fa-circle-info me-1"></i> About Us</a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $activeTab === 'contact' ? 'active' : ''; ?>" href="?tab=contact"><i class="fa-solid fa-envelope me-1"></i> Contact Us</a>
            </li>
        </ul>

        <form action="?tab=<?php echo htmlspecialchars($activeTab); ?>" method="POST">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="save_tab" value="<?php echo htmlspecialchars($activeTab); ?>">

            <?php if ($activeTab === 'about'): ?>
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header">About Us — SEO</div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Meta Title</label>
                            <input type="text" name="about_meta_title" class="form-control" value="<?php echo htmlspecialchars($site_settings['about_meta_title'] ?? ''); ?>" placeholder="About Us - Your Site Name">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Page Heading (H1)</label>
                            <input type="text" name="about_page_title" class="form-control" value="<?php echo htmlspecialchars($site_settings['about_page_title'] ?? ''); ?>" placeholder="About Us">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Meta Description</label>
                            <textarea name="about_meta_description" class="form-control" rows="2" placeholder="Short description for search engines"><?php echo htmlspecialchars($site_settings['about_meta_description'] ?? ''); ?></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Subtitle (below heading)</label>
                            <input type="text" name="about_page_subtitle" class="form-control" value="<?php echo htmlspecialchars($site_settings['about_page_subtitle'] ?? ''); ?>" placeholder="Your ultimate source for reading premium novels...">
                        </div>
                    </div>
                </div>
            </div>
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header">About Us — Page Content</div>
                <div class="card-body">
                    <textarea name="about_page_content" class="summernote"><?php echo htmlspecialchars($site_settings['about_page_content'] ?? ''); ?></textarea>
                </div>
            </div>
            <?php else: ?>
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header">Contact Us — SEO</div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Meta Title</label>
                            <input type="text" name="contact_meta_title" class="form-control" value="<?php echo htmlspecialchars($site_settings['contact_meta_title'] ?? ''); ?>" placeholder="Contact Us - Your Site Name">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Page Heading (H1)</label>
                            <input type="text" name="contact_page_title" class="form-control" value="<?php echo htmlspecialchars($site_settings['contact_page_title'] ?? ''); ?>" placeholder="Contact Us">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Meta Description</label>
                            <textarea name="contact_meta_description" class="form-control" rows="2" placeholder="Short description for search engines"><?php echo htmlspecialchars($site_settings['contact_meta_description'] ?? ''); ?></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Subtitle (below heading)</label>
                            <input type="text" name="contact_page_subtitle" class="form-control" value="<?php echo htmlspecialchars($site_settings['contact_page_subtitle'] ?? ''); ?>" placeholder="Have a question? We'd love to hear from you.">
                        </div>
                    </div>
                </div>
            </div>
            <div class="alert alert-info">
                <i class="fa-solid fa-circle-info me-1"></i>
                Email, phone, WhatsApp, and social links are managed in <a href="settings.php" class="alert-link">Platform Settings</a> and appear automatically on the contact page.
            </div>
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header">Contact Us — Content Below Form</div>
                <div class="card-body">
                    <textarea name="contact_page_content" class="summernote"><?php echo htmlspecialchars($site_settings['contact_page_content'] ?? ''); ?></textarea>
                </div>
            </div>
            <?php endif; ?>

            <div class="text-end">
                <button type="submit" class="btn btn-primary px-5 py-2 fw-bold"><i class="fa-solid fa-save me-2"></i> Save Changes</button>
            </div>
        </form>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.js"></script>
    <script>
        $(document).ready(function() {
            $('.summernote').summernote({
                height: 280,
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
