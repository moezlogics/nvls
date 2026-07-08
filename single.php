<?php
require_once 'includes/cache_start.php';
require_once 'db.php';
require_once 'lib/image_seo.php';

$status_condition = isset($_SESSION['admin_logged_in']) ? "" : " AND s.status='active'";

if (isset($_GET['slug']) && !empty($_GET['slug'])) {
    $slug = $conn->real_escape_string($_GET['slug']);
    $query = "SELECT s.*, c.name as category, w.name as writer_name, w.slug as writer_slug, w.short_bio as writer_short_bio, w.profile_pic as writer_profile_pic FROM posts s LEFT JOIN categories c ON s.category_id = c.id LEFT JOIN writers w ON s.writer_id = w.id WHERE s.slug = '$slug' $status_condition";
} elseif (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $id = (int)$_GET['id'];
    $query = "SELECT s.*, c.name as category, w.name as writer_name, w.slug as writer_slug, w.short_bio as writer_short_bio, w.profile_pic as writer_profile_pic FROM posts s LEFT JOIN categories c ON s.category_id = c.id LEFT JOIN writers w ON s.writer_id = w.id WHERE s.id = $id $status_condition";
} else {
    header("Location: " . $base_path . "/posts/");
    exit;
}

$result = $conn->query($query);

if (!$result || $result->num_rows == 0) {
    http_response_code(404);
    require '404.php';
    exit;
}

$row = $result->fetch_assoc();

// Track user category interest for feed personalization algorithm
if ($row && !empty($row['category_id'])) {
    $catId = (int)$row['category_id'];
    if (isset($_SESSION['member_id'])) {
        $mId = (int)$_SESSION['member_id'];
        $stmt = $conn->prepare("INSERT INTO member_interests (member_id, category_id, weight) VALUES (?, ?, 1) ON DUPLICATE KEY UPDATE weight = weight + 1");
        $stmt->bind_param("ii", $mId, $catId);
        $stmt->execute();
        $stmt->close();
    } else {
        // Guest user cookie interest tracking
        $affinities = [];
        if (isset($_COOKIE['guest_category_affinity'])) {
            $decoded = json_decode($_COOKIE['guest_category_affinity'], true);
            if (is_array($decoded)) {
                $affinities = $decoded;
            }
        }
        $affinities[$catId] = isset($affinities[$catId]) ? $affinities[$catId] + 1 : 1;
        setcookie('guest_category_affinity', json_encode($affinities), time() + 30 * 86400, '/');
    }
}

$msg = "";
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_comment'])) {
    require_once __DIR__ . '/lib/content_brain.php';

    $c_message = trim($_POST['message'] ?? '');
    $ip_address = $_SERVER['REMOTE_ADDR'];
    $s_id = (int)$row['id'];
    $parent_id = isset($_POST['parent_id']) && is_numeric($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;

    // Determine commenter type
    $member_id = null;
    $is_admin = 0;
    $c_name = '';
    $c_email = '';

    if (isset($_SESSION['admin_logged_in'])) {
        // Logged-in admin
        $is_admin = 1;
        $c_name = isset($_SESSION['admin_username']) ? $_SESSION['admin_username'] : 'Admin';
        $c_email = isset($_SESSION['admin_email']) ? $_SESSION['admin_email'] : 'admin@site.com';
    } elseif (isset($_SESSION['member_id'])) {
        // Logged-in community member
        $member_id = (int)$_SESSION['member_id'];
        $q = $conn->query("SELECT name, email FROM members WHERE id = $member_id LIMIT 1");
        if ($q && $q->num_rows > 0) {
            $m_info = $q->fetch_assoc();
            $c_name = $m_info['name'];
            $c_email = $m_info['email'];
        } else {
            $c_name = isset($_SESSION['member_name']) ? $_SESSION['member_name'] : 'Member';
            $c_email = 'member_' . $member_id . '@example.com';
        }
    } else {
        // Guest user
        $c_name = trim($_POST['name'] ?? '');
        $c_email = 'guest_' . substr(md5($ip_address), 0, 8) . '@example.com'; // unique fallback email
    }

    if (empty($c_name)) {
        $msg = "<div class='sp-alert sp-alert-err'><i class='fa-solid fa-triangle-exclamation'></i> Please enter your name.</div>";
    } elseif (empty($c_message)) {
        $msg = "<div class='sp-alert sp-alert-err'><i class='fa-solid fa-triangle-exclamation'></i> Please write a comment.</div>";
    } else {
        $mod = content_brain_moderate('comment', $c_message);
        if (!$mod['ok']) {
            $msg = "<div class='sp-alert sp-alert-err'><i class='fa-solid fa-triangle-exclamation'></i> " . htmlspecialchars($mod['reason']) . "</div>";
        } else {
            // 2. Prevent identical duplicate comments from same IP in last 30 seconds
            $c_message_escaped = $conn->real_escape_string($c_message);
            $check_dup = $conn->query("SELECT id FROM comments WHERE ip_address = '$ip_address' AND message = '$c_message_escaped' AND created_at > NOW() - INTERVAL 30 SECOND");
            if ($check_dup && $check_dup->num_rows > 0) {
                $msg = "<div class='sp-alert sp-alert-warn'><i class='fa-solid fa-circle-exclamation'></i> Duplicate comment detected. Please wait a few seconds.</div>";
            } else {
                // 3. Insert approved comment
                $stmt = $conn->prepare("INSERT INTO comments (post_id, member_id, parent_id, name, email, ip_address, is_admin, message, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'approved')");
                $stmt->bind_param("iiisssis", $s_id, $member_id, $parent_id, $c_name, $c_email, $ip_address, $is_admin, $c_message);
                if ($stmt->execute()) {
                    $msg = "<div class='sp-alert sp-alert-ok'><i class='fa-solid fa-circle-check'></i> Comment posted successfully!</div>";
                } else {
                    $msg = "<div class='sp-alert sp-alert-err'><i class='fa-solid fa-triangle-exclamation'></i> Failed to post comment. Try again.</div>";
                }
                $stmt->close();
            }
        }
    }
}
$scheme = (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off') ? 'https' : 'http';
if (($site_settings['permalink_structure'] ?? '') === 'postname' && !empty($row['slug'])) {
    if (strpos($_SERVER['REQUEST_URI'], 'single.php') !== false) {
        $pretty_url = $scheme . '://' . $_SERVER['HTTP_HOST'] . get_post_link($row['id'], $row['slug']);
        header("HTTP/1.1 301 Moved Permanently");
        header("Location: " . $pretty_url);
        exit;
    }
}

$lcp_image_url = !empty($row['image']) ? $row['image'] : '';
$canonical_url = $scheme . '://' . $_SERVER['HTTP_HOST'] . get_post_link($row['id'], $row['slug']);

$is_single_post = true;
include 'includes/header.php';
    
$commentsQuery = $conn->query("
    SELECT c.*, m.profile_pic AS member_avatar
    FROM comments c
    LEFT JOIN members m ON c.member_id = m.id
    WHERE c.post_id = " . (int)$row['id'] . " AND c.status = 'approved'
    ORDER BY c.id ASC
");

$commentsCount = 0;
$parents = [];
$replies = [];

if ($commentsQuery) {
    $commentsCount = $commentsQuery->num_rows;
    while ($c = $commentsQuery->fetch_assoc()) {
        $avatar = $c['member_avatar'] ?? '';
        if ($avatar && !preg_match("~^(?:f|ht)tps?://~i", $avatar)) {
            $c['member_avatar'] = $site_url . '/' . ltrim($avatar, '/');
        }
        
        if ($c['parent_id'] === null) {
            $parents[] = $c;
        } else {
            $replies[$c['parent_id']][] = $c;
        }
    }
}

// Sort top-level parents by newest first
usort($parents, function($a, $b) {
    return $b['id'] <=> $a['id'];
});
$shareUrl = $canonical_url;

// Check if there is an active printed copy of this book in the shop
$shopProduct = null;
$shopProductQuery = $conn->query("SELECT id, price, sale_price, stock FROM products WHERE post_id = " . (int)$row['id'] . " AND status = 'active' LIMIT 1");
if ($shopProductQuery && $shopProductQuery->num_rows > 0) {
    $shopProduct = $shopProductQuery->fetch_assoc();
}

$featMeta = !empty($row['image']) ? get_media_by_path($conn, $row['image']) : null;
$featAlt  = !empty($featMeta['alt_text']) ? $featMeta['alt_text'] : $row['title'];
$featImg  = !empty($row['image']) ? $site_url . '/' . htmlspecialchars($row['image']) : 'https://placehold.co/300x450/004d5e/fff?text=Novel';
?>

<style>
/* ── Single Post Layout ───────────────────────── */
.sp-wrap {
    max-width: 1200px;
    margin: 0 auto;
    padding: 24px 5px 60px;
}
@media (min-width: 768px) {
    .sp-wrap {
        padding: 24px 16px 60px;
    }
}

/* Hero: image + info side by side */
.sp-hero {
    display: flex;
    gap: 20px;
    align-items: flex-start;
    margin-bottom: 20px;
}
.sp-hero-img {
    flex: 0 0 130px;
    width: 130px;
}
.sp-hero-img img {
    width: 100%;
    border-radius: 10px;
    display: block;
    box-shadow: 0 4px 16px rgba(0,0,0,0.13);
    aspect-ratio: 2/3;
    object-fit: cover;
}
.sp-hero-info {
    flex: 1;
    min-width: 0;
}
.sp-category-badge {
    display: inline-block;
    font-size: 0.7rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    padding: 3px 10px;
    border-radius: 20px;
    background: var(--theme-secondary, #0e7490);
    color: #fff;
    margin-bottom: 10px;
}
.sp-title {
    font-size: 1.35rem;
    font-weight: 800;
    line-height: 1.3;
    color: #1e293b;
    margin: 0 0 10px;
    word-break: break-word;
}
@media (max-width: 500px) {
    .sp-title { font-size: 1.5rem !important; }
    .sp-meta-row { font-size: 0.62rem !important; gap: 3px 6px !important; }
    .sp-meta-row a, .sp-meta-item, .sp-meta-item i { font-size: 0.62rem !important; }
    .sp-category-badge { font-size: 0.58rem !important; padding: 2px 7px !important; margin-bottom: 4px !important; }
}
.sp-meta-row {
    display: flex;
    flex-wrap: wrap;
    gap: 8px 16px;
    font-size: 0.8rem;
    color: #64748b;
    margin-bottom: 6px;
}
.sp-meta-row a {
    color: var(--theme-secondary, #0e7490);
    font-weight: 600;
    text-decoration: none;
}
.sp-meta-row a:hover { text-decoration: underline; }
.sp-meta-item {
    display: flex;
    align-items: center;
    gap: 5px;
}

/* ── Action Buttons ───────────────────────────── */
.sp-actions {
    display: flex;
    flex-direction: row;
    gap: 10px;
    margin-bottom: 20px;
}
.sp-btn {
    flex: 1;
    min-width: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 11px 10px;
    border-radius: 8px;
    font-size: 0.9rem;
    font-weight: 700;
    text-decoration: none;
    transition: opacity 0.15s, transform 0.15s;
    border: none;
    cursor: pointer;
}
@media (max-width: 500px) {
    .sp-btn {
        padding: 11px 6px;
        font-size: 0.82rem;
        gap: 6px;
    }
}
.sp-btn:hover { opacity: 0.88; transform: translateY(-1px); }
.sp-btn-read {
    background: var(--theme-secondary, #0e7490);
    color: #fff;
}
.sp-btn-dl {
    background: #f1f5f9;
    color: #1e293b;
    border: 1.5px solid #e2e8f0;
}
.sp-btn-dl.is-downloading {
    position: relative;
    overflow: hidden;
    pointer-events: none;
    background: #f8fafc !important;
    color: #64748b !important;
    border-color: #e2e8f0 !important;
}
.sp-btn-dl.is-downloading::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    width: var(--download-progress, 0%);
    height: 3px;
    background-color: var(--theme-secondary);
    transition: width 0.1s ease-out;
}

/* ── Specs Table ──────────────────────────────── */
.sp-table-wrap {
    margin-bottom: 24px;
    border-radius: 8px;
    overflow: hidden;
    border: 1px solid #e2e8f0;
}
.sp-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.85rem;
}
.sp-table tr:nth-child(even) { background: #f8fafc; }
.sp-table tr:nth-child(odd)  { background: #fff; }
.sp-table td {
    padding: 9px 14px;
    border-bottom: 1px solid #e2e8f0;
    vertical-align: top;
}
.sp-table tr:last-child td { border-bottom: none; }
.sp-table td:first-child {
    font-weight: 700;
    color: #475569;
    white-space: nowrap;
    width: 36%;
}
.sp-table td:last-child {
    color: #1e293b;
}

/* ── Content Area ─────────────────────────────── */
.sp-content {
    font-size: 0.97rem;
    line-height: 1.85;
    color: #334155;
    margin-bottom: 32px;
    word-break: break-word;
}
.sp-content p  { margin-bottom: 1em; }
.sp-content h2 { font-size: 1.2rem; font-weight: 700; margin: 1.5em 0 0.5em; color: #1e293b; }
.sp-content h3 { font-size: 1.05rem; font-weight: 700; margin: 1.2em 0 0.4em; color: #1e293b; }
.sp-content img { max-width: 100%; border-radius: 8px; }

/* ── Share Row ────────────────────────────────── */
.sp-share {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
    margin-bottom: 28px;
    padding: 12px 0;
    border-top: 1px solid #e2e8f0;
    border-bottom: 1px solid #e2e8f0;
}
.sp-share-label {
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: #94a3b8;
}
.sp-share-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 34px;
    height: 34px;
    border-radius: 50%;
    text-decoration: none;
    font-size: 0.9rem;
    transition: transform 0.15s;
}
.sp-share-btn:hover { transform: scale(1.12); }
.sp-share-fb { background: #1877f2; color: #fff; }
.sp-share-wa { background: #25d366; color: #fff; }
.sp-share-li { background: #0a66c2; color: #fff; }

/* ── Comments ─────────────────────────────────── */
.sp-comments-title {
    font-size: 1.15rem;
    font-weight: 800;
    color: #1e293b;
    margin-bottom: 12px;
    display: flex;
    align-items: center;
    gap: 8px;
}
.sp-comment-thread {
    display: flex;
    flex-direction: column;
    gap: 8px;
    margin-bottom: 12px;
}
.sp-comment-node {
    display: flex;
    gap: 10px;
    position: relative;
}
.sp-comment-avatar-wrap {
    flex-shrink: 0;
    width: 38px;
    height: 38px;
    position: relative;
    z-index: 2;
}
.sp-comment-avatar {
    width: 38px;
    height: 38px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid #fff;
    box-shadow: 0 2px 4px rgba(0,0,0,0.06);
}
.guest-letter-avatar {
    width: 38px;
    height: 38px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-weight: 700;
    font-size: 0.9rem;
    text-transform: uppercase;
    border: 2px solid #fff;
    box-shadow: 0 2px 4px rgba(0,0,0,0.06);
}
.sp-comment-content {
    flex-grow: 1;
    min-width: 0;
    background: #f8fafc;
    border: 1px solid #f1f5f9;
    border-radius: 10px;
    padding: 8px 12px;
    position: relative;
}
.sp-comment-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 2px;
    flex-wrap: wrap;
    gap: 6px;
}
.sp-comment-author-wrap {
    display: flex;
    align-items: center;
    gap: 6px;
}
.sp-comment-author-name {
    font-weight: 700;
    font-size: 0.85rem;
    color: #1e293b;
}
.sp-comment-badge-admin {
    background: #ef4444;
    color: #fff;
    font-size: 0.6rem;
    font-weight: 700;
    padding: 1px 6px;
    border-radius: 20px;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    display: inline-flex;
    align-items: center;
}
.sp-comment-date {
    font-size: 0.7rem;
    color: #94a3b8;
}
.sp-comment-text {
    font-size: 0.85rem;
    color: #334155;
    line-height: 1.5;
    word-wrap: break-word;
    word-break: break-word;
}
.sp-comment-actions {
    display: flex;
    gap: 10px;
    margin-top: 4px;
    font-size: 0.74rem;
}
.sp-comment-action-btn {
    background: none;
    border: none;
    padding: 0;
    color: var(--theme-secondary, #0e7490);
    font-weight: 700;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    transition: opacity 0.15s;
}
.sp-comment-action-btn:hover {
    opacity: 0.8;
}

/* Replies styling */
.sp-comment-replies-list {
    margin-left: 18px;
    padding-left: 18px;
    border-left: 2px solid #f1f5f9;
    display: flex;
    flex-direction: column;
    gap: 8px;
    margin-top: 8px;
}
@media (max-width: 640px) {
    .sp-comment-replies-list {
        margin-left: 10px;
        padding-left: 10px;
    }
}

/* Inline Reply form */
.sp-reply-form-wrap {
    margin-top: 10px;
    background: #fff;
    border: 1.5px solid #cbd5e1;
    border-radius: 10px;
    padding: 12px;
    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
}

/* ── Comment Form ─────────────────────────────── */
.sp-form-title {
    font-size: 1.05rem;
    font-weight: 800;
    color: #1e293b;
    margin: 32px 0 16px;
}
.sp-form-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 12px;
    margin-bottom: 12px;
}
@media (min-width: 640px) {
    .sp-form-grid {
        grid-template-columns: 1fr 1fr;
    }
}
.sp-field label {
    display: block;
    font-size: 0.72rem;
    font-weight: 700;
    color: #475569;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin-bottom: 5px;
}
.sp-field input,
.sp-field textarea {
    width: 100%;
    border: 1.5px solid #cbd5e1;
    border-radius: 10px;
    padding: 10px 12px;
    font-size: 0.88rem;
    color: #1e293b;
    background: #fff;
    outline: none;
    box-sizing: border-box;
    transition: border-color 0.15s;
}
.sp-field input:focus,
.sp-field textarea:focus {
    border-color: var(--theme-secondary, #0e7490);
}
.sp-field textarea { resize: vertical; min-height: 100px; }
.sp-submit {
    margin-top: 12px;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 24px;
    border-radius: 10px;
    background: var(--theme-secondary, #0e7490);
    color: #fff;
    font-size: 0.88rem;
    font-weight: 700;
    border: none;
    cursor: pointer;
    transition: opacity 0.15s;
}
.sp-submit:hover { opacity: 0.88; }

/* ── Alerts ───────────────────────────────────── */
.sp-alert {
    padding: 10px 14px;
    border-radius: 7px;
    font-size: 0.85rem;
    margin-bottom: 12px;
    display: flex;
    align-items: center;
    gap: 8px;
}
.sp-alert-ok   { background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; }
.sp-alert-warn { background: #fffbeb; color: #92400e; border: 1px solid #fde68a; }
.sp-alert-err  { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }

/* ── Responsive ───────────────────────────────── */
@media (max-width: 500px) {
    .sp-hero { gap: 14px; }
    .sp-hero-img { flex: 0 0 100px; width: 100px; }
    .sp-title { font-size: 1.05rem; }
    .sp-form-grid { grid-template-columns: 1fr; }
}
/* ── Writer Info Card ─────────────────────────── */
.sp-author-card {
    display: flex;
    gap: 16px;
    align-items: center;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    padding: 16px;
    border-radius: 8px;
    margin: 24px 0;
}
.sp-author-avatar {
    width: 64px;
    height: 64px;
    border-radius: 50%;
    object-fit: cover;
    background: var(--theme-secondary);
    color: #fff;
    font-weight: 700;
    font-size: 1.5rem;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    text-transform: uppercase;
    box-shadow: 0 0 0 2px #fff, 0 0 0 4px var(--theme-secondary);
}
.sp-author-details {
    flex-grow: 1;
}
.sp-author-name {
    font-weight: 700;
    font-size: 0.95rem;
    color: #1e293b;
    margin-bottom: 4px;
    display: inline-block;
    text-decoration: none;
    transition: color 0.15s;
}
.sp-author-name:hover {
    color: var(--theme-secondary);
}
.sp-author-bio {
    font-size: 0.8rem;
    color: #64748b;
    margin: 0;
    line-height: 1.4;
}
</style>

<div class="sp-wrap">

  <!-- ① HERO: Image + Info -->
  <div class="sp-hero">
    <div class="sp-hero-img">
      <img src="<?php echo $featImg; ?>" alt="<?php echo htmlspecialchars($featAlt); ?>" fetchpriority="high" width="300" height="450">
    </div>
    <div class="sp-hero-info">
      <?php if (!empty($row['category'])): ?>
      <span class="sp-category-badge"><?php echo htmlspecialchars($row['category']); ?></span>
      <?php endif; ?>
      <h1 class="sp-title"><?php echo htmlspecialchars($row['title']); ?></h1>
      <div class="sp-meta-row">
        <?php if (!empty($row['writer_name'])): ?>
        <span class="sp-meta-item">
          <i class="fa-solid fa-user-pen" style="font-size:0.75rem;"></i>
          <a href="<?php echo get_writer_link($row['writer_slug']); ?>"><?php echo htmlspecialchars($row['writer_name']); ?></a>
        </span>
        <?php endif; ?>

        <span class="sp-meta-item">
          <i class="fa-solid fa-comments" style="font-size:0.75rem;"></i>
          <a href="#comments" onclick="document.getElementById('comments').scrollIntoView({behavior:'smooth'}); return false;" style="text-decoration:none; color:inherit; transition:color 0.15s;" onmouseover="this.style.color='var(--theme-secondary)'" onmouseout="this.style.color='inherit'"><?php echo $commentsCount; ?> Comments</a>
        </span>
      </div>
    </div>
  </div>

  <!-- ② ACTION BUTTONS -->
  <?php
  $driveReadId = null;
  if (!empty($row['read_online_url'])) {
      $rurl = $row['read_online_url'];
      if (preg_match('/\/d\/([a-zA-Z0-9_-]{25,})/', $rurl, $m))   $driveReadId = $m[1];
      elseif (preg_match('/[?&]id=([a-zA-Z0-9_-]{25,})/', $rurl, $m)) $driveReadId = $m[1];
  }
  ?>
  <?php if (!empty($row['read_online_url']) || !empty($row['download_url']) || $shopProduct): ?>
  <div class="sp-actions">
    <?php if ($driveReadId): ?>
    <button type="button" class="sp-btn sp-btn-read"
            onclick="openNovelReader('<?php echo (int)$row['id']; ?>','<?php echo htmlspecialchars($driveReadId); ?>')">
      <i class="fa-solid fa-book-open-reader"></i> Read Online
    </button>
    <?php endif; ?>
    
    <?php if (!empty($row['download_url'])): 
        $dlUrl = $row['download_url'];
        $driveDlId = null;
        if (preg_match('/\/d\/([a-zA-Z0-9_-]{25,})/', $dlUrl, $m))   $driveDlId = $m[1];
        elseif (preg_match('/[?&]id=([a-zA-Z0-9_-]{25,})/', $dlUrl, $m)) $driveDlId = $m[1];

        if ($driveDlId):
    ?>
        <button type="button" class="sp-btn sp-btn-dl"
                onclick="downloadDriveNovel('<?php echo (int)$row['id']; ?>','<?php echo htmlspecialchars($driveDlId); ?>','<?php echo htmlspecialchars(addslashes($row['title'])); ?>', this)">
          <i class="fa-solid fa-file-arrow-down"></i> <span class="dl-text">Download</span>
        </button>
    <?php else: ?>
        <a href="<?php echo htmlspecialchars($dlUrl); ?>" target="_blank" rel="nofollow noopener noreferrer"
           class="sp-btn sp-btn-dl">
          <i class="fa-solid fa-file-arrow-down"></i> Download
        </a>
    <?php endif; ?>
    <?php endif; ?>

    <?php if ($shopProduct && (empty($site_settings['shop_coming_soon']) || coming_soon_has_preview_access('shop'))): 
        $sp_price = ($shopProduct['sale_price'] !== null) ? $shopProduct['sale_price'] : $shopProduct['price'];
    ?>
    <a href="<?php echo $site_url; ?>/shop/product.php?id=<?php echo $shopProduct['id']; ?>" class="sp-btn" style="background:#10b981; color:#fff; border:none; text-decoration:none;">
      <i class="fa-solid fa-shopping-cart"></i> Buy Printed Book (Rs. <?php echo number_format($sp_price, 0); ?>)
    </a>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <!-- ③ SPECS TABLE -->
  <div class="sp-table-wrap">
    <table class="sp-table">
      <tr>
        <td>Novel Name</td>
        <td><?php echo htmlspecialchars($row['title']); ?></td>
      </tr>
      <?php if (!empty($row['writer_name'])): ?>
      <tr>
        <td>Writer / Author</td>
        <td><a href="<?php echo get_writer_link($row['writer_slug']); ?>" style="color:var(--theme-secondary);font-weight:600;text-decoration:none;"><?php echo htmlspecialchars($row['writer_name']); ?></a></td>
      </tr>
      <?php endif; ?>
      <?php if (!empty($row['category'])): ?>
      <tr>
        <td>Category</td>
        <td><?php echo htmlspecialchars($row['category']); ?></td>
      </tr>
      <?php endif; ?>
      <?php if (!empty($row['file_size'])): ?>
      <tr>
        <td>File Size</td>
        <td><?php echo htmlspecialchars($row['file_size']); ?></td>
      </tr>
      <?php endif; ?>
      <?php if (!empty($row['file_type'])): ?>
      <tr>
        <td>File Format</td>
        <td><?php echo htmlspecialchars($row['file_type']); ?></td>
      </tr>
      <?php endif; ?>
      <?php if (!empty($row['pages'])): ?>
      <tr>
        <td>Pages</td>
        <td><?php echo htmlspecialchars($row['pages']); ?></td>
      </tr>
      <?php endif; ?>
    </table>
  </div>

  <!-- ④ CONTENT -->
  <?php if (!empty($row['description'])): ?>
  <div class="sp-content">
    <?php echo $row['description']; ?>
  </div>
  <?php endif; ?>

  <!-- ⑤ SHARE -->
  <div class="sp-share">
    <span class="sp-share-label">Share:</span>
    <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode($shareUrl); ?>" target="_blank" rel="noopener" class="sp-share-btn sp-share-fb" title="Facebook"><i class="fa-brands fa-facebook-f"></i></a>
    <a href="https://api.whatsapp.com/send?text=<?php echo urlencode($row['title'] . ' - ' . $shareUrl); ?>" target="_blank" rel="noopener" class="sp-share-btn sp-share-wa" title="WhatsApp"><i class="fa-brands fa-whatsapp"></i></a>
    <a href="https://www.linkedin.com/sharing/share-offsite/?url=<?php echo urlencode($shareUrl); ?>" target="_blank" rel="noopener" class="sp-share-btn sp-share-li" title="LinkedIn"><i class="fa-brands fa-linkedin-in"></i></a>
  </div>

  <!-- Writer Info Card (E-E-A-T SEO Recommendation) -->
  <?php if (!empty($row['writer_name'])): ?>
  <div class="sp-author-card">
      <?php if (!empty($row['writer_profile_pic'])): ?>
          <img src="<?php echo $site_url . '/' . htmlspecialchars(preg_replace('/^\.\.\//', '', $row['writer_profile_pic'])); ?>" alt="<?php echo htmlspecialchars($row['writer_name']); ?>" class="sp-author-avatar">
      <?php else: 
          $writer_initial = strtoupper(substr($row['writer_name'], 0, 1));
      ?>
          <div class="sp-author-avatar"><?php echo $writer_initial; ?></div>
      <?php endif; ?>
      <div class="sp-author-details">
          <a href="<?php echo get_writer_link($row['writer_slug']); ?>" class="sp-author-name">About <?php echo htmlspecialchars($row['writer_name']); ?></a>
          <p class="sp-author-bio">
              <?php 
              if (!empty($row['writer_short_bio'])) {
                  echo htmlspecialchars($row['writer_short_bio']);
              } else {
                  echo "Read all novels and books written by " . htmlspecialchars($row['writer_name']) . " online or download in PDF format on our platform.";
              }
              ?>
          </p>
      </div>
  </div>
  <?php endif; ?>

  <!-- ⑥ COMMENTS -->

    <?php
    if (!function_exists('get_guest_avatar_color')) {
        function get_guest_avatar_color($name) {
            $colors = ['#0284c7', '#0d9488', '#059669', '#7c3aed', '#db2777', '#ea580c', '#e11d48', '#2563eb'];
            $hash = crc32(strtolower(trim($name)));
            return $colors[abs($hash) % count($colors)];
        }
    }
    if (!function_exists('esc_js')) {
        function esc_js($str) {
            return addslashes(htmlspecialchars($str, ENT_QUOTES, 'UTF-8'));
        }
    }
    ?>

    <div id="comments">
      <div class="sp-comments-title">
        <i class="fa-solid fa-comments"></i> Comments (<?php echo $commentsCount; ?>)
      </div>

      <?php if (!empty($parents)): ?>
        <div style="display:flex; flex-direction:column; gap:20px;">
        <?php foreach ($parents as $c): 
            $c_avatar = $c['member_avatar'] ?? '';
            $c_initial = strtoupper(substr($c['name'], 0, 1));
            $c_bg = get_guest_avatar_color($c['name']);
        ?>
            <!-- Parent Comment Block -->
            <div class="sp-comment-thread" id="comment-thread-<?php echo $c['id']; ?>">
              <div class="sp-comment-node" id="comment-<?php echo $c['id']; ?>">
                <div class="sp-comment-avatar-wrap">
                  <?php if ($c_avatar): ?>
                    <img src="<?php echo htmlspecialchars($c_avatar); ?>" class="sp-comment-avatar" alt="<?php echo htmlspecialchars($c['name']); ?>" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                    <div class="guest-letter-avatar" style="background-color:<?php echo $c_bg; ?>; display:none;"><?php echo $c_initial; ?></div>
                  <?php else: ?>
                    <div class="guest-letter-avatar" style="background-color:<?php echo $c_bg; ?>;"><?php echo $c_initial; ?></div>
                  <?php endif; ?>
                </div>
                <div class="sp-comment-content">
                  <div class="sp-comment-header">
                    <div class="sp-comment-author-wrap">
                      <span class="sp-comment-author-name"><?php echo htmlspecialchars($c['name']); ?></span>
                      <?php if ($c['is_admin']): ?>
                        <span class="sp-comment-badge-admin"><i class="fa-solid fa-user-shield" style="font-size:0.55rem;margin-right:2.5px;"></i>Admin</span>
                      <?php endif; ?>
                    </div>
                    <span class="sp-comment-date"><?php echo date('d M Y, h:i A', strtotime($c['created_at'])); ?></span>
                  </div>
                  <div class="sp-comment-text"><?php echo nl2br(htmlspecialchars($c['message'])); ?></div>
                  
                  <div class="sp-comment-actions">
                    <button type="button" class="sp-comment-action-btn" onclick="toggleReplyForm(<?php echo $c['id']; ?>, '<?php echo esc_js($c['name']); ?>')">
                      <i class="fa-solid fa-reply"></i> Reply
                    </button>
                  </div>

                  <!-- Inline Reply Form Container -->
                  <div id="reply-form-container-<?php echo $c['id']; ?>" style="display:none; width:100%;"></div>
                </div>
              </div>

              <!-- Replies List -->
              <?php if (!empty($replies[$c['id']])): ?>
                <div class="sp-comment-replies-list">
                  <?php foreach ($replies[$c['id']] as $r): 
                      $r_avatar = $r['member_avatar'] ?? '';
                      $r_initial = strtoupper(substr($r['name'], 0, 1));
                      $r_bg = get_guest_avatar_color($r['name']);
                  ?>
                    <div class="sp-comment-node" id="comment-<?php echo $r['id']; ?>">
                      <div class="sp-comment-avatar-wrap">
                        <?php if ($r_avatar): ?>
                          <img src="<?php echo htmlspecialchars($r_avatar); ?>" class="sp-comment-avatar" alt="<?php echo htmlspecialchars($r['name']); ?>" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                          <div class="guest-letter-avatar" style="background-color:<?php echo $r_bg; ?>; display:none;"><?php echo $r_initial; ?></div>
                        <?php else: ?>
                          <div class="guest-letter-avatar" style="background-color:<?php echo $r_bg; ?>;"><?php echo $r_initial; ?></div>
                        <?php endif; ?>
                      </div>
                      <div class="sp-comment-content">
                        <div class="sp-comment-header">
                          <div class="sp-comment-author-wrap">
                            <span class="sp-comment-author-name"><?php echo htmlspecialchars($r['name']); ?></span>
                            <?php if ($r['is_admin']): ?>
                              <span class="sp-comment-badge-admin"><i class="fa-solid fa-user-shield" style="font-size:0.55rem;margin-right:2.5px;"></i>Admin</span>
                            <?php endif; ?>
                          </div>
                          <span class="sp-comment-date"><?php echo date('d M Y, h:i A', strtotime($r['created_at'])); ?></span>
                        </div>
                        <div class="sp-comment-text"><?php echo nl2br(htmlspecialchars($r['message'])); ?></div>
                        <div class="sp-comment-actions">
                          <button type="button" class="sp-comment-action-btn" onclick="toggleReplyForm(<?php echo $c['id']; ?>, '<?php echo esc_js($r['name']); ?>')">
                            <i class="fa-solid fa-reply"></i> Reply
                          </button>
                        </div>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>

            </div>
        <?php endforeach; ?>
        </div>
      <?php else: ?>
        <p style="font-size:0.88rem;color:#94a3b8;padding:12px 0;">No comments yet. Be the first to comment!</p>
      <?php endif; ?>

      <!-- Comment Form -->
      <div class="sp-form-title">Leave a Comment</div>
      <?php if (!empty($msg)) echo $msg; ?>
      
      <form action="#comments" method="POST" id="mainCommentForm">
        <?php
        // Check if user is logged in
        $logged_in = isset($_SESSION['member_id']) || isset($_SESSION['admin_logged_in']);
        ?>

        <?php if (!$logged_in): ?>
          <div class="sp-form-grid">
            <div class="sp-field">
              <label>Your Name <span style="color:#ef4444;">*</span></label>
              <input type="text" name="name" required placeholder="e.g. Ali Khan">
            </div>
          </div>
        <?php endif; ?>

        <div class="sp-field">
          <label>Comment <span style="color:#ef4444;">*</span></label>
          <textarea name="message" rows="4" required placeholder="Write your comment here..."></textarea>
        </div>
        <button type="submit" name="submit_comment" class="sp-submit">
          <i class="fa-solid fa-paper-plane"></i> Post Comment
        </button>
      </form>
    </div>

    <script>
    const isCommentLoggedIn = <?php echo $logged_in ? 'true' : 'false'; ?>;
    
    function closeAllReplyForms() {
        document.querySelectorAll('[id^="reply-form-container-"]').forEach(el => {
            el.innerHTML = '';
            el.style.display = 'none';
        });
    }

    function toggleReplyForm(parentId, authorName) {
        const container = document.getElementById('reply-form-container-' + parentId);
        if (!container) return;

        if (container.innerHTML !== '') {
            closeAllReplyForms();
            return;
        }

        closeAllReplyForms();

        container.style.display = 'block';
        container.innerHTML = `
            <div class="sp-reply-form-wrap">
                <form action="#comments" method="POST" style="display:flex; flex-direction:column; gap:12px; margin:0;">
                    <input type="hidden" name="parent_id" value="${parentId}">
                    
                    ${!isCommentLoggedIn ? `
                    <div class="sp-field">
                        <label style="font-size:0.7rem; font-weight:700; color:#475569; text-transform:uppercase;">Your Name <span style="color:#ef4444;">*</span></label>
                        <input type="text" name="name" required placeholder="e.g. Ali Khan" style="padding:8px 12px; font-size:0.85rem; height:38px;">
                    </div>
                    ` : ''}
                    
                    <div class="sp-field">
                        <label style="font-size:0.7rem; font-weight:700; color:#475569; text-transform:uppercase;">Reply to ${authorName} <span style="color:#ef4444;">*</span></label>
                        <textarea name="message" rows="3" required style="padding:8px 12px; font-size:0.85rem; min-height:80px;" placeholder="Write your reply here..."></textarea>
                    </div>
                    
                    <div style="display:flex; gap:10px;">
                        <button type="submit" name="submit_comment" class="sp-submit" style="margin-top:0; padding:8px 20px; font-size:0.82rem;">Post Reply</button>
                        <button type="button" class="sp-submit" onclick="closeAllReplyForms()" style="margin-top:0; padding:8px 20px; font-size:0.82rem; background:#cbd5e1; color:#475569;">Cancel</button>
                    </div>
                </form>
            </div>
        `;
        // Scroll slightly to view
        container.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
    </script>
  </div>

</div><!-- /sp-wrap -->

<!-- ════ NOVEL READER OVERLAY ════ -->
<style>
#nvl-overlay{display:none;position:fixed;inset:0;z-index:99999;background:#111827;flex-direction:column;}
#nvl-topbar{flex:0 0 auto;display:flex;align-items:center;gap:10px;padding:0 12px;height:52px;background:#1f2937;border-bottom:1px solid #374151;}
.nvl-back-btn{display:flex;align-items:center;justify-content:center;width:36px;height:36px;border-radius:8px;background:#374151;border:none;color:#e5e7eb;font-size:0.9rem;cursor:pointer;flex-shrink:0;transition:background 0.15s;}
.nvl-back-btn:hover{background:#4b5563;}
#nvl-overlay-title{flex:1;min-width:0;font-size:0.85rem;font-weight:600;color:#f9fafb;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
#nvl-pageinfo{font-size:0.75rem;color:#9ca3af;flex-shrink:0;white-space:nowrap;}
#nvl-bm-btn{width:36px;height:36px;border-radius:8px;background:none;border:none;color:#9ca3af;font-size:1rem;cursor:pointer;flex-shrink:0;transition:color 0.15s;}
#nvl-bm-btn.active,#nvl-bm-btn:hover{color:#f59e0b;}
/* Scrollable viewport */
#nvl-viewport{flex:1 1 auto;overflow:auto;background:#1a1a2e;position:relative;-webkit-overflow-scrolling:touch;touch-action:pan-x pan-y;overscroll-behavior:contain;}
#nvl-zoom-shell{width:100%;position:relative;}
#nvl-pages{display:block;position:relative;width:100%;transform-origin:top center;will-change:transform;padding:0;margin:0;}
.nvl-pg{position:absolute;left:50%;transform:translateX(-50%);background:#fff;border-radius:3px;box-shadow:0 4px 18px rgba(0,0,0,0.5);min-height:80px;display:flex;align-items:center;justify-content:center;}
.nvl-pg canvas{display:block;width:100%;height:auto;-webkit-user-select:none;user-select:none;pointer-events:none;}
.nvl-pg-lbl{position:absolute;bottom:5px;right:7px;font-size:0.62rem;color:rgba(0,0,0,0.3);pointer-events:none;user-select:none;}
.nvl-pg-spin{width:28px;height:28px;border:2px solid #374151;border-top-color:var(--theme-secondary,#0e7490);border-radius:50%;animation:nvlsp 0.7s linear infinite;}
@keyframes nvlsp{to{transform:rotate(360deg);}}
/* Loader / Error */
#nvl-loader,#nvl-error{position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:14px;background:#1a1a2e;z-index:10;text-align:center;padding:20px;}
#nvl-error{display:none;}
#nvl-error i{font-size:2.2rem;color:#ef4444;}
#nvl-error h3{font-size:0.95rem;color:#f9fafb;}
#nvl-error p{font-size:0.8rem;color:#6b7280;max-width:280px;}
.nvl-spinner{width:42px;height:42px;border:3px solid #374151;border-top-color:var(--theme-secondary,#0e7490);border-radius:50%;animation:nvlsp 0.8s linear infinite;}
#nvl-loader p{font-size:0.82rem;color:#6b7280;}
/* Bottom bar */
#nvl-bottombar{flex:0 0 auto;display:flex;align-items:center;gap:8px;padding:0 10px;height:50px;background:#1f2937;border-top:1px solid #374151;}
#nvl-slider-wrap{flex:1;display:flex;align-items:center;}
#nvl-slider{flex:1;-webkit-appearance:none;height:4px;border-radius:4px;background:#374151;outline:none;cursor:pointer;}
#nvl-slider::-webkit-slider-thumb{-webkit-appearance:none;width:18px;height:18px;border-radius:50%;background:var(--theme-secondary,#0e7490);cursor:pointer;}
#nvl-slider::-moz-range-thumb{width:18px;height:18px;border-radius:50%;background:var(--theme-secondary,#0e7490);cursor:pointer;border:none;}
.nvl-zoom-btn{width:32px;height:32px;border-radius:6px;background:#374151;border:none;color:#e5e7eb;font-size:0.78rem;cursor:pointer;flex-shrink:0;transition:background 0.15s;display:flex;align-items:center;justify-content:center;}
.nvl-zoom-btn:hover{background:#4b5563;}
#nvl-zoom-fit{width:auto;padding:0 8px;font-size:0.68rem;}
/* Toasts */
#nvl-cnt-toast{display:none;position:fixed;bottom:58px;left:50%;transform:translateX(-50%);background:#1f2937;border:1px solid var(--theme-secondary,#0e7490);border-radius:12px;padding:12px 16px;z-index:100001;box-shadow:0 8px 24px rgba(0,0,0,0.6);min-width:260px;max-width:90vw;text-align:center;}
#nvl-cnt-toast p{font-size:0.8rem;color:#d1d5db;margin-bottom:10px;}
.nvl-toast-btns{display:flex;gap:8px;justify-content:center;}
.nvl-tbtn{padding:6px 14px;border-radius:6px;font-size:0.78rem;font-weight:600;border:none;cursor:pointer;}
.nvl-tbtn-yes{background:var(--theme-secondary,#0e7490);color:#fff;}
.nvl-tbtn-no{background:#374151;color:#d1d5db;}
#nvl-bm-toast{position:fixed;bottom:58px;left:50%;transform:translateX(-50%);padding:8px 18px;background:#111827;border:1px solid #374151;border-radius:20px;font-size:0.78rem;color:#d1d5db;z-index:100001;opacity:0;transition:opacity 0.3s;pointer-events:none;white-space:nowrap;}
@media(max-width:500px){
  .nvl-zoom-btn{width:30px;height:30px;font-size:0.72rem;}
  #nvl-zoom-fit{padding:0 6px;font-size:0.62rem;}
}
</style>

<div id="nvl-overlay">
  <header id="nvl-topbar">
    <button class="nvl-back-btn" onclick="closeNovelReader()" title="Close"><i class="fa-solid fa-xmark"></i></button>
    <div id="nvl-overlay-title"><?php echo htmlspecialchars($row['title']); ?></div>
    <div id="nvl-pageinfo">— / —</div>
    <button id="nvl-bm-btn" title="Bookmark"><i class="fa-regular fa-bookmark"></i></button>
  </header>
  <main id="nvl-viewport">
    <div id="nvl-loader"><div class="nvl-spinner"></div><p id="nvl-load-pct">Loading…</p></div>
    <div id="nvl-error">
      <i class="fa-solid fa-triangle-exclamation"></i>
      <h3>Could not load novel</h3>
      <p>Make sure the Google Drive file is publicly shared (Anyone with link → Viewer).</p>
    </div>
    <div id="nvl-zoom-shell"><div id="nvl-pages"></div></div>
  </main>
  <footer id="nvl-bottombar">
    <div id="nvl-slider-wrap"><input type="range" id="nvl-slider" min="1" max="1" value="1" step="1" title="Jump to page"></div>
    <button class="nvl-zoom-btn" id="nvl-zoom-out" title="Zoom Out"><i class="fa-solid fa-minus"></i></button>
    <button class="nvl-zoom-btn" id="nvl-zoom-fit" title="Fit Width">Fit</button>
    <button class="nvl-zoom-btn" id="nvl-zoom-in"  title="Zoom In"><i class="fa-solid fa-plus"></i></button>
  </footer>
</div>
<div id="nvl-cnt-toast">
  <p>Continue from page <strong id="nvl-saved-pg">1</strong>?</p>
  <div class="nvl-toast-btns">
    <button class="nvl-tbtn nvl-tbtn-yes" onclick="nvlContinue()">Yes, Continue</button>
    <button class="nvl-tbtn nvl-tbtn-no"  onclick="nvlDismiss()">Start Over</button>
  </div>
</div>
<div id="nvl-bm-toast"></div>

<?php if (!empty($driveReadId)): ?>
<link rel="preload" as="script" href="<?php echo rtrim($base_path,'/'); ?>/vendor/pdfjs/pdf.min.js">
<link rel="preload" as="script" href="<?php echo rtrim($base_path,'/'); ?>/vendor/pdfjs/pdf.worker.min.js">
<script src="<?php echo rtrim($base_path,'/'); ?>/vendor/pdfjs/pdf.min.js" defer></script>
<?php endif; ?>
<script>
(function(){
'use strict';

function initReader() {
    if (typeof pdfjsLib === 'undefined') {
        setTimeout(initReader, 50);
        return;
    }

    pdfjsLib.GlobalWorkerOptions.workerSrc =
        '<?php echo rtrim($base_path,"/"); ?>/vendor/pdfjs/pdf.worker.min.js';

    const PROXY = '<?php echo rtrim($base_path,"/"); ?>/proxy.php';
    const PAGE_GAP = 10;
    const VIRTUAL_RADIUS = 4; // pages above/below viewport center (max ~9 slots in DOM)
    const MAX_DPR = 1.5;

    let PDF = null, pdfLoadTask = null, totalPgs = 0, viewZoom = 1, activePid = null, activeDriveId = null;
    let basePageW = 0, basePageH = 0;
    const rendered = new Set(), rendering = new Set();
    const mountedPages = new Map();
    let renderQueue = Promise.resolve();
    let virtualRaf = 0;

    const overlay   = document.getElementById('nvl-overlay');
    const vp        = document.getElementById('nvl-viewport');
    const pagesWrap = document.getElementById('nvl-pages');
    const zoomShell = document.getElementById('nvl-zoom-shell');
    const loader    = document.getElementById('nvl-loader');
    const errBox    = document.getElementById('nvl-error');
    const pageInfo  = document.getElementById('nvl-pageinfo');
    const slider    = document.getElementById('nvl-slider');
    const bmBtn     = document.getElementById('nvl-bm-btn');
    const cntToast  = document.getElementById('nvl-cnt-toast');
    const bmToast   = document.getElementById('nvl-bm-toast');
    const pctEl     = document.getElementById('nvl-load-pct');

    function pdfUrl(driveId) {
        return PROXY + '?id=' + encodeURIComponent(driveId);
    }

    function pageTop(num) {
        return 14 + (num - 1) * (basePageH + PAGE_GAP);
    }

    function contentHeight() {
        if (!basePageH || !totalPgs) return 0;
        return pageTop(totalPgs + 1) + 32;
    }

    function syncZoomShell() {
        if (!zoomShell) return;
        const h = contentHeight();
        zoomShell.style.height = h ? Math.ceil(h * (viewZoom || 1)) + 'px' : '';
    }

    function currentPageFromScroll() {
        if (!basePageH || !totalPgs) return 1;
        const z = viewZoom || 1;
        const mid = (vp.scrollTop + vp.clientHeight / 2) / z;
        return Math.max(1, Math.min(totalPgs, Math.floor(mid / (basePageH + PAGE_GAP)) + 1));
    }

    function scrollToPage(num) {
        if (!totalPgs || !basePageH) return;
        num = Math.max(1, Math.min(totalPgs, num | 0));
        const z = viewZoom || 1;
        vp.scrollTo({ top: Math.max(0, pageTop(num) * z - 8), behavior: 'smooth' });
    }

    function setLoadText(t) {
        if (pctEl) pctEl.textContent = t;
    }

    /* ══════════════════════════════════════════
       OPEN READER — stream via byte-range, no full download
       ══════════════════════════════════════════ */
    window.openNovelReader = async function(pid, driveId) {
        if (activePid === pid && PDF) {
            overlay.style.display = 'flex';
            document.body.style.overflow = 'hidden';
            vp.removeEventListener('scroll', onScrollVirtual);
            vp.addEventListener('scroll', onScrollVirtual, {passive:true});
            scheduleVirtualUpdate();
            return;
        }

        if (PDF) {
            try { PDF.destroy(); } catch(e) {}
            PDF = null;
        }
        if (pdfLoadTask) {
            try { pdfLoadTask.destroy(); } catch(e) {}
            pdfLoadTask = null;
        }

        activePid = pid;
        activeDriveId = driveId;
        overlay.style.display = 'flex';
        document.body.style.overflow = 'hidden';
        pagesWrap.innerHTML = '';
        mountedPages.clear();
        rendered.clear();
        rendering.clear();
        renderQueue = Promise.resolve();
        loader.style.display = 'flex';
        errBox.style.display = 'none';
        pageInfo.textContent = '— / —';
        setLoadText('Opening reader…');
        totalPgs = 0;
        viewZoom = 1;
        basePageW = 0;
        basePageH = 0;
        applyViewZoom(1, false);
        isPinching = false;

        try {
            await initPDF(pdfUrl(driveId), pid);
            warmCacheInBackground(driveId);
        } catch(e) {
            console.error(e);
            loader.style.display = 'none';
            errBox.style.display = 'flex';
        }
    };

  window.closeNovelReader = function() {
        overlay.style.display = 'none';
        document.body.style.overflow = '';
        vp.removeEventListener('scroll', onScrollVirtual);
        cntToast.style.display = 'none';
        if (virtualRaf) { cancelAnimationFrame(virtualRaf); virtualRaf = 0; }
        if (totalPgs > 150 && PDF) {
            try { PDF.destroy(); } catch(e) {}
            PDF = null;
            activePid = null;
            mountedPages.clear();
            rendered.clear();
            rendering.clear();
            pagesWrap.innerHTML = '';
            pagesWrap.style.minHeight = '';
        }
    };

    function warmCacheInBackground(driveId) {
        const run = () => fetch(PROXY + '?warm=1&id=' + encodeURIComponent(driveId), { credentials: 'same-origin' }).catch(() => {});
        if ('requestIdleCallback' in window) requestIdleCallback(run, { timeout: 12000 });
        else setTimeout(run, 4000);
    }

    /* ══════════════════════════════════════════
       INIT PDF — range streaming, page 1 first
       ══════════════════════════════════════════ */
    async function initPDF(url, pid) {
        pdfLoadTask = pdfjsLib.getDocument({
            url: url,
            disableAutoFetch: true,
            disableStream: false,
            rangeChunkSize: 131072,
            isEvalSupported: false,
        });
        pdfLoadTask.onProgress = function(p) {
            if (p.total > 0) setLoadText('Loading… ' + Math.round(p.loaded / p.total * 100) + '%');
            else if (p.loaded > 0) setLoadText('Loading… ' + (p.loaded / 1048576).toFixed(1) + ' MB');
        };

        PDF = await pdfLoadTask.promise;
        totalPgs = PDF.numPages;
        slider.max = totalPgs;
        setLoadText('Rendering page 1…');

        const pg1 = await PDF.getPage(1);
        const raw1 = pg1.getViewport({scale:1});
        const sc1 = fitW(raw1.width);
        const vp1 = pg1.getViewport({scale: sc1});
        basePageW = Math.round(vp1.width);
        basePageH = Math.round(vp1.height);
        viewZoom = 1;
        applyViewZoom(1, false);
        pagesWrap.style.minHeight = contentHeight() + 'px';
        syncZoomShell();

        slider.value = 1;
        pageInfo.textContent = '1 / ' + totalPgs;

        scheduleVirtualUpdate(true);
        const p1el = mountedPages.get(1);
        if (p1el) await renderPg(p1el, 1, pg1);
        loader.style.display = 'none';

        vp.addEventListener('scroll', onScrollVirtual, {passive:true});

        const saved = ls('rdr_page_'+pid);
        if (saved && parseInt(saved) > 1 && parseInt(saved) <= totalPgs) {
            document.getElementById('nvl-saved-pg').textContent = saved;
            cntToast.style.display = 'block';
        }
    }

    /* ══════════════════════════════════════════
       VIRTUAL SCROLL — only ~9 page slots in DOM (not 2000)
       ══════════════════════════════════════════ */
    function scheduleVirtualUpdate(force) {
        if (force) {
            updateVirtualWindow();
            return;
        }
        if (virtualRaf) return;
        virtualRaf = requestAnimationFrame(() => {
            virtualRaf = 0;
            updateVirtualWindow();
        });
    }

    function updateVirtualWindow() {
        if (!PDF || !totalPgs || !basePageH) return;

        const center = currentPageFromScroll();
        const start = Math.max(1, center - VIRTUAL_RADIUS);
        const end = Math.min(totalPgs, center + VIRTUAL_RADIUS);
        const needed = new Set();
        for (let n = start; n <= end; n++) needed.add(n);

        for (const [num, el] of mountedPages) {
            if (!needed.has(num)) {
                pagesWrap.removeChild(el);
                mountedPages.delete(num);
                rendered.delete(num);
                rendering.delete(num);
            }
        }

        for (let n = start; n <= end; n++) {
            if (mountedPages.has(n)) continue;
            const el = document.createElement('div');
            el.className = 'nvl-pg';
            el.dataset.page = n;
            el.style.top = pageTop(n) + 'px';
            el.style.width = basePageW + 'px';
            el.style.height = basePageH + 'px';
            const s = document.createElement('div');
            s.className = 'nvl-pg-spin';
            el.appendChild(s);
            pagesWrap.appendChild(el);
            mountedPages.set(n, el);
            queueRender(el, n);
        }
    }

    function queueRender(el, num) {
        if (!el || rendered.has(num) || rendering.has(num) || el.querySelector('canvas')) return;
        renderQueue = renderQueue.then(() => renderPg(el, num));
    }

    async function renderPg(el, num, pageInst) {
        if (!PDF || !el || rendering.has(num) || rendered.has(num)) return;
        rendering.add(num);
        try {
            const page = pageInst || await PDF.getPage(num);
            if (!mountedPages.has(num) || mountedPages.get(num) !== el) return;

            const raw  = page.getViewport({scale:1});
            const sc   = fitW(raw.width);
            const vprt = page.getViewport({scale: sc});
            const cv   = document.createElement('canvas');
            const dpr  = Math.min(window.devicePixelRatio || 1, MAX_DPR);
            cv.width = Math.round(vprt.width * dpr);
            cv.height = Math.round(vprt.height * dpr);
            cv.style.width = vprt.width + 'px';
            cv.style.height = vprt.height + 'px';
            const ctx = cv.getContext('2d', { alpha: false });
            ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
            await page.render({ canvasContext: ctx, viewport: vprt }).promise;

            if (!mountedPages.has(num) || mountedPages.get(num) !== el) return;
            el.style.width = vprt.width + 'px';
            el.style.height = vprt.height + 'px';
            el.innerHTML = '';
            el.appendChild(cv);
            const lbl = document.createElement('span');
            lbl.className = 'nvl-pg-lbl';
            lbl.textContent = num;
            el.appendChild(lbl);
            rendered.add(num);
            el.dataset.loaded = '1';
        } catch(e) {
            if (e && e.name !== 'RenderingCancelledException') console.error('pg', num, e);
        } finally {
            rendering.delete(num);
        }
    }

    /* ══════════════════════════════════════════
       SCROLL TRACKING
       ══════════════════════════════════════════ */
    let scT;
    function onScrollVirtual() {
        scheduleVirtualUpdate();
        clearTimeout(scT);
        scT = setTimeout(() => {
            const best = currentPageFromScroll();
            if (best !== parseInt(slider.value)) {
                slider.value = best;
                pageInfo.textContent = best + ' / ' + totalPgs;
                try { localStorage.setItem('rdr_page_'+activePid, best); } catch(e){}
                updBm(best);
            }
        }, 80);
    }

    slider.addEventListener('input', function(){
        scrollToPage(parseInt(this.value, 10) || 1);
        scheduleVirtualUpdate(true);
    });

    function applyViewZoom(z, keepCenter) {
        z = Math.max(0.55, Math.min(3.5, z));
        const prev = viewZoom || 1;
        const cx = vp.scrollLeft + vp.clientWidth / 2;
        const cy = vp.scrollTop + vp.clientHeight / 2;
        viewZoom = z;
        pagesWrap.style.transform = z === 1 ? '' : ('scale(' + z + ')');
        syncZoomShell();
        if (keepCenter && prev > 0) {
            const ratio = z / prev;
            vp.scrollLeft = Math.max(0, cx * ratio - vp.clientWidth / 2);
            vp.scrollTop = Math.max(0, cy * ratio - vp.clientHeight / 2);
        }
    }

    document.getElementById('nvl-zoom-out').addEventListener('click', () => applyViewZoom(viewZoom - 0.18, true));
    document.getElementById('nvl-zoom-in').addEventListener('click',  () => applyViewZoom(viewZoom + 0.18, true));
    document.getElementById('nvl-zoom-fit').addEventListener('click', () => applyViewZoom(1, true));

    let pinchStartDist = 0;
    let pinchStartZoom = 1;
    let isPinching = false;
    let pinchRaf = 0;
    let lastTapTime = 0;

    function touchDist(t) {
        const dx = t[0].clientX - t[1].clientX;
        const dy = t[0].clientY - t[1].clientY;
        return Math.hypot(dx, dy);
    }

    vp.addEventListener('touchstart', (e) => {
        if (overlay.style.display === 'none') return;
        if (e.touches.length === 2) {
            isPinching = true;
            pinchStartDist = touchDist(e.touches);
            pinchStartZoom = viewZoom || 1;
            e.preventDefault();
            return;
        }
        if (e.touches.length === 1) {
            const now = Date.now();
            if (now - lastTapTime < 280) {
                e.preventDefault();
                applyViewZoom(viewZoom > 1.05 ? 1 : 1.9, true);
                lastTapTime = 0;
            } else {
                lastTapTime = now;
            }
        }
    }, { passive: false });

    vp.addEventListener('touchmove', (e) => {
        if (!isPinching || e.touches.length !== 2) return;
        e.preventDefault();
        const dist = touchDist(e.touches);
        if (!pinchStartDist) return;
        const next = pinchStartZoom * (dist / pinchStartDist);
        if (pinchRaf) cancelAnimationFrame(pinchRaf);
        pinchRaf = requestAnimationFrame(() => applyViewZoom(next, true));
    }, { passive: false });

    function endPinch() {
        if (!isPinching) return;
        isPinching = false;
        pinchStartDist = 0;
    }
    vp.addEventListener('touchend', endPinch, { passive: true });
    vp.addEventListener('touchcancel', endPinch, { passive: true });

    vp.addEventListener('wheel', (e) => {
        if (overlay.style.display === 'none') return;
        if (!(e.ctrlKey || e.metaKey)) return;
        e.preventDefault();
        applyViewZoom(viewZoom + (e.deltaY > 0 ? -0.12 : 0.12), true);
    }, { passive: false });

    /* KEYBOARD */
    document.addEventListener('keydown', e => {
        if (overlay.style.display === 'none') return;
        const cur = parseInt(slider.value, 10) || 1;
        if (e.key==='ArrowDown'||e.key==='PageDown') {
            const n = Math.min(totalPgs, cur + 1);
            slider.value = n; scrollToPage(n); scheduleVirtualUpdate(true);
        }
        if (e.key==='ArrowUp'||e.key==='PageUp') {
            const n = Math.max(1, cur - 1);
            slider.value = n; scrollToPage(n); scheduleVirtualUpdate(true);
        }
        if (e.key==='Escape') closeNovelReader();
    });

    /* BOOKMARK */
    bmBtn.addEventListener('click', () => {
        const cur = parseInt(slider.value)||1;
        const bm  = parseInt(ls('rdr_bm_'+activePid));
        if (bm===cur) { lsDel('rdr_bm_'+activePid); showBmToast('Bookmark removed'); }
        else          { lsSet('rdr_bm_'+activePid, cur); showBmToast('Page '+cur+' bookmarked ✓'); }
        updBm(cur);
    });
    function updBm(pg) {
        const bm = parseInt(ls('rdr_bm_'+activePid));
        bmBtn.classList.toggle('active', bm===pg);
        bmBtn.querySelector('i').className = bm===pg ? 'fa-solid fa-bookmark' : 'fa-regular fa-bookmark';
    }

    /* CONTINUE */
    window.nvlContinue = function() {
        cntToast.style.display = 'none';
        const pg = parseInt(ls('rdr_page_'+activePid))||1;
        slider.value = pg;
        scrollToPage(pg);
        scheduleVirtualUpdate(true);
    };
    window.nvlDismiss = function() {
        cntToast.style.display = 'none';
        try { localStorage.removeItem('rdr_page_'+activePid); } catch(e){}
    };

    /* HELPERS */
    function fitW(raw) { return (vp.clientWidth - 24) / raw; }
    function showBmToast(m) { bmToast.textContent=m; bmToast.style.opacity='1'; setTimeout(()=>bmToast.style.opacity='0',2200); }
    function ls(k)    { try { return localStorage.getItem(k); }    catch(e){ return null; } }
    function lsSet(k,v){ try { localStorage.setItem(k,v); }        catch(e){} }
    function lsDel(k)  { try { localStorage.removeItem(k); }       catch(e){} }
    /* ══════════════════════════════════════════
       GOOGLE DRIVE NOVEL DOWNLOADER (WITH BORDER PROGRESS UX)
       ══════════════════════════════════════════ */
    window.downloadDriveNovel = async function(pid, driveId, filename, btn) {
        if (btn.classList.contains('is-downloading')) return;
        btn.classList.add('is-downloading');
        const originalHtml = btn.innerHTML;

        btn.style.setProperty('--download-progress', '0%');
        btn.innerHTML = `<i class="fa-solid fa-spinner fa-spin"></i> <span class="dl-text">Preparing...</span>`;

        const updateProgress = (text, pct) => {
            const txt = btn.querySelector('.dl-text');
            if (txt) txt.textContent = text;
            if (pct !== undefined) btn.style.setProperty('--download-progress', pct + '%');
        };

        try {
            updateProgress('Downloading…', 0);
            const resp = await fetch(pdfUrl(driveId));
            if (!resp.ok) throw new Error('Download failed');

            const total = parseInt(resp.headers.get('Content-Length') || '0', 10);
            const reader = resp.body.getReader();
            const chunks = [];
            let got = 0;
            for (;;) {
                const { done, value } = await reader.read();
                if (done) break;
                chunks.push(value);
                got += value.length;
                if (total > 0) updateProgress('Downloading ' + Math.round(got / total * 100) + '%', Math.round(got / total * 100));
                else updateProgress((got / 1048576).toFixed(1) + ' MB…', undefined);
            }
            const merged = new Uint8Array(got);
            let off = 0;
            for (const c of chunks) { merged.set(c, off); off += c.length; }
            triggerSave(merged.buffer, filename);

            btn.style.setProperty('--download-progress', '100%');
            btn.innerHTML = `<i class="fa-solid fa-check"></i> <span class="dl-text">Done!</span>`;
        } catch (e) {
            console.error(e);
            btn.innerHTML = `<i class="fa-solid fa-triangle-exclamation"></i> <span class="dl-text">Failed</span>`;
        }

        setTimeout(() => {
            btn.classList.remove('is-downloading');
            btn.innerHTML = originalHtml;
            btn.style.removeProperty('--download-progress');
        }, 1500);
    };

    function triggerSave(arrayBuffer, filename) {
        const blob = new Blob([arrayBuffer], { type: 'application/pdf' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = filename.endsWith('.pdf') ? filename : filename + '.pdf';
        document.body.appendChild(a);
        a.click();
        setTimeout(() => {
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
        }, 100);
    }

    /* ══════════════════════════════════════════
       Service worker (PDF assets only)
       ══════════════════════════════════════════ */
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('<?php echo rtrim($base_path,"/"); ?>/sw.js').catch(() => {});
    }
}

<?php if (!empty($driveReadId)): ?>
initReader();
<?php endif; ?>

})();
</script>

<?php include 'includes/footer.php'; ?>
<?php include 'includes/cache_end.php'; ?>


