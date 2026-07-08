<?php
/**
 * community.php — Main social community feed (mobile-app style).
 */
require_once 'db.php';
require_once __DIR__ . '/lib/coming_soon.php';
coming_soon_gate('community');

$categories = [];
$catResult = $conn->query("SELECT id, name FROM categories ORDER BY name ASC");
if ($catResult) {
    while ($cat = $catResult->fetch_assoc()) {
        $categories[] = $cat;
    }
}

$member = null;
$member_avatar_url = '';
if (isset($_SESSION['member_id'])) {
    $stmt = $conn->prepare("SELECT id, name, username, profile_pic, bio FROM members WHERE id = ? AND status='active'");
    $stmt->bind_param("i", $_SESSION['member_id']);
    $stmt->execute();
    $res = $stmt->get_result();
    $member = $res->fetch_assoc();
    $stmt->close();
    if (!$member) {
        session_unset();
        session_destroy();
    } else {
        $pic = $member['profile_pic'] ?? '';
        if ($pic && !preg_match("~^(?:f|ht)tps?://~i", $pic)) {
            $member_avatar_url = $site_url . '/' . ltrim($pic, '/');
        } elseif ($pic) {
            $member_avatar_url = $pic;
        } else {
            $member_avatar_url = $site_url . '/uploads/community/default_avatar.png';
        }
    }
}

$scheme = (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off') ? 'https' : 'http';
$canonical_url = $scheme . '://' . $_SERVER['HTTP_HOST'] . $base_path . '/community/';
$meta_robots = 'index, follow';
$body_class_extra = 'comm-app-page';
$custom_meta_title = 'Community - ' . ($site_settings['site_name'] ?? 'Portal');
$custom_meta_description = 'Join ' . ($site_settings['site_name'] ?? 'our') . ' social community. Read and share novels, quotes and stories.';
$google_client_id = defined('GOOGLE_CLIENT_ID') ? GOOGLE_CLIENT_ID : '';

include 'includes/header.php';
?>
<link rel="stylesheet" href="<?php echo $site_url; ?>/css/community.css">

<div class="comm-app" id="commApp">
  <!-- Mobile top bar -->
  <header class="comm-topbar comm-mobile-only">
    <h1>Community</h1>
    <div class="comm-topbar-actions">
      <button type="button" class="comm-icon-btn" id="commRefreshBtn" title="Refresh feed"><i class="fa-solid fa-rotate-right"></i></button>
      <?php if ($member): ?>
      <a href="<?php echo $site_url; ?>/community_profile.php?username=<?php echo urlencode($member['username']); ?>" rel="nofollow">
        <img src="<?php echo htmlspecialchars($member_avatar_url); ?>" alt="" class="comm-avatar-btn" onerror="this.src='<?php echo $site_url; ?>/uploads/community/default_avatar.png'">
      </a>
      <?php else: ?>
      <button type="button" class="comm-btn comm-btn-primary comm-btn-sm" onclick="openAuthModal()">Sign in</button>
      <?php endif; ?>
    </div>
  </header>

  <!-- Left sidebar (desktop) -->
  <aside class="comm-sidebar comm-desktop-only">
    <div class="comm-panel comm-profile-card">
      <?php if ($member): ?>
        <img src="<?php echo htmlspecialchars($member_avatar_url); ?>" alt="" class="avatar" onerror="this.src='<?php echo $site_url; ?>/uploads/community/default_avatar.png'">
        <div class="name"><?php echo htmlspecialchars($member['name']); ?></div>
        <div class="handle">@<?php echo htmlspecialchars($member['username']); ?></div>
        <?php if (!empty($member['bio'])): ?><div class="bio"><?php echo htmlspecialchars($member['bio']); ?></div><?php endif; ?>
        <div style="display:flex;flex-direction:column;gap:8px;margin-top:14px;">
          <a href="<?php echo $site_url; ?>/community_profile.php?username=<?php echo urlencode($member['username']); ?>" class="comm-btn comm-btn-primary comm-btn-block" rel="nofollow">My Profile</a>
          <button type="button" class="comm-btn comm-btn-ghost comm-btn-block" onclick="openEditProfileModal()">Edit Profile</button>
          <button type="button" class="comm-btn comm-btn-ghost comm-btn-block" style="color:#dc2626;" onclick="logoutMember()">Sign Out</button>
        </div>
      <?php else: ?>
        <div class="name" style="margin-bottom:6px;">Welcome</div>
        <p style="font-size:.88rem;color:#64748b;line-height:1.5;margin:0 0 14px;">Sign in to post, like, and comment with readers.</p>
        <?php if ($google_client_id): ?>
        <div id="google-signin-sidebar-btn"></div>
        <?php endif; ?>
      <?php endif; ?>
    </div>
    <div class="comm-panel">
      <div class="comm-panel-title">Community Rules</div>
      <ul class="comm-rules">
        <li>Be respectful to others</li>
        <li>Share original or credited content</li>
        <li>No spam or abusive posts</li>
        <li>Tag a category for better reach</li>
      </ul>
    </div>
  </aside>

  <!-- Main feed column -->
  <div class="comm-main">
    <div class="comm-tabs" id="commTabs">
      <button type="button" class="comm-tab active" data-sort="for_you">For You</button>
      <button type="button" class="comm-tab" data-sort="latest">Latest</button>
    </div>

    <div class="comm-chips" id="commChips">
      <button type="button" class="comm-chip active" data-cat="0">All</button>
      <?php foreach ($categories as $cat): ?>
      <button type="button" class="comm-chip" data-cat="<?php echo (int)$cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></button>
      <?php endforeach; ?>
    </div>

    <?php if ($member): ?>
    <div class="comm-composer-wrap comm-desktop-only">
      <button type="button" class="comm-composer-trigger" onclick="openComposeSheet()">
        <img src="<?php echo htmlspecialchars($member_avatar_url); ?>" alt="" onerror="this.src='<?php echo $site_url; ?>/uploads/community/default_avatar.png'">
        <span>Share a quote, excerpt, or thought…</span>
      </button>
    </div>
    <?php else: ?>
    <div class="comm-guest-banner comm-desktop-only">
      <p><strong>Join the conversation</strong><br>Sign in with Google to post, like, and comment.</p>
      <?php if ($google_client_id): ?><div id="google-signin-feed-btn"></div><?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="comm-feed" id="feed"></div>
    <div class="comm-sentinel" id="feedSentinel"></div>
  </div>

  <!-- Right sidebar (desktop) -->
  <aside class="comm-sidebar comm-desktop-only">
    <div class="comm-panel">
      <div class="comm-panel-title">Browse Categories</div>
      <ul class="comm-link-list">
        <?php foreach (array_slice($categories, 0, 12) as $cat): ?>
        <li><a href="<?php echo get_category_link($cat['id']); ?>" rel="nofollow"><?php echo htmlspecialchars($cat['name']); ?> <i class="fa-solid fa-chevron-right" style="font-size:.7rem;opacity:.4;"></i></a></li>
        <?php endforeach; ?>
      </ul>
    </div>
  </aside>
</div>

<?php if ($member): ?>
<button type="button" class="comm-fab comm-mobile-only" onclick="openComposeSheet()" aria-label="Create post"><i class="fa-solid fa-plus"></i></button>
<?php endif; ?>

<nav class="comm-bottom-nav comm-mobile-only" aria-label="Community navigation">
  <button type="button" class="comm-nav-item active" data-nav="feed"><i class="fa-solid fa-house"></i> Feed</button>
  <button type="button" class="comm-nav-item" data-nav="explore"><i class="fa-solid fa-compass"></i> Explore</button>
  <?php if ($member): ?>
  <a href="<?php echo $site_url; ?>/community_profile.php?username=<?php echo urlencode($member['username']); ?>" class="comm-nav-item" rel="nofollow"><i class="fa-solid fa-user"></i> Profile</a>
  <?php else: ?>
  <button type="button" class="comm-nav-item" onclick="openAuthModal()"><i class="fa-solid fa-right-to-bracket"></i> Sign in</button>
  <?php endif; ?>
</nav>

<!-- Compose sheet -->
<div class="comm-overlay" id="composeOverlay" onclick="closeComposeSheet()"></div>
<div class="comm-sheet" id="composeSheet" role="dialog" aria-label="Create post">
  <div class="comm-sheet-header">
    <h3>Create Post</h3>
    <button type="button" class="comm-icon-btn" onclick="closeComposeSheet()"><i class="fa-solid fa-xmark"></i></button>
  </div>
  <form id="postForm" class="comm-sheet-body" enctype="multipart/form-data">
    <textarea id="postContent" name="content" placeholder="What's on your mind? Share a novel excerpt, quote, or story…" required></textarea>
    <div id="imgPreviewGrid" class="comm-img-preview" style="display:none;"></div>
    <div class="comm-sheet-footer">
      <select name="category_id" id="postCategory">
        <option value="">Category (optional)</option>
        <?php foreach ($categories as $cat): ?>
        <option value="<?php echo (int)$cat['id']; ?>"><?php echo htmlspecialchars($cat['name']); ?></option>
        <?php endforeach; ?>
      </select>
      <label class="comm-btn comm-btn-ghost comm-btn-sm" for="postImages" style="cursor:pointer;flex-shrink:0;"><i class="fa-solid fa-image"></i></label>
      <input type="file" name="images[]" id="postImages" multiple accept="image/*" style="display:none;">
      <button type="submit" class="comm-btn comm-btn-primary comm-btn-sm" id="postBtn" style="flex-shrink:0;">Post</button>
    </div>
  </form>
</div>

<!-- Auth modal -->
<div class="comm-modal" id="authModal" onclick="if(event.target===this)closeAuthModal()">
  <div class="comm-modal-card">
    <div style="width:56px;height:56px;border-radius:16px;background:color-mix(in srgb,var(--theme-secondary) 15%,#fff);display:flex;align-items:center;justify-content:center;margin:0 auto 14px;color:var(--theme-secondary);font-size:1.4rem;"><i class="fa-solid fa-users"></i></div>
    <h3>Join the Community</h3>
    <p>Sign in with Google to post, like, comment, and connect with other readers.</p>
    <?php if ($google_client_id): ?>
    <div id="google-signin-modal-btn" style="display:flex;justify-content:center;"></div>
    <?php else: ?>
    <p style="color:#dc2626;font-size:.82rem;">Google sign-in is not configured yet.</p>
    <?php endif; ?>
  </div>
</div>

<div class="comm-lightbox" id="imgLightbox" onclick="closeLightbox()">
  <button type="button" class="comm-lightbox-close" onclick="closeLightbox()"><i class="fa-solid fa-xmark"></i></button>
  <img src="" id="lightboxImg" alt="">
</div>
<div class="comm-toast" id="commToast"></div>

<script>
(function(){
  const BASE = <?php echo json_encode($site_url); ?>;
  const DEFAULT_AVATAR = BASE + '/uploads/community/default_avatar.png';
  const MEMBER = <?php echo $member ? json_encode([
    'id' => (int)$member['id'],
    'name' => $member['name'],
    'username' => $member['username'],
    'avatar' => $member_avatar_url,
  ]) : 'null'; ?>;

  let page = 1, hasMore = true, loading = false;
  let currentSort = 'for_you', currentCat = 0;

  function toast(msg) {
    const el = document.getElementById('commToast');
    el.textContent = msg;
    el.classList.add('show');
    clearTimeout(el._t);
    el._t = setTimeout(() => el.classList.remove('show'), 2600);
  }

  function escHtml(str) {
    if (!str) return '';
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  }

  function timeAgo(dateStr) {
    const diff = Math.floor((Date.now() - new Date(dateStr).getTime()) / 1000);
    if (diff < 60) return 'now';
    if (diff < 3600) return Math.floor(diff / 60) + 'm';
    if (diff < 86400) return Math.floor(diff / 3600) + 'h';
    if (diff < 604800) return Math.floor(diff / 86400) + 'd';
    return new Date(dateStr).toLocaleDateString();
  }

  function skeletons(n) {
    let h = '';
    for (let i = 0; i < n; i++) {
      h += `<div class="comm-skeleton"><div style="display:flex;gap:10px;margin-bottom:12px;"><div class="comm-skel-avatar"></div><div style="flex:1"><div class="comm-skel-line w40"></div><div class="comm-skel-line w60"></div></div></div><div class="comm-skel-line w80"></div><div class="comm-skel-line w60"></div></div>`;
    }
    return h;
  }

  window.openAuthModal = () => document.getElementById('authModal').classList.add('open');
  window.closeAuthModal = () => document.getElementById('authModal').classList.remove('open');
  window.openComposeSheet = () => {
    document.getElementById('composeOverlay').classList.add('open');
    document.getElementById('composeSheet').classList.add('open');
    setTimeout(() => document.getElementById('postContent')?.focus(), 200);
  };
  window.closeComposeSheet = () => {
    document.getElementById('composeOverlay').classList.remove('open');
    document.getElementById('composeSheet').classList.remove('open');
  };
  window.openLightbox = (src) => {
    document.getElementById('lightboxImg').src = src;
    document.getElementById('imgLightbox').classList.add('open');
  };
  window.closeLightbox = () => document.getElementById('imgLightbox').classList.remove('open');

  window.logoutMember = async function() {
    await fetch(BASE + '/community_auth.php?action=logout');
    window.location.reload();
  };

  <?php if ($google_client_id): ?>
  window.handleGoogleLogin = async function(response) {
    const res = await fetch(BASE + '/community_auth.php?action=google_login', {
      method: 'POST', headers: {'Content-Type':'application/json'},
      body: JSON.stringify({ credential: response.credential })
    });
    const data = await res.json();
    if (data.status === 'success') window.location.reload();
    else toast(data.error || 'Login failed');
  };
  <?php endif; ?>

  document.getElementById('postImages')?.addEventListener('change', function() {
    const grid = document.getElementById('imgPreviewGrid');
    grid.innerHTML = '';
    const files = Array.from(this.files).slice(0, 4);
    if (!files.length) { grid.style.display = 'none'; return; }
    grid.style.display = 'flex';
    files.forEach(file => {
      const reader = new FileReader();
      reader.onload = e => {
        const img = document.createElement('img');
        img.src = e.target.result;
        grid.appendChild(img);
      };
      reader.readAsDataURL(file);
    });
  });

  document.getElementById('postForm')?.addEventListener('submit', async function(e) {
    e.preventDefault();
    const btn = document.getElementById('postBtn');
    btn.disabled = true;
    btn.textContent = 'Posting…';
    const res = await fetch(BASE + '/community_api.php?action=create_post', { method: 'POST', body: new FormData(this) });
    const data = await res.json();
    btn.disabled = false;
    btn.textContent = 'Post';
    if (data.status === 'success') {
      this.reset();
      document.getElementById('imgPreviewGrid').innerHTML = '';
      document.getElementById('imgPreviewGrid').style.display = 'none';
      closeComposeSheet();
      toast('Post published');
      resetFeed();
    } else {
      toast(data.error || 'Post blocked by moderation');
    }
  });

  function resetFeed() {
    page = 1; hasMore = true;
    document.getElementById('feed').innerHTML = skeletons(3);
    loadPosts(true);
  }

  async function loadPosts(reset) {
    if (loading || (!hasMore && !reset)) return;
    loading = true;
    if (reset) { page = 1; hasMore = true; }
    const feed = document.getElementById('feed');
    if (page === 1 && !feed.children.length) feed.innerHTML = skeletons(3);

    const url = `${BASE}/community_api.php?action=get_posts&page=${page}&limit=10&sort=${currentSort}&category_id=${currentCat}`;
    try {
      const res = await fetch(url);
      const data = await res.json();
      if (data.status !== 'success') throw new Error(data.error || 'load failed');
      hasMore = data.has_more;
      if (page === 1) feed.innerHTML = '';
      if (!data.posts.length && page === 1) {
        feed.innerHTML = '<div class="comm-empty"><i class="fa-regular fa-comment-dots"></i><p>No posts yet. Be the first to share something!</p></div>';
      } else {
        data.posts.forEach(p => feed.insertAdjacentHTML('beforeend', renderPost(p)));
      }
      page++;
    } catch (e) {
      console.error('Feed load error:', e);
      if (page === 1) feed.innerHTML = '<div class="comm-empty"><p>Could not load feed. Try again.</p></div>';
    }
    loading = false;
  }

  function renderPost(post) {
    const avatar = post.author_avatar || DEFAULT_AVATAR;
    const liked = post.user_liked ? 'liked' : '';
    const heart = post.user_liked ? 'fa-solid fa-heart' : 'fa-regular fa-heart';
    const profileUrl = `${BASE}/community_profile.php?username=${encodeURIComponent(post.author_username)}`;
    const isOwn = MEMBER && post.member_id === MEMBER.id;
    let imagesHtml = '';
    if (post.images && post.images.length) {
      const cls = post.images.length === 1 ? 'single' : 'multi';
      imagesHtml = `<div class="comm-post-images ${cls}">` +
        post.images.map(img => `<img src="${BASE}/${img}" alt="" loading="lazy" onclick="event.stopPropagation();openLightbox('${BASE}/${img}')">`).join('') +
        '</div>';
    }
    const catBadge = post.category_name ? `<span class="comm-post-badge">${escHtml(post.category_name)}</span>` : '';
    const menuHtml = isOwn ? `
      <div class="comm-post-menu">
        <button type="button" class="comm-post-menu-btn" onclick="togglePostMenu(event,${post.id})"><i class="fa-solid fa-ellipsis"></i></button>
        <div class="comm-post-dropdown" id="post-menu-${post.id}">
          <button type="button" onclick="deletePost(${post.id})">Delete post</button>
        </div>
      </div>` : '';

    return `
    <article class="comm-post" data-post-id="${post.id}">
      <div class="comm-post-header">
        <img src="${avatar}" alt="" class="comm-post-avatar" onclick="location.href='${profileUrl}'" onerror="this.src='${DEFAULT_AVATAR}'">
        <div class="comm-post-meta">
          <a href="${profileUrl}" class="comm-post-name" rel="nofollow">${escHtml(post.author_name)}</a>
          <div class="comm-post-sub">@${escHtml(post.author_username)} · ${timeAgo(post.created_at)} ${catBadge}</div>
        </div>
        ${menuHtml}
      </div>
      <div class="comm-post-body">${escHtml(post.content)}</div>
      ${imagesHtml}
      <div class="comm-post-actions">
        <button type="button" class="comm-action ${liked}" onclick="toggleLike(${post.id}, this)">
          <i class="${heart}"></i> <span class="like-count">${post.likes_count}</span>
        </button>
        <button type="button" class="comm-action" onclick="toggleComments(${post.id})">
          <i class="fa-regular fa-comment"></i> <span class="cmt-count">${post.comments_count}</span>
        </button>
        <button type="button" class="comm-action" onclick="sharePost(${post.id})">
          <i class="fa-solid fa-share"></i>
        </button>
      </div>
      <div class="comm-comments" id="comments-${post.id}">
        <div class="comments-list" id="comments-list-${post.id}"></div>
        ${MEMBER ? `
        <div class="comm-comment-form">
          <input type="text" placeholder="Write a comment…" id="cmt-input-${post.id}" onkeydown="if(event.key==='Enter')sendComment(${post.id})">
          <button type="button" class="comm-comment-send" onclick="sendComment(${post.id})"><i class="fa-solid fa-paper-plane"></i></button>
        </div>` : `<p style="text-align:center;font-size:.82rem;color:#94a3b8;padding:8px 0;"><a href="#" onclick="openAuthModal();return false;">Sign in</a> to comment</p>`}
      </div>
    </article>`;
  }

  window.togglePostMenu = function(e, id) {
    e.stopPropagation();
    document.querySelectorAll('.comm-post-dropdown.open').forEach(d => d.classList.remove('open'));
    document.getElementById('post-menu-' + id)?.classList.toggle('open');
  };
  document.addEventListener('click', () => document.querySelectorAll('.comm-post-dropdown.open').forEach(d => d.classList.remove('open')));

  window.deletePost = async function(postId) {
    if (!confirm('Delete this post permanently?')) return;
    const res = await fetch(BASE + '/community_api.php?action=delete_post', {
      method: 'POST', headers: {'Content-Type':'application/json'},
      body: JSON.stringify({ post_id: postId })
    });
    const data = await res.json();
    if (data.status === 'success') {
      document.querySelector(`.comm-post[data-post-id="${postId}"]`)?.remove();
      toast('Post deleted');
    } else toast(data.error || 'Could not delete');
  };

  window.toggleLike = async function(postId, btn) {
    if (!MEMBER) { openAuthModal(); return; }
    const res = await fetch(`${BASE}/community_api.php?action=toggle_like&post_id=${postId}`);
    const data = await res.json();
    if (data.status === 'success') {
      btn.querySelector('.like-count').textContent = data.likes_count;
      const icon = btn.querySelector('i');
      if (data.user_liked) { btn.classList.add('liked'); icon.className = 'fa-solid fa-heart'; }
      else { btn.classList.remove('liked'); icon.className = 'fa-regular fa-heart'; }
    } else toast(data.error || 'Failed');
  };

  window.toggleComments = async function(postId) {
    const sec = document.getElementById('comments-' + postId);
    sec.classList.toggle('open');
    if (sec.classList.contains('open')) await loadComments(postId);
  };

  async function loadComments(postId) {
    const list = document.getElementById('comments-list-' + postId);
    list.innerHTML = '<p style="text-align:center;color:#94a3b8;font-size:.82rem;padding:8px;"><i class="fa-solid fa-spinner fa-spin"></i></p>';
    const res = await fetch(`${BASE}/community_api.php?action=get_comments&post_id=${postId}`);
    const data = await res.json();
    if (data.status !== 'success' || !data.comments.length) {
      list.innerHTML = '<p style="text-align:center;color:#94a3b8;font-size:.82rem;padding:8px;">No comments yet</p>';
      return;
    }
    list.innerHTML = data.comments.map(c => `
      <div class="comm-comment">
        <img src="${c.author_avatar || DEFAULT_AVATAR}" alt="" class="comm-comment-avatar" onerror="this.src='${DEFAULT_AVATAR}'">
        <div class="comm-comment-bubble">
          <div class="comm-comment-author">${escHtml(c.author_name)}</div>
          <div class="comm-comment-text">${escHtml(c.comment)}</div>
          <div class="comm-comment-time">${timeAgo(c.created_at)}</div>
        </div>
      </div>`).join('');
  }

  window.sendComment = async function(postId) {
    if (!MEMBER) { openAuthModal(); return; }
    const input = document.getElementById('cmt-input-' + postId);
    const text = input.value.trim();
    if (!text) return;
    input.disabled = true;
    const res = await fetch(`${BASE}/community_api.php?action=create_comment`, {
      method: 'POST', headers: {'Content-Type':'application/json'},
      body: JSON.stringify({ post_id: postId, comment: text })
    });
    const data = await res.json();
    input.disabled = false;
    if (data.status === 'success') {
      input.value = '';
      await loadComments(postId);
      const cnt = document.querySelector(`.comm-post[data-post-id="${postId}"] .cmt-count`);
      if (cnt) cnt.textContent = parseInt(cnt.textContent, 10) + 1;
    } else toast(data.error || 'Failed to comment');
  };

  window.sharePost = function(postId) {
    const url = `${BASE}/community_post.php?id=${postId}`;
    if (navigator.share) navigator.share({ title: 'Community post', url }).catch(() => {});
    else navigator.clipboard.writeText(url).then(() => toast('Link copied')).catch(() => prompt('Copy link:', url));
  };

  document.querySelectorAll('.comm-tab').forEach(tab => {
    tab.addEventListener('click', () => {
      document.querySelectorAll('.comm-tab').forEach(t => t.classList.remove('active'));
      tab.classList.add('active');
      currentSort = tab.dataset.sort;
      resetFeed();
    });
  });

  document.querySelectorAll('.comm-chip').forEach(chip => {
    chip.addEventListener('click', () => {
      document.querySelectorAll('.comm-chip').forEach(c => c.classList.remove('active'));
      chip.classList.add('active');
      currentCat = parseInt(chip.dataset.cat, 10) || 0;
      resetFeed();
    });
  });

  document.getElementById('commRefreshBtn')?.addEventListener('click', () => { resetFeed(); toast('Feed refreshed'); });

  document.querySelectorAll('.comm-nav-item[data-nav]').forEach(item => {
    item.addEventListener('click', () => {
      const nav = item.dataset.nav;
      document.querySelectorAll('.comm-nav-item').forEach(n => n.classList.remove('active'));
      item.classList.add('active');
      if (nav === 'explore') document.getElementById('commChips')?.scrollIntoView({ behavior: 'smooth' });
      else window.scrollTo({ top: 0, behavior: 'smooth' });
    });
  });

  const sentinel = document.getElementById('feedSentinel');
  if ('IntersectionObserver' in window && sentinel) {
    new IntersectionObserver(entries => {
      if (entries[0].isIntersecting && hasMore && !loading) loadPosts(false);
    }, { rootMargin: '200px' }).observe(sentinel);
  }

  resetFeed();

  <?php if ($google_client_id): ?>
  window.addEventListener('load', function() {
    if (typeof google === 'undefined' || !google.accounts) return;
    const clientId = <?php echo json_encode($google_client_id); ?>;
    const renderBtn = (id) => {
      const el = document.getElementById(id);
      if (!el) return;
      google.accounts.id.renderButton(el, { type: 'standard', shape: 'pill', theme: 'outline', text: 'continue_with', size: 'large' });
    };
    google.accounts.id.initialize({ client_id: clientId, callback: window.handleGoogleLogin });
    ['google-signin-sidebar-btn', 'google-signin-modal-btn', 'google-signin-feed-btn'].forEach(renderBtn);
  });
  <?php endif; ?>
})();
</script>

<?php if ($google_client_id): ?>
<script src="https://accounts.google.com/gsi/client" async defer></script>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
<?php include 'includes/cache_end.php'; ?>
