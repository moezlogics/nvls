<?php
/**
 * community_profile.php — Author profile (noindex).
 */
$meta_robots = 'noindex, nofollow';
$body_class_extra = 'comm-app-page';
require_once 'db.php';
require_once __DIR__ . '/lib/coming_soon.php';
coming_soon_gate('community');

$username = isset($_GET['username']) ? trim($_GET['username']) : '';
if ($username === '') {
    http_response_code(404);
    require '404.php';
    exit;
}

$stmt = $conn->prepare("SELECT id, name, username, profile_pic, bio, created_at FROM members WHERE username = ? AND status = 'active'");
$stmt->bind_param("s", $username);
$stmt->execute();
$author = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$author) {
    http_response_code(404);
    require '404.php';
    exit;
}

$stmt = $conn->prepare("
    SELECT cp.id, cp.content, cp.images, cp.created_at, c.name AS category_name,
           (SELECT COUNT(*) FROM community_likes WHERE post_id = cp.id) AS likes_count,
           (SELECT COUNT(*) FROM community_comments WHERE post_id = cp.id) AS comments_count
    FROM community_posts cp
    LEFT JOIN categories c ON cp.category_id = c.id
    WHERE cp.member_id = ?
    ORDER BY cp.created_at DESC
    LIMIT 30
");
$stmt->bind_param("i", $author['id']);
$stmt->execute();
$posts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$author_pic = $author['profile_pic'] ?? '';
if ($author_pic && !preg_match("~^(?:f|ht)tps?://~i", $author_pic)) {
    $author_pic = $site_url . '/' . ltrim($author_pic, '/');
} elseif (!$author_pic) {
    $author_pic = $site_url . '/uploads/community/default_avatar.png';
}

$custom_meta_title = htmlspecialchars($author['name']) . "'s Profile";
$custom_meta_description = htmlspecialchars($author['bio'] ?: $author['name'] . ' on ' . ($site_settings['site_name'] ?? 'Community'));

include 'includes/header.php';
?>
<link rel="stylesheet" href="<?php echo $site_url; ?>/css/community.css">

<div class="comm-profile-page">
  <header class="comm-topbar comm-mobile-only">
    <a href="<?php echo $site_url; ?>/community.php" class="comm-icon-btn" rel="nofollow" style="text-decoration:none;"><i class="fa-solid fa-arrow-left"></i></a>
    <h1 style="flex:1;text-align:center;">Profile</h1>
    <div style="width:36px;"></div>
  </header>

  <div class="comm-profile-hero"></div>
  <div class="comm-profile-card-lg">
    <img src="<?php echo htmlspecialchars($author_pic); ?>" alt="" class="big-avatar" onerror="this.src='<?php echo $site_url; ?>/uploads/community/default_avatar.png'">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap;">
      <div>
        <div style="font-weight:800;font-size:1.2rem;color:#0f172a;"><?php echo htmlspecialchars($author['name']); ?></div>
        <div style="font-size:.85rem;color:#94a3b8;">@<?php echo htmlspecialchars($author['username']); ?></div>
      </div>
      <?php if (isset($_SESSION['member_id']) && (int)$_SESSION['member_id'] === (int)$author['id']): ?>
      <button type="button" class="comm-btn comm-btn-ghost comm-btn-sm" onclick="openEditProfileModal()"><i class="fa-solid fa-pen"></i> Edit</button>
      <?php endif; ?>
    </div>
    <?php if ($author['bio']): ?>
    <p style="margin:12px 0 0;font-size:.9rem;color:#475569;line-height:1.6;"><?php echo htmlspecialchars($author['bio']); ?></p>
    <?php endif; ?>
    <div class="comm-stat-row">
      <div><div class="comm-stat-num"><?php echo count($posts); ?></div><div class="comm-stat-label">Posts</div></div>
      <div><div class="comm-stat-num"><?php echo array_sum(array_column($posts, 'likes_count')); ?></div><div class="comm-stat-label">Likes</div></div>
      <div><div class="comm-stat-num"><?php echo date('M Y', strtotime($author['created_at'])); ?></div><div class="comm-stat-label">Joined</div></div>
    </div>
  </div>

  <div style="padding:0 16px;">
    <div class="comm-panel-title" style="padding:0 4px 10px;">Posts</div>
    <?php if (empty($posts)): ?>
    <div class="comm-empty" style="background:#fff;border-radius:14px;border:1px solid #e8ecf1;">
      <i class="fa-regular fa-pen-to-square"></i><p>No posts yet.</p>
    </div>
    <?php else: ?>
      <?php foreach ($posts as $p):
        $pImages = $p['images'] ? json_decode($p['images'], true) : [];
        $postUrl = $site_url . '/community_post.php?id=' . $p['id'];
      ?>
      <a href="<?php echo $postUrl; ?>" class="comm-post" rel="nofollow" style="display:block;text-decoration:none;color:inherit;margin-bottom:8px;border-radius:14px;border:1px solid #e8ecf1;">
        <?php if (!empty($pImages)): ?>
        <img src="<?php echo $site_url . '/' . htmlspecialchars($pImages[0]); ?>" alt="" style="width:100%;max-height:200px;object-fit:cover;border-radius:10px;margin-bottom:10px;" loading="lazy">
        <?php endif; ?>
        <div style="font-size:.92rem;line-height:1.6;color:#334155;display:-webkit-box;-webkit-line-clamp:4;-webkit-box-orient:vertical;overflow:hidden;"><?php echo htmlspecialchars($p['content']); ?></div>
        <div style="display:flex;gap:14px;margin-top:10px;font-size:.78rem;color:#94a3b8;">
          <?php if ($p['category_name']): ?><span class="comm-post-badge"><?php echo htmlspecialchars($p['category_name']); ?></span><?php endif; ?>
          <span><i class="fa-regular fa-heart"></i> <?php echo (int)$p['likes_count']; ?></span>
          <span><i class="fa-regular fa-comment"></i> <?php echo (int)$p['comments_count']; ?></span>
          <span><?php echo date('d M Y', strtotime($p['created_at'])); ?></span>
        </div>
      </a>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<nav class="comm-bottom-nav comm-mobile-only">
  <a href="<?php echo $site_url; ?>/community.php" class="comm-nav-item" rel="nofollow"><i class="fa-solid fa-house"></i> Feed</a>
  <a href="<?php echo $site_url; ?>/community.php" class="comm-nav-item" rel="nofollow"><i class="fa-solid fa-compass"></i> Explore</a>
  <span class="comm-nav-item active"><i class="fa-solid fa-user"></i> Profile</span>
</nav>

<?php include 'includes/footer.php'; ?>
<?php include 'includes/cache_end.php'; ?>
