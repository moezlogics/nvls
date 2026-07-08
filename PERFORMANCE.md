# Performance & Cache Guide (Live)

## What changed in code
1. **Anonymous pages no longer force PHP sessions** → Cloudflare/Varnish can cache HTML.
2. **Settings cached** in Redis (if available) or `cache/obj_*.json` file fallback.
3. **PDF proxy disk cache** at `cache/pdf/{driveId}.pdf` with Range support + long CDN headers.
4. **Reader opens via URL + HTTP Range** so first page shows fast; full file is NOT required in RAM first.
5. **Rate limit** on `proxy.php` (default 30 req/min/IP).

## Recommended cache stack (priority)
1. **Cloudflare** (edge) — HTML + CSS/JS/fonts + cached PDFs
2. **Varnish** (origin) — anonymous HTML
3. **Redis** — settings/menus/object data (not full HTML replacement)
4. **Local HTML cache** — fallback safety net (`PAGE_HTML_CACHE`)

Do **not** disable Cloudflare/Varnish and rely only on local HTML cache under heavy traffic.

## Cloudflare setup (important)
### Cache Rules
- **Cache Everything** for:
  - `/` homepage
  - `/*.css`, `/*.js`, `/css/*`, `/vendor/*`, `/images/*`, `/uploads/*`
  - `/proxy.php*` (PDF) with long Edge TTL (7–30 days) — query string included (`id=`)
- **Bypass cache** for:
  - `/newlogin/*`
  - `/community*` (logged-in / dynamic)
  - POST requests

### Optional
- Enable **Brotli**
- Enable **HTTP/3**
- Image optimization if plan allows
- Under Attack / Bot Fight only during abuse spikes

## Varnish
- Cache anonymous GET HTML
- Bypass cookies for admin/community
- Purge on publish/edit (or purge all after content updates)

## Redis
In `config.php`:
```php
define('REDIS_ENABLED', true);
define('REDIS_HOST', '127.0.0.1');
define('REDIS_PORT', 6379);
define('REDIS_PASSWORD', '');
```
Install PHP Redis extension on server. If Redis is down, file cache fallback keeps working.

## Disk space for PDFs
Popular novels will be stored in `cache/pdf/`.
- Keep enough free disk (estimate: unique novels × average PDF size)
- Daily cron (included): `cron/purge_pdf_cache.php` deletes PDFs older than 30 days

### Setup cron (Linux)
```bash
# Daily at 3:15 AM
15 3 * * * /usr/bin/php /path/to/novels/cron/purge_pdf_cache.php >> /path/to/novels/cache/pdf_purge.log 2>&1
```

Custom days:
```bash
/usr/bin/php /path/to/novels/cron/purge_pdf_cache.php --days=30
```

### Setup cron (cPanel)
1. Cron Jobs → Daily
2. Command:
```bash
/usr/bin/php /home/USER/public_html/novels/cron/purge_pdf_cache.php
```

### Optional HTTP cron
1. Set `CRON_SECRET` in `config.php`
2. Temporarily allow the cron folder OR call via CLI (CLI preferred)
3. URL example:
```
https://yourdomain.com/novels/cron/purge_pdf_cache.php?key=YOUR_SECRET
```
Note: `cron/.htaccess` blocks web access by default — CLI is safest.

Log file: `cache/pdf_purge.log`
## PHP-FPM / server checklist
- Raise `pm.max_children` carefully based on RAM
- MySQL indexes on `posts.slug`, `posts.status`, `categories.slug`, `writers.slug`
- Monitor: concurrent `proxy.php`, disk IO, bandwidth
- First request of a new novel still hits Google Drive once; later users get disk/CDN HIT

## Expected behavior after deploy
| Case | Result |
|------|--------|
| First reader of a novel | Drive fetch once → saved to `cache/pdf` |
| Next readers (same novel) | Served from disk (`X-Cache: HIT`) / Cloudflare |
| Repeat visit same browser | Instant from IndexedDB |
| Homepage anonymous users | HTML from CF/Varnish/local page cache |

## Local vs Live flags (`config.php`)
- Local XAMPP: `REDIS_ENABLED` can stay `true` (auto-falls back if Redis missing)
- Live: keep `PDF_DISK_CACHE true`, Cloudflare PDF cache on
- If origin disk is tiny: still keep Cloudflare caching `proxy.php`; disk cache remains best origin shield

## Verify quickly
1. Open a novel → Network tab → `proxy.php?id=...`
2. First load: `X-Cache: MISS` (or no HIT)
3. Reload / second user: `X-Cache: HIT`
4. Homepage response header: `X-Cache: PAGE-HIT` or CF `cf-cache-status: HIT`
