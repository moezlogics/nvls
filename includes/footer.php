</main>
<footer class="site-footer mt-16 text-white" style="background:linear-gradient(160deg,var(--theme-primary),color-mix(in srgb,var(--theme-primary) 82%, #000));">
    <div class="global-container py-14">
        <div class="grid grid-cols-1 gap-10 md:grid-cols-3 text-center md:text-left">
            <div><?php echo isset($site_settings['footer_col1']) ? $site_settings['footer_col1'] : ''; ?></div>
            <div class="text-center"><?php echo isset($site_settings['footer_col2']) ? $site_settings['footer_col2'] : ''; ?></div>
            <div class="text-center"><?php echo isset($site_settings['footer_col3']) ? $site_settings['footer_col3'] : ''; ?></div>
        </div>

        <div class="mt-12 border-t border-white/15 pt-6 text-center text-sm text-white/70">
            &copy; <?php echo date('Y'); ?>
            <?php echo isset($site_settings['site_name']) ? htmlspecialchars($site_settings['site_name']) : 'Portal'; ?>.
            All rights reserved.
        </div>
    </div>

    <!-- Scoped styling so admin-entered footer HTML looks right without Bootstrap -->
    <style>
        .site-footer h5 { color:#fff; font-size:1.1rem; font-weight:700; margin-bottom:1rem; }
        .site-footer p  { color:rgba(255,255,255,.72); line-height:1.7; font-size:.95rem; }
        .site-footer ul { list-style:none; padding:0; margin:0; }
        .site-footer li { margin-bottom:.5rem; }
        .site-footer a  { color:rgba(255,255,255,.78); text-decoration:none; transition:color .2s; }
        .site-footer a:hover { color:var(--theme-hover); }
        .site-footer font { color:#fff !important; }
        .site-footer .social-links { display:flex; gap:.6rem; justify-content:center; }
        .site-footer .social-links a {
            display:inline-flex; align-items:center; justify-content:center;
            width:40px; height:40px; border-radius:6px;
            background:rgba(255,255,255,.12); color:#fff !important; text-decoration:none;
        }
        .site-footer .social-links a:hover { background:var(--theme-hover); color:var(--theme-primary) !important; }
    </style>
</footer>
</body>
</html>
