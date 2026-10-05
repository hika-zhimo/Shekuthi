# Shekuthi Hostinger shared-hosting deployment (M8.4/M32.2)

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
