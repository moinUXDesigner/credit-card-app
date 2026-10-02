# Web development and deployment

## Development

The PHP service now mounts `backend/`, including its ignored `.env`, dependencies, and persistent local statement files. MySQL data uses the existing Docker volume. The Laravel environment must use `DB_CONNECTION=mysql`; tests use isolated SQLite. Copy both environment examples before first startup. Generate APP_KEY and JWT_SECRET once; preserve them across rebuilds. Regenerating JWT_SECRET invalidates existing sessions.

```sh
docker compose up --build -d
docker compose exec app composer install
docker compose exec app php artisan migrate
docker compose exec app php artisan db:seed --class=DemoUsersSeeder
docker compose exec app php artisan users:role admin@example.com admin
```

The development users are `demo@example.com` / `DemoUser@123` and `admin@example.com` / `AdminUser@123`. The seeder never resets an existing account's password. Do not seed demo accounts in a hosted production environment. Roles are assigned only through the command; public registration creates ordinary users. Administrators have account operations, not unrestricted access to personal cards.

## Feature behavior

- **Admin:** `/admin` supports paginated user search, suspension/reactivation, health, and audit events. Suspended users cannot use APIs or refresh tokens. Self-suspension and removal of the last active admin are denied.
- **Sharing:** `/sharing` invites an existing user as viewer/editor. Acceptance is required within seven days. Sharing includes metadata, spend, transactions and PDFs. Editors may modify data and manage statements/benefits; only owners delete cards or manage invitations/memberships. Reminder mail remains owner-only.
- **Message import:** `/import-messages` accepts pasted SMS/email text or UTF-8 `.txt`/`.eml` up to 5 MB, at most 200 rows. ISO dates are recognized; ambiguous dates/directions require correction. Preview changes no financial records. Confirmation is atomic and requires duplicate review. Raw messages are not retained. Account summaries change balances only when explicitly selected.
- **Spending:** monthly manual amounts remain separate from imported purchase contributions. Credits/payment receipts do not increase purchases. The migration preserves totals and subtracts known historical PDF contributions from the manual backfill so they are not counted twice on the next recomputation. Historical source attribution cannot be recovered when old manual entries overwrote imported totals; review unusual legacy totals before deployment.
- **AI:** `/recommendation` has an Explain recommendation action. Ranking is always deterministic. Explanations use only card labels and scores; no personal documents or messages are sent. The provider request has a 30-second timeout and a six-per-minute user limit. Missing configuration/failure/invalid card IDs or order uses score-based explanations. Live provider behavior still requires credentials and integration verification.
- **Offline:** `/sync` enables device-local account storage, caches the shell/records, and queues card/benefit/spend changes, deletions, card imports and PDF uploads. Uploaded files remain queued until server processing; nothing is displayed as extracted beforehand. AI, admin, sharing and message parsing require online access. Each file follows the API's 15 MB limit; pending files total at most 100 MB per account. Cached reports show last downloaded values until refreshed online. PDF downloads are cached only if previously requested; the bootstrap contains statement metadata, not PDF blobs.
- **Conflicts:** local work is retained; choose server values (discard the mutation/dependencies) or explicitly reapply the local payload against the latest revision. Invalid/access-denied items remain available for review. Temporary failures retry with exponential backoff capped at one minute. Expired sessions pause replay until login.
- **Device privacy:** account data is partitioned in IndexedDB. Explicit logout or account switching warns about pending changes and clears the previous account's local storage after confirmation. Session expiry preserves pending work. Shared data is purged after reconnect/revocation; offline devices cannot be remotely erased. Use only trusted devices. Browser storage eviction remains possible despite requesting persistent storage.

## API additions

Existing online endpoints remain compatible; resource responses add revisions and card permissions. Online updates may include a revision; sync updates require one. Public ledger access is limited to `import.meta.env.DEV`.

- `GET /api/admin/users?search=&page=`, `PATCH /api/admin/users/{user}/status` with `suspended`, `GET /api/admin/health`, `GET /api/admin/audit?page=`.
- `GET/POST /api/cards/{card}/sharing`; POST takes `email`, `permission`. `PATCH/DELETE /api/cards/{card}/sharing/{member}` updates permission or revokes. `GET /api/invitations`, `POST /api/invitations/{invitation}/accept`.
- `POST /api/cards/{card}/messages/preview` takes text or file; returns `preview_id`, rows, summary, duplicate status. `POST .../confirm` takes preview ID, corrected rows and `apply_summary`; candidate duplicates require `duplicate_action=skip|keep`. Previews expire after one hour.
- `POST /api/recommendation/explain` takes optional category and returns source plus ordered explanation rows.
- `GET /api/sync/bootstrap`, `GET /api/sync/changes?cursor=` return an authoritative accessible snapshot and account-scoped events/tombstones with a server cursor. Full snapshots intentionally purge revoked data; a future optimization may introduce incremental record payloads.
- `POST /api/sync/mutations` takes UUID `operation_id`, whitelisted method/path, payload and required target revision. JSON uses `payload`; multipart uses `payload_json` and optional `file`. Successful responses contain original status/data. Idempotency receipts and mutations commit atomically; replay rechecks access. 409 includes server values/revision, 410 means unavailable, 403 means denied, and 422 means invalid input. Retrying a UUID with changed content is rejected. Keep receipts and tombstones while offline clients may retry.

## Verification

```sh
docker compose exec app php artisan test
cd frontend
npm run lint
npm run build
npx playwright install chromium
npm run test:browser
```

Browser tests start Vite on 5178 and the production preview on 4174 and require the local backend on 8080. They create isolated `browser-*@example.test` fixtures; card resources are cleaned up on successful runs. Tests cover offline reload, dependent replay, conflicts, files/imports, quota, revoked sharing, logout, session expiry and production-only authentication. Failed test fixtures can be removed from the development database; never run these against hosted production.

## Hosting prerequisites and rollout

Provisioning hosting and Android are outside this delivery. Serve the web app over HTTPS (localhost is the development exception for service workers). Deploy backend contracts/migrations before the updated frontend. Use `APP_ENV=production`, `APP_DEBUG=false`, a stable APP_KEY/JWT_SECRET, correctly configured MySQL, and explicit FRONTEND_URL/CORS. Keep the frontend API URL on HTTPS to avoid mixed-content failures. Configure SPA routes to serve index.html, and serve sw.js without long immutable caching.

The compose source mounts are for development; a production deployment should use a built backend image and separately persistent environment, database, and private statement storage. Keep statement files outside public web roots and retain authenticated download routes. Configure PHP/proxy request limits to allow a 15 MB file plus multipart overhead (for example 20 MB request bodies), with application validation enforcing individual file limits. Back up data/files before migration; inspect legacy spend attribution on a representative restored database. Migrations are additive; do not use migrate:fresh on populated environments.

Provide OPENAI_API_KEY if live analysis/explanations are required. Missing credentials use documented fallbacks. Configure SMTP and a scheduler separately for reminder delivery; no scheduler container is provisioned by this feature work. Configure monitoring for failed sync operations, provider availability, storage, and reminder delivery without logging message content or authentication tokens.
