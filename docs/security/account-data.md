# Account-data boundaries — 2026-10-05

M7 completes registered-account consent capture, private export, and deletion. Ownership is defined in `backend/app/Services/AccountDataScope.php`, and applied by the consent/export/deletion services. Automated tests use isolated records and fake storage; no production data is exported or erased by development verification.

## Consent

Errand submission requires an affirmative contact/address choice. Consent belongs to the errand, including guest requests, with server-controlled purpose, text version 1.0, and time. The app shows the purpose before submission and starts unchecked. Optional Sanctum bearer authentication links a registered customer's errand to their account; unauthenticated guest requests remain unlinked.

Device-token registration requires an affirmative push choice and records account notification consent. A token cannot move to a different account through registration. Push delivery requires an active account and unrevoked consent. Revoking push consent removes all of that account's device tokens; removing the last device revokes the active push consent. The transactional database inbox continues to carry account service messages. Mobile FCM registration and the notification event integration remain M8.1.

The generic grant endpoint accepts only the caller's own account. Record-specific consent is captured during the corresponding operation. Listing, verification-evidence and registered-customer errand consent appears in the account consent list and can be revoked by its owner. Revocation alone does not delete an order/report or constitute an account-erasure request.

## Export

The existing self-serve endpoint and app action create a JSON export on private local storage, with a ten-minute signed download URL. The account profile, every stored role profile/category/base/assignment, listings, vendor booking items, related jobs/errands/verifications, referral ledgers, authored/related stories and badges, media records and base64 file contents, consents, inbox, device/session/access-token metadata, password-change history, and rights-request ledger are included. Admin reruns use the same private-file marker as self-service.

Password hashes, reset/OTP credentials, Sanctum token values/hashes, FCM token values, session cookies/payloads, lookup blind indexes, and arbitrary referral metadata are excluded. Other participants' contact details are excluded: vendor booking exports contain transaction/items/status data; driver/collector exports contain assignment/status data rather than customer contacts/addresses. Reports and public stories include authored content; another party's account credentials and profiles are never joined into an export.

Guest bookings have no account foreign key and phone ownership is not verified at account registration. A matching phone alone therefore never links a guest booking to an account export or erasure operation. Guest rights requests require a separately verified contact/code journey through support; they are not silently associated with a registered account. There is no external analytics integration in the current repository.

## Deletion and retained integrity

The account is disabled first, its sessions/device tokens/access tokens revoked, and stale authenticated sessions rejected by the active-account middleware. Deletion removes owned media files and rows, every copied occurrence of those image paths, private and legacy public exports (including superseded files identified by the embedded account ID), inbox, consent records, password-change/reset artifacts, worker/driver/collector/volunteer profiles, and role/category/base links.

Vendor/listing/story free text, images, addresses and coordinates are stripped or unpublished. Linked reports are scrubbed. Customer-created errands lose contacts/addresses/descriptions; driver assignments are unlinked while other customers' data remains. Completed transaction statuses, item prices, approved offline commission amounts, and the anonymized rights-request ledger remain for integrity. Referral codes, identities and free-form metadata are removed from retained ledgers. Immutable badge volunteer names remain only as the explicit attribution exception already defined by M5.3; volunteer photos and authored story text are removed. No payment credentials are collected or exported.

File operations are not database transactions. Any storage failure leaves the deletion request processing, preserves discoverable file references for retry, and keeps the account disabled. An admin can rerun that request through the existing rights ledger. A request is marked complete only after application database/storage erasure succeeds. Export download URLs reject disabled accounts even during a failed erasure attempt.

## Hosting backups and restore procedure

The repository has no access to Hostinger backup controls or an external analytics system. This implementation cannot erase a historical host backup or prove its expiry; backup inventory/retention and restoration verification remain the Q7/M8.4 operational gate. Operators must keep the completed deletion-request IDs and timestamps in a separate protected ledger before restoring an older database. Before the restored site is reachable, reapply each completed deletion through DataDeletionService using its account ID, remove affected files/exports, and verify login, public URLs and private-download denial. Keep the restored site offline if the deletion ledger or any storage operation cannot be reconciled. Set and verify a bounded encrypted backup retention policy with the host; do not claim historical backups were erased by this code change.

Production deployment and hosted backup/restore verification are separate tracked work.
