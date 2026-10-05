# Shekuthi — Requests

> Add one function per line with the next permanent `R<n>` ID. These requests mirror the technical plan in this folder. Completion ticks were synchronized to the verified technical task statuses on 2026-10-05 at your request. Unticked items are incomplete or still need verification; details stay in the plan.

## How to use

1. Add new requests under the relevant area, or add a new area.
2. Use the next unused `R<n>` ID; never renumber or reuse IDs.
3. A tick records completed technical scope; you can review and change your own confirmation after trying the function. The 2026-10-05 ticks were applied by the agent under your explicit instruction to mark built work done.
4. Agents link each request to a plan task before implementation. Questions and preferences go under Notes.

## M0 · Design system & assets
- [x] R1 · Pick the website UI template (adopt ONE) (plan: M0.1)
- [x] R2 · Pick the dashboard UI system (adopt ONE) (plan: M0.2)
- [x] R3 · Adopt mobile design language: Material 3 (plan: M0.3)
- [x] R4 · Generate token files from the chosen systems (plan: M0.4)
- [ ] R5 · Icon pipeline (copy-out, not reference) (plan: M0.5)
- [ ] R6 · Illustration pipeline (copy-out) (plan: M0.6)
- [ ] R7 · Brand basics: name, logo, palette (plan: M0.7)

## M1 · Foundations
- [x] R8 · Backend scaffold (Laravel on shared hosting) (plan: M1.1)
- [x] R9 · Create project, docroot → backend/public/ (plan: M1.1a)
- [x] R10 · .env.example with DB / Sanctum / app-key / FCM placeholders (plan: M1.1b)
- [ ] R11 · Record Hostinger limits in an ADR (plan: M1.1c)
- [x] R12 · Mobile app scaffold (Flutter) (plan: M1.2)
- [x] R13 · Database schema — all migrations (plan: M1.3)
- [x] R14 · users (+ roles, encrypted PII, blind-index phone) (plan: M1.3a)
- [x] R15 · vendors (profile + category: traditional / agro / rental_homestay) (plan: M1.3b)
- [x] R16 · districts + localities (admin-managed names) (plan: M1.3c)
- [x] R17 · rider_base_operations (1 district + ≤5 localities) + driver_availability (plan: M1.3d)
- [x] R18 · products (category enum, price, unit, moq, stock/batch, availability dates for rentals) (plan: M1.3e)
- [x] R19 · bookings (+ items pivot, status lifecycle, guest contact fields) (plan: M1.3f)
- [x] R20 · logistics_jobs (pickup/delivery, collector + driver legs) + errands (plan: M1.3g)
- [x] R21 · verifications + verification_volunteers + badges (volunteer-name snapshot) (plan: M1.3h)
- [x] R22 · donation_settings (upi_id, qr_path; single admin-managed row) (plan: M1.3i)
- [x] R23 · referrals (code, owner, attribution, conversion ledger) (plan: M1.3j)
- [x] R24 · consents + data_requests (DPDP) (plan: M1.3k)
- [x] R25 · media + inbox notifications + device tokens (plan: M1.3l)
- [x] R26 · Auth + roles (vendors, drivers, workers, volunteers only) (plan: M1.4)
- [x] R27 · PII encryption layer (registered users) (plan: M1.5)
- [x] R28 · CI pipeline (plan: M1.6)

## M2 · Roles, catalog & listings
- [x] R29 · Registration & profiles per role (plan: M2.1)
- [x] R30 · Vendor profile (categories, location; no payout fields (plan: M2.1a)
- [x] R31 · Worker profile (services offered, service areas) (plan: M2.1b)
- [x] R32 · Volunteer profile (availability, TA/DA record) (plan: M2.1c)
- [x] R33 · Listing CRUD — traditional, agro, rental/homestay (plan: M2.2)
- [x] R34 · Media upload + validation (shared validator) (plan: M2.3)
- [x] R35 · In-app photo attach (add image_picker, upload to /media, wire into listing form) (plan: M2.3a)
- [x] R36 · Catalog browse/search — guest, app + website (plan: M2.4)
- [x] R37 · Listing detail + vendor landing page (plan: M2.5)

## M3 · Booking + MOQ (guest)
- [x] R38 · Booking creation with MOQ validation (no account) (plan: M3.1)
- [x] R39 · Booking lifecycle + vendor actions (plan: M3.2)
- [x] R40 · Booking lookup for guests (phone + booking code) (plan: M3.3)

## M4 · Logistics & errands
- [x] R41 · District & locality management (admin dashboard) (plan: M4.1)
- [x] R42 · Rider base of operation — 1 district + up to 5 localities (plan: M4.2)
- [x] R43 · Driver online/offline availability toggle (plan: M4.3)
- [x] R44 · Pickup/delivery jobs + base matching (collector → driver) (plan: M4.4)
- [x] R45 · Errands (ride-style task for any user) (plan: M4.5)
- [x] R46 · Driver-availability view — which drivers are online where (no map SDK) (plan: M4.6)

## M5 · Verification & verified badge
- [x] R47 · Volunteer profile + visit queue (plan: M5.1)
- [x] R48 · Site-visit report + evidence upload (plan: M5.2)
- [x] R49 · Verified badge — "verified by: <volunteer>" (plan: M5.3)
- [x] R50 · Verification fee (admin-set, goes to volunteer) (plan: M5.4)
- [x] R51 · Bug: volunteer profile POST 500 after registration (plan: M5.5)
- [x] R52 · Persist verification evidence (JSON column) (plan: M5.6)

## M6 · Donations (UPI) + referral/affiliate
- [x] R53 · Donation page — UPI ID + QR (app + website) (plan: M6.1)
- [x] R54 · Admin donation settings (upload UPI ID + QR image) (plan: M6.2)
- [x] R55 · Vendor referral & affiliate links (plan: M6.3)

## M7 · DPDP + security
- [x] R56 · Consent capture + records (plan: M7.1)
- [x] R57 · Data export (self-serve) (plan: M7.2)
- [x] R58 · Data deletion (self-serve + admin dashboard) (plan: M7.3)
- [x] R59 · Security hardening pass (plan: M7.4)
- [x] R60 · Retention & DPDP scheduled jobs (plan: M7.5)

## M8 · Notifications, website, testing, deploy, launch
- [ ] R61 · Notifications (FCM + in-app inbox) (plan: M8.1)
- [x] R62 · Website content pages (plan: M8.2)
- [x] R63 · Automated test suite (plan: M8.3)
- [ ] R64 · Hostinger deployment (plan: M8.4)
- [x] R65 · Launch checklist (plan: M8.5)
- [x] R66 · Web member auth + role dashboards (plan: M8.6)
- [x] R67 · Web member auth UI polish (plan: M8.7)
- [x] R68 · vendor/ dependency handling for portability (plan: M8.8)
- [x] R69 · Auth pages adopt the admin centered-card layout (plan: M8.10)
- [x] R70 · Auth forms match the admin form styling (plan: M8.11)
- [x] R71 · Member auth pages use the admin design system (plan: M8.12)
- [x] R72 · Auth pages review + polish (login/register) (plan: M8.9)

## M9 · Service areas + audit gap-closing

## M10 · Scope corrections & role rename

## M11 · Upload optimisation

## M12 · Vendor functional gaps
- [x] R73 · Web vendor listing create + edit (bug fix) (plan: M12.1)
- [x] R74 · Web vendor listing archive/unpublish + photo removal (plan: M12.2)
- [x] R75 · Web vendor profile edit (plan: M12.3)
- [x] R76 · Web vendor incoming bookings (plan: M12.4)

## M13 · App parity gaps
- [ ] R77 · App listing edit screen (plan: M13.1)
- [x] R78 · App skilled-worker profile (plan: M13.2)
- [ ] R79 · App vendor pickup/delivery job request (plan: M13.3)

## M14 · Admin & platform gaps
- [x] R80 · Admin listing moderation screen (plan: M14.1)
- [ ] R81 · Web notifications inbox (plan: M14.2)

## M15 · Member workspace & form UI

## M16 · Dev tooling
- [ ] R82 · Demo accounts seeder for local testing (plan: M16.1)

— 2026-10-05: M16.1 repaired and technically verified (14 tests, 85 assertions; scoped Pint clean). R82 remains unticked for owner confirmation. Demo seeding was exercised only against isolated SQLite.
- [x] R83 · Premium member dashboard + listing form (plan: M15.1)
- [x] R84 · Web driver base-of-operation setting (plan: M15.2)
- [x] R85 · Web skilled-worker profile edit (plan: M15.3)
- [x] R86 · Modern public booking form (plan: M15.4)
- [x] R87 · Publish stylesheets + cache-bust assets (bug fix) (plan: M15.5)
- [x] R88 · Convert image uploads to WebP (plan: M11.1)
- [x] R89 · Admin: add multiple localities in one submission (plan: M10.1)
- [x] R90 · Service-area enable/disable applies only to driver bases, errands and delivery (plan: M10.2)
- [x] R91 · Rename worker role to skilled_worker (stored + labels) (plan: M10.3)
- [x] R92 · Locality service-area flag — tick-to-activate + active-only enforcement (plan: M9.1)
- [x] R93 · App catalog district/locality filters (plan: M9.2)
- [x] R94 · Share buttons with referral attribution (web + app) (plan: M9.3)
- [x] R95 · Vendor profile view/edit (app) (plan: M9.4)
- [x] R96 · Admin verification queue (web) (plan: M9.5)
- [x] R97 · Volunteer training — in-app checklist + docs guide (plan: M9.6)

## M17 · Volunteer approval & skilled-worker discovery
- [x] R98 · Volunteer registration requires admin approval (plan: M17.1)
- [x] R99 · Skilled-worker skill categories (admin-managed) + custom work (plan: M17.2)
- [x] R100 · Public skilled-worker directory, searchable by category (plan: M17.3)

## M18 · Transport & errands directory
- [x] R101 · Transport & errand categories (admin-managed) + driver ticks & contact (plan: M18.1)
- [x] R102 · Public Transport & errands directory with call (web + API) (plan: M18.2)

## M19 · Region reference data
- [x] R103 · Real localities for Dimapur, Kohima, Chümoukedima & Niuland (additive seed) (plan: M19.1)

## M20 · Production readiness
- [x] R104 · App parity for the directory & role-profile features (plan: M20.1)
- [x] R105 · Production readiness audit (website + app) (plan: M20.2)
- [ ] R106 · App release hardening (plan: M20.3)

## M21 · Sales reporting & peer-to-peer affiliate commissions
- [x] R107 · Vendor sales report (monthly / quarterly / yearly) + PDF (plan: M21.1)
- [x] R108 · Peer-to-peer affiliate commissions (vendors + drivers) (plan: M21.2)
- [ ] R109 · App: affiliate commission UI + driver errand attribution (plan: M21.3)

## M22 · Blog / community stories
- [x] R110 · Blog: admin authoring + public pages (plan: M22.1)
- [x] R111 · App: community stories reader (plan: M22.2)

## M23 · Dedicated PG / rentals / homestays section
- [x] R112 · Stays section: web search page + API + app shortcut (plan: M23.1)

## M24 · About page & content
- [x] R113 · Rewrite the About page around the stakeholders and the full feature set (plan: M24.1)

## M25 · Verification: volunteer questionnaire + signed story
- [x] R114 · Platform questionnaire + volunteer-signed verification story + badge details (plan: M25.1)
- [x] R115 · App: volunteer questionnaire + verification story reader (plan: M25.2)
- [ ] R116 · App: volunteer profile photo upload (plan: M25.3)

## M26 · Legal pages & DPDP compliance
- [x] R117 · Terms, Privacy and Disclaimer pages (env-driven legal identity) (plan: M26.1)
- [x] R118 · DPDP consent capture at registration + compliance docs (plan: M26.2)
- [x] R119 · App: consent at sign-up + legal links (plan: M26.3)

## M27 · Production audit (2026-09-19)
- [x] R120 · Collector role is non-functional (or remove it) (plan: M27.1)
- [ ] R121 · Vendor pickup/delivery request has no client (plan: M27.2)
- [ ] R122 · Volunteer report cannot attach evidence photos (app/web) (plan: M27.3)
- [x] R123 · DPDP data export is incomplete (plan: M27.4)
- [x] R124 · Missing consent rows: errand contact + notifications (plan: M27.5)
- [x] R125 · Data deletion does not cover all PII (plan: M27.6)
- [ ] R126 · Listing does not link to its verification story (app API) (plan: M27.7)
- [ ] R127 · Website catalog lacks area/price filters (plan: M27.8)

## M28 · Collectors & reseller farm produce
- [x] R128 · Collector role: one per sub-division, signed by admin (plan: M28.1)
- [x] R129 · Farm produce (reseller) category + public section (plan: M28.2)
- [x] R130 · Farm-produce collection jobs + hub districts (plan: M28.3)
- [x] R131 · App: collector screens (assignment + collection jobs) (plan: M28.4)
- [x] R132 · App + web: vendor requests a farm-produce collection (plan: M28.5)

## M29 · Open-source distribution
- [x] R133 · Publish to GitHub under MIT + About-page contribution note (plan: M29.1)
- [x] R134 · About page: AI-built disclosure (plan: M29.2)

## M30 · Media limits & optimisation
- [x] R135 · 2 MB cap, downscale + WebP on upload, max 4 listing photos (plan: M30.1)

## M31 · Third-party asset hygiene
- [x] R136 · Untrack third-party art/reference docs; credit owners + source links (plan: M31.1)

## M32 · Shekuthi brand + Hostinger deployment readiness
- [x] R137 · Rename public platform branding to Shekuthi and set shekuthi.in defaults (plan: M32.1)
- [x] R138 · Hostinger shared-hosting deployment audit + launch runbook (plan: M32.2)

## M33 · Contact, grievance and peer-to-peer responsibility copy
- [x] R139 · Add Shekuthi contact and grievance details (plan: M33.1)
- [x] R140 · Add rate-compliance and peer-to-peer responsibility disclaimer (plan: M33.2)

## M34 · Responsive UI fluidity audit
- [x] R141 · Audit website and Flutter layouts across viewport sizes (plan: M34.1)

## M35 · Modern responsive public search/filter UI
- [x] R142 · Replace brittle public filter rows with responsive labeled filter cards (plan: M35.1)

## M36 · Clean sharp typography and UI pass
- [x] R143 · Apply lighter typography, sharper radii and crisp surfaces across web/app (plan: M36.1)

## M37 · Mobile website app-like shell
- [x] R144 · Add compact mobile header and bottom navigation (plan: M37.1)

## M38 · Admin password change with email OTP
- [x] R145 · OTP-confirmed admin password change (plan: M38.1)

## M39 · About-page AI attribution update
- [x] R146 · Name OpenCode and the LLMs used in the About disclosure (plan: M39.1)

## M40 · Android APK build and live API verification
- [x] R147 · Build release APK and verify the live Shekuthi API surface (plan: M40.1)

## M41 · Flutter bottom navigation shell
- [x] R148 · Add persistent app navigation and Back action (plan: M41.1)

## M42 · Navigation inset fix + causal functional audit
- [x] R149 · Make bottom navigation safe-area aware across phones (plan: M42.1)
- [x] R150 · Audit plan requirements through data, logic, API, web, app and tests (plan: M42.2)

## M43 · Mobile auth network diagnostics
- [x] R151 · Show useful registration/login API and connection errors (plan: M43.1)

## M44 · Android release network permission
- [x] R152 · Add INTERNET permission to the main Android manifest (plan: M44.1)

## M45 · Email verification + listing approval
- [x] R153 · Make registration require email-link verification (plan: M45.1)
- [x] R154 · Require admin approval before a new listing becomes active (plan: M45.2)

## M46 · Current function inventory and plan reconciliation
- [x] R155 · Append current function inventory, evidence and open gaps (plan: M46.1)

## M47 · Protect data export files
- [x] R156 · Store exports privately and limit download exposure/retention (plan: M47.1)

## M48 · Vendor delayed listing deletion
- [x] R157 · Delete option after 7 days unpublished (vendor products only) (plan: M48.1)

## M49 · Skill tracking migration
- [x] R158 · Populate skill plan and request files from the current project (plan: M49.1)

## M50 · Repository documentation

- [x] R159 · Merge the skill folder README and ignore rules into the root and delete the remaining folder (plan: M50.1)

## M51 · Logo and listing-first app

- [x] R160 · Use my supplied logo in the app and website (plan: M51.1)
- [x] R161 · Show all listings on Home, choose categories from bottom navigation, and open the updated app in the emulator (plan: M51.2)
- [x] R162 · Check which milestones still need to be built and append the findings to the plan (plan: M51.3)

## M52 · Completion review

- [x] R163 · Check already-built work and mark completed milestones and requests done (plan: M52.1)

## Notes

- Next unused request ID: **R174**.
- Migrated from the project tracker on 2026-10-05; wording derives from existing task scopes, not new feature requests.
- Completed engineering work remains marked in plan.md. Unticked requests do not mean it is unimplemented.
- Known outstanding work includes ordinary vendor pickup requests, listing editing in the app, website notifications, app affiliate commissions, volunteer photo/evidence uploads, full-data export/deletion, consent gaps, verification-story links and launch/device checks. Consult linked tasks and the latest function inventory for overlap and details.
- Q7 hosting confirmation, Q9 media storage and Q13 final logo/palette remain open as recorded. Duplicate historical Q7 rows are intentionally preserved.
- Older asset-copy instructions are historical; the current root agreement's third-party asset isolation rule applies to future work.
- Root ATTRIBUTION.md remains the project attribution register; the skill's attribution template is not a replacement.

- 2026-10-05: R160–R162 implemented/reviewed under M51; technical completion is in plan.md. Confirm by ticking these requests yourself after review. Remaining implementation/release work is listed in plan.md §11.

- 2026-10-05 (M52): owner explicitly requested marking already-built work done. Request ticks now mirror reviewed plan completion rather than the earlier unticked migration state. No ticks added for partial features, failed demo seeding, or unverified operations.

## Verification photo limits

- [x] R164 · Limit verification evidence to four photos, 500 KB each (plan: M5.7)

## Listing approval, expiry and renewal

- [x] R165 · Require admin approval for every listing before it becomes public; expire one year after approval (plan: M53.1)
- [x] R166 · Automatically remove unrenewed listings 30 days after expiry, sending email and in-app warnings first (plan: M53.2)
- [x] R167 · Let vendors renew expired listings from the app and website, with admin review and visible deadlines (plan: M53.3)

— 2026-10-05: R165–R167 technically complete under M53. Automated/local app checks passed; production migration, SMTP and scheduler activation remain M8.4/Q7.

## Driver vehicle categories

- [x] R168 · Drivers choose their vehicle category before listing in Transport & errands; show the vehicle on their listing (plan: M54.1)

— 2026-10-05: R168 technically complete under M54.1; app/web driver selectors and public listing labels verified locally. Legacy drivers must choose a category after backend deployment.

## Fluid UI and role workflows

- [x] R169 · Check every role's capabilities and permission boundaries, recording remaining gaps (plan: M55.1)
- [x] R170 · Keep role screens fluid with proper component spacing at narrow widths and enlarged text (plan: M55.2)
- [x] R171 · Show each role its usable tools and protect private navigation (plan: M55.3)
- [x] R172 · Drivers can accept, start and complete jobs and errands without losing their assigned work (plan: M55.4)

- [x] R173 · Role dashboards handle missing profiles and contact edits preserve advertised services (plan: M55.5)

— 2026-10-05: R169–R173 technically verified locally under M55; synchronized using the owner’s prior instruction to mark already-built verified work done. Remaining feature and deployment gaps stay open; see `../../docs/audits/role-workflows-2026-10-05.md`.

## Home filter spacing

- [x] R174 · Add proper space between Home search and district/locality filters (plan: M56.1)

## Navigation on every screen

- [x] R175 · Keep the bottom navigation menu fixed and available on every app screen, including sign-in and registration (plan: M57.1)

- [x] R176 · Registration options and Home empty/error states fit narrow screens with large text and the fixed bottom menu (plan: M57.2)

- [ ] R177 · Open the app in the emulator and test navigation and screens (plan: M57.3)

— 2026-10-05: R177 technically verified under M57.3; emulator left open on Home. 98 Flutter tests pass and analyzer clean. Guest navigation and auth form layouts checked; authenticated submissions excluded. Owner confirmation remains unticked.

## Optimization and website update

- [ ] R178 · Optimize repeated catalog database queries while preserving results (plan: M58.1)
- [ ] R179 · Share SSH commands to update the website (plan: M58.2)

— 2026-10-05: R178–R179 technically complete under M58; full backend tests and scoped Pint pass, SSH shell syntax checked. Requests remain unticked for owner confirmation; no production update performed.

## Brand kit

- [ ] R180 · Use the supplied logo green with white and black across the brand kit, app and website (plan: M59.1)

— 2026-10-05: R180 palette implemented for app, public website and dashboards; tests/build verified. M59.1 remains in progress pending website browser visual state verification. Brand kit: `../../docs/design/brand-kit.md`.

- [ ] R181 · Serve the website and app locally for review before updating live (plan: M60.1)

— 2026-10-05: R181 local preview running; website localhost:8000, emulator uses 10.0.2.2:8000; isolated demo database, live site unchanged. Owner confirmation remains unticked.

- [ ] R182 · Replace website homepage illustration with an original hill village and farm produce SVG in the brand palette (plan: M61.1)

— 2026-10-05: R182 technically complete under M61.1; original SVG installed on local homepage, desktop/mobile visually verified. Owner confirmation remains unticked.

- [ ] R183 · Refine homepage hills using the supplied landscape inspiration, only black/white/green, and credit the link (plan: M61.2)

— 2026-10-05: R183 complete technically; three-color SVG refined and inspiration credited in ATTRIBUTION.md, desktop/mobile renders verified locally.

- [ ] R184 · Convert supplied landscape image to true SVG paths in the site brand colors (plan: M62.1)

— 2026-10-05: R184 technically complete; local true-vector SVG delivered, exact brand palette verified; source licence unknown so derivative stays ignored and homepage unchanged.

- [ ] R185 · Replace homepage illustration with the generated traced landscape SVG (plan: M62.2)

- [ ] R186 · Remove the supplied landscape background and use it as the homepage illustration (plan: M62.3)

— 2026-10-05: R185 superseded by owner request R186. R186 complete technically: transparent PNG on local homepage, desktop/mobile checked; live site unchanged.

- [ ] R187 · Push current work to GitHub and provide SSH commands for Git-based Hostinger updates (plan: M63.1)
