<?php
/**
 * Import/Export Settings Tab
 *
 * Admin interface for exporting and importing full plugin configuration and data.
 *
 * @package Humata_Chatbot
 * @since 1.3.0
 */
defined( 'ABSPATH' ) || exit;

/**
 * Class Humata_Chatbot_Settings_Tab_Import_Export
 *
 * Handles the Import/Export admin tab.
 *
 * @since 1.3.0
 */
class Humata_Chatbot_Settings_Tab_Import_Export extends Humata_Chatbot_Settings_Tab_Base {

	/**
	 * Flag to prevent double initialization.
	 *
	 * @since 1.3.0
	 * @var bool
	 */
	private static $initialized = false;

	/**
	 * Get the tab key.
	 *
	 * @since 1.3.0
	 * @return string
	 */
	public function get_key() {
		return 'import_export';
	}

	/**
	 * Get the tab label.
	 *
	 * @since 1.3.0
	 * @return string
	 */
	public function get_label() {
		return __( 'Import/Export', 'humata-chatbot' );
	}

	/**
	 * Get the Settings API page ID.
	 *
	 * @since 1.3.0
	 * @return string
	 */
	public function get_page_id() {
		return 'humata-chatbot-tab-import-export';
	}

	/**
	 * This tab does not use the standard settings form.
	 *
	 * @since 1.3.0
	 * @return bool
	 */
	public function has_form() {
		return false;
	}

	/**
	 * Register settings (none for this tab - uses custom forms).
	 *
	 * @since 1.3.0
	 * @return void
	 */
	public function register() {
		// No settings to register - this tab uses custom forms.
	}

	/**
	 * Initialize the tab - hook into admin_init for form processing.
	 *
	 * @since 1.3.0
	 * @return void
	 */
	public function init() {
		if ( self::$initialized ) {
			return;
		}

		self::$initialized = true;
		add_action( 'admin_init', array( $this, 'handle_actions' ) );
	}

	/**
	 * Handle form actions.
	 *
	 * @since 1.3.0
	 * @return void
	 */
	public function handle_actions() {
		if ( ! isset( $_GET['page'] ) || 'humata-chatbot' !== $_GET['page'] ) {
			return;
		}

		if ( ! isset( $_GET['tab'] ) || 'import_export' !== $_GET['tab'] ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Handle export.
		if ( isset( $_POST['humata_export_package'] ) ) {
			$this->handle_export();
		}

		// Handle import.
		if ( isset( $_POST['humata_import_package'] ) ) {
			$this->handle_import();
		}
	}

	/**
	 * Render the tab content.
	 *
	 * @since 1.3.0
	 * @return void
	 */
	public function render() {
		?>
		<div class="humata-import-export-tab">
			<div class="notice notice-warning" style="margin: 0 0 16px 0;">
				<p>
					<strong><?php esc_html_e( 'Heads up:', 'humata-chatbot' ); ?></strong>
					<?php esc_html_e( 'Full exports include your local documents and message analytics database (which may contain user questions and other sensitive content). Store export files securely.', 'humata-chatbot' ); ?>
				</p>
			</div>
			<div class="card">
				<h2><?php esc_html_e( 'Export', 'humata-chatbot' ); ?></h2>
				<p class="description">
					<?php esc_html_e( 'Download a ZIP package containing plugin settings and data for transfer to another site.', 'humata-chatbot' ); ?>
				</p>
				<form method="post">
					<?php wp_nonce_field( 'humata_export_package', 'humata_export_package_nonce' ); ?>
					<p>
						<label>
							<input type="checkbox" name="humata_export_include_secrets" value="1" />
							<?php esc_html_e( 'Include API keys/secrets (not recommended)', 'humata-chatbot' ); ?>
						</label>
					</p>
					<p class="description" style="margin-top: 0;">
						<?php esc_html_e( 'If checked, the export file will contain API keys. Only enable this if you understand the security risk.', 'humata-chatbot' ); ?>
					</p>
					<p>
						<button type="submit" name="humata_export_package" class="button button-primary">
							<?php esc_html_e( 'Download Full Export (ZIP)', 'humata-chatbot' ); ?>
						</button>
					</p>
				</form>
			</div>

			<div class="card" style="margin-top: 16px;">
				<h2><?php esc_html_e( 'Import', 'humata-chatbot' ); ?></h2>
				<p class="description">
					<?php esc_html_e( 'Upload a previously exported ZIP (full package) or JSON (settings-only).', 'humata-chatbot' ); ?>
				</p>
				<form method="post" enctype="multipart/form-data">
					<?php wp_nonce_field( 'humata_import_package', 'humata_import_package_nonce' ); ?>
					<p class="description" style="margin-top: 0;">
						<?php esc_html_e( 'Importing a ZIP can overwrite your local documents + analytics database (a backup will be created first).', 'humata-chatbot' ); ?>
					</p>
					<p>
						<input type="file" name="humata_import_file" accept=".zip,.json,application/zip,application/json" required />
					</p>
					<p>
						<label>
							<input type="checkbox" name="humata_import_keep_existing_secrets" value="1" checked />
							<?php esc_html_e( 'Keep existing API keys if the import omits them', 'humata-chatbot' ); ?>
						</label>
					</p>
					<p>
						<label>
							<input type="checkbox" name="humata_import_overwrite_data" value="1" checked />
							<?php esc_html_e( 'Overwrite local documents + analytics database (a backup will be created)', 'humata-chatbot' ); ?>
						</label>
					</p>
					<p>
						<button
							type="submit"
							name="humata_import_package"
							class="button button-primary"
							onclick="return confirm('<?php echo esc_js( __( 'Import will update plugin settings and may overwrite local documents/analytics data (with backup). Continue?', 'humata-chatbot' ) ); ?>');"
						>
							<?php esc_html_e( 'Import', 'humata-chatbot' ); ?>
						</button>
					</p>
				</form>
			</div>
		</div>
		<?php
	}

	/**
	 * Add a notice and redirect back to this tab (avoids form resubmission prompts).
	 *
	 * Uses WP's settings error transient mechanism for post-redirect-get.
	 *
	 * @since 1.3.0
	 * @param string $code
	 * @param string $message
	 * @param string $type success|error|warning|info
	 * @return void
	 */
	private function add_notice_and_redirect( $code, $message, $type ) {
		$code    = sanitize_key( (string) $code );
		$message = (string) $message;
		$type    = sanitize_key( (string) $type );

		add_settings_error( 'humata_chatbot_messages', $code, $message, $type );

		if ( function_exists( 'get_settings_errors' ) ) {
			// Persist notices across redirect.
			set_transient( 'settings_errors', get_settings_errors(), 30 );
		}

		$url = add_query_arg(
			array(
				'page'             => 'humata-chatbot',
				'tab'              => 'import_export',
				'settings-updated' => '1',
			),
			admin_url( 'options-general.php' )
		);

		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Handle export request.
	 *
	 * @since 1.3.0
	 * @return void
	 */
	private function handle_export() {
		if ( ! check_admin_referer( 'humata_export_package', 'humata_export_package_nonce' ) ) {
			return;
		}

		if ( ! class_exists( 'Humata_Chatbot_Admin_Settings_Export_Import_Exporter' ) ) {
			$this->add_notice_and_redirect(
				'export_unavailable',
				__( 'Export is not available (missing exporter class).', 'humata-chatbot' ),
				'error'
			);
		}

		$include_secrets = ! empty( $_POST['humata_export_include_secrets'] );

		$exporter = new Humata_Chatbot_Admin_Settings_Export_Import_Exporter();
		$result   = $exporter->send_export_zip( $include_secrets );

		if ( is_wp_error( $result ) ) {
			$this->add_notice_and_redirect( 'export_error', $result->get_error_message(), 'error' );
		}
	}

	/**
	 * Handle import request.
	 *
	 * @since 1.3.0
	 * @return void
	 */
	private function handle_import() {
		if ( ! check_admin_referer( 'humata_import_package', 'humata_import_package_nonce' ) ) {
			$this->add_notice_and_redirect(
				'nonce_error',
				__( 'Security check failed. Please try again.', 'humata-chatbot' ),
				'error'
			);
		}

		if ( ! class_exists( 'Humata_Chatbot_Admin_Settings_Export_Import_Importer' ) ) {
			$this->add_notice_and_redirect(
				'import_unavailable',
				__( 'Import is not available (missing importer class).', 'humata-chatbot' ),
				'error'
			);
		}

		$keep_existing_secrets = ! empty( $_POST['humata_import_keep_existing_secrets'] );
		$overwrite_data        = ! empty( $_POST['humata_import_overwrite_data'] );

		$importer = new Humata_Chatbot_Admin_Settings_Export_Import_Importer();
		$result   = $importer->handle_upload_and_import( $_FILES, $keep_existing_secrets, $overwrite_data );

		if ( is_wp_error( $result ) ) {
			$this->add_notice_and_redirect( 'import_error', $result->get_error_message(), 'error' );
		}

		$this->add_notice_and_redirect(
			'import_success',
			__( 'Import completed successfully.', 'humata-chatbot' ),
			'success'
		);
	}
}


