# Final Backend Audit — Douzloo SaaS

**Release candidate review.** Scope: audit + documentation of the current state only — no features added, no business logic changed, no refactoring performed during this review.
**Date:** 2026-08-08
**Baseline:** commit `9b3c574` (post multi-tenant); current working tree.

---

## 1. Architecture Summary

**Stack:** Laravel 13 (PHP 8.3/8.5) · Sanctum (token auth) · MySQL/MariaDB (SQLite for tests) · Redis/database cache · DomPDF (RTL invoices) · Scramble (OpenAPI) · Inertia scaffold (web root only; API-first).

**Layout:**
- **API-first**: `routes/api.php` + `routes/admin.php` expose 116 JSON operations under `/api`; the web route is a bare Inertia shell.
- **Auth model**: role enum on `users` (`admin` / `staff` / `customer`) enforced by two middleware aliases (`admin`, `staff`) on route groups, plus per-resource ownership checks in controllers. Sanctum bearer tokens; login rate-limited (5/min).
- **Service layer**: 8 domain services (`InvoiceService`, `PaymentService`, `OrderService`, `LicenseService`, `TicketService`, `LeadService`, `ProductReleaseService`, `ProductAssignmentService`) behind interfaces, all bound in `AppServiceProvider`.
- **Controllers**: 20 controllers + `AdminDashboardController`; JSON API resources (24) shape responses.
- **Domain modules** (all verified): Products & Releases, Downloads & download logging, Licensing (desktop + portal, activation limits), Orders & Coupons, Invoices & Payments (partial/refund), Tickets (attachments), CRM (leads/stages/notes/activities/reminders), CMS (pages/FAQs/settings), Portal (customer dashboard), Admin (dashboard/metrics/management).
- **Database**: 26 migrations → 51 tables (see `DATABASE_SCHEMA_REPORT.md`).
- **Authorization pattern**: route groups (`admin`/`staff`) + in-controller `user_id` ownership checks + FormRequest `authorize()`. No policy classes; the spatie/permission tables are scaffolded but unused.

## 2. Test Summary

| Gate | Result |
|---|---|
| Pest suite | **197 tests / 640 assertions — PASSING** |
| PHPStan (level 7) | **0 errors** |
| Pint (PSR-12) | **clean** |
| Coverage artifacts | `RouteSmokeTest` (route targets + 8 service bindings resolve), `EndpointValidationTest` (~120 endpoint interactions), `DashboardMetricsTest` + `BillingMetricsVerificationTest` (dashboard/billing metrics vs raw DB aggregates incl. partial payments/refunds), `SecurityCheckTest` (authorization regressions), `ApiDocsTest` (docs access control) |

**Coverage by module:** auth, products/releases, downloads, licensing (desktop + portal + admin), orders, billing (27 tests), tickets + attachments, CRM (leads + pipeline), portal, CMS, admin metrics, docs access, security. Integration tests use SQLite in-memory with full migrations + factories.

## 3. Security Summary

**Threat model:** unauthenticated public surface, authenticated customers, staff, and admin roles.

**Findings during this release pass — all fixed before this audit:**
1. **IDOR — ticket `reply`/`updateStatus`/`assign`** (High): no ownership check. Now owner-or-staff (`reply`/`updateStatus`), staff-only (`assign`).
2. **IDOR — order `applyCoupon`** (High): could mutate another user's order. Now owner-or-staff.
3. **Self-marked `paid` invoices** (Medium): non-staff invoice creation restricted to `draft` status in `StoreInvoiceRequest`.

**Verified secure:**
- **Public endpoints** — read-only or internally gated: downloads require a valid license for `requires_activation` products (`canDownload`); desktop license API uses the license `key` as credential; contact/auth rate-limited.
- **Authenticated endpoints** — ownership enforced on orders, invoices, payments, tickets, portal (licenses/downloads), with `user_id` bound server-side on all create paths (never client-supplied).
- **Staff/Admin groups** — hard role middleware; admin covers all 39 management routes.
- **Rate limiting** — `auth` 5/min, `contact` 10/min, `license-verify` 30/min, `api` 60/min, `global` 120/min.
- **Docs exposure** — `/docs/api` gated to staff via `ViewApiDocs` Gate + `RestrictedDocsAccess` (anonymous/customer → 403).
- **Data hygiene** — soft deletes on sensitive entities; secrets not committed; `password`/`remember_token` hidden on `UserResource`.
- Full detail in `docs/SECURITY_AUDIT.md`.

## 4. Remaining Risks / Observations

| # | Risk | Severity | Mitigation / Recommendation |
|---|---|---|---|
| 1 | No scheduled tasks registered (overdue-invoice marking, license expiry, reminders) | Low | Scheduler cron installed in `DEPLOYMENT_CHECKLIST.md` §7; add tasks when scheduled behaviour is required. |
| 2 | No queued jobs wired; work is synchronous | Low | Worker supervisor config provided (§8); acceptable for beta volume. |
| 3 | CRM leads not scoped per staff product assignment — any staff sees all leads | Low | Documented; matches ticket/order staff model. Add scoping if isolation needed (no change now). |
| 4 | Spatie permission tables scaffolded but unused — potential drift | Low | Either wire in or remove for clarity; current role-enum model is consistent and tested. |
| 5 | Invoice can be marked `paid` by staff without a payment record | Low | Canonical path is `POST /api/payments` or `/api/invoices/{id}/pay`; staff-only. |
| 6 | Email/SMTP not exercised | Low | Mailer config documented; no transactional jobs exist yet. |
| 7 | `config:cache`/`route:cache` must run post-deploy; route closure dependency checked | Info | Covered in deploy checklist §9. |
| 8 | Migrations include an enum expansion (`invoices.status` + `sent`) and a settings unique-key swap | Medium | Enum expansion is non-destructive on MySQL; settings swap validates duplicates before running. Backup before deploy. |

## 5. Production Readiness

| Dimension | Rating | Basis |
|---|---|---|
| Feature completeness | 100% | All planned modules + verification pass delivered |
| Code quality | 100% | PHPStan 0, Pint clean, service-layer structure |
| Test coverage | High | 197 tests / 640 assertions across every module + security regressions |
| API documentation | 100% | OpenAPI 116/116 coverage; `API_REFERENCE.md`; staff-gated docs |
| Security posture | Hardened | 3 real issues fixed pre-release; ownership/role model verified |
| Schema integrity | Verified | 51 tables documented; indexes/FKs present; enum/unique changes assessed |
| Operations | ~90% | Deploy checklist complete (env/queue/redis/storage/cron/supervisor/cache/nginx/SSL/backup); SMTP + scheduled tasks outstanding |
| **Overall** | **≈ 95%** | **Release-candidate ready.** Remaining 5% = operational wiring (SMTP, scheduled tasks, prod smoke test on real infra). |

## 6. Verification Log (this review)

- `php artisan test` → 197 passed / 640 assertions ✅
- `./vendor/bin/phpstan analyse` → 0 errors ✅
- `./vendor/bin/pint --test` → clean ✅
- `route:list` vs `docs/openapi.json` → 116 ↔ 116, **0 missing / 0 extra** ✅
- Migrations read and cross-checked against models/factories/seeders ✅
- Controllers/requests/middleware audited for authorization ✅

**No code changes were made during this review** (all five reports are documentation-only).
