<?php
/**
 * reader.php — Custom in-site PDF reader using PDF.js
 * Renders Google Drive novels in your own layout.
 * Features: Continue Reading, Bookmark, Zoom, Mobile swipe
 *
 * Usage: reader.php?id=POST_ID
 */
require_once 'db.php';

$post_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$post_id) { header("Location: $base_path/"); exit; }

$status_cond = isset($_SESSION['admin_logged_in']) ? '' : "AND p.status='active'";
$res = $conn->query("
    SELECT p.id, p.title, p.slug, p.read_online_url, p.image,
           c.name AS category, w.name AS writer_name, w.slug AS writer_slug
    FROM posts p
    LEFT JOIN categories c ON p.category_id = c.id
    LEFT JOIN writers w    ON p.writer_id    = w.id
    WHERE p.id = $post_id $status_cond
    LIMIT 1
");
if (!$res || $res->num_rows === 0) { http_response_code(404); exit('<h2>Novel not found.</h2>'); }
$novel = $res->fetch_assoc();

// ── Extract Google Drive File ID ───────────────────────────────────────
function extract_drive_id($url) {
    if (preg_match('/\/d\/([a-zA-Z0-9_-]{25,})/', $url, $m))     return $m[1];
    if (preg_match('/[?&]id=([a-zA-Z0-9_-]{25,})/', $url, $m))   return $m[1];
    if (preg_match('/open\?id=([a-zA-Z0-9_-]{25,})/', $url, $m)) return $m[1];
    return null;
}

$driveId = extract_drive_id($novel['read_online_url'] ?? '');
if (!$driveId) {
    // No Drive URL — redirect to the original URL
    $fallback = !empty($novel['read_online_url']) ? $novel['read_online_url'] : "$base_path/";
    header("Location: $fallback");
    exit;
}

$proxyUrl    = $base_path . '/proxy.php?id=' . rawurlencode($driveId);
$novelTitle  = htmlspecialchars($novel['title']);
$backUrl     = get_post_link($novel['id'], $novel['slug'] ?? '');
$coverImg    = !empty($novel['image']) ? $base_path . '/' . htmlspecialchars($novel['image']) : '';
$siteName    = htmlspecialchars($site_settings['site_name'] ?? 'Reader');
$themeColor  = htmlspecialchars($site_settings['theme_secondary'] ?? '#0e7490');
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title><?php echo $novelTitle; ?> — <?php echo $siteName; ?></title>
<meta name="robots" content="noindex">
<link rel="stylesheet" href="<?php echo rtrim($base_path,'/'); ?>/vendor/fontawesome/css/all.min.css" media="print" onload="this.media='all'">
<link rel="preload" as="script" href="<?php echo rtrim($base_path,'/'); ?>/vendor/pdfjs/pdf.min.js">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html, body {
    height: 100%;
    overflow: hidden;
    font-family: "Outfit", ui-sans-serif, system-ui, sans-serif;
    background: #111827;
    color: #e5e7eb;
    -webkit-tap-highlight-color: transparent;
}

/* ═══════════════════════════════════════════════════════
   LAYOUT SHELL
══════════════════════════════════════════════════════ */
#rdr-shell {
    display: flex;
    flex-direction: column;
    height: 100vh;
    height: 100dvh;
}

/* ── Top Bar ──────────────────────────────────────────── */
#rdr-topbar {
    flex: 0 0 auto;
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 0 12px;
    height: 52px;
    background: #1f2937;
    border-bottom: 1px solid #374151;
    z-index: 20;
}
.rdr-back-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    border-radius: 8px;
    background: #374151;
    color: #e5e7eb;
    text-decoration: none;
    font-size: 0.9rem;
    transition: background 0.15s;
    flex-shrink: 0;
}
.rdr-back-btn:hover { background: #4b5563; }
#rdr-title {
    flex: 1;
    min-width: 0;
    font-size: 0.85rem;
    font-weight: 600;
    color: #f9fafb;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
#rdr-pageinfo {
    font-size: 0.75rem;
    color: #9ca3af;
    flex-shrink: 0;
    min-width: 60px;
    text-align: right;
}
#rdr-bm-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    border-radius: 8px;
    background: none;
    border: none;
    color: #9ca3af;
    font-size: 1rem;
    cursor: pointer;
    transition: color 0.15s;
    flex-shrink: 0;
}
#rdr-bm-btn.active { color: #f59e0b; }
#rdr-bm-btn:hover  { color: #f59e0b; }

/* ── Reading Canvas Area ──────────────────────────────── */
#rdr-viewport {
    flex: 1 1 auto;
    overflow-y: auto;
    overflow-x: hidden;
    display: flex;
    align-items: flex-start;
    justify-content: center;
    background: #111827;
    position: relative;
    -webkit-overflow-scrolling: touch;
}
#rdr-canvas-wrap {
    display: flex;
    justify-content: center;
    align-items: flex-start;
    padding: 16px 8px;
    min-height: 100%;
    width: 100%;
}
#rdr-canvas {
    display: block;
    max-width: 100%;
    border-radius: 4px;
    box-shadow: 0 8px 32px rgba(0,0,0,0.6);
    background: #fff;
}

/* ── Loading / Error States ───────────────────────────── */
#rdr-loader {
    position: absolute;
    inset: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 16px;
    background: #111827;
    z-index: 10;
}
.rdr-spinner {
    width: 44px;
    height: 44px;
    border: 3px solid #374151;
    border-top-color: <?php echo $themeColor; ?>;
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }
#rdr-loader p { font-size: 0.85rem; color: #6b7280; }

#rdr-error {
    display: none;
    position: absolute;
    inset: 0;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 12px;
    background: #111827;
    z-index: 10;
    padding: 24px;
    text-align: center;
}
#rdr-error i { font-size: 2.5rem; color: #ef4444; }
#rdr-error h3 { font-size: 1rem; color: #f9fafb; }
#rdr-error p  { font-size: 0.82rem; color: #6b7280; max-width: 300px; }
#rdr-error a  {
    margin-top: 8px;
    padding: 8px 20px;
    border-radius: 8px;
    background: <?php echo $themeColor; ?>;
    color: #fff;
    text-decoration: none;
    font-size: 0.85rem;
    font-weight: 600;
}

/* ── Bottom Bar ───────────────────────────────────────── */
#rdr-bottombar {
    flex: 0 0 auto;
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 0 12px;
    height: 56px;
    background: #1f2937;
    border-top: 1px solid #374151;
    z-index: 20;
}
.rdr-nav-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    border-radius: 8px;
    background: #374151;
    border: none;
    color: #e5e7eb;
    font-size: 0.9rem;
    cursor: pointer;
    transition: background 0.15s;
    flex-shrink: 0;
}
.rdr-nav-btn:hover:not(:disabled) { background: <?php echo $themeColor; ?>; }
.rdr-nav-btn:disabled { opacity: 0.35; cursor: default; }

/* Page slider */
#rdr-slider-wrap {
    flex: 1;
    display: flex;
    align-items: center;
    gap: 8px;
    min-width: 0;
}
#rdr-slider {
    flex: 1;
    -webkit-appearance: none;
    height: 4px;
    border-radius: 4px;
    background: #374151;
    outline: none;
    cursor: pointer;
}
#rdr-slider::-webkit-slider-thumb {
    -webkit-appearance: none;
    width: 18px;
    height: 18px;
    border-radius: 50%;
    background: <?php echo $themeColor; ?>;
    cursor: pointer;
    box-shadow: 0 2px 6px rgba(0,0,0,0.4);
}
#rdr-slider::-moz-range-thumb {
    width: 18px;
    height: 18px;
    border-radius: 50%;
    background: <?php echo $themeColor; ?>;
    cursor: pointer;
    border: none;
}

/* Zoom buttons */
.rdr-zoom-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 34px;
    height: 34px;
    border-radius: 6px;
    background: #374151;
    border: none;
    color: #e5e7eb;
    font-size: 0.78rem;
    cursor: pointer;
    transition: background 0.15s;
    flex-shrink: 0;
}
.rdr-zoom-btn:hover { background: #4b5563; }

/* ── Continue Reading Toast ───────────────────────────── */
#rdr-continue-toast {
    display: none;
    position: fixed;
    bottom: 70px;
    left: 50%;
    transform: translateX(-50%);
    background: #1f2937;
    border: 1px solid <?php echo $themeColor; ?>;
    border-radius: 12px;
    padding: 12px 16px;
    z-index: 100;
    box-shadow: 0 8px 24px rgba(0,0,0,0.5);
    min-width: 260px;
    max-width: 90vw;
    text-align: center;
}
#rdr-continue-toast p {
    font-size: 0.8rem;
    color: #d1d5db;
    margin-bottom: 10px;
}
.toast-btns {
    display: flex;
    gap: 8px;
    justify-content: center;
}
.toast-btn {
    padding: 6px 14px;
    border-radius: 6px;
    font-size: 0.78rem;
    font-weight: 600;
    border: none;
    cursor: pointer;
    transition: opacity 0.15s;
}
.toast-btn:hover { opacity: 0.85; }
.toast-yes { background: <?php echo $themeColor; ?>; color: #fff; }
.toast-no  { background: #374151; color: #d1d5db; }

/* ── Bookmark Toast ───────────────────────────────────── */
#rdr-bm-toast {
    position: fixed;
    bottom: 70px;
    left: 50%;
    transform: translateX(-50%);
    padding: 8px 18px;
    background: #111827;
    border: 1px solid #374151;
    border-radius: 20px;
    font-size: 0.78rem;
    color: #d1d5db;
    z-index: 100;
    opacity: 0;
    transition: opacity 0.3s;
    pointer-events: none;
    white-space: nowrap;
}

/* ── Mobile: swipe hint ───────────────────────────────── */
@media (max-width: 500px) {
    #rdr-title { font-size: 0.78rem; }
    .rdr-zoom-btn { display: none; }
}
</style>
</head>
<body>

<div id="rdr-shell">

  <!-- ① TOP BAR -->
  <header id="rdr-topbar">
    <a href="<?php echo htmlspecialchars($backUrl); ?>" class="rdr-back-btn" title="Back to novel">
      <i class="fa-solid fa-arrow-left"></i>
    </a>
    <div id="rdr-title"><?php echo $novelTitle; ?></div>
    <div id="rdr-pageinfo">— / —</div>
    <button id="rdr-bm-btn" title="Bookmark this page">
      <i class="fa-regular fa-bookmark"></i>
    </button>
  </header>

  <!-- ② CANVAS VIEWPORT -->
  <main id="rdr-viewport">
    <!-- Loading -->
    <div id="rdr-loader">
      <div class="rdr-spinner"></div>
      <p>Loading novel…</p>
    </div>
    <!-- Error -->
    <div id="rdr-error">
      <i class="fa-solid fa-triangle-exclamation"></i>
      <h3>Could not load the novel</h3>
      <p>The PDF could not be retrieved from Google Drive. Please make sure the file is publicly shared.</p>
      <a href="<?php echo htmlspecialchars($backUrl); ?>">← Go Back</a>
    </div>
    <!-- Canvas -->
    <div id="rdr-canvas-wrap">
      <canvas id="rdr-canvas"></canvas>
    </div>
  </main>

  <!-- ③ BOTTOM BAR -->
  <footer id="rdr-bottombar">
    <button id="rdr-prev" class="rdr-nav-btn" title="Previous page" disabled>
      <i class="fa-solid fa-chevron-left"></i>
    </button>

    <div id="rdr-slider-wrap">
      <input type="range" id="rdr-slider" min="1" max="1" value="1" step="1">
    </div>

    <button id="rdr-next" class="rdr-nav-btn" title="Next page" disabled>
      <i class="fa-solid fa-chevron-right"></i>
    </button>

    <button class="rdr-zoom-btn" id="rdr-zoom-out" title="Zoom Out">
      <i class="fa-solid fa-minus"></i>
    </button>
    <button class="rdr-zoom-btn" id="rdr-zoom-fit" title="Fit Width" style="width:auto;padding:0 8px;font-size:0.7rem;">Fit</button>
    <button class="rdr-zoom-btn" id="rdr-zoom-in"  title="Zoom In">
      <i class="fa-solid fa-plus"></i>
    </button>
  </footer>

</div><!-- /rdr-shell -->

<!-- Continue Reading Toast -->
<div id="rdr-continue-toast">
  <p id="rdr-continue-text">Continue from page <strong id="rdr-saved-page">1</strong>?</p>
  <div class="toast-btns">
    <button class="toast-btn toast-yes" onclick="continueReading()">Yes, Continue</button>
    <button class="toast-btn toast-no"  onclick="dismissContinue()">Start from beginning</button>
  </div>
</div>

<!-- Bookmark Toast -->
<div id="rdr-bm-toast"></div>

<script src="<?php echo rtrim($base_path,'/'); ?>/vendor/pdfjs/pdf.min.js"></script>
<script>
(function() {
'use strict';

const POST_ID   = <?php echo (int)$novel['id']; ?>;
const PROXY_URL = <?php echo json_encode($proxyUrl); ?>;
const LS_PAGE   = 'rdr_page_' + POST_ID;
const LS_BM     = 'rdr_bm_'   + POST_ID;

let pdfDoc       = null;
let currentPage  = 1;
let totalPages   = 0;
let scale        = 1.0;
let renderTask   = null;
let isRendering  = false;

const canvas      = document.getElementById('rdr-canvas');
const ctx         = canvas.getContext('2d');
const loader      = document.getElementById('rdr-loader');
const errBox      = document.getElementById('rdr-error');
const pageInfo    = document.getElementById('rdr-pageinfo');
const slider      = document.getElementById('rdr-slider');
const prevBtn     = document.getElementById('rdr-prev');
const nextBtn     = document.getElementById('rdr-next');
const bmBtn       = document.getElementById('rdr-bm-btn');
const contToast   = document.getElementById('rdr-continue-toast');
const savedPageEl = document.getElementById('rdr-saved-page');
const bmToast     = document.getElementById('rdr-bm-toast');

pdfjsLib.GlobalWorkerOptions.workerSrc =
    '<?php echo rtrim($base_path,"/"); ?>/vendor/pdfjs/pdf.worker.min.js';

function computeFitScale(viewport) {
    const vw = document.getElementById('rdr-viewport').clientWidth - 24;
    return Math.min(vw / viewport.width, 2.5);
}

async function renderPage(num) {
    if (isRendering) {
        if (renderTask) { try { renderTask.cancel(); } catch(e){} }
    }
    isRendering = true;

    try {
        const page     = await pdfDoc.getPage(num);
        const rawVP    = page.getViewport({ scale: 1 });
        const fitScale = scale || computeFitScale(rawVP);
        const viewport = page.getViewport({ scale: fitScale });

        canvas.width  = viewport.width;
        canvas.height = viewport.height;

        renderTask = page.render({ canvasContext: ctx, viewport });
        await renderTask.promise;

        currentPage = num;
        pageInfo.textContent = num + ' / ' + totalPages;
        slider.value         = num;
        prevBtn.disabled     = num <= 1;
        nextBtn.disabled     = num >= totalPages;
        try { localStorage.setItem(LS_PAGE, num); } catch(e){}
        const bm = getSavedBookmark();
        bmBtn.classList.toggle('active', bm === num);
        bmBtn.querySelector('i').className = bm === num ? 'fa-solid fa-bookmark' : 'fa-regular fa-bookmark';
        document.getElementById('rdr-viewport').scrollTop = 0;
    } catch (err) {
        if (err && err.name !== 'RenderingCancelledException') {
            console.error('Render error:', err);
        }
    } finally {
        isRendering = false;
    }
}

async function loadPDF() {
    try {
        const loadTask = pdfjsLib.getDocument({
            url: PROXY_URL,
            disableAutoFetch: true,
            disableStream: false,
            rangeChunkSize: 131072,
            isEvalSupported: false,
        });

        pdfDoc     = await loadTask.promise;
        totalPages = pdfDoc.numPages;
        slider.max = totalPages;
        slider.min = 1;
        loader.style.display = 'none';
        prevBtn.disabled = false;
        nextBtn.disabled = false;

        const savedPage = getSavedPage();
        if (savedPage && savedPage > 1 && savedPage <= totalPages) {
            showContinueToast(savedPage);
            await renderPage(1);
        } else {
            await renderPage(1);
        }
    } catch (err) {
        console.error('PDF load error:', err);
        loader.style.display   = 'none';
        errBox.style.display   = 'flex';
    }
}

// ── Navigation ───────────────────────────────────────────
function goTo(num) {
    num = Math.max(1, Math.min(num, totalPages));
    if (num !== currentPage) renderPage(num);
}

prevBtn.addEventListener('click', () => goTo(currentPage - 1));
nextBtn.addEventListener('click', () => goTo(currentPage + 1));
slider.addEventListener('input',  () => goTo(parseInt(slider.value)));

// ── Zoom ─────────────────────────────────────────────────
document.getElementById('rdr-zoom-out').addEventListener('click', () => {
    scale = Math.max(0.5, scale - 0.15);
    renderPage(currentPage);
});
document.getElementById('rdr-zoom-in').addEventListener('click', () => {
    scale = Math.min(3.0, scale + 0.15);
    renderPage(currentPage);
});
document.getElementById('rdr-zoom-fit').addEventListener('click', () => {
    scale = 0; // triggers auto-fit
    renderPage(currentPage);
});

// ── Keyboard Navigation ───────────────────────────────────
document.addEventListener('keydown', (e) => {
    if (e.key === 'ArrowRight' || e.key === 'ArrowDown')  goTo(currentPage + 1);
    if (e.key === 'ArrowLeft'  || e.key === 'ArrowUp')    goTo(currentPage - 1);
});

// ── Touch Swipe (Mobile) ─────────────────────────────────
let touchStartX = 0;
let touchStartY = 0;
document.getElementById('rdr-viewport').addEventListener('touchstart', (e) => {
    touchStartX = e.touches[0].clientX;
    touchStartY = e.touches[0].clientY;
}, { passive: true });
document.getElementById('rdr-viewport').addEventListener('touchend', (e) => {
    const dx = e.changedTouches[0].clientX - touchStartX;
    const dy = e.changedTouches[0].clientY - touchStartY;
    if (Math.abs(dx) > Math.abs(dy) && Math.abs(dx) > 40) {
        // Horizontal swipe — navigate pages
        if (dx < 0) goTo(currentPage + 1); // swipe left = next
        else         goTo(currentPage - 1); // swipe right = prev
    }
}, { passive: true });

// ── Bookmark System ───────────────────────────────────────
function getSavedBookmark() {
    try { return parseInt(localStorage.getItem(LS_BM)) || null; } catch(e){ return null; }
}
bmBtn.addEventListener('click', () => {
    const existing = getSavedBookmark();
    if (existing === currentPage) {
        // Remove bookmark
        try { localStorage.removeItem(LS_BM); } catch(e){}
        bmBtn.classList.remove('active');
        bmBtn.querySelector('i').className = 'fa-regular fa-bookmark';
        showBmToast('Bookmark removed');
    } else {
        // Set bookmark
        try { localStorage.setItem(LS_BM, currentPage); } catch(e){}
        bmBtn.classList.add('active');
        bmBtn.querySelector('i').className = 'fa-solid fa-bookmark';
        showBmToast('Page ' + currentPage + ' bookmarked ✓');
    }
});

function showBmToast(msg) {
    bmToast.textContent = msg;
    bmToast.style.opacity = '1';
    setTimeout(() => { bmToast.style.opacity = '0'; }, 2000);
}

// ── Continue Reading System ───────────────────────────────
function getSavedPage() {
    try { return parseInt(localStorage.getItem(LS_PAGE)) || null; } catch(e){ return null; }
}
function showContinueToast(page) {
    savedPageEl.textContent = page;
    contToast.style.display = 'block';
}
window.continueReading = function() {
    const page = getSavedPage();
    contToast.style.display = 'none';
    if (page) renderPage(page);
};
window.dismissContinue = function() {
    contToast.style.display = 'none';
    try { localStorage.removeItem(LS_PAGE); } catch(e){}
    renderPage(1);
};

// ── Window Resize — Re-render ────────────────────────────
let resizeTimer;
window.addEventListener('resize', () => {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(() => {
        if (pdfDoc) {
            const prev = scale;
            scale = 0; // auto-fit
            renderPage(currentPage);
            scale = prev;
        }
    }, 300);
});

// ── Start ────────────────────────────────────────────────
loadPDF();

})();
</script>
</body>
</html>
