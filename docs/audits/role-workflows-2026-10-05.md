# Role workflow audit — 2026-10-05

M55 checks the implemented role workflows and fixes defects found during that review. It does not mark unbuilt features complete. Test users and records are isolated fixtures; no production accounts, notifications or records were modified.

## Verified capabilities

| Role | Implemented capabilities checked | Evidence in `backend/tests/Feature/` |
|---|---|---|
| Guest | Public listings, transport and worker directories; guest booking and errand requests/tracking; private account/workspace access refused | `RoleWorkflowAuditTest.php`, `BookingTest.php`, `WebBookingTest.php`, `LogisticsTest.php`, `TransportDirectoryTest.php`, `WorkerDirectoryTest.php` |
| Vendor | Own profile, listing submission/renewal with admin approval, bookings, sales and farm-produce collection requests | `RoleWorkflowAuditTest.php`, `ListingsTest.php`, `ListingLifecycleTest.php`, `WebVendorListingTest.php`, `VendorSalesReportTest.php`, `CollectionJobTest.php` |
| Driver | Base/availability, vehicle and offered services, directory eligibility, assigned job/errand acceptance, start and completion; another driver's work cannot be claimed/progressed | `DriverWorkLifecycleTest.php`, `DriverVehicleCategoryTest.php`, `DriverTransportTest.php`, `WebDriverBaseTest.php`, `ServiceAreaTest.php` |
| Collector | Admin assignment required at sign-in; assigned farm-produce jobs through completion, restricted to assigned collector | `CollectorAssignmentTest.php`, `CollectionJobTest.php` |
| Volunteer | Approval required at sign-in; profile, questionnaire/report/evidence submission, owned queue; admin review and public badge/story | `VolunteerApprovalTest.php`, `VerificationTest.php`, `VerificationStoryTest.php`, `RoleWorkflowAuditTest.php` |
| Skilled worker | Contact/services/service-area updates, category selection and directory filtering; partial contact edits preserve advertised services | `RoleWorkflowAuditTest.php`, `WorkerSkillsTest.php`, `WorkerDirectoryTest.php`, `WebWorkerProfileTest.php` |
| Administrator | Website listing moderation, volunteer/collector assignments, verification review, managed service/skill categories and data requests; non-admin access refused | `RoleWorkflowAuditTest.php`, `ListingApprovalTest.php`, `VolunteerApprovalTest.php`, `CollectorAssignmentTest.php`, `VerificationTest.php`, `AdminSkillCategoryTest.php`, `AdminTransportCategoryTest.php`, `AccountDataLifecycleTest.php` |

Registered-role profile, consent and database notification entry points were checked for all six roles. Authentication, account lifecycle, validation, upload boundaries and security checks are included in the full backend suite. This is automated local evidence, not a claim that every feature has been exercised against production.

## Changes and decisions

- One `RoleAccess` policy controls app menu visibility and private navigation. Public browsing remains available to guests. Login restores a safe internal destination allowed for the authenticated role; foreign-role routes return to More. The login screen no longer overrides this destination. Server authorization remains authoritative.
- Admin tools open the existing website console at `/admin/listings`. Website authentication is separate from the app token session. A new native admin workspace was not added.
- Driver queues retain owned assigned/accepted/in-progress/completed work even when offline or without a base. Unassigned offers require an online active driver and an active locality/district in their base. Acceptance and progress lock the work row, preserve assignment ownership and enforce accepted → in progress → completed. Repeating the current progress state is safe; skipping or reopening is rejected. Driver controls cannot operate collector jobs. Disabled users are excluded from automatic matching.
- Driver base sheets scroll with long locality lists and keyboard insets. Dashboard labels, collector queue headings and volunteer training content wrap. Vendor profile labels sit above values. Audited screens use shared spacing tokens and static loading feedback. Busy actions disable repeat submission and report failures without losing their controls.
- A volunteer website dashboard without a profile renders an empty report list instead of crashing or querying unassigned reports. Worker profile updates preserve omitted services/service areas; explicit null still clears the selected field.

## UI and test evidence

- Full backend suite: **304 tests, 1,527 assertions**, using the local PHP 8.3 runtime with GD loaded for that process. Pint passes on the M55 PHP files.
- Full Flutter suite: **95 tests passed**. Driver controls additionally passed a focused rerun after adding mouse hover and keyboard focus interaction checks. Flutter analyzer reports no issues.
- Role screens tested at **320×640 and 640×320**, **2× text**, light/dark themes. Driver sheet tests include 30 long locality names and a simulated keyboard inset. Each affected role screen also handles a simulated network failure without overflow or a stuck loading indicator.
- Shared driver work tests cover assigned → accepted → in progress → completed, loading, empty, load/action errors, retry, disabled busy controls, hover, focus and pressed states. Existing home-navigation checks cover navigation interactions and loading motion at 10% speed. New loading feedback has no animation.
- Layout snapshots under `/tmp/shekuthi_role_audit/` were inspected for component bounds and wrapping. Widget tests use the test font, so these snapshots provide geometry evidence rather than production typography approval.
- Debug APK built, installed and opened on `emulator-5554`. The actual-font home screenshot `/tmp/shekuthi_m55_home.png` was visually inspected: supplied logo, public empty listing state, location filters and bottom navigation render. No test listings were inserted into the running app. Role workflows use isolated automated fixtures rather than live role accounts.

## Remaining work stays open

| Tracker task | Remaining capability |
|---|---|
| M13.1 | App editing of existing listings; creation and lifecycle actions are already built |
| M13.3 / M27.2 | Ordinary vendor booking-linked pickup/delivery request UI; farm-produce collections are a separate implemented flow |
| M14.2 | Website notification inbox |
| M21.3 | App commission management and driver errand referral attribution |
| M25.3 | Volunteer profile photo upload in the app |
| M27.3 | Website verification evidence submission; app report evidence is implemented |
| M27.7 | Listing detail link to its verification story in the app |
| M8.1 | Full push/event integration, including mobile FCM |
| M8.4 / Q7 | Production deployment, migrations, hosting extensions, scheduler and email configuration |
| M20.3 | Physical-device verification and remaining app release checks |

The collector's operational workspace is in the app; a full website collector workspace is not demonstrated by the generic dashboard response check. Production email/push delivery, production concurrency and physical-device behavior were not verified in this local audit. Preserve these limits when evaluating release readiness.
