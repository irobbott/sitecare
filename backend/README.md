# SiteCare backend

Laravel 13 JSON API for the SiteCare React app. The API routes are under `/api/v1`; Sanctum uses first-party session cookies and CSRF.

## Setup

Use PHP 8.3 or newer, MySQL, and Composer 2:

```powershell
Copy-Item .env.example .env
composer install
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

For API calls from the frontend, first request `GET /sanctum/csrf-cookie`, then sign in at `POST /api/v1/auth/login`. Seeded development accounts and the demo password are listed in the root README.

## Background work

Run `php artisan queue:work` and `php artisan schedule:work` in separate terminals. `php artisan sitecare:monitor-due` queues health checks for active websites. Checks require public HTTP/HTTPS targets on ports 80 or 443.

## Current API coverage

Authentication, invitation management/acceptance, password recovery, profile and notification preferences, organisation and team management, website submission/review, ticket workflows and categories, comments, private attachments, maintenance and backup records, signed backup webhooks, incidents, audit events, SSL checks and queued notifications are implemented. Admins can configure elapsed-hour first-response and resolution targets. Resolution time pauses while a ticket waits for the client. See the root OpenAPI file and Postman collection for API examples.

The monitor validates public HTTP/HTTPS targets and pins resolved public addresses. Add network-level egress restrictions before exposing it to untrusted traffic. Ticket targets use elapsed hours, not business hours. Resolution targets pause while tickets are waiting for the client; first-response targets do not. The first client-visible staff response is timestamped. Backup records are external reports; SiteCare does not run or restore backups.
