# Shekuthi

Shekuthi is an open-source platform connecting buyers, sellers/vendors (farmers, traditional and agro products, rentals/homestays), logistics (drivers/riders, collectors), skilled workers and verification volunteers. The platform handles no transactions and charges no commission - it runs on donations (UPI).

## Repository layout

| Path | What it is |
|---|---|
| `backend/` | Laravel 12 (PHP 8.2+) - JSON API for the app + public website (Blade). One folder for both, as deployed on Hostinger. |
| `mobile/` | Flutter (Dart) - single role-based Android app (buyer guest, vendor, driver, collector, skilled worker, volunteer). |
| `docs/` | Decisions (one ADR per open question), design tokens, deploy notes. |
| `plan-template/resources/plan.md` | Active status tracker — task IDs, status, comments and owning files. |
| `plan-template/resources/request.md` | Feature requests with permanent request IDs and your confirmation checkboxes. |
| `AGENTS.md` | Process rules for coding agents (stack-agnostic). |
| `ATTRIBUTION.md` | Credits for third-party icons/illustrations and how to restore the assets that are intentionally not tracked. |
| `local-market.md`, `additionalfeatures.txt` | Read-only reference material. Never edit; copy out what a task needs. |

> **Not in this repo:** third-party icons, illustrations and design-reference
> documents (the `assets/`, `UX/` and `ui deisgns/` paths) are excluded and
> gitignored — they are not our work. See `ATTRIBUTION.md` for the owners,
> sources and how to fetch them. A fresh clone runs without them; only the
> empty-state and hero illustrations fall back to their alt text.

## Quick start - backend

Requires PHP >= 8.2, Composer, MySQL (SQLite works for tests out of the box).

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed        # seeds example districts/localities (replace per Q10 before production)
php artisan serve                 # http://127.0.0.1:8000
```

Local API base: `http://127.0.0.1:8000/api/v1`. Production website/API: `https://shekuthi.in/` and `https://shekuthi.in/api/v1`.

## Quick start - mobile

Requires the Flutter SDK (stable channel).

```bash
cd mobile
flutter create . --org com.listingplatform --project-name listingplatform   # generates android/ platform folder once
flutter pub get
flutter run --dart-define=API_BASE_URL=http://10.0.2.2:8000/api/v1          # Android emulator reaches host via 10.0.2.2
```

## Hard rules for all contributions

1. Update `plan-template/resources/plan.md` with every change (status, dated note, files touched, change-log row).
2. No emojis anywhere in the built project UI or code - icons come from the SVG icon library (`assets/icons/`, copied into `backend/public/icons/` and `mobile/assets/icons/`). The library itself is third-party and not tracked here; see `ATTRIBUTION.md`.
3. Personal data (name, phone, email, address) is encrypted at rest; lookups go through blind-index columns. Keys live only in `.env`.
4. Buyers never register - they browse as guests. Only vendors, drivers, collectors, skilled workers and volunteers register.

## Plan-template workflow

Project tracking uses the **plan-template** skill. Add requests to [request.md](plan-template/resources/request.md); agents maintain [plan.md](plan-template/resources/plan.md). Request checkboxes are your manual confirmation.

- **Skill Instructions:** [`plan-template/SKILL.md`](./plan-template/SKILL.md)
- **YAML Specification:** [`plan-template/skill.yaml`](./plan-template/skill.yaml)
- **Adoption Guide:** [`plan-template/README.md`](./plan-template/README.md)
- **Reusable Templates:** [`plan-template/resources/`](./plan-template/resources/)
  - [`resources/AGENTS.md`](./plan-template/resources/AGENTS.md) — Frozen working agreement
  - [`resources/plan.md`](./plan-template/resources/plan.md) — Status tracker & repo map
  - [`resources/request.md`](./plan-template/resources/request.md) — Plain-English feature list
  - [`resources/ATTRIBUTION.md`](./plan-template/resources/ATTRIBUTION.md) — Third-party asset & license register
