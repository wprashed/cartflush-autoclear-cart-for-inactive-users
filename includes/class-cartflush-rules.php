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
	const PRODUCT_TIMEOUT_META_KEY = '_cartflush_timeout_override';

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

		return [
			'customer_type_rules' => $this->normalize_customer_type_rules( $rules['customer_type_rules'] ),
			'role_rules'          => $this->normalize_timeout_map( $rules['role_rules'], 'sanitize_key' ),
			'cart_value_rules'    => $this->normalize_cart_value_rules( $rules['cart_value_rules'] ),
			'category_rules'      => $this->normalize_timeout_map( $rules['category_rules'], 'sanitize_title' ),
			'tag_rules'           => $this->normalize_timeout_map( $rules['tag_rules'], 'sanitize_title' ),
			'product_rules'       => $this->normalize_product_timeout_rules( $rules['product_rules'] ),
			'excluded_roles'      => $this->normalize_string_list( $rules['excluded_roles'], 'sanitize_key' ),
			'excluded_products'   => $this->normalize_integer_list( $rules['excluded_products'] ),
			'excluded_categories' => $this->normalize_string_list( $rules['excluded_categories'], 'sanitize_title' ),
			'excluded_tags'       => $this->normalize_string_list( $rules['excluded_tags'], 'sanitize_title' ),
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
		$is_guest   = ! ( $user instanceof WP_User ) || 0 === (int) $user->ID;
		$cart_total = $this->get_cart_subtotal();

		if ( $is_guest && isset( $rules['customer_type_rules']['guest'] ) ) {
			$timeouts[] = (int) $rules['customer_type_rules']['guest'];
		}

		if ( ! $is_guest && isset( $rules['customer_type_rules']['logged_in'] ) ) {
			$timeouts[] = (int) $rules['customer_type_rules']['logged_in'];
		}

		if ( $user instanceof WP_User && ! empty( $user->roles ) ) {
			foreach ( $user->roles as $role ) {
				$role = sanitize_key( $role );

				if ( isset( $rules['role_rules'][ $role ] ) ) {
					$timeouts[] = (int) $rules['role_rules'][ $role ];
				}
			}
		}

		foreach ( $rules['cart_value_rules'] as $rule ) {
			$minimum = isset( $rule['minimum'] ) ? (float) $rule['minimum'] : 0;
			$maximum = isset( $rule['maximum'] ) ? (float) $rule['maximum'] : 0;
			$timeout = isset( $rule['timeout'] ) ? absint( $rule['timeout'] ) : 0;

			if ( $timeout <= 0 || $cart_total < $minimum ) {
				continue;
			}

			if ( $maximum > 0 && $cart_total > $maximum ) {
				continue;
			}

			$timeouts[] = $timeout;
		}

		foreach ( $cart_items as $cart_item ) {
			$product_id = isset( $cart_item['product_id'] ) ? absint( $cart_item['product_id'] ) : 0;

			if ( ! $product_id ) {
				continue;
			}

			$product_timeout = $this->get_product_timeout_override( $product_id );

			if ( $product_timeout > 0 ) {
				$timeouts[] = $product_timeout;
			}

			if ( isset( $rules['product_rules'][ $product_id ] ) ) {
				$timeouts[] = (int) $rules['product_rules'][ $product_id ];
			}

			foreach ( $this->get_product_term_slugs( $product_id, 'product_cat' ) as $slug ) {
				if ( isset( $rules['category_rules'][ $slug ] ) ) {
					$timeouts[] = (int) $rules['category_rules'][ $slug ];
				}
			}

			foreach ( $this->get_product_term_slugs( $product_id, 'product_tag' ) as $slug ) {
				if ( isset( $rules['tag_rules'][ $slug ] ) ) {
					$timeouts[] = (int) $rules['tag_rules'][ $slug ];
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
	 * Determine whether the current cart contains excluded items.
	 *
	 * @return bool
	 */
	public function cart_has_excluded_items() {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return false;
		}

		$rules = $this->get_rules_option();
		$user  = wp_get_current_user();

		if ( $user instanceof WP_User && ! empty( $user->roles ) ) {
			foreach ( $user->roles as $role ) {
				if ( in_array( sanitize_key( $role ), $rules['excluded_roles'], true ) ) {
					return true;
				}
			}
		}

		foreach ( WC()->cart->get_cart() as $cart_item ) {
			$product_id = isset( $cart_item['product_id'] ) ? absint( $cart_item['product_id'] ) : 0;

			if ( $product_id && in_array( $product_id, $rules['excluded_products'], true ) ) {
				return true;
			}

			foreach ( $this->get_product_term_slugs( $product_id, 'product_cat' ) as $slug ) {
				if ( in_array( $slug, $rules['excluded_categories'], true ) ) {
					return true;
				}
			}

			foreach ( $this->get_product_term_slugs( $product_id, 'product_tag' ) as $slug ) {
				if ( in_array( $slug, $rules['excluded_tags'], true ) ) {
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
			'customer_type_rules' => [],
			'role_rules'          => [],
			'cart_value_rules'    => [],
			'category_rules'      => [],
			'tag_rules'           => [],
			'product_rules'       => [],
			'excluded_roles'      => [],
			'excluded_products'   => [],
			'excluded_categories' => [],
			'excluded_tags'       => [],
		];
	}

	/**
	 * Normalize customer type rules.
	 *
	 * @param mixed $rules Customer type rules.
	 * @return array<string, int>
	 */
	private function normalize_customer_type_rules( $rules ) {
		$allowed    = [ 'guest', 'logged_in' ];
		$normalized = [];

		if ( ! is_array( $rules ) ) {
			return $normalized;
		}

		foreach ( $rules as $type => $timeout ) {
			$type    = sanitize_key( $type );
			$timeout = absint( $timeout );

			if ( in_array( $type, $allowed, true ) && $timeout > 0 ) {
				$normalized[ $type ] = $timeout;
			}
		}

		return $normalized;
	}

	/**
	 * Normalize cart value rules.
	 *
	 * @param mixed $rules Cart value rules.
	 * @return array<int, array<string, float|int>>
	 */
	private function normalize_cart_value_rules( $rules ) {
		$normalized = [];

		if ( ! is_array( $rules ) ) {
			return $normalized;
		}

		foreach ( $rules as $rule ) {
			if ( ! is_array( $rule ) ) {
				continue;
			}

			$minimum = isset( $rule['minimum'] ) ? wc_format_decimal( $rule['minimum'] ) : 0;
			$maximum = isset( $rule['maximum'] ) ? wc_format_decimal( $rule['maximum'] ) : 0;
			$timeout = isset( $rule['timeout'] ) ? absint( $rule['timeout'] ) : 0;

			$minimum = max( 0, (float) $minimum );
			$maximum = max( 0, (float) $maximum );

			if ( $timeout <= 0 ) {
				continue;
			}

			if ( $maximum > 0 && $maximum < $minimum ) {
				continue;
			}

			$normalized[] = [
				'minimum' => $minimum,
				'maximum' => $maximum,
				'timeout' => $timeout,
			];
		}

		return $normalized;
	}

	/**
	 * Normalize a generic timeout map.
	 *
	 * @param mixed    $rules Timeout rules.
	 * @param callable $sanitizer Key sanitizer.
	 * @return array<string, int>
	 */
	private function normalize_timeout_map( $rules, $sanitizer ) {
		$normalized = [];

		if ( ! is_array( $rules ) ) {
			return $normalized;
		}

		foreach ( $rules as $key => $timeout ) {
			$key     = call_user_func( $sanitizer, $key );
			$timeout = absint( $timeout );

			if ( $key && $timeout > 0 ) {
				$normalized[ $key ] = $timeout;
			}
		}

		return $normalized;
	}

	/**
	 * Normalize product-specific timeout rules.
	 *
	 * @param mixed $rules Product rules.
	 * @return array<int, int>
	 */
	private function normalize_product_timeout_rules( $rules ) {
		$normalized = [];

		if ( ! is_array( $rules ) ) {
			return $normalized;
		}

		foreach ( $rules as $product_id => $timeout ) {
			$product_id = absint( $product_id );
			$timeout    = absint( $timeout );

			if ( $product_id > 0 && $timeout > 0 ) {
				$normalized[ $product_id ] = $timeout;
			}
		}

		return $normalized;
	}

	/**
	 * Normalize a list of integer IDs.
	 *
	 * @param mixed $items List value.
	 * @return array<int>
	 */
	private function normalize_integer_list( $items ) {
		$normalized = [];

		if ( ! is_array( $items ) ) {
			return $normalized;
		}

		foreach ( $items as $item ) {
			$item = absint( $item );

			if ( $item > 0 ) {
				$normalized[] = $item;
			}
		}

		return array_values( array_unique( $normalized ) );
	}

	/**
	 * Normalize a list of strings.
	 *
	 * @param mixed    $items List value.
	 * @param callable $sanitizer Item sanitizer.
	 * @return array<int, string>
	 */
	private function normalize_string_list( $items, $sanitizer ) {
		$normalized = [];

		if ( ! is_array( $items ) ) {
			return $normalized;
		}

		foreach ( $items as $item ) {
			$item = call_user_func( $sanitizer, $item );

			if ( $item ) {
				$normalized[] = $item;
			}
		}

		return array_values( array_unique( $normalized ) );
	}

	/**
	 * Get sanitized term slugs for a product and taxonomy.
	 *
	 * @param int    $product_id Product ID.
	 * @param string $taxonomy Taxonomy name.
	 * @return array<int, string>
	 */
	private function get_product_term_slugs( $product_id, $taxonomy ) {
		$product_id = absint( $product_id );

		if ( $product_id <= 0 ) {
			return [];
		}

		$terms = get_the_terms( $product_id, $taxonomy );

		if ( is_wp_error( $terms ) || ! is_array( $terms ) ) {
			return [];
		}

		$slugs = [];

		foreach ( $terms as $term ) {
			if ( isset( $term->slug ) ) {
				$slugs[] = sanitize_title( $term->slug );
			}
		}

		return array_values( array_unique( array_filter( $slugs ) ) );
	}

	/**
	 * Get a product-level timeout override.
	 *
	 * @param int $product_id Product ID.
	 * @return int
	 */
	public function get_product_timeout_override( $product_id ) {
		return absint( get_post_meta( absint( $product_id ), self::PRODUCT_TIMEOUT_META_KEY, true ) );
	}

	/**
	 * Resolve the current cart subtotal for value rules.
	 *
	 * @return float
	 */
	private function get_cart_subtotal() {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return 0;
		}

		$subtotal = WC()->cart->get_subtotal();

		if ( '' === $subtotal || null === $subtotal ) {
			return 0;
		}

		return (float) wc_format_decimal( $subtotal );
	}
}
