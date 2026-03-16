<?php
/**
 * Admin screen, settings registration, and import/export handlers.
 *
 * @package CartFlush
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CartFlush_Admin {

	/**
	 * Rules manager.
	 *
	 * @var CartFlush_Rules
	 */
	private $rules;

	/**
	 * Constructor.
	 *
	 * @param CartFlush_Rules $rules Rules manager.
	 */
	public function __construct( CartFlush_Rules $rules ) {
		$this->rules = $rules;

		add_action( 'admin_menu', [ $this, 'add_settings_page' ] );
		add_action( 'admin_init', [ $this, 'register_settings' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		add_action( 'admin_notices', [ $this, 'render_admin_notices' ] );
		add_action( 'admin_post_cartflush_import_json', [ $this, 'handle_json_import' ] );
		add_action( 'admin_post_cartflush_import_csv', [ $this, 'handle_csv_import' ] );
		add_action( 'admin_post_cartflush_export_json', [ $this, 'handle_json_export' ] );
	}

	/**
	 * Add settings page under Settings.
	 *
	 * @return void
	 */
	public function add_settings_page() {
		add_options_page(
			__( 'CartFlush Settings', 'cartflush' ),
			__( 'CartFlush', 'cartflush' ),
			'manage_options',
			'cartflush-settings',
			[ $this, 'settings_page_html' ]
		);
	}

	/**
	 * Register plugin settings.
	 *
	 * @return void
	 */
	public function register_settings() {
		register_setting(
			'cartflush_settings_group',
			'cartflush_expiration_time',
			[
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'default'           => 30,
			]
		);

		register_setting(
			'cartflush_settings_group',
			CartFlush_Rules::OPTION_NAME,
			[
				'type'              => 'array',
				'sanitize_callback' => [ $this, 'sanitize_rules_option' ],
				'default'           => $this->rules->get_default_rules(),
			]
		);

		add_settings_section(
			'cartflush_main',
			__( 'Timeout Settings', 'cartflush' ),
			[ $this, 'render_main_section' ],
			'cartflush-settings'
		);

		add_settings_field(
			'cartflush_expiration_time',
			__( 'Default cart expiration', 'cartflush' ),
			[ $this, 'render_expiration_field' ],
			'cartflush-settings',
			'cartflush_main'
		);
	}

	/**
	 * Sanitize imported rules.
	 *
	 * @param mixed $value Rules payload.
	 * @return array<string, mixed>
	 */
	public function sanitize_rules_option( $value ) {
		return $this->rules->normalize_rules_data( $value );
	}

	/**
	 * Enqueue admin assets on the plugin settings page.
	 *
	 * @param string $hook Current admin hook suffix.
	 * @return void
	 */
	public function enqueue_assets( $hook ) {
		if ( 'settings_page_cartflush-settings' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'cartflush-admin',
			CARTFLUSH_URL . 'assets/css/admin.css',
			[],
			CARTFLUSH_VERSION
		);
	}

	/**
	 * Render settings section intro.
	 *
	 * @return void
	 */
	public function render_main_section() {
		echo '<p>' . esc_html__( 'Set a default inactivity window. Imported role and category rules can override it for specific carts.', 'cartflush' ) . '</p>';
	}

	/**
	 * Render expiration input.
	 *
	 * @return void
	 */
	public function render_expiration_field() {
		$value = (int) get_option( 'cartflush_expiration_time', 30 );
		echo '<label class="cartflush-field">';
		echo '<input type="number" min="1" step="1" class="small-text" name="cartflush_expiration_time" value="' . esc_attr( $value ) . '"> ';
		echo '<span>' . esc_html__( 'minutes', 'cartflush' ) . '</span>';
		echo '</label>';
		echo '<p class="description">' . esc_html__( 'This fallback timeout is used when no imported rule matches the current customer or cart contents.', 'cartflush' ) . '</p>';
	}

	/**
	 * Output admin notices.
	 *
	 * @return void
	 */
	public function render_admin_notices() {
		if ( ! isset( $_GET['page'] ) || 'cartflush-settings' !== sanitize_key( wp_unslash( $_GET['page'] ) ) ) {
			return;
		}

		if ( ! empty( $_GET['cartflush_notice'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( wp_unslash( $_GET['cartflush_notice'] ) ) . '</p></div>';
		}

		if ( ! empty( $_GET['cartflush_error'] ) ) {
			echo '<div class="notice notice-error"><p>' . esc_html( wp_unslash( $_GET['cartflush_error'] ) ) . '</p></div>';
		}
	}

	/**
	 * Import a full JSON settings file.
	 *
	 * @return void
	 */
	public function handle_json_import() {
		$this->assert_admin_permissions();
		check_admin_referer( 'cartflush_import_json' );

		if ( empty( $_FILES['cartflush_json_file']['tmp_name'] ) ) {
			$this->redirect_with_message( '', __( 'Please choose a JSON file to import.', 'cartflush' ) );
		}

		$raw = file_get_contents( $_FILES['cartflush_json_file']['tmp_name'] ); // phpcs:ignore WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown
		$data = json_decode( $raw, true );

		if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $data ) ) {
			$this->redirect_with_message( '', __( 'The uploaded JSON file is invalid.', 'cartflush' ) );
		}

		$default_timeout = isset( $data['cartflush_expiration_time'] ) ? absint( $data['cartflush_expiration_time'] ) : get_option( 'cartflush_expiration_time', 30 );
		$rules           = isset( $data['import_rules'] ) ? $data['import_rules'] : $data;
		$normalized      = $this->rules->normalize_rules_data( $rules );

		update_option( 'cartflush_expiration_time', max( 1, $default_timeout ) );
		update_option( CartFlush_Rules::OPTION_NAME, $normalized );

		$this->redirect_with_message( __( 'JSON settings imported successfully.', 'cartflush' ) );
	}

	/**
	 * Import timeout and exclusion rules from CSV.
	 *
	 * @return void
	 */
	public function handle_csv_import() {
		$this->assert_admin_permissions();
		check_admin_referer( 'cartflush_import_csv' );

		if ( empty( $_FILES['cartflush_csv_file']['tmp_name'] ) ) {
			$this->redirect_with_message( '', __( 'Please choose a CSV file to import.', 'cartflush' ) );
		}

		$handle = fopen( $_FILES['cartflush_csv_file']['tmp_name'], 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen,WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown

		if ( ! $handle ) {
			$this->redirect_with_message( '', __( 'The uploaded CSV file could not be read.', 'cartflush' ) );
		}

		$header = fgetcsv( $handle );

		if ( ! is_array( $header ) ) {
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			$this->redirect_with_message( '', __( 'The CSV file is empty.', 'cartflush' ) );
		}

		$header = array_map( 'sanitize_key', $header );
		$rules  = $this->rules->get_rules_option();

		while ( false !== ( $row = fgetcsv( $handle ) ) ) {
			$row = array_pad( $row, count( $header ), '' );
			$row = array_combine( $header, $row );

			if ( ! is_array( $row ) ) {
				continue;
			}

			$type    = isset( $row['type'] ) ? sanitize_key( $row['type'] ) : '';
			$key     = isset( $row['key'] ) ? $row['key'] : '';
			$timeout = isset( $row['timeout_minutes'] ) ? absint( $row['timeout_minutes'] ) : 0;

			if ( ! $type || '' === trim( $key ) ) {
				continue;
			}

			switch ( $type ) {
				case 'role':
					if ( $timeout > 0 ) {
						$rules['role_rules'][ sanitize_key( $key ) ] = $timeout;
					}
					break;

				case 'category':
					if ( $timeout > 0 ) {
						$rules['category_rules'][ sanitize_title( $key ) ] = $timeout;
					}
					break;

				case 'excluded_product':
				case 'product':
					$product_id = absint( $key );
					if ( $product_id > 0 ) {
						$rules['excluded_products'][] = $product_id;
					}
					break;

				case 'excluded_category':
					$rules['excluded_categories'][] = sanitize_title( $key );
					break;
			}
		}

		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

		update_option( CartFlush_Rules::OPTION_NAME, $this->rules->normalize_rules_data( $rules ) );

		$this->redirect_with_message( __( 'CSV rules imported successfully.', 'cartflush' ) );
	}

	/**
	 * Export JSON settings.
	 *
	 * @return void
	 */
	public function handle_json_export() {
		$this->assert_admin_permissions();
		check_admin_referer( 'cartflush_export_json' );

		$payload = [
			'plugin'                    => 'CartFlush',
			'version'                   => CARTFLUSH_VERSION,
			'exported_at'               => gmdate( 'c' ),
			'cartflush_expiration_time' => (int) get_option( 'cartflush_expiration_time', 30 ),
			'import_rules'              => $this->rules->get_rules_option(),
		];

		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=cartflush-settings-' . gmdate( 'Y-m-d' ) . '.json' );

		echo wp_json_encode( $payload, JSON_PRETTY_PRINT );
		exit;
	}

	/**
	 * Render the admin page.
	 *
	 * @return void
	 */
	public function settings_page_html() {
		$rules = $this->rules->get_rules_option();
		?>
		<div class="wrap cartflush-admin">
			<div class="cartflush-hero">
				<div>
					<h1><?php esc_html_e( 'CartFlush Settings', 'cartflush' ); ?></h1>
					<p><?php esc_html_e( 'Protect your WooCommerce experience with rule-based cart cleanup, import/export tools, and product-aware exclusions.', 'cartflush' ); ?></p>
				</div>
				<div class="cartflush-hero__meta">
					<span><?php esc_html_e( 'Version', 'cartflush' ); ?> <?php echo esc_html( CARTFLUSH_VERSION ); ?></span>
					<span><?php esc_html_e( 'Settings', 'cartflush' ); ?></span>
				</div>
			</div>

			<div class="cartflush-grid">
				<div class="cartflush-card cartflush-card--primary">
					<h2><?php esc_html_e( 'Default Timeout', 'cartflush' ); ?></h2>
					<form method="post" action="options.php">
						<?php
						settings_fields( 'cartflush_settings_group' );
						do_settings_sections( 'cartflush-settings' );
						submit_button( __( 'Save Settings', 'cartflush' ) );
						?>
					</form>
				</div>

				<div class="cartflush-card">
					<h2><?php esc_html_e( 'Import Full Settings', 'cartflush' ); ?></h2>
					<p><?php esc_html_e( 'Upload a JSON export to move CartFlush settings between sites.', 'cartflush' ); ?></p>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
						<?php wp_nonce_field( 'cartflush_import_json' ); ?>
						<input type="hidden" name="action" value="cartflush_import_json">
						<input type="file" name="cartflush_json_file" accept=".json,application/json" required>
						<?php submit_button( __( 'Import JSON', 'cartflush' ), 'secondary', 'submit', false ); ?>
					</form>
				</div>

				<div class="cartflush-card">
					<h2><?php esc_html_e( 'Import Rules from CSV', 'cartflush' ); ?></h2>
					<p><?php esc_html_e( 'Use type, key, and timeout_minutes columns to import role rules, category rules, or exclusions.', 'cartflush' ); ?></p>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
						<?php wp_nonce_field( 'cartflush_import_csv' ); ?>
						<input type="hidden" name="action" value="cartflush_import_csv">
						<input type="file" name="cartflush_csv_file" accept=".csv,text/csv" required>
						<?php submit_button( __( 'Import CSV', 'cartflush' ), 'secondary', 'submit', false ); ?>
					</form>
					<p class="description"><code>role,customer,30</code> <code>category,subscription-box,10</code> <code>excluded_product,123,</code></p>
				</div>

				<div class="cartflush-card">
					<h2><?php esc_html_e( 'Export Settings', 'cartflush' ); ?></h2>
					<p><?php esc_html_e( 'Download the current timeout, imported rules, and exclusions as a JSON snapshot.', 'cartflush' ); ?></p>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<?php wp_nonce_field( 'cartflush_export_json' ); ?>
						<input type="hidden" name="action" value="cartflush_export_json">
						<?php submit_button( __( 'Export JSON', 'cartflush' ), 'secondary', 'submit', false ); ?>
					</form>
				</div>
			</div>

			<div class="cartflush-card cartflush-card--wide">
				<h2><?php esc_html_e( 'Imported Configuration Summary', 'cartflush' ); ?></h2>
				<div class="cartflush-summary">
					<div>
						<h3><?php esc_html_e( 'Role Rules', 'cartflush' ); ?></h3>
						<p><?php echo esc_html( $this->format_assoc_list( $rules['role_rules'] ) ); ?></p>
					</div>
					<div>
						<h3><?php esc_html_e( 'Category Rules', 'cartflush' ); ?></h3>
						<p><?php echo esc_html( $this->format_assoc_list( $rules['category_rules'] ) ); ?></p>
					</div>
					<div>
						<h3><?php esc_html_e( 'Excluded Product IDs', 'cartflush' ); ?></h3>
						<p><?php echo esc_html( $this->format_simple_list( $rules['excluded_products'] ) ); ?></p>
					</div>
					<div>
						<h3><?php esc_html_e( 'Excluded Category Slugs', 'cartflush' ); ?></h3>
						<p><?php echo esc_html( $this->format_simple_list( $rules['excluded_categories'] ) ); ?></p>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Format key-value lists for display.
	 *
	 * @param array<string, int> $items Display items.
	 * @return string
	 */
	private function format_assoc_list( $items ) {
		if ( empty( $items ) ) {
			return __( 'None imported yet.', 'cartflush' );
		}

		$formatted = [];

		foreach ( $items as $key => $value ) {
			$formatted[] = sprintf(
				/* translators: 1: rule key, 2: timeout in minutes. */
				__( '%1$s: %2$d min', 'cartflush' ),
				$key,
				absint( $value )
			);
		}

		return implode( ', ', $formatted );
	}

	/**
	 * Format flat lists for display.
	 *
	 * @param array<int|string> $items Display items.
	 * @return string
	 */
	private function format_simple_list( $items ) {
		if ( empty( $items ) ) {
			return __( 'None imported yet.', 'cartflush' );
		}

		return implode( ', ', array_map( 'strval', $items ) );
	}

	/**
	 * Ensure the current user can manage the plugin.
	 *
	 * @return void
	 */
	private function assert_admin_permissions() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'cartflush' ) );
		}
	}

	/**
	 * Redirect back to the settings page with a status message.
	 *
	 * @param string $notice Success message.
	 * @param string $error  Error message.
	 * @return void
	 */
	private function redirect_with_message( $notice = '', $error = '' ) {
		$url = add_query_arg(
			array_filter(
				[
					'page'             => 'cartflush-settings',
					'cartflush_notice' => $notice,
					'cartflush_error'  => $error,
				]
			),
			admin_url( 'options-general.php' )
		);

		wp_safe_redirect( $url );
		exit;
	}
}
