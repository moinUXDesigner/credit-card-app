# Credit Card Usage Optimizer

A personal credit-card management app for users who hold multiple Indian credit cards. It helps decide which card to use, tracks annual-fee waiver progress, monitors credit utilization, reminds about due/statement dates, and tracks unused card benefits (lounge access, cashback caps, reward points, etc.).

This is **not** a payment app — it never stores full card numbers, CVV, PIN, OTP, or banking credentials. Only last 4 digits, network, limits, dates, fee rules, benefits, and manually-entered outstanding/spend figures are stored. It also never claims to "improve your CIBIL score" — only that it supports healthier credit card usage (utilization and payment-history awareness).

## Stack

- **Backend**: PHP Laravel + MySQL, JWT auth (`php-open-source-saver/jwt-auth`), run via Docker Compose (PHP-FPM + Nginx + MySQL).
- **Frontend**: React + Vite + Tailwind CSS, Zustand for auth state, React Hook Form + Zod for validation. Runs locally with the Vite dev server — it is **not** containerized and talks to the backend only over HTTP.

## Project structure

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
docker compose up --build -d
docker compose exec app composer install   # first time only
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

Deferred for a later phase: SMS/email/PDF statement parsing, AI-assisted recommendations, family/multi-user card sharing, cloud sync, Capacitor/Android packaging.

## Security notes

- No card number, CVV, PIN, or OTP fields exist anywhere in the schema or API — only `last_four_digits` and `network`.
- Per-resource ownership is enforced via Laravel Policies (`CardPolicy`, `BenefitPolicy`); every resource test suite checks that one user cannot view/update/delete another user's records.
