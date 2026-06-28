# Cấu trúc database

- Database: `furniture_shop`
- Kết nối: `mysql`
- Tổng số bảng: 39

## Danh sách bảng

| Bảng | Số dòng | Khóa chính/cột chính | Khóa ngoại |
|---|---:|---|---|
| `address` | 81 | address_id | user_id → users.user_id |
| `ai_combo_settings` | 1 | id | updated_by → users.user_id |
| `brands` | 6 | brand_id | Không có |
| `catalog_quality_issues` | 9 | id | product_id → products.product_id, run_id → catalog_quality_runs.id, sku → product_details.sku |
| `catalog_quality_runs` | 1 | id | started_by → users.user_id |
| `categories` | 9 | category_id | parent_id → categories.category_id |
| `colors` | 16 | color_id | Không có |
| `discounts` | 1 | discount_id | Không có |
| `migrations` | 33 | id | Không có |
| `model_has_permissions` | 0 | permission_id, model_type, model_id | permission_id → permissions.id |
| `model_has_roles` | 2 | role_id, model_type, model_id | role_id → roles.id |
| `order_details` | 634 | order_id, sku | order_id → orders.order_id, sku → product_details.sku |
| `orders` | 262 | order_id | created_by → users.user_id, customer_id → users.user_id |
| `payments` | 0 | id | order_id → orders.order_id |
| `permissions` | 40 | id | Không có |
| `product_details` | 216 | sku | color_id → colors.color_id, product_id → products.product_id |
| `product_discounts` | 12 | sku, discount_id | discount_id → discounts.discount_id, sku → product_details.sku |
| `product_images` | 216 | product_image_id | sku → product_details.sku |
| `product_tags` | 227 | product_id, tag_id | product_id → products.product_id, tag_id → tags.tag_id |
| `products` | 72 | product_id | brand_id → brands.brand_id, category_id → categories.category_id |
| `receiving_report_details` | 88 | receiving_report_id, sku | receiving_report_id → receiving_reports.receiving_report_id, sku → product_details.sku |
| `receiving_reports` | 25 | receiving_report_id | created_by → users.user_id, supplier_id → suppliers.supplier_id |
| `review_images` | 0 | id | review_id → reviews.id |
| `reviews` | 0 | id | approved_by → users.user_id, order_id → orders.order_id, product_id → products.product_id, sku → product_details.sku, user_id → users.user_id |
| `role_has_permissions` | 40 | permission_id, role_id | permission_id → permissions.id, role_id → roles.id |
| `roles` | 1 | id | Không có |
| `room_collections` | 4 | id | Không có |
| `sessions` | 4 | id | Không có |
| `site_visits` | 6800 | site_visit_id | converted_order_id → orders.order_id, user_id → users.user_id |
| `suppliers` | 4 | supplier_id | Không có |
| `tags` | 13 | tag_id | Không có |
| `user_verifies` | 1 | user_id | user_id → users.user_id |
| `users` | 71 | user_id | Không có |
| `visual_feedback_activities` | 12 | id | feedback_id → visual_feedbacks.id, user_id → users.user_id |
| `visual_feedback_attachments` | 0 | id | feedback_id → visual_feedbacks.id, user_id → users.user_id |
| `visual_feedback_comments` | 0 | id | feedback_id → visual_feedbacks.id, user_id → users.user_id |
| `visual_feedbacks` | 8 | id | assigned_to → users.user_id, parent_id → visual_feedbacks.id, user_id → users.user_id |
| `warranties` | 0 | warranty_id | order_id → orders.order_id, sku → product_details.sku |
| `wishlist_items` | 3 | id | product_id → products.product_id, user_id → users.user_id |

## Chi tiết cột theo bảng

### `address`

| Cột | Kiểu | Null | Key | Default | Extra |
|---|---|---|---|---|---|
| `address_id` | `int unsigned` | NO | PRI |  | auto_increment |
| `user_id` | `int unsigned` | NO | MUL |  |  |
| `receiver_name` | `varchar(255)` | NO |  |  |  |
| `address` | `varchar(255)` | NO |  |  |  |
| `phone_number` | `varchar(255)` | NO |  |  |  |
| `is_default` | `tinyint(1)` | NO |  |  |  |
| `created_at` | `timestamp` | YES |  |  |  |
| `updated_at` | `timestamp` | YES |  |  |  |

### `ai_combo_settings`

| Cột | Kiểu | Null | Key | Default | Extra |
|---|---|---|---|---|---|
| `id` | `bigint unsigned` | NO | PRI |  | auto_increment |
| `enabled` | `tinyint(1)` | NO | MUL | 1 |  |
| `model` | `varchar(120)` | NO |  | furemir-rule-assisted |  |
| `max_products` | `tinyint unsigned` | NO |  | 5 |  |
| `candidate_limit` | `tinyint unsigned` | NO |  | 24 |  |
| `budget_overage_percent` | `decimal(5,2)` | NO |  | 10.00 |  |
| `room_category_mapping` | `json` | YES |  |  |  |
| `updated_by` | `int unsigned` | YES | MUL |  |  |
| `created_at` | `timestamp` | YES |  |  |  |
| `updated_at` | `timestamp` | YES |  |  |  |

### `brands`

| Cột | Kiểu | Null | Key | Default | Extra |
|---|---|---|---|---|---|
| `brand_id` | `int unsigned` | NO | PRI |  | auto_increment |
| `name` | `varchar(255)` | NO |  |  |  |
| `description` | `text` | NO |  |  |  |
| `index` | `int` | NO |  | 0 |  |
| `created_at` | `timestamp` | YES |  |  |  |
| `updated_at` | `timestamp` | YES |  |  |  |

### `catalog_quality_issues`

| Cột | Kiểu | Null | Key | Default | Extra |
|---|---|---|---|---|---|
| `id` | `bigint unsigned` | NO | PRI |  | auto_increment |
| `run_id` | `bigint unsigned` | NO | MUL |  |  |
| `product_id` | `int unsigned` | YES | MUL |  |  |
| `sku` | `varchar(255)` | YES | MUL |  |  |
| `issue_code` | `varchar(80)` | NO | MUL |  |  |
| `severity` | `varchar(20)` | NO | MUL |  |  |
| `source` | `varchar(20)` | NO | MUL |  |  |
| `field_name` | `varchar(255)` | YES |  |  |  |
| `current_value` | `text` | YES |  |  |  |
| `suggestion` | `text` | YES |  |  |  |
| `status` | `varchar(20)` | NO | MUL | open |  |
| `metadata` | `json` | YES |  |  |  |
| `created_at` | `timestamp` | YES | MUL |  |  |
| `updated_at` | `timestamp` | YES |  |  |  |

### `catalog_quality_runs`

| Cột | Kiểu | Null | Key | Default | Extra |
|---|---|---|---|---|---|
| `id` | `bigint unsigned` | NO | PRI |  | auto_increment |
| `started_by` | `int unsigned` | YES | MUL |  |  |
| `status` | `varchar(20)` | NO | MUL | running |  |
| `scan_type` | `varchar(20)` | NO | MUL | rule |  |
| `total_products` | `int unsigned` | NO |  | 0 |  |
| `total_issues` | `int unsigned` | NO |  | 0 |  |
| `started_at` | `timestamp` | YES | MUL |  |  |
| `completed_at` | `timestamp` | YES |  |  |  |
| `error_message` | `text` | YES |  |  |  |
| `created_at` | `timestamp` | YES |  |  |  |
| `updated_at` | `timestamp` | YES |  |  |  |

### `categories`

| Cột | Kiểu | Null | Key | Default | Extra |
|---|---|---|---|---|---|
| `category_id` | `int unsigned` | NO | PRI |  | auto_increment |
| `name` | `varchar(255)` | NO |  |  |  |
| `slug` | `varchar(255)` | YES | UNI |  |  |
| `description` | `text` | NO |  |  |  |
| `meta_title` | `varchar(255)` | YES |  |  |  |
| `meta_description` | `varchar(320)` | YES |  |  |  |
| `index` | `int` | NO |  | 0 |  |
| `parent_id` | `int unsigned` | YES | MUL |  |  |
| `created_at` | `timestamp` | YES |  |  |  |
| `updated_at` | `timestamp` | YES |  |  |  |

### `colors`

| Cột | Kiểu | Null | Key | Default | Extra |
|---|---|---|---|---|---|
| `color_id` | `int unsigned` | NO | PRI |  | auto_increment |
| `name` | `varchar(255)` | NO |  |  |  |
| `code` | `varchar(255)` | NO |  |  |  |
| `created_at` | `timestamp` | YES |  |  |  |
| `updated_at` | `timestamp` | YES |  |  |  |

### `discounts`

| Cột | Kiểu | Null | Key | Default | Extra |
|---|---|---|---|---|---|
| `discount_id` | `int unsigned` | NO | PRI |  | auto_increment |
| `title` | `varchar(255)` | NO |  |  |  |
| `description` | `text` | NO |  |  |  |
| `percentage` | `decimal(10,2)` | NO |  |  |  |
| `start_date` | `date` | NO |  | 2026-06-09 |  |
| `end_date` | `date` | NO |  |  |  |
| `is_active` | `tinyint(1)` | NO |  | 1 |  |
| `created_at` | `timestamp` | YES |  |  |  |
| `updated_at` | `timestamp` | YES |  |  |  |

### `migrations`

| Cột | Kiểu | Null | Key | Default | Extra |
|---|---|---|---|---|---|
| `id` | `int unsigned` | NO | PRI |  | auto_increment |
| `migration` | `varchar(255)` | NO |  |  |  |
| `batch` | `int` | NO |  |  |  |

### `model_has_permissions`

| Cột | Kiểu | Null | Key | Default | Extra |
|---|---|---|---|---|---|
| `permission_id` | `bigint unsigned` | NO | PRI |  |  |
| `model_type` | `varchar(255)` | NO | PRI |  |  |
| `model_id` | `bigint unsigned` | NO | PRI |  |  |

### `model_has_roles`

| Cột | Kiểu | Null | Key | Default | Extra |
|---|---|---|---|---|---|
| `role_id` | `bigint unsigned` | NO | PRI |  |  |
| `model_type` | `varchar(255)` | NO | PRI |  |  |
| `model_id` | `bigint unsigned` | NO | PRI |  |  |

### `order_details`

| Cột | Kiểu | Null | Key | Default | Extra |
|---|---|---|---|---|---|
| `order_id` | `int unsigned` | NO | PRI |  |  |
| `sku` | `varchar(255)` | NO | PRI |  |  |
| `quantities` | `int unsigned` | NO |  |  |  |
| `unit_price` | `decimal(14,2)` | NO |  |  |  |
| `created_at` | `timestamp` | YES |  |  |  |
| `updated_at` | `timestamp` | YES |  |  |  |

### `orders`

| Cột | Kiểu | Null | Key | Default | Extra |
|---|---|---|---|---|---|
| `order_id` | `int unsigned` | NO | PRI |  | auto_increment |
| `total_price` | `decimal(14,2)` | NO |  |  |  |
| `is_paid` | `tinyint(1)` | NO |  |  |  |
| `payment_method` | `varchar(40)` | NO | MUL | cod |  |
| `payment_status` | `varchar(40)` | NO | MUL | unpaid |  |
| `paid_at` | `timestamp` | YES | MUL |  |  |
| `status` | `int unsigned` | NO |  |  |  |
| `receiver_name` | `varchar(255)` | NO |  |  |  |
| `address` | `varchar(255)` | NO |  |  |  |
| `phone_number` | `varchar(255)` | NO |  |  |  |
| `customer_id` | `int unsigned` | YES | MUL |  |  |
| `created_by` | `int unsigned` | YES | MUL |  |  |
| `created_at` | `timestamp` | YES |  |  |  |
| `updated_at` | `timestamp` | YES |  |  |  |
| `note` | `text` | YES |  |  |  |

### `payments`

| Cột | Kiểu | Null | Key | Default | Extra |
|---|---|---|---|---|---|
| `id` | `bigint unsigned` | NO | PRI |  | auto_increment |
| `order_id` | `int unsigned` | NO | MUL |  |  |
| `provider` | `varchar(40)` | NO | MUL |  |  |
| `transaction_reference` | `varchar(120)` | NO | UNI |  |  |
| `provider_transaction_id` | `varchar(120)` | YES | MUL |  |  |
| `amount` | `decimal(14,2)` | NO |  |  |  |
| `status` | `varchar(40)` | NO | MUL | pending |  |
| `response_code` | `varchar(20)` | YES | MUL |  |  |
| `transaction_status` | `varchar(20)` | YES | MUL |  |  |
| `bank_code` | `varchar(60)` | YES |  |  |  |
| `card_type` | `varchar(60)` | YES |  |  |  |
| `order_info` | `varchar(255)` | YES |  |  |  |
| `callback_payload` | `json` | YES |  |  |  |
| `paid_at` | `timestamp` | YES | MUL |  |  |
| `created_at` | `timestamp` | YES | MUL |  |  |
| `updated_at` | `timestamp` | YES |  |  |  |

### `permissions`

| Cột | Kiểu | Null | Key | Default | Extra |
|---|---|---|---|---|---|
| `id` | `bigint unsigned` | NO | PRI |  | auto_increment |
| `name` | `varchar(255)` | NO | MUL |  |  |
| `guard_name` | `varchar(255)` | NO |  |  |  |
| `created_at` | `timestamp` | YES |  |  |  |
| `updated_at` | `timestamp` | YES |  |  |  |

### `product_details`

| Cột | Kiểu | Null | Key | Default | Extra |
|---|---|---|---|---|---|
| `sku` | `varchar(255)` | NO | PRI |  |  |
| `product_id` | `int unsigned` | NO | MUL |  |  |
| `name` | `varchar(255)` | NO |  |  |  |
| `description` | `text` | NO |  |  |  |
| `size` | `varchar(255)` | NO |  |  |  |
| `original_price` | `decimal(10,2)` | NO |  |  |  |
| `warranty_month` | `int unsigned` | NO |  |  |  |
| `quantities` | `int unsigned` | NO |  |  |  |
| `is_deleted` | `tinyint(1)` | NO |  | 0 |  |
| `created_at` | `timestamp` | YES |  |  |  |
| `updated_at` | `timestamp` | YES |  |  |  |
| `color_id` | `int unsigned` | NO | MUL |  |  |

### `product_discounts`

| Cột | Kiểu | Null | Key | Default | Extra |
|---|---|---|---|---|---|
| `sku` | `varchar(255)` | NO | PRI |  |  |
| `discount_id` | `int unsigned` | NO | PRI |  |  |
| `created_at` | `timestamp` | YES |  |  |  |
| `updated_at` | `timestamp` | YES |  |  |  |

### `product_images`

| Cột | Kiểu | Null | Key | Default | Extra |
|---|---|---|---|---|---|
| `product_image_id` | `int unsigned` | NO | PRI |  | auto_increment |
| `sku` | `varchar(255)` | NO | MUL |  |  |
| `url` | `varchar(255)` | NO |  |  |  |
| `alt_text` | `varchar(255)` | YES |  |  |  |
| `created_at` | `timestamp` | YES |  |  |  |
| `updated_at` | `timestamp` | YES |  |  |  |

### `product_tags`

| Cột | Kiểu | Null | Key | Default | Extra |
|---|---|---|---|---|---|
| `product_id` | `int unsigned` | NO | PRI |  |  |
| `tag_id` | `int unsigned` | NO | PRI |  |  |
| `created_at` | `timestamp` | YES |  |  |  |
| `updated_at` | `timestamp` | YES |  |  |  |

### `products`

| Cột | Kiểu | Null | Key | Default | Extra |
|---|---|---|---|---|---|
| `product_id` | `int unsigned` | NO | PRI |  | auto_increment |
| `name` | `varchar(255)` | NO |  |  |  |
| `slug` | `varchar(255)` | YES | UNI |  |  |
| `description` | `text` | NO |  |  |  |
| `short_description` | `varchar(255)` | YES |  |  |  |
| `material` | `varchar(255)` | YES |  |  |  |
| `rating` | `decimal(2,1)` | NO |  | 4.6 |  |
| `review_count` | `int unsigned` | NO |  | 0 |  |
| `meta_title` | `varchar(255)` | YES |  |  |  |
| `meta_description` | `varchar(320)` | YES |  |  |  |
| `og_image` | `varchar(255)` | YES |  |  |  |
| `category_id` | `int unsigned` | NO | MUL |  |  |
| `brand_id` | `int unsigned` | NO | MUL |  |  |
| `is_deleted` | `tinyint(1)` | NO |  | 0 |  |
| `created_at` | `timestamp` | YES |  |  |  |
| `updated_at` | `timestamp` | YES |  |  |  |
| `amount_sold` | `int unsigned` | NO |  |  |  |

### `receiving_report_details`

| Cột | Kiểu | Null | Key | Default | Extra |
|---|---|---|---|---|---|
| `receiving_report_id` | `int unsigned` | NO | PRI |  |  |
| `sku` | `varchar(255)` | NO | PRI |  |  |
| `quantities` | `int unsigned` | NO |  |  |  |
| `unit_price` | `decimal(14,2)` | NO |  |  |  |
| `created_at` | `timestamp` | YES |  |  |  |
| `updated_at` | `timestamp` | YES |  |  |  |

### `receiving_reports`

| Cột | Kiểu | Null | Key | Default | Extra |
|---|---|---|---|---|---|
| `receiving_report_id` | `int unsigned` | NO | PRI |  | auto_increment |
| `total_price` | `decimal(14,2)` | NO |  |  |  |
| `supplier_id` | `int unsigned` | NO | MUL |  |  |
| `created_by` | `int unsigned` | NO | MUL |  |  |
| `created_at` | `timestamp` | YES |  |  |  |
| `updated_at` | `timestamp` | YES |  |  |  |

### `review_images`

| Cột | Kiểu | Null | Key | Default | Extra |
|---|---|---|---|---|---|
| `id` | `bigint unsigned` | NO | PRI |  | auto_increment |
| `review_id` | `bigint unsigned` | NO | MUL |  |  |
| `image_path` | `varchar(255)` | NO |  |  |  |
| `sort_order` | `int unsigned` | NO |  | 0 |  |
| `created_at` | `timestamp` | YES |  |  |  |
| `updated_at` | `timestamp` | YES |  |  |  |

### `reviews`

| Cột | Kiểu | Null | Key | Default | Extra |
|---|---|---|---|---|---|
| `id` | `bigint unsigned` | NO | PRI |  | auto_increment |
| `user_id` | `int unsigned` | NO | MUL |  |  |
| `product_id` | `int unsigned` | NO | MUL |  |  |
| `order_id` | `int unsigned` | NO | MUL |  |  |
| `sku` | `varchar(255)` | NO | MUL |  |  |
| `order_detail_id` | `varchar(255)` | NO | UNI |  |  |
| `rating` | `tinyint unsigned` | NO |  |  |  |
| `content` | `text` | NO |  |  |  |
| `status` | `varchar(20)` | NO | MUL | pending |  |
| `admin_note` | `text` | YES |  |  |  |
| `approved_by` | `int unsigned` | YES | MUL |  |  |
| `approved_at` | `timestamp` | YES |  |  |  |
| `created_at` | `timestamp` | YES | MUL |  |  |
| `updated_at` | `timestamp` | YES |  |  |  |

### `role_has_permissions`

| Cột | Kiểu | Null | Key | Default | Extra |
|---|---|---|---|---|---|
| `permission_id` | `bigint unsigned` | NO | PRI |  |  |
| `role_id` | `bigint unsigned` | NO | PRI |  |  |

### `roles`

| Cột | Kiểu | Null | Key | Default | Extra |
|---|---|---|---|---|---|
| `id` | `bigint unsigned` | NO | PRI |  | auto_increment |
| `name` | `varchar(255)` | NO | MUL |  |  |
| `guard_name` | `varchar(255)` | NO |  |  |  |
| `created_at` | `timestamp` | YES |  |  |  |
| `updated_at` | `timestamp` | YES |  |  |  |

### `room_collections`

| Cột | Kiểu | Null | Key | Default | Extra |
|---|---|---|---|---|---|
| `id` | `bigint unsigned` | NO | PRI |  | auto_increment |
| `room_key` | `varchar(255)` | NO | UNI |  |  |
| `slug` | `varchar(255)` | NO | UNI |  |  |
| `title` | `varchar(255)` | NO |  |  |  |
| `description` | `text` | YES |  |  |  |
| `hero_image` | `varchar(255)` | YES |  |  |  |
| `category_keywords` | `json` | YES |  |  |  |
| `featured_category_ids` | `json` | YES |  |  |  |
| `meta_title` | `varchar(255)` | YES |  |  |  |
| `meta_description` | `varchar(320)` | YES |  |  |  |
| `is_active` | `tinyint(1)` | NO |  | 1 |  |
| `created_at` | `timestamp` | YES |  |  |  |
| `updated_at` | `timestamp` | YES |  |  |  |

### `sessions`

| Cột | Kiểu | Null | Key | Default | Extra |
|---|---|---|---|---|---|
| `id` | `varchar(255)` | NO | PRI |  |  |
| `user_id` | `bigint unsigned` | YES | MUL |  |  |
| `ip_address` | `varchar(45)` | YES |  |  |  |
| `user_agent` | `text` | YES |  |  |  |
| `payload` | `longtext` | NO |  |  |  |
| `last_activity` | `int` | NO | MUL |  |  |

### `site_visits`

| Cột | Kiểu | Null | Key | Default | Extra |
|---|---|---|---|---|---|
| `site_visit_id` | `bigint unsigned` | NO | PRI |  | auto_increment |
| `session_id` | `varchar(255)` | NO | MUL |  |  |
| `user_id` | `int unsigned` | YES | MUL |  |  |
| `converted_order_id` | `int unsigned` | YES | MUL |  |  |
| `url` | `varchar(255)` | NO |  |  |  |
| `referrer` | `varchar(255)` | YES |  |  |  |
| `source` | `varchar(255)` | YES | MUL |  |  |
| `device` | `varchar(40)` | YES | MUL |  |  |
| `browser` | `varchar(60)` | YES |  |  |  |
| `ip_address` | `varchar(45)` | YES |  |  |  |
| `metadata` | `json` | YES |  |  |  |
| `created_at` | `timestamp` | YES |  |  |  |
| `updated_at` | `timestamp` | YES |  |  |  |

### `suppliers`

| Cột | Kiểu | Null | Key | Default | Extra |
|---|---|---|---|---|---|
| `supplier_id` | `int unsigned` | NO | PRI |  | auto_increment |
| `name` | `varchar(255)` | NO |  |  |  |
| `description` | `text` | NO |  |  |  |
| `address` | `varchar(255)` | NO |  |  |  |
| `phone_number` | `varchar(255)` | NO |  |  |  |
| `created_at` | `timestamp` | YES |  |  |  |
| `updated_at` | `timestamp` | YES |  |  |  |

### `tags`

| Cột | Kiểu | Null | Key | Default | Extra |
|---|---|---|---|---|---|
| `tag_id` | `int unsigned` | NO | PRI |  | auto_increment |
| `name` | `varchar(255)` | NO |  |  |  |
| `created_at` | `timestamp` | YES |  |  |  |
| `updated_at` | `timestamp` | YES |  |  |  |

### `user_verifies`

| Cột | Kiểu | Null | Key | Default | Extra |
|---|---|---|---|---|---|
| `user_id` | `int unsigned` | NO | PRI |  |  |
| `otp` | `varchar(255)` | NO |  |  |  |
| `expired_time` | `timestamp` | NO |  |  |  |
| `created_at` | `timestamp` | YES |  |  |  |
| `updated_at` | `timestamp` | YES |  |  |  |

### `users`

| Cột | Kiểu | Null | Key | Default | Extra |
|---|---|---|---|---|---|
| `user_id` | `int unsigned` | NO | PRI |  | auto_increment |
| `email` | `varchar(255)` | NO |  |  |  |
| `password` | `varchar(255)` | NO |  |  |  |
| `first_name` | `varchar(255)` | NO |  |  |  |
| `last_name` | `varchar(255)` | NO |  |  |  |
| `avatar` | `varchar(255)` | NO |  | http://localhost:8000//images/default_avatar.jpg |  |
| `birth_date` | `date` | YES |  |  |  |
| `gender` | `tinyint(1)` | YES |  |  |  |
| `is_staff` | `tinyint(1)` | NO |  | 0 |  |
| `is_verified` | `tinyint(1)` | NO |  | 0 |  |
| `is_active` | `tinyint(1)` | NO |  | 1 |  |
| `created_at` | `timestamp` | YES |  |  |  |
| `updated_at` | `timestamp` | YES |  |  |  |

### `visual_feedback_activities`

| Cột | Kiểu | Null | Key | Default | Extra |
|---|---|---|---|---|---|
| `id` | `bigint unsigned` | NO | PRI |  | auto_increment |
| `feedback_id` | `bigint unsigned` | NO | MUL |  |  |
| `user_id` | `int unsigned` | YES | MUL |  |  |
| `action` | `varchar(120)` | NO |  |  |  |
| `old_value` | `json` | YES |  |  |  |
| `new_value` | `json` | YES |  |  |  |
| `created_at` | `timestamp` | YES |  |  |  |
| `updated_at` | `timestamp` | YES |  |  |  |

### `visual_feedback_attachments`

| Cột | Kiểu | Null | Key | Default | Extra |
|---|---|---|---|---|---|
| `id` | `bigint unsigned` | NO | PRI |  | auto_increment |
| `feedback_id` | `bigint unsigned` | NO | MUL |  |  |
| `user_id` | `int unsigned` | NO | MUL |  |  |
| `file_name` | `varchar(255)` | NO |  |  |  |
| `file_path` | `varchar(255)` | NO |  |  |  |
| `mime_type` | `varchar(120)` | NO |  |  |  |
| `file_size` | `bigint unsigned` | NO |  | 0 |  |
| `created_at` | `timestamp` | YES |  |  |  |
| `updated_at` | `timestamp` | YES |  |  |  |

### `visual_feedback_comments`

| Cột | Kiểu | Null | Key | Default | Extra |
|---|---|---|---|---|---|
| `id` | `bigint unsigned` | NO | PRI |  | auto_increment |
| `feedback_id` | `bigint unsigned` | NO | MUL |  |  |
| `user_id` | `int unsigned` | NO | MUL |  |  |
| `comment` | `text` | NO |  |  |  |
| `is_internal` | `tinyint(1)` | NO |  | 0 |  |
| `created_at` | `timestamp` | YES |  |  |  |
| `updated_at` | `timestamp` | YES |  |  |  |

### `visual_feedbacks`

| Cột | Kiểu | Null | Key | Default | Extra |
|---|---|---|---|---|---|
| `id` | `bigint unsigned` | NO | PRI |  | auto_increment |
| `user_id` | `int unsigned` | NO | MUL |  |  |
| `assigned_to` | `int unsigned` | YES | MUL |  |  |
| `parent_id` | `bigint unsigned` | YES | MUL |  |  |
| `type` | `varchar(80)` | NO | MUL |  |  |
| `priority` | `varchar(30)` | NO |  | Medium |  |
| `status` | `varchar(40)` | NO | MUL | Open |  |
| `title` | `varchar(180)` | NO |  |  |  |
| `description` | `text` | NO |  |  |  |
| `expected_result` | `text` | YES |  |  |  |
| `admin_note` | `text` | YES |  |  |  |
| `page_url` | `varchar(2048)` | NO |  |  |  |
| `page_path` | `varchar(512)` | YES | MUL |  |  |
| `page_title` | `varchar(255)` | YES |  |  |  |
| `route_name` | `varchar(255)` | YES |  |  |  |
| `element_tag` | `varchar(80)` | YES |  |  |  |
| `element_text` | `text` | YES |  |  |  |
| `element_id` | `varchar(255)` | YES |  |  |  |
| `element_classes` | `text` | YES |  |  |  |
| `css_selector` | `varchar(2048)` | NO |  |  |  |
| `xpath` | `text` | YES |  |  |  |
| `element_attributes` | `json` | YES |  |  |  |
| `element_rect` | `json` | YES |  |  |  |
| `scroll_position` | `json` | YES |  |  |  |
| `viewport` | `json` | YES |  |  |  |
| `device_type` | `varchar(40)` | YES |  |  |  |
| `browser_info` | `json` | YES |  |  |  |
| `screenshot_path` | `varchar(255)` | YES |  |  |  |
| `created_by_role` | `varchar(80)` | NO |  | user |  |
| `resolved_at` | `timestamp` | YES |  |  |  |
| `created_at` | `timestamp` | YES | MUL |  |  |
| `updated_at` | `timestamp` | YES |  |  |  |

### `warranties`

| Cột | Kiểu | Null | Key | Default | Extra |
|---|---|---|---|---|---|
| `warranty_id` | `int unsigned` | NO | PRI |  | auto_increment |
| `order_id` | `int unsigned` | NO | MUL |  |  |
| `sku` | `varchar(255)` | NO | MUL |  |  |
| `start_date` | `date` | NO |  | 2026-06-09 |  |
| `end_date` | `date` | NO |  |  |  |
| `description` | `text` | NO |  |  |  |
| `created_at` | `timestamp` | YES |  |  |  |
| `updated_at` | `timestamp` | YES |  |  |  |

### `wishlist_items`

| Cột | Kiểu | Null | Key | Default | Extra |
|---|---|---|---|---|---|
| `id` | `bigint unsigned` | NO | PRI |  | auto_increment |
| `user_id` | `int unsigned` | NO | MUL |  |  |
| `product_id` | `int unsigned` | NO | MUL |  |  |
| `created_at` | `timestamp` | YES |  |  |  |
| `updated_at` | `timestamp` | YES |  |  |  |
