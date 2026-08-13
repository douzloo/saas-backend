# Douzloo API Reference

REST API for the Douzloo SaaS platform.

- **Base URL:** `https://<your-domain>/api`
- **Format:** JSON
- **Version:** 1.0.0-beta
- **Auth:** Bearer token (Sanctum) for protected endpoints

## Interactive Documentation

- **UI:** `GET /docs/api` (requires staff role in production; public in local)
- **OpenAPI JSON:** `GET /docs/api.json`
- **Spec file:** `docs/openapi.json` (committed, auto-generated via `dedoc/scramble`)

> Regenerate the spec with: `php artisan scramble:export --path=docs/openapi.json`

## Authentication

Protected endpoints require an `Authorization: Bearer <token>` header. Obtain a token:

```
POST /api/auth/login
Content-Type: application/json

{ "email": "customer@example.com", "password": "password" }
```

Response:

```json
{
  "token": "1|abcdef...",
  "user": { "id": 3, "name": "...", "role": "customer" }
}
```

### Roles

| Role | Permissions |
|---|---|
| `admin` | Everything, including `/api/admin/*` |
| `staff` | CRM, tickets, downloads, licenses, billing operations |
| `customer` | Own portal, own orders/invoices/licenses/tickets |

---

## 1. Auth

| Method | Endpoint | Auth | Description |
|---|---|---|---|
| POST | `/api/auth/register` | public | Register a customer account |
| POST | `/api/auth/login` | public | Login, returns bearer token |
| POST | `/api/auth/logout` | bearer | Revoke current token |
| GET | `/api/auth/user` | bearer | Current authenticated user |
| PUT | `/api/auth/profile` | bearer | Update name/phone/company/profile |
| PUT | `/api/auth/password` | bearer | Change password |

---

## 2. Products & CMS

| Method | Endpoint | Auth | Description |
|---|---|---|---|
| GET | `/api/products` | public | List active products |
| GET | `/api/products/categories` | public | List categories |
| GET | `/api/products/{product:slug}` | public | Product detail + FAQs + releases |
| GET | `/api/products/{product:slug}/faqs` | public | Product FAQs |
| GET | `/api/products/{product:slug}/releases` | public | Published releases |
| GET | `/api/products/{product:slug}/releases/latest` | public | Latest published release |
| GET | `/api/settings` | public | Public site settings |
| GET | `/api/pages` | public | Published pages |
| GET | `/api/pages/{slug}` | public | Single published page |
| POST | `/api/contact` | public | Submit contact form (creates a lead) |

---

## 3. Downloads

| Method | Endpoint | Auth | Description |
|---|---|---|---|
| GET | `/api/downloads` | public | List downloads (`?channel=stable\|beta`) |
| GET | `/api/downloads/latest` | public | Latest available downloads |
| GET | `/api/downloads/product/{product:slug}` | public | Downloads for a product |
| GET | `/api/downloads/{download}` | public* | Download a file (gated) |

*`/api/downloads/{download}` honors `canDownload()`: available without auth, or gated by a valid license key / logged-in owner for protected files.

---

## 4. Licensing

### Public / machine endpoints

| Method | Endpoint | Auth | Description |
|---|---|---|---|
| POST | `/api/license/activate` | public | Activate a license (domain/identifier) |
| POST | `/api/license/deactivate` | public | Deactivate an activation |
| POST | `/api/license/verify` | public | Verify license + heartbeat semantics |
| POST | `/api/license/heartbeat` | public | Send heartbeat, refresh last-seen |

### Customer portal licenses

| Method | Endpoint | Auth | Description |
|---|---|---|---|
| GET | `/api/portal/licenses` | bearer | Current user's licenses |
| GET | `/api/portal/licenses/{key}` | bearer | License detail + activations |
| POST | `/api/portal/licenses/activate` | bearer | Activate via portal |
| POST | `/api/portal/licenses/deactivate` | bearer | Deactivate via portal |
| POST | `/api/portal/licenses/heartbeat` | bearer | Heartbeat via portal |

---

## 5. CRM

| Method | Endpoint | Auth | Description |
|---|---|---|---|
| GET | `/api/crm/leads` | staff | List leads (`?status=&search=&assignee=`) |
| POST | `/api/crm/leads` | staff | Create lead |
| GET | `/api/crm/leads/{lead}` | staff | Lead detail |
| PUT | `/api/crm/leads/{lead}` | staff | Update lead |
| PUT | `/api/crm/leads/{lead}/status` | staff | Update status (pipeline movement) |
| POST | `/api/crm/leads/bulk-assign` | staff | Bulk assign leads to a user |
| GET | `/api/crm/leads/pipeline` | staff | Pipeline summary by stage |
| GET | `/api/crm/leads/stats` | staff | Lead statistics |
| GET | `/api/crm/leads/reminders` | staff | Upcoming reminders |
| GET | `/api/crm/leads/{lead}/activities` | staff | Activity history |
| POST | `/api/crm/leads/{lead}/activities` | staff | Log an activity |
| POST | `/api/crm/leads/{lead}/assign` | staff | Assign lead |
| POST | `/api/crm/leads/{lead}/convert` | staff | Convert to customer |
| GET | `/api/crm/leads/{lead}/notes` | staff | List notes |
| POST | `/api/crm/leads/{lead}/notes` | staff | Add note |
| PUT | `/api/crm/lead-notes/{note}` | staff | Update note |
| DELETE | `/api/crm/lead-notes/{note}` | staff | Delete note |
| GET | `/api/crm/lead-stages` | staff | List pipeline stages |
| POST | `/api/crm/lead-stages` | staff | Create stage |
| PUT | `/api/crm/lead-stages/{stage}` | staff | Update stage |
| DELETE | `/api/crm/lead-stages/{stage}` | staff | Delete stage |
| POST | `/api/crm/leads/{lead}/reminders` | staff | Create reminder |
| PUT | `/api/crm/reminders/{activity}/complete` | staff | Complete reminder |

---

## 6. Orders

| Method | Endpoint | Auth | Description |
|---|---|---|---|
| GET | `/api/orders` | bearer | List own orders |
| POST | `/api/orders` | bearer | Create an order |
| GET | `/api/orders/{order}` | bearer | Own order detail |
| POST | `/api/orders/{order}/pay` | bearer | Pay an order (creates invoice + payment) |
| POST | `/api/orders/{order}/coupon` | bearer | Apply a coupon |
| GET | `/api/orders/{order}/invoice` | bearer | Invoice for the order |

---

## 7. Billing — Invoices & Payments

| Method | Endpoint | Auth | Description |
|---|---|---|---|
| GET | `/api/invoices` | staff | List invoices (`?status=`) |
| POST | `/api/invoices` | staff | Create invoice (with items) |
| GET | `/api/invoices/{invoice}` | staff/owner | Invoice detail |
| PUT | `/api/invoices/{invoice}` | staff | Update invoice |
| DELETE | `/api/invoices/{invoice}` | staff | Delete invoice |
| POST | `/api/invoices/{invoice}/pay` | staff/owner | Record a payment |
| GET | `/api/invoices/{invoice}/pdf` | staff/owner | Download PDF (DomPDF, RTL) |
| GET | `/api/payments` | staff | List payments |
| POST | `/api/payments` | staff | Record payment for an invoice |
| GET | `/api/payments/{payment}` | staff | Payment detail |
| POST | `/api/payments/{payment}/refund` | staff | Refund a payment |

Invoice statuses: `draft`, `sent`, `issued`, `paid`, `overdue`, `cancelled`.
Payment statuses: `pending`, `completed`, `failed`, `refunded`.

---

## 8. Tickets

| Method | Endpoint | Auth | Description |
|---|---|---|---|
| GET | `/api/tickets` | bearer | List own tickets (staff: all) |
| POST | `/api/tickets` | bearer | Open a ticket |
| GET | `/api/tickets/{ticket}` | owner/staff | Ticket detail + messages |
| POST | `/api/tickets/{ticket}/messages` | owner/staff | Reply / add message |
| GET | `/api/tickets/{ticket}/messages` | owner/staff | List messages |
| PUT | `/api/tickets/{ticket}/status` | staff | Change status |
| POST | `/api/tickets/{ticket}/assign` | staff | Assign ticket |

---

## 9. Admin

All admin routes require the `admin` role (except where noted as staff).

### Dashboard

| Method | Endpoint | Auth | Description |
|---|---|---|---|
| GET | `/api/admin/dashboard` | admin | KPIs: users, orders, revenue, licenses, tickets, leads, downloads |
| GET | `/api/admin/dashboard/recent-orders` | admin | 10 most recent orders |
| GET | `/api/admin/dashboard/recent-leads` | admin | 10 most recent leads |
| GET | `/api/admin/dashboard/recent-tickets` | admin | 10 most recent tickets |
| GET | `/api/admin/billing/metrics` | admin | Revenue, outstanding, overdue, success rate |

### Products & Releases

| Method | Endpoint | Auth | Description |
|---|---|---|---|
| POST | `/api/admin/products` | admin | Create product |
| PUT | `/api/admin/products/{product}` | admin | Update product |
| DELETE | `/api/admin/products/{product}` | admin | Delete product |
| GET | `/api/admin/products/{product}/releases` | admin | List releases |
| POST | `/api/admin/products/{product}/releases` | admin | Create release |
| GET | `/api/admin/products/{product}/releases/{release}` | admin | Release detail |
| PUT | `/api/admin/products/{product}/releases/{release}` | admin | Update release |
| DELETE | `/api/admin/products/{product}/releases/{release}` | admin | Delete release |
| POST | `/api/admin/products/{product}/releases/{release}/publish` | admin | Publish release |
| POST | `/api/admin/products/{product}/releases/{release}/deprecate` | admin | Deprecate release |
| POST | `/api/admin/products/{product}/releases/{release}/rollback` | admin | Rollback release |

### Product Staff Assignment

| Method | Endpoint | Auth | Description |
|---|---|---|---|
| GET | `/api/admin/products/{product}/staff` | admin | List assigned staff |
| POST | `/api/admin/products/{product}/staff` | admin | Assign staff |
| PUT | `/api/admin/products/{product}/staff/{assignment}` | admin | Update role |
| DELETE | `/api/admin/products/{product}/staff/{assignment}` | admin | Remove staff |
| GET | `/api/admin/users/{user}/products` | admin | Products a user can access |

### Licenses

| Method | Endpoint | Auth | Description |
|---|---|---|---|
| GET | `/api/admin/licenses` | admin | List all licenses |
| GET | `/api/admin/licenses/{license}` | admin | License detail |
| GET | `/api/admin/licenses/{license}/activations` | admin | List activations |
| GET | `/api/admin/licenses/stale-activations` | admin | Stale activations |
| POST | `/api/admin/licenses/{license}/suspend` | admin | Suspend |
| POST | `/api/admin/licenses/{license}/unsuspend` | admin | Unsuspend |
| POST | `/api/admin/licenses/{license}/revoke` | admin | Revoke |
| POST | `/api/admin/licenses/{license}/renew` | admin | Renew (days) |
| PUT | `/api/admin/licenses/{license}/upgrade` | admin | Upgrade type/max_activations |
| PUT | `/api/admin/licenses/{license}/transfer` | admin | Transfer to another user |

### Downloads (staff+)

| Method | Endpoint | Auth | Description |
|---|---|---|---|
| GET | `/api/admin/downloads` | staff | List downloads |
| GET | `/api/admin/downloads/stats` | staff | Download statistics |
| GET | `/api/admin/download-logs` | staff | Download log entries |

---

## Portal (customer)

| Method | Endpoint | Auth | Description |
|---|---|---|---|
| GET | `/api/portal/dashboard` | bearer | Portal summary |
| GET | `/api/portal/downloads` | bearer | Downloads available to the user |
| GET | `/api/portal/invoices` | bearer | Own invoices |
| GET | `/api/portal/invoices/{invoice}` | bearer | Own invoice detail + payments |

---

## Common Response Shape

Collection endpoints return `{ "data": [...] }`; single resources return `{ "data": { ... } }`. Form validation errors return `422` with `{ "message": ..., "errors": { field: [...] } }`.

## Error Codes

| Code | Meaning |
|---|---|
| 200 | OK |
| 201 | Created |
| 401 | Unauthenticated / invalid or missing token |
| 403 | Authenticated but not authorized (wrong role / not owner) |
| 404 | Not found |
| 422 | Validation failed |
| 429 | Rate limit exceeded (auth: 5/min, global API: 60/min, contact: 10/min) |
