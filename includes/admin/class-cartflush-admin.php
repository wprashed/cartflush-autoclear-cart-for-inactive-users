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
		add_filter( 'option_page_capability_cartflush_settings_group', [ $this, 'settings_capability' ] );
	}

	/**
	 * Add settings page under WooCommerce.
	 *
	 * @return void
	 */
	public function add_settings_page() {
		add_submenu_page(
			'woocommerce',
			__( 'CartFlush Settings', 'cartflush' ),
			__( 'CartFlush', 'cartflush' ),
			$this->get_required_capability(),
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
	 * Sanitize rules before saving from the settings form.
	 *
	 * @param mixed $value Rules payload.
	 * @return array<string, mixed>
	 */
	public function sanitize_rules_option( $value ) {
		$prepared = is_array( $value ) ? $this->prepare_rules_for_storage( $value ) : $value;

		return $this->rules->normalize_rules_data( $prepared );
	}

	/**
	 * Use WooCommerce capability on options.php submissions.
	 *
	 * @return string
	 */
	public function settings_capability() {
		return $this->get_required_capability();
	}

	/**
	 * Enqueue admin assets on the plugin settings page.
	 *
	 * @param string $hook Current admin hook suffix.
	 * @return void
	 */
	public function enqueue_assets( $hook ) {
		if ( 'woocommerce_page_cartflush-settings' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'cartflush-admin',
			CARTFLUSH_URL . 'assets/css/admin.css',
			[],
			CARTFLUSH_VERSION
		);

		wp_enqueue_script(
			'cartflush-admin',
			CARTFLUSH_URL . 'assets/js/admin.js',
			[],
			CARTFLUSH_VERSION,
			true
		);
	}

	/**
	 * Render settings section intro.
	 *
	 * @return void
	 */
	public function render_main_section() {
		echo '<p>' . esc_html__( 'Set a default inactivity window, then add role, category, and exclusion rules directly below. CSV and JSON imports still work and feed into the same saved configuration.', 'cartflush' ) . '</p>';
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
		echo '<p class="description">' . esc_html__( 'This fallback timeout is used when no matching role or category rule applies to the current cart.', 'cartflush' ) . '</p>';
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

		if ( ! empty( $_GET['settings-updated'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved.', 'cartflush' ) . '</p></div>';
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

		$raw  = file_get_contents( $_FILES['cartflush_json_file']['tmp_name'] ); // phpcs:ignore WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown
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
		$rules      = $this->rules->get_rules_option();
		$roles      = $this->get_role_options();
		$categories = $this->get_product_category_options();
		?>
		<div class="wrap cartflush-admin">
			<div class="cartflush-hero">
				<div>
					<h1><?php esc_html_e( 'CartFlush Settings', 'cartflush' ); ?></h1>
					<p><?php esc_html_e( 'Manage your default timeout, add custom rules directly from this screen, and still use CSV or JSON tools when you need bulk changes or migrations.', 'cartflush' ); ?></p>
				</div>
				<div class="cartflush-hero__meta">
					<span><?php esc_html_e( 'Version', 'cartflush' ); ?> <?php echo esc_html( CARTFLUSH_VERSION ); ?></span>
					<span><?php esc_html_e( 'WooCommerce', 'cartflush' ); ?></span>
				</div>
			</div>

			<div class="cartflush-grid">
				<div class="cartflush-card cartflush-card--primary">
					<h2><?php esc_html_e( 'Timeout and Rule Settings', 'cartflush' ); ?></h2>
					<form method="post" action="options.php" class="cartflush-settings-form">
						<?php
						settings_fields( 'cartflush_settings_group' );
						do_settings_sections( 'cartflush-settings' );
						?>

						<div class="cartflush-editor-grid">
							<div class="cartflush-editor-panel">
								<div class="cartflush-editor-panel__header">
									<div>
										<h3><?php esc_html_e( 'Role-Based Rules', 'cartflush' ); ?></h3>
										<p><?php esc_html_e( 'Assign a custom timeout to specific user roles.', 'cartflush' ); ?></p>
									</div>
									<button type="button" class="button button-secondary" data-cartflush-add-row="role-rule"><?php esc_html_e( 'Add Role Rule', 'cartflush' ); ?></button>
								</div>
								<div class="cartflush-table-wrap">
									<table class="widefat striped cartflush-rule-table">
										<thead>
											<tr>
												<th><?php esc_html_e( 'Role', 'cartflush' ); ?></th>
												<th><?php esc_html_e( 'Timeout (minutes)', 'cartflush' ); ?></th>
											</tr>
										</thead>
										<tbody data-cartflush-rows="role-rule">
											<?php $this->render_role_rule_rows( $rules['role_rules'], $roles ); ?>
										</tbody>
									</table>
								</div>
							</div>

							<div class="cartflush-editor-panel">
								<div class="cartflush-editor-panel__header">
									<div>
										<h3><?php esc_html_e( 'Category-Based Rules', 'cartflush' ); ?></h3>
										<p><?php esc_html_e( 'Set a shorter or longer timeout for specific product categories.', 'cartflush' ); ?></p>
									</div>
									<button type="button" class="button button-secondary" data-cartflush-add-row="category-rule"><?php esc_html_e( 'Add Category Rule', 'cartflush' ); ?></button>
								</div>
								<div class="cartflush-table-wrap">
									<table class="widefat striped cartflush-rule-table">
										<thead>
											<tr>
												<th><?php esc_html_e( 'Category', 'cartflush' ); ?></th>
												<th><?php esc_html_e( 'Timeout (minutes)', 'cartflush' ); ?></th>
											</tr>
										</thead>
										<tbody data-cartflush-rows="category-rule">
											<?php $this->render_category_rule_rows( $rules['category_rules'], $categories ); ?>
										</tbody>
									</table>
								</div>
							</div>
						</div>

						<div class="cartflush-editor-grid cartflush-editor-grid--secondary">
							<div class="cartflush-editor-panel">
								<div class="cartflush-editor-panel__header">
									<div>
										<h3><?php esc_html_e( 'Excluded Products', 'cartflush' ); ?></h3>
										<p><?php esc_html_e( 'If any of these product IDs are in the cart, CartFlush will skip clearing it.', 'cartflush' ); ?></p>
									</div>
									<button type="button" class="button button-secondary" data-cartflush-add-row="excluded-product"><?php esc_html_e( 'Add Product', 'cartflush' ); ?></button>
								</div>
								<div class="cartflush-table-wrap">
									<table class="widefat striped cartflush-rule-table">
										<thead>
											<tr>
												<th><?php esc_html_e( 'Product ID', 'cartflush' ); ?></th>
											</tr>
										</thead>
										<tbody data-cartflush-rows="excluded-product">
											<?php $this->render_excluded_product_rows( $rules['excluded_products'] ); ?>
										</tbody>
									</table>
								</div>
							</div>

							<div class="cartflush-editor-panel">
								<div class="cartflush-editor-panel__header">
									<div>
										<h3><?php esc_html_e( 'Excluded Categories', 'cartflush' ); ?></h3>
										<p><?php esc_html_e( 'If a cart contains products from these categories, CartFlush will leave the cart untouched.', 'cartflush' ); ?></p>
									</div>
									<button type="button" class="button button-secondary" data-cartflush-add-row="excluded-category"><?php esc_html_e( 'Add Category', 'cartflush' ); ?></button>
								</div>
								<div class="cartflush-table-wrap">
									<table class="widefat striped cartflush-rule-table">
										<thead>
											<tr>
												<th><?php esc_html_e( 'Category', 'cartflush' ); ?></th>
											</tr>
										</thead>
										<tbody data-cartflush-rows="excluded-category">
											<?php $this->render_excluded_category_rows( $rules['excluded_categories'], $categories ); ?>
										</tbody>
									</table>
								</div>
							</div>
						</div>

						<?php submit_button( __( 'Save Settings', 'cartflush' ) ); ?>
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
					<p><?php esc_html_e( 'You can still bulk import with type, key, and timeout_minutes columns when that is faster for your workflow.', 'cartflush' ); ?></p>
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
					<p><?php esc_html_e( 'Download the current timeout, rules, and exclusions as a JSON snapshot.', 'cartflush' ); ?></p>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<?php wp_nonce_field( 'cartflush_export_json' ); ?>
						<input type="hidden" name="action" value="cartflush_export_json">
						<?php submit_button( __( 'Export JSON', 'cartflush' ), 'secondary', 'submit', false ); ?>
					</form>
				</div>
			</div>

			<div class="cartflush-card cartflush-card--wide">
				<h2><?php esc_html_e( 'Saved Configuration Summary', 'cartflush' ); ?></h2>
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
						<h3><?php esc_html_e( 'Excluded Categories', 'cartflush' ); ?></h3>
						<p><?php echo esc_html( $this->format_simple_list( $rules['excluded_categories'] ) ); ?></p>
					</div>
				</div>
			</div>

			<script type="text/html" id="tmpl-cartflush-role-rule">
				<tr>
					<td><?php $this->render_select_field( 'cartflush_import_rules[role_rules][{{index}}][role]', '', $roles, __( 'Select a role', 'cartflush' ) ); ?></td>
					<td><input type="number" min="1" step="1" class="small-text" name="cartflush_import_rules[role_rules][{{index}}][timeout]" value=""></td>
				</tr>
			</script>

			<script type="text/html" id="tmpl-cartflush-category-rule">
				<tr>
					<td><?php $this->render_select_field( 'cartflush_import_rules[category_rules][{{index}}][slug]', '', $categories, __( 'Select a category', 'cartflush' ) ); ?></td>
					<td><input type="number" min="1" step="1" class="small-text" name="cartflush_import_rules[category_rules][{{index}}][timeout]" value=""></td>
				</tr>
			</script>

			<script type="text/html" id="tmpl-cartflush-excluded-product">
				<tr>
					<td><input type="number" min="1" step="1" class="small-text" name="cartflush_import_rules[excluded_products][{{index}}][product_id]" value=""></td>
				</tr>
			</script>

			<script type="text/html" id="tmpl-cartflush-excluded-category">
				<tr>
					<td><?php $this->render_select_field( 'cartflush_import_rules[excluded_categories][{{index}}][slug]', '', $categories, __( 'Select a category', 'cartflush' ) ); ?></td>
				</tr>
			</script>
		</div>
		<?php
	}

	/**
	 * Render role rows.
	 *
	 * @param array<string, int> $role_rules Role rules.
	 * @param array<string, string> $roles Role options.
	 * @return void
	 */
	private function render_role_rule_rows( $role_rules, $roles ) {
		if ( empty( $role_rules ) ) {
			echo $this->get_role_rule_row_html( 0, '', '', $roles ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			return;
		}

		$index = 0;
		foreach ( $role_rules as $role => $timeout ) {
			echo $this->get_role_rule_row_html( $index, $role, $timeout, $roles ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			++$index;
		}
	}

	/**
	 * Render category rows.
	 *
	 * @param array<string, int> $category_rules Category rules.
	 * @param array<string, string> $categories Category options.
	 * @return void
	 */
	private function render_category_rule_rows( $category_rules, $categories ) {
		if ( empty( $category_rules ) ) {
			echo $this->get_category_rule_row_html( 0, '', '', $categories ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			return;
		}

		$index = 0;
		foreach ( $category_rules as $slug => $timeout ) {
			echo $this->get_category_rule_row_html( $index, $slug, $timeout, $categories ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			++$index;
		}
	}

	/**
	 * Render excluded product rows.
	 *
	 * @param array<int> $excluded_products Excluded product IDs.
	 * @return void
	 */
	private function render_excluded_product_rows( $excluded_products ) {
		if ( empty( $excluded_products ) ) {
			echo $this->get_excluded_product_row_html( 0, '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			return;
		}

		foreach ( array_values( $excluded_products ) as $index => $product_id ) {
			echo $this->get_excluded_product_row_html( $index, $product_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}

	/**
	 * Render excluded category rows.
	 *
	 * @param array<int, string> $excluded_categories Excluded category slugs.
	 * @param array<string, string> $categories Category options.
	 * @return void
	 */
	private function render_excluded_category_rows( $excluded_categories, $categories ) {
		if ( empty( $excluded_categories ) ) {
			echo $this->get_excluded_category_row_html( 0, '', $categories ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			return;
		}

		foreach ( array_values( $excluded_categories ) as $index => $slug ) {
			echo $this->get_excluded_category_row_html( $index, $slug, $categories ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}

	/**
	 * Build a role rule row.
	 *
	 * @param int|string $index Row index.
	 * @param string     $role Selected role.
	 * @param int|string $timeout Timeout in minutes.
	 * @param array<string, string> $roles Available roles.
	 * @return string
	 */
	private function get_role_rule_row_html( $index, $role, $timeout, $roles ) {
		ob_start();
		?>
		<tr>
			<td><?php $this->render_select_field( 'cartflush_import_rules[role_rules][' . $index . '][role]', (string) $role, $roles, __( 'Select a role', 'cartflush' ) ); ?></td>
			<td><input type="number" min="1" step="1" class="small-text" name="<?php echo esc_attr( 'cartflush_import_rules[role_rules][' . $index . '][timeout]' ); ?>" value="<?php echo esc_attr( $timeout ); ?>"></td>
		</tr>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Build a category rule row.
	 *
	 * @param int|string $index Row index.
	 * @param string     $slug Selected category slug.
	 * @param int|string $timeout Timeout in minutes.
	 * @param array<string, string> $categories Available categories.
	 * @return string
	 */
	private function get_category_rule_row_html( $index, $slug, $timeout, $categories ) {
		ob_start();
		?>
		<tr>
			<td><?php $this->render_select_field( 'cartflush_import_rules[category_rules][' . $index . '][slug]', (string) $slug, $categories, __( 'Select a category', 'cartflush' ) ); ?></td>
			<td><input type="number" min="1" step="1" class="small-text" name="<?php echo esc_attr( 'cartflush_import_rules[category_rules][' . $index . '][timeout]' ); ?>" value="<?php echo esc_attr( $timeout ); ?>"></td>
		</tr>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Build an excluded product row.
	 *
	 * @param int|string $index Row index.
	 * @param int|string $product_id Product ID.
	 * @return string
	 */
	private function get_excluded_product_row_html( $index, $product_id ) {
		ob_start();
		?>
		<tr>
			<td><input type="number" min="1" step="1" class="small-text" name="<?php echo esc_attr( 'cartflush_import_rules[excluded_products][' . $index . '][product_id]' ); ?>" value="<?php echo esc_attr( $product_id ); ?>"></td>
		</tr>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Build an excluded category row.
	 *
	 * @param int|string $index Row index.
	 * @param string     $slug Selected category slug.
	 * @param array<string, string> $categories Available categories.
	 * @return string
	 */
	private function get_excluded_category_row_html( $index, $slug, $categories ) {
		ob_start();
		?>
		<tr>
			<td><?php $this->render_select_field( 'cartflush_import_rules[excluded_categories][' . $index . '][slug]', (string) $slug, $categories, __( 'Select a category', 'cartflush' ) ); ?></td>
		</tr>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Render a select field with a placeholder.
	 *
	 * @param string               $name Field name.
	 * @param string               $selected Selected value.
	 * @param array<string, string> $options Available options.
	 * @param string               $placeholder Placeholder label.
	 * @return void
	 */
	private function render_select_field( $name, $selected, $options, $placeholder ) {
		$options = is_array( $options ) ? $options : [];

		if ( $selected && ! isset( $options[ $selected ] ) ) {
			$options = [ $selected => $selected ] + $options;
		}
		?>
		<select class="regular-text" name="<?php echo esc_attr( $name ); ?>">
			<option value=""><?php echo esc_html( $placeholder ); ?></option>
			<?php foreach ( $options as $value => $label ) : ?>
				<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $selected, (string) $value ); ?>><?php echo esc_html( $label ); ?></option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	/**
	 * Prepare row-based settings data for storage.
	 *
	 * @param array<string, mixed> $value Raw settings value.
	 * @return array<string, mixed>
	 */
	private function prepare_rules_for_storage( $value ) {
		$prepared = $value;

		if ( isset( $value['role_rules'] ) && $this->is_repeatable_row_collection( $value['role_rules'] ) ) {
			$prepared['role_rules'] = [];

			foreach ( $value['role_rules'] as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}

				$role    = isset( $row['role'] ) ? sanitize_key( $row['role'] ) : '';
				$timeout = isset( $row['timeout'] ) ? absint( $row['timeout'] ) : 0;

				if ( $role && $timeout > 0 ) {
					$prepared['role_rules'][ $role ] = $timeout;
				}
			}
		}

		if ( isset( $value['category_rules'] ) && $this->is_repeatable_row_collection( $value['category_rules'] ) ) {
			$prepared['category_rules'] = [];

			foreach ( $value['category_rules'] as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}

				$slug    = isset( $row['slug'] ) ? sanitize_title( $row['slug'] ) : '';
				$timeout = isset( $row['timeout'] ) ? absint( $row['timeout'] ) : 0;

				if ( $slug && $timeout > 0 ) {
					$prepared['category_rules'][ $slug ] = $timeout;
				}
			}
		}

		if ( isset( $value['excluded_products'] ) && is_array( $value['excluded_products'] ) ) {
			$prepared['excluded_products'] = [];

			foreach ( $value['excluded_products'] as $row ) {
				$product_id = is_array( $row ) && isset( $row['product_id'] ) ? absint( $row['product_id'] ) : absint( $row );

				if ( $product_id > 0 ) {
					$prepared['excluded_products'][] = $product_id;
				}
			}
		}

		if ( isset( $value['excluded_categories'] ) && is_array( $value['excluded_categories'] ) ) {
			$prepared['excluded_categories'] = [];

			foreach ( $value['excluded_categories'] as $row ) {
				$slug = is_array( $row ) && isset( $row['slug'] ) ? sanitize_title( $row['slug'] ) : sanitize_title( $row );

				if ( $slug ) {
					$prepared['excluded_categories'][] = $slug;
				}
			}
		}

		return $prepared;
	}

	/**
	 * Check whether a value contains repeatable row arrays.
	 *
	 * @param mixed $rows Rows value.
	 * @return bool
	 */
	private function is_repeatable_row_collection( $rows ) {
		if ( ! is_array( $rows ) || [] === $rows ) {
			return false;
		}

		foreach ( $rows as $row ) {
			return is_array( $row );
		}

		return false;
	}

	/**
	 * Get available role labels.
	 *
	 * @return array<string, string>
	 */
	private function get_role_options() {
		if ( ! function_exists( 'wp_roles' ) ) {
			return [];
		}

		$role_objects = wp_roles()->roles;
		$options      = [];

		foreach ( $role_objects as $role_key => $role_data ) {
			$options[ sanitize_key( $role_key ) ] = isset( $role_data['name'] ) ? $role_data['name'] : $role_key;
		}

		return $options;
	}

	/**
	 * Get available WooCommerce product categories.
	 *
	 * @return array<string, string>
	 */
	private function get_product_category_options() {
		$options = [];
		$terms   = get_terms(
			[
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
			]
		);

		if ( is_wp_error( $terms ) || ! is_array( $terms ) ) {
			return $options;
		}

		foreach ( $terms as $term ) {
			if ( isset( $term->slug, $term->name ) ) {
				$options[ $term->slug ] = $term->name . ' (' . $term->slug . ')';
			}
		}

		return $options;
	}

	/**
	 * Format key-value lists for display.
	 *
	 * @param array<string, int> $items Display items.
	 * @return string
	 */
	private function format_assoc_list( $items ) {
		if ( empty( $items ) ) {
			return __( 'None saved yet.', 'cartflush' );
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
			return __( 'None saved yet.', 'cartflush' );
		}

		return implode( ', ', array_map( 'strval', $items ) );
	}

	/**
	 * Ensure the current user can manage the plugin.
	 *
	 * @return void
	 */
	private function assert_admin_permissions() {
		if ( ! current_user_can( $this->get_required_capability() ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'cartflush' ) );
		}
	}

	/**
	 * Get the capability required to manage CartFlush.
	 *
	 * @return string
	 */
	private function get_required_capability() {
		return 'manage_woocommerce';
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
			admin_url( 'admin.php' )
		);

		wp_safe_redirect( $url );
		exit;
	}
}
