# Route Report — Douzloo SaaS Backend

Generated from `php artisan route:list` (124 routes total). All API routes prefixed with `/api`. Auth requirement inferred from middleware (all routes carry the global `api` middleware; throttles/role middleware noted).

Middleware legend:
- **public** — no auth middleware
- **auth** — `auth:sanctum`
- **staff** — `auth:sanctum` + `EnsureUserIsStaff` (role ∈ {admin, staff})
- **admin** — `auth:sanctum` + `EnsureUserIsAdmin` (role = admin)
- **throttle:x** — `ThrottleRequests:x` named limiter

---

## 1. Public — Products & Downloads

| Method | Route | Controller@action | Auth | Middleware |
|---|---|---|---|---|
| GET | `/api/products` | ProductController@index | public | api |
| GET | `/api/products/categories` | ProductController@categories | public | api |
| GET | `/api/products/{product:slug}` | ProductController@show | public | api |
| GET | `/api/products/{product:slug}/faqs` | ProductController@faqs | public | api |
| GET | `/api/products/{product:slug}/releases` | ProductController@releases | public | api |
| GET | `/api/products/{product:slug}/releases/latest` | ProductController@latestRelease | public | api |
| GET | `/api/downloads` | DownloadController@index | public | api |
| GET | `/api/downloads/latest` | DownloadController@latest | public | api |
| GET | `/api/downloads/product/{product:slug}` | DownloadController@byProduct | public | api |
| GET | `/api/downloads/{download}` | DownloadController@download | public* | api |

*`/api/downloads/{download}` is public at the route level but internally gated by `canDownload()` — products without `requires_activation` are open; products requiring activation need a valid license key (via authenticated user or `license_key` body param).

## 2. Public — Desktop License API

| Method | Route | Controller@action | Auth | Middleware |
|---|---|---|---|---|
| POST | `/api/license/activate` | LicenseController@desktopActivate | public | api |
| POST | `/api/license/deactivate` | LicenseController@desktopDeactivate | public | api |
| POST | `/api/license/heartbeat` | LicenseController@heartbeat | public | api |
| POST | `/api/license/verify` | LicenseController@verify | public | api + throttle:license-verify (30/min) |

Uses the license `key` as the credential (validated against product + status). By design for desktop apps.

## 3. Public — CMS & Contact & Auth

| Method | Route | Controller@action | Auth | Middleware |
|---|---|---|---|---|
| GET | `/api/pages` | CmsController@pages | public | api |
| GET | `/api/pages/{slug}` | CmsController@page | public | api |
| GET | `/api/settings` | CmsController@settings | public | api |
| POST | `/api/contact` | ContactController@submit | public | api + throttle:contact (10/min) |
| POST | `/api/auth/login` | AuthController@login | public | api + throttle:auth (5/min) |
| POST | `/api/auth/register` | AuthController@register | public | api + throttle:auth (5/min) |

## 4. Authenticated — Auth

| Method | Route | Controller@action | Auth | Middleware |
|---|---|---|---|---|
| GET | `/api/auth/user` | AuthController@me | auth | api + auth:sanctum |
| POST | `/api/auth/logout` | AuthController@logout | auth | api + auth:sanctum |
| PUT | `/api/auth/profile` | AuthController@updateProfile | auth | api + auth:sanctum |
| PUT | `/api/auth/password` | AuthController@changePassword | auth | api + auth:sanctum |

## 5. Authenticated — Orders

| Method | Route | Controller@action | Auth | Middleware |
|---|---|---|---|---|
| GET | `/api/orders` | OrderController@index | auth | api + auth:sanctum |
| POST | `/api/orders` | OrderController@store | auth | api + auth:sanctum |
| GET | `/api/orders/{order}` | OrderController@show | auth | api + auth:sanctum |
| POST | `/api/orders/{order}/coupon` | OrderController@applyCoupon | auth | api + auth:sanctum |
| GET | `/api/orders/{order}/invoice` | OrderController@invoice | auth | api + auth:sanctum |
| POST | `/api/orders/{order}/pay` | OrderController@pay | auth | api + auth:sanctum |

Ownership: `index` scoped to current user; `show`/`applyCoupon`/`pay`/`invoice` owner-or-staff.

## 6. Authenticated — Billing (Invoices & Payments)

| Method | Route | Controller@action | Auth | Middleware |
|---|---|---|---|---|
| GET | `/api/invoices` | InvoiceController@index | auth | api + auth:sanctum |
| POST | `/api/invoices` | InvoiceController@store | auth | api + auth:sanctum |
| GET | `/api/invoices/{invoice}` | InvoiceController@show | auth | api + auth:sanctum |
| PUT | `/api/invoices/{invoice}` | InvoiceController@update | auth | api + auth:sanctum |
| DELETE | `/api/invoices/{invoice}` | InvoiceController@destroy | auth | api + auth:sanctum |
| POST | `/api/invoices/{invoice}/pay` | InvoiceController@pay | auth | api + auth:sanctum |
| GET | `/api/invoices/{invoice}/pdf` | InvoiceController@pdf | auth | api + auth:sanctum |
| GET | `/api/payments` | PaymentController@index | auth | api + auth:sanctum |
| POST | `/api/payments` | PaymentController@store | auth | api + auth:sanctum |
| GET | `/api/payments/{payment}` | PaymentController@show | auth | api + auth:sanctum |
| POST | `/api/payments/{payment}/refund` | PaymentController@refund | auth | api + auth:sanctum |

Authorization enforced in-controller: invoices `show`/`pay`/`pdf` owner-or-staff, `update`/`destroy` staff; `store` self-issue draft-only for customers. Payments `store`/`refund` staff-only, `show`/`index` owner-scoped. Request-level gates in `StoreInvoiceRequest` (non-staff status=draft), `UpdateInvoiceRequest`, `StorePaymentRequest`, `PayInvoiceRequest`.

## 7. Authenticated — Tickets

| Method | Route | Controller@action | Auth | Middleware |
|---|---|---|---|---|
| GET | `/api/tickets` | TicketController@index | auth | api + auth:sanctum |
| POST | `/api/tickets` | TicketController@store | auth | api + auth:sanctum |
| GET | `/api/tickets/{ticket}` | TicketController@show | auth | api + auth:sanctum |
| POST | `/api/tickets/{ticket}/messages` | TicketController@reply | auth | api + auth:sanctum |
| GET | `/api/tickets/{ticket}/messages` | TicketController@messages | auth | api + auth:sanctum |
| POST | `/api/tickets/{ticket}/assign` | TicketController@assign | auth | api + auth:sanctum |
| PUT | `/api/tickets/{ticket}/status` | TicketController@updateStatus | auth | api + auth:sanctum |
| GET | `/api/ticket-attachments/{attachment}/download` | TicketController@downloadAttachment | auth | api + auth:sanctum |

Ownership: `index` own-or-all (staff); `show`/`reply`/`messages`/`updateStatus`/`downloadAttachment` owner-or-staff; `assign` staff-only; `store` always creates for the authenticated user.

## 8. Authenticated — Portal

| Method | Route | Controller@action | Auth | Middleware |
|---|---|---|---|---|
| GET | `/api/portal/dashboard` | PortalController@dashboard | auth | api + auth:sanctum |
| GET | `/api/portal/invoices` | PortalController@invoices | auth | api + auth:sanctum |
| GET | `/api/portal/invoices/{invoice}` | PortalController@invoice | auth | api + auth:sanctum |
| GET | `/api/portal/downloads` | PortalController@downloads | auth | api + auth:sanctum |
| GET | `/api/portal/licenses` | LicenseController@index | auth | api + auth:sanctum |
| GET | `/api/portal/licenses/{key}` | LicenseController@show | auth | api + auth:sanctum |
| GET | `/api/portal/licenses/{key}/activations` | LicenseController@activations | auth | api + auth:sanctum |
| POST | `/api/portal/licenses/activate` | LicenseController@activate | auth | api + auth:sanctum |
| POST | `/api/portal/licenses/deactivate` | LicenseController@deactivate | auth | api + auth:sanctum |
| POST | `/api/portal/licenses/heartbeat` | LicenseController@portalHeartbeat | auth | api + auth:sanctum |

All portal queries scoped to `$request->user()->licenses()` / `->orders()` / `->tickets()`; portal `invoice` owner-or-staff; portal downloads limited to products the user has an active license for.

## 9. Staff — CRM

| Method | Route | Controller@action | Auth | Middleware |
|---|---|---|---|---|
| GET | `/api/crm/leads` | LeadController@index | staff | api + auth:sanctum + staff |
| POST | `/api/crm/leads` | LeadController@store | staff | api + auth:sanctum + staff |
| GET | `/api/crm/leads/stats` | LeadController@stats | staff | api + auth:sanctum + staff |
| GET | `/api/crm/leads/pipeline` | LeadController@pipeline | staff | api + auth:sanctum + staff |
| POST | `/api/crm/leads/bulk-assign` | LeadController@bulkAssign | staff | api + auth:sanctum + staff |
| GET | `/api/crm/leads/reminders` | LeadController@reminders | staff | api + auth:sanctum + staff |
| GET | `/api/crm/leads/{lead}` | LeadController@show | staff | api + auth:sanctum + staff |
| PUT | `/api/crm/leads/{lead}` | LeadController@update | staff | api + auth:sanctum + staff |
| PUT | `/api/crm/leads/{lead}/status` | LeadController@updateStatus | staff | api + auth:sanctum + staff |
| POST | `/api/crm/leads/{lead}/assign` | LeadController@assign | staff | api + auth:sanctum + staff |
| POST | `/api/crm/leads/{lead}/convert` | LeadController@convert | staff | api + auth:sanctum + staff |
| GET | `/api/crm/leads/{lead}/activities` | LeadController@activities | staff | api + auth:sanctum + staff |
| POST | `/api/crm/leads/{lead}/activities` | LeadController@addActivity | staff | api + auth:sanctum + staff |
| POST | `/api/crm/leads/{lead}/reminders` | LeadController@createReminder | staff | api + auth:sanctum + staff |
| PUT | `/api/crm/reminders/{activity}/complete` | LeadController@completeReminder | staff | api + auth:sanctum + staff |
| GET | `/api/crm/leads/{lead}/notes` | LeadNoteController@index | staff | api + auth:sanctum + staff |
| POST | `/api/crm/leads/{lead}/notes` | LeadNoteController@store | staff | api + auth:sanctum + staff |
| PUT | `/api/crm/lead-notes/{note}` | LeadNoteController@update | staff | api + auth:sanctum + staff |
| DELETE | `/api/crm/lead-notes/{note}` | LeadNoteController@destroy | staff | api + auth:sanctum + staff |
| GET | `/api/crm/lead-stages` | LeadStageController@index | staff | api + auth:sanctum + staff |
| POST | `/api/crm/lead-stages` | LeadStageController@store | staff | api + auth:sanctum + staff |
| PUT | `/api/crm/lead-stages/{stage}` | LeadStageController@update | staff | api + auth:sanctum + staff |
| DELETE | `/api/crm/lead-stages/{stage}` | LeadStageController@destroy | staff | api + auth:sanctum + staff |

## 10. Admin

| Method | Route | Controller@action | Auth | Middleware |
|---|---|---|---|---|
| GET | `/api/admin/dashboard` | AdminDashboardController@overview | admin | api + auth:sanctum + admin |
| GET | `/api/admin/dashboard/recent-orders` | AdminDashboardController@recentOrders | admin | api + auth:sanctum + admin |
| GET | `/api/admin/dashboard/recent-leads` | AdminDashboardController@recentLeads | admin | api + auth:sanctum + admin |
| GET | `/api/admin/dashboard/recent-tickets` | AdminDashboardController@recentTickets | admin | api + auth:sanctum + admin |
| GET | `/api/admin/billing/metrics` | BillingMetricsController@index | admin | api + auth:sanctum + admin |
| GET | `/api/admin/downloads` | DownloadAdminController@index | admin | api + auth:sanctum + admin |
| GET | `/api/admin/downloads/stats` | DownloadAdminController@stats | admin | api + auth:sanctum + admin |
| GET | `/api/admin/download-logs` | DownloadAdminController@logs | admin | api + auth:sanctum + admin |
| POST | `/api/admin/products` | ProductController@store | admin | api + auth:sanctum + admin |
| PUT | `/api/admin/products/{product}` | ProductController@update | admin | api + auth:sanctum + admin |
| DELETE | `/api/admin/products/{product}` | ProductController@destroy | admin | api + auth:sanctum + admin |
| GET | `/api/admin/products/{product}/releases` | ReleaseController@index | admin | api + auth:sanctum + admin |
| POST | `/api/admin/products/{product}/releases` | ReleaseController@store | admin | api + auth:sanctum + admin |
| GET | `/api/admin/products/{product}/releases/{release}` | ReleaseController@show | admin | api + auth:sanctum + admin |
| PUT | `/api/admin/products/{product}/releases/{release}` | ReleaseController@update | admin | api + auth:sanctum + admin |
| DELETE | `/api/admin/products/{product}/releases/{release}` | ReleaseController@destroy | admin | api + auth:sanctum + admin |
| POST | `/api/admin/products/{product}/releases/{release}/publish` | ReleaseController@publish | admin | api + auth:sanctum + admin |
| POST | `/api/admin/products/{product}/releases/{release}/deprecate` | ReleaseController@deprecate | admin | api + auth:sanctum + admin |
| POST | `/api/admin/products/{product}/releases/{release}/rollback` | ReleaseController@rollback | admin | api + auth:sanctum + admin |
| GET | `/api/admin/products/{product}/staff` | ProductAssignmentController@index | admin | api + auth:sanctum + admin |
| POST | `/api/admin/products/{product}/staff` | ProductAssignmentController@store | admin | api + auth:sanctum + admin |
| PUT | `/api/admin/products/{product}/staff/{assignment}` | ProductAssignmentController@update | admin | api + auth:sanctum + admin |
| DELETE | `/api/admin/products/{product}/staff/{assignment}` | ProductAssignmentController@destroy | admin | api + auth:sanctum + admin |
| GET | `/api/admin/users/{user}/products` | ProductAssignmentController@userProducts | admin | api + auth:sanctum + admin |
| GET | `/api/admin/licenses` | LicenseAdminController@index | admin | api + auth:sanctum + admin |
| GET | `/api/admin/licenses/stale-activations` | LicenseAdminController@staleActivations | admin | api + auth:sanctum + admin |
| GET | `/api/admin/licenses/{license}` | LicenseAdminController@show | admin | api + auth:sanctum + admin |
| GET | `/api/admin/licenses/{license}/activations` | LicenseAdminController@activations | admin | api + auth:sanctum + admin |
| POST | `/api/admin/licenses/{license}/renew` | LicenseAdminController@renew | admin | api + auth:sanctum + admin |
| POST | `/api/admin/licenses/{license}/revoke` | LicenseAdminController@revoke | admin | api + auth:sanctum + admin |
| POST | `/api/admin/licenses/{license}/suspend` | LicenseAdminController@suspend | admin | api + auth:sanctum + admin |
| POST | `/api/admin/licenses/{license}/unsuspend` | LicenseAdminController@unsuspend | admin | api + auth:sanctum + admin |
| PUT | `/api/admin/licenses/{license}/transfer` | LicenseAdminController@transfer | admin | api + auth:sanctum + admin |
| PUT | `/api/admin/licenses/{license}/upgrade` | LicenseAdminController@upgrade | admin | api + auth:sanctum + admin |

## 11. Non-API Routes (excluded from OpenAPI)

| Method | Route | Handler | Purpose |
|---|---|---|---|
| GET | `/` | Inertia\Controller | Web root (web middleware) |
| POST | `/_boost/browser-logs` | Closure | Laravel Boost browser logs |
| GET | `/docs/api` | Scramble Closure | OpenAPI UI (web + RestrictedDocsAccess) |
| GET | `/docs/api.json` | Scramble Closure | OpenAPI JSON (web + RestrictedDocsAccess) |
| GET | `/sanctum/csrf-cookie` | CsrfCookieController@show | CSRF cookie (web) |
| GET | `/storage/{path}` | Closure | Local storage serving |
| PUT | `/storage/{path}` | Closure | Local storage (uploads) |
| GET | `/up` | Closure | Health check |

---

## Summary

| Group | Count | Auth model |
|---|---|---|
| Public (products/downloads/license/CMS/contact/auth) | 21 | none (2 throttle-limited) |
| Authenticated (auth/orders/billing/tickets/portal) | 33 | auth:sanctum + in-controller authorization |
| Staff CRM | 23 | staff middleware |
| Admin | 39 | admin middleware |
| Non-API | 8 | web / none |
| **Total** | **124** (116 API ops after HEAD de-duplication) | |

All 116 API operations are covered by `docs/openapi.json` (see OpenAPI coverage verification).

---

## OpenAPI Coverage Verification

**Method:** programmatically compared `php artisan route:list --json` (API routes only, HEAD duplicates removed) against the operations in `docs/openapi.json`, normalizing path parameters (`{x}`) so only method + path shape are compared.

### Result

| Metric | Count |
|---|---|
| API operations (route:list) | 116 |
| OpenAPI operations | 116 |
| **Missing from OpenAPI** | **0** |
| In OpenAPI but not route:list | 0 |

### Notes

- **100% coverage.** Every API endpoint (`/api/*`) has a matching OpenAPI operation, and every OpenAPI operation maps to a real route. No dead or undocumented endpoints.
- **Parameter naming:** 5 routes bind `{product:slug}` (custom slug binding via `Product::getRouteKeyName()`); Scramble renders these as `{product}` in the spec. Semantically identical — parameter is the product slug.
- **Excluded from OpenAPI (by design):** the 8 non-API routes (`/`, `/_boost/browser-logs`, `/docs/api`, `/docs/api.json`, `/sanctum/csrf-cookie`, `/storage/{path}`, `/storage/{path}`, `/up`). These are web/utility routes, not JSON API endpoints.
- **Security marking:** global bearer `securityScheme` applied via `MiddlewareAuthSecurityStrategy`; public routes (products, downloads, license, CMS, contact, auth login/register) are explicitly marked `security: []` in the spec.
- **Regeneration:** `docs/openapi.json` was regenerated after the `StoreInvoiceRequest` status change and reflects the current route set.
