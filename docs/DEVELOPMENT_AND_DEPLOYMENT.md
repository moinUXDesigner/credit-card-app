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
- **Statements:** click a card or its Upload statement action to open `/cards/:id?tab=statements`. PDFs are analyzed into a private 24-hour preview, then reviewed before importing. Add Card can attach its reviewed PDF as the first statement. Exact reuploads do not add spend; overlapping transactions need explicit skip/keep decisions. Newer manual/message balances and newer statements are protected from historical overwrites. Failed extraction supports retry or PDF-only saving. Upload an unlocked PDF for password-protected documents. Run `statements:cleanup-previews` through the existing hourly scheduler (or manually) to remove abandoned drafts; attached PDFs remain private.

- **Spending:** monthly manual amounts remain separate from imported purchase contributions. Credits/payment receipts do not increase purchases. The migration preserves totals and subtracts known historical PDF contributions from the manual backfill so they are not counted twice on the next recomputation. Historical source attribution cannot be recovered when old manual entries overwrote imported totals; review unusual legacy totals before deployment.
- **AI:** `/recommendation` has an Explain recommendation action. Ranking is always deterministic. Explanations use only card labels and scores; no personal documents or messages are sent. The provider request has a 30-second timeout and a six-per-minute user limit. Missing configuration/failure/invalid card IDs or order uses score-based explanations. Live provider behavior still requires credentials and integration verification.
- **Offline:** `/sync` enables device-local account storage, caches the shell/records, and queues card/benefit/spend changes, deletions, card imports and PDF uploads. Uploaded files remain queued until server processing and then await review; balances/spend change only after confirmation. Confirmed imports retain the PDF in the file queue when available; expired previews preserve the PDF as pending and require fresh review. AI, admin, sharing and message parsing require online access. Each file follows the API's 15 MB limit; pending files total at most 100 MB per account. Cached reports show last downloaded values until refreshed online. PDF downloads are cached only if previously requested; the bootstrap contains statement metadata, not PDF blobs.
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


## Reviewed statement API

- `POST /api/statement-previews`: multipart `file` (PDF, maximum 15 MB), optional `card_id`; alternatively `statement_id` reanalyzes an existing pending/PDF-only statement. Returns `preview_id`, expiry, destination revision, detected summary, editable rows, duplicate hints, warnings and default summary selections.
- `GET /api/statement-previews/{id}`: requester-owned draft, with card edit access rechecked. Drafts expire after 24 hours.
- `POST /api/cards/{card}/statements`: confirm with `preview_id`, UUID `idempotency_key`, card `revision`, billing month/year, reviewed `summary`/`rows`, `apply_summary` selections, `acknowledge_identity`, and `save_pdf_only`. Dates use YYYY-MM-DD; amounts are positive with purchase/credit directions. Identity mismatches block import. Raw multipart file submissions stage pending review instead of applying extraction.
- Migration is additive. Legacy statements have unknown source provenance; newer dated legacy statements establish an import cutoff. Structured summaries keep source/previous-value history for deletion recovery. PHPUnit forces SQLite `:memory:` even when Docker sets `DB_DATABASE`.


## Queued AI statements and Card Chat — 4 October 2026

Set server-side `OPENAI_API_KEY` to enable AI PDF reading and Card Chat. `OPENAI_PDF_MODEL` and `OPENAI_CHAT_MODEL` override `OPENAI_MODEL`; choose Responses-compatible models supporting PDF inputs and function calling. Issuer research also requires web search support. Credentials never belong in frontend variables.

Deploy additive migrations before the frontend and start `docker compose up -d --build worker`. Outside Docker, run `php artisan queue:work database --queue=ai --sleep=2 --timeout=300 --tries=1` under a process manager. Database queue retry-after is 420 seconds, exceeding job timeouts. Run the Laravel scheduler every minute; `ai:expire-requests` recovers requests stuck for ten minutes. Restart workers after backend/config deployments using `php artisan queue:restart`. Persist private statement storage across deployments.

PDF previews return HTTP 202 while processing. Poll the existing preview GET until ready/failed. AI reads the entire uploaded PDF, validates structured output and falls back to local extraction on provider failure. No card balances or spends change until review confirmation. Add Card retains its source PDF and reuses this preview instead of uploading/extracting twice.

`/chat` saves private conversations with an all-accessible-cards or single-card scope and optional selected statement. Routes are `GET/POST /api/chat/conversations`, `GET/DELETE /api/chat/conversations/{id}`, `POST /api/chat/conversations/{id}/messages` (content plus UUID client_id), and `GET /api/ai/requests/{id}`. Chat is online-only and excluded from offline response caching. Retry reuses client IDs; only one answer runs per conversation. Current card access is rechecked before tool calls and delivery. Revoked context restricts history, but its owner can delete it.

Chat computes balances, spending and recommendations through authorized backend tools. Public issuer research runs separately with product labels only and an official-domain allowlist; financial context is excluded. Answers link supporting cards/statements or official pages. Chat cannot alter financial records. Processing limits, rate limits, request durations and token usage provide operational bounds; logs omit financial content and provider payloads.

Without a key, PDF local fallback remains available and chat preserves the draft while explaining configuration is missing. This repository's local environment currently has no configured key; live extraction quality and issuer responses require a configured provider smoke test.
