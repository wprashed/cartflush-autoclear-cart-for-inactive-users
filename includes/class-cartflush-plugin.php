<?php
/**
 * Main plugin bootstrap class.
 *
 * @package CartFlush
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CartFlush_Plugin {

	/**
	 * Rules manager.
	 *
	 * @var CartFlush_Rules
	 */
	private $rules;

	/**
	 * Admin controller.
	 *
	 * @var CartFlush_Admin
	 */
	private $admin;

	public function __construct() {
		$this->rules = new CartFlush_Rules();

		add_action( 'plugins_loaded', [ $this, 'load_textdomain' ] );
		add_action( 'init', [ $this, 'maybe_clear_cart' ] );
		add_action( 'woocommerce_before_cart', [ $this, 'store_last_activity_time' ] );
		add_action( 'woocommerce_before_checkout_form', [ $this, 'store_last_activity_time' ] );
		add_action( 'woocommerce_add_to_cart', [ $this, 'store_last_activity_time' ] );

		if ( is_admin() ) {
			$this->admin = new CartFlush_Admin( $this->rules );
		}
	}

	/**
	 * Load translations.
	 *
	 * @return void
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'cartflush', false, dirname( plugin_basename( CARTFLUSH_FILE ) ) . '/languages' );
	}

	/**
	 * Persist cart activity timestamps.
	 *
	 * @return void
	 */
	public function store_last_activity_time() {
		if ( ! function_exists( 'WC' ) || ! WC()->session ) {
			return;
		}

		WC()->session->set( 'cart_last_activity', time() );
	}

	/**
	 * Clear the cart when inactivity rules are met.
	 *
	 * @return void
	 */
	public function maybe_clear_cart() {
		if ( is_admin() || ! function_exists( 'WC' ) || ! WC()->session || ! WC()->cart ) {
			return;
		}

		if ( $this->rules->cart_has_excluded_items() ) {
			return;
		}

		$last_activity  = WC()->session->get( 'cart_last_activity' );
		$expire_minutes = $this->rules->get_cart_expiration_minutes();

		if ( ! $last_activity || ! $expire_minutes ) {
			return;
		}

		if ( ( time() - $last_activity ) > ( $expire_minutes * 60 ) ) {
			WC()->cart->empty_cart();
			WC()->session->set( 'cart_last_activity', time() );

			add_action(
				'woocommerce_before_cart',
				static function() {
					wc_print_notice( __( 'Your cart was cleared due to inactivity.', 'cartflush' ), 'notice' );
				}
			);
		}
	}
}
