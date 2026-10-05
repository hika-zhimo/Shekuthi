# Shekuthi Hostinger shared-hosting deployment (M8.4/M32.2)

> **Use the confirmed procedure in section 12 for this account.** Earlier layout examples and sections 10–11 are historical/generic; the live app is directly under `domains/shekuthi.in`, not a `backend/` subdirectory.
>
> Status: reference — apply once the target plan, PHP version, and cron access
> are confirmed with the host (Q7). Everything here matches the codebase's
> Hostinger constraints: **no Redis, no long-lived queue workers**, media on
> the `public` disk, database cache/session/queue drivers.

## Readiness verdict

The Laravel website/API is **compatible with Hostinger shared hosting** when
the plan provides PHP 8.2+ and the required extensions below. It is **not ready
for public go-live yet** until DNS/SSL for `shekuthi.in`, database, legal
identity, mail, first admin/region, backups and cron are verified. See
`docs/launch/checklist.md`.

The Flutter app is a separate release: build it with
`--dart-define=API_BASE_URL=https://shekuthi.in/api/v1` and
`--dart-define=SITE_BASE_URL=https://shekuthi.in`. Android release signing is
still separate from this PHP deployment and is not complete in the repository.

## 1. Confirm the plan (Q7)

- Exact PHP version (target >= 8.2; Laravel 12). Used to pin `composer.json`'s
  `platform` so `composer install --no-dev` resolves reliably.
- Required PHP extensions: `pdo_mysql`, `mbstring`, `openssl`, `ctype`,
  `fileinfo`, `gd` (2 MB image resize/WebP), `dom` (PDF reports), `tokenizer`
  and `xml`.
- Cron job availability. Decides whether M7.5 retention sweeps and M8.1
  notification cleanup run on a schedule or as a documented manual command.
- Web-server layout. Prefer pointing the docroot at `backend/public/`; if
  that is not allowed, use a symlink (`storage` link) or the `.htaccess`
  rewrite that routes to `backend/public/index.php`.

## 2. Layout on the host

```
~/domains/<app>/
├── backend/            # whole Laravel app + blade website (upload as-is)
└── public_html/        # docroot for https://shekuthi.in
    └── index.php       # the backend/public/index.php file
```

Upload `backend/` excluding `node_modules/`, `.git/`, `tests/`, and
`storage/logs/*.log` (or exclude via `.gitattributes` export-ignore).

The safest layout points the domain document root directly at `backend/public/`.
If hPanel requires `public_html/`, copy the contents of `backend/public/` there
and keep the Laravel application directory outside the document root; update
`public_html/index.php` paths accordingly.

## 3. Environment

Copy `backend/.env.production.example` to `backend/.env` and fill in:

| Key | Value |
|-----|-------|
| `APP_ENV` | `production` |
| `APP_KEY` | `php artisan key:generate --show` output |
| `APP_URL` | `https://shekuthi.in` |
| `DB_*` | panel-created database (see below) |
| `PII_INDEX_KEY` | `php -r "echo bin2hex(random_bytes(32));"` |
| `FCM_SERVER_KEY` | legacy FCM server key (optional; empty = in-app inbox only) |
| `CACHE_STORE` | `database` |
| `SESSION_DRIVER` | `database` |
| `QUEUE_CONNECTION` | `database` |
| `SANCTUM_STATEFUL_DOMAINS` | `shekuthi.in,www.shekuthi.in` |
| `APP_CRON_ENABLED` | `false` until dry-run and cron verification; then `true` |

Before caching configuration, replace every `<...>` placeholder in
`.env.production.example`, especially `APP_KEY`, `PII_INDEX_KEY`, database
credentials, legal identity and SMTP credentials. Never use the example file
as the live `.env` without filling it.

## 4. Database via panel

- Create the database + user in hPanel.
- Run migrations from `backend/`:
  `php artisan migrate --force`
- Seed the first region (after Q10 decides the district list):
  `php artisan db:seed --class=DistrictLocalitySeeder --force`
  (replace the example districts with the real first-region data first.)
- Install optimized dependencies:
  `composer install --no-dev --prefer-dist --optimize-autoloader`
- Verify the host before going live:
  `composer check-platform-reqs --no-dev`

## 5. Storage + permissions

```bash
cd backend
php artisan storage:link          # if the docroot layout allows it
chmod -R ug+rw storage bootstrap/cache
```

If `storage:link` is unavailable, drop a `storage/` symlink inside the
docroot to `../backend/storage/app/public` and confirm asset URLs.

## 6. Cron (required for retention)

If cron is available, register one line (localhost connection):

```
* * * * * cd /home/<user>/domains/<app>/backend && php artisan schedule:run >> /dev/null 2>&1
```

After a successful dry run, set `APP_CRON_ENABLED=true` and confirm the
schedule lists the daily `retention:sweep` command. Keep the cron disabled
until that verification is complete.

If cron is NOT available: run the retention sweep manually with
`php artisan retention:sweep --dry-run` first, then the live command during a
controlled maintenance window; document the manual cadence in the launch
runbook.

## 6b. Publish stylesheets

`resources/css/` is the source of truth; the web server serves the copies in
`public/css/`. After any CSS change, publish them:

```
php artisan assets:publish
```

Skipping this means the browser keeps rendering the old stylesheet even
though the markup changed (this bit us once — M15.5). Asset URLs carry a
mtime version, so no manual cache clearing is needed once published.

## 7. SSL + cache

- Enable free SSL in hPanel (Let's Encrypt) and force HTTPS in panel config.
- Clear caches after deploy:
  `php artisan config:cache && php artisan route:cache && php artisan view:cache`
- `php artisan optimize` for OPcache-aware production.

Smoke-test `https://shekuthi.in/`, `/about`, `/catalog`, `/donation`, `/privacy`,
`/terms` and `/api/v1/locations` after the cache step.

## 8. Update flow

1. Put the site in maintenance mode if the change includes migrations.
2. Upload changed files over the old `backend/` tree.
3. `php artisan migrate --force` if there are new migrations.
4. `php artisan assets:publish` if any CSS changed.
5. Re-run `php artisan config:cache && php artisan route:cache && php artisan view:cache`.
6. Confirm the smoke-test URLs and exit maintenance mode.

## 9. Media backup

The storage `public/` directory holds products, UPI QR, and evidence.
Schedule a weekly download of `backend/storage/app/public/` (panel backup or
a cron `tar` to a private directory outside the docroot). Keep the media
backup separate from DB backups.

## 10. SSH update commands (M58.2)

Upload the reviewed backend changes first. This workspace contains uncommitted
implementation changes: `git pull` on the server will not transfer them.
Preserve the live `.env`, `storage/` (uploads, exports, sessions), and the
host's `public_html/index.php` bootstrap paths. Do not upload a local `.env`,
replace the application's encryption keys, or seed demo accounts.

Get SSH host, username and port from hPanel. Replace the uppercase placeholders:

```bash
ssh -p SSH_PORT SSH_USER@SSH_HOST
```

On the server, after backing up the database and uploading reviewed files:

```bash
cd ~/domains/shekuthi.in/backend
set -e
PHP_BIN=/opt/alt/php82/usr/bin/php
"$PHP_BIN" artisan down --retry=60
"$PHP_BIN" /PATH/TO/composer.phar install --no-dev --prefer-dist --optimize-autoloader --no-interaction
"$PHP_BIN" /PATH/TO/composer.phar check-platform-reqs --no-dev
"$PHP_BIN" artisan config:clear
"$PHP_BIN" artisan migrate --force
"$PHP_BIN" artisan assets:publish
"$PHP_BIN" artisan config:cache
"$PHP_BIN" artisan route:cache
"$PHP_BIN" artisan view:cache
"$PHP_BIN" artisan up
```

The PHP 8.2 path was recorded from the live host on 2026-09-24. Confirm the
backend directory and Composer PHAR location on your account before running.
Run in a dedicated SSH shell: `set -e` stops after a failed command and leaves
the site in maintenance mode for investigation. After correcting the failure,
rerun the remaining steps and `artisan up`. Never proceed past a failed migration.

If the domain serves a separate `public_html/`, publish updated `backend/public/`
assets there as well (CSS, JS and brand images), preserving its customized
`index.php`, `.htaccess`, and storage symlink. The asset publishing command
writes to `backend/public/`; it does not synchronize a separate document root.

Verify Home, catalog, login and `/api/v1/catalog` after updating. Confirm the
existing scheduled-task cron still runs. Do not run `cache:clear` or
`optimize:clear` routinely: database-backed application cache may be shared
with other features. These commands deliberately rebuild only deployment caches.

Catalog optimization uses eager-loaded badge queries and request-local fee
reuse. It does not cache public responses across requests, so revoked badges
and administrator fee changes remain visible on the next request.
References: https://laravel.com/docs/12.x/eloquent-relationships#eager-loading
and https://laravel.com/docs/12.x/deployment#optimization.

## 11. Git-based updates (M63.1)

From the existing Git checkout on Hostinger, first run `git status --short`.
Resolve local tracked changes before pulling; never reset or clean the host.
Use `git pull --ff-only origin main`, preserving ignored backend/.env/storage.
Run commands one at a time; do not enable `set -e` in an interactive SSH shell.
The website code lives in backend/ beneath the Git repository root.
After pulling: install production Composer dependencies, check platform
requirements, run migrations after backup, publish assets and rebuild
config/route/view caches. Restore service with `artisan up` after success.

If public_html is separate, copy backend/public/css/, js/ and img/ there,
preserving index.php, .htaccess and the storage symlink. Never rsync --delete.
The current homepage image `home-landscape-cutout.png` is an ignored local
asset derived from supplied artwork; Git does not distribute it. Upload it
separately to backend/public/img/ and public_html/img/ before deploying the
homepage view. Logo/favicon and original project SVG are part of the code
release. Do not seed demo users on production or copy local databases.

## 12. Confirmed working update procedure — 2026-10-05 (M64.1)

Owner confirmed the website is live and updated after the troubleshooting
below. These paths supersede generic layout examples above.

| Item | Confirmed value |
|---|---|
| GitHub | `git@github.com:hika-zhimo/Shekuthi.git`, branch `main` |
| Server user | `u710272704` |
| Server prompt hostname | `in-mum-web1338` (use hPanel SSH host/IP and port to connect) |
| Git source checkout | `/home/u710272704/shekuthi-source` |
| Live Laravel app | `/home/u710272704/domains/shekuthi.in` |
| Browser document root | `/home/u710272704/domains/shekuthi.in/public_html` |
| Laravel asset lookup directory | `/home/u710272704/domains/shekuthi.in/public` |
| PHP | `/opt/alt/php82/usr/bin/php` |
| Composer | `/usr/local/bin/composer` |

The live application has no Git checkout. Pull into `shekuthi-source`, then
copy its `backend/` into the live app. The checkout under `domains/aghili.in`
is a different sports application: never change its remote or deploy here.

### A. Before updating

1. Test locally, commit reviewed code and push to Shekuthi GitHub main.
2. Back up the live database and media in hPanel.
3. Upload new ignored artwork before releasing views that reference it.
   For the current illustration, File Manager destination:
   `domains/shekuthi.in/public_html/img/home-landscape-cutout.png`.
   Local source:
   `/home/openlogic/Documents/projects/Shekuthi/backend/public/img/home-landscape-cutout.png`.
   Optionally keep a matching copy in live `public/img/` as well.
4. Connect using `ssh -p SSH_PORT u710272704@SSH_HOST`, taking host/port from
   hPanel. The server's GitHub access key is independent of your computer key.

### B. Fetch the release on Hostinger

Run commands individually. Do not use `set -e` in the interactive shell.
Stop on every error rather than pasting the next block.

```bash
cd /home/u710272704/shekuthi-source
git status --short
git remote -v
git pull --ff-only origin main
```

Proceed only with a clean checkout and the Shekuthi remote. No reset, clean,
force pull, or unrelated-history merge. This source checkout is already
created; do not clone again for routine updates.

### C. Maintain and copy application code

```bash
cd /home/u710272704/domains/shekuthi.in
/opt/alt/php82/usr/bin/php artisan down --retry=60
rsync -av \
  --exclude='.env*' \
  --exclude='storage/' \
  --exclude='vendor/' \
  --exclude='bootstrap/cache/' \
  --exclude='database/*.sqlite*' \
  --exclude='database/listingplatform' \
  --exclude='public/' \
  --exclude='tests/' \
  /home/u710272704/shekuthi-source/backend/ \
  /home/u710272704/domains/shekuthi.in/
```

Preserves live `.env`, encryption keys, uploaded media, local databases,
dependencies and cached configuration until the explicit steps below.
Never add `--delete`, copy a local database, or run demo seeders in production.

### D. Dependencies and database — complete before going live

```bash
/opt/alt/php82/usr/bin/php /usr/local/bin/composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
/opt/alt/php82/usr/bin/php /usr/local/bin/composer check-platform-reqs --no-dev
/opt/alt/php82/usr/bin/php artisan config:clear
/opt/alt/php82/usr/bin/php artisan migrate --force
/opt/alt/php82/usr/bin/php artisan migrate:status
```

Stop if any command fails. The October 5 listing migration moves legacy
active listings to pending admin review; admin approval republishes them.
Do not use `migrate:fresh`, database resets, or `key:generate` during updates.

### E. Publish assets to BOTH public directories

```bash
rsync -av \
  --exclude='index.php' \
  --exclude='.htaccess' \
  --exclude='storage' \
  /home/u710272704/shekuthi-source/backend/public/ \
  /home/u710272704/domains/shekuthi.in/public/
/opt/alt/php82/usr/bin/php artisan assets:publish
rsync -av \
  --exclude='index.php' \
  --exclude='.htaccess' \
  --exclude='storage' \
  /home/u710272704/domains/shekuthi.in/public/ \
  /home/u710272704/domains/shekuthi.in/public_html/
```

Laravel checks `public/` for logo existence and CSS modification timestamps;
the browser serves `public_html/`. Updating only public_html caused missing
logo markup and stale CSS version URLs. Preserve both bootstrap index.php
files, .htaccess files and storage symlinks. Ignored illustrations are not
in Git, so these copies do not replace the separate upload in step A.

### F. Refresh deployment caches and restore service

```bash
/opt/alt/php82/usr/bin/php artisan config:cache
/opt/alt/php82/usr/bin/php artisan route:cache
/opt/alt/php82/usr/bin/php artisan view:cache
/opt/alt/php82/usr/bin/php artisan up
```

Purge the site cache/CDN through hPanel and hard-refresh the browser
(Ctrl+Shift+R). Avoid routine `cache:clear`/`optimize:clear`, which can affect
application cache rather than only deployment caches.

### G. Verify

Open Home, catalog, sign-in and the admin dashboard. Check logo, green palette,
illustration and existing uploaded media. Verify direct asset URLs:

- `https://shekuthi.in/css/tokens.css` contains `--color-accent: #008000`.
- `https://shekuthi.in/img/logo.svg` loads.
- `https://shekuthi.in/img/home-landscape-cutout.png` loads (not 404).

Confirm configured cron continues running, especially listing lifecycle and
retention schedules. A successful page update alone does not verify cron.

### Known failures and the checks that resolved them

| Symptom | Cause/check | Resolution |
|---|---|---|
| `not a git repository` from `~` | Commands run outside source checkout | `cd /home/u710272704/shekuthi-source` |
| Aghili players/matches/tournaments in status | Wrong application checkout | Leave Aghili untouched; use Shekuthi source |
| SSH shell closes after command error | Interactive `set -e` | Run individually without `set -e`; `set +e` if already enabled |
| 503 after deployment | Maintenance mode still enabled or cached response | Complete dependencies/migrations, run `artisan up`, purge cache |
| 500: missing `products.expires_at` | New migrations pending | `artisan migrate --force`, then verify status |
| Old UI/no logo | public/public_html differ or cache stale | Synchronize both, clear/rebuild views, purge CDN |
| Illustration 404 | Ignored image never uploaded | Upload PNG through File Manager to public_html/img |

For errors, obtain the current response and latest error headings, not just
the bottom of an old stack trace:

```bash
curl -sS -D - -o /tmp/shekuthi-response.html "https://shekuthi.in/?check=$(date +%s)"
grep -nE 'production.ERROR|local.ERROR' storage/logs/laravel.log | tail -n 5
/opt/alt/php82/usr/bin/php artisan migrate:status
ls -l storage/framework/down storage/framework/maintenance.php
```

Missing maintenance files are normal after `artisan up`. Redact credentials
and personal information before sharing logs; do not paste `.env`.

## 13. Preserve user data and diagnose missing listings — 2026-10-05 (M67.1)

Before every deployment, back up the live database and storage uploads through
hPanel and confirm the backup is downloadable/restorable. Preserve `.env`,
APP_KEY, PII_INDEX_KEY, database credentials and storage; never overwrite a live
database with a local one. Use section 12's excluded paths and never run
migrate:fresh, migrate:refresh, db:wipe or demo seeders in production.
Review new migrations for changes to existing rows, not just schema changes.
Compare user totals and listing totals by status before and after updating;
verify an existing account, listing and uploaded photo. Rehearse database changes
on an isolated, access-controlled backup with outbound email disabled.

An empty approval queue means no pending products; it does not prove deletion.
Check archived, inactive, draft and active records before recreating anything.
Product #1 was reported archived with last update September 23 and null expiry
fields. The archive cause is unknown; the October 5 update is not established
as its cause. Owner authorized submitting only this test record for review.

Run inside Hostinger SSH from the live application root. This guarded update
changes only product #1 when its current status is archived; it preserves owner,
media and other fields, apart from the normal updated_at timestamp. It does not
publish automatically. Already pending is safe to retry; other states stop.

```bash
cd /home/u710272704/domains/shekuthi.in
/opt/alt/php82/usr/bin/php -r 'require "vendor/autoload.php"; $app = require "bootstrap/app.php"; $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); $changed = App\Models\Product::query()->whereKey(1)->where("status", "archived")->update(["status" => "pending"]); $status = App\Models\Product::query()->whereKey(1)->value("status"); if ($status !== "pending") { fwrite(STDERR, "Stopped: listing #1 is missing or no longer archived. No forced change made.\n"); exit(1); } echo $changed ? "Listing #1 sent to admin approval.\n" : "Listing #1 is already pending approval.\n";'
```

Verify Admin → Listings shows #1, then review and approve normally. Production
execution is owner-run; no live recovery has been confirmed yet.
