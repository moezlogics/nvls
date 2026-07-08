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
require_once '../lib/image_seo.php';
require_once '../lib/coming_soon.php';

$msg = '';

if (isset($_GET['regen_community_preview']) && $_SESSION['admin_role'] === 'main_admin') {
    $key = coming_soon_generate_key();
    $conn->query("UPDATE settings SET community_preview_key='" . $conn->real_escape_string($key) . "' WHERE id=1");
    clear_page_cache();
    header('Location: settings.php#coming-soon');
    exit;
}
if (isset($_GET['regen_shop_preview']) && $_SESSION['admin_role'] === 'main_admin') {
    $key = coming_soon_generate_key();
    $conn->query("UPDATE settings SET shop_preview_key='" . $conn->real_escape_string($key) . "' WHERE id=1");
    clear_page_cache();
    header('Location: settings.php#coming-soon');
    exit;
}

coming_soon_ensure_preview_keys($conn, $site_settings);

if (isset($_GET['remove_logo'])) {
    $conn->query("UPDATE settings SET site_logo='' WHERE id=1");
    header("Location: settings.php");
    exit;
}

if (isset($_GET['remove_favicon'])) {
    $conn->query("UPDATE settings SET site_favicon='' WHERE id=1");
    header("Location: settings.php");
    exit;
}

if (isset($_GET['remove_homepage_featured_image'])) {
    $conn->query("UPDATE settings SET homepage_featured_image='' WHERE id=1");
    header("Location: settings.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    csrf_verify();
    $site_name = $conn->real_escape_string($_POST['site_name']);
    $tagline = $conn->real_escape_string($_POST['tagline']);
    $contact_email = $conn->real_escape_string($_POST['contact_email']);
    $contact_phone = $conn->real_escape_string($_POST['contact_phone']);
    $contact_whatsapp = $conn->real_escape_string($_POST['contact_whatsapp']);
    
    $facebook_url = $conn->real_escape_string($_POST['facebook_url']);
    $instagram_url = $conn->real_escape_string($_POST['instagram_url']);
    $tiktok_url = $conn->real_escape_string($_POST['tiktok_url']);
    $youtube_url = $conn->real_escape_string($_POST['youtube_url']);
    
    $seo_description = ''; // Removed default meta description
    $seo_keywords = $conn->real_escape_string($_POST['seo_keywords']);
    $homepage_title = $conn->real_escape_string($_POST['homepage_title']);
    $homepage_description = $conn->real_escape_string($_POST['homepage_description']);
    $permalink_structure = $conn->real_escape_string($_POST['permalink_structure']);
    
    $google_analytics = $conn->real_escape_string($_POST['google_analytics']);
    $search_console = $conn->real_escape_string($_POST['search_console']);
    $larapush_code = $conn->real_escape_string($_POST['larapush_code']);
    $logo_width_desktop = $conn->real_escape_string($_POST['logo_width_desktop']);
    $logo_width_mobile = $conn->real_escape_string($_POST['logo_width_mobile']);
    
    $maintenance_mode = isset($_POST['maintenance_mode']) ? 1 : 0;
    $community_coming_soon = isset($_POST['community_coming_soon']) ? 1 : 0;
    $shop_coming_soon = isset($_POST['shop_coming_soon']) ? 1 : 0;

    // Handle File Uploads (routed through the AI media handler for SEO metadata)
    $site_logo = $site_settings['site_logo'] ?? '';
    if (isset($_FILES['site_logo']) && $_FILES['site_logo']['error'] == 0) {
        $up = media_store_upload($conn, $_FILES['site_logo'], __DIR__ . '/../images', 'images');
        if ($up['ok']) $site_logo = $up['path'];
    }

    $site_favicon = $site_settings['site_favicon'] ?? '';
    if (isset($_FILES['site_favicon']) && $_FILES['site_favicon']['error'] == 0) {
        $up = media_store_upload($conn, $_FILES['site_favicon'], __DIR__ . '/../images', 'images');
        if ($up['ok']) $site_favicon = $up['path'];
    }

    $homepage_featured_image = $site_settings['homepage_featured_image'] ?? '';
    if (isset($_FILES['homepage_featured_image']) && $_FILES['homepage_featured_image']['error'] == 0) {
        $up = media_store_upload($conn, $_FILES['homepage_featured_image'], __DIR__ . '/../images', 'images');
        if ($up['ok']) $homepage_featured_image = $up['path'];
    }
    
    if (isset($_FILES['larapush_file']) && $_FILES['larapush_file']['error'] == 0) {
        $filename = $_FILES['larapush_file']['name'];
        if(pathinfo($filename, PATHINFO_EXTENSION) == 'js') {
            move_uploaded_file($_FILES['larapush_file']['tmp_name'], '../' . $filename);
        }
    }

    $query = "UPDATE settings SET 
        site_name='$site_name', tagline='$tagline', 
        contact_email='$contact_email', contact_phone='$contact_phone', contact_whatsapp='$contact_whatsapp',
        facebook_url='$facebook_url', instagram_url='$instagram_url', tiktok_url='$tiktok_url', youtube_url='$youtube_url',
        seo_description='$seo_description', seo_keywords='$seo_keywords', 
        homepage_title='$homepage_title', homepage_description='$homepage_description',
        permalink_structure='$permalink_structure', maintenance_mode=$maintenance_mode,
        community_coming_soon=$community_coming_soon, shop_coming_soon=$shop_coming_soon,
        google_analytics='$google_analytics', search_console='$search_console', larapush_code='$larapush_code',
        site_logo='$site_logo', site_favicon='$site_favicon', homepage_featured_image='$homepage_featured_image',
        logo_width_desktop='$logo_width_desktop', logo_width_mobile='$logo_width_mobile' 
        WHERE id=1";
    
    if ($conn->query($query) === TRUE) {
        $msg = "<div class='alert alert-success'><i class='fa-solid fa-check-circle'></i> Settings saved successfully!</div>";
        clear_page_cache();
        $settings_query = $conn->query("SELECT * FROM settings WHERE id = 1");
        $site_settings = $settings_query->fetch_assoc();
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
    <title>Platform Settings - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .settings-card { background: rgba(20,24,16,0.55); border-radius:0; border: 1px solid rgba(255,255,255,0.12); margin-bottom: 25px; padding: 25px;}
        .settings-card-title { font-size: 0.9rem; font-weight: 700; color: #6CC832; text-transform: uppercase; margin-bottom: 20px; display: flex; align-items: center; gap: 8px;}
        .form-label { font-weight: 600; font-size: 0.85rem; color: #c5cad4; text-transform: uppercase; margin-bottom: 8px;}
        .form-control { border-radius:0; border: 1px solid rgba(255,255,255,0.12); padding: 10px 15px; font-size: 0.95rem; background: rgba(10,12,8,0.6); color: #ffffff;}
        .form-control:focus { border-color: #6CC832; box-shadow: 0 0 0 0.25rem rgba(108,200,50, 0.25); background: rgba(10,12,8,0.6); color: #fff;}
        
        .upload-box { border: 2px dashed rgba(255,255,255,0.12); border-radius:0; padding: 20px; text-align: center; background: rgba(10,12,8,0.6); cursor: pointer; transition: 0.3s;}
        .upload-box:hover { border-color: #6CC832; background: rgba(255,255,255,0.05); }
        .preview-img { max-width: 150px; max-height: 80px; object-fit: contain; margin-bottom: 10px;}
        
        .btn-save { background: #6CC832; color: white; border: none; font-weight: 600; padding: 10px 25px; border-radius:0; transition: 0.3s;}
        .btn-save:hover { background: #54a626; transform: translateY(-2px); color: white;}
        
        .maintenance-toggle { background: rgba(10,12,8,0.6); border-radius:0; padding: 15px; display: flex; align-items: center; gap: 15px; border: 1px solid rgba(255,255,255,0.12);}
    </style>
</head>
<body>
    
    <?php include 'sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <form action="" method="POST" enctype="multipart/form-data">
                            <?php echo csrf_field(); ?>
            
            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h3 class="fw-bold mb-1 text-white"><i class="fa-solid fa-gear text-primary me-2"></i> Platform Settings</h3>
                    <p class="text-muted small mb-0">Configure global platform settings, SEO, and preferences.</p>
                </div>
                <button type="submit" class="btn-save"><i class="fa-solid fa-save me-2"></i> Save All</button>
            </div>
            
            <?php echo $msg; ?>

            <!-- Branding / Logo -->
            <div class="settings-card">
                <div class="settings-card-title"><i class="fa-solid fa-image"></i> Site Branding</div>
                <div class="row">
                    <div class="col-md-6 mb-4">
                        <label class="form-label">Site Logo</label>
                        <div class="upload-box" onclick="document.getElementById('site_logo').click();">
                            <?php if(!empty($site_settings['site_logo'])): ?>
                                <img src="../<?php echo $site_settings['site_logo']; ?>" class="preview-img">
                                <div class="text-primary small fw-bold mt-2">Click to Change Logo</div>
                            <?php else: ?>
                                <i class="fa-solid fa-cloud-arrow-up fs-2 text-muted mb-2"></i>
                                <div class="text-muted small">Click to upload logo</div>
                            <?php endif; ?>
                            <input type="file" name="site_logo" id="site_logo" class="d-none" accept="image/*">
                        </div>
                        <?php if(!empty($site_settings['site_logo'])): ?>
                            <a href="settings.php?remove_logo=1" class="btn btn-sm btn-outline-danger mt-2 w-100" onclick="return confirm('Are you sure you want to remove the logo?');"><i class="fa-solid fa-trash"></i> Remove Logo</a>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6 mb-4">
                        <label class="form-label">Site Favicon</label>
                        <div class="upload-box" onclick="document.getElementById('site_favicon').click();">
                            <?php if(!empty($site_settings['site_favicon'])): ?>
                                <img src="../<?php echo $site_settings['site_favicon']; ?>" class="preview-img" style="max-width: 50px;">
                                <div class="text-primary small fw-bold mt-2">Click to Change Favicon</div>
                            <?php else: ?>
                                <i class="fa-solid fa-cloud-arrow-up fs-2 text-muted mb-2"></i>
                                <div class="text-muted small">Upload 512x512px icon</div>
                            <?php endif; ?>
                            <input type="file" name="site_favicon" id="site_favicon" class="d-none" accept="image/*">
                        </div>
                        <?php if(!empty($site_settings['site_favicon'])): ?>
                            <a href="settings.php?remove_favicon=1" class="btn btn-sm btn-outline-danger mt-2 w-100" onclick="return confirm('Are you sure you want to remove the favicon?');"><i class="fa-solid fa-trash"></i> Remove Favicon</a>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="row mt-3 border-top pt-3">
                    <div class="col-md-6 mb-3">
                        <label class="form-label" for="logo_width_desktop">Logo Width (Desktop)</label>
                        <input type="text" name="logo_width_desktop" id="logo_width_desktop" class="form-control" placeholder="e.g. 150px, 12rem, or auto" value="<?php echo htmlspecialchars($site_settings['logo_width_desktop'] ?? 'auto'); ?>">
                        <div class="form-text text-muted">Set width in px/rem/%, or keep it 'auto' to auto-size based on height.</div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label" for="logo_width_mobile">Logo Width (Mobile)</label>
                        <input type="text" name="logo_width_mobile" id="logo_width_mobile" class="form-control" placeholder="e.g. 100px, 8rem, or auto" value="<?php echo htmlspecialchars($site_settings['logo_width_mobile'] ?? 'auto'); ?>">
                        <div class="form-text text-muted">Set width in px/rem/%, or keep it 'auto' to auto-size based on height.</div>
                    </div>
                </div>
            </div>

            <!-- Platform Info -->
            <div class="settings-card">
                <div class="settings-card-title"><i class="fa-solid fa-circle-info"></i> Platform Info</div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Site Name</label>
                        <input type="text" name="site_name" class="form-control" value="<?php echo htmlspecialchars($site_settings['site_name'] ?? ''); ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Tagline</label>
                        <input type="text" name="tagline" class="form-control" value="<?php echo htmlspecialchars($site_settings['tagline'] ?? ''); ?>" placeholder="E.g., Explore Latest Articles & Stories">
                    </div>
                </div>
            </div>

            <!-- Contact Information -->
            <div class="settings-card">
                <div class="settings-card-title"><i class="fa-regular fa-envelope"></i> Contact Information</div>
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label"><i class="fa-regular fa-envelope me-1"></i> Contact Email</label>
                        <input type="email" name="contact_email" class="form-control" value="<?php echo htmlspecialchars($site_settings['contact_email'] ?? ''); ?>">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label"><i class="fa-solid fa-phone me-1"></i> Contact Phone</label>
                        <input type="text" name="contact_phone" class="form-control" value="<?php echo htmlspecialchars($site_settings['contact_phone'] ?? ''); ?>">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label"><i class="fa-brands fa-whatsapp me-1"></i> WhatsApp</label>
                        <input type="text" name="contact_whatsapp" class="form-control" value="<?php echo htmlspecialchars($site_settings['contact_whatsapp'] ?? ''); ?>">
                    </div>
                </div>
            </div>

            <!-- Social Media -->
            <div class="settings-card">
                <div class="settings-card-title"><i class="fa-solid fa-share-nodes"></i> Social Media</div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Facebook URL</label>
                        <input type="url" name="facebook_url" class="form-control" value="<?php echo htmlspecialchars($site_settings['facebook_url'] ?? ''); ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Instagram URL</label>
                        <input type="url" name="instagram_url" class="form-control" value="<?php echo htmlspecialchars($site_settings['instagram_url'] ?? ''); ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">TikTok URL</label>
                        <input type="url" name="tiktok_url" class="form-control" value="<?php echo htmlspecialchars($site_settings['tiktok_url'] ?? ''); ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">YouTube URL</label>
                        <input type="url" name="youtube_url" class="form-control" value="<?php echo htmlspecialchars($site_settings['youtube_url'] ?? ''); ?>">
                    </div>
                </div>
            </div>

            <!-- Homepage & Site-wide SEO -->
            <div class="settings-card">
                <div class="settings-card-title"><i class="fa-solid fa-magnifying-glass"></i> Homepage & Site-wide SEO Settings</div>
                
                <div class="mb-3">
                    <label class="form-label">Site Meta Keywords</label>
                    <input type="text" name="seo_keywords" class="form-control" value="<?php echo htmlspecialchars($site_settings['seo_keywords'] ?? ''); ?>" placeholder="comma, separated, keywords">
                    <small class="text-muted d-block mt-1">Site-wide keywords used for search indexing.</small>
                </div>

                <div class="mb-3">
                    <label class="form-label">Homepage Meta Title</label>
                    <input type="text" name="homepage_title" class="form-control" value="<?php echo htmlspecialchars($site_settings['homepage_title'] ?? ''); ?>" placeholder="SEO Title (e.g. Online Urdu Novels Library)">
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Homepage Meta Description</label>
                    <textarea name="homepage_description" class="form-control" rows="3" placeholder="A brief description of your homepage..."><?php echo htmlspecialchars($site_settings['homepage_description'] ?? ''); ?></textarea>
                </div>

                <div class="mb-4">
                    <label class="form-label">Homepage Featured Image (OG Image)</label>
                    <?php if(!empty($site_settings['homepage_featured_image'])): ?>
                        <div class="mb-3" style="max-width: 320px;">
                            <img src="../<?php echo htmlspecialchars($site_settings['homepage_featured_image']); ?>" class="img-thumbnail w-100 mb-2" style="border-color: rgba(255,255,255,0.12); background: rgba(10,12,8,0.6);">
                            <a href="settings.php?remove_homepage_featured_image=1" class="btn btn-sm btn-danger" onclick="return confirm('Remove this image?');"><i class="fa-solid fa-trash me-1"></i> Remove Image</a>
                        </div>
                    <?php endif; ?>
                    <input type="file" name="homepage_featured_image" class="form-control" accept="image/*">
                    <small class="text-muted d-block mt-1">This featured image is displayed when the homepage link is shared on Facebook, WhatsApp, Twitter, etc.</small>
                </div>

                <div class="p-3 rounded border" style="background-color: rgba(10,12,8,0.6); border-color: rgba(255,255,255,0.12) !important;">
                    <label class="form-label mb-3"><i class="fa-solid fa-link"></i> Permalink Structure</label>
                    <div class="form-check mb-2 text-white">
                        <input class="form-check-input" type="radio" name="permalink_structure" id="plain" value="plain" <?php echo ($site_settings['permalink_structure'] == 'plain' || empty($site_settings['permalink_structure'])) ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="plain">
                            <strong>Plain</strong> <code>/single.php?id=123</code>
                        </label>
                    </div>
                    <div class="form-check text-white">
                        <input class="form-check-input" type="radio" name="permalink_structure" id="postname" value="postname" <?php echo ($site_settings['permalink_structure'] == 'postname') ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="postname">
                            <strong>Post name</strong> <code>/sample-post/</code>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Google Integrations -->
            <div class="settings-card">
                <div class="settings-card-title"><i class="fa-brands fa-google"></i> Google Integrations (SEO & Analytics)</div>
                
                <div class="mb-3">
                    <label class="form-label">Google Search Console Verification Code</label>
                    <input type="text" name="search_console" class="form-control" value="<?php echo htmlspecialchars($site_settings['search_console'] ?? ''); ?>" placeholder='<meta name="google-site-verification" content="..." />'>
                    <small class="text-muted d-block mt-1">Paste the full meta tag provided by Google Search Console here.</small>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Google Analytics Code (Measurement ID or Script)</label>
                    <textarea name="google_analytics" class="form-control" rows="4" placeholder="<!-- Google tag (gtag.js) -->..."><?php echo htmlspecialchars($site_settings['google_analytics'] ?? ''); ?></textarea>
                    <small class="text-muted d-block mt-1">Paste your full Google Analytics tracking script here. It will automatically be injected into the website's header.</small>
                </div>
            </div>

            <!-- Push Notifications (LaraPush) -->
            <div class="settings-card">
                <div class="settings-card-title"><i class="fa-solid fa-bell"></i> Push Notifications (LaraPush)</div>
                
                <div class="mb-3">
                    <label class="form-label">LaraPush Integration Code</label>
                    <textarea name="larapush_code" class="form-control" rows="4" placeholder="<script src='...'></script>..."><?php echo htmlspecialchars($site_settings['larapush_code'] ?? ''); ?></textarea>
                    <small class="text-muted d-block mt-1">Paste your LaraPush tracking/integration javascript code here.</small>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Upload Service Worker File (.js)</label>
                    <input type="file" name="larapush_file" class="form-control" accept=".js">
                    <small class="text-muted d-block mt-1">Upload the <code>sw.js</code> or <code>larapush-sw.js</code> file provided by LaraPush. It will be uploaded to the root directory automatically.</small>
                </div>
            </div>

            <!-- Business & Security -->
            <div class="settings-card" id="coming-soon">
                <div class="settings-card-title"><i class="fa-solid fa-shield-halved"></i> Business & Security</div>
                <div class="row g-4">
                    <div class="col-md-6">
                        <label class="form-label">Maintenance Mode</label>
                        <div class="maintenance-toggle">
                            <div class="form-check form-switch fs-5 mb-0">
                                <input class="form-check-input" type="checkbox" name="maintenance_mode" id="maintenanceMode" <?php echo !empty($site_settings['maintenance_mode']) ? 'checked' : ''; ?>>
                                <label class="form-check-label ms-2 fs-6 fw-bold <?php echo !empty($site_settings['maintenance_mode']) ? 'text-danger' : 'text-success'; ?>" for="maintenanceMode">
                                    <?php echo !empty($site_settings['maintenance_mode']) ? 'Site is UNDER MAINTENANCE' : 'Site is LIVE'; ?>
                                </label>
                            </div>
                        </div>
                        <small class="text-muted mt-2 d-block">Whole site — visitors see maintenance (HTTP 503). Admins can still access the panel.</small>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Community — Coming Soon</label>
                        <div class="form-check form-switch fs-5 mb-2">
                            <input class="form-check-input" type="checkbox" name="community_coming_soon" id="communityComingSoon" <?php echo !empty($site_settings['community_coming_soon']) ? 'checked' : ''; ?>>
                            <label class="form-check-label ms-2 fs-6 fw-bold" for="communityComingSoon">
                                <?php echo !empty($site_settings['community_coming_soon']) ? 'Community is HIDDEN' : 'Community is LIVE'; ?>
                            </label>
                        </div>
                        <small class="text-muted d-block mb-2">Only <code>/community/</code> and related pages. Main site stays open.</small>
                        <?php $commPreview = coming_soon_preview_url('community'); if ($commPreview): ?>
                        <div class="input-group input-group-sm">
                            <input type="text" class="form-control" id="communityPreviewUrl" readonly value="<?php echo htmlspecialchars($commPreview); ?>">
                            <button type="button" class="btn btn-outline-secondary" onclick="navigator.clipboard.writeText(document.getElementById('communityPreviewUrl').value)"><i class="fa-regular fa-copy"></i></button>
                            <a href="<?php echo htmlspecialchars($commPreview); ?>" target="_blank" rel="noopener" class="btn btn-outline-success"><i class="fa-solid fa-external-link-alt"></i></a>
                        </div>
                        <small class="text-muted d-block mt-1">Preview URL — share this link to see the live community while visitors see coming soon.</small>
                        <a href="settings.php?regen_community_preview=1" class="small text-warning d-inline-block mt-1" onclick="return confirm('Generate a new Community preview link? Old links will stop working.');">Regenerate preview key</a>
                        <?php endif; ?>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">Shop — Coming Soon</label>
                        <div class="form-check form-switch fs-5 mb-2">
                            <input class="form-check-input" type="checkbox" name="shop_coming_soon" id="shopComingSoon" <?php echo !empty($site_settings['shop_coming_soon']) ? 'checked' : ''; ?>>
                            <label class="form-check-label ms-2 fs-6 fw-bold" for="shopComingSoon">
                                <?php echo !empty($site_settings['shop_coming_soon']) ? 'Shop is HIDDEN' : 'Shop is LIVE'; ?>
                            </label>
                        </div>
                        <small class="text-muted d-block mb-2">Only <code>/shop/</code> pages. Novel pages stay open.</small>
                        <?php $shopPreview = coming_soon_preview_url('shop'); if ($shopPreview): ?>
                        <div class="input-group input-group-sm">
                            <input type="text" class="form-control" id="shopPreviewUrl" readonly value="<?php echo htmlspecialchars($shopPreview); ?>">
                            <button type="button" class="btn btn-outline-secondary" onclick="navigator.clipboard.writeText(document.getElementById('shopPreviewUrl').value)"><i class="fa-regular fa-copy"></i></button>
                            <a href="<?php echo htmlspecialchars($shopPreview); ?>" target="_blank" rel="noopener" class="btn btn-outline-success"><i class="fa-solid fa-external-link-alt"></i></a>
                        </div>
                        <small class="text-muted d-block mt-1">Preview URL — open the real shop while everyone else sees coming soon.</small>
                        <a href="settings.php?regen_shop_preview=1" class="small text-warning d-inline-block mt-1" onclick="return confirm('Generate a new Shop preview link? Old links will stop working.');">Regenerate preview key</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Sticky Save Footer (Mobile/Bottom) -->
            <div class="text-end mb-5">
                <button type="submit" class="btn-save btn-lg px-5"><i class="fa-solid fa-save me-2"></i> Save All Settings</button>
            </div>
            
        </form>
    </div>

    <!-- Script for file upload preview -->
    <script>
        document.getElementById('site_logo').onchange = function (evt) {
            var tgt = evt.target || window.event.srcElement, files = tgt.files;
            if (FileReader && files && files.length) {
                var fr = new FileReader();
                fr.onload = function () {
                    // Update preview if exists, or just alert user it's selected
                    alert("Logo selected. Save to apply.");
                }
                fr.readAsDataURL(files[0]);
            }
        }
        document.getElementById('site_favicon').onchange = function (evt) {
            var tgt = evt.target || window.event.srcElement, files = tgt.files;
            if (FileReader && files && files.length) {
                var fr = new FileReader();
                fr.onload = function () {
                    alert("Favicon selected. Save to apply.");
                }
                fr.readAsDataURL(files[0]);
            }
        }
        
        // Maintenance mode text toggle
        document.getElementById('maintenanceMode').addEventListener('change', function() {
            let label = this.nextElementSibling;
            if(this.checked) {
                label.textContent = "Site is UNDER MAINTENANCE";
                label.className = "form-check-label ms-2 fs-6 fw-bold text-danger";
            } else {
                label.textContent = "Site is LIVE";
                label.className = "form-check-label ms-2 fs-6 fw-bold text-success";
            }
        });
    </script>
</body>
</html>
