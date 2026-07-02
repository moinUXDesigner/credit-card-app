# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project overview

A personal credit-card management app for users who hold multiple Indian credit cards: which card to use, annual-fee waiver progress, credit utilization, due/statement date reminders, and benefit tracking (lounge access, cashback caps, reward points). It is **not** a payment app — it never stores full card numbers, CVV, PIN, OTP, or banking credentials (only last 4 digits, network, limits, dates, fee rules, benefits, and manually-entered spend figures). It also never claims to "improve your CIBIL score," only that it supports healthier utilization/payment-history habits.

Stack: a Laravel API backend (`backend/`) and a separate React SPA frontend (`frontend/`), connected only over HTTP via JWT-authenticated JSON requests — they are not built or served together. `docker-compose.yml` runs the backend stack (nginx + php-fpm + MySQL); the frontend runs independently via Vite dev server and talks to the API at `VITE_API_BASE_URL` (`frontend/.env`).

Note: `backend/resources/{js,css,views}` and `backend/vite.config.js` are unused leftovers from the Laravel starter template — the backend is a pure JSON API, it does not render or serve the frontend.

## Commands

### Backend (`backend/`)

- Install: `composer install`
- Run full local stack (server + queue + logs + Vite): `composer run dev`
- Run tests: `composer run test` (or `php artisan test`)
- Run a single test: `php artisan test --filter=test_user_can_create_card` (or target a file: `php artisan test tests/Feature/CardCrudTest.php`)
- Lint/format: `vendor/bin/pint`
- Tests use an in-memory SQLite DB (`phpunit.xml`), not MySQL — no Docker/DB setup needed to run `php artisan test`.

### Frontend (`frontend/`)

- Install: `npm install`
- Dev server: `npm run dev`
- Build: `npm run build`
- Lint: `npm run lint` (oxlint)

### Docker (full backend stack: nginx + php + MySQL)

- `docker compose up --build -d` from the repo root, then (first time) `docker compose exec app composer install && docker compose exec app php artisan migrate`.
- API reachable at `http://localhost:${APP_PORT:-8080}/api`; phpMyAdmin (against the `mysql` service) at `http://localhost:${PMA_PORT:-8081}`.
- CORS (`backend/config/cors.php`) allows any `localhost`/`127.0.0.1` port in development, so a Vite port bump (5173 → 5174, etc.) doesn't need manual config changes.

## Architecture

### Backend: Laravel JSON API with JWT auth

- Auth uses `php-open-source-saver/jwt-auth`, configured in `config/auth.php` (the `api` guard uses driver `jwt`) and `config/jwt.php`. `App\Models\User` implements `JWTSubject`.
- All endpoints are defined in `routes/api.php`, prefixed `/api` automatically:
  - `AuthController`: `/auth/register`, `/auth/login` (public), `/auth/refresh`, `/auth/logout`, `/auth/me` (behind `auth:api`).
  - Everything else lives behind a single `auth:api` group: `apiResource('cards', CardController)`; `apiResource('cards.benefits', BenefitController)->shallow()` plus `POST benefits/{benefit}/mark-used`; `GET recommendation`; `GET comparison`; `GET/POST cards/{card}/spend-entries` (`MonthlySpendEntryController`); `GET reports/monthly`; `GET dashboard`.
- Authorization for per-resource ownership checks is done via Laravel Policies (`app/Policies/CardPolicy.php`, `BenefitPolicy.php`), not in controllers — controllers call `$this->authorize(...)`. A `Card`/`Benefit` can only be viewed/updated/deleted by its owning `User` (traced through `card.user_id`).
- Validation lives in Form Request classes under `app/Http/Requests/` (`StoreCardRequest`, `UpdateCardRequest`, `StoreBenefitRequest`, `UpdateBenefitRequest`, `StoreMonthlySpendEntryRequest`, `Auth/LoginRequest`, `Auth/RegisterRequest`), not inline in controllers.
- API responses are shaped through `app/Http/Resources/` (`CardResource`, `BenefitResource`, `MonthlySpendEntryResource`, `UserResource`) rather than returning Eloquent models directly.
- Business logic for scoring/aggregation lives in `app/Services/`, injected into controllers rather than computed inline:
  - `UtilizationService` — per-card/overall utilization % and a 4-tier band (`good` / `caution` / `avoid_further_use` / `urgent_repayment`, thresholds at 30/50/75%). Cards can pool one physical credit line via `Card.shared_limit_group` (nullable string, e.g. two HDFC cards sharing a combined limit) — `Card::limitGroupCards()` resolves siblings, `cardUtilization()` sums outstanding across the group against the (shared) `total_limit`, and `overallUtilization()` counts a group's `total_limit` only once. `StoreCardRequest`/`UpdateCardRequest` reject a card joining a group whose `total_limit` doesn't match its siblings.
  - `WaiverService` — remaining spend, progress %, months left until the card's fee-year anniversary (`card_year_start_month`), suggested monthly spend, and an urgency score feeding the recommendation engine.
  - `RecommendationService` — combines `WaiverService` urgency, reward-category match (`best_categories`/`reward_rate_general`), unused-benefit score, and penalties for utilization/upcoming due date into a single `total_score` + breakdown per card (`recommend()` sorts descending).
  - `ReportService` — builds the `reports/monthly` payload (total spend, best card used, waiver/utilization snapshots, unused benefits, potential missed reward value) for a given year/month.
  - `DateOccurrenceService` — resolves the next occurrence of a recurring day-of-month (statement/due dates), clamping to shorter months (e.g. day 31 in February).
- Data model: `Card` (`user_id`) `hasMany` `Benefit` and `MonthlySpendEntry`; `Benefit` `hasMany` `BenefitUsageLog` (written by `BenefitController::markUsed`, which also increments `Benefit.used_count`). `Card` has decimal-cast money/points fields and a `best_categories` JSON-cast array — defaults are duplicated in `protected $attributes` on the model, so check the model and its migration together when changing the schema.
- `php artisan reminders:send-due-dates` (`app/Console/Commands/SendDueDateReminders.php`, mailable in `app/Mail/DueDateReminder.php`) is scheduled daily at 08:00 via `withSchedule()` in `bootstrap/app.php` (not a `Kernel.php` — this app uses Laravel's new bootstrap-based structure).

### Frontend: React SPA

- Routing is centralized in `src/router.jsx` using `createBrowserRouter`; protected routes are nested under `<ProtectedRoute />` then `<AppShell />` (layout shell with nav).
- Auth state (`token`, `user`) is a persisted Zustand store (`src/store/authStore.js`, localStorage key `ccapp-auth`).
- All HTTP calls go through the shared axios instance in `src/api/client.js`, which attaches the bearer token from `authStore` on every request and auto-refreshes via `/auth/refresh` on a 401, retrying the original request once; if refresh fails it logs out and hard-redirects to `/login`.
- Domain-specific API calls are grouped per-resource under `src/api/` (`auth.js`, `cards.js`, `benefits.js`, `comparison.js`, `dashboard.js`, `recommendations.js`, `reports.js`).
- Only `cards` and `benefits` have dedicated data hooks (`src/hooks/useCards.js`, `useBenefits.js` — manual `useState`/`useEffect`/`useCallback`, no react-query). Other pages (`Dashboard`, `Reports`, `Recommendation`, `Comparison`, `CalendarPage`) call their `src/api/*.js` functions directly inside a page-level `useEffect`; follow that lighter pattern rather than adding a hook unless the fetch logic needs to be reused across components.
- Form validation uses `react-hook-form` + `zod` resolvers, with schemas in `src/schemas/` (`cardSchema.js`, `benefitSchema.js`).

## Testing conventions (backend)

- Feature tests (`tests/Feature/`) use `RefreshDatabase` and authenticate by generating a JWT directly with `auth('api')->login($user)` rather than hitting the login endpoint — see `CardCrudTest.php` for the pattern (`authHeaders()` helper).
- Ownership-boundary tests are expected for any new per-user resource: every existing resource test file checks that one user cannot view/update/delete another user's records (expect `403`), in addition to standard CRUD, validation (`422`), and unauthenticated (`401`) cases.
- Existing suites to mirror when adding related tests: `AuthTest`, `CardCrudTest`, `BenefitTest`, `RecommendationEndpointTest`, `ReportTest`, `DashboardTest`, `SendDueDateRemindersTest`.
