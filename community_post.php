<?php
/**
 * community_post.php — Single post share page (noindex, nofollow).
 */
$meta_robots = 'noindex, nofollow';
require_once 'db.php';
require_once __DIR__ . '/lib/coming_soon.php';
coming_soon_gate('community');

$postId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($postId <= 0) {
    http_response_code(404);
    require '404.php';
    exit;
}

$stmt = $conn->prepare("
    SELECT cp.*, m.name AS author_name, m.username AS author_username, m.profile_pic AS author_avatar, m.bio AS author_bio, c.name AS category_name
    FROM community_posts cp
    JOIN members m ON cp.member_id = m.id
    LEFT JOIN categories c ON cp.category_id = c.id
    WHERE cp.id = ?
");
$stmt->bind_param("i", $postId);
$stmt->execute();
$post = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$post) {
    http_response_code(404);
    require '404.php';
    exit;
}

// Load comments
$stmt = $conn->prepare("
    SELECT cc.comment, cc.created_at, m.name AS author_name, m.username AS author_username, m.profile_pic AS author_avatar
    FROM community_comments cc
    JOIN members m ON cc.member_id = m.id
    WHERE cc.post_id = ?
    ORDER BY cc.id ASC
");
$stmt->bind_param("i", $postId);
$stmt->execute();
$comments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$images = $post['images'] ? json_decode($post['images'], true) : [];
$likesCount = (int)($conn->query("SELECT COUNT(*) AS c FROM community_likes WHERE post_id = $postId")->fetch_assoc()['c']);

$scheme = (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off') ? 'https' : 'http';
$postUrl = $scheme . '://' . $_SERVER['HTTP_HOST'] . $base_path . '/community_post.php?id=' . $postId;
$previewImg = !empty($images) ? $scheme . '://' . $_SERVER['HTTP_HOST'] . $base_path . '/' . $images[0] : '';
$excerpt = substr(strip_tags($post['content']), 0, 160);

// OG tags for WhatsApp/FB share previews (even though noindex, og tags still work)
$custom_meta_title  = htmlspecialchars($post['author_name']) . ' on ' . htmlspecialchars($site_settings['site_name'] ?? 'Community');
$custom_meta_description = $excerpt;
$body_class_extra = 'comm-app-page';

include 'includes/header.php';
?>
<link rel="stylesheet" href="<?php echo $site_url; ?>/css/community.css">

<div class="comm-profile-page">
  <header class="comm-topbar comm-mobile-only">
    <a href="<?php echo $site_url; ?>/community.php" class="comm-icon-btn" rel="nofollow" style="text-decoration:none;"><i class="fa-solid fa-arrow-left"></i></a>
    <h1 style="flex:1;text-align:center;font-size:1rem;">Post</h1>
    <button type="button" class="comm-icon-btn" onclick="sharePost()"><i class="fa-solid fa-share-nodes"></i></button>
  </header>

  <div style="padding:16px;max-width:640px;margin:0 auto;">
    <a href="<?php echo $site_url; ?>/community.php" class="comm-btn comm-btn-ghost comm-btn-sm comm-desktop-only" rel="nofollow" style="margin-bottom:16px;text-decoration:none;display:inline-flex;"><i class="fa-solid fa-arrow-left"></i> Back to feed</a>

    <article class="comm-post" style="border-radius:16px;border:1px solid #e8ecf1;margin-bottom:12px;">
      <div class="comm-post-header">
      <?php
      $post_avatar = $post['author_avatar'] ?? '';
      if ($post_avatar && !preg_match("~^(?:f|ht)tps?://~i", $post_avatar)) {
          $post_avatar = $site_url . '/' . ltrim($post_avatar, '/');
      } elseif (!$post_avatar) {
          $post_avatar = $site_url . '/uploads/community/default_avatar.png';
      }
      ?>
      <img src="<?php echo htmlspecialchars($post_avatar); ?>"
           class="comm-post-avatar" alt="<?php echo htmlspecialchars($post['author_name']); ?>"
           onerror="this.src='<?php echo $site_url; ?>/uploads/community/default_avatar.png'">
      <div class="comm-post-meta">
        <a href="<?php echo $site_url; ?>/community_profile.php?username=<?php echo urlencode($post['author_username']); ?>"
           class="comm-post-name" rel="nofollow"><?php echo htmlspecialchars($post['author_name']); ?></a>
        <div class="comm-post-sub">
          @<?php echo htmlspecialchars($post['author_username']); ?> · <?php echo date('d M Y', strtotime($post['created_at'])); ?>
          <?php if ($post['category_name']): ?><span class="comm-post-badge"><?php echo htmlspecialchars($post['category_name']); ?></span><?php endif; ?>
        </div>
      </div>
      </div>

    <div class="comm-post-body"><?php echo htmlspecialchars($post['content']); ?></div>

    <?php if (!empty($images)): ?>
    <div class="comm-post-images <?php echo count($images) === 1 ? 'single' : 'multi'; ?>">
      <?php foreach ($images as $img): ?>
      <img src="<?php echo $site_url . '/' . htmlspecialchars($img); ?>" alt="Post image" loading="lazy">
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="comm-post-actions" style="border-top:1px solid #f1f5f9;">
      <span class="comm-action" style="cursor:default;"><i class="fa-solid fa-heart"></i> <?php echo $likesCount; ?></span>
      <span class="comm-action" style="cursor:default;"><i class="fa-regular fa-comment"></i> <?php echo count($comments); ?></span>
    </div>

    <div style="display:flex;gap:8px;margin-top:12px;flex-wrap:wrap;">
      <a href="https://wa.me/?text=<?php echo urlencode($excerpt . "\n\n" . $postUrl); ?>" target="_blank" rel="nofollow noopener noreferrer" class="comm-btn comm-btn-primary comm-btn-sm" style="background:#25D366;text-decoration:none;"><i class="fa-brands fa-whatsapp"></i> WhatsApp</a>
      <button type="button" class="comm-btn comm-btn-outline comm-btn-sm" onclick="copyLink()"><i class="fa-solid fa-link"></i> Copy link</button>
    </div>
    </article>

    <div class="comm-panel" style="margin-top:0;">
      <div class="comm-panel-title">Comments (<?php echo count($comments); ?>)</div>
    <?php if (empty($comments)): ?>
    <p style="color:#94a3b8;text-align:center;padding:16px 0;font-size:.88rem;">No comments yet.</p>
    <?php else: ?>
      <?php foreach ($comments as $c): ?>
      <?php
        $c_avatar = $c['author_avatar'] ?? '';
        if ($c_avatar && !preg_match("~^(?:f|ht)tps?://~i", $c_avatar)) {
            $c_avatar = $site_url . '/' . ltrim($c_avatar, '/');
        } elseif (!$c_avatar) {
            $c_avatar = $site_url . '/uploads/community/default_avatar.png';
        }
      ?>
      <div class="comm-comment">
        <img src="<?php echo htmlspecialchars($c_avatar); ?>" class="comm-comment-avatar" alt=""
             onerror="this.src='<?php echo $site_url; ?>/uploads/community/default_avatar.png'">
        <div class="comm-comment-bubble">
          <div class="comm-comment-author"><?php echo htmlspecialchars($c['author_name']); ?></div>
          <div class="comm-comment-text"><?php echo htmlspecialchars($c['comment']); ?></div>
          <div class="comm-comment-time"><?php echo date('d M Y', strtotime($c['created_at'])); ?></div>
        </div>
      </div>
      <?php endforeach; ?>
    <?php endif; ?>

    <div style="margin-top:16px;text-align:center;">
      <a href="<?php echo $site_url; ?>/community.php" class="comm-btn comm-btn-primary" rel="nofollow" style="text-decoration:none;">
        <i class="fa-solid fa-comments"></i> Join discussion
      </a>
    </div>
    </div>
  </div>
</div>

<div class="comm-toast" id="commToast"></div>

<script>
window.copyLink = function() {
  const toast = document.getElementById('commToast');
  navigator.clipboard.writeText(<?php echo json_encode($postUrl); ?>).then(() => {
    toast.textContent = 'Link copied';
    toast.classList.add('show');
    setTimeout(() => toast.classList.remove('show'), 2500);
  }).catch(() => prompt('Copy link:', <?php echo json_encode($postUrl); ?>));
};
window.sharePost = function() {
  const url = <?php echo json_encode($postUrl); ?>;
  if (navigator.share) navigator.share({ title: document.title, url }).catch(() => {});
  else copyLink();
};
</script>
<?php include 'includes/footer.php'; ?>
<?php include 'includes/cache_end.php'; ?>
