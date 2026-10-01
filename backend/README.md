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

Authentication, invitation management/acceptance, dashboard summaries, website submission/review, tickets, comments, maintenance and backup records, signed backup webhooks, incidents and basic monitor jobs are implemented. See the root OpenAPI file for the route subset. Password recovery, file attachments, SSL checks and general email notifications are not implemented yet.
