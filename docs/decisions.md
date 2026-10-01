# Architecture notes

## Laravel API and separate SPA

The backend is Laravel 13 and exposes JSON endpoints under `/api/v1`. The frontend is a separate React app served by Vite in development and Nginx in the frontend container. Sanctum's stateful SPA middleware uses session cookies and CSRF tokens; no token is stored in browser local storage.

## Organisation ownership

Websites and tickets carry `organisation_id`. Client API queries filter by the authenticated user's organisation. Technician queries filter by assigned user. Administrator access is explicit. The schema preserves historical records when an organisation is suspended.

## Monitoring

The scheduler checks active records and sends unique jobs to Laravel's database queue. Jobs use short timeouts, validate public A/AAAA addresses, pin the selected address through cURL and do not follow redirects. A production deployment should still apply outbound network filtering.

## Service targets

Ticket targets use elapsed hours by priority. This first version does not implement business calendars, pause rules, or contractual SLA claims.

## Implemented workflows and remaining work

Invitation creation, queued email and acceptance are implemented through the API and administrator screen. Password reset, private attachment storage, SSL certificate history, incident alerts, configurable response targets and notification preferences are also implemented. Signed backup events encrypt per-site secrets and reject stale or repeated event IDs.

The current target calculation uses elapsed hours and does not pause while waiting for a client. Broader audit coverage, business-hour calendars, richer analytics, health-history retention, production egress filtering and more role-isolation workflow tests remain future work.
