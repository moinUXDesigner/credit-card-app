# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project overview

A credit card management app: a Laravel API backend (`backend/`) and a separate React SPA frontend (`frontend/`), connected only over HTTP via JWT-authenticated JSON requests — they are not built or served together. `docker-compose.yml` runs the backend stack (nginx + php-fpm + MySQL); the frontend runs independently via Vite dev server and talks to the API at `VITE_API_BASE_URL` (`frontend/.env`).

Note: `backend/resources/{js,css,views}` and `backend/vite.config.js` are unused leftovers from the Laravel starter template — the backend is a pure JSON API, it does not render or serve the frontend.

## Commands

### Backend (`backend/`)

- Install: `composer install`
- Run full local stack (server + queue + logs + Vite): `composer run dev`
- Run tests: `composer run test` (or `php artisan test`)
- Run a single test: `php artisan test --filter=test_user_can_create_card` (or target a file: `php artisan test tests/Feature/CardCrudTest.php`)
- Lint/format: `vendor/bin/pint` (Laravel Pint)
- Tests use an in-memory SQLite DB (`phpunit.xml`), not MySQL — no Docker/DB setup needed to run `php artisan test`.

### Frontend (`frontend/`)

- Install: `npm install`
- Dev server: `npm run dev`
- Build: `npm run build`
- Lint: `npm run lint` (oxlint)

### Docker (full backend stack: nginx + php + MySQL)

- `docker-compose up` from the repo root. Backend is reachable at `http://localhost:${APP_PORT:-8080}`.

## Architecture

### Backend: Laravel JSON API with JWT auth

- Auth uses `php-open-source-saver/jwt-auth`, configured in `config/auth.php` (the `api` guard uses driver `jwt`) and `config/jwt.php`. `App\Models\User` implements `JWTSubject`.
- All endpoints are defined in `routes/api.php`, prefixed `/api` automatically. Auth endpoints (`/auth/register`, `/auth/login`, `/auth/refresh`, `/auth/logout`, `/auth/me`) live in `AuthController`; resourceful card endpoints (`/cards`) live in `CardController` and are protected by `auth:api` middleware.
- Authorization for per-resource ownership checks is done via Laravel Policies (`app/Policies/CardPolicy.php`), not in controllers — a `Card` can only be viewed/updated/deleted by its owning `User` (`user_id` match). Controllers call `$this->authorize(...)`.
- Validation lives in Form Request classes under `app/Http/Requests/` (e.g. `StoreCardRequest`, `UpdateCardRequest`, `Auth/LoginRequest`, `Auth/RegisterRequest`), not inline in controllers.
- API responses are shaped through `app/Http/Resources/` (`CardResource`, `UserResource`) rather than returning Eloquent models directly.
- The `Card` model (`app/Models/Card.php`) declares `benefits()` and `spendEntries()` relations to `Benefit` and `MonthlySpendEntry` models — these models/migrations do not exist yet. Only the `cards` table is migrated so far (`database/migrations/2026_06_29_115257_create_cards_table.php`); benefits/spend tracking is unbuilt.
- `Card` has decimal-cast money/points fields and a `best_categories` JSON-cast array; see the model and its migration together when changing the schema, since defaults are duplicated in `protected $attributes` on the model.

### Frontend: React SPA

- Routing is centralized in `src/router.jsx` using `createBrowserRouter`; protected routes are nested under `<ProtectedRoute />` then `<AppShell />` (layout shell with nav).
- Auth state (`token`, `user`) is a persisted Zustand store (`src/store/authStore.js`, localStorage key `ccapp-auth`).
- All HTTP calls go through the shared axios instance in `src/api/client.js`, which attaches the bearer token from `authStore` on every request and auto-refreshes via `/auth/refresh` on a 401, retrying the original request once; if refresh fails it logs out and hard-redirects to `/login`.
- Domain-specific API calls are grouped into `src/api/auth.js` and `src/api/cards.js`; data fetching/mutation hooks live in `src/hooks/` (e.g. `useCards.js`).
- Form validation uses `react-hook-form` + `zod` resolvers, with schemas in `src/schemas/` (e.g. `cardSchema.js`).
- Several routed pages (`Dashboard`, `Benefits`, `CalendarPage`, `Reports`, `Recommendation`, `Comparison`) are currently stub placeholders (~8 lines) with no corresponding backend endpoints — only auth and card CRUD are fully implemented end-to-end.

## Testing conventions (backend)

- Feature tests (`tests/Feature/`) use `RefreshDatabase` and authenticate by generating a JWT directly with `auth('api')->login($user)` rather than hitting the login endpoint — see `CardCrudTest.php` for the pattern (`authHeaders()` helper).
- Ownership-boundary tests are expected for any new per-user resource: every existing resource test file checks that one user cannot view/update/delete another user's records (expect `403`), in addition to standard CRUD and validation (`422`) and unauthenticated (`401`) cases.
