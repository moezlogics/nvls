<?php
$current_page = basename($_SERVER['PHP_SELF']);
if(isset($_SESSION['admin_id'])) {
    $sidebar_user_query = $conn->query("SELECT username, profile_pic, role FROM admin_users WHERE id = " . (int)$_SESSION['admin_id']);
    $sidebar_user = $sidebar_user_query->fetch_assoc();
}

// Smart badge: pending comments count
$pending_count = 0;
$new_contact_count = 0;
if (isset($_SESSION['admin_id'])) {
    $pc = $conn->query("SELECT COUNT(*) c FROM comments WHERE status='pending'");
    if ($pc) $pending_count = (int)$pc->fetch_assoc()['c'];
    $nc = $conn->query("SELECT COUNT(*) c FROM contact_queries WHERE status='new'");
    if ($nc) $new_contact_count = (int)$nc->fetch_assoc()['c'];
}

$page_titles = [
    'dashboard.php' => 'Dashboard', 'manage.php' => 'All Posts', 'add.php' => 'Add New Post',
    'edit.php' => 'Edit Post', 'categories.php' => 'Categories', 'writers.php' => 'Writers', 'comments.php' => 'Comments',
    'contact_messages.php' => 'Contact Messages', 'page_content.php' => 'About & Contact Pages',
    'media.php' => 'Media Library', 'manage_pages.php' => 'All Pages', 'add_page.php' => 'Add New Page',
    'edit_page.php' => 'Edit Page', 'menu.php' => 'Navigation Menu', 'theme.php' => 'Theme & Appearance',
    'footer.php' => 'Manage Footer', 'settings.php' => 'Platform Settings',
    'profile.php' => 'My Profile', 'users.php' => 'Manage Users',
    'products.php' => 'Bookstore Products', 'orders.php' => 'Bookstore Orders',
];
$pt = $page_titles[$current_page] ?? 'Admin';

function admin_avatar($user, $size = 60) {
    if (!empty($user['profile_pic'])) {
        return "<img src='../" . htmlspecialchars($user['profile_pic']) . "' class='av-img' style='width:{$size}px;height:{$size}px;'>";
    }
    $initial = strtoupper(substr($user['username'] ?? 'A', 0, 1));
    $fs = round($size * 0.42);
    return "<span class='av-fallback' style='width:{$size}px;height:{$size}px;font-size:{$fs}px;'>{$initial}</span>";
}

// ── Smart, WordPress-style menu tree ──
$is_main_admin = (isset($_SESSION['admin_role']) && $_SESSION['admin_role'] == 'main_admin');
$menu = [
    ['type' => 'link', 'label' => 'Dashboard', 'icon' => 'fa-gauge', 'href' => 'dashboard.php', 'page' => 'dashboard.php'],
    ['type' => 'group', 'label' => 'Posts', 'icon' => 'fa-thumbtack', 'children' => [
        ['label' => 'All Posts',  'href' => 'manage.php',     'page' => 'manage.php', 'alt' => ['edit.php']],
        ['label' => 'Add New',    'href' => 'add.php',        'page' => 'add.php'],
        ['label' => 'Categories', 'href' => 'categories.php', 'page' => 'categories.php'],
        ['label' => 'Writers',    'href' => 'writers.php',    'page' => 'writers.php'],
        ['label' => 'Comments',   'href' => 'comments.php',   'page' => 'comments.php', 'badge' => $pending_count],
        ['label' => 'Contact Messages', 'href' => 'contact_messages.php', 'page' => 'contact_messages.php', 'badge' => $new_contact_count],
    ]],
    ['type' => 'group', 'label' => 'Pages', 'icon' => 'fa-file-lines', 'children' => [
        ['label' => 'All Pages', 'href' => 'manage_pages.php', 'page' => 'manage_pages.php', 'alt' => ['edit_page.php']],
        ['label' => 'Add New',   'href' => 'add_page.php',     'page' => 'add_page.php'],
    ]],
    ['type' => 'group', 'label' => 'Bookstore', 'icon' => 'fa-store', 'children' => [
        ['label' => 'Products',  'href' => 'products.php',     'page' => 'products.php'],
        ['label' => 'Orders',    'href' => 'orders.php',       'page' => 'orders.php'],
    ]],
    ['type' => 'link', 'label' => 'Media', 'icon' => 'fa-image', 'href' => 'media.php', 'page' => 'media.php'],
];

if ($is_main_admin) {
    $menu[] = ['type' => 'group', 'label' => 'Appearance', 'icon' => 'fa-palette', 'children' => [
        ['label' => 'Themes',         'href' => 'theme.php', 'page' => 'theme.php'],
        ['label' => 'Navigation Menu','href' => 'menu.php',  'page' => 'menu.php'],
        ['label' => 'Footer',         'href' => 'footer.php','page' => 'footer.php'],
        ['label' => 'About & Contact','href' => 'page_content.php', 'page' => 'page_content.php'],
    ]];
    $menu[] = ['type' => 'link', 'label' => 'Settings', 'icon' => 'fa-gear', 'href' => 'settings.php', 'page' => 'settings.php'];
}

$menu[] = ['type' => 'group', 'label' => 'Account', 'icon' => 'fa-user', 'children' => array_merge(
    [['label' => 'My Profile', 'href' => 'profile.php', 'page' => 'profile.php']],
    $is_main_admin ? [['label' => 'Manage Users', 'href' => 'users.php', 'page' => 'users.php']] : []
)];
function child_active($child, $current) {
    if ($child['page'] === $current) return true;
    if (!empty($child['alt']) && in_array($current, $child['alt'])) return true;
    return false;
}
?>
<script>document.documentElement.setAttribute('data-bs-theme','dark');</script>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
    :root{
        --adm-bg:#000000;
        --adm-glass:rgba(255,255,255,.05); --adm-glass-2:rgba(255,255,255,.07);
        --adm-border:rgba(255,255,255,.10); --adm-border-green:rgba(108,200,50,.18);
        --adm-text:#ffffff; --adm-muted:#c5cad4;
        --adm-accent:#6CC832; --adm-accent-2:#54a626; --adm-on-accent:#0c2104;
    }
    html{ font-size:16.5px; }
    *{ scrollbar-width:thin; scrollbar-color:#2f3a26 transparent; }
    body{
        background:#000000 !important; color:var(--adm-text)!important;
        font-family:'Inter',sans-serif; letter-spacing:.1px; line-height:1.55; position:relative;
    }
    /* Center green glassmorphism glow on the full-black background */
    body::before{
        content:''; position:fixed; inset:0; z-index:0; pointer-events:none;
        background:
            radial-gradient(1100px 760px at 50% 26%, rgba(108,200,50,.18), rgba(108,200,50,.05) 36%, transparent 60%),
            radial-gradient(760px 520px at 88% 92%, rgba(108,200,50,.08), transparent 55%);
    }
    .main-content{ position:relative; z-index:1; }
    p, span, td, th, li, label, h1, h2, h3, h4, h5, h6, strong, small, div{ color:inherit; }
    small, .small{ font-size:.84rem; }
    ::-webkit-scrollbar{ width:8px; height:8px; }
    ::-webkit-scrollbar-thumb{ background:#2f3a26; border-radius:0; }

    /* ---------- Glass cards ---------- */
    .card{
        background:var(--adm-glass)!important; border:1px solid var(--adm-border-green)!important;
        border-radius:0; color:var(--adm-text)!important;
        box-shadow:0 8px 32px rgba(0,0,0,.45)!important;
        -webkit-backdrop-filter:blur(16px); backdrop-filter:blur(16px);
    }
    .card-header{ background:transparent!important; border-bottom:1px solid var(--adm-border)!important; color:#fff!important; font-weight:700; padding:1rem 1.25rem; font-size:1.02rem; }
    .card-footer{ background:transparent!important; border-top:1px solid var(--adm-border)!important; }
    .card-body{ color:var(--adm-text)!important; }

    /* ---------- Forms (glass) ---------- */
    .form-control,.form-select,.input-group-text,textarea{ background:var(--adm-glass)!important; border:1px solid var(--adm-border)!important; color:#ffffff!important; border-radius:0; padding:.62rem .85rem; font-size:.98rem; }
    .form-control::placeholder,textarea::placeholder{ color:#8a909c!important; }
    .form-control:focus,.form-select:focus,textarea:focus{ background:var(--adm-glass-2)!important; border-color:var(--adm-accent)!important; color:#fff!important; box-shadow:0 0 0 3px rgba(108,200,50,.25)!important; }
    .form-label{ font-weight:600; color:#eef0f4; margin-bottom:.4rem; font-size:.95rem; }
    .form-check-input{ background-color:rgba(255,255,255,.06); border-color:var(--adm-border); }
    .form-check-input:checked{ background-color:var(--adm-accent); border-color:var(--adm-accent); }

    /* ---------- Tables ---------- */
    .table{ color:var(--adm-text)!important; --bs-table-bg:transparent!important; border-color:var(--adm-border)!important; font-size:.96rem; }
    .table thead th{ background:rgba(255,255,255,.03)!important; color:#dfe3ea!important; text-transform:uppercase; font-size:.78rem; letter-spacing:.5px; border-bottom:1px solid var(--adm-border)!important; border-top:none!important; padding:.9rem .9rem; }
    .table td,.table th{ border-color:var(--adm-border)!important; padding:.9rem .9rem!important; vertical-align:middle; color:var(--adm-text)!important; }
    .table-hover tbody tr:hover td{ background:rgba(108,200,50,.1)!important; color:#fff!important; }

    /* ---------- Buttons: FULLY ROUNDED (pill), correct text contrast ---------- */
    .btn{ border-radius:0; font-weight:700; font-size:.96rem; padding:.5rem 1.3rem; }
    .btn-primary{ background:linear-gradient(135deg,var(--adm-accent),var(--adm-accent-2))!important; border:none!important; color:var(--adm-on-accent)!important; box-shadow:0 4px 14px rgba(108,200,50,.35); }
    .btn-primary:hover{ filter:brightness(1.07); color:var(--adm-on-accent)!important; }
    .btn-outline-primary{ color:#9bdd6b!important; border:1px solid var(--adm-accent)!important; background:transparent!important; }
    .btn-outline-primary:hover{ background:var(--adm-accent)!important; color:var(--adm-on-accent)!important; }
    .btn-success{ background:#16a34a!important; border:none!important; color:#fff!important; }
    .btn-warning{ background:#f59e0b!important; border:none!important; color:#3d2c00!important; }
    .btn-danger{ background:#dc2626!important; border:none!important; color:#fff!important; }
    .btn-info{ background:linear-gradient(135deg,var(--adm-accent),var(--adm-accent-2))!important; border:none!important; color:var(--adm-on-accent)!important; }
    .btn-secondary{ background:rgba(255,255,255,.1)!important; border:none!important; color:#fff!important; }
    .btn-light{ background:rgba(255,255,255,.08)!important; border:1px solid var(--adm-border)!important; color:#fff!important; }
    .btn-outline-secondary{ color:#e2e5ea!important; border-color:var(--adm-border)!important; }
    .btn-outline-danger{ color:#f87171!important; border-color:#dc2626!important; }
    .btn-outline-danger:hover{ background:#dc2626!important; color:#fff!important; }
    /* Page-specific publish button -> green + dark text + pill */
    .btn-publish{ background:linear-gradient(135deg,var(--adm-accent),var(--adm-accent-2))!important; color:var(--adm-on-accent)!important; border-radius:0; }
    .select2-results__option--highlighted{ color:var(--adm-on-accent)!important; }

    /* ---------- Blue -> single green theme everywhere ---------- */
    .text-primary,.text-info,.link-primary{ color:var(--adm-accent)!important; }

    /* ---------- Badges ---------- */
    .badge{ font-size:.74rem; }
    .badge.bg-secondary{ background:rgba(255,255,255,.12)!important; color:#fff!important; }
    .badge.bg-success{ background:#16a34a!important; color:#fff!important; }
    .badge.bg-warning{ background:#f59e0b!important; color:#3d2c00!important; }
    .badge.bg-danger{ background:#dc2626!important; color:#fff!important; }
    .badge.bg-primary,.badge.bg-info{ background:var(--adm-accent)!important; color:var(--adm-on-accent)!important; }

    /* ---------- Alerts (glass) ---------- */
    .alert{ border-radius:0; border:1px solid transparent; font-size:.96rem; }
    .alert-success{ background:rgba(22,163,74,.16)!important; border-color:rgba(22,163,74,.45)!important; color:#a7f3c0!important; }
    .alert-danger{ background:rgba(220,38,38,.16)!important; border-color:rgba(220,38,38,.45)!important; color:#fca5a5!important; }
    .alert-warning{ background:rgba(245,158,11,.16)!important; border-color:rgba(245,158,11,.45)!important; color:#fcd34d!important; }
    .alert-info{ background:rgba(108,200,50,.16)!important; border-color:rgba(108,200,50,.45)!important; color:#bde89a!important; }

    /* ---------- Misc components ---------- */
    .modal-content{ background:rgba(16,18,14,.85)!important; -webkit-backdrop-filter:blur(18px); backdrop-filter:blur(18px); border:1px solid var(--adm-border-green)!important; border-radius:0; color:var(--adm-text); }
    .modal-header,.modal-footer{ border-color:var(--adm-border)!important; }
    .dropdown-menu{ background:rgba(16,18,14,.92)!important; -webkit-backdrop-filter:blur(14px); backdrop-filter:blur(14px); border:1px solid var(--adm-border)!important; border-radius:0; }
    .dropdown-item{ color:var(--adm-text)!important; }
    .dropdown-item:hover{ background:rgba(108,200,50,.14)!important; color:#fff!important; }
    .list-group-item{ background:var(--adm-glass)!important; border-color:var(--adm-border)!important; color:var(--adm-text)!important; }
    .nav-tabs{ border-bottom:1px solid var(--adm-border); }
    .nav-tabs .nav-link{ color:var(--adm-muted); border:none; }
    .nav-tabs .nav-link.active{ background:transparent; color:#fff; border-bottom:2px solid var(--adm-accent); }
    .page-link{ background:var(--adm-glass); border-color:var(--adm-border); color:var(--adm-text); }
    .page-item.active .page-link{ background:var(--adm-accent); border-color:var(--adm-accent); color:var(--adm-on-accent); }
    .breadcrumb{ background:transparent; }
    a{ color:#9bdd6b; }
    a:hover{ color:#b6ea90; }
    .text-muted{ color:var(--adm-muted)!important; }
    .text-dark{ color:#fff!important; }
    .text-secondary{ color:var(--adm-muted)!important; }
    .bg-white,.bg-light{ background-color:transparent!important; }
    .border,.border-bottom,.border-top,.border-start,.border-end{ border-color:var(--adm-border)!important; }

    /* ---------- Stat cards (green-family, no blue) ---------- */
    .bg-primary{ background:linear-gradient(135deg,#6CC832,#54a626)!important; }
    .bg-success{ background:linear-gradient(135deg,#16a34a,#15803d)!important; }
    .bg-info{ background:linear-gradient(135deg,#10b981,#059669)!important; }
    .bg-warning{ background:linear-gradient(135deg,#f59e0b,#d97706)!important; }

    /* ---------- Summernote ---------- */
    .note-editor.note-frame{ border:1px solid var(--adm-border)!important; border-radius:0; overflow:hidden; }
    .note-toolbar{ background:rgba(255,255,255,.05)!important; border-bottom:1px solid var(--adm-border)!important; }
    .note-btn{ background:rgba(255,255,255,.06)!important; border:1px solid var(--adm-border)!important; color:var(--adm-text)!important; }
    .note-editable{ background:#ffffff!important; color:#1f2430!important; }
    .note-editing-area .note-editable{ min-height:220px; }
    .note-statusbar{ background:rgba(255,255,255,.05)!important; border-top:1px solid var(--adm-border)!important; }
    .note-dropdown-menu{ background:rgba(16,18,14,.95)!important; border:1px solid var(--adm-border)!important; color:var(--adm-text)!important; }

    /* ========== LAYOUT: glass sidebar + topbar ========== */
    .sidebar{
        position:fixed; top:0; left:0; width:260px; height:100vh; z-index:1050;
        background:rgba(6,9,4,.72); -webkit-backdrop-filter:blur(20px); backdrop-filter:blur(20px);
        border-right:1px solid var(--adm-border-green);
        padding:18px 0 30px; overflow-y:auto; transition:transform .3s ease;
    }
    .sidebar-brand{ display:flex; align-items:center; gap:10px; padding:0 22px 16px; }
    .sidebar-brand .logo{ display:inline-flex; align-items:center; justify-content:center; width:38px; height:38px; border-radius:0; background:linear-gradient(135deg,var(--adm-accent),var(--adm-accent-2)); color:var(--adm-on-accent); font-size:1.1rem; box-shadow:0 4px 14px rgba(108,200,50,.45); }
    .sidebar-brand .name{ font-weight:800; color:#fff; font-size:1.1rem; }
    .sidebar-profile{ display:flex; align-items:center; gap:12px; margin:0 16px 14px; padding:12px; border-radius:0; background:var(--adm-glass); border:1px solid var(--adm-border); }
    .av-img{ border-radius:0; object-fit:cover; border:2px solid rgba(108,200,50,.4); }
    .av-fallback{ display:inline-flex; align-items:center; justify-content:center; border-radius:0; background:linear-gradient(135deg,var(--adm-accent),var(--adm-accent-2)); color:var(--adm-on-accent); font-weight:800; }
    .sidebar-profile .pname{ font-weight:700; color:#fff; font-size:.98rem; line-height:1.1; }
    .sidebar-profile .prole{ display:inline-block; margin-top:4px; font-size:.64rem; font-weight:700; text-transform:uppercase; letter-spacing:.5px; padding:2px 8px; border-radius:0; background:rgba(108,200,50,.22); color:#bdee93; }

    .sidebar a.nav-item-link{
        display:flex; align-items:center; gap:11px; color:#e3e6ec; text-decoration:none;
        padding:10px 16px; margin:3px 14px; border-radius:0; font-weight:500; font-size:.98rem; transition:.2s;
    }
    .sidebar a.nav-item-link i.lead{ width:20px; text-align:center; font-size:1rem; }
    .sidebar a.nav-item-link:hover{ background:rgba(108,200,50,.12); color:#fff; }
    .sidebar a.nav-item-link.active{ background:linear-gradient(135deg,var(--adm-accent),var(--adm-accent-2)); color:var(--adm-on-accent); font-weight:700; box-shadow:0 4px 16px rgba(108,200,50,.45); }
    .sidebar a.logout{ color:#f87171; }
    .sidebar a.viewsite{ color:#9bdd6b; }

    .nav-group{ margin:3px 14px; }
    .nav-group > summary{
        display:flex; align-items:center; gap:11px; cursor:pointer; list-style:none;
        color:#e3e6ec; padding:10px 16px; border-radius:0; font-weight:500; font-size:.98rem; transition:.2s;
    }
    .nav-group > summary::-webkit-details-marker{ display:none; }
    .nav-group > summary i.lead{ width:20px; text-align:center; font-size:1rem; }
    .nav-group > summary .chev{ margin-left:auto; font-size:.7rem; transition:transform .25s ease; opacity:.7; }
    .nav-group[open] > summary .chev{ transform:rotate(90deg); }
    .nav-group > summary:hover{ background:rgba(108,200,50,.12); color:#fff; }
    .nav-group.has-active > summary{ color:#fff; background:rgba(108,200,50,.1); }
    .nav-submenu{ display:flex; flex-direction:column; gap:2px; margin:3px 0 6px 30px; padding-left:10px; border-left:1px solid rgba(255,255,255,.12); }
    .nav-submenu a{ display:flex; align-items:center; gap:8px; color:#c5cad4; text-decoration:none; padding:8px 14px; border-radius:0; font-size:.92rem; transition:.2s; position:relative; }
    .nav-submenu a:hover{ background:rgba(108,200,50,.12); color:#fff; }
    .nav-submenu a.active{ color:#fff; background:rgba(108,200,50,.2); font-weight:600; }
    .nav-submenu a.active::before{ content:''; position:absolute; left:-11px; top:50%; transform:translateY(-50%); width:3px; height:16px; border-radius:0; background:var(--adm-accent); }
    .nav-badge{ margin-left:auto; min-width:20px; text-align:center; background:#dc2626; color:#fff; font-size:.68rem; font-weight:700; padding:1px 7px; border-radius:0; line-height:1.4; }

    .admin-topbar{
        position:fixed; top:0; left:260px; right:0; height:64px; z-index:1030;
        display:flex; align-items:center; gap:14px; padding:0 24px;
        background:rgba(6,9,4,.55); -webkit-backdrop-filter:blur(16px); backdrop-filter:blur(16px); border-bottom:1px solid var(--adm-border-green);
    }
    .admin-topbar .tb-title{ font-weight:700; font-size:1.12rem; color:#fff; }
    .admin-topbar .tb-right{ margin-left:auto; display:flex; align-items:center; gap:16px; }
    .admin-topbar .tb-link{ color:#d3d7df; text-decoration:none; font-size:.92rem; font-weight:600; }
    .admin-topbar .tb-link:hover{ color:#fff; }
    .admin-topbar .tb-user{ display:flex; align-items:center; gap:9px; }
    .admin-topbar .tb-user .uname{ font-size:.9rem; font-weight:600; color:#fff; }
    .sidebar-toggle{ background:var(--adm-glass); border:1px solid var(--adm-border); color:#fff; width:42px; height:42px; border-radius:0; font-size:1.1rem; cursor:pointer; }

    .main-content{ margin-left:260px; padding:88px 30px 40px; min-height:100vh; }

    .sidebar-overlay{ position:fixed; inset:0; background:rgba(0,0,0,.6); z-index:1040; display:none; }
    .sidebar-overlay.show{ display:block; }

    @media(max-width:991.98px){
        .sidebar{ transform:translateX(-100%); }
        .sidebar.open{ transform:translateX(0); box-shadow:0 0 50px rgba(0,0,0,.7); }
        .admin-topbar{ left:0; padding:0 16px; }
        .main-content{ margin-left:0; padding:84px 16px 32px; }
        .admin-topbar .tb-link span{ display:none; }
    }
    @media(min-width:992px){ .sidebar-toggle{ display:none; } }

    /* Sharp, full-width "proper" sidebar items with green left-accent */
    .sidebar a.nav-item-link, .nav-group > summary{ margin:0; padding:11px 22px; border-left:3px solid transparent; }
    .sidebar a.nav-item-link:hover, .nav-group > summary:hover{ background:rgba(108,200,50,.1); color:#fff; border-left-color:var(--adm-accent); }
    .sidebar a.nav-item-link.active{ background:linear-gradient(90deg,rgba(108,200,50,.30),rgba(108,200,50,.06)); color:#fff; font-weight:700; border-left-color:var(--adm-accent); box-shadow:none; }
    .nav-group{ margin:0; }
    .nav-group.has-active > summary{ background:rgba(108,200,50,.07); color:#fff; border-left-color:var(--adm-accent); }
    .nav-submenu{ margin:0; padding:0; border-left:none; background:rgba(0,0,0,.25); }
    .nav-submenu a{ padding:9px 22px 9px 54px; border-left:3px solid transparent; }
    .nav-submenu a:hover{ background:rgba(108,200,50,.1); }
    .nav-submenu a.active{ background:rgba(108,200,50,.16); border-left-color:var(--adm-accent); }
    .nav-submenu a.active::before{ display:none; }
    /* Square all Bootstrap rounded utilities + thumbnails (full sharp) */
    .rounded,.rounded-0,.rounded-1,.rounded-2,.rounded-3,.rounded-4,.rounded-circle,.rounded-pill,.rounded-top,.rounded-bottom,.rounded-start,.rounded-end,.img-thumbnail{ border-radius:0!important; }
</style>

<!-- Top bar -->
<header class="admin-topbar">
    <button class="sidebar-toggle" onclick="toggleAdminSidebar()" aria-label="Menu"><i class="fa-solid fa-bars"></i></button>
    <span class="tb-title"><?php echo htmlspecialchars($pt); ?></span>
    <div class="tb-right">
        <a href="../index.php" target="_blank" class="tb-link"><i class="fa-solid fa-globe me-1"></i><span>View Site</span></a>
        <?php if(isset($sidebar_user)): ?>
        <div class="tb-user">
            <?php echo admin_avatar($sidebar_user, 34); ?>
            <span class="uname d-none d-sm-inline"><?php echo htmlspecialchars($sidebar_user['username']); ?></span>
        </div>
        <?php endif; ?>
    </div>
</header>

<!-- Sidebar -->
<nav class="sidebar" id="adminSidebar">
    <div class="sidebar-brand">
        <span class="logo"><i class="fa-solid fa-book"></i></span>
        <span class="name">Admin Panel</span>
    </div>
    <?php if(isset($sidebar_user)): ?>
    <div class="sidebar-profile">
        <?php echo admin_avatar($sidebar_user, 44); ?>
        <div>
            <div class="pname"><?php echo htmlspecialchars($sidebar_user['username']); ?></div>
            <span class="prole"><?php echo htmlspecialchars(str_replace('_', ' ', $sidebar_user['role'])); ?></span>
        </div>
    </div>
    <?php endif; ?>

    <?php foreach ($menu as $item): ?>
        <?php if ($item['type'] === 'link'): ?>
            <a href="<?php echo $item['href']; ?>" class="nav-item-link <?php echo $current_page == $item['page'] ? 'active' : ''; ?>">
                <i class="fa-solid <?php echo $item['icon']; ?> lead"></i> <?php echo $item['label']; ?>
            </a>
        <?php else:
            $group_active = false;
            foreach ($item['children'] as $ch) { if (child_active($ch, $current_page)) { $group_active = true; break; } }
        ?>
            <details class="nav-group <?php echo $group_active ? 'has-active' : ''; ?>" <?php echo $group_active ? 'open' : ''; ?>>
                <summary>
                    <i class="fa-solid <?php echo $item['icon']; ?> lead"></i>
                    <?php echo $item['label']; ?>
                    <i class="fa-solid fa-chevron-right chev"></i>
                </summary>
                <div class="nav-submenu">
                    <?php foreach ($item['children'] as $ch): ?>
                    <a href="<?php echo $ch['href']; ?>" class="nav-sub <?php echo child_active($ch, $current_page) ? 'active' : ''; ?>">
                        <?php echo $ch['label']; ?>
                        <?php if (!empty($ch['badge'])): ?><span class="nav-badge"><?php echo (int)$ch['badge']; ?></span><?php endif; ?>
                    </a>
                    <?php endforeach; ?>
                </div>
            </details>
        <?php endif; ?>
    <?php endforeach; ?>

    <a href="logout.php" class="nav-item-link logout" style="margin-top:14px;"><i class="fa-solid fa-right-from-bracket lead"></i> Logout</a>
    <a href="../index.php" target="_blank" class="nav-item-link viewsite"><i class="fa-solid fa-globe lead"></i> View Live Site</a>
</nav>

<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleAdminSidebar()"></div>

<script>
    function toggleAdminSidebar(){
        document.getElementById('adminSidebar').classList.toggle('open');
        document.getElementById('sidebarOverlay').classList.toggle('show');
    }
    document.querySelectorAll('#adminSidebar a.nav-item-link, #adminSidebar a.nav-sub').forEach(function(a){
        a.addEventListener('click', function(){
            if(window.innerWidth < 992){
                document.getElementById('adminSidebar').classList.remove('open');
                document.getElementById('sidebarOverlay').classList.remove('show');
            }
        });
    });

    // Global CSRF Token Injection for jQuery AJAX and Native Fetch POST requests
    (function() {
        var token = '<?php echo csrf_token(); ?>';
        // Intercept native fetch
        if (window.fetch) {
            var originalFetch = window.fetch;
            window.fetch = function(input, init) {
                if (init && init.method && init.method.toUpperCase() === 'POST') {
                    init.headers = init.headers || {};
                    if (init.headers instanceof Headers) {
                        init.headers.set('X-CSRF-TOKEN', token);
                    } else if (Array.isArray(init.headers)) {
                        init.headers.push(['X-CSRF-TOKEN', token]);
                    } else {
                        init.headers['X-CSRF-TOKEN'] = token;
                    }
                }
                return originalFetch(input, init);
            };
        }
        // Setup jQuery Ajax
        function setupJqueryAjax() {
            if (typeof jQuery !== 'undefined') {
                jQuery.ajaxSetup({
                    headers: {
                        'X-CSRF-TOKEN': token
                    }
                });
            }
        }
        setupJqueryAjax();
        document.addEventListener('DOMContentLoaded', setupJqueryAjax);
    })();
</script>
