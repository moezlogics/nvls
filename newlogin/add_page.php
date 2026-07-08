<?php
include '../db.php';
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: index.php");
    exit;
}

$msg = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    csrf_verify();
    $title = $conn->real_escape_string($_POST['title']);
    
    // Clean paste for content
    $raw_content = $_POST['content'];
    $clean_content = preg_replace('/<(img|iframe|script)\b[^>]*>/i', '', $raw_content);
    $content = $conn->real_escape_string($clean_content);
    
    $status = $conn->real_escape_string($_POST['status']);
    $meta_title = $conn->real_escape_string(trim($_POST['meta_title']));
    $meta_description = $conn->real_escape_string(trim($_POST['meta_description']));
    
    // Custom or auto slug
    if(isset($_POST['slug']) && !empty(trim($_POST['slug']))) {
        $slug = preg_replace('/[^a-z0-9\-]/', '', strtolower(str_replace(' ', '-', trim($_POST['slug']))));
    } else {
        $slug = preg_replace('/[^a-z0-9\-]/', '', strtolower(str_replace(' ', '-', trim($title))));
    }

    $query = "INSERT INTO pages (title, slug, content, status, meta_title, meta_description) VALUES ('$title', '$slug', '$content', '$status', '$meta_title', '$meta_description')";
    
    if ($conn->query($query) === TRUE) {
        $msg = "<div class='alert alert-success'><i class='fa-solid fa-check-circle'></i> Page saved successfully!</div>";
    } else {
        // Handle duplicate slug
        if($conn->errno == 1062) {
             $msg = "<div class='alert alert-danger'>Error: A page with this URL slug already exists. Please choose a different title or slug.</div>";
        } else {
             error_log("Database error: " . $conn->error); $msg = "<div class='alert alert-danger'><i class='fa-solid fa-circle-xmark me-2'></i> Something went wrong. Please try again.</div>";
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
    <title>Add Page - Admin</title>
    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Summernote Rich Text Editor CSS -->
    <link href="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.css" rel="stylesheet">
    
    <style>
        .form-label { font-weight: 600; font-size: 0.85rem; color: #c5cad4; margin-bottom: 8px;}
        .title-input { font-size: 1.2rem; font-weight: 600; padding: 12px 15px; }
        
        .btn-publish { background: #6CC832; color: white; border: none; font-weight: 600; padding: 10px 20px; border-radius:0; width: 100%; margin-bottom: 10px; transition: 0.3s;}
        .btn-publish:hover { background: #54a626; color: white;}
        .btn-draft { background: rgba(255,255,255,0.12); color: #ffffff; border: 1px solid #495057; font-weight: 600; padding: 10px 20px; border-radius:0; width: 100%; transition: 0.3s;}
        .btn-draft:hover { background: #343a40; color: white;}
        
        /* Summernote Dark Theme Overrides */
        .note-editor.note-frame { border: 1px solid rgba(255,255,255,0.12); border-radius:0; }
        .note-editor .note-toolbar { background-color: rgba(255,255,255,0.05); border-bottom: 1px solid rgba(255,255,255,0.12); }
        .note-editor .note-editing-area .note-editable { background-color: rgba(10,12,8,0.6); color: #ffffff; min-height: 400px; }
        .note-editor .note-statusbar { background-color: rgba(255,255,255,0.05); border-top: 1px solid rgba(255,255,255,0.12); }
        .note-btn { background-color: transparent !important; color: #c5cad4 !important; border: 1px solid transparent !important; }
        .note-btn:hover { background-color: rgba(255,255,255,0.12) !important; color: #fff !important; }
        .note-dropdown-menu { background-color: rgba(20,24,16,0.55); border: 1px solid rgba(255,255,255,0.12); }
        .note-dropdown-item { color: #ffffff; }
        .note-dropdown-item:hover { background-color: rgba(255,255,255,0.12); }
    </style>
</head>
<body>
    
    <?php include 'sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <form action="" method="POST">
                            <?php echo csrf_field(); ?>
            
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3 class="fw-bold mb-0 text-white">Add New Page</h3>
            </div>
            
            <?php echo $msg; ?>

            <div class="row">
                <!-- Left Column -->
                <div class="col-lg-8">
                    <div class="mb-4">
                        <label class="form-label">Title *</label>
                        <input type="text" name="title" class="form-control title-input" placeholder="Enter post title..." required>
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label">Content</label>
                        <textarea name="content" id="summernote" class="form-control"></textarea>
                    </div>

                    <!-- SEO Section -->
                    <div class="card mb-4">
                        <div class="card-header"><i class="fa-solid fa-search me-2"></i> SEO Meta Information</div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-label">Meta Title</label>
                                <input type="text" name="meta_title" class="form-control" placeholder="SEO Title (defaults to page title if empty)">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Meta Description</label>
                                <textarea name="meta_description" class="form-control" rows="3" placeholder="SEO Description (snippets shown in Google search)"></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column -->
                <div class="col-lg-4">
                    
                    <!-- Publish Card -->
                    <div class="card mb-4">
                        <div class="card-header"><i class="fa-solid fa-paper-plane me-2"></i> Status</div>
                        <div class="card-body">
                            <button type="submit" name="status" value="publish" class="btn-publish">Published</button>
                            <button type="submit" name="status" value="draft" class="btn-draft">Draft</button>
                        </div>
                    </div>

                    <!-- Page Attributes -->
                    <div class="card mb-4">
                        <div class="card-header"><i class="fa-solid fa-link me-2"></i> Handle (URL Slug)</div>
                        <div class="card-body">
                            <input type="text" name="slug" class="form-control" placeholder="post-url-slug">
                            <small class="text-muted mt-2 d-block">If left blank, auto-generated.</small>
                        </div>
                    </div>

                </div>
            </div>
            
        </form>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Summernote JS -->
    <script src="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.js"></script>
    
    <script>
        $(document).ready(function() {
            $('#summernote').summernote({
                placeholder: 'Write your page content here...',
                tabsize: 2,
                height: 400,
                toolbar: [
                  ['style', ['style']],
                  ['font', ['bold', 'underline', 'clear']],
                  ['color', ['color']],
                  ['para', ['ul', 'ol', 'paragraph']],
                  ['table', ['table']],
                  ['insert', ['link', 'picture']],
                  ['view', ['fullscreen', 'codeview', 'help']]
                ],
                callbacks: {
                    onPaste: function (e) {
                        var clipboardData = (e.originalEvent || e).clipboardData || window.clipboardData;
                        var html = clipboardData.getData('text/html');
                        var text = clipboardData.getData('text/plain');
                        e.preventDefault();
                        if (html) {
                            var div = document.createElement('div');
                            div.innerHTML = html;
                            var badTags = div.querySelectorAll('img, iframe, script, ins, style, link, meta, noscript, svg, aside');
                            badTags.forEach(el => el.remove());
                            var allElements = div.querySelectorAll('*');
                            allElements.forEach(el => {
                                for (var i = el.attributes.length - 1; i >= 0; i--) {
                                    var attrName = el.attributes[i].name;
                                    if (attrName !== 'href') { el.removeAttribute(attrName); }
                                }
                            });
                            document.execCommand('insertHTML', false, div.innerHTML);
                        } else {
                            document.execCommand('insertText', false, text);
                        }
                    },
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
