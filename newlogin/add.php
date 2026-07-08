<?php
include '../db.php';
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: index.php");
    exit;
}
require_once '../lib/image_seo.php';

// Sugggestion helpers removed

$msg = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    csrf_verify();
    $title = $conn->real_escape_string($_POST['title']);
    
    // Clean paste for description
    $raw_description = $_POST['description'];
    $clean_description = preg_replace('/<(img|iframe|script)\b[^>]*>/i', '', $raw_description);
    $description = $conn->real_escape_string($clean_description);
    
    $categories = isset($_POST['categories']) ? $_POST['categories'] : [];
    $primary_category_id = !empty($categories) ? (int)$categories[0] : 0;
    $additional_categories = count($categories) > 1 ? $conn->real_escape_string(implode(',', array_slice($categories, 1))) : '';
    
    $status = $conn->real_escape_string($_POST['status']);
    
    $writer_id = !empty($_POST['writer_id']) ? (int)$_POST['writer_id'] : 'NULL';
    $read_online_url = $conn->real_escape_string(trim($_POST['read_online_url'] ?? ''));
    $download_url = $conn->real_escape_string(trim($_POST['download_url'] ?? ''));
    $file_size = $conn->real_escape_string(trim($_POST['file_size'] ?? ''));
    $file_type = $conn->real_escape_string(trim($_POST['file_type'] ?? ''));
    $pages = !empty($_POST['pages']) ? (int)$_POST['pages'] : 'NULL';
    
    // Create URL slug
    $slug = preg_replace('/[^a-z0-9\-]/', '', strtolower(str_replace(' ', '-', trim($title))));
    
    // Featured image upload — AI stores ALT + caption + description in the media table
    $image = '';
    if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] == 0) {
        $up = media_store_upload($conn, $_FILES['image_file'], __DIR__ . '/../images', 'images');
        if ($up['ok']) $image = $up['path'];
    }

    $meta_title = $conn->real_escape_string(trim($_POST['meta_title'] ?? ''));
    $meta_description = $conn->real_escape_string(trim($_POST['meta_description'] ?? ''));

    $query = "INSERT INTO posts (title, slug, category_id, additional_categories, writer_id, description, image, read_online_url, download_url, file_size, file_type, pages, status, meta_title, meta_description) 
              VALUES ('$title', '$slug', $primary_category_id, '$additional_categories', $writer_id, '$description', '$image', '$read_online_url', '$download_url', '$file_size', '$file_type', $pages, '$status', '$meta_title', '$meta_description')";
    
    if ($conn->query($query) === TRUE) {
        clear_page_cache();
        $msg = "<div class='alert alert-success'><i class='fa-solid fa-check-circle'></i> Post created successfully!</div>";
    } else {
        if($conn->errno == 1062) {
             $msg = "<div class='alert alert-danger'>Error: A post with this title already exists.</div>";
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
    <title>Add Post - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.css" rel="stylesheet">
    <style>
        .form-label { font-weight: 600; font-size: 0.85rem; color: #c5cad4; margin-bottom: 8px;}
        .title-input { font-size: 1.2rem; font-weight: 600; padding: 12px 15px; }
        
        .btn-publish { background: #6CC832; color: white; border: none; font-weight: 600; padding: 10px 20px; border-radius:0; width: 100%; margin-bottom: 10px; transition: 0.3s;}
        .btn-publish:hover { background: #54a626; color: white;}
        .btn-draft { background: rgba(255,255,255,0.12); color: #ffffff; border: 1px solid #495057; font-weight: 600; padding: 10px 20px; border-radius:0; width: 100%; transition: 0.3s;}
        .btn-draft:hover { background: #343a40; color: white;}
        /* Select2 Dark Theme Overrides */
        .select2-container--classic .select2-selection--multiple { background-color: rgba(10,12,8,0.6) !important; border: 1px solid rgba(255,255,255,0.12) !important; }
        .select2-container--classic .select2-selection--multiple .select2-selection__choice { background-color: rgba(20,24,16,0.55) !important; border: 1px solid rgba(255,255,255,0.12) !important; color: #ffffff !important; }
        .select2-container--classic .select2-selection--multiple .select2-selection__choice__remove { color: #c5cad4 !important; }
        .select2-dropdown { background-color: rgba(20,24,16,0.55) !important; border: 1px solid rgba(255,255,255,0.12) !important; }
        .select2-container--classic .select2-results__option--highlighted.select2-results__option--selectable { background-color: #6CC832 !important; color: #fff !important; }
        .select2-results__option { color: #ffffff !important; }
        .select2-search__field { background-color: rgba(10,12,8,0.6) !important; color: #ffffff !important; border: none !important; }

        /* Summernote UI Correction for Dark Theme */
        .note-editor.note-frame { border: 1px solid rgba(255,255,255,0.12) !important; background: #111310 !important; }
        .note-editor .note-toolbar { background-color: #222522 !important; border-bottom: 1px solid rgba(255,255,255,0.12) !important; }
        .note-editor .note-editing-area .note-editable { background-color: #1a1d1a !important; color: #ffffff !important; min-height: 400px; }
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
                <h3 class="fw-bold mb-0 text-white">Add New Post</h3>
            </div>
            
            <?php echo $msg; ?>

            <div class="row">
                <div class="col-lg-8">
                    <div class="mb-4">
                        <label class="form-label">Title *</label>
                        <input type="text" name="title" class="form-control title-input" placeholder="Enter post title..." required>
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label">Content</label>
                        <textarea name="description" id="summernote" class="form-control"></textarea>
                    </div>

                    <!-- SEO Section -->
                    <div class="card mb-4">
                        <div class="card-header"><i class="fa-solid fa-search me-2"></i> SEO Meta Information</div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-label">Meta Title</label>
                                <input type="text" name="meta_title" class="form-control" placeholder="SEO Title (defaults to post title if empty)">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Meta Description</label>
                                <textarea name="meta_description" class="form-control" rows="3" placeholder="SEO Description (snippets shown in Google search)"></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    
                    <div class="card mb-4">
                        <div class="card-header"><i class="fa-solid fa-paper-plane me-2"></i> Status</div>
                        <div class="card-body">
                            <button type="submit" name="status" value="active" class="btn-publish">Published</button>
                            <button type="submit" name="status" value="draft" class="btn-draft">Draft</button>
                        </div>
                    </div>

                    <div class="card mb-4">
                        <div class="card-header"><i class="fa-solid fa-image me-2"></i> Featured Image</div>
                        <div class="card-body">
                            <input type="file" name="image_file" class="form-control" accept="image/*">
                        </div>
                    </div>

                    <div class="card mb-4">
                        <div class="card-header"><i class="fa-solid fa-book-open me-2"></i> Novel Details</div>
                        <div class="card-body">
                            <label class="form-label mt-2">Writer *</label>
                            <select name="writer_id" id="writerSelect" class="form-select mb-2" required>
                                <option value="">Select Writer</option>
                                <?php
                                $writers = $conn->query("SELECT * FROM writers ORDER BY name ASC");
                                while($w = $writers->fetch_assoc()){
                                    echo "<option value='".$w['id']."'>".htmlspecialchars($w['name'])."</option>";
                                }
                                ?>
                            </select>
                            
                            <div class="input-group input-group-sm mb-3">
                                <input type="text" id="newWriterName" class="form-control" placeholder="New writer name">
                                <button type="button" class="btn btn-outline-secondary" id="addWriterBtn">Add</button>
                            </div>

                            <label class="form-label mt-2">Read Online Link</label>
                            <input type="url" name="read_online_url" class="form-control mb-3" placeholder="https://...">

                            <label class="form-label mt-2">Download Link</label>
                            <input type="url" name="download_url" class="form-control mb-3" placeholder="https://...">

                            <label class="form-label mt-2">File Size</label>
                            <input type="text" name="file_size" class="form-control mb-3" placeholder="e.g. 2.4 MB">

                            <label class="form-label mt-2">File Type</label>
                            <input type="text" name="file_type" class="form-control mb-3" placeholder="e.g. PDF">

                            <label class="form-label mt-2">Pages</label>
                            <input type="number" name="pages" class="form-control mb-3" placeholder="e.g. 320" min="1">
                        </div>
                    </div>

                    <div class="card mb-4">
                        <div class="card-header"><i class="fa-solid fa-circle-info me-2"></i> Post Settings</div>
                        <div class="card-body">
                            <label class="form-label mt-2">Categories <small class="text-muted">(Select 1 or more)</small></label>
                            <select name="categories[]" id="categorySelect" class="form-select mb-2" multiple="multiple" required>
                                <?php
                                $cats = $conn->query("SELECT * FROM categories ORDER BY name ASC");
                                while($c = $cats->fetch_assoc()){
                                    echo "<option value='".$c['id']."'>".htmlspecialchars($c['name'])."</option>";
                                }
                                ?>
                            </select>
                            
                            <div class="input-group input-group-sm mb-3">
                                <input type="text" id="newCategoryName" class="form-control" placeholder="New category name">
                                <button type="button" class="btn btn-outline-secondary" id="addCategoryBtn">Add</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.js"></script>
    <!-- Select2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        $(document).ready(function() {
            // Initialize Select2 for multiple categories
            $('#categorySelect').select2({
                placeholder: 'Select Categories...',
                width: '100%',
                theme: 'classic'
            });

            // Initialize Select2 for writer
            $('#writerSelect').select2({
                placeholder: 'Select Writer...',
                width: '100%',
                theme: 'classic'
            });
            
            // Inline add category
            $('#addCategoryBtn').click(function() {
                var catName = $('#newCategoryName').val().trim();
                if(catName === '') {
                    alert('Please enter a category name');
                    return;
                }
                
                $.ajax({
                    url: 'ajax_add_category.php',
                    type: 'POST',
                    data: { name: catName },
                    dataType: 'json',
                    success: function(response) {
                        if(response.success) {
                            var newOption = new Option(response.name, response.id, true, true);
                            $('#categorySelect').append(newOption).trigger('change');
                            $('#newCategoryName').val('');
                        } else {
                            alert('Error: ' + response.error);
                        }
                    },
                    error: function(xhr, status, error) {
                        alert('Network error while adding category. Details: ' + (xhr.responseText || error));
                        console.error(xhr);
                    }
                });
            });

            // Inline add writer
            $('#addWriterBtn').click(function() {
                var writerName = $('#newWriterName').val().trim();
                if(writerName === '') {
                    alert('Please enter a writer name');
                    return;
                }
                
                $.ajax({
                    url: 'ajax_add_writer.php',
                    type: 'POST',
                    data: { name: writerName },
                    dataType: 'json',
                    success: function(response) {
                        if(response.success) {
                            var newOption = new Option(response.name, response.id, true, true);
                            $('#writerSelect').append(newOption).trigger('change');
                            $('#newWriterName').val('');
                        } else {
                            alert('Error: ' + response.error);
                        }
                    },
                    error: function(xhr, status, error) {
                        alert('Network error while adding writer. Details: ' + (xhr.responseText || error));
                        console.error(xhr);
                    }
                });
            });

            var summernoteConfig = {
                tabsize: 2, height: 300,
                dialogsInBody: true,
                dialogsFade: true,
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
            };
            $('#summernote').summernote($.extend({placeholder: 'Write your full post description here...'}, summernoteConfig));
        });
    </script>
</body>
</html>
