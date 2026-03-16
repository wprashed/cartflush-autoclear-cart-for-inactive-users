# CartFlush AutoClear Cart for Inactive Users

CartFlush is a WooCommerce utility plugin that automatically clears inactive carts after a configurable timeout. It also supports imported rule sets, product and category exclusions, JSON-based settings transfer between stores, and WordPress.org-friendly packaging.

## Features

- Default cart inactivity timeout
- CSV import for role-based timeout rules
- CSV import for category-based timeout rules
- Product and category exclusion support
- JSON import/export for full plugin settings
- Clean uninstall behavior
- Translation-ready text domain with POT template
- Modern WordPress admin screen

## Example CSV Format

```csv
type,key,timeout_minutes
role,customer,30
category,subscription-box,10
excluded_product,123,
excluded_category,high-ticket,
```

## JSON Export Contents

CartFlush exports:

- Default timeout
- Imported role rules
- Imported category rules
- Excluded product IDs
- Excluded category slugs

## Development Structure

```text
cartflush-autoclear-cart-for-inactive-users.php
includes/
  class-cartflush-plugin.php
  class-cartflush-rules.php
  admin/
    class-cartflush-admin.php
assets/
  css/
    admin.css
docs/
  wordpress-org-assets.md
languages/
  cartflush.pot
readme.txt
readme.md
uninstall.php
```

## Installation

1. Copy the plugin to `wp-content/plugins/cartflush-autoclear-cart-for-inactive-users`.
2. Activate it from the WordPress admin.
3. Ensure WooCommerce is active.
4. Open `Settings > CartFlush`.

## WordPress.org Prep

- Translation template: [languages/cartflush.pot](/Users/rashed/Pictures/cartflush-autoclear-cart-for-inactive-users/languages/cartflush.pot)
- Asset guidance: [docs/wordpress-org-assets.md](/Users/rashed/Pictures/cartflush-autoclear-cart-for-inactive-users/docs/wordpress-org-assets.md)
- Uninstall cleanup: [uninstall.php](/Users/rashed/Pictures/cartflush-autoclear-cart-for-inactive-users/uninstall.php)

## Changelog

### 1.2.1

- Added uninstall cleanup for saved options
- Added a starter POT file for translations
- Added WordPress.org asset and screenshot guidance

### 1.2.0

- Refactored the plugin into a multi-file structure
- Added a cleaner admin UI
- Updated WordPress.org readme
- Added repository-friendly `readme.md`
