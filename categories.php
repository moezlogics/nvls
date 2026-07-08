<?php
include 'includes/cache_start.php';
include 'db.php';

$scheme = (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off') ? 'https' : 'http';
$site_url_base = rtrim($scheme . '://' . $_SERVER['HTTP_HOST'] . $base_path, '/');
$custom_meta_title = 'Novel Categories - ' . ($site_settings['site_name'] ?? 'Kitab Nagri');
$custom_meta_description = 'Browse and read novels, stories, and series by category on ' . ($site_settings['site_name'] ?? 'our platform') . '.';
$canonical_url = $site_url_base . '/categories/';

include 'includes/header.php';
?>

<div class="border-b border-slate-100 bg-white/40 backdrop-blur-md py-12">
    <div class="global-container text-center">
        <h1 class="text-2xl md:text-3xl font-extrabold text-slate-900">Novel Categories</h1>
        <p class="mt-2 text-slate-500">Browse and read novels, stories, and series by category.</p>
    </div>
</div>

<div class="global-container py-14">
    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
        <?php
        $categories_query = $conn->query("SELECT * FROM categories ORDER BY name ASC");
        if ($categories_query && $categories_query->num_rows > 0) {
            while ($c = $categories_query->fetch_assoc()) {
                $catLink = get_category_link($c['slug']);
        ?>
        <div class="hover-lift flex flex-col items-center rounded-md border border-slate-100 bg-white p-8 text-center shadow-sm">
            <span class="mb-4 flex h-16 w-16 items-center justify-center rounded-md text-2xl text-white shadow"
                  style="background:linear-gradient(135deg,var(--theme-primary),var(--theme-secondary));">
                <i class="fa-solid fa-folder"></i>
            </span>
            <h2 class="text-lg font-bold text-slate-900"><?php echo htmlspecialchars($c['name']); ?></h2>
            <p class="mb-5 mt-2 text-sm text-slate-500">Explore all novels categorized under <?php echo htmlspecialchars($c['name']); ?>.</p>
            <a href="<?php echo $catLink; ?>" class="mt-auto inline-block rounded-md border-2 px-5 py-2 text-sm font-bold transition-colors hover:text-white"
               style="border-color:var(--theme-primary);color:var(--theme-primary);"
               onmouseover="this.style.background='var(--theme-primary)';this.style.color='#fff';"
               onmouseout="this.style.background='transparent';this.style.color='var(--theme-primary)';">
               View Novels
            </a>
        </div>
        <?php 
            }
        } else {
            echo "<div class='col-span-full py-8 text-center text-slate-500'>No categories found.</div>";
        }
        ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
<?php include 'includes/cache_end.php'; ?>
