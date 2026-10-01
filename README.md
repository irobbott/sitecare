# SiteCare

SiteCare is a website maintenance workspace for agencies and the organisations they support. Clients can submit websites, open support requests and follow service history. Administrators and technicians can review work, assign tickets, monitor uptime and SSL certificates, record maintenance and backup events, and keep an audit trail. The repository contains a React dashboard and a Laravel REST API.

## What is implemented

- Laravel 13 API and React 19 + TypeScript single-page application.
- Cookie-based SPA authentication through Laravel Sanctum; there is no public registration route.
- Organisation, user, website, ticket, comment, private attachment, uptime-check, SSL-check, incident, backup-record, invitation, notification and audit-log tables.
- Client website submissions start pending and are not monitored until activated by staff.
- Administrators can create or suspend client organisations, manage team roles, and create or revoke invitations. Invitation and password-reset emails are queued; both flows use expiring, single-use tokens.
- Users can edit their profile, change their password, request a password reset and choose in-app and email notification preferences.
- Ticket attachments accept PNG, JPEG, WebP, PDF, plain text and ZIP files up to 5 MB. Files use randomized names in private storage and downloads check ticket access.
- Staff can add maintenance and backup records. Backup webhooks use encrypted per-site secrets, HMAC-SHA256, a five-minute replay window and per-site event-id deduplication.
- Clients can view client-visible maintenance entries, backup summaries and incidents; staff can acknowledge incidents.
- Organisation-scoped website and ticket queries, role checks, internal comment filtering, ticket status transitions and audit entries for implemented changes.
- Ticket categories are seeded with common maintenance request types. Administrators can add, disable and re-enable categories; old tickets keep their original category label.
- Administrators can configure elapsed-hour first-response and resolution targets by priority. Targets apply to new tickets and tickets whose priority changes; resolution time pauses while a ticket waits for the client. They are internal targets, not guarantees.
- Reports include role-scoped ticket status and priority counts, first-response timeliness and 30-day uptime metrics.
- A queued monitor command checks approved sites on configured intervals, records uptime and SSL certificate history, and opens or recovers incidents after consecutive failures.
- SSL checks pin the resolved public IP, request TLS metadata using the site's hostname for SNI, validate the certificate chain and hostname, and deduplicate expiry alerts at 30, 14, 7, 3 and 1 day thresholds.
- Ticket assignment, client replies, response and resolution target alerts, website reviews, incidents, SSL expiry and password recovery can create in-app and queued email notifications. Internal staff notes never notify clients.
- Local MySQL, queue worker, scheduler and Mailpit services in Compose.
- Responsive dashboard UI with demo preview data.
- Working website submission and health details, ticket assignment/comments/attachments, maintenance, backup, incident, organisation/team, profile, notification, password-reset and report screens that consume the REST API.

Some larger product areas still need more depth: richer analytics, complete audit coverage, business-hour response-target calculations, notification coverage for every event, and broader automated workflow coverage. Backup entries are operator-reported; a signed event does not prove a backup can be restored. Do not use this version as a production service.

## Roles and tenant boundaries

Users have `admin`, `technician`, or `client` roles. Client requests are restricted to their organisation. Technicians see only tickets and sites assigned to them in the current API queries. Administrators can see all records. Ticket comments marked internal are excluded from client responses. Checks run server-side; frontend visibility is not the security boundary.

## Ticket workflow and response targets

Tickets begin open. Normal transitions are checked in the update endpoint across `open`, `triaged`, `assigned`, `in_progress`, `waiting_for_client`, `resolved`, and `closed`. An administrator can correct a status. By default, first-response targets use elapsed hours by priority (low 72, normal 24, high 4, urgent 1); resolution targets default to low 240, normal 120, high 24, urgent 8 hours. Administrators can configure both from 1 to 720 hours. Resolution time pauses while a ticket is waiting for the client. The first client-visible staff reply is recorded. Weekends and holidays are not excluded. These dates are internal targets, not service guarantees.

## Monitoring behaviour and limits

Run `php artisan sitecare:monitor-due` to queue checks for active websites in active organisations. Checks use a 4-second connection timeout, a 12-second request timeout, TLS verification, a fixed user agent and no redirect following. 2xx and 3xx responses count as available. Two consecutive recent failures open one incident; a successful check recovers it. HTTPS checks record certificate subject, issuer, validity dates, chain validation and hostname matching. The scheduler invokes monitoring and ticket-target alerts every minute; unique jobs reduce duplicate monitor dispatches.

`SafeWebsiteUrl` permits HTTP/HTTPS on ports 80/443, rejects credentials and non-public IP ranges, checks A and AAAA results, and pins the selected address in cURL. SSL inspection uses that same validated public address and sends the original hostname as SNI. Redirects are not followed. This still needs a production egress proxy that blocks private, link-local, metadata and reserved ranges at the network layer.

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

The API base is `/api/v1`. Routes cover authentication, password reset, profile and notification settings, invitations and team access, organisation management, dashboard, website submission/review/health, tickets/comments/attachments, maintenance and backup records, signed backup webhooks, incidents and monitoring history. `/api/health` and Laravel's `/up` are health checks. Cookie-authenticated SPA requests first fetch `/sanctum/csrf-cookie`.

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

Set `VITE_API_URL` in `frontend/.env` if the API is not at `http://localhost:8000`. Without local SMTP, use `MAIL_MAILER=log`. Start a worker and scheduler separately with `php artisan queue:work` and `php artisan schedule:work`. Target alerts go to the assigned technician, or administrators when no technician is assigned, once within an hour of the target and once after it passes.

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

The backend includes focused tests for SSRF, tenant isolation, website review, password recovery, account settings, ticket categories, attachments, notifications, backups and SSL. Frontend unit coverage is small; Playwright checks the dashboard, workspace navigation and password-reset screen. The browser suite builds and serves the production bundle. OpenAPI and the Postman collection cover only implemented routes.

GitHub Actions runs backend tests on PHP 8.3 and frontend lint, type, unit and browser checks on pushes and pull requests to `main`. This is useful when a local PHP installation is older than Laravel's supported version.

## Security and production notes

The app has no public registration endpoint. It uses Sanctum session cookies and CSRF protection. API queries scope client data to an organisation and filter internal notes. Website submissions reject credentials, unsupported protocols/ports and detected private/reserved IPs. Monitor requests have bounded timeouts and do not follow redirects. Attachment downloads check ticket access; reset responses do not reveal whether an email belongs to an account.

Before production, add network-level egress filtering for monitor traffic, review the authorization policies and isolation tests, configure secret storage, TLS and ingress controls, and define health-history retention and deployment monitoring. The application does not guarantee uptime or run a customer's backups.

See [docs/decisions.md](docs/decisions.md) for implementation choices. Monitoring uses Laravel's database queue so local setup does not require Redis. Response and resolution targets are configurable elapsed-hour estimates; resolution targets pause while a ticket waits on a client. The targets do not use business calendars. Analytics and audit coverage can be expanded further.
