# Architecture notes

## Laravel API and separate SPA

The backend is Laravel 13 and exposes JSON endpoints under `/api/v1`. The frontend is a separate React app served by Vite in development and Nginx in the frontend container. Sanctum's stateful SPA middleware uses session cookies and CSRF tokens; no token is stored in browser local storage.

## Organisation ownership

Websites and tickets carry `organisation_id`. Client API queries filter by the authenticated user's organisation. Technician queries filter by assigned user. Administrator access is explicit. The schema preserves historical records when an organisation is suspended.

## Monitoring

The scheduler checks active records and sends unique jobs to Laravel's database queue. Jobs use short timeouts and do not follow redirects. URL validation is defense in depth, not a replacement for network egress filtering; production should pin resolved addresses or use an outbound proxy.

## Service targets

Ticket targets use elapsed hours by priority. This first version does not implement business calendars, pause rules, or contractual SLA claims.

## Deferred work

Invitation and reset flows, attachment storage, signed backup webhooks, SSL certificate history, email notifications, a full policy suite and end-to-end tests are not implemented. A few tables reserve room for upcoming work without claiming those workflows exist.
