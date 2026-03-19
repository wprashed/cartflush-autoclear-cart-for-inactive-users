Here’s a **WordPress.org–optimized version** of your README. This is cleaner, keyword-focused, and aligned with how plugins rank and convert on wp.org:

---

## === CartFlush – Auto Clear WooCommerce Cart for Inactive Users ===

Contributors: wprashed
Tags: woocommerce cart cleanup, abandoned cart, cart timeout, woocommerce optimization, cart management
Requires at least: 5.8
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 2.0.0
License: GPLv2 or later
License URI: [https://www.gnu.org/licenses/gpl-2.0.html](https://www.gnu.org/licenses/gpl-2.0.html)

Automatically clear inactive WooCommerce carts with advanced timeout rules, exclusions, and import/export tools.

---

## == Description ==

CartFlush helps you automatically clear inactive WooCommerce carts, keeping your store clean, fast, and optimized.

Instead of relying on a basic timeout, CartFlush gives you full control over how cart expiration works. You can define different timeout rules based on user roles, product categories, and exclusions—making it flexible enough for real-world eCommerce scenarios.

Whether you want faster cart turnover, better session management, or cleaner abandoned cart handling, CartFlush gives you the tools to do it properly.

---

## 🚀 Why Use CartFlush?

* Prevent stale and abandoned carts from piling up
* Improve WooCommerce session performance
* Apply smarter rules based on users and products
* Save time with import/export configuration tools
* Maintain clean and optimized cart behavior

---

## == Key Features ==

### ⏱ Default Cart Timeout

Set a global inactivity timeout (in minutes). If no other rules apply, this value determines when a cart is cleared.

---

### 👤 Role-Based Timeout Rules

Define custom cart expiration times based on user roles.

Examples:

* Customers → 30 minutes
* Subscribers → 60 minutes
* Wholesale users → 120 minutes

Perfect for stores with different user types and behaviors.

---

### 🛍 Category-Based Timeout Rules

Set cart timeout rules based on product categories.

Use cases:

* Flash sale items → shorter timeout
* Subscription products → shorter timeout
* High-value products → longer timeout

CartFlush checks all items in the cart and applies the most relevant rule.

---

### ⚡ Smart Timeout Logic

When multiple rules apply, CartFlush automatically selects the **shortest timeout**.

This ensures:

* Predictable behavior
* Better control over urgency
* No rule conflicts

---

### 🚫 Product Exclusions

Exclude specific products from cart clearing.

If a cart contains an excluded product:
→ The cart will NOT be cleared.

---

### 📂 Category Exclusions

Exclude entire categories from auto-clear.

If any product in the cart belongs to an excluded category:
→ Cart clearing is skipped.

---

### 📥 CSV Import for Rules

Bulk import rules using CSV.

Supported types:

* role
* category
* excluded_product
* excluded_category

Quickly configure large stores without manual setup.

---

### 📤 JSON Export (Full Backup)

Export all settings into a JSON file.

Includes:

* Default timeout
* Role rules
* Category rules
* Exclusions

Perfect for backups and migrations.

---

### 🔁 JSON Import (Quick Setup)

Import settings instantly on another site.

Ideal for:

* Agencies
* Multi-store setups
* Staging → production deployment

---

### 👥 Works for Guests & Logged-in Users

CartFlush uses WooCommerce sessions, so it works for:

* Guest users
* Logged-in customers

No additional configuration required.

---

### 🎯 Lightweight & Efficient

No unnecessary overhead. The plugin focuses only on:

* Tracking inactivity
* Applying rules
* Clearing carts

---

### 🌍 Translation Ready

Includes text domain and POT file for easy localization.

---

### 🧹 Clean Uninstall

When the plugin is deleted:

* All data and settings are removed automatically

---

## == How It Works ==

1. Customer adds items to cart
2. Inactivity timer starts
3. Plugin checks:

   * Default timeout
   * User role rules
   * Product category rules
4. Shortest valid timeout is applied
5. If excluded items exist → skip clearing
6. Cart is cleared after timeout

---

## == Supported Import Formats ==

CSV headers:

`type,key,timeout_minutes`

### Supported types:

* role
* category
* excluded_product
* excluded_category

### Example:

`role,customer,30`
`category,subscription-box,10`
`excluded_product,123,`
`excluded_category,high-ticket,`

---

## == Frequently Asked Questions ==

### Does this work for guest users and logged-in users?

Yes. CartFlush uses WooCommerce sessions, so both are supported.

---

### How is the timeout calculated?

The plugin starts with the default timeout, then checks role and category rules. The shortest valid timeout is applied.

---

### What if a cart contains excluded items?

CartFlush will skip clearing the cart entirely.

---

### Can I migrate settings between sites?

Yes. Export settings as JSON and import them on another site.

---

### Does uninstall remove all data?

Yes. All plugin options are deleted during uninstall.

---

## == Screenshots ==

1. Clean and modern CartFlush settings panel
2. CSV import interface for rules
3. JSON export/import tools
4. Active rules and exclusions overview

---

## == Changelog ==

= 2.0.0 =

* Added JSON import/export system
* Added CSV rule import support
* Added role-based timeout rules
* Added category-based timeout rules
* Added product and category exclusions
* Improved admin UI with modern layout
* Added uninstall cleanup
* Added localization support
* Refactored plugin structure

= 1.0.0 =

* Initial release
