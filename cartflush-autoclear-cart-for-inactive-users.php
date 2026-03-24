<?php
/**
 * Plugin Name: CartFlush AutoClear Cart for Inactive Users
 * Plugin URI: https://wordpress.org/plugins/cartflush-autoclear-cart-for-inactive-users/
 * Description: Automatically clears WooCommerce carts after inactivity with configurable default timeouts, import/export tools, and rule-based exclusions.
 * Version: 2.2.0
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Author: Rashed Hossain
 * Author URI: https://profiles.wordpress.org/wprashed/
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: cartflush
 * Domain Path: /languages
 *
 * @package CartFlush
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CARTFLUSH_VERSION', '2.2.0' );
define( 'CARTFLUSH_FILE', __FILE__ );
define( 'CARTFLUSH_PATH', plugin_dir_path( __FILE__ ) );
define( 'CARTFLUSH_URL', plugin_dir_url( __FILE__ ) );

require_once CARTFLUSH_PATH . 'includes/class-cartflush-rules.php';
require_once CARTFLUSH_PATH . 'includes/admin/class-cartflush-admin.php';
require_once CARTFLUSH_PATH . 'includes/class-cartflush-plugin.php';

function cartflush() {
	static $plugin = null;

	if ( null === $plugin ) {
		$plugin = new CartFlush_Plugin();
	}

	return $plugin;
}

cartflush();
