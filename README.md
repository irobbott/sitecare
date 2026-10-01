# SiteCare

SiteCare is a website maintenance workspace for a small agency and its clients. Clients can submit a site for review and follow support tickets. Staff can review the submitted work, assign tickets and run scheduled availability checks. This repository is an early, usable foundation for that product, with a polished React dashboard and a Laravel REST API.

## What is implemented

- Laravel 13 API and React 19 + TypeScript single-page application.
- Cookie-based SPA authentication through Laravel Sanctum; there is no public registration route.
- Organisation, user, website, ticket, comment, uptime-check, incident, backup-record, invitation and audit-log tables.
- Client website submissions start pending and are not monitored until activated by staff.
- Administrator invitation APIs create random expiring one-time tokens, queue email and accept/revoke invitations; the invited user has a small browser acceptance form.
- Staff can add maintenance and backup records. Backup webhooks use encrypted per-site secrets, HMAC-SHA256, a five-minute replay window and per-site event-id deduplication.
- Clients can view client-visible maintenance entries, backup summaries and incidents; staff can acknowledge incidents.
- Organisation-scoped website and ticket queries, role checks, internal comment filtering, ticket status transitions and audit entries for implemented changes.
- A queued monitor command with a configurable minimum interval and basic incident open/recovery handling.
- Local MySQL, queue worker, scheduler and Mailpit services in Compose.
- Responsive dashboard UI with demo preview data.

The brief describes a much wider product than this first implementation. Password reset, richer admin screens, file attachments, SSL inspection, notification preferences, complete audit coverage, analytics, detailed health tabs and comprehensive automated coverage still need implementation. Backup entries are operator-reported; a signed event does not prove a backup can be restored. Do not use this version as a production service.

## Roles and tenant boundaries

Users have `admin`, `technician`, or `client` roles. Client requests are restricted to their organisation. Technicians see only tickets and sites assigned to them in the current API queries. Administrators can see all records. Ticket comments marked internal are excluded from client responses. Checks run server-side; frontend visibility is not the security boundary.

## Ticket workflow and response targets

Tickets begin open. Normal transitions are checked in the update endpoint across `open`, `triaged`, `assigned`, `in_progress`, `waiting_for_client`, `resolved`, and `closed`. An administrator can correct a status. Target dates use simple elapsed hours by priority (low 72, normal 24, high 4, urgent 1); weekends and holidays are not excluded, and waiting-on-client time is not paused. These dates are internal targets, not service guarantees.

## Monitoring behaviour and limits

Run `php artisan sitecare:monitor-due` to queue checks for active websites. Checks use a 4-second connection timeout, a 12-second request timeout, TLS verification, a fixed user agent and no redirect following. 2xx and 3xx responses count as available. Two consecutive recent failures open one incident; a successful check recovers it. The scheduler invokes the command every minute and unique jobs reduce duplicate dispatches.

`SafeWebsiteUrl` permits HTTP/HTTPS on ports 80/443, rejects credentials and non-public IP ranges, checks A and AAAA results, and pins the selected address in cURL. Redirects are not followed. This still needs a production egress proxy that blocks private, link-local, metadata and reserved ranges at the network layer. Monitoring currently records no SSL certificate details.

SiteCare records check results and operator-reported backup events; it does not control client hosting or create backups. A backup marked verified means an operator marked it that way; SiteCare has not tested a restore.

## Technology and layout

Backend: PHP 8.3+, Laravel 13, Sanctum, MySQL 8.4, PHPUnit. Frontend: React 19, TypeScript, Vite 7, React Router, TanStack Query, Axios, Tailwind CSS, React Hook Form and Zod dependencies. Runtime: Docker Compose, database queue and Mailpit. No Redis or paid monitoring service.

```text
backend/       Laravel REST API, migrations, seed data and monitoring job
frontend/      React TypeScript application
docker-compose.yml
openapi.yaml
postman/       Importable API collection
examples/      Backup signing example
docs/          Architecture notes
```

The API base is `/api/v1`. Implemented routes cover authentication, invitations, dashboard, website submission and review, tickets and comments, maintenance and backup records, signed backup webhooks, and incidents. `/api/health` and Laravel's `/up` are health checks. Cookie-authenticated SPA requests first fetch `/sanctum/csrf-cookie`.

## Backup records and webhook signing

Staff can record files, database, full-site or incremental backup results. Clients see summary fields without internal destination notes. SiteCare does not run or restore these backups.

An administrator rotates the per-website webhook secret with `POST /api/v1/websites/{website}/backup-webhook-secret`; copy the returned secret into the backup provider's secret store because it is shown once. The sender signs the exact request bytes with `HMAC-SHA256(secret, timestamp + newline + event-id + newline + raw-body)`, then sends the lowercase hex digest in `X-SiteCare-Signature`, Unix seconds in `X-SiteCare-Timestamp`, and a unique value in `X-SiteCare-Event-Id`. Requests older than five minutes and repeated event IDs are rejected. The standard-library Python example is [examples/backup_webhook.py](examples/backup_webhook.py); set `SITECARE_URL`, `SITECARE_WEBSITE_ID`, and `SITECARE_BACKUP_SECRET` before running it. Keep the secret in a local secret store; never commit it.

## Local setup without Docker

Use PHP 8.3 or later, Composer 2, Node 24, MySQL 8.4 and a running database. PHP 8.2 is not sufficient for Laravel 13.

```powershell
Copy-Item backend/.env.example backend/.env
# Set DB_DATABASE, DB_USERNAME and DB_PASSWORD for local MySQL.
cd backend
composer install
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

In another terminal:

```powershell
cd frontend
npm ci
npm run dev
```

Set `VITE_API_URL` in `frontend/.env` if the API is not at `http://localhost:8000`. Without local SMTP, use `MAIL_MAILER=log`. Start a worker and scheduler separately with `php artisan queue:work` and `php artisan schedule:work`.

## Docker setup

Docker Desktop with Compose v2 is required. Create the ignored local environment file, then start the services:

```powershell
Copy-Item backend/.env.example backend/.env
docker compose up --build
```

Open the app at `http://localhost:8080`, API at `http://localhost:8000`, and Mailpit at `http://localhost:8025`. The API container applies migrations at startup. Seed with `docker compose exec api php artisan db:seed`. `docker compose down -v` also deletes the local MySQL volume.

## Development accounts

After seeding, all accounts use the development-only password `password`:

- Administrator: `admin@sitecare.test`
- Technician: `tech@sitecare.test`
- Client: `client@sitecare.test`

These accounts are marked `is_demo`; never reuse these credentials outside local development.

## Tests and API references

```powershell
cd backend
php artisan test
cd ../frontend
npm test
npm run typecheck
npm run build
npm run lint
npm run test:e2e
```

The backend includes focused SSRF and tenant-isolation tests. The frontend has a dashboard preview, sign-in dialog, invitation acceptance form, one unit test and a Playwright smoke flow. Broader workflow coverage still needs to be added. OpenAPI and the Postman collection cover only implemented routes.

## Security and production notes

The app has no public registration endpoint. It uses Sanctum session cookies and CSRF protection. API queries scope client data to an organisation and filter internal notes. Website submissions reject credentials, unsupported protocols/ports and detected private/reserved IPs. Monitor requests have bounded timeouts and do not follow redirects.

Attachments, SSL monitoring, general email notifications, full password recovery and production-grade SSRF egress filtering remain future work. Before production, add those flows, policy and isolation tests, secrets management, TLS and ingress controls, application backups and deployment monitoring. Do not claim guaranteed uptime or that SiteCare runs a customer's backups.

See [docs/decisions.md](docs/decisions.md) for implementation choices. Monitoring uses Laravel's database queue so local setup does not require Redis. Response targets are elapsed-hour estimates. Retention and aggregation for health history still need a production policy.
