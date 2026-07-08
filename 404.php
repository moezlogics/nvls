<?php
if (!isset($conn) || !isset($site_settings)) {
    require_once __DIR__ . '/db.php';
}

http_response_code(404);

$siteName = htmlspecialchars($site_settings['site_name'] ?? 'Portal');
$custom_meta_title = 'Page Not Found — ' . ($site_settings['site_name'] ?? 'Portal');
$custom_meta_description = 'The page you requested could not be found. Browse novels, categories, or return home.';
$meta_robots = 'noindex, follow';

$scheme = (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off') ? 'https' : 'http';
$site_url = rtrim($scheme . '://' . $_SERVER['HTTP_HOST'] . ($base_path ?? ''), '/');
$canonical_url = $site_url . '/';

include __DIR__ . '/includes/header.php';
?>

<section class="error-404" aria-labelledby="error-404-title">
    <div class="error-404__inner">
        <p class="error-404__code" aria-hidden="true">404</p>
        <h1 id="error-404-title" class="error-404__title">Page not found</h1>
        <p class="error-404__text">
            This link is broken or the page was removed. Try searching, or head back home.
        </p>
        <form class="error-404__search" action="<?php echo $site_url; ?>/search/" method="GET" role="search">
            <label class="sr-only" for="error404Search">Search novels</label>
            <input id="error404Search" type="search" name="search" placeholder="Search novels..." autocomplete="off" required>
            <button type="submit" aria-label="Search">
                <i class="fa-solid fa-magnifying-glass"></i>
            </button>
        </form>
        <div class="error-404__actions">
            <a href="<?php echo $site_url; ?>/" class="error-404__btn error-404__btn--primary">Home</a>
            <a href="<?php echo $site_url; ?>/categories/" class="error-404__btn error-404__btn--ghost">Categories</a>
        </div>
    </div>
</section>

<style>
.error-404 {
    min-height: 62vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 4rem 1rem;
}
.error-404__inner {
    width: 100%;
    max-width: 420px;
    text-align: center;
}
.error-404__code {
    margin: 0 0 .4rem;
    font-size: clamp(3.5rem, 10vw, 5.5rem);
    font-weight: 800;
    letter-spacing: -.04em;
    line-height: 1;
    color: color-mix(in srgb, var(--theme-primary) 12%, #fff);
    user-select: none;
}
.error-404__title {
    margin: 0 0 .75rem;
    font-size: 1.5rem;
    font-weight: 700;
    color: #0f172a;
    letter-spacing: -.02em;
}
.error-404__text {
    margin: 0 auto 1.5rem;
    max-width: 32ch;
    font-size: .95rem;
    line-height: 1.6;
    color: #64748b;
}
.error-404__search {
    display: flex;
    align-items: stretch;
    gap: 0;
    margin: 0 auto 1.25rem;
    border: 1px solid #e2e8f0;
    border-radius: 999px;
    overflow: hidden;
    background: #fff;
    box-shadow: 0 1px 2px rgba(15, 23, 42, .04);
}
.error-404__search:focus-within {
    border-color: color-mix(in srgb, var(--theme-secondary) 55%, #e2e8f0);
    box-shadow: 0 0 0 3px color-mix(in srgb, var(--theme-secondary) 18%, transparent);
}
.error-404__search input {
    flex: 1;
    border: 0;
    outline: 0;
    background: transparent;
    padding: .85rem 1.1rem;
    font: inherit;
    font-size: .92rem;
    color: #0f172a;
}
.error-404__search button {
    border: 0;
    background: transparent;
    color: #64748b;
    padding: 0 1.1rem;
    cursor: pointer;
}
.error-404__search button:hover { color: var(--theme-secondary); }
.error-404__actions {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: .65rem;
}
.error-404__btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 7.5rem;
    padding: .7rem 1.15rem;
    border-radius: 999px;
    font-size: .875rem;
    font-weight: 600;
    text-decoration: none;
    transition: background .2s ease, color .2s ease, border-color .2s ease;
}
.error-404__btn--primary {
    background: var(--theme-primary);
    color: #fff;
    border: 1px solid var(--theme-primary);
}
.error-404__btn--primary:hover {
    background: var(--theme-hover);
    border-color: var(--theme-hover);
    color: var(--theme-primary);
}
.error-404__btn--ghost {
    background: #fff;
    color: #334155;
    border: 1px solid #e2e8f0;
}
.error-404__btn--ghost:hover {
    border-color: #cbd5e1;
    color: #0f172a;
}
.sr-only {
    position: absolute;
    width: 1px;
    height: 1px;
    padding: 0;
    margin: -1px;
    overflow: hidden;
    clip: rect(0,0,0,0);
    white-space: nowrap;
    border: 0;
}
</style>

<?php include __DIR__ . '/includes/footer.php'; ?>
