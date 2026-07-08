<?php
require_once 'includes/cache_start.php';
require_once 'db.php';

$category_slug = isset($_GET['category']) ? $conn->real_escape_string($_GET['category']) : '';
$writer_slug = isset($_GET['writer']) ? $conn->real_escape_string($_GET['writer']) : '';
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';

if ($category_slug === '' && $writer_slug === '' && $search === '') {
    header("Location: " . $base_path . "/");
    exit;
}

$category_id = 0;
$writer_id = 0;

$page_heading = "";
$page_subheading = "";
$custom_page_title = "";

$custom_meta_title = "";
$custom_meta_description = "";

if ($category_slug != '') {
    $catQuery = $conn->query("SELECT id, name, description, image, meta_title, meta_description FROM categories WHERE slug = '$category_slug'");
    if ($catQuery && $catQuery->num_rows > 0) {
        $catData = $catQuery->fetch_assoc();
        $category_id = (int)$catData['id'];
        $page_heading = htmlspecialchars($catData['name']);
        $page_subheading = "Explore all novels under category " . htmlspecialchars($catData['name']);
        $custom_meta_title = !empty($catData['meta_title']) ? $catData['meta_title'] : $catData['name'] . " - " . ($site_settings['site_name'] ?? 'Portal');
        $custom_meta_description = !empty($catData['meta_description']) ? $catData['meta_description'] : $page_subheading;
    } else {
        http_response_code(404);
        require __DIR__ . '/404.php';
        exit;
    }
} elseif ($writer_slug != '') {
    $writerQuery = $conn->query("SELECT id, name, bio, meta_title, meta_description FROM writers WHERE slug = '$writer_slug'");
    if ($writerQuery && $writerQuery->num_rows > 0) {
        $writerData = $writerQuery->fetch_assoc();
        $writer_id = (int)$writerData['id'];
        $page_heading = htmlspecialchars($writerData['name']);
        $page_subheading = "Explore all novels written by " . htmlspecialchars($writerData['name']);
        $custom_meta_title = !empty($writerData['meta_title']) ? $writerData['meta_title'] : $writerData['name'] . " - " . ($site_settings['site_name'] ?? 'Portal');
        $custom_meta_description = !empty($writerData['meta_description']) ? $writerData['meta_description'] : $page_subheading;
    } else {
        http_response_code(404);
        require __DIR__ . '/404.php';
        exit;
    }
} elseif ($search != '') {
    $page_heading = "Search Results";
    $page_subheading = "Showing results matching keyword: \"" . htmlspecialchars($search) . "\"";
    $custom_meta_title = "Search: " . strip_tags($search) . " - " . ($site_settings['site_name'] ?? 'Portal');
    $custom_meta_description = $page_subheading;
    $meta_robots = 'noindex, follow';
}

// Canonical URL setup
$scheme = (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off') ? 'https' : 'http';
if ($category_slug != '' && $category_id > 0) {
    $canonical_url = $scheme . '://' . $_SERVER['HTTP_HOST'] . get_category_link($category_slug);
} elseif ($writer_slug != '' && $writer_id > 0) {
    $canonical_url = $scheme . '://' . $_SERVER['HTTP_HOST'] . get_writer_link($writer_slug);
} else {
    $canonical_url = $scheme . '://' . $_SERVER['HTTP_HOST'] . $base_path . '/search/';
}

$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
if ($page > 1) {
    $meta_robots = 'noindex, follow';
}

include 'includes/header.php';

$limit = 12;
$offset = ($page - 1) * $limit;

// Build query
$whereClause = "WHERE s.status='active'";
if ($search != '') {
    $whereClause .= " AND (s.title LIKE '%$search%' OR s.description LIKE '%$search%')";
}
if ($category_id > 0) {
    $whereClause .= " AND (s.category_id = $category_id OR FIND_IN_SET('$category_id', s.additional_categories))";
}
if ($writer_id > 0) {
    $whereClause .= " AND s.writer_id = $writer_id";
}

// Count total records for pagination
$countQuery = "SELECT COUNT(s.id) as total FROM posts s $whereClause";
$countResult = $conn->query($countQuery);
$totalRecords = $countResult->fetch_assoc()['total'];
$totalPages = ceil($totalRecords / $limit);

// Fetch data
$query = "SELECT s.*, c.name as category_name, w.name as writer_name FROM posts s 
          LEFT JOIN categories c ON s.category_id = c.id 
          LEFT JOIN writers w ON s.writer_id = w.id 
          $whereClause ORDER BY s.id DESC LIMIT $offset, $limit";
$result = $conn->query($query);

// Reusable card renderer
function render_post_card($r) {
    global $site_url, $site_settings;
    $lnk = ($site_settings['permalink_structure'] === 'postname' && !empty($r['slug']))
        ? $site_url . '/' . $r['slug'] . '/'
        : $site_url . '/single.php?id=' . $r['id'];
    $img = !empty($r['image']) ? $site_url . '/'.htmlspecialchars($r['image']) : "https://placehold.co/300x450/004d5e/fff?text=" . urlencode($r['title']);
    $title = htmlspecialchars($r['title']);
    $author = htmlspecialchars($r['writer_name'] ?? 'Unknown Author');
    echo "
    <a href='$lnk' class='flex flex-col group cursor-pointer mb-6 no-underline'>
      <div class='book-cover-wrapper mb-4'>
        <img class='book-cover-img' src='$img' alt='$title'>
      </div>
      <h3 class='font-title-md text-title-md text-slate-800 dark:text-neutral-100 line-clamp-2 group-hover:text-primary transition-colors'>$title</h3>
      <p class='font-body-md text-body-md text-slate-500 dark:text-neutral-400 mt-1'>$author</p>
    </a>";
}

// AJAX Handler for Load More
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    if ($result && $result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            render_post_card($row);
        }
    }
    exit;
}
?>

<div class="py-8 bg-slate-50/50 border-b border-slate-100/80">
    <div class="global-container">
        <h1 class="text-2xl md:text-3xl font-extrabold text-slate-900"><?php echo $page_heading; ?></h1>
        <?php if ($search != ''): ?>
            <p class="text-sm text-slate-500 mt-1">Showing results for: <span class="font-semibold text-slate-800">"<?php echo htmlspecialchars($search); ?>"</span></p>
        <?php endif; ?>
    </div>
</div>

<div class="global-container py-10">

    <!-- Listings -->
    <div id="posts-container" class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-8 gap-4 md:gap-card-gap">
        <?php
        if ($result && $result->num_rows > 0) {
            while($row = $result->fetch_assoc()) {
                render_post_card($row);
            }
        } else {
            echo "<div class='col-span-full rounded-md border border-amber-100 bg-amber-50 px-4 py-6 text-center text-amber-700'>No novels found matching your criteria.</div>";
        }
        ?>
    </div>

    <!-- Load More -->
    <?php if ($totalPages > 1): ?>
    <div class="mt-12 text-center">
        <?php
            $qs = $_GET;
            unset($qs['page'], $qs['ajax']);
            $queryString = http_build_query($qs);
            $queryString = $queryString ? '&' . $queryString : '';
        ?>
        <button id="loadMoreBtn" class="btn-brand" data-page="1" data-query="<?php echo $queryString; ?>" data-total="<?php echo $totalPages; ?>">
            Load More <i class="fa-solid fa-spinner fa-spin hidden" id="loadMoreSpinner"></i>
        </button>
    </div>
    <?php endif; ?>
</div>

<?php 
$has_bottom_content = false;
$bottom_content = '';
if ($category_id > 0 && !empty($catData['description'])) {
    $has_bottom_content = true;
    $bottom_content = $catData['description'];
} elseif ($writer_id > 0 && !empty($writerData['bio'])) {
    $has_bottom_content = true;
    $bottom_content = $writerData['bio'];
}

if ($has_bottom_content): 
?>
<div class="border-t border-slate-100 bg-slate-50/50 py-12 mb-6">
    <div class="global-container">
        <div class="prose prose-slate max-w-none text-slate-600 text-sm md:text-base leading-relaxed bg-white rounded-md p-6 md:p-8 border border-slate-100 shadow-sm">
            <?php echo $bottom_content; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const loadMoreBtn = document.getElementById('loadMoreBtn');
    if(loadMoreBtn) {
        loadMoreBtn.addEventListener('click', function() {
            let currentPage = parseInt(this.getAttribute('data-page'));
            let totalPages = parseInt(this.getAttribute('data-total'));
            let qs = this.getAttribute('data-query');
            let spinner = document.getElementById('loadMoreSpinner');

            if (currentPage >= totalPages) return;
            let nextPage = currentPage + 1;

            this.disabled = true;
            spinner.classList.remove('hidden');

            fetch(window.location.pathname + `?ajax=1&page=${nextPage}${qs}`)
                .then(response => response.text())
                .then(html => {
                    if(html.trim() !== '') {
                        document.getElementById('posts-container').insertAdjacentHTML('beforeend', html);
                        this.setAttribute('data-page', nextPage);
                        if(nextPage >= totalPages) { this.style.display = 'none'; }
                    }
                })
                .catch(error => console.error('Error loading more:', error))
                .finally(() => {
                    this.disabled = false;
                    spinner.classList.add('hidden');
                });
        });
    }
});
</script>

<?php include 'includes/cache_end.php'; ?>
