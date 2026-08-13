# Security Audit Report

**Scope:** Authorization & access control across the entire API surface, as part of the deployable-beta verification pass.
**Date:** 2026-08-08
**Result:** 2 authorization vulnerabilities confirmed and fixed; no remaining critical/high findings.

---

## Summary

| Area | Verdict | Notes |
|------|---------|-------|
| Authentication | ✅ | Sanctum token auth; role middleware `admin`/`staff` aliases enforced per route group |
| Authorization (customers vs staff/admin) | ✅ (2 fixed) | See Vulnerabilities below |
| Ownership checks (customer-scoped resources) | ✅ | Orders, invoices, payments, tickets, licenses, portal all verify `user_id` |
| Public API | ✅ | Downloads/license/contact/CMS only; download gated by license validity |
| Rate limiting | ✅ | `auth` 5/min, `contact` 10/min, `license-verify` 30/min, `api` 60/min, `global` 120/min |
| API docs exposure | ✅ | Scramble restricted to staff (`ViewApiDocs` gate + `RestrictedDocsAccess`) |
| Data integrity (billing) | ✅ (1 hardened) | Customers can no longer self-mark invoices `paid`/`sent` |

---

## Vulnerabilities Found & Fixed

### 1. IDOR: Ticket `reply`, `assign`, `updateStatus` — no ownership check (HIGH)

`TicketController::reply()`, `assign()`, `updateStatus()` operated on any ticket by primary key with no check, while `show()`, `messages()`, and `downloadAttachment()` correctly enforced owner-or-staff.

**Impact:** A customer could reply to another customer's ticket, re-assign tickets, and change ticket status for tickets they do not own.

**Fix:** Added the same owner-or-staff guard used by `show()`/`messages()` to `reply()` and `updateStatus()`; `assign()` is now **staff-only** (customers have no legitimate reason to assign). Applied in `app/Http/Controllers/Api/V1/TicketController.php`.

**Regression tests:** `SecurityCheckTest` — "forbids customers from replying to another users ticket", "forbids customers from changing another users ticket status", "forbids customers from assigning tickets".

### 2. IDOR: Order `applyCoupon` — no ownership check (HIGH)

`OrderController::applyCoupon()` applied a coupon (and mutated order totals) to any order by ID.

**Impact:** A customer could apply coupons to another customer's order, altering its total.

**Fix:** Added the same owner-or-staff guard used by `show()`/`pay()`/`invoice()`. Applied in `app/Http/Controllers/Api/V1/OrderController.php`.

### 3. Data integrity: customers could create `paid` invoices (MEDIUM)

`StoreInvoiceRequest` allowed any authenticated user to create an invoice with any status including `paid`, effectively recording a payment without a payment record.

**Fix:** Non-staff users may only create invoices with `status = draft` (rules are staff-conditional). Self-invoicing in draft state remains supported as designed (the invoice is always owned by the authenticated user — `user_id` is forced server-side in `InvoiceService::create()`).

**Regression tests:** `SecurityCheckTest` — "forbids customers from creating paid or sent invoices", "allows customers to create draft invoices for themselves only".

---

## Verified Secure (no changes needed)

### Route-group gating
- **Admin routes** (`/api/admin/*`): `middleware('admin')` → `role === 'admin'`, plus `auth:sanctum`. All 39 admin endpoints (dashboard, billing metrics, product/release/assignment CRUD, license admin) covered.
- **Staff (CRM) routes** (`/api/crm/*`): `middleware('staff')` → `role in [admin, staff]`. All lead/stage/note/reminder endpoints covered.
- **Customer routes** (`/api/orders`, `/api/invoices`, `/api/payments`, `/api/tickets`, `/api/portal/*`): `auth:sanctum`, with controller-level ownership checks.

### Ownership checks confirmed
- **Orders** (`OrderController`): `index` scoped to `$request->user()->orders()`; `show`/`pay`/`invoice`/`applyCoupon` require owner-or-staff.
- **Invoices** (`InvoiceController`): `authorizeView()` (owner-or-staff) on `show`/`pay`/`pdf`; `authorizeManage()` (staff-only) on `update`/`destroy`.
- **Payments** (`PaymentController`): `index` scoped via invoice owner for customers; `show` owner-or-staff; `refund` staff-only; `store` via `StorePaymentRequest` (staff-gated).
- **Tickets** (`TicketController`): `index` scoped to own tickets for customers; `show`/`messages`/`reply`/`updateStatus`/`downloadAttachment` owner-or-staff; `assign` staff-only; `store` always creates for the authenticated user.
- **Licenses / portal** (`LicenseController`, `PortalController`): all queries scoped to `$request->user()->licenses()`; portal `invoice` aborts 403 unless owner-or-staff; portal downloads limited to products the user has an active license for.
- **Desktop license API** (`/api/license/*`): uses the license `key` itself as the credential — activation/deactivation/verify/heartbeat all validate key + product + status. By design.

### Public surface
- `GET /api/products*`, `GET /api/downloads*`, `GET /api/pages*`, `GET /api/settings` — read-only.
- `POST /api/downloads/{download}` — gated by `canDownload()`: free products (no `requires_activation`) are open; products requiring activation need a valid license key (via header/auth user or `license_key` body). Logs every download (`DownloadLog`) with IP + user agent.
- `POST /api/contact`, `POST /api/license/verify`, `POST /api/auth/login|register` — rate-limited.

### Misc
- `User::isAdmin()` / `User::isStaff()` used consistently in controllers (no ad-hoc role comparisons).
- All `store`/`create` paths that receive an owner always bind `user_id` to `auth()->id()` server-side (orders, tickets, invoices, contact demo) — no client-supplied ownership.
- `docs/` API documentation requires the `view-api-docs` ability: blocked for anonymous and customer roles (403), staff only.

---

## Known / Accepted (documented for the team, no code change)

1. **CRM lead product-scoping** — `LeadController` is staff-gated but not scoped to each staff member's product assignments; any staff member sees all leads. This mirrors the ticket/order model (staff see all). If per-product staff isolation is desired, scope `LeadController::index` by the staff member's `assignments`. Not changed to preserve the established permission model.
2. **Self-invoicing** — any authenticated user may create their own draft invoice. This is the P5 design (allows customers to generate invoices); totals are recalculated server-side from line items. Drafts carry no payment or authoritative status, so there is no direct financial impact.
3. **Env safety** — rely on `APP_DEBUG=false`, fresh `APP_KEY`, strong `APP_ENV=production` in deployment (see `DEPLOYMENT_CHECKLIST.md`). No secrets are committed.

---

## Verification

- Full suite: **197 tests / 640 assertions passing** (7 new security regression tests).
- PHPStan level 7: **0 errors**.
- Pint: clean.
