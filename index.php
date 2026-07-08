<?php
include 'includes/cache_start.php';
include 'db.php';

// ── DB Queries ──────────────────────────────────────────

// Fetch all categories
$catSectionsQuery = $conn->query("
    SELECT id, name, slug, image
    FROM categories
    ORDER BY name ASC
");
$activeCategories = [];
if ($catSectionsQuery && $catSectionsQuery->num_rows > 0) {
    $activeCategories = $catSectionsQuery->fetch_all(MYSQLI_ASSOC);
}

// Fetch posts (only required fields for rendering book cards, reducing DB transmission load)
$postsListQuery = $conn->query("
    SELECT s.id, s.title, s.slug, s.image, s.category_id, s.additional_categories, w.name as writer_name 
    FROM posts s 
    LEFT JOIN writers w ON s.writer_id = w.id 
    WHERE s.status='active' 
    ORDER BY s.id DESC 
    LIMIT 300
");

$recentPosts = [];
$categoryPosts = [];

if (!empty($activeCategories)) {
    foreach ($activeCategories as $cat) {
        $categoryPosts[(int)$cat['id']] = [];
    }
}

if ($postsListQuery && $postsListQuery->num_rows > 0) {
    $counter = 0;
    while ($post = $postsListQuery->fetch_assoc()) {
        // Fill recent uploads (first 8 posts)
        if ($counter < 8) {
            $recentPosts[] = $post;
            $counter++;
        }
        
        // Distribute to primary category
        $primaryCatId = (int)$post['category_id'];
        if (isset($categoryPosts[$primaryCatId]) && count($categoryPosts[$primaryCatId]) < 8) {
            $categoryPosts[$primaryCatId][] = $post;
        }
        
        // Distribute to additional categories
        if (!empty($post['additional_categories'])) {
            $addIds = explode(',', $post['additional_categories']);
            foreach ($addIds as $addId) {
                $addId = (int)trim($addId);
                if (isset($categoryPosts[$addId]) && count($categoryPosts[$addId]) < 8) {
                    $duplicate = false;
                    foreach ($categoryPosts[$addId] as $existingPost) {
                        if ($existingPost['id'] == $post['id']) {
                            $duplicate = true;
                            break;
                        }
                    }
                    if (!$duplicate) {
                        $categoryPosts[$addId][] = $post;
                    }
                }
            }
        }
    }
}

// LCP Image Preload for Homepage
$lcp_image_url = '';
if (!empty($recentPosts)) {
    $firstPost = $recentPosts[0];
    $lcp_image_url = !empty($firstPost['image']) ? $firstPost['image'] : '';
}

$is_homepage = true;
$scheme = (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off') ? 'https' : 'http';
$canonical_url = rtrim($scheme . '://' . $_SERVER['HTTP_HOST'] . $base_path, '/') . '/';
include 'includes/header.php';
?>
<!-- ════════════ MAIN CONTENT ════════════ -->
<!-- Categories Carousel Slider Bar (Full Width Screen Stretch) -->
<?php if (!empty($activeCategories)): ?>
<div style="width:100%;padding:10px 0;position:relative;box-sizing:border-box;margin:0;">
  <div class="global-container relative">
    <style>
      .cat-carousel-container {
        display: flex;
        overflow-x: auto;
        scroll-behavior: smooth;
        scroll-snap-type: x mandatory;
        scrollbar-width: none; /* Firefox */
        -ms-overflow-style: none;  /* IE/Edge */
        gap: 12px;
        padding: 4px 0;
      }
      .cat-carousel-container::-webkit-scrollbar {
        display: none; /* WebKit/Chrome */
      }
      .cat-carousel-item {
        flex: 0 0 82px;
        width: 82px;
        max-width: 82px;
        scroll-snap-align: start;
        display: flex;
        flex-direction: column;
        align-items: center;
        text-decoration: none;
        color: inherit;
        box-sizing: border-box;
      }
      @media (min-width: 1024px) {
        .cat-carousel-item {
          flex: 0 0 96px;
          width: 96px;
          max-width: 96px;
        }
        .cat-carousel-container {
          gap: 18px;
        }
      }
      .cat-icon-wrapper {
        position: relative;
        border-radius: 50%;
        background-color: #f1f5f9;
        flex-shrink: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 0 0 2px #f8fafc, 0 0 0 3.5px var(--theme-secondary);
        width: 68px;
        height: 68px;
        overflow: hidden;
        transition: transform 0.3s;
      }
      @media (min-width: 1024px) {
        .cat-icon-wrapper {
          width: 78px;
          height: 78px;
          box-shadow: 0 0 0 2.5px #f8fafc, 0 0 0 4.5px var(--theme-secondary);
        }
      }
      .cat-carousel-item:hover .cat-icon-wrapper {
        transform: scale(1.05);
      }
      .cat-nav-fade-wrap {
        position: absolute;
        top: 0;
        bottom: 0;
        width: 28px;
        z-index: 10;
        display: none; /* Controlled by JS */
        align-items: center;
        pointer-events: none;
      }
      .cat-nav-fade-left {
        left: 0;
        justify-content: flex-start;
      }
      .cat-nav-fade-right {
        right: 0;
        justify-content: flex-end;
      }
      .cat-nav-fade-btn {
        pointer-events: auto;
        background: none;
        border: none;
        padding: 4px;
        cursor: pointer;
        color: var(--theme-secondary);
        font-size: 1rem;
        line-height: 1;
        display: flex;
        align-items: center;
        transition: transform 0.15s;
        opacity: 0.85;
      }
      .cat-nav-fade-btn:hover {
        opacity: 1;
        transform: scale(1.15);
      }
      .cat-title-text {
        font-size: 0.58rem;
        font-weight: 700;
        color: #1e293b !important; /* Force highly visible slate dark text */
        text-align: center;
        line-clamp: 2;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
        margin-top: 6px;
        max-width: 80px;
        word-break: break-word;
        transition: color 0.2s;
      }
      .cat-carousel-item:hover .cat-title-text {
        color: var(--theme-secondary) !important;
      }
    </style>
    <div class="relative w-full cat-carousel-wrapper">
      <!-- Left Navigation Button & Fade -->
      <div id="cat-btn-left" class="cat-nav-fade-wrap cat-nav-fade-left">
        <button onclick="scrollCategories(-1)" class="cat-nav-fade-btn" aria-label="Scroll left">
          <i class="fa-solid fa-chevron-left text-xs"></i>
        </button>
      </div>
      
      <!-- Scroll Container -->
      <div id="cat-scroll-container" class="cat-carousel-container">
        <?php foreach ($activeCategories as $cat):
            $cat_lnk = get_category_link($cat['slug']);
            $cat_img = !empty($cat['image'])
                ? $site_url . '/' . ltrim(preg_replace('/^\.\.\//', '', $cat['image']), '/')
                : 'https://placehold.co/150x150/004d5e/fff?text=' . urlencode(substr($cat['name'], 0, 1));
      ?>
        <a href="<?php echo $cat_lnk; ?>" class="cat-carousel-item group" style="padding: 4px 0;">
          <div class="cat-icon-wrapper">
            <img src="<?php echo $cat_img; ?>" alt="<?php echo htmlspecialchars($cat['name']); ?>"
                 style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%; display: block;" loading="lazy">
          </div>
          <span class="cat-title-text">
            <?php echo htmlspecialchars($cat['name']); ?>
          </span>
        </a>
        <?php endforeach; ?>
      </div>

      <!-- Right Navigation Button & Fade -->
      <div id="cat-btn-right" class="cat-nav-fade-wrap cat-nav-fade-right">
        <button onclick="scrollCategories(1)" class="cat-nav-fade-btn" aria-label="Scroll right">
          <i class="fa-solid fa-chevron-right text-xs"></i>
        </button>
      </div>
    </div>
  </div>
</div>

<script>
(function() {
    var container = document.getElementById('cat-scroll-container');
    var leftBtn = document.getElementById('cat-btn-left');
    var rightBtn = document.getElementById('cat-btn-right');
    
    function updateCatArrows() {
        if (!container || !leftBtn || !rightBtn) return;
        var scrollLeft = container.scrollLeft;
        var scrollWidth = container.scrollWidth;
        var clientWidth = container.clientWidth;
        
        leftBtn.style.display = scrollLeft <= 5 ? 'none' : 'flex';
        rightBtn.style.display = (scrollLeft + clientWidth >= scrollWidth - 5) ? 'none' : 'flex';
    }
    
    if (container) {
        container.addEventListener('scroll', updateCatArrows);
        window.addEventListener('resize', updateCatArrows);
        // Initial trigger with minor delay to ensure elements are rendered
        setTimeout(updateCatArrows, 100);
    }
    
    window.scrollCategories = function(direction) {
        if (container) {
            var amount = container.clientWidth * 0.7 * direction;
            container.scrollBy({
                left: amount,
                behavior: 'smooth'
            });
            // Recalculate arrow state shortly after smooth scroll starts/stops
            setTimeout(updateCatArrows, 100);
            setTimeout(updateCatArrows, 400);
        }
    };
})();
</script>
<?php endif; ?>

<!-- ════════════ MAIN CONTENT ════════════ -->
<div class="global-container pt-1 pb-6">

<?php
// ── REUSABLE: Book Card Grid Card ─────────────────────────
// ── REUSABLE: Book Card Grid Card ─────────────────────────
function render_book_card($r, $lazy = true) {
    global $site_url, $site_settings;
    $lnk = ($site_settings['permalink_structure'] === 'postname' && !empty($r['slug']))
        ? $site_url . '/' . $r['slug'] . '/'
        : $site_url . '/single.php?id=' . $r['id'];
    $img = !empty($r['image']) ? $site_url . '/'.htmlspecialchars($r['image']) : "https://placehold.co/300x450/004d5e/fff?text=" . urlencode($r['title']);
    $title = htmlspecialchars($r['title']);
    $author = htmlspecialchars($r['writer_name'] ?? 'Unknown Author');
    $lazyAttr = $lazy ? "loading='lazy'" : "fetchpriority='high'";
    echo "
    <a href='$lnk' class='flex flex-col group cursor-pointer mb-6 no-underline'>
      <div class='book-cover-wrapper mb-1.5'>
        <img class='book-cover-img' src='$img' alt='$title' $lazyAttr width='300' height='450'>
      </div>
      <h3 class='font-title-md text-title-md text-slate-800 dark:text-neutral-100 line-clamp-2 group-hover:text-primary transition-colors' style='font-size:1.0rem;'>$title</h3>
      <p class='font-body-md text-body-md text-slate-500 dark:text-neutral-400 mt-0.5'>$author</p>
    </a>";
}
?>

  <?php if (!empty($recentPosts)): ?>
  <div class="flex justify-between items-center mb-3 mt-1">
      <h2 class="font-headline-sm text-headline-sm font-bold text-slate-800 dark:text-white flex items-center gap-2">
          <i class="fa-solid fa-compass text-primary"></i> Recent Uploads
      </h2>
      <a href="<?php echo $site_url; ?>/posts/" class="font-label-lg text-label-lg text-primary hover:text-primary-container font-semibold transition-colors flex items-center gap-0.5">
          View All <i class="fa-solid fa-arrow-right text-xs"></i>
      </a>
  </div>
  <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-8 gap-3 md:gap-card-gap">
      <?php 
      foreach ($recentPosts as $idx => $rowPost) { 
          $lazy = ($idx >= 4); 
          render_book_card($rowPost, $lazy); 
      } 
      ?>
  </div>
  <?php endif; ?>

  <!-- ═══ DYNAMIC CATEGORY SECTIONS ═══ -->
  <?php if (!empty($activeCategories)): ?>
      <?php foreach ($activeCategories as $cat): 
          $catId = (int)$cat['id'];
          $catList = $categoryPosts[$catId] ?? [];
          if (!empty($catList)):
      ?>
          <div class="flex justify-between items-center mb-3 mt-6">
              <h2 class="font-headline-sm text-headline-sm font-bold text-slate-800 dark:text-white flex items-center gap-2">
                  <i class="fa-solid fa-book-open text-primary"></i> <?php echo htmlspecialchars($cat['name']); ?>
              </h2>
              <a href="<?php echo get_category_link($cat['slug']); ?>" class="font-label-lg text-label-lg text-primary hover:text-primary-container font-semibold transition-colors flex items-center gap-0.5">
                  View All <i class="fa-solid fa-arrow-right text-xs"></i>
              </a>
          </div>
          <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-8 gap-4 md:gap-card-gap">
              <?php foreach ($catList as $rowPost) { render_book_card($rowPost); } ?>
          </div>
          <?php endif; ?>
      <?php endforeach; ?>
  <?php else: ?>
      <p class="py-10 text-center text-slate-500">No posts available at the moment.</p>
  <?php endif; ?>

</div><!-- /container -->

<?php include 'includes/footer.php'; ?>
<?php include 'includes/cache_end.php'; ?>
