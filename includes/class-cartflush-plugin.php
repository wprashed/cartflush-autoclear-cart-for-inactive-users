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
		add_action( 'wp', [ $this, 'maybe_add_warning_notice' ] );
		add_action( 'woocommerce_before_cart', [ $this, 'store_last_activity_time' ] );
		add_action( 'woocommerce_before_checkout_form', [ $this, 'store_last_activity_time' ] );
		add_action( 'woocommerce_add_to_cart', [ $this, 'store_last_activity_time' ] );

		if ( is_admin() ) {
			$this->admin = new CartFlush_Admin( $this->rules );
			add_action( 'woocommerce_product_options_general_product_data', [ $this, 'render_product_timeout_field' ] );
			add_action( 'woocommerce_process_product_meta', [ $this, 'save_product_timeout_field' ] );
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

	/**
	 * Show a warning before the cart is about to be cleared.
	 *
	 * @return void
	 */
	public function maybe_add_warning_notice() {
		if ( is_admin() || ! function_exists( 'WC' ) || ! WC()->session || ! WC()->cart ) {
			return;
		}

		if ( ! is_cart() && ! is_checkout() ) {
			return;
		}

		if ( 'yes' !== get_option( 'cartflush_enable_warning_notice', 'no' ) ) {
			return;
		}

		if ( $this->rules->cart_has_excluded_items() ) {
			return;
		}

		$last_activity     = absint( WC()->session->get( 'cart_last_activity' ) );
		$expire_minutes    = absint( $this->rules->get_cart_expiration_minutes() );
		$warning_threshold = absint( get_option( 'cartflush_warning_notice_minutes', 5 ) );

		if ( ! $last_activity || $expire_minutes <= 0 || $warning_threshold <= 0 ) {
			return;
		}

		$remaining_seconds = ( $expire_minutes * 60 ) - ( time() - $last_activity );

		if ( $remaining_seconds <= 0 || $remaining_seconds > ( $warning_threshold * 60 ) ) {
			return;
		}

		if ( wc_has_notice( __( 'Your cart will expire soon due to inactivity. Continue shopping or update your cart to keep it active.', 'cartflush' ), 'notice' ) ) {
			return;
		}

		wc_add_notice( __( 'Your cart will expire soon due to inactivity. Continue shopping or update your cart to keep it active.', 'cartflush' ), 'notice' );
	}

	/**
	 * Render product-level timeout override field.
	 *
	 * @return void
	 */
	public function render_product_timeout_field() {
		echo '<div class="options_group">';
		woocommerce_wp_text_input(
			[
				'id'                => CartFlush_Rules::PRODUCT_TIMEOUT_META_KEY,
				'label'             => __( 'CartFlush timeout', 'cartflush' ),
				'description'       => __( 'Optional timeout in minutes for this product. Leave empty to use global CartFlush rules.', 'cartflush' ),
				'desc_tip'          => true,
				'type'              => 'number',
				'custom_attributes' => [
					'min'  => '1',
					'step' => '1',
				],
				'value'             => get_post_meta( get_the_ID(), CartFlush_Rules::PRODUCT_TIMEOUT_META_KEY, true ),
			]
		);
		echo '</div>';
	}

	/**
	 * Save product-level timeout override.
	 *
	 * @param int $post_id Product ID.
	 * @return void
	 */
	public function save_product_timeout_field( $post_id ) {
		$value = isset( $_POST[ CartFlush_Rules::PRODUCT_TIMEOUT_META_KEY ] ) ? absint( wp_unslash( $_POST[ CartFlush_Rules::PRODUCT_TIMEOUT_META_KEY ] ) ) : 0;

		if ( $value > 0 ) {
			update_post_meta( $post_id, CartFlush_Rules::PRODUCT_TIMEOUT_META_KEY, $value );
			return;
		}

		delete_post_meta( $post_id, CartFlush_Rules::PRODUCT_TIMEOUT_META_KEY );
	}
}
