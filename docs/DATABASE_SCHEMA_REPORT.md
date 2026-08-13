# Database Schema Report — Douzloo SaaS Backend

Derived from the 26 migration files in `database/migrations/`. All tables listed with columns, types, nullability/defaults, indexes, and foreign keys. Total tables: **51**.

Legend: PK=primary key, UQ=unique index, IDX=index, FK=foreign key, `—`=none.

---

## Core / Framework Tables

### `users`
| Column | Type | Null | Default |
|---|---|---|---|
| id | bigint (PK) | no | auto |
| name | string(255) | no | — |
| email | string(255) | no | — |
| phone | string(20) | yes | null |
| avatar | string(255) | yes | null |
| role | enum('admin','staff','customer') | no | customer |
| status | enum('active','inactive','suspended') | no | active |
| company | string(255) | yes | null |
| national_id | string(20) | yes | null |
| address | text | yes | null |
| city | string(255) | yes | null |
| province | string(255) | yes | null |
| postal_code | string(20) | yes | null |
| locale | string(10) | no | fa |
| email_verified_at | timestamp | yes | null |
| password | string(255) | no | — |
| remember_token | string(100) | yes | null |
| last_login_at | timestamp | yes | null |
| last_login_ip | string(45) | yes | null |
| created_at / updated_at | timestamp | yes | null |
| deleted_at | timestamp | yes | null |

- UQ: `users_email_unique`
- IDX: `users_role_index`, `users_status_index`

### `password_reset_tokens`
| Column | Type | Null | Default |
|---|---|---|---|
| email | string(255) (PK) | no | — |
| token | string(255) | no | — |
| created_at | timestamp | yes | null |

### `sessions`
| Column | Type | Null | Default |
|---|---|---|---|
| id | string (PK) | no | — |
| user_id | bigint | yes | null |
| ip_address | string(45) | yes | null |
| user_agent | text | yes | null |
| payload | longText | no | — |
| last_activity | integer | no | — |

- IDX: `sessions_user_id_index`, `sessions_last_activity_index`
- FK: `sessions_user_id_foreign` → `users(id)`

### `cache`
| Column | Type | Null | Default |
|---|---|---|---|
| key | string (PK) | no | — |
| value | mediumText | no | — |
| expiration | bigint | no | — |

- IDX: `cache_expiration_index`

### `cache_locks`
| Column | Type | Null | Default |
|---|---|---|---|
| key | string (PK) | no | — |
| owner | string(255) | no | — |
| expiration | bigint | no | — |

- IDX: `cache_locks_expiration_index`

### `jobs`
| Column | Type | Null | Default |
|---|---|---|---|
| id | bigint (PK) | no | auto |
| queue | string(255) | no | — |
| payload | longText | no | — |
| attempts | unsignedSmallInteger | no | — |
| reserved_at | unsignedInteger | yes | null |
| available_at | unsignedInteger | no | — |
| created_at | unsignedInteger | no | — |

- IDX: `jobs_queue_index`

### `job_batches`
| Column | Type | Null | Default |
|---|---|---|---|
| id | string (PK) | no | — |
| name | string(255) | no | — |
| total_jobs | integer | no | — |
| pending_jobs | integer | no | — |
| failed_jobs | integer | no | — |
| failed_job_ids | longText | no | — |
| options | mediumText | yes | null |
| cancelled_at | integer | yes | null |
| created_at | integer | no | — |
| finished_at | integer | yes | null |

### `failed_jobs`
| Column | Type | Null | Default |
|---|---|---|---|
| id | bigint (PK) | no | auto |
| uuid | string(255) | no | — |
| connection | string(255) | no | — |
| queue | string(255) | no | — |
| payload | longText | no | — |
| exception | longText | no | — |
| failed_at | timestamp | no | current |

- UQ: `failed_jobs_uuid_unique`
- IDX: `failed_jobs_queue_index`

### `personal_access_tokens`
| Column | Type | Null | Default |
|---|---|---|---|
| id | bigint (PK) | no | auto |
| tokenable_type | string(255) | no | — |
| tokenable_id | bigint | no | — |
| name | string(255) | no | — |
| token | string(64) | no | — |
| abilities | text | yes | null |
| last_used_at | timestamp | yes | null |
| expires_at | timestamp | yes | null |
| created_at / updated_at | timestamp | yes | null |

- UQ: `personal_access_tokens_token_unique`
- IDX: `personal_access_tokens_tokenable_type_tokenable_id_index`, `personal_access_tokens_expires_at_index`

### `notifications`
| Column | Type | Null | Default |
|---|---|---|---|
| id | uuid (PK) | no | — |
| type | string(255) | no | — |
| notifiable_type | string(255) | no | — |
| notifiable_id | bigint | no | — |
| data | text | no | — |
| read_at | timestamp | yes | null |
| created_at / updated_at | timestamp | yes | null |

### `media`
| Column | Type | Null | Default |
|---|---|---|---|
| id | bigint (PK) | no | auto |
| name | string(255) | no | — |
| file_name | string(255) | no | — |
| mime_type | string(255) | no | — |
| disk | string(20) | no | public |
| size | bigint | no | — |
| path | string(255) | no | — |
| alt_text | string(255) | yes | null |
| title | string(255) | yes | null |
| custom_properties | json | yes | null |
| created_at / updated_at | timestamp | yes | null |

---

## Multi-Tenancy

### `organizations`
| Column | Type | Null | Default |
|---|---|---|---|
| id | bigint (PK) | no | auto |
| name | string(255) | no | — |
| slug | string(255) | no | — |
| owner_id | bigint | no | — |
| status | enum('active','inactive') | no | active |
| created_at / updated_at | timestamp | yes | null |

- UQ: `organizations_slug_unique`
- FK: `organizations_owner_id_foreign` → `users(id)` (CASCADE)

### `organization_user`
| Column | Type | Null | Default |
|---|---|---|---|
| id | bigint (PK) | no | auto |
| organization_id | bigint | no | — |
| user_id | bigint | no | — |
| role | string(255) | no | member |
| permissions | json | yes | null |
| created_at / updated_at | timestamp | yes | null |

- UQ: `organization_user_organization_id_user_id_unique`
- FK: `organization_user_organization_id_foreign` → `organizations(id)` (CASCADE)
- FK: `organization_user_user_id_foreign` → `users(id)` (CASCADE)

### `organization_settings`
| Column | Type | Null | Default |
|---|---|---|---|
| id | bigint (PK) | no | auto |
| organization_id | bigint | no | — |
| company_name | string(255) | no | — |
| brand_name | string(255) | yes | null |
| logo | string(255) | yes | null |
| phone | string(255) | yes | null |
| mobile | string(255) | yes | null |
| email | string(255) | yes | null |
| website | string(255) | yes | null |
| address | text | yes | null |
| currency | string(10) | no | IRR |
| timezone | string(255) | no | Asia/Tehran |
| locale | string(255) | no | fa |
| fiscal_year_start | string(255) | no | 01-01 |
| invoice_sequence | unsignedInteger | no | 1 |
| tax_percent | decimal(8,2) | no | 0 |
| settings | json | yes | null |
| created_at / updated_at | timestamp | yes | null |

- UQ: `organization_settings_organization_id_unique`
- FK: `organization_settings_organization_id_foreign` → `organizations(id)` (CASCADE)

---

## Products & Releases

### `products`
| Column | Type | Null | Default |
|---|---|---|---|
| id | bigint (PK) | no | auto |
| name | string(255) | no | — |
| slug | string(255) | no | — |
| description | text | yes | null |
| short_description | text | yes | null |
| version | string(20) | no | 1.0.0 |
| sku | string(50) | yes | null |
| price | decimal(12,2) | no | 0 |
| trial_days | decimal(5,0) | no | 0 |
| icon | string(255) | yes | null |
| screenshot | string(255) | yes | null |
| type | enum('standard','pro','enterprise') | no | standard |
| status | enum('active','inactive','archived') | no | active |
| is_downloadable | boolean | no | false |
| requires_activation | boolean | no | true |
| activation_strategy | enum('none','domain','machine','hybrid') | yes | null |
| default_max_activations | integer | no | 1 |
| latest_release_version | string(20) | yes | null |
| category | string(50) | yes | null |
| max_domains | integer | no | 1 |
| features | json | yes | null |
| requirements | json | yes | null |
| changelog | text | yes | null |
| installation_guide | text | yes | null |
| download_url | string(255) | yes | null |
| file_size | bigint | yes | null |
| file_hash | string(64) | yes | null |
| sort_order | unsignedInteger | no | 0 |
| created_at / updated_at | timestamp | yes | null |
| deleted_at | timestamp | yes | null |

- UQ: `products_slug_unique`, `products_sku_unique`
- IDX: `products_status_index`, `products_type_index`

### `product_categories`
| Column | Type | Null | Default |
|---|---|---|---|
| id | bigint (PK) | no | auto |
| name | string(255) | no | — |
| slug | string(255) | no | — |
| description | text | yes | null |
| parent_id | bigint | yes | null |
| sort_order | unsignedInteger | no | 0 |
| created_at / updated_at | timestamp | yes | null |

- UQ: `product_categories_slug_unique`
- FK: `product_categories_parent_id_foreign` → `product_categories(id)` (SET NULL)

### `product_category_product`
| Column | Type | Null | Default |
|---|---|---|---|
| product_id | bigint | no | — |
| product_category_id | bigint | no | — |

- PK: composite `(product_id, product_category_id)`
- FK: `product_id` → `products(id)` (CASCADE)
- FK: `product_category_id` → `product_categories(id)` (CASCADE)

### `product_releases`
| Column | Type | Null | Default |
|---|---|---|---|
| id | bigint (PK) | no | auto |
| product_id | bigint | no | — |
| version | string(20) | no | — |
| channel | enum('stable','beta','alpha','nightly') | no | stable |
| status | enum('draft','published','deprecated','rolled_back') | no | draft |
| release_notes | longText | yes | null |
| changelog | text | yes | null |
| is_force_update | boolean | no | false |
| min_app_version | string(20) | yes | null |
| max_app_version | string(20) | yes | null |
| released_at | timestamp | yes | null |
| deprecated_at | timestamp | yes | null |
| rolled_back_at | timestamp | yes | null |
| created_by | bigint | yes | null |
| created_at / updated_at | timestamp | yes | null |
| deleted_at | timestamp | yes | null |

- UQ: `product_releases_product_id_version_unique`
- IDX: `product_releases_product_id_status_index`, `product_releases_product_id_channel_index`, `product_releases_status_index`
- FK: `product_releases_product_id_foreign` → `products(id)` (CASCADE)
- FK: `product_releases_created_by_foreign` → `users(id)` (SET NULL)

### `product_assignments`
| Column | Type | Null | Default |
|---|---|---|---|
| id | bigint (PK) | no | auto |
| user_id | bigint | no | — |
| product_id | bigint | no | — |
| role | enum('viewer','support','manager','admin') | no | support |
| is_primary | boolean | no | false |
| assigned_at | timestamp | yes | null |
| created_at / updated_at | timestamp | yes | null |

- UQ: `product_assignments_user_id_product_id_unique`
- IDX: `product_assignments_product_id_index`, `product_assignments_user_id_index`, `product_assignments_role_index`
- FK: `user_id` → `users(id)` (CASCADE)
- FK: `product_id` → `products(id)` (CASCADE)

---

## Licensing

### `licenses`
| Column | Type | Null | Default |
|---|---|---|---|
| id | bigint (PK) | no | auto |
| key | string(64) | no | — |
| product_id | bigint | no | — |
| user_id | bigint | no | — |
| label | string(255) | yes | null |
| status | enum('active','inactive','expired','suspended','revoked') | no | active |
| type | enum('trial','standard','extended','enterprise') | no | standard |
| max_activations | integer | no | 1 |
| activation_count | integer | no | 0 |
| price | decimal(12,2) | no | 0 |
| activated_at | timestamp | yes | null |
| expires_at | timestamp | yes | null |
| last_check_at | timestamp | yes | null |
| notes | text | yes | null |
| metadata | json | yes | null |
| created_at / updated_at | timestamp | yes | null |
| deleted_at | timestamp | yes | null |

- UQ: `licenses_key_unique`
- IDX: `licenses_user_id_index`, `licenses_product_id_index`, `licenses_status_index`, `licenses_expires_at_index`
- FK: `licenses_product_id_foreign` → `products(id)` (CASCADE)
- FK: `licenses_user_id_foreign` → `users(id)` (CASCADE)

### `license_activations`
| Column | Type | Null | Default |
|---|---|---|---|
| id | bigint (PK) | no | auto |
| license_id | bigint | no | — |
| domain | string(255) | **yes** | null |
| identifier | string(255) | yes | null |
| ip_address | string(45) | yes | null |
| hostname | string(255) | yes | null |
| platform | string(255) | yes | null |
| php_version | string(20) | yes | null |
| app_version | string(20) | yes | null |
| fingerprint | string(128) | yes | null |
| last_heartbeat_at | timestamp | yes | null |
| is_active | boolean | no | true |
| created_at / updated_at | timestamp | yes | null |
| deleted_at | timestamp | yes | null |

- IDX: `license_activations_license_id_index`, `license_activations_domain_index`, `license_activations_fingerprint_index`
- FK: `license_activations_license_id_foreign` → `licenses(id)` (CASCADE)

### `license_verifications`
| Column | Type | Null | Default |
|---|---|---|---|
| id | bigint (PK) | no | auto |
| license_id | bigint | no | — |
| activation_id | bigint | yes | null |
| ip_address | string(45) | no | — |
| domain | string(255) | yes | null |
| is_valid | boolean | no | — |
| reason | string(255) | yes | null |
| created_at / updated_at | timestamp | yes | null |

- IDX: `license_verifications_license_id_index`, `license_verifications_created_at_index`
- FK: `license_verifications_license_id_foreign` → `licenses(id)` (CASCADE)
- FK: `license_verifications_activation_id_foreign` → `license_activations(id)` (SET NULL)

---

## Orders & Billing

### `orders`
| Column | Type | Null | Default |
|---|---|---|---|
| id | bigint (PK) | no | auto |
| order_number | string(30) | no | — |
| user_id | bigint | no | — |
| status | enum('pending','processing','completed','failed','cancelled','refunded') | no | pending |
| subtotal | decimal(12,2) | no | — |
| discount | decimal(12,2) | no | 0 |
| tax | decimal(12,2) | no | 0 |
| total | decimal(12,2) | no | — |
| currency | string(3) | no | IRR |
| coupon_code | string(50) | yes | null |
| notes | text | yes | null |
| billing_address | json | yes | null |
| metadata | json | yes | null |
| paid_at | timestamp | yes | null |
| created_at / updated_at | timestamp | yes | null |
| deleted_at | timestamp | yes | null |

- UQ: `orders_order_number_unique`
- IDX: `orders_user_id_index`, `orders_status_index`
- FK: `orders_user_id_foreign` → `users(id)` (CASCADE)

### `order_items`
| Column | Type | Null | Default |
|---|---|---|---|
| id | bigint (PK) | no | auto |
| order_id | bigint | no | — |
| product_id | bigint | no | — |
| quantity | integer | no | 1 |
| unit_price | decimal(12,2) | no | — |
| total_price | decimal(12,2) | no | — |
| options | json | yes | null |
| created_at / updated_at | timestamp | yes | null |

- IDX: `order_items_order_id_index`
- FK: `order_items_order_id_foreign` → `orders(id)` (CASCADE)
- FK: `order_items_product_id_foreign` → `products(id)` (CASCADE)

### `invoices`
| Column | Type | Null | Default |
|---|---|---|---|
| id | bigint (PK) | no | auto |
| invoice_number | string(30) | no | — |
| order_id | bigint | yes | null |
| user_id | bigint | no | — |
| organization_id | bigint | yes | null |
| status | enum('draft','sent','issued','paid','overdue','cancelled') | no | draft |
| subtotal | decimal(12,2) | no | — |
| discount | decimal(12,2) | no | 0 |
| tax | decimal(12,2) | no | 0 |
| total | decimal(12,2) | no | — |
| balance_due | decimal(12,2) | no | 0 |
| currency | string(3) | no | IRR |
| notes | text | yes | null |
| items | json | yes | null |
| billing_details | json | yes | null |
| issued_at | timestamp | yes | null |
| due_at | timestamp | yes | null |
| paid_at | timestamp | yes | null |
| created_at / updated_at | timestamp | yes | null |

- UQ: `invoices_invoice_number_unique`
- IDX: `invoices_user_id_index`, `invoices_status_index`, `invoices_due_at_index`, `invoices_issued_at_index`, `invoices_user_id_status_index`, `invoices_organization_id_index`
- FK: `invoices_order_id_foreign` → `orders(id)` (SET NULL)
- FK: `invoices_user_id_foreign` → `users(id)` (CASCADE)
- FK: `invoices_organization_id_foreign` → `organizations(id)` (SET NULL)

### `invoice_items`
| Column | Type | Null | Default |
|---|---|---|---|
| id | bigint (PK) | no | auto |
| invoice_id | bigint | no | — |
| product_id | bigint | yes | null |
| description | string(255) | no | — |
| quantity | integer | no | 1 |
| unit_price | decimal(12,2) | no | — |
| total_price | decimal(12,2) | no | — |
| tax_rate | decimal(5,2) | no | 0 |
| tax_amount | decimal(12,2) | no | 0 |
| options | json | yes | null |
| created_at / updated_at | timestamp | yes | null |

- IDX: `invoice_items_invoice_id_index`, `invoice_items_product_id_index`
- FK: `invoice_items_invoice_id_foreign` → `invoices(id)` (CASCADE)
- FK: `invoice_items_product_id_foreign` → `products(id)` (SET NULL)

### `payments`
| Column | Type | Null | Default |
|---|---|---|---|
| id | bigint (PK) | no | auto |
| invoice_id | bigint | yes | null |
| order_id | bigint | **yes** | null |
| transaction_id | string(100) | no | — |
| reference_id | string(100) | yes | null |
| gateway | enum('zarinpal','mellat','saman','pay_ir','idpay','nextpay','wallex','manual') | no | zarinpal |
| status | enum('pending','processing','completed','failed','refunded') | no | pending |
| payment_type | enum('invoice','order') | no | invoice |
| amount | decimal(12,2) | no | — |
| refunded_amount | decimal(12,2) | no | 0 |
| currency | string(3) | no | IRR |
| gateway_response | json | yes | null |
| failure_reason | text | yes | null |
| paid_at | timestamp | yes | null |
| refunded_at | timestamp | yes | null |
| created_at / updated_at | timestamp | yes | null |

- UQ: `payments_transaction_id_unique`
- IDX: `payments_order_id_index`, `payments_transaction_id_index`, `payments_status_index`, `payments_invoice_id_index`, `payments_paid_at_index`
- FK: `payments_order_id_foreign` → `orders(id)` (CASCADE)
- FK: `payments_invoice_id_foreign` → `invoices(id)` (SET NULL)

### `coupons`
| Column | Type | Null | Default |
|---|---|---|---|
| id | bigint (PK) | no | auto |
| code | string(50) | no | — |
| type | enum('percentage','fixed') | no | percentage |
| value | decimal(12,2) | no | — |
| min_order_amount | decimal(12,2) | no | 0 |
| max_uses | decimal(10,0) | yes | null |
| used_count | unsignedInteger | no | 0 |
| starts_at | timestamp | yes | null |
| expires_at | timestamp | yes | null |
| is_active | boolean | no | true |
| applicable_products | json | yes | null |
| created_at / updated_at | timestamp | yes | null |

- UQ: `coupons_code_unique`

---

## CRM

### `leads`
| Column | Type | Null | Default |
|---|---|---|---|
| id | bigint (PK) | no | auto |
| name | string(255) | no | — |
| email | string(255) | no | — |
| phone | string(20) | yes | null |
| company | string(255) | yes | null |
| job_title | string(255) | yes | null |
| source | enum('website','referral','social_media','advertisement','cold_call','import','other') | no | website |
| status | enum('new','contacted','qualified','proposal','negotiation','won','lost','dormant') | no | new |
| priority | enum('low','medium','high','urgent') | no | medium |
| estimated_value | decimal(12,2) | no | 0 |
| assigned_to | bigint | yes | null |
| product_id | bigint | yes | null |
| notes | text | yes | null |
| metadata | json | yes | null |
| contacted_at | timestamp | yes | null |
| qualified_at | timestamp | yes | null |
| converted_at | timestamp | yes | null |
| converted_user_id | bigint | yes | null |
| created_at / updated_at | timestamp | yes | null |
| deleted_at | timestamp | yes | null |

- IDX: `leads_status_index`, `leads_source_index`, `leads_assigned_to_index`, `leads_priority_index`
- FK: `leads_assigned_to_foreign` → `users(id)` (SET NULL)
- FK: `leads_product_id_foreign` → `products(id)` (SET NULL)
- FK: `leads_converted_user_id_foreign` → `users(id)` (SET NULL)

### `lead_activities`
| Column | Type | Null | Default |
|---|---|---|---|
| id | bigint (PK) | no | auto |
| lead_id | bigint | no | — |
| user_id | bigint | yes | null |
| type | enum('note','call','email','meeting','task','status_change','system') | no | note |
| subject | string(255) | yes | null |
| description | text | yes | null |
| old_values | json | yes | null |
| new_values | json | yes | null |
| scheduled_at | timestamp | yes | null |
| is_completed | boolean | no | false |
| created_at / updated_at | timestamp | yes | null |

- IDX: `lead_activities_lead_id_index`, `lead_activities_type_index`, `lead_activities_scheduled_at_index`
- FK: `lead_activities_lead_id_foreign` → `leads(id)` (CASCADE)
- FK: `lead_activities_user_id_foreign` → `users(id)` (SET NULL)

### `lead_stages`
| Column | Type | Null | Default |
|---|---|---|---|
| id | bigint (PK) | no | auto |
| name | string(255) | no | — |
| key | string(255) | no | — |
| color | string(7) | yes | null |
| sort_order | integer | no | 0 |
| is_active | boolean | no | true |
| created_at / updated_at | timestamp | yes | null |

- UQ: `lead_stages_key_unique`
- IDX: `lead_stages_sort_order_index`

### `lead_notes`
| Column | Type | Null | Default |
|---|---|---|---|
| id | bigint (PK) | no | auto |
| lead_id | bigint | no | — |
| user_id | bigint | yes | null |
| body | text | no | — |
| created_at / updated_at | timestamp | yes | null |

- IDX: `lead_notes_lead_id_index`
- FK: `lead_notes_lead_id_foreign` → `leads(id)` (CASCADE)
- FK: `lead_notes_user_id_foreign` → `users(id)` (SET NULL)

### `tags`
| Column | Type | Null | Default |
|---|---|---|---|
| id | bigint (PK) | no | auto |
| name | string(255) | no | — |
| slug | string(255) | no | — |
| color | string(7) | yes | null |
| created_at / updated_at | timestamp | yes | null |

- UQ: `tags_name_unique`, `tags_slug_unique`

### `taggables`
| Column | Type | Null | Default |
|---|---|---|---|
| tag_id | bigint | no | — |
| taggable_type | string(255) | no | — |
| taggable_id | bigint | no | — |

- PK: composite `(tag_id, taggable_id, taggable_type)`
- FK: `tag_id` → `tags(id)` (CASCADE)

### `contacts`
| Column | Type | Null | Default |
|---|---|---|---|
| id | bigint (PK) | no | auto |
| user_id | bigint | yes | null |
| lead_id | bigint | yes | null |
| name | string(255) | no | — |
| email | string(255) | no | — |
| phone | string(20) | yes | null |
| company | string(255) | yes | null |
| job_title | string(255) | yes | null |
| address | text | yes | null |
| type | enum('individual','company') | no | individual |
| custom_fields | json | yes | null |
| created_at / updated_at | timestamp | yes | null |
| deleted_at | timestamp | yes | null |

- IDX: `contacts_user_id_index`, `contacts_email_index`
- FK: `contacts_user_id_foreign` → `users(id)` (SET NULL)
- FK: `contacts_lead_id_foreign` → `leads(id)` (SET NULL)

---

## Tickets

### `tickets`
| Column | Type | Null | Default |
|---|---|---|---|
| id | bigint (PK) | no | auto |
| ticket_number | string(20) | no | — |
| user_id | bigint | no | — |
| assigned_to | bigint | yes | null |
| product_id | bigint | yes | null |
| license_id | bigint | yes | null |
| subject | string(255) | no | — |
| status | enum('open','in_progress','waiting_reply','resolved','closed') | no | open |
| priority | enum('low','medium','high','urgent') | no | medium |
| category | enum('general','technical','billing','bug_report','feature_request','other') | no | general |
| department | string(255) | yes | null |
| is_internal | boolean | no | false |
| created_at / updated_at | timestamp | yes | null |
| deleted_at | timestamp | yes | null |

- UQ: `tickets_ticket_number_unique`
- IDX: `tickets_user_id_index`, `tickets_assigned_to_index`, `tickets_status_index`, `tickets_priority_index`, `tickets_category_index`
- FK: `tickets_user_id_foreign` → `users(id)` (CASCADE)
- FK: `tickets_assigned_to_foreign` → `users(id)` (SET NULL)
- FK: `tickets_product_id_foreign` → `products(id)` (SET NULL)
- FK: `tickets_license_id_foreign` → `licenses(id)` (SET NULL)

### `ticket_messages`
| Column | Type | Null | Default |
|---|---|---|---|
| id | bigint (PK) | no | auto |
| ticket_id | bigint | no | — |
| user_id | bigint | yes | null |
| body | text | no | — |
| is_staff_reply | boolean | no | false |
| is_internal_note | boolean | no | false |
| created_at / updated_at | timestamp | yes | null |

- IDX: `ticket_messages_ticket_id_index`
- FK: `ticket_messages_ticket_id_foreign` → `tickets(id)` (CASCADE)
- FK: `ticket_messages_user_id_foreign` → `users(id)` (SET NULL)

### `ticket_attachments`
| Column | Type | Null | Default |
|---|---|---|---|
| id | bigint (PK) | no | auto |
| ticket_message_id | bigint | no | — |
| filename | string(255) | no | — |
| original_filename | string(255) | no | — |
| mime_type | string(255) | no | — |
| file_size | bigint | no | — |
| disk | string(255) | no | public |
| path | string(255) | no | — |
| created_at / updated_at | timestamp | yes | null |

- IDX: `ticket_attachments_ticket_message_id_index`
- FK: `ticket_attachments_ticket_message_id_foreign` → `ticket_messages(id)` (CASCADE)

### `ticket_custom_fields`
| Column | Type | Null | Default |
|---|---|---|---|
| id | bigint (PK) | no | auto |
| ticket_id | bigint | no | — |
| key | string(255) | no | — |
| value | text | yes | null |
| created_at / updated_at | timestamp | yes | null |

- IDX: `ticket_custom_fields_ticket_id_index`
- FK: `ticket_custom_fields_ticket_id_foreign` → `tickets(id)` (CASCADE)

---

## Downloads

### `downloads`
| Column | Type | Null | Default |
|---|---|---|---|
| id | bigint (PK) | no | auto |
| product_release_id | bigint | yes | null |
| product_id | bigint | no | — |
| version | string(20) | no | — |
| filename | string(255) | no | — |
| original_filename | string(255) | no | — |
| mime_type | string(255) | no | — |
| file_size | bigint | no | — |
| file_hash | string(64) | no | — |
| download_url | string(255) | no | — |
| changelog | text | yes | null |
| platform | enum('windows','macos','linux','web','source','universal') | no | universal |
| status | enum('available','deprecated','removed') | no | available |
| is_active | boolean | no | true |
| is_stable | boolean | no | true |
| requirements | json | yes | null |
| download_count | unsignedInteger | no | 0 |
| created_at / updated_at | timestamp | yes | null |

- IDX: `downloads_product_id_index`, `downloads_version_index`, `downloads_is_active_index`
- FK: `downloads_product_release_id_foreign` → `product_releases(id)` (SET NULL)
- FK: `downloads_product_id_foreign` → `products(id)` (CASCADE)

### `download_logs`
| Column | Type | Null | Default |
|---|---|---|---|
| id | bigint (PK) | no | auto |
| download_id | bigint | no | — |
| user_id | bigint | yes | null |
| license_id | bigint | yes | null |
| ip_address | string(45) | no | — |
| user_agent | string(255) | yes | null |
| created_at / updated_at | timestamp | yes | null |

- IDX: `download_logs_download_id_index`, `download_logs_user_id_index`
- FK: `download_logs_download_id_foreign` → `downloads(id)` (CASCADE)
- FK: `download_logs_user_id_foreign` → `users(id)` (SET NULL)
- FK: `download_logs_license_id_foreign` → `licenses(id)` (SET NULL)

---

## CMS / Settings

### `settings`
| Column | Type | Null | Default |
|---|---|---|---|
| id | bigint (PK) | no | auto |
| product_id | bigint | yes | null |
| group | string(50) | no | general |
| key | string(100) | no | — |
| value | text | yes | null |
| type | string(20) | no | text |
| created_at / updated_at | timestamp | yes | null |

- UQ: `settings_key_product_id_unique` (composite, replaces legacy `settings_key_unique`)
- FK: `settings_product_id_foreign` → `products(id)` (SET NULL)

### `pages`
| Column | Type | Null | Default |
|---|---|---|---|
| id | bigint (PK) | no | auto |
| title | string(255) | no | — |
| slug | string(255) | no | — |
| body | text | yes | null |
| meta_title | string(255) | yes | null |
| meta_description | text | yes | null |
| status | enum('draft','published') | no | draft |
| created_at / updated_at | timestamp | yes | null |
| deleted_at | timestamp | yes | null |

- UQ: `pages_slug_unique`

### `faqs`
| Column | Type | Null | Default |
|---|---|---|---|
| id | bigint (PK) | no | auto |
| question | string(255) | no | — |
| answer | text | no | — |
| product_id | bigint | yes | null |
| sort_order | unsignedInteger | no | 0 |
| is_published | boolean | no | true |
| created_at / updated_at | timestamp | yes | null |

- FK: `faqs_product_id_foreign` → `products(id)` (SET NULL)

---

## Spatie Permission Tables (baseline, unused by feature code)

### `permissions`
| Column | Type | Null | Default |
|---|---|---|---|
| id | bigint (PK) | no | auto |
| name | string(255) | no | — |
| guard_name | string(255) | no | — |
| created_at / updated_at | timestamp | yes | null |

- UQ: `permissions_name_guard_name_unique`

### `roles`
| Column | Type | Null | Default |
|---|---|---|---|
| id | bigint (PK) | no | auto |
| name | string(255) | no | — |
| guard_name | string(255) | no | — |
| created_at / updated_at | timestamp | yes | null |

- UQ: `roles_name_guard_name_unique`

### `model_has_permissions`
| Column | Type | Null | Default |
|---|---|---|---|
| permission_id | bigint | no | — |
| model_type | string(255) | no | — |
| model_id | bigint | no | — |

- PK: composite `(permission_id, model_id, model_type)`
- IDX: `model_has_permissions_model_id_model_type_index`
- FK: `permission_id` → `permissions(id)` (CASCADE)

### `model_has_roles`
| Column | Type | Null | Default |
|---|---|---|---|
| role_id | bigint | no | — |
| model_type | string(255) | no | — |
| model_id | bigint | no | — |

- PK: composite `(role_id, model_id, model_type)`
- IDX: `model_has_roles_model_id_model_type_index`
- FK: `role_id` → `roles(id)` (CASCADE)

### `role_has_permissions`
| Column | Type | Null | Default |
|---|---|---|---|
| permission_id | bigint | no | — |
| role_id | bigint | no | — |

- PK: composite `(permission_id, role_id)`
- FK: `permission_id` → `permissions(id)` (CASCADE)
- FK: `role_id` → `roles(id)` (CASCADE)

---

## Cross-Table Notes

1. **`settings.key` uniqueness changed** to `(key, product_id)` composite (per-product settings); the migration validates duplicates before dropping the old constraint.
2. **`payments.order_id` is nullable** — a payment may belong to an invoice, an order, or (in theory) neither; `payment_type` distinguishes `invoice` vs `order`.
3. **`invoices.status` enum** is the only enum that was *expanded* (added `sent`); all other enum changes are additive-only, so MySQL enum swaps are non-destructive.
4. **`license_activations.domain` is nullable** — machine/hybrid activation strategies use `identifier` instead of a domain.
5. **Spatie permission tables** exist (baseline) but feature code uses the simple `users.role` enum + middleware; they are not wired into the app's authorization.
6. **Soft deletes** present on: users, products, product_categories, product_releases, licenses, license_activations, orders, leads, contacts, tickets, pages.
7. **JSON columns** (MySQL native / SQLite text): products.features/requirements, orders.billing_address/metadata, invoices.items/billing_details, payments.gateway_response, lead metadata/old_values/new_values, organization_user.permissions, organization_settings.settings, downloads.requirements, coupons.applicable_products, media.custom_properties.
