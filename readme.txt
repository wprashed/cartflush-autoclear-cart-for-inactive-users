=== CartFlush AutoClear Cart for Inactive Users ===
Contributors: wprashed
Tags: woocommerce, cart, abandoned cart, cart timeout, cart cleanup
Requires at least: 5.8
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.2.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Automatically clear inactive WooCommerce carts with flexible timeout rules, exclusions, and import/export tools.

== Description ==

CartFlush helps store owners keep carts fresh by automatically clearing inactive WooCommerce carts after a configurable delay. Beyond a simple global timeout, the plugin also supports imported role-based rules, category-based rules, product exclusions, and settings migration between stores.

= Key Features =

* Set a default inactivity timeout in minutes
* Import role-based timeout rules from CSV
* Import category-based timeout rules from CSV
* Exclude specific product IDs or category slugs from auto-clear
* Export the full plugin configuration as JSON
* Import JSON settings on another site
* Clean uninstall that removes stored plugin options
* Translation-ready text domain and POT template
* Clean, modern WordPress admin screen

= Supported Import Formats =

CSV headers:

`type,key,timeout_minutes`

Supported CSV types:

* `role`
* `category`
* `excluded_product`
* `excluded_category`

Example CSV rows:

`role,customer,30`
`category,subscription-box,10`
`excluded_product,123,`
`excluded_category,high-ticket,`

JSON exports include:

* Default timeout
* Imported role rules
* Imported category rules
* Excluded products
* Excluded categories

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/cartflush-autoclear-cart-for-inactive-users/`.
2. Activate the plugin through the WordPress Plugins screen.
3. Make sure WooCommerce is active.
4. Go to `Settings > CartFlush`.
5. Save your default timeout or import rule files as needed.

== Frequently Asked Questions ==

= Does this work for guest users and logged-in users? =

Yes. CartFlush uses the WooCommerce session, so it can track inactivity for both guest and logged-in shoppers.

= How is the final timeout chosen? =

The plugin starts with the default timeout, then checks imported role rules and product category rules in the current cart. If more than one applies, the shortest valid timeout is used.

= What happens when a cart contains an excluded product or category? =

CartFlush skips auto-clearing for that cart.

= Can I move my settings between sites? =

Yes. Export the current configuration as JSON and import it on another site running CartFlush.

= Does uninstall remove plugin data? =

Yes. When the plugin is uninstalled from WordPress, CartFlush removes its saved timeout and imported rule options.

== Screenshots ==

1. Modern CartFlush settings screen with timeout controls and import/export actions
2. CSV and JSON import tools for moving rules between stores
3. Imported configuration summary showing active rules and exclusions

== Changelog ==

= 1.2.1 =

* Added uninstall cleanup for plugin options.
* Added localization scaffolding and a starter POT file.
* Added WordPress.org asset and screenshot planning documentation.

= 1.2.0 =

* Refactored the plugin into a multi-file WordPress-friendly structure.
* Added a polished admin interface with card-based layout and custom styling.
* Refreshed plugin metadata and documentation for WordPress.org distribution.
* Added README.md for repository hosting and project overview.

= 1.1.0 =

* Added JSON import/export for CartFlush settings.
* Added CSV import for role rules, category rules, and exclusion lists.
* Added support for excluded products and excluded categories.
* Added dynamic timeout matching based on user roles and cart categories.

= 1.0.0 =

* Initial release.

== Upgrade Notice ==

= 1.2.1 =

This update adds uninstall cleanup and translation scaffolding to make packaging and distribution more WordPress.org friendly.

= 1.2.0 =

This release reorganizes the plugin into a more maintainable multi-file structure and introduces a redesigned admin experience.
