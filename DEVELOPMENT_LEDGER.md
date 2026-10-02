# Development ledger

Updated: 1 October 2026 (Asia/Kolkata).

Available in the app under **Development Ledger** at `/development-ledger`. The page reads this file and provides scope/status filters, search, pending tasks, and development history.

When running `npm run dev`, the ledger is accessible without login, including a link from the login page. Production builds keep the ledger behind authentication; all other application pages remain protected.

This ledger compares the README scope with the current backend and frontend source. **Built** means an implementation exists; it does not imply production readiness. The backend regression suite and browser integration suite were run for the implemented feature work; detailed results are recorded below. Both demo account logins were verified through the local HTTP API during this session.

## Scope and delivery

| ID | Feature / scope | Status | Built evidence | Pending work / verification |
| --- | --- | --- | --- | --- |
| DEV-01 | Register, login, logout, token refresh | Built | `AuthController`, Login/Register pages, `AuthTest` | Verify browser flows and token expiry; JWT/application configuration now persists in the ignored backend environment file. |
| DEV-02 | Demo and admin accounts | Built | Repeatable `DemoUsersSeeder`; local demo/admin logins; `users:role` command | Admin now has account operations access. Seeder does not reset existing passwords. |
| DEV-03 | Admin role and account operations | Built | Roles, suspension middleware, audited role command, admin API/UI; authorization and suspension tests | Admin has no automatic personal card access. Hosting and operational monitoring remain external. |
| DEV-04 | Card create, read, update, delete | Built | `CardController`, MyCards/CardForm/CardDetail pages, `CardCrudTest` | Verify full browser lifecycle. |
| DEV-05 | Card limits, billing dates, fee rules | Built | Card model, form and resources | Verify representative card data and date boundaries. |
| DEV-06 | Annual fee waiver tracking | Built | `WaiverService`, progress component, `WaiverServiceTest` | Verify progress and suggested monthly spend in the UI. |
| DEV-07 | Per-card and overall utilization | Built | `UtilizationService`, dashboard, utilization bars, unit tests | Verify threshold bands and overall aggregation in the UI. |
| DEV-08 | Shared credit limit groups | Built extension | `shared_limit_group` form field, `SharedLimitGroupTest` | Verify grouped limit behavior with multiple cards. This does not provide family account sharing. |
| DEV-09 | Benefits and usage logging | Built | `BenefitController`, Benefits page, card benefits panel, `BenefitTest` | Verify usage limits and resets in the UI. |
| DEV-10 | Card recommendation and monthly plan | Built | `RecommendationController`, `RecommendationService`, Recommendation page, tests | Verify scoring and category/monthly-plan results with realistic data. |
| DEV-11 | Card comparison | Built | `ComparisonController`, Comparison page | Verify comparison output and empty states. |
| DEV-12 | Monthly spend logging and reports | Built | `MonthlySpendEntryController`, `ReportService`, Reports page, tests | Verify report totals, waiver/utilization output, and reward estimates. |
| DEV-13 | Dashboard and billing calendar | Built | `DashboardController`, Dashboard/Calendar pages, `DashboardTest` | Verify upcoming dates and alert states. |
| DEV-14 | Due/statement reminder emails | Built; operations pending | Reminder command, mail class, daily schedule in `bootstrap/app.php`, reminder tests | Configure delivery and a running scheduler; verify a real reminder arrives. Compose defines no dedicated scheduler service. |
| DEV-15 | Card import | Built extension | `CardImportController`, `CardImportService`, CardImport page, `CardImportTest` | Verify supported import payloads through the UI. |
| DEV-16 | AI card analysis | Built; integration verification pending | Card analysis controller/service/upload component, analysis tests and fallback tests | Configure provider credentials and verify a real request. |
| DEV-17 | PDF statements and transaction extraction | Built; integration verification pending | Statement controller, analysis/text extraction services, upload/list UI, `StatementTest` | Verify real PDFs, extraction accuracy, provider configuration, and failure behavior. README's blanket PDF deferral is stale. |
| DEV-18 | Category spend analysis | Built extension | `SpendAnalyzerService`, category report API, SpendAnalyzer page/charts, tests | Verify totals from manual and extracted transactions. |
| DEV-19 | Per-user resource ownership | Built | Card/Benefit/Statement policies and feature tests | Run the current suite; no security audit was performed for this ledger. |
| DEV-20 | Manual SMS/email ingestion | Built | Deterministic parser, UTF-8 TXT/EML preview and atomic confirmation, duplicate review, editable import UI; parser/aggregation tests | Manual import only; automatic inbox/device SMS integration remains deferred. Real bank formats may need parser extensions. |
| DEV-21 | AI-assisted recommendation explanations | Built; live provider verification pending | Authenticated throttled endpoint, candidate/order validation, 30-second timeout, explanation UI and deterministic fallback tests | Existing ranking stays authoritative. Validate live provider behavior with configured credentials. |
| DEV-22 | Per-card family sharing | Built | Owner-controlled viewer/editor invitations, acceptance/expiry/revocation, nested policies, shared aggregates and sharing UI; permission tests | Complete card data is shared. Reminders stay owner-only; no ownership transfer. Offline copies cannot be instantly revoked. |
| DEV-23 | Offline account synchronization | Built | Revision/event/receipt APIs; service worker, account-partitioned IndexedDB, dependent mutation/file queues, conflict UI, Sync Center; backend/browser tests | Full snapshots prioritize correct revocation over bandwidth. Hosted HTTPS/backend provisioning remains external; browser storage can be evicted. |
| DEV-24 | Capacitor / Android packaging | Deferred scope | No Android dependencies or project added | Web app only for this implementation; Android will be planned later. |

## Pending development and operational work

The five selected web features have implementations and regression coverage. Android remains the only unbuilt feature among the 24 tracked scope entries. These operational prerequisites remain separate from feature counts.

| Priority | Item | Completion evidence |
| --- | --- | --- |
| Medium | Live AI integration verification | Configure provider credentials and verify card/PDF analysis and recommendation explanations with real inputs. Fallback and mocked provider behavior are tested. |
| Medium | Reminder delivery and scheduler | Configure SMTP and a running scheduler; verify real reminder delivery. No scheduler container was added. |
| Medium | Hosted rollout | Provision HTTPS hosting, preserve secrets/private files, back up the database, apply additive migrations and verify representative legacy spend attribution. Hosting is outside this implementation. |

## Session development history

| Date | Change | Result |
| --- | --- | --- |
| 1 October 2026 | Added repeatable `DemoUsersSeeder` | Creates missing demo/admin-named users without resetting existing passwords. |
| 1 October 2026 | Created both users in the running database | Password hashes verified. No admin privileges exist. |
| 1 October 2026 | Diagnosed login HTTP 500 | Missing JWT signing secret prevented token creation. |
| 1 October 2026 | Generated container JWT secret and cleared config | Both accounts returned HTTP 200 with login tokens. Secret persistence was subsequently completed with the development source/environment mount. |
| 1 October 2026 | Created this ledger | Source-based delivery inventory and pending work recorded. |
| 1 October 2026 | Added the in-app Development Ledger page | Navigation, counts, filters, search, pending tasks, and history implemented. Frontend production build and page lint passed; browser visual verification was subsequently completed during feature delivery. |
| 1 October 2026 | Implemented admin operations | Audited roles/suspension, admin UI and API boundaries verified. |
| 1 October 2026 | Implemented manual message ingestion | Preview/confirmation, duplicates, credits, and manual-spend preservation tested. |
| 1 October 2026 | Implemented AI recommendation explanations | Deterministic ranking retained; mocked structured output and fallback verified. |
| 1 October 2026 | Implemented per-card sharing | Viewer/editor invitations, acceptance, nested policies, shared aggregates and revocation verified. |
| 1 October 2026 | Implemented offline account sync | Durable queues, revisions/receipts, conflicts, uploads, quota, revocation and account cleanup verified. |
| 1 October 2026 | Deferred Android by user direction | Web app only; no Android project/dependencies added. |

## Implementation validation

- Final checks: **154 backend tests passed (426 assertions); 12 browser tests passed; frontend lint and production build passed.** The populated migration fixture and production offline UI are included.
- Backend: admin boundaries, suspension and token refresh, audited roles, invitation/permission matrix, shared aggregates, deterministic message parsing, duplicates/manual spend, AI output/fallback, revision privacy, idempotent replay and upload retry, revoked replay authorization, and a populated legacy migration upgrade.
- Browser: development/public versus production/authenticated ledger, offline reload, card/benefit/spend dependency replay, cross-session conflicts, queued files/imports, 100 MB quota, revoked data purge, logout cleanup, expired-session preservation, and production UI card creation while offline.
- Frontend production build and lint are part of the final checks. Build retains upstream Lottie eval and bundle-size warnings.
- No hosted deployment, real provider call, email delivery, or Android build is claimed.

## Scope boundaries

The product manages card metadata and spending decisions. Payments, storage of full card numbers/CVV/PIN/OTP/banking credentials, and claims of improving CIBIL scores are outside the documented scope. No delivery dates or owners have been assigned to pending items.

Update the relevant row whenever scope or implementation changes. Promote a pending item to built only with concrete implementation evidence, and record validation separately.
