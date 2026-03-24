# CartFlush - Auto Clear WooCommerce Cart for Inactive Users

Contributors: wprashed  
Tags: woocommerce cart cleanup, abandoned cart, cart timeout, woocommerce optimization, cart management  
Requires at least: 5.8  
Tested up to: 6.8  
Requires PHP: 7.4  
Stable tag: 2.1.0  
License: GPLv2 or later  
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Automatically clear inactive WooCommerce carts with advanced timeout rules, exclusions, and import/export tools.

---

## Description

CartFlush helps you automatically clear inactive WooCommerce carts, keeping your store clean, fast, and optimized.

Instead of relying on a single timeout, CartFlush gives you full control over cart expiration from one settings screen under WooCommerce. You can define different timeout rules based on customer type, user roles, cart value, products, categories, and tags, then add exclusions for carts that should never be cleared automatically.

Whether you want faster cart turnover, better session management, or cleaner abandoned cart handling, CartFlush gives you the tools to do it properly.

---

## Why Use CartFlush?

- Prevent stale and abandoned carts from piling up
- Improve WooCommerce session performance
- Apply smarter rules based on customers, cart value, and products
- Manage rules visually from the WooCommerce settings page
- Import or export rules for fast setup and migration

---

## Key Features

### Default Cart Timeout

Set a global inactivity timeout in minutes. If no other rules apply, this value determines when a cart is cleared.

### Visual Rule Builder

Create and edit cart timeout rules directly from the CartFlush settings page under WooCommerce. No CSV import is required for day-to-day management.

### Customer Type Rules

Define separate timeouts for:

- Guest customers
- Logged-in customers

### Role-Based Timeout Rules

Define custom cart expiration times for specific user roles.

Examples:

- Customers - 30 minutes
- Subscribers - 60 minutes
- Wholesale users - 120 minutes

### Cart Value Rules

Create timeout rules based on cart subtotal ranges.

Examples:

- `0-49.99` -> 20 minutes
- `50-199.99` -> 45 minutes
- `200+` -> 120 minutes

This is especially useful for giving high-value carts more time before they are cleared.

### Product, Category, and Tag Rules

Apply specific timeout values based on:

- Product ID
- Product category
- Product tag

This makes it easy to create shorter or longer expiration windows for special items, campaigns, or collections.

### Per-Product Timeout Override

Add a CartFlush timeout directly on the WooCommerce product edit screen for products that need a custom timeout without relying on a global rule.

### Smart Timeout Logic

When multiple timeout rules apply, CartFlush automatically uses the shortest valid timeout.

### Exclusion Rules

Prevent cart clearing entirely for matching carts using:

- Excluded roles
- Excluded products
- Excluded categories
- Excluded tags

### Pre-Clear Warning Notice

Optionally show a notice on the cart and checkout pages shortly before CartFlush clears the cart due to inactivity.

### CSV Import for Bulk Rules

Bulk import rule data with CSV when that is faster than manual entry.

Supported CSV types:

- `customer_type`
- `role`
- `cart_value`
- `product_rule`
- `category`
- `tag`
- `excluded_role`
- `excluded_product`
- `excluded_category`
- `excluded_tag`

CartFlush also includes a downloadable sample CSV from the settings page to help merchants get started faster.

### JSON Import and Export

Export the full CartFlush configuration as JSON for backup or migration, then import it on another store when needed.

### WooCommerce Menu Integration

The CartFlush settings page is available directly under the WooCommerce admin menu for quicker access.

### Lightweight and Efficient

CartFlush focuses only on inactivity tracking, rule evaluation, and cart clearing without adding unnecessary overhead.

### Translation Ready

Includes the `cartflush` text domain for localization.

### Clean Uninstall

When the plugin is deleted, CartFlush removes its stored settings automatically.

---

## How It Works

1. A customer adds items to the cart.
2. The inactivity timer begins.
3. CartFlush checks the default timeout and any matching timeout rules.
4. The shortest valid timeout is selected.
5. If an exclusion rule matches, cart clearing is skipped.
6. The cart is cleared after the final timeout is reached.

---

## Supported Import Formats

CSV headers:

`type,key,timeout_minutes`

Supported types:

- `customer_type`
- `role`
- `cart_value`
- `product_rule`
- `category`
- `tag`
- `excluded_role`
- `excluded_product`
- `excluded_category`
- `excluded_tag`

Example rows:

`customer_type,guest,20`  
`role,customer,30`  
`cart_value,100+,90`  
`product_rule,321,10`  
`category,flash-sale,15`  
`tag,seasonal,25`  
`excluded_role,wholesale_customer,`  
`excluded_product,123,`  
`excluded_category,high-ticket,`  
`excluded_tag,fragile,`

---

## Frequently Asked Questions

### Does this work for guest users and logged-in users?

Yes. CartFlush uses WooCommerce sessions, so both are supported.

### How is the timeout calculated?

The plugin starts with the default timeout, then checks matching customer type, role, cart value, product, category, and tag rules. The shortest valid timeout is applied.

### What if a cart contains excluded items?

CartFlush skips clearing the cart entirely.

### Can I manage rules without importing CSV?

Yes. Rules can be added and edited directly from the CartFlush settings page.

### Can I migrate settings between sites?

Yes. Export settings as JSON and import them on another site.

### Does uninstall remove all data?

Yes. All plugin options are deleted during uninstall.

---

## Screenshots

1. Modern CartFlush settings page under WooCommerce
2. Manual rule builder for timeout rules and exclusions
3. CSV and JSON import/export tools
4. Saved configuration overview

---

## Changelog

### 2.1.0

- Added a full visual rule builder to the settings page
- Moved the plugin page under the WooCommerce admin menu
- Added customer type, product, and tag timeout rules
- Added excluded role and excluded tag support
- Expanded CSV import to support all new rule types
- Redesigned the admin settings interface with a more modern layout
- Improved import/export presentation and rule card usability
- Added cart value timeout rules
- Added product-level timeout overrides in the product editor
- Added optional pre-clear cart warning notices
- Added downloadable sample CSV templates
- Added duplicate rule detection warnings in admin

### 2.0.0

- Added JSON import/export system
- Added CSV rule import support
- Added role-based timeout rules
- Added category-based timeout rules
- Added product and category exclusions
- Improved admin UI with modern layout
- Added uninstall cleanup
- Added localization support
- Refactored plugin structure

### 1.0.0

- Initial release
