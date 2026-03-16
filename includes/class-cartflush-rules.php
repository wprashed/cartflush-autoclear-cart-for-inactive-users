<?php
/**
 * Rule resolution and imported configuration helpers.
 *
 * @package CartFlush
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CartFlush_Rules {

	const OPTION_NAME = 'cartflush_import_rules';

	/**
	 * Get the normalized saved rules.
	 *
	 * @return array<string, mixed>
	 */
	public function get_rules_option() {
		return $this->normalize_rules_data( get_option( self::OPTION_NAME, $this->get_default_rules() ) );
	}

	/**
	 * Normalize rules before saving or using them.
	 *
	 * @param mixed $value Rules payload.
	 * @return array<string, mixed>
	 */
	public function normalize_rules_data( $value ) {
		$defaults = $this->get_default_rules();
		$value    = is_array( $value ) ? $value : [];
		$rules    = wp_parse_args( $value, $defaults );

		$normalized_role_rules = [];
		if ( is_array( $rules['role_rules'] ) ) {
			foreach ( $rules['role_rules'] as $role => $timeout ) {
				$role    = sanitize_key( $role );
				$timeout = absint( $timeout );

				if ( $role && $timeout > 0 ) {
					$normalized_role_rules[ $role ] = $timeout;
				}
			}
		}

		$normalized_category_rules = [];
		if ( is_array( $rules['category_rules'] ) ) {
			foreach ( $rules['category_rules'] as $slug => $timeout ) {
				$slug    = sanitize_title( $slug );
				$timeout = absint( $timeout );

				if ( $slug && $timeout > 0 ) {
					$normalized_category_rules[ $slug ] = $timeout;
				}
			}
		}

		$excluded_products = [];
		if ( is_array( $rules['excluded_products'] ) ) {
			foreach ( $rules['excluded_products'] as $product_id ) {
				$product_id = absint( $product_id );

				if ( $product_id > 0 ) {
					$excluded_products[] = $product_id;
				}
			}
		}

		$excluded_categories = [];
		if ( is_array( $rules['excluded_categories'] ) ) {
			foreach ( $rules['excluded_categories'] as $slug ) {
				$slug = sanitize_title( $slug );

				if ( $slug ) {
					$excluded_categories[] = $slug;
				}
			}
		}

		return [
			'role_rules'          => $normalized_role_rules,
			'category_rules'      => $normalized_category_rules,
			'excluded_products'   => array_values( array_unique( $excluded_products ) ),
			'excluded_categories' => array_values( array_unique( $excluded_categories ) ),
		];
	}

	/**
	 * Get the cart timeout with imported rules applied.
	 *
	 * @return int
	 */
	public function get_cart_expiration_minutes() {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return 0;
		}

		$timeouts   = [ (int) get_option( 'cartflush_expiration_time', 30 ) ];
		$rules      = $this->get_rules_option();
		$user       = wp_get_current_user();
		$cart_items = WC()->cart->get_cart();

		if ( $user instanceof WP_User && ! empty( $user->roles ) ) {
			foreach ( $user->roles as $role ) {
				$role = sanitize_key( $role );

				if ( isset( $rules['role_rules'][ $role ] ) ) {
					$timeouts[] = (int) $rules['role_rules'][ $role ];
				}
			}
		}

		foreach ( $cart_items as $cart_item ) {
			$product_id   = isset( $cart_item['product_id'] ) ? absint( $cart_item['product_id'] ) : 0;
			$category_ids = $product_id ? wc_get_product_term_ids( $product_id, 'product_cat' ) : [];

			foreach ( $category_ids as $category_id ) {
				$term = get_term( $category_id, 'product_cat' );

				if ( $term && ! is_wp_error( $term ) && isset( $rules['category_rules'][ $term->slug ] ) ) {
					$timeouts[] = (int) $rules['category_rules'][ $term->slug ];
				}
			}
		}

		$timeouts = array_filter(
			array_map( 'absint', $timeouts ),
			static function( $timeout ) {
				return $timeout > 0;
			}
		);

		return empty( $timeouts ) ? 0 : min( $timeouts );
	}

	/**
	 * Determine whether the current cart contains excluded products or categories.
	 *
	 * @return bool
	 */
	public function cart_has_excluded_items() {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return false;
		}

		$rules = $this->get_rules_option();

		foreach ( WC()->cart->get_cart() as $cart_item ) {
			$product_id = isset( $cart_item['product_id'] ) ? absint( $cart_item['product_id'] ) : 0;

			if ( $product_id && in_array( $product_id, $rules['excluded_products'], true ) ) {
				return true;
			}

			$category_ids = $product_id ? wc_get_product_term_ids( $product_id, 'product_cat' ) : [];

			foreach ( $category_ids as $category_id ) {
				$term = get_term( $category_id, 'product_cat' );

				if ( $term && ! is_wp_error( $term ) && in_array( $term->slug, $rules['excluded_categories'], true ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Get default rules shape.
	 *
	 * @return array<string, array>
	 */
	public function get_default_rules() {
		return [
			'role_rules'          => [],
			'category_rules'      => [],
			'excluded_products'   => [],
			'excluded_categories' => [],
		];
	}
}
