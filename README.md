# Credit Card Usage Optimizer

A personal credit-card management app for users who hold multiple Indian credit cards. It helps decide which card to use, tracks annual-fee waiver progress, monitors credit utilization, reminds about due/statement dates, and tracks unused card benefits (lounge access, cashback caps, reward points, etc.).

This is **not** a payment app — it never stores full card numbers, CVV, PIN, OTP, or banking credentials. Structured card records contain only last 4 digits, network, limits, dates, fee rules, benefits, and reviewed outstanding/spend figures. Optional uploaded statement PDFs are stored privately and may contain the original document’s personal/account details. It also never claims to "improve your CIBIL score" — only that it supports healthier credit card usage (utilization and payment-history awareness).

## Stack

- **Backend**: PHP Laravel + MySQL, JWT auth (`php-open-source-saver/jwt-auth`), run via Docker Compose (PHP-FPM + Nginx + MySQL).
- **Frontend**: React + Vite + Tailwind CSS, Zustand for auth state, React Hook Form + Zod for validation. Runs locally with the Vite dev server — it is **not** containerized and talks to the backend only over HTTP.

## Project structure

See the [development ledger](DEVELOPMENT_LEDGER.md) for scoped features, built implementations, pending work, and verification status.

```
credit-card-app/
├── docker-compose.yml      # app (PHP-FPM), nginx, mysql
├── docker/                 # Dockerfile + nginx vhost config
├── backend/                # Laravel JSON API
└── frontend/               # React SPA
```

## Getting started

### 1. Backend (Docker)

```bash
cp .env.example .env                  # root compose env (DB creds, ports)
cp backend/.env.example backend/.env  # persistent Laravel configuration
docker compose up --build -d
docker compose exec app composer install   # first time only, includes test dependencies
docker compose exec app php artisan key:generate
docker compose exec app php artisan jwt:secret
docker compose exec app php artisan migrate
```

The API is reachable at `http://localhost:8080/api`. phpMyAdmin is reachable at `http://localhost:8081` (login with the `DB_USERNAME`/`DB_PASSWORD` from `.env`).

### 2. Frontend (local Vite dev server)

```bash
cd frontend
cp .env.example .env        # sets VITE_API_BASE_URL=http://localhost:8080/api
npm install
npm run dev
```

Open the URL Vite prints (usually `http://localhost:5173`). If that port is taken, Vite picks the next free one (5174, etc.) — the backend's CORS config (`backend/config/cors.php`) allows any `localhost`/`127.0.0.1` port in development, so this doesn't require manual changes.

### Running backend tests

```bash
docker compose exec app php artisan test
```

Tests run against an in-memory SQLite database (`phpunit.xml`), not MySQL, so no extra setup is needed.

## Core features (MVP)

- JWT auth (register/login/refresh/logout)
- Card CRUD with per-card limits, dates, and fee/waiver rules
- Annual-fee waiver tracker (progress, months left, suggested monthly spend)
- Credit utilization monitor (per-card and overall, banded: good / caution / avoid further use / urgent repayment)
- Benefits tracker (lounge visits, cashback caps, reward points, etc.) with usage logging
- Scored "which card should I use" recommendation engine (optionally filtered by spend category)
- Card comparison table
- Monthly spend logging and reports (total spend, best card used, waiver/utilization snapshot, unused benefits, potential missed rewards)
- Dashboard summary (overall utilization, upcoming due/statement dates, waiver alerts, unused benefits) and a calendar view of each card's key dates
- Daily due-date reminder email job (`php artisan reminders:send-due-dates`, scheduled via `bootstrap/app.php`)

Additional web features: admin account operations, manual SMS/email message import with editable previews, PDF statement analysis, AI explanations of deterministic recommendations, per-card viewer/editor invitations, and offline account synchronization. Enable offline storage through Sync Center on a trusted device.

Click a card to view its details, or choose **Upload statement** to open its Statements tab. Review extracted dates, balances and transactions before importing. A PDF used during Add Card can be saved as the first statement; exact reuploads do not duplicate spend. If a statement shows fewer than four ending digits, enter the full last four manually. Offline PDFs await review after synchronization.

Deferred: automatic mailbox/device-SMS ingestion and Capacitor/Android packaging. This implementation is a web app only; Android will be planned later.

See [deployment and feature operations](docs/DEVELOPMENT_AND_DEPLOYMENT.md) for permissions, synchronization contracts, limits, and validation commands.

## Security notes

- No card number, CVV, PIN, or OTP fields exist anywhere in the schema or API — only `last_four_digits` and `network`.
- Per-resource ownership is enforced via Laravel Policies (`CardPolicy`, `BenefitPolicy`); every resource test suite checks that one user cannot view/update/delete another user's records.

AI PDF reading and the private Card Chat page are available with a server-side OpenAI key and the `ai` queue worker. PDF reading falls back to local extraction when AI is unavailable. See [setup and API details](docs/DEVELOPMENT_AND_DEPLOYMENT.md#queued-ai-statements-and-card-chat--4-october-2026) and the [implementation plan](codex-plans/ai-statements-and-card-chat.md).
