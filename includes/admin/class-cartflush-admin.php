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

	public function __construct( CartFlush_Rules $rules ) {
		$this->rules = $rules;
		add_action( 'admin_menu', [ $this, 'add_settings_page' ] );
		add_action( 'admin_init', [ $this, 'register_settings' ] );
		add_action( 'admin_init', [ $this, 'maybe_sync_settings_to_rules' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		add_action( 'admin_notices', [ $this, 'render_admin_notices' ] );
		add_action( 'admin_post_cartflush_import_json', [ $this, 'handle_json_import' ] );
		add_action( 'admin_post_cartflush_import_csv', [ $this, 'handle_csv_import' ] );
		add_action( 'admin_post_cartflush_export_json', [ $this, 'handle_json_export' ] );
		add_action( 'admin_post_cartflush_download_csv_sample', [ $this, 'handle_csv_sample_download' ] );
		add_filter( 'option_page_capability_cartflush_settings_group', [ $this, 'settings_capability' ] );
	}

	public function add_settings_page() {
		add_submenu_page( 'woocommerce', __( 'CartFlush Settings', 'cartflush' ), __( 'CartFlush', 'cartflush' ), $this->get_required_capability(), 'cartflush-settings', [ $this, 'settings_page_html' ] );
	}

	public function register_settings() {
		register_setting( 'cartflush_settings_group', 'cartflush_expiration_time', [ 'type' => 'integer', 'sanitize_callback' => 'absint', 'default' => 30 ] );
		register_setting( 'cartflush_settings_group', 'cartflush_customer_type_rules_text', [ 'type' => 'string', 'sanitize_callback' => 'sanitize_textarea_field', 'default' => '' ] );
		register_setting( 'cartflush_settings_group', 'cartflush_role_rules_text', [ 'type' => 'string', 'sanitize_callback' => 'sanitize_textarea_field', 'default' => '' ] );
		register_setting( 'cartflush_settings_group', 'cartflush_product_rules_text', [ 'type' => 'string', 'sanitize_callback' => 'sanitize_textarea_field', 'default' => '' ] );
		register_setting( 'cartflush_settings_group', 'cartflush_category_rules_text', [ 'type' => 'string', 'sanitize_callback' => 'sanitize_textarea_field', 'default' => '' ] );
		register_setting( 'cartflush_settings_group', 'cartflush_tag_rules_text', [ 'type' => 'string', 'sanitize_callback' => 'sanitize_textarea_field', 'default' => '' ] );
		register_setting( 'cartflush_settings_group', 'cartflush_excluded_roles_text', [ 'type' => 'string', 'sanitize_callback' => 'sanitize_textarea_field', 'default' => '' ] );
		register_setting( 'cartflush_settings_group', 'cartflush_excluded_products_text', [ 'type' => 'string', 'sanitize_callback' => 'sanitize_textarea_field', 'default' => '' ] );
		register_setting( 'cartflush_settings_group', 'cartflush_excluded_categories_text', [ 'type' => 'string', 'sanitize_callback' => 'sanitize_textarea_field', 'default' => '' ] );
		register_setting( 'cartflush_settings_group', 'cartflush_excluded_tags_text', [ 'type' => 'string', 'sanitize_callback' => 'sanitize_textarea_field', 'default' => '' ] );
		add_settings_section( 'cartflush_main', __( 'Timeout Settings', 'cartflush' ), [ $this, 'render_main_section' ], 'cartflush-settings' );
		add_settings_field( 'cartflush_expiration_time', __( 'Default cart expiration', 'cartflush' ), [ $this, 'render_expiration_field' ], 'cartflush-settings', 'cartflush_main' );
	}

	public function sanitize_rules_option( $value ) {
		$this->store_duplicate_rule_warnings( is_array( $value ) ? $value : [] );
		return $this->rules->normalize_rules_data( is_array( $value ) ? $this->prepare_rules_for_storage( $value ) : $value );
	}

	public function settings_capability() {
		return $this->get_required_capability();
	}

	public function enqueue_assets( $hook ) {
		if ( 'woocommerce_page_cartflush-settings' !== $hook ) {
			return;
		}

		wp_enqueue_style( 'cartflush-admin', CARTFLUSH_URL . 'assets/css/admin.css', [], CARTFLUSH_VERSION );
	}

	public function render_main_section() {
		echo '<p>' . esc_html__( 'Set the fallback timeout first, then add rule-based overrides and exclusions below.', 'cartflush' ) . '</p>';
	}

	public function render_expiration_field() {
		$value = (int) get_option( 'cartflush_expiration_time', 30 );
		echo '<label class="cartflush-field"><input type="number" min="1" step="1" class="small-text" name="cartflush_expiration_time" value="' . esc_attr( $value ) . '"> <span>' . esc_html__( 'minutes', 'cartflush' ) . '</span></label>';
		echo '<p class="description">' . esc_html__( 'Fallback timeout when no custom timeout rule matches.', 'cartflush' ) . '</p>';
	}

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
		$warnings = get_transient( 'cartflush_admin_duplicate_warnings_' . get_current_user_id() );
		if ( is_array( $warnings ) && ! empty( $warnings ) ) {
			delete_transient( 'cartflush_admin_duplicate_warnings_' . get_current_user_id() );
			echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html__( 'Some duplicate rules were detected. CartFlush kept the last saved value for each duplicate key.', 'cartflush' ) . '</p><ul>';
			foreach ( $warnings as $warning ) {
				echo '<li>' . esc_html( $warning ) . '</li>';
			}
			echo '</ul></div>';
		}
	}

	public function handle_json_import() {
		$this->assert_admin_permissions();
		check_admin_referer( 'cartflush_import_json' );
		if ( empty( $_FILES['cartflush_json_file']['tmp_name'] ) ) {
			$this->redirect_with_message( '', __( 'Please choose a JSON file to import.', 'cartflush' ) );
		}
		$raw  = file_get_contents( $_FILES['cartflush_json_file']['tmp_name'] ); // phpcs:ignore
		$data = json_decode( $raw, true );
		if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $data ) ) {
			$this->redirect_with_message( '', __( 'The uploaded JSON file is invalid.', 'cartflush' ) );
		}
		$default_timeout = isset( $data['cartflush_expiration_time'] ) ? absint( $data['cartflush_expiration_time'] ) : get_option( 'cartflush_expiration_time', 30 );
		$rules           = isset( $data['import_rules'] ) ? $data['import_rules'] : $data;
		update_option( 'cartflush_expiration_time', max( 1, $default_timeout ) );
		update_option( CartFlush_Rules::OPTION_NAME, $this->rules->normalize_rules_data( $rules ) );
		$this->redirect_with_message( __( 'JSON settings imported successfully.', 'cartflush' ) );
	}

	public function handle_csv_import() {
		$this->assert_admin_permissions();
		check_admin_referer( 'cartflush_import_csv' );
		if ( empty( $_FILES['cartflush_csv_file']['tmp_name'] ) ) {
			$this->redirect_with_message( '', __( 'Please choose a CSV file to import.', 'cartflush' ) );
		}
		$handle = fopen( $_FILES['cartflush_csv_file']['tmp_name'], 'r' ); // phpcs:ignore
		if ( ! $handle ) {
			$this->redirect_with_message( '', __( 'The uploaded CSV file could not be read.', 'cartflush' ) );
		}
		$header = fgetcsv( $handle );
		if ( ! is_array( $header ) ) {
			fclose( $handle ); // phpcs:ignore
			$this->redirect_with_message( '', __( 'The CSV file is empty.', 'cartflush' ) );
		}
		$header = array_map( 'sanitize_key', $header );
		$rules  = $this->rules->get_rules_option();
		while ( false !== ( $row = fgetcsv( $handle ) ) ) {
			$row = array_combine( $header, array_pad( $row, count( $header ), '' ) );
			if ( ! is_array( $row ) ) {
				continue;
			}
			$type    = isset( $row['type'] ) ? sanitize_key( $row['type'] ) : '';
			$key     = isset( $row['key'] ) ? trim( (string) $row['key'] ) : '';
			$timeout = isset( $row['timeout_minutes'] ) ? absint( $row['timeout_minutes'] ) : 0;
			if ( ! $type || '' === $key ) {
				continue;
			}
			switch ( $type ) {
				case 'customer_type':
					if ( in_array( sanitize_key( $key ), [ 'guest', 'logged_in' ], true ) && $timeout > 0 ) {
						$rules['customer_type_rules'][ sanitize_key( $key ) ] = $timeout;
					}
					break;
				case 'role':
					if ( $timeout > 0 ) {
						$rules['role_rules'][ sanitize_key( $key ) ] = $timeout;
					}
					break;
				case 'product_rule':
					if ( absint( $key ) > 0 && $timeout > 0 ) {
						$rules['product_rules'][ absint( $key ) ] = $timeout;
					}
					break;
				case 'category':
					if ( $timeout > 0 ) {
						$rules['category_rules'][ sanitize_title( $key ) ] = $timeout;
					}
					break;
				case 'tag':
					if ( $timeout > 0 ) {
						$rules['tag_rules'][ sanitize_title( $key ) ] = $timeout;
					}
					break;
				case 'excluded_role':
					$rules['excluded_roles'][] = sanitize_key( $key );
					break;
				case 'excluded_product':
				case 'product':
					if ( absint( $key ) > 0 ) {
						$rules['excluded_products'][] = absint( $key );
					}
					break;
				case 'excluded_category':
					$rules['excluded_categories'][] = sanitize_title( $key );
					break;
				case 'excluded_tag':
					$rules['excluded_tags'][] = sanitize_title( $key );
					break;
			}
		}
		fclose( $handle ); // phpcs:ignore
		update_option( CartFlush_Rules::OPTION_NAME, $this->rules->normalize_rules_data( $rules ) );
		$this->redirect_with_message( __( 'CSV rules imported successfully.', 'cartflush' ) );
	}

	public function handle_csv_sample_download() {
		$this->assert_admin_permissions();
		check_admin_referer( 'cartflush_download_csv_sample' );
		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=cartflush-sample-rules.csv' );

		$handle = fopen( 'php://output', 'w' );

		if ( ! $handle ) {
			exit;
		}

		fputcsv( $handle, [ 'type', 'key', 'timeout_minutes' ] );
		fputcsv( $handle, [ 'customer_type', 'guest', '20' ] );
		fputcsv( $handle, [ 'role', 'customer', '30' ] );
		fputcsv( $handle, [ 'product_rule', '321', '10' ] );
		fputcsv( $handle, [ 'category', 'flash-sale', '15' ] );
		fputcsv( $handle, [ 'tag', 'seasonal', '25' ] );
		fputcsv( $handle, [ 'excluded_role', 'wholesale_customer', '' ] );
		fputcsv( $handle, [ 'excluded_product', '123', '' ] );
		fputcsv( $handle, [ 'excluded_category', 'high-ticket', '' ] );
		fputcsv( $handle, [ 'excluded_tag', 'fragile', '' ] );
		fclose( $handle );
		exit;
	}

	public function handle_json_export() {
		$this->assert_admin_permissions();
		check_admin_referer( 'cartflush_export_json' );
		$payload = [ 'plugin' => 'CartFlush', 'version' => CARTFLUSH_VERSION, 'exported_at' => gmdate( 'c' ), 'cartflush_expiration_time' => (int) get_option( 'cartflush_expiration_time', 30 ), 'import_rules' => $this->rules->get_rules_option() ];
		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=cartflush-settings-' . gmdate( 'Y-m-d' ) . '.json' );
		echo wp_json_encode( $payload, JSON_PRETTY_PRINT );
		exit;
	}

	public function settings_page_html() {
		$rules          = $this->rules->get_rules_option();
		?>
		<div class="wrap cartflush-admin">
			<div class="cartflush-shell">
				<section class="cartflush-hero">
					<div class="cartflush-hero__content">
						<span class="cartflush-eyebrow"><?php esc_html_e( 'WooCommerce Settings', 'cartflush' ); ?></span>
						<h1><?php esc_html_e( 'CartFlush', 'cartflush' ); ?></h1>
						<p><?php esc_html_e( 'Configure automatic cart clearing with layered timeout rules, product conditions, and exclusions from one settings page.', 'cartflush' ); ?></p>
						<div class="cartflush-hero__stats">
							<?php foreach ( $this->get_stats( $rules ) as $stat ) : ?>
								<div class="cartflush-stat">
									<span class="cartflush-stat__label"><?php echo esc_html( $stat['label'] ); ?></span>
									<strong><?php echo esc_html( $stat['value'] ); ?></strong>
									<span class="cartflush-stat__meta"><?php echo esc_html( $stat['meta'] ); ?></span>
								</div>
							<?php endforeach; ?>
						</div>
					</div>
					<div class="cartflush-hero__rail">
						<div class="cartflush-rail-card">
							<span class="cartflush-chip cartflush-chip--blue"><?php esc_html_e( 'How It Works', 'cartflush' ); ?></span>
							<h3><?php esc_html_e( 'Build rules directly from this screen', 'cartflush' ); ?></h3>
							<p><?php esc_html_e( 'Use a default timeout, layer rule-based overrides, then add exclusions for carts that should never be cleared automatically.', 'cartflush' ); ?></p>
							<ul class="cartflush-bullet-list">
								<li><?php esc_html_e( 'Customer type and role timeouts', 'cartflush' ); ?></li>
								<li><?php esc_html_e( 'Product, category, and tag rules', 'cartflush' ); ?></li>
								<li><?php esc_html_e( 'Role, product, category, and tag exclusions', 'cartflush' ); ?></li>
							</ul>
						</div>
					</div>
				</section>

				<div class="cartflush-layout">
					<main class="cartflush-main">
						<form method="post" action="options.php" class="cartflush-settings-form">
							<?php settings_fields( 'cartflush_settings_group' ); ?>
							<section class="cartflush-panel">
								<div class="cartflush-panel__intro">
									<div>
										<span class="cartflush-chip"><?php esc_html_e( 'General', 'cartflush' ); ?></span>
										<h2><?php esc_html_e( 'General Settings', 'cartflush' ); ?></h2>
										<p><?php esc_html_e( 'Choose the fallback timeout used when no more specific rule applies.', 'cartflush' ); ?></p>
									</div>
								</div>
								<?php $this->render_expiration_field(); ?>
							</section>

							<section class="cartflush-panel">
								<div class="cartflush-panel__intro">
									<div>
										<span class="cartflush-chip"><?php esc_html_e( 'Timeout Rules', 'cartflush' ); ?></span>
										<h2><?php esc_html_e( 'Rule-Based Timeouts', 'cartflush' ); ?></h2>
										<p><?php esc_html_e( 'Apply shorter or longer cart expiration windows based on the customer or the products in the cart.', 'cartflush' ); ?></p>
									</div>
								</div>
								<div class="cartflush-editor-grid">
									<?php
									$this->render_rule_textarea(
										__( 'Customer Type Rules', 'cartflush' ),
										'cartflush_customer_type_rules_text',
										__( 'One rule per line using customer_type|minutes.', 'cartflush' ),
										"guest|20\nlogged_in|45",
										$this->format_timeout_lines( $rules['customer_type_rules'] )
									);
									$this->render_rule_textarea(
										__( 'Role Rules', 'cartflush' ),
										'cartflush_role_rules_text',
										__( 'One rule per line using role|minutes.', 'cartflush' ),
										"customer|30\nsubscriber|60",
										$this->format_timeout_lines( $rules['role_rules'] )
									);
									$this->render_rule_textarea(
										__( 'Product Rules', 'cartflush' ),
										'cartflush_product_rules_text',
										__( 'One rule per line using product_id|minutes.', 'cartflush' ),
										"123|15\n456|40",
										$this->format_timeout_lines( $rules['product_rules'] )
									);
									$this->render_rule_textarea(
										__( 'Category Rules', 'cartflush' ),
										'cartflush_category_rules_text',
										__( 'One rule per line using category_slug|minutes.', 'cartflush' ),
										"flash-sale|15\nhigh-ticket|90",
										$this->format_timeout_lines( $rules['category_rules'] )
									);
									$this->render_rule_textarea(
										__( 'Tag Rules', 'cartflush' ),
										'cartflush_tag_rules_text',
										__( 'One rule per line using tag_slug|minutes.', 'cartflush' ),
										"seasonal|20\nclearance|10",
										$this->format_timeout_lines( $rules['tag_rules'] )
									);
									?>
								</div>
							</section>

							<section class="cartflush-panel">
								<div class="cartflush-panel__intro">
									<div>
										<span class="cartflush-chip"><?php esc_html_e( 'Exclusions', 'cartflush' ); ?></span>
										<h2><?php esc_html_e( 'Exclusion Rules', 'cartflush' ); ?></h2>
										<p><?php esc_html_e( 'Skip the auto-clear behavior completely when a cart matches one of the following conditions.', 'cartflush' ); ?></p>
									</div>
								</div>
								<div class="cartflush-editor-grid">
									<?php
									$this->render_rule_textarea(
										__( 'Excluded Roles', 'cartflush' ),
										'cartflush_excluded_roles_text',
										__( 'One role per line.', 'cartflush' ),
										"shop_manager\nwholesale_customer",
										$this->format_list_lines( $rules['excluded_roles'] )
									);
									$this->render_rule_textarea(
										__( 'Excluded Products', 'cartflush' ),
										'cartflush_excluded_products_text',
										__( 'One product ID per line.', 'cartflush' ),
										"123\n456",
										$this->format_list_lines( $rules['excluded_products'] )
									);
									$this->render_rule_textarea(
										__( 'Excluded Categories', 'cartflush' ),
										'cartflush_excluded_categories_text',
										__( 'One category slug per line.', 'cartflush' ),
										"high-ticket\nsubscriptions",
										$this->format_list_lines( $rules['excluded_categories'] )
									);
									$this->render_rule_textarea(
										__( 'Excluded Tags', 'cartflush' ),
										'cartflush_excluded_tags_text',
										__( 'One tag slug per line.', 'cartflush' ),
										"fragile\nmade-to-order",
										$this->format_list_lines( $rules['excluded_tags'] )
									);
									?>
								</div>
							</section>

							<div class="cartflush-savebar">
								<div>
									<strong><?php esc_html_e( 'Save changes', 'cartflush' ); ?></strong>
									<p><?php esc_html_e( 'Your new rules will be used immediately for future inactivity checks.', 'cartflush' ); ?></p>
								</div>
								<?php submit_button( __( 'Save Settings', 'cartflush' ), 'primary cartflush-savebar__button', 'submit', false ); ?>
							</div>
						</form>
					</main>

					<aside class="cartflush-sidebar">
						<div class="cartflush-sidepanel">
							<div class="cartflush-sidepanel__intro">
								<span class="cartflush-chip"><?php esc_html_e( 'Tools', 'cartflush' ); ?></span>
								<h2><?php esc_html_e( 'Import & Export', 'cartflush' ); ?></h2>
								<p><?php esc_html_e( 'Bring settings in from another store or create a backup before making large rule changes.', 'cartflush' ); ?></p>
							</div>

								<div class="cartflush-sidecard">
									<div class="cartflush-sidecard__header">
										<span class="cartflush-chip"><?php esc_html_e( 'CSV Import', 'cartflush' ); ?></span>
										<strong><?php esc_html_e( 'Bulk Rules', 'cartflush' ); ?></strong>
									</div>
								<p><?php esc_html_e( 'Upload a CSV using the headers type, key, timeout_minutes. Exclusion rows can keep timeout_minutes empty.', 'cartflush' ); ?></p>
								<div class="cartflush-code-list">
									<code>customer_type</code>
									<code>role</code>
									<code>product_rule</code>
									<code>category</code>
									<code>tag</code>
								</div>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
									<?php wp_nonce_field( 'cartflush_import_csv' ); ?>
									<input type="hidden" name="action" value="cartflush_import_csv">
									<label class="cartflush-upload-field">
										<span><?php esc_html_e( 'Choose CSV file', 'cartflush' ); ?></span>
										<input type="file" name="cartflush_csv_file" accept=".csv,text/csv" required>
									</label>
									<div class="cartflush-tool-actions">
										<?php submit_button( __( 'Import CSV', 'cartflush' ), 'secondary', 'submit', false ); ?>
										<a class="button button-secondary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=cartflush_download_csv_sample' ), 'cartflush_download_csv_sample' ) ); ?>"><?php esc_html_e( 'Download Sample', 'cartflush' ); ?></a>
									</div>
								</form>
							</div>

							<div class="cartflush-sidecard">
								<div class="cartflush-sidecard__header">
									<span class="cartflush-chip"><?php esc_html_e( 'JSON Import', 'cartflush' ); ?></span>
									<strong><?php esc_html_e( 'Move Settings', 'cartflush' ); ?></strong>
								</div>
								<p><?php esc_html_e( 'Import a full CartFlush configuration from another WooCommerce store.', 'cartflush' ); ?></p>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
									<?php wp_nonce_field( 'cartflush_import_json' ); ?>
									<input type="hidden" name="action" value="cartflush_import_json">
									<label class="cartflush-upload-field">
										<span><?php esc_html_e( 'Choose JSON file', 'cartflush' ); ?></span>
										<input type="file" name="cartflush_json_file" accept=".json,application/json" required>
									</label>
									<?php submit_button( __( 'Import JSON', 'cartflush' ), 'secondary', 'submit', false ); ?>
								</form>
							</div>

							<div class="cartflush-sidecard">
								<div class="cartflush-sidecard__header">
									<span class="cartflush-chip"><?php esc_html_e( 'JSON Export', 'cartflush' ); ?></span>
									<strong><?php esc_html_e( 'Backup', 'cartflush' ); ?></strong>
								</div>
								<p><?php esc_html_e( 'Download the current timeout and every saved rule group as a portable JSON backup.', 'cartflush' ); ?></p>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
									<?php wp_nonce_field( 'cartflush_export_json' ); ?>
									<input type="hidden" name="action" value="cartflush_export_json">
									<?php submit_button( __( 'Export JSON', 'cartflush' ), 'secondary', 'submit', false ); ?>
								</form>
							</div>
						</div>
					</aside>
				</div>
				
			</div>

		</div>
		<?php
	}

	private function render_rule_textarea( $title, $name, $description, $placeholder, $value ) {
		?>
		<div class="cartflush-editor-card">
			<h3><?php echo esc_html( $title ); ?></h3>
			<p><?php echo esc_html( $description ); ?></p>
			<textarea name="<?php echo esc_attr( $name ); ?>" rows="6" placeholder="<?php echo esc_attr( $placeholder ); ?>"><?php echo esc_textarea( $value ); ?></textarea>
			<div class="cartflush-editor-card__sample">
				<span><?php esc_html_e( 'Sample', 'cartflush' ); ?></span>
				<pre><?php echo esc_html( $placeholder ); ?></pre>
			</div>
		</div>
		<?php
	}

	private function render_rule_card( $title, $description, $type, $button_label, $headers, $count, $hint, $renderer ) {
		?>
		<div class="cartflush-rule-card">
			<div class="cartflush-rule-card__header">
				<div class="cartflush-rule-card__title">
					<div class="cartflush-rule-card__meta">
						<span class="cartflush-rule-card__count"><?php echo esc_html( absint( $count ) ); ?></span>
						<span class="cartflush-rule-card__label"><?php esc_html_e( 'saved', 'cartflush' ); ?></span>
					</div>
					<h3><?php echo esc_html( $title ); ?></h3>
					<p><?php echo esc_html( $description ); ?></p>
				</div>
				<button type="button" class="button cartflush-action" data-cartflush-add-row="<?php echo esc_attr( $type ); ?>"><?php echo esc_html( $button_label ); ?></button>
			</div>
			<div class="cartflush-table-wrap">
				<table class="widefat striped cartflush-rule-table">
					<thead><tr><?php foreach ( $headers as $index => $header ) : ?><th class="<?php echo esc_attr( $index === count( $headers ) - 1 ? 'cartflush-rule-table__action' : '' ); ?>"><?php echo esc_html( $header ); ?></th><?php endforeach; ?></tr></thead>
					<tbody data-cartflush-rows="<?php echo esc_attr( $type ); ?>"><?php call_user_func( $renderer ); ?></tbody>
				</table>
			</div>
			<p class="cartflush-rule-card__hint"><?php echo esc_html( $hint ); ?></p>
		</div>
		<?php
	}

	private function render_templates( $roles, $customer_types, $categories, $tags ) {
		?>
		<script type="text/html" id="tmpl-cartflush-customer-type"><tr><td><?php $this->render_select_field( 'cartflush_import_rules[customer_type_rules][{{index}}][type]', '', $customer_types, __( 'Select customer type', 'cartflush' ) ); ?></td><td><input type="number" min="1" step="1" class="small-text" name="cartflush_import_rules[customer_type_rules][{{index}}][timeout]" value=""></td><td class="cartflush-row-action"><?php $this->render_remove_button(); ?></td></tr></script>
		<script type="text/html" id="tmpl-cartflush-role-rule"><tr><td><?php $this->render_select_field( 'cartflush_import_rules[role_rules][{{index}}][role]', '', $roles, __( 'Select a role', 'cartflush' ) ); ?></td><td><input type="number" min="1" step="1" class="small-text" name="cartflush_import_rules[role_rules][{{index}}][timeout]" value=""></td><td class="cartflush-row-action"><?php $this->render_remove_button(); ?></td></tr></script>
		<script type="text/html" id="tmpl-cartflush-product-rule"><tr><td><input type="number" min="1" step="1" class="small-text" name="cartflush_import_rules[product_rules][{{index}}][product_id]" value=""></td><td><input type="number" min="1" step="1" class="small-text" name="cartflush_import_rules[product_rules][{{index}}][timeout]" value=""></td><td class="cartflush-row-action"><?php $this->render_remove_button(); ?></td></tr></script>
		<script type="text/html" id="tmpl-cartflush-category-rule"><tr><td><?php $this->render_select_field( 'cartflush_import_rules[category_rules][{{index}}][slug]', '', $categories, __( 'Select a category', 'cartflush' ) ); ?></td><td><input type="number" min="1" step="1" class="small-text" name="cartflush_import_rules[category_rules][{{index}}][timeout]" value=""></td><td class="cartflush-row-action"><?php $this->render_remove_button(); ?></td></tr></script>
		<script type="text/html" id="tmpl-cartflush-tag-rule"><tr><td><?php $this->render_select_field( 'cartflush_import_rules[tag_rules][{{index}}][slug]', '', $tags, __( 'Select a tag', 'cartflush' ) ); ?></td><td><input type="number" min="1" step="1" class="small-text" name="cartflush_import_rules[tag_rules][{{index}}][timeout]" value=""></td><td class="cartflush-row-action"><?php $this->render_remove_button(); ?></td></tr></script>
		<script type="text/html" id="tmpl-cartflush-excluded-role"><tr><td><?php $this->render_select_field( 'cartflush_import_rules[excluded_roles][{{index}}][role]', '', $roles, __( 'Select a role', 'cartflush' ) ); ?></td><td class="cartflush-row-action"><?php $this->render_remove_button(); ?></td></tr></script>
		<script type="text/html" id="tmpl-cartflush-excluded-product"><tr><td><input type="number" min="1" step="1" class="small-text" name="cartflush_import_rules[excluded_products][{{index}}][product_id]" value=""></td><td class="cartflush-row-action"><?php $this->render_remove_button(); ?></td></tr></script>
		<script type="text/html" id="tmpl-cartflush-excluded-category"><tr><td><?php $this->render_select_field( 'cartflush_import_rules[excluded_categories][{{index}}][slug]', '', $categories, __( 'Select a category', 'cartflush' ) ); ?></td><td class="cartflush-row-action"><?php $this->render_remove_button(); ?></td></tr></script>
		<script type="text/html" id="tmpl-cartflush-excluded-tag"><tr><td><?php $this->render_select_field( 'cartflush_import_rules[excluded_tags][{{index}}][slug]', '', $tags, __( 'Select a tag', 'cartflush' ) ); ?></td><td class="cartflush-row-action"><?php $this->render_remove_button(); ?></td></tr></script>
		<?php
	}

	private function render_customer_type_rows( $items, $options ) {
		if ( empty( $items ) ) { echo $this->get_select_timeout_row( 'customer_type_rules', 0, 'type', '', '', $options, __( 'Select customer type', 'cartflush' ) ); return; } // phpcs:ignore
		$index = 0; foreach ( $items as $key => $timeout ) { echo $this->get_select_timeout_row( 'customer_type_rules', $index, 'type', $key, $timeout, $options, __( 'Select customer type', 'cartflush' ) ); ++$index; } // phpcs:ignore
	}

	private function render_map_timeout_rows( $group, $field, $items, $options, $placeholder ) {
		if ( empty( $items ) ) { echo $this->get_select_timeout_row( $group, 0, $field, '', '', $options, $placeholder ); return; } // phpcs:ignore
		$index = 0; foreach ( $items as $key => $timeout ) { echo $this->get_select_timeout_row( $group, $index, $field, $key, $timeout, $options, $placeholder ); ++$index; } // phpcs:ignore
	}

	private function render_product_timeout_rows( $group, $items ) {
		if ( empty( $items ) ) { echo $this->get_number_timeout_row( $group, 0, '', '' ); return; } // phpcs:ignore
		$index = 0; foreach ( $items as $key => $timeout ) { echo $this->get_number_timeout_row( $group, $index, $key, $timeout ); ++$index; } // phpcs:ignore
	}

	private function render_simple_select_rows( $group, $field, $items, $options, $placeholder ) {
		if ( empty( $items ) ) { echo $this->get_simple_select_row( $group, 0, $field, '', $options, $placeholder ); return; } // phpcs:ignore
		foreach ( array_values( $items ) as $index => $item ) { echo $this->get_simple_select_row( $group, $index, $field, $item, $options, $placeholder ); } // phpcs:ignore
	}

	private function render_simple_number_rows( $group, $field, $items ) {
		if ( empty( $items ) ) { echo $this->get_simple_number_row( $group, 0, $field, '' ); return; } // phpcs:ignore
		foreach ( array_values( $items ) as $index => $item ) { echo $this->get_simple_number_row( $group, $index, $field, $item ); } // phpcs:ignore
	}

	private function get_select_timeout_row( $group, $index, $field, $value, $timeout, $options, $placeholder ) {
		ob_start(); ?><tr><td><?php $this->render_select_field( 'cartflush_import_rules[' . $group . '][' . $index . '][' . $field . ']', (string) $value, $options, $placeholder ); ?></td><td><input type="number" min="1" step="1" class="small-text" name="<?php echo esc_attr( 'cartflush_import_rules[' . $group . '][' . $index . '][timeout]' ); ?>" value="<?php echo esc_attr( $timeout ); ?>"></td><td class="cartflush-row-action"><?php $this->render_remove_button(); ?></td></tr><?php return (string) ob_get_clean();
	}

	private function get_number_timeout_row( $group, $index, $value, $timeout ) {
		ob_start(); ?><tr><td><input type="number" min="1" step="1" class="small-text" name="<?php echo esc_attr( 'cartflush_import_rules[' . $group . '][' . $index . '][product_id]' ); ?>" value="<?php echo esc_attr( $value ); ?>"></td><td><input type="number" min="1" step="1" class="small-text" name="<?php echo esc_attr( 'cartflush_import_rules[' . $group . '][' . $index . '][timeout]' ); ?>" value="<?php echo esc_attr( $timeout ); ?>"></td><td class="cartflush-row-action"><?php $this->render_remove_button(); ?></td></tr><?php return (string) ob_get_clean();
	}

	private function get_simple_select_row( $group, $index, $field, $value, $options, $placeholder ) {
		ob_start(); ?><tr><td><?php $this->render_select_field( 'cartflush_import_rules[' . $group . '][' . $index . '][' . $field . ']', (string) $value, $options, $placeholder ); ?></td><td class="cartflush-row-action"><?php $this->render_remove_button(); ?></td></tr><?php return (string) ob_get_clean();
	}

	private function get_simple_number_row( $group, $index, $field, $value ) {
		ob_start(); ?><tr><td><input type="number" min="1" step="1" class="small-text" name="<?php echo esc_attr( 'cartflush_import_rules[' . $group . '][' . $index . '][' . $field . ']' ); ?>" value="<?php echo esc_attr( $value ); ?>"></td><td class="cartflush-row-action"><?php $this->render_remove_button(); ?></td></tr><?php return (string) ob_get_clean();
	}

	private function render_select_field( $name, $selected, $options, $placeholder ) {
		$options = is_array( $options ) ? $options : [];
		if ( $selected && ! isset( $options[ $selected ] ) ) { $options = [ $selected => $selected ] + $options; }
		?><select class="regular-text" name="<?php echo esc_attr( $name ); ?>"><option value=""><?php echo esc_html( $placeholder ); ?></option><?php foreach ( $options as $value => $label ) : ?><option value="<?php echo esc_attr( $value ); ?>" <?php selected( $selected, (string) $value ); ?>><?php echo esc_html( $label ); ?></option><?php endforeach; ?></select><?php
	}

	private function build_rules_from_text_inputs( $data ) {
		$data = is_array( $data ) ? wp_unslash( $data ) : [];

		return [
			'customer_type_rules' => $this->parse_timeout_text( isset( $data['cartflush_customer_type_rules_text'] ) ? $data['cartflush_customer_type_rules_text'] : '', 'sanitize_key', [ 'guest', 'logged_in' ] ),
			'role_rules'          => $this->parse_timeout_text( isset( $data['cartflush_role_rules_text'] ) ? $data['cartflush_role_rules_text'] : '', 'sanitize_key' ),
			'product_rules'       => $this->parse_integer_timeout_text( isset( $data['cartflush_product_rules_text'] ) ? $data['cartflush_product_rules_text'] : '' ),
			'category_rules'      => $this->parse_timeout_text( isset( $data['cartflush_category_rules_text'] ) ? $data['cartflush_category_rules_text'] : '', 'sanitize_title' ),
			'tag_rules'           => $this->parse_timeout_text( isset( $data['cartflush_tag_rules_text'] ) ? $data['cartflush_tag_rules_text'] : '', 'sanitize_title' ),
			'excluded_roles'      => $this->parse_list_text( isset( $data['cartflush_excluded_roles_text'] ) ? $data['cartflush_excluded_roles_text'] : '', 'sanitize_key' ),
			'excluded_products'   => $this->parse_integer_list_text( isset( $data['cartflush_excluded_products_text'] ) ? $data['cartflush_excluded_products_text'] : '' ),
			'excluded_categories' => $this->parse_list_text( isset( $data['cartflush_excluded_categories_text'] ) ? $data['cartflush_excluded_categories_text'] : '', 'sanitize_title' ),
			'excluded_tags'       => $this->parse_list_text( isset( $data['cartflush_excluded_tags_text'] ) ? $data['cartflush_excluded_tags_text'] : '', 'sanitize_title' ),
		];
	}

	public function maybe_sync_settings_to_rules() {
		if ( ! is_admin() ) {
			return;
		}

		if ( ! isset( $_GET['page'] ) || 'cartflush-settings' !== sanitize_key( wp_unslash( $_GET['page'] ) ) ) {
			return;
		}

		if ( empty( $_GET['settings-updated'] ) ) {
			return;
		}

		$rules = $this->build_rules_from_saved_settings();
		update_option( CartFlush_Rules::OPTION_NAME, $this->rules->normalize_rules_data( $rules ) );
	}

	private function build_rules_from_saved_settings() {
		return $this->build_rules_from_text_inputs(
			[
				'cartflush_customer_type_rules_text' => get_option( 'cartflush_customer_type_rules_text', '' ),
				'cartflush_role_rules_text'          => get_option( 'cartflush_role_rules_text', '' ),
				'cartflush_product_rules_text'       => get_option( 'cartflush_product_rules_text', '' ),
				'cartflush_category_rules_text'      => get_option( 'cartflush_category_rules_text', '' ),
				'cartflush_tag_rules_text'           => get_option( 'cartflush_tag_rules_text', '' ),
				'cartflush_excluded_roles_text'      => get_option( 'cartflush_excluded_roles_text', '' ),
				'cartflush_excluded_products_text'   => get_option( 'cartflush_excluded_products_text', '' ),
				'cartflush_excluded_categories_text' => get_option( 'cartflush_excluded_categories_text', '' ),
				'cartflush_excluded_tags_text'       => get_option( 'cartflush_excluded_tags_text', '' ),
			]
		);
	}

	private function parse_timeout_text( $value, $sanitizer, $allowed_keys = null ) {
		$parsed = [];
		foreach ( $this->get_text_lines( $value ) as $line ) {
			$parts = preg_split( '/\s*\|\s*/', $line );
			if ( ! is_array( $parts ) || count( $parts ) < 2 ) {
				continue;
			}
			$key     = call_user_func( $sanitizer, $parts[0] );
			$timeout = absint( $parts[1] );
			if ( ! $key || $timeout <= 0 ) {
				continue;
			}
			if ( is_array( $allowed_keys ) && ! in_array( $key, $allowed_keys, true ) ) {
				continue;
			}
			$parsed[ $key ] = $timeout;
		}
		return $parsed;
	}

	private function parse_integer_timeout_text( $value ) {
		$parsed = [];
		foreach ( $this->get_text_lines( $value ) as $line ) {
			$parts = preg_split( '/\s*\|\s*/', $line );
			if ( ! is_array( $parts ) || count( $parts ) < 2 ) {
				continue;
			}
			$key     = absint( $parts[0] );
			$timeout = absint( $parts[1] );
			if ( $key > 0 && $timeout > 0 ) {
				$parsed[ $key ] = $timeout;
			}
		}
		return $parsed;
	}

	private function parse_list_text( $value, $sanitizer ) {
		$parsed = [];
		foreach ( $this->get_text_lines( $value ) as $line ) {
			$item = call_user_func( $sanitizer, $line );
			if ( $item ) {
				$parsed[] = $item;
			}
		}
		return array_values( array_unique( $parsed ) );
	}

	private function parse_integer_list_text( $value ) {
		$parsed = [];
		foreach ( $this->get_text_lines( $value ) as $line ) {
			$item = absint( $line );
			if ( $item > 0 ) {
				$parsed[] = $item;
			}
		}
		return array_values( array_unique( $parsed ) );
	}

	private function get_text_lines( $value ) {
		$value = is_string( $value ) ? $value : '';
		$lines = preg_split( '/\r\n|\r|\n/', $value );
		return array_values(
			array_filter(
				array_map( 'trim', is_array( $lines ) ? $lines : [] ),
				static function( $line ) {
					return '' !== $line;
				}
			)
		);
	}

	private function format_timeout_lines( $items ) {
		$lines = [];
		foreach ( $items as $key => $value ) {
			$lines[] = $key . '|' . absint( $value );
		}
		return implode( "\n", $lines );
	}

	private function format_list_lines( $items ) {
		return implode( "\n", array_map( 'strval', $items ) );
	}

	private function render_remove_button() {
		echo '<button type="button" class="button-link-delete cartflush-remove-row">' . esc_html__( 'Remove', 'cartflush' ) . '</button>';
	}

	private function prepare_rules_for_storage( $value ) {
		return [
			'customer_type_rules' => $this->prepare_timeout_rows( isset( $value['customer_type_rules'] ) ? $value['customer_type_rules'] : [], 'type', 'sanitize_key' ),
			'role_rules'          => $this->prepare_timeout_rows( isset( $value['role_rules'] ) ? $value['role_rules'] : [], 'role', 'sanitize_key' ),
			'product_rules'       => $this->prepare_integer_timeout_rows( isset( $value['product_rules'] ) ? $value['product_rules'] : [], 'product_id' ),
			'category_rules'      => $this->prepare_timeout_rows( isset( $value['category_rules'] ) ? $value['category_rules'] : [], 'slug', 'sanitize_title' ),
			'tag_rules'           => $this->prepare_timeout_rows( isset( $value['tag_rules'] ) ? $value['tag_rules'] : [], 'slug', 'sanitize_title' ),
			'excluded_roles'      => $this->prepare_string_list_rows( isset( $value['excluded_roles'] ) ? $value['excluded_roles'] : [], 'role', 'sanitize_key' ),
			'excluded_products'   => $this->prepare_integer_list_rows( isset( $value['excluded_products'] ) ? $value['excluded_products'] : [], 'product_id' ),
			'excluded_categories' => $this->prepare_string_list_rows( isset( $value['excluded_categories'] ) ? $value['excluded_categories'] : [], 'slug', 'sanitize_title' ),
			'excluded_tags'       => $this->prepare_string_list_rows( isset( $value['excluded_tags'] ) ? $value['excluded_tags'] : [], 'slug', 'sanitize_title' ),
		];
	}

	private function prepare_timeout_rows( $rows, $field, $sanitizer ) {
		$prepared = []; if ( ! is_array( $rows ) ) { return $prepared; } foreach ( $rows as $row ) { if ( ! is_array( $row ) ) { continue; } $key = isset( $row[ $field ] ) ? call_user_func( $sanitizer, $row[ $field ] ) : ''; $timeout = isset( $row['timeout'] ) ? absint( $row['timeout'] ) : 0; if ( $key && $timeout > 0 ) { $prepared[ $key ] = $timeout; } } return $prepared;
	}

	private function prepare_integer_timeout_rows( $rows, $field ) {
		$prepared = []; if ( ! is_array( $rows ) ) { return $prepared; } foreach ( $rows as $row ) { if ( ! is_array( $row ) ) { continue; } $key = isset( $row[ $field ] ) ? absint( $row[ $field ] ) : 0; $timeout = isset( $row['timeout'] ) ? absint( $row['timeout'] ) : 0; if ( $key > 0 && $timeout > 0 ) { $prepared[ $key ] = $timeout; } } return $prepared;
	}

	private function prepare_string_list_rows( $rows, $field, $sanitizer ) {
		$prepared = []; if ( ! is_array( $rows ) ) { return $prepared; } foreach ( $rows as $row ) { $item = is_array( $row ) && isset( $row[ $field ] ) ? call_user_func( $sanitizer, $row[ $field ] ) : ''; if ( $item ) { $prepared[] = $item; } } return array_values( array_unique( $prepared ) );
	}

	private function prepare_integer_list_rows( $rows, $field ) {
		$prepared = []; if ( ! is_array( $rows ) ) { return $prepared; } foreach ( $rows as $row ) { $item = is_array( $row ) && isset( $row[ $field ] ) ? absint( $row[ $field ] ) : 0; if ( $item > 0 ) { $prepared[] = $item; } } return array_values( array_unique( $prepared ) );
	}

	private function get_stats( $rules ) {
		return [
			[ 'label' => __( 'Default Timeout', 'cartflush' ), 'value' => (int) get_option( 'cartflush_expiration_time', 30 ), 'meta' => __( 'minutes', 'cartflush' ) ],
			[ 'label' => __( 'Timeout Rules', 'cartflush' ), 'value' => count( $rules['customer_type_rules'] ) + count( $rules['role_rules'] ) + count( $rules['product_rules'] ) + count( $rules['category_rules'] ) + count( $rules['tag_rules'] ), 'meta' => __( 'active', 'cartflush' ) ],
			[ 'label' => __( 'Exclusions', 'cartflush' ), 'value' => count( $rules['excluded_roles'] ) + count( $rules['excluded_products'] ) + count( $rules['excluded_categories'] ) + count( $rules['excluded_tags'] ), 'meta' => __( 'guards', 'cartflush' ) ],
			[ 'label' => __( 'Import Modes', 'cartflush' ), 'value' => 'CSV + JSON', 'meta' => __( 'plus sample', 'cartflush' ) ],
		];
	}

	private function get_summary_items( $rules ) {
		return [
			[ 'label' => __( 'Customer Type Rules', 'cartflush' ), 'value' => $this->format_assoc_list( $rules['customer_type_rules'] ) ],
			[ 'label' => __( 'Role Rules', 'cartflush' ), 'value' => $this->format_assoc_list( $rules['role_rules'] ) ],
			[ 'label' => __( 'Product Rules', 'cartflush' ), 'value' => $this->format_assoc_list( $rules['product_rules'] ) ],
			[ 'label' => __( 'Category Rules', 'cartflush' ), 'value' => $this->format_assoc_list( $rules['category_rules'] ) ],
			[ 'label' => __( 'Tag Rules', 'cartflush' ), 'value' => $this->format_assoc_list( $rules['tag_rules'] ) ],
			[ 'label' => __( 'Excluded Roles', 'cartflush' ), 'value' => $this->format_simple_list( $rules['excluded_roles'] ) ],
			[ 'label' => __( 'Excluded Products', 'cartflush' ), 'value' => $this->format_simple_list( $rules['excluded_products'] ) ],
			[ 'label' => __( 'Excluded Categories', 'cartflush' ), 'value' => $this->format_simple_list( $rules['excluded_categories'] ) ],
			[ 'label' => __( 'Excluded Tags', 'cartflush' ), 'value' => $this->format_simple_list( $rules['excluded_tags'] ) ],
		];
	}

	private function get_role_options() {
		if ( ! function_exists( 'wp_roles' ) ) { return []; }
		$options = []; foreach ( wp_roles()->roles as $key => $data ) { $options[ sanitize_key( $key ) ] = isset( $data['name'] ) ? $data['name'] : $key; } return $options;
	}

	private function get_taxonomy_options( $taxonomy ) {
		$options = []; $terms = get_terms( [ 'taxonomy' => $taxonomy, 'hide_empty' => false ] ); if ( is_wp_error( $terms ) || ! is_array( $terms ) ) { return $options; } foreach ( $terms as $term ) { if ( isset( $term->slug, $term->name ) ) { $options[ $term->slug ] = $term->name . ' (' . $term->slug . ')'; } } return $options;
	}

	private function format_assoc_list( $items ) {
		if ( empty( $items ) ) { return __( 'None saved yet.', 'cartflush' ); }
		$formatted = []; foreach ( $items as $key => $value ) { $formatted[] = sprintf( __( '%1$s: %2$d min', 'cartflush' ), (string) $key, absint( $value ) ); } return implode( ', ', $formatted );
	}

	private function format_simple_list( $items ) {
		return empty( $items ) ? __( 'None saved yet.', 'cartflush' ) : implode( ', ', array_map( 'strval', $items ) );
	}

	private function store_duplicate_rule_warnings( $value ) {
		$warnings = [];

		$this->collect_duplicate_timeout_warnings( $warnings, isset( $value['customer_type_rules'] ) ? $value['customer_type_rules'] : [], 'type', __( 'Customer type rules', 'cartflush' ) );
		$this->collect_duplicate_timeout_warnings( $warnings, isset( $value['role_rules'] ) ? $value['role_rules'] : [], 'role', __( 'Role rules', 'cartflush' ) );
		$this->collect_duplicate_timeout_warnings( $warnings, isset( $value['product_rules'] ) ? $value['product_rules'] : [], 'product_id', __( 'Product rules', 'cartflush' ) );
		$this->collect_duplicate_timeout_warnings( $warnings, isset( $value['category_rules'] ) ? $value['category_rules'] : [], 'slug', __( 'Category rules', 'cartflush' ) );
		$this->collect_duplicate_timeout_warnings( $warnings, isset( $value['tag_rules'] ) ? $value['tag_rules'] : [], 'slug', __( 'Tag rules', 'cartflush' ) );
		$this->collect_duplicate_simple_warnings( $warnings, isset( $value['excluded_roles'] ) ? $value['excluded_roles'] : [], 'role', __( 'Excluded roles', 'cartflush' ) );
		$this->collect_duplicate_simple_warnings( $warnings, isset( $value['excluded_products'] ) ? $value['excluded_products'] : [], 'product_id', __( 'Excluded products', 'cartflush' ) );
		$this->collect_duplicate_simple_warnings( $warnings, isset( $value['excluded_categories'] ) ? $value['excluded_categories'] : [], 'slug', __( 'Excluded categories', 'cartflush' ) );
		$this->collect_duplicate_simple_warnings( $warnings, isset( $value['excluded_tags'] ) ? $value['excluded_tags'] : [], 'slug', __( 'Excluded tags', 'cartflush' ) );

		if ( ! empty( $warnings ) ) {
			set_transient( 'cartflush_admin_duplicate_warnings_' . get_current_user_id(), array_values( array_unique( $warnings ) ), MINUTE_IN_SECONDS );
			return;
		}

		delete_transient( 'cartflush_admin_duplicate_warnings_' . get_current_user_id() );
	}

	private function collect_duplicate_timeout_warnings( &$warnings, $rows, $field, $label ) {
		if ( ! is_array( $rows ) ) {
			return;
		}

		$seen = [];

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) || empty( $row[ $field ] ) ) {
				continue;
			}

			$key = sanitize_text_field( (string) $row[ $field ] );

			if ( isset( $seen[ $key ] ) ) {
				$warnings[] = sprintf( __( '%1$s contains a duplicate entry for %2$s.', 'cartflush' ), $label, $key );
			}

			$seen[ $key ] = true;
		}
	}

	private function collect_duplicate_simple_warnings( &$warnings, $rows, $field, $label ) {
		$this->collect_duplicate_timeout_warnings( $warnings, $rows, $field, $label );
	}

	private function assert_admin_permissions() {
		if ( ! current_user_can( $this->get_required_capability() ) ) { wp_die( esc_html__( 'You do not have permission to access this page.', 'cartflush' ) ); }
	}

	private function get_required_capability() {
		return 'manage_woocommerce';
	}

	private function redirect_with_message( $notice = '', $error = '' ) {
		$url = add_query_arg( array_filter( [ 'page' => 'cartflush-settings', 'cartflush_notice' => $notice, 'cartflush_error' => $error ] ), admin_url( 'admin.php' ) );
		wp_safe_redirect( $url );
		exit;
	}
}
