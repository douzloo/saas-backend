# Changed Files Report — Douzloo SaaS Backend

**Baseline:** commit `9b3c574` (`Add multi tenant organization support with roles and permissions`)
**Current state:** working tree vs baseline (142 changed/new files; includes all work since baseline: P2 Licenses, P3 Downloads, P4 CRM, P5 Billing, and the Verification pass)
**Scope note:** the baseline commit predates most feature work, so this report covers the entire delta that constitutes the current beta.

Legend — **Risk:**
- **Low** — additive, isolated, no schema or security impact
- **Medium** — schema-affecting, transactional, or cross-cutting
- **High** — security-sensitive, destructive, or risky to deploy

Legend — **Migration impact:** `NEW TABLE`, `COLUMN(S)`, `INDEX`, `FK`, `ENUM`, `REWRITE` (constraint swap), or `—` (none).

---

## 1. Controllers

| File | Purpose | Status | Feature | Migration impact | Risk |
|---|---|---|---|---|---|
| `app/Http/Controllers/Api/V1/AuthController.php` | Login/register/logout/me/profile/password (token auth, Persian errors, last_login tracking) | Modified | Auth/core | — | High |
| `app/Http/Controllers/Api/V1/ProductController.php` | Public product catalog, categories, per-product FAQs/releases | Created | Products | — | Low |
| `app/Http/Controllers/Api/V1/DownloadController.php` | Public download index/latest/byProduct + license-gated download (DownloadLog) | Created | Downloads | — | Medium |
| `app/Http/Controllers/Api/V1/LicenseController.php` | Desktop + portal license activate/deactivate/heartbeat/verify | Created | Licenses | — | Medium |
| `app/Http/Controllers/Api/V1/OrderController.php` | Order CRUD, coupon apply, pay, invoice (owner-or-staff gated) | Created | Orders/Billing | — | Medium |
| `app/Http/Controllers/Api/V1/InvoiceController.php` | Invoice CRUD, pay, PDF (RTL DomPDF), authorizeView/authorizeManage | Created | Billing | — | High |
| `app/Http/Controllers/Api/V1/PaymentController.php` | Payment index/store/show/refund (staff-gated write ops) | Created | Billing | — | High |
| `app/Http/Controllers/Api/V1/TicketController.php` | Ticket CRUD, reply, assign, status, messages, attachment download | Created | Tickets | — | Medium |
| `app/Http/Controllers/Api/V1/LeadController.php` | CRM leads: index/store/show/update, pipeline/stats, assign, convert, reminders | Created | CRM | — | Low |
| `app/Http/Controllers/Api/V1/LeadNoteController.php` | CRM lead notes CRUD | Created | CRM | — | Low |
| `app/Http/Controllers/Api/V1/LeadStageController.php` | CRM lead stage CRUD | Created | CRM | — | Low |
| `app/Http/Controllers/Api/V1/ContactController.php` | Public contact form + demo-request lead creation (rate-limited) | Created | CRM | — | Low |
| `app/Http/Controllers/Api/V1/CmsController.php` | Public pages/FAQs/settings; `/api/pages` fix | Created | CMS | — | Low |
| `app/Http/Controllers/Api/V1/AdminDashboardController.php` | Admin dashboard metrics (real DB queries incl. activations) | Created | Admin | — | Low |
| `app/Http/Controllers/Api/V1/Admin/DownloadAdminController.php` | Admin download index/stats/logs | Created | Admin | — | Low |
| `app/Http/Controllers/Api/V1/Admin/LicenseAdminController.php` | Admin license mgmt: list/show, renew/revoke/suspend/unsuspend/transfer/upgrade | Created | Admin/Licenses | — | High |
| `app/Http/Controllers/Api/V1/Admin/ProductAssignmentController.php` | Product↔staff assignment CRUD + user products | Created | Admin | — | Medium |
| `app/Http/Controllers/Api/V1/Admin/ReleaseController.php` | Product release CRUD + publish/deprecate/rollback | Created | Admin/Downloads | — | Medium |
| `app/Http/Controllers/Api/V1/Admin/BillingMetricsController.php` | Admin billing metrics endpoint | Created | Admin/Billing | — | Low |
| `app/Http/Controllers/Api/V1/Portal/PortalController.php` | Customer portal dashboard/invoices/downloads (owner-scoped) | Created | Portal | — | Medium |

## 2. Models

| File | Purpose | Status | Feature | Migration impact | Risk |
|---|---|---|---|---|---|
| `app/Models/User.php` | Rewritten: fillable/hidden/casts, role, status, profile fields, relations, isAdmin/isStaff, SoftDeletes | Modified | Auth/core | COLUMN(S) | Medium |
| `app/Models/Organization.php` | Typed relations, `users()` pivot w/ role+permissions, `setting()` | Modified | Multi-tenant | — | Low |
| `app/Models/OrganizationUser.php` | Pivot with role/permissions PHPDoc | Modified | Multi-tenant | — | Low |
| `app/Models/Product.php` | Product model (getRouteKeyName=slug, assignments, scopes) | Created | Products | — | Low |
| `app/Models/ProductCategory.php` | Product category model | Created | Products | — | Low |
| `app/Models/ProductRelease.php` | Release model (status lifecycle helpers) | Created | Products | — | Low |
| `app/Models/License.php` | License model + valid()/status scopes, activation relations | Created | Licenses | — | Low |
| `app/Models/LicenseActivation.php` | Activation model | Created | Licenses | — | Low |
| `app/Models/LicenseVerification.php` | Verification log model | Created | Licenses | — | Low |
| `app/Models/Order.php` | Order model + relations/status helpers | Created | Orders | — | Low |
| `app/Models/OrderItem.php` | Order item model | Created | Orders | — | Low |
| `app/Models/Invoice.php` | Rewritten billing model: relations, scopes (outstanding/overdue/paid/expired/forUser/byStatus), isPaid/isOverdue/cancelled, getPaidAmount/getRefundableAmount | Created | Billing | — | Low |
| `app/Models/InvoiceItem.php` | Invoice line item model | Created | Billing | — | Low |
| `app/Models/Payment.php` | Payment model (payment_type, refunded_amount, relations) | Created | Billing | — | Low |
| `app/Models/Ticket.php` | Ticket model + relations | Created | Tickets | — | Low |
| `app/Models/TicketMessage.php` | Ticket message model | Created | Tickets | — | Low |
| `app/Models/TicketAttachment.php` | Attachment model (disk/path storage) | Created | Tickets | — | Low |
| `app/Models/TicketCustomField.php` | Custom field model | Created | Tickets | — | Low |
| `app/Models/Lead.php` | Lead model + tag/activity/contact relations | Created | CRM | — | Low |
| `app/Models/LeadActivity.php` | Lead activity model (reminders use this) | Created | CRM | — | Low |
| `app/Models/LeadNote.php` | Lead note model | Created | CRM | — | Low |
| `app/Models/LeadStage.php` | Lead stage model (keys aligned to status enum) | Created | CRM | — | Low |
| `app/Models/Contact.php` | Contact model | Created | CRM | — | Low |
| `app/Models/Download.php` | Download model (active/forChannel scopes, formatted_size) | Created | Downloads | — | Low |
| `app/Models/DownloadLog.php` | Download log model | Created | Downloads | — | Low |
| `app/Models/Coupon.php` | Coupon model | Created | Orders | — | Low |
| `app/Models/ProductAssignment.php` | Product↔staff assignment model | Created | Admin | — | Low |
| `app/Models/OrganizationSetting.php` | Organization settings model | Created | Multi-tenant | — | Low |
| `app/Models/Setting.php` | Key/value settings model | Created | CMS | — | Low |
| `app/Models/Page.php` | Page model | Created | CMS | — | Low |
| `app/Models/Faq.php` | FAQ model | Created | CMS | — | Low |
| `app/Models/Tag.php` | Tag model | Created | CRM | — | Low |

## 3. Services

| File | Purpose | Status | Feature | Migration impact | Risk |
|---|---|---|---|---|---|
| `app/Services/LicenseService.php` | License domain logic (activation limits, desktop/portal flows) | Created | Licenses | — | Medium |
| `app/Services/OrderService.php` | Order creation, coupons, payment processing | Created | Orders | — | Medium |
| `app/Services/LeadService.php` | Lead creation/conversion/status/activities | Created | CRM | — | Low |
| `app/Services/TicketService.php` | Ticket create/reply/assign/status/attachments | Created | Tickets | — | Low |
| `app/Services/ProductReleaseService.php` | Release lifecycle (publish/deprecate/rollback) | Created | Products | — | Medium |
| `app/Services/ProductAssignmentService.php` | Product/staff assignment + scope checks | Created | Admin | — | Medium |
| `app/Services/InvoiceService.php` | Invoice CRUD, items sync, recalculation, metrics | Created | Billing | — | Medium |
| `app/Services/PaymentService.php` | Payments, partial/refund logic, metrics | Created | Billing | — | High |
| `app/Services/Interfaces/*.php` (8) | Interface contracts bound in AppServiceProvider | Created | All | — | Low |

## 4. Form Requests

| File | Purpose | Status | Feature | Migration impact | Risk |
|---|---|---|---|---|---|
| `app/Http/Requests/StoreOrderRequest.php` | Order creation validation | Created | Orders | — | Low |
| `app/Http/Requests/StoreTicketRequest.php` | Ticket creation validation | Created | Tickets | — | Low |
| `app/Http/Requests/StoreProductRequest.php` | Product creation validation | Created | Products | — | Low |
| `app/Http/Requests/StoreInvoiceRequest.php` | Invoice creation validation (**status restricted to draft for non-staff**) | Created | Billing | — | High |
| `app/Http/Requests/UpdateInvoiceRequest.php` | Invoice update validation | Created | Billing | — | Medium |
| `app/Http/Requests/PayInvoiceRequest.php` | Invoice payment validation | Created | Billing | — | Medium |
| `app/Http/Requests/StorePaymentRequest.php` | Payment recording validation (staff-gated) | Created | Billing | — | Medium |
| `app/Http/Requests/LicenseActivationRequest.php` | Activation/deactivation/verify validation | Created | Licenses | — | Medium |
| `app/Http/Requests/CreateReleaseRequest.php` | Release creation validation | Created | Products | — | Low |
| `app/Http/Requests/UpdateReleaseRequest.php` | Release update validation | Created | Products | — | Low |
| `app/Http/Requests/CreateProductAssignmentRequest.php` | Assignment creation validation | Created | Admin | — | Low |
| `app/Http/Requests/UpdateProductAssignmentRequest.php` | Assignment update validation | Created | Admin | — | Low |
| `app/Http/Requests/RenewLicenseRequest.php` | License renew validation | Created | Licenses | — | Low |
| `app/Http/Requests/SuspendLicenseRequest.php` | License suspend validation | Created | Licenses | — | Low |
| `app/Http/Requests/TransferLicenseRequest.php` | License transfer validation | Created | Licenses | — | Low |
| `app/Http/Requests/UpgradeLicenseRequest.php` | License upgrade validation | Created | Licenses | — | Low |
| `app/Http/Requests/StoreLeadRequest.php` | Lead creation validation | Created | CRM | — | Low |

## 5. API Resources

| File | Purpose | Status | Feature | Migration impact | Risk |
|---|---|---|---|---|---|
| `app/Http/Resources/UserResource.php` | User JSON shape | Created | Auth | — | Low |
| `app/Http/Resources/ProductResource.php` | Product JSON shape | Created | Products | — | Low |
| `app/Http/Resources/ProductCategoryResource.php` | Category JSON shape | Created | Products | — | Low |
| `app/Http/Resources/ProductReleaseResource.php` | Release JSON shape | Created | Products | — | Low |
| `app/Http/Resources/DownloadResource.php` | Download JSON shape | Created | Downloads | — | Low |
| `app/Http/Resources/LicenseResource.php` | License JSON shape | Created | Licenses | — | Low |
| `app/Http/Resources/LicenseActivationResource.php` | Activation JSON shape | Created | Licenses | — | Low |
| `app/Http/Resources/OrderResource.php` | Order JSON shape | Created | Orders | — | Low |
| `app/Http/Resources/OrderItemResource.php` | Order item JSON shape | Created | Orders | — | Low |
| `app/Http/Resources/InvoiceResource.php` | Invoice JSON shape | Created | Billing | — | Low |
| `app/Http/Resources/InvoiceItemResource.php` | Invoice item JSON shape | Created | Billing | — | Low |
| `app/Http/Resources/PaymentResource.php` | Payment JSON shape | Created | Billing | — | Low |
| `app/Http/Resources/TicketResource.php` | Ticket JSON shape | Created | Tickets | — | Low |
| `app/Http/Resources/TicketMessageResource.php` | Message JSON shape | Created | Tickets | — | Low |
| `app/Http/Resources/TicketAttachmentResource.php` | Attachment JSON shape | Created | Tickets | — | Low |
| `app/Http/Resources/TicketCustomFieldResource.php` | Custom field JSON shape | Created | Tickets | — | Low |
| `app/Http/Resources/LeadResource.php` | Lead JSON shape | Created | CRM | — | Low |
| `app/Http/Resources/LeadActivityResource.php` | Activity JSON shape | Created | CRM | — | Low |
| `app/Http/Resources/LeadNoteResource.php` | Note JSON shape | Created | CRM | — | Low |
| `app/Http/Resources/LeadStageResource.php` | Stage JSON shape | Created | CRM | — | Low |
| `app/Http/Resources/ContactResource.php` | Contact JSON shape | Created | CRM | — | Low |
| `app/Http/Resources/TagResource.php` | Tag JSON shape | Created | CRM | — | Low |
| `app/Http/Resources/FaqResource.php` | FAQ JSON shape | Created | CMS | — | Low |
| `app/Http/Resources/ProductAssignmentResource.php` | Assignment JSON shape | Created | Admin | — | Low |

## 6. Middleware & Exceptions

| File | Purpose | Status | Feature | Migration impact | Risk |
|---|---|---|---|---|---|
| `app/Http/Middleware/EnsureUserIsAdmin.php` | `admin` alias — role must be `admin` | Created (recreated, was root-owned) | Auth | — | High |
| `app/Http/Middleware/EnsureUserIsStaff.php` | `staff` alias — role in `[admin, staff]` | Created (recreated, was root-owned) | Auth | — | High |
| `app/Http/Middleware/SetCurrentOrganization.php` | Organization resolution middleware (typed return, cleanup) | Modified | Multi-tenant | — | Low |
| `app/Exceptions/LicenseException.php` | License domain exception | Created | Licenses | — | Low |
| `app/Exceptions/ReleaseException.php` | Release domain exception | Created | Products | — | Low |
| `app/Exceptions/AssignmentException.php` | Assignment domain exception | Created | Admin | — | Low |

## 7. Migrations

| File | Purpose | Status | Feature | Migration impact | Risk |
|---|---|---|---|---|---|
| `database/migrations/2026_01_01_000002_create_products_table.php` | products, product_categories, product_category_product | Created | Products | NEW TABLE ×3 | Medium |
| `database/migrations/2026_01_01_000003_create_licenses_table.php` | licenses, license_activations, license_verifications | Created | Licenses | NEW TABLE ×3 | Medium |
| `database/migrations/2026_01_01_000004_create_orders_table.php` | orders, order_items, payments, invoices (original) | Created | Orders/Billing | NEW TABLE ×4 | Medium |
| `database/migrations/2026_01_01_000005_create_leads_table.php` | leads, lead_activities, tags, taggables, contacts | Created | CRM | NEW TABLE ×5 | Medium |
| `database/migrations/2026_01_01_000006_create_tickets_downloads_table.php` | tickets, ticket_messages, ticket_attachments, ticket_custom_fields, downloads, download_logs | Created | Tickets/Downloads | NEW TABLE ×6 | Medium |
| `database/migrations/2026_01_01_000007_create_settings_pages_table.php` | settings, media, pages, faqs, coupons, notifications | Created | CMS | NEW TABLE ×6 | Medium |
| `database/migrations/2026_07_23_000001_sprint1_product_core.php` | product_releases; downloads +platform/status/release FK; products activation fields; license_activations identifier; settings key swap; product_assignments | Created | Products/Downloads | NEW TABLE ×2 + COLUMN(S)/FK/REWRITE | High |
| `database/migrations/2026_07_28_145156_create_organization_settings_table.php` | organization_settings | Created | Multi-tenant | NEW TABLE | Medium |
| `database/migrations/2026_08_04_000001_add_profile_fields_to_users_table.php` | users profile/role/status columns + soft deletes | Created | Auth | COLUMN(S) + INDEX | Medium |
| `database/migrations/2026_08_07_000001_add_storage_to_ticket_attachments_table.php` | ticket_attachments disk + path | Created | Tickets | COLUMN(S) | Low |
| `database/migrations/2026_08_07_141008_make_domain_nullable_on_license_activations_table.php` | license_activations.domain nullable | Created | Licenses | COLUMN(S) | Low |
| `database/migrations/2026_08_07_143118_create_lead_stages_table.php` | lead_stages | Created | CRM | NEW TABLE | Low |
| `database/migrations/2026_08_07_143119_create_lead_notes_table.php` | lead_notes | Created | CRM | NEW TABLE | Low |
| `database/migrations/2026_08_07_145914_create_invoice_items_table.php` | invoice_items | Created | Billing | NEW TABLE | Medium |
| `database/migrations/2026_08_07_145915_add_billing_fields_to_invoices_table.php` | invoices: discount, balance_due, organization_id, status enum +sent, indexes | Created | Billing | COLUMN(S) + ENUM + INDEX | High |
| `database/migrations/2026_08_07_145916_add_invoice_relation_to_payments_table.php` | payments: invoice_id, payment_type, refunded_amount, order_id nullable | Created | Billing | COLUMN(S) + FK + INDEX | High |
| `database/migrations/2026_07_26_072106_create_organizations_table.php` | organizations (formatting only) | Modified | Multi-tenant | — (reformat) | Low |
| `database/migrations/2026_07_26_072801_create_organization_user_table.php` | organization_user (formatting only) | Modified | Multi-tenant | — (reformat) | Low |
| `database/migrations/2026_07_26_080807_add_role_to_organization_user_table.php` | No-op up (empty up; role moved to 083046) | Modified | Multi-tenant | — | Low |
| `database/migrations/2026_07_26_083046_add_role_permissions_columns_to_organization_user_table.php` | organization_user role + permissions | Modified | Multi-tenant | COLUMN(S) | Medium |

> Note: the shared base `0001_01_01_*` (users/cache/jobs/password_reset_tokens/sessions/personal_access_tokens/permission tables) are baseline Laravel scaffolding and are unchanged from the baseline commit.

## 8. Factories

| File | Purpose | Status | Feature | Migration impact | Risk |
|---|---|---|---|---|---|
| `database/factories/UserFactory.php` | User factory (role/status/profile) | Modified | Auth | — | Low |
| `database/factories/ProductFactory.php` | Product factory | Created | Products | — | Low |
| `database/factories/ProductCategoryFactory.php` | Category factory | Created | Products | — | Low |
| `database/factories/ProductReleaseFactory.php` | Release factory (**collision fix: `1.{1-999}.{1-999}`**) | Created | Products | — | Low |
| `database/factories/LicenseFactory.php` | License factory | Created | Licenses | — | Low |
| `database/factories/LicenseActivationFactory.php` | Activation factory | Created | Licenses | — | Low |
| `database/factories/OrderFactory.php` | Order factory | Created | Orders | — | Low |
| `database/factories/DownloadFactory.php` | Download factory | Created | Downloads | — | Low |
| `database/factories/DownloadLogFactory.php` | Download log factory | Created | Downloads | — | Low |
| `database/factories/TicketFactory.php` | Ticket factory | Created | Tickets | — | Low |
| `database/factories/LeadFactory.php` | Lead factory | Created | CRM | — | Low |
| `database/factories/LeadActivityFactory.php` | Activity factory | Created | CRM | — | Low |
| `database/factories/LeadNoteFactory.php` | Note factory | Created | CRM | — | Low |
| `database/factories/LeadStageFactory.php` | Stage factory | Created | CRM | — | Low |
| `database/factories/TagFactory.php` | Tag factory | Created | CRM | — | Low |
| `database/factories/InvoiceFactory.php` | Invoice factory | Created | Billing | — | Low |
| `database/factories/InvoiceItemFactory.php` | Invoice item factory | Created | Billing | — | Low |
| `database/factories/PaymentFactory.php` | Payment factory (no `user_id` column) | Created | Billing | — | Low |

## 9. Seeders

| File | Purpose | Status | Feature | Migration impact | Risk |
|---|---|---|---|---|---|
| `database/seeders/DatabaseSeeder.php` | Calls all 10 seeders | Modified | All | — | Low |
| `database/seeders/UserSeeder.php` | Admin/staff/customer users | Created | Auth | — | Low |
| `database/seeders/CustomerSeeder.php` | 12 realistic Persian customers | Created | Auth | — | Low |
| `database/seeders/ProductSeeder.php` | Products + releases | Created | Products | — | Low |
| `database/seeders/LicenseSeeder.php` | Licenses + activations | Created | Licenses | — | Low |
| `database/seeders/OrderSeeder.php` | Orders with invoice-before-payment link fix | Created | Orders | — | Low |
| `database/seeders/InvoiceSeeder.php` | 15 invoices across statuses with items/discounts/tax | Created | Billing | — | Low |
| `database/seeders/PaymentSeeder.php` | Partial/refunded payments | Created | Billing | — | Low |
| `database/seeders/LeadSeeder.php` | Leads + stages + notes | Created | CRM | — | Low |
| `database/seeders/TicketSeeder.php` | Support tickets | Created | Tickets | — | Low |
| `database/seeders/DownloadSeeder.php` | Download records | Created | Downloads | — | Low |

## 10. Routes & Config

| File | Purpose | Status | Feature | Migration impact | Risk |
|---|---|---|---|---|---|
| `routes/api.php` | Full API route map + rate limiters + middleware aliases | Modified | All | — | High |
| `routes/admin.php` | Admin sub-routes (dashboard, billing, products, releases, downloads, assignments, licenses) | Created | Admin | — | High |
| `bootstrap/app.php` | Middleware alias registration | Modified | Multi-tenant | — | Low |
| `config/sanctum.php` | Cast-safe env access | Modified | Auth | — | Low |
| `config/dompdf.php` | DomPDF (RTL invoice) config | Created | Billing | — | Low |
| `config/scramble.php` | OpenAPI generator config (bearer security, info, docs access) | Created | Docs | — | Low |
| `composer.json` / `composer.lock` | Added barryvdh/laravel-dompdf, dedoc/scramble, spatie/laravel-sluggable | Modified | Billing/Docs | — | Low |

## 11. Views

| File | Purpose | Status | Feature | Migration impact | Risk |
|---|---|---|---|---|---|
| `resources/views/pdf/invoice.blade.php` | RTL invoice PDF template | Created | Billing | — | Low |
| `resources/views/vendor/scramble/*.blade.php` (2) | Scramble docs UI templates | Created | Docs | — | Low |

## 12. Tests

| File | Purpose | Status | Feature | Migration impact | Risk |
|---|---|---|---|---|---|
| `tests/Feature/AuthApiTest.php` | Auth flow tests | Created | Auth | — | Low |
| `tests/Feature/ProductApiTest.php` | Product/release tests | Created | Products | — | Low |
| `tests/Feature/LicenseApiTest.php` | License API tests | Created | Licenses | — | Low |
| `tests/Feature/LicenseManagementApiTest.php` | License admin tests | Created | Licenses | — | Low |
| `tests/Feature/OrderApiTest.php` | Order tests | Created | Orders | — | Low |
| `tests/Feature/BillingApiTest.php` | 27 billing tests | Created | Billing | — | Low |
| `tests/Feature/TicketApiTest.php` | Ticket tests | Created | Tickets | — | Low |
| `tests/Feature/TicketAttachmentTest.php` | Attachment tests | Created | Tickets | — | Low |
| `tests/Feature/LeadApiTest.php` | CRM lead tests | Created | CRM | — | Low |
| `tests/Feature/CrmPipelineApiTest.php` | Pipeline tests | Created | CRM | — | Low |
| `tests/Feature/CustomerPortalTest.php` | Portal tests | Created | Portal | — | Low |
| `tests/Feature/DownloadManagementApiTest.php` | Download tests | Created | Downloads | — | Low |
| `tests/Feature/CmsApiTest.php` | CMS pages/settings tests | Created | CMS | — | Low |
| `tests/Feature/RouteSmokeTest.php` | Route targets + service bindings smoke test | Created | Verification | — | Low |
| `tests/Feature/EndpointValidationTest.php` | ~120 endpoint interactions | Created | Verification | — | Low |
| `tests/Feature/DashboardMetricsTest.php` | Dashboard metrics vs raw DB aggregates | Created | Verification | — | Low |
| `tests/Feature/BillingMetricsVerificationTest.php` | Billing metrics incl. partial/refunds | Created | Verification | — | Low |
| `tests/Feature/AdminMetricsSmokeTest.php` | Admin metrics smoke | Created | Verification | — | Low |
| `tests/Feature/ApiDocsTest.php` | Docs access control tests | Created | Docs | — | Low |
| `tests/Feature/SecurityCheckTest.php` | Security regression tests (invoice/ticket/order authz) | Created | Verification | — | Low |
| `tests/Feature/ExampleTest.php` / `tests/Unit/ExampleTest.php` | Sanitized default tests | Modified | — | — | Low |

## 13. Docs & Tooling

| File | Purpose | Status | Feature | Migration impact | Risk |
|---|---|---|---|---|---|
| `docs/openapi.json` | Generated OpenAPI spec (96 paths / 116 ops / 48 schemas) | Created | Docs | — | Low |
| `docs/API_REFERENCE.md` | Endpoint reference by domain | Created | Docs | — | Low |
| `docs/SECURITY_AUDIT.md` | Security audit findings | Created | Docs | — | Low |
| `docs/BETA_RELEASE_REPORT.md` | Beta release report | Created | Docs | — | Low |
| `DEPLOYMENT_CHECKLIST.md` | Deploy checklist (nginx/SSL added) | Created | Docs | — | Low |
| `phpstan.neon` | Added `parseModelCastsMethod: true` | Modified | Tooling | — | Low |
| `phpunit.xml` | Raised `memory_limit` to 512M (DomPDF/Scramble) | Modified | Tooling | — | Low |
| `.2026_07_26_081709_*.php.swp` | Stray Vim swap file (deleted) | Deleted | — | — | Low |

---

## Summary

- **Modified:** 22 files (incl. composer.lock, migrations reformat, models typing, AuthController rewrite, routes)
- **Created:** 120 files (19 controllers, 32 models, 8 services + 8 interfaces, 17 requests, 24 resources, 2 middleware + 3 exceptions, 16 migrations, 18 factories, 10 seeders, 2 routes/config, 3 views, 20 tests, 4 docs)
- **Deleted:** 1 stray swap file
- **Schema impact concentrated in:** `2026_01_01_00000{2..7}`, `2026_07_23_000001` (HIGH — settings constraint swap), and the three `2026_08_07` billing migrations (HIGH — enum change + nullable FK).

## Deployment Notes

- **Order matters:** run migrations sequentially; `2026_08_07_145915` alters the invoices `status` enum — verify no legacy values conflict on MySQL (enum expansion is safe; contraction is not — none were removed).
- **`2026_07_23_000001`** drops the unique constraint on `settings.key` in favor of `(key, product_id)` after a duplicate check — safe on fresh installs; on existing prod data it validates duplicates first.
- **No destructive `down()` is expected to be run in production**; all `down()` methods exist for dev rollback.
- Files flagged **High** are those that handle security boundaries (auth, middleware, billing writes) or destructive schema changes; none represent open defects — they are audited and tested.
