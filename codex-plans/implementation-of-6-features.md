# Implementation of pending features

Implement five web features: DEV-03 admin operations, DEV-20 manual SMS/email ingestion, DEV-21 AI recommendation explanations, DEV-22 per-card family sharing, and DEV-23 offline account synchronization. DEV-24 Android stays deferred: web app only; Android will be planned later.

## Admin
Add roles defaulting to user, suspension timestamps, audit events, admin-only paginated user search, suspension/reactivation, health and audit UI/API. Do not grant admins personal resource access. Check suspension on authenticated requests and refresh; prevent self/last-admin suspension. Role changes use an explicit command; public registration cannot choose roles. Promote the local admin account explicitly and audit changes.

## Manual ingestion
Paste SMS/email or upload UTF-8 txt/eml up to 5 MB; select destination card. Deterministic parsing produces editable date/description/amount/direction/category previews; missing/ambiguous values require correction. Raw content is discarded after processing. Preview changes no financial records; validated confirmation is atomic. Fingerprints skip exact reimports and transaction candidates flag cross-source duplicates. Summary changes require explicit confirmation. Preserve manual contributions when aggregating PDF/message purchases; credits do not increase spend. No inbox/device integration or msg files.

## AI explanations
Existing deterministic ranking stays authoritative. Add a throttled authenticated endpoint and explanation UI using configured provider and minimal metadata/score breakdowns. Exclude personal documents and messages. Validate structured candidate references and order. Thirty-second timeout, deterministic fallback on invalid output/unconfigured provider/failure. Label AI; no financial writes.

## Sharing
Existing users accept owner invitations within seven days as viewer/editor. Share complete card data including PDFs/transactions with an explicit disclosure. Viewers read; editors manage metadata/spend/benefits/imports/statements. Owner alone manages sharing and deletes card. Central accessible-card queries apply to all aggregates; nested authorization and frontend permissions follow. Limit groups stay owner-scoped; reminders go to owners. No ownership transfer.

## Offline sync
Laravel is authoritative. Service worker caches shell; account-partitioned IndexedDB caches records and durable mutations/upload blobs. Queue all core card/benefit/spend workflows, deletions, imports, statements; AI/admin/invitations/message parsing remain online. Authenticated bootstrap/feed/mutation APIs use cursors, revisions, tombstones and UUID receipts committed atomically with writes. Replay dependencies with temporary-ID mapping; deleting an unsent card cancels children. Record changes including sharing/derived updates. Preserve conflicts and offer local/server choice. Reauthorize replay; purge revoked data on reconnection. Retry transient errors with capped exponential backoff; pause expired auth and block denied operations. Limit pending files to 100 MB/account; never silently drop work. Logout/account switching warns and clears data only on confirmation. Sync Center shows offline/pending/failed/conflicts and supports retry/discard. Disclose inability to revoke offline copies immediately.

## Delivery
Persist JWT and backend configuration, align container source, install test dependencies and establish regression baseline. Implement admin/sharing foundations, ingestion/aggregation, AI, then sync/backend contracts and frontend. Use additive migrations and preserve data/ownership, backfill manual contributions; maintain online API fields and require sync revisions. Hosting is external; document HTTPS, stable secrets, storage, limits, migrations; deploy backend before frontend.

## Validation and ledger
Test admin boundaries/suspension/audits; parser ambiguity/duplicates/manual totals; AI validation/fallback; invitation and permission matrix/aggregates; sync retry/conflicts/dependencies/uploads/quota/revocation/auth/account switching. Run backend suite, frontend lint/build, browser scenarios and populated migration upgrade. Verify public ledger only in development. Update DEVELOPMENT_LEDGER.md per completed/validated feature and record limitations honestly. Successful completion yields 23 built / 1 pending; do not count unverified work as complete.
