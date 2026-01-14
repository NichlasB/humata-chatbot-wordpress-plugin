<?php
/**
 * Message Analytics Settings Tab
 *
 * Admin interface for viewing chat message logs and AI-generated insights.
 *
 * @package Humata_Chatbot
 * @since 1.2.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Humata_Chatbot_Settings_Tab_Analytics
 *
 * Handles the Message Analytics admin tab.
 *
 * @since 1.2.0
 */
class Humata_Chatbot_Settings_Tab_Analytics extends Humata_Chatbot_Settings_Tab_Base {

	/**
	 * Flag to prevent double initialization.
	 *
	 * @since 1.2.0
	 * @var bool
	 */
	private static $initialized = false;

	/**
	 * Get the tab key.
	 *
	 * @since 1.2.0
	 * @return string
	 */
	public function get_key() {
		return 'analytics';
	}

	/**
	 * Get the tab label.
	 *
	 * @since 1.2.0
	 * @return string
	 */
	public function get_label() {
		return __( 'Message Analytics', 'humata-chatbot' );
	}

	/**
	 * Get the Settings API page ID.
	 *
	 * @since 1.2.0
	 * @return string
	 */
	public function get_page_id() {
		return 'humata-chatbot-tab-analytics';
	}

	/**
	 * This tab does not use the standard settings form.
	 *
	 * @since 1.2.0
	 * @return bool
	 */
	public function has_form() {
		return false;
	}

	/**
	 * Register settings (none for this tab - uses custom forms).
	 *
	 * @since 1.2.0
	 * @return void
	 */
	public function register() {
		// No settings to register - this tab uses custom forms.
	}

	/**
	 * Initialize the tab - hook into admin_init for form processing.
	 *
	 * @since 1.2.0
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
	 * @since 1.2.0
	 * @return void
	 */
	public function handle_actions() {
		if ( ! isset( $_GET['page'] ) || 'humata-chatbot' !== $_GET['page'] ) {
			return;
		}

		if ( ! isset( $_GET['tab'] ) || 'analytics' !== $_GET['tab'] ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Handle settings save.
		if ( isset( $_POST['humata_analytics_save_settings'] ) ) {
			$this->handle_save_settings();
		}

		// Handle delete message.
		if ( isset( $_GET['action'] ) && 'delete_message' === $_GET['action'] && isset( $_GET['msg_id'] ) ) {
			$this->handle_delete_message();
		}

		// Handle bulk delete.
		if ( isset( $_POST['humata_analytics_bulk_delete'] ) ) {
			$this->handle_bulk_delete();
		}

		// Handle bulk reprocess.
		if ( isset( $_POST['humata_analytics_bulk_reprocess'] ) ) {
			$this->handle_bulk_reprocess();
		}

		// Handle export.
		if ( isset( $_POST['humata_analytics_export'] ) ) {
			$this->handle_export();
		}

		// Handle clear all.
		if ( isset( $_POST['humata_analytics_clear_all'] ) ) {
			$this->handle_clear_all();
		}
	}

	/**
	 * Handle settings save.
	 *
	 * @since 1.2.0
	 * @return void
	 */
	private function handle_save_settings() {
		if ( ! check_admin_referer( 'humata_analytics_settings', 'humata_analytics_nonce' ) ) {
			add_settings_error(
				'humata_analytics',
				'nonce_error',
				__( 'Security check failed. Please try again.', 'humata-chatbot' ),
				'error'
			);
			return;
		}

		// Save settings.
		$enabled = isset( $_POST['humata_analytics_enabled'] ) ? 1 : 0;
		update_option( 'humata_analytics_enabled', $enabled );

		$processing_enabled = isset( $_POST['humata_analytics_processing_enabled'] ) ? 1 : 0;
		update_option( 'humata_analytics_processing_enabled', $processing_enabled );

		$provider = isset( $_POST['humata_analytics_provider'] ) ? sanitize_text_field( $_POST['humata_analytics_provider'] ) : '';
		if ( ! in_array( $provider, array( 'anthropic', 'openrouter', 'straico' ), true ) ) {
			$provider = '';
		}
		update_option( 'humata_analytics_provider', $provider );

		$api_key = isset( $_POST['humata_analytics_api_key'] ) ? sanitize_text_field( $_POST['humata_analytics_api_key'] ) : '';
		$api_keys = '' !== $api_key ? array( $api_key ) : array();
		update_option( 'humata_analytics_api_key', $api_keys );

		$model = isset( $_POST['humata_analytics_model'] ) ? sanitize_text_field( $_POST['humata_analytics_model'] ) : '';
		update_option( 'humata_analytics_model', $model );

		$system_prompt = isset( $_POST['humata_analytics_system_prompt'] ) ? sanitize_textarea_field( $_POST['humata_analytics_system_prompt'] ) : '';
		update_option( 'humata_analytics_system_prompt', $system_prompt );

		$retention_days = isset( $_POST['humata_analytics_retention_days'] ) ? absint( $_POST['humata_analytics_retention_days'] ) : 90;
		update_option( 'humata_analytics_retention_days', $retention_days );

		add_settings_error(
			'humata_analytics',
			'settings_saved',
			__( 'Settings saved successfully.', 'humata-chatbot' ),
			'success'
		);
	}

	/**
	 * Handle delete message.
	 *
	 * @since 1.2.0
	 * @return void
	 */
	private function handle_delete_message() {
		$msg_id = absint( $_GET['msg_id'] );

		if ( ! wp_verify_nonce( $_GET['_wpnonce'], 'humata_delete_message_' . $msg_id ) ) {
			add_settings_error(
				'humata_analytics',
				'nonce_error',
				__( 'Security check failed. Please try again.', 'humata-chatbot' ),
				'error'
			);
			return;
		}

		require_once HUMATA_CHATBOT_PATH . 'includes/Rest/SearchDatabase.php';
		require_once HUMATA_CHATBOT_PATH . 'includes/Rest/MessageLogger.php';

		$database = new Humata_Chatbot_Rest_Search_Database();
		$logger   = new Humata_Chatbot_Rest_Message_Logger( $database );

		$result = $logger->delete_messages( array( $msg_id ) );

		if ( is_wp_error( $result ) ) {
			add_settings_error(
				'humata_analytics',
				'delete_error',
				$result->get_error_message(),
				'error'
			);
			return;
		}

		add_settings_error(
			'humata_analytics',
			'delete_success',
			__( 'Message deleted successfully.', 'humata-chatbot' ),
			'success'
		);

		// Redirect to remove action from URL.
		wp_safe_redirect( admin_url( 'options-general.php?page=humata-chatbot&tab=analytics' ) );
		exit;
	}

	/**
	 * Handle bulk delete.
	 *
	 * @since 1.2.0
	 * @return void
	 */
	private function handle_bulk_delete() {
		if ( ! check_admin_referer( 'humata_analytics_bulk', 'humata_analytics_bulk_nonce' ) ) {
			add_settings_error(
				'humata_analytics',
				'nonce_error',
				__( 'Security check failed. Please try again.', 'humata-chatbot' ),
				'error'
			);
			return;
		}

		$msg_ids = isset( $_POST['humata_msg_ids'] ) ? array_map( 'absint', (array) $_POST['humata_msg_ids'] ) : array();
		$msg_ids = array_filter( $msg_ids );

		if ( empty( $msg_ids ) ) {
			add_settings_error(
				'humata_analytics',
				'no_selection',
				__( 'No messages selected.', 'humata-chatbot' ),
				'error'
			);
			return;
		}

		require_once HUMATA_CHATBOT_PATH . 'includes/Rest/SearchDatabase.php';
		require_once HUMATA_CHATBOT_PATH . 'includes/Rest/MessageLogger.php';

		$database = new Humata_Chatbot_Rest_Search_Database();
		$logger   = new Humata_Chatbot_Rest_Message_Logger( $database );

		$result = $logger->delete_messages( $msg_ids );

		if ( is_wp_error( $result ) ) {
			add_settings_error(
				'humata_analytics',
				'delete_error',
				$result->get_error_message(),
				'error'
			);
			return;
		}

		add_settings_error(
			'humata_analytics',
			'delete_success',
			sprintf(
				/* translators: %d: number of messages */
				_n( '%d message deleted.', '%d messages deleted.', $result, 'humata-chatbot' ),
				$result
			),
			'success'
		);
	}

	/**
	 * Handle bulk reprocess.
	 *
	 * @since 1.2.0
	 * @return void
	 */
	private function handle_bulk_reprocess() {
		if ( ! check_admin_referer( 'humata_analytics_bulk', 'humata_analytics_bulk_nonce' ) ) {
			add_settings_error(
				'humata_analytics',
				'nonce_error',
				__( 'Security check failed. Please try again.', 'humata-chatbot' ),
				'error'
			);
			return;
		}

		$msg_ids = isset( $_POST['humata_msg_ids'] ) ? array_map( 'absint', (array) $_POST['humata_msg_ids'] ) : array();
		$msg_ids = array_filter( $msg_ids );

		if ( empty( $msg_ids ) ) {
			add_settings_error(
				'humata_analytics',
				'no_selection',
				__( 'No messages selected.', 'humata-chatbot' ),
				'error'
			);
			return;
		}

		require_once HUMATA_CHATBOT_PATH . 'includes/Rest/SearchDatabase.php';
		require_once HUMATA_CHATBOT_PATH . 'includes/Rest/MessageLogger.php';
		require_once HUMATA_CHATBOT_PATH . 'includes/Rest/MessageAnalyzer.php';

		$database = new Humata_Chatbot_Rest_Search_Database();
		$logger   = new Humata_Chatbot_Rest_Message_Logger( $database );
		$analyzer = new Humata_Chatbot_Rest_Message_Analyzer( $logger );

		if ( ! $analyzer->is_enabled() ) {
			add_settings_error(
				'humata_analytics',
				'processing_disabled',
				__( 'AI processing is not enabled. Please configure the AI settings first.', 'humata-chatbot' ),
				'error'
			);
			return;
		}

		$processed = 0;
		$failed    = 0;

		foreach ( $msg_ids as $msg_id ) {
			$result = $analyzer->reprocess_message( $msg_id );
			if ( is_wp_error( $result ) ) {
				$failed++;
			} else {
				$processed++;
			}
		}

		$message = sprintf(
			/* translators: 1: processed count, 2: failed count */
			__( '%1$d messages processed, %2$d failed.', 'humata-chatbot' ),
			$processed,
			$failed
		);

		add_settings_error(
			'humata_analytics',
			'reprocess_complete',
			$message,
			$failed > 0 ? 'warning' : 'success'
		);
	}

	/**
	 * Handle export.
	 *
	 * @since 1.2.0
	 * @return void
	 */
	private function handle_export() {
		if ( ! check_admin_referer( 'humata_analytics_export', 'humata_analytics_export_nonce' ) ) {
			return;
		}

		require_once HUMATA_CHATBOT_PATH . 'includes/Rest/SearchDatabase.php';
		require_once HUMATA_CHATBOT_PATH . 'includes/Rest/MessageLogger.php';

		$database = new Humata_Chatbot_Rest_Search_Database();
		$logger   = new Humata_Chatbot_Rest_Message_Logger( $database );

		$filters = array();
		if ( ! empty( $_POST['export_date_from'] ) ) {
			$filters['date_from'] = sanitize_text_field( $_POST['export_date_from'] );
		}
		if ( ! empty( $_POST['export_date_to'] ) ) {
			$filters['date_to'] = sanitize_text_field( $_POST['export_date_to'] );
		}

		$csv = $logger->export_csv( $filters );

		if ( is_wp_error( $csv ) ) {
			add_settings_error(
				'humata_analytics',
				'export_error',
				$csv->get_error_message(),
				'error'
			);
			return;
		}

		// Send CSV download.
		$filename = 'humata-messages-' . gmdate( 'Y-m-d' ) . '.csv';

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=' . $filename );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		echo $csv; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	/**
	 * Handle clear all messages.
	 *
	 * @since 1.2.0
	 * @return void
	 */
	private function handle_clear_all() {
		if ( ! check_admin_referer( 'humata_analytics_clear_all', 'humata_analytics_clear_nonce' ) ) {
			add_settings_error(
				'humata_analytics',
				'nonce_error',
				__( 'Security check failed. Please try again.', 'humata-chatbot' ),
				'error'
			);
			return;
		}

		require_once HUMATA_CHATBOT_PATH . 'includes/Rest/SearchDatabase.php';
		require_once HUMATA_CHATBOT_PATH . 'includes/Rest/MessageLogger.php';

		$database = new Humata_Chatbot_Rest_Search_Database();
		$logger   = new Humata_Chatbot_Rest_Message_Logger( $database );

		$result = $logger->delete_all_messages();

		if ( is_wp_error( $result ) ) {
			add_settings_error(
				'humata_analytics',
				'clear_error',
				$result->get_error_message(),
				'error'
			);
			return;
		}

		add_settings_error(
			'humata_analytics',
			'clear_success',
			sprintf(
				/* translators: %d: number of messages */
				__( '%d messages deleted.', 'humata-chatbot' ),
				$result
			),
			'success'
		);
	}

	/**
	 * Render the tab content.
	 *
	 * @since 1.2.0
	 * @return void
	 */
	public function render() {
		require_once HUMATA_CHATBOT_PATH . 'includes/Rest/SearchDatabase.php';

		$database = new Humata_Chatbot_Rest_Search_Database();

		if ( ! $database->is_available() ) {
			$this->render_sqlite_unavailable();
			return;
		}

		require_once HUMATA_CHATBOT_PATH . 'includes/Rest/MessageLogger.php';

		// Initialize database if needed.
		$database->maybe_init();

		$logger = new Humata_Chatbot_Rest_Message_Logger( $database );
		$stats  = $logger->get_stats();

		?>
		<div class="humata-analytics-tab">
			<?php $this->render_settings_form(); ?>
			<?php $this->render_stats_box( $stats ); ?>
			<?php $this->render_message_list( $logger ); ?>
			<?php $this->render_export_form(); ?>
		</div>
		<?php
	}

	/**
	 * Render SQLite unavailable message.
	 *
	 * @since 1.2.0
	 * @return void
	 */
	private function render_sqlite_unavailable() {
		?>
		<div class="notice notice-error">
			<p>
				<strong><?php esc_html_e( 'SQLite3 Extension Not Available', 'humata-chatbot' ); ?></strong>
			</p>
			<p>
				<?php esc_html_e( 'Message analytics requires the SQLite3 PHP extension, which is not available on this server.', 'humata-chatbot' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Render settings form.
	 *
	 * @since 1.2.0
	 * @return void
	 */
	private function render_settings_form() {
		$enabled            = get_option( 'humata_analytics_enabled', false );
		$processing_enabled = get_option( 'humata_analytics_processing_enabled', false );
		$provider           = get_option( 'humata_analytics_provider', '' );
		$api_keys           = get_option( 'humata_analytics_api_key', array() );
		$api_key            = is_array( $api_keys ) && ! empty( $api_keys ) ? reset( $api_keys ) : '';
		$model              = get_option( 'humata_analytics_model', '' );
		$system_prompt      = get_option( 'humata_analytics_system_prompt', '' );
		$retention_days     = get_option( 'humata_analytics_retention_days', 90 );

		?>
		<div class="card">
			<h2><?php esc_html_e( 'Analytics Settings', 'humata-chatbot' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Configure message logging and AI-powered analysis for chat conversations.', 'humata-chatbot' ); ?>
			</p>

			<form method="post">
				<?php wp_nonce_field( 'humata_analytics_settings', 'humata_analytics_nonce' ); ?>

				<table class="form-table">
					<tr>
						<th scope="row"><?php esc_html_e( 'Enable Message Logging', 'humata-chatbot' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="humata_analytics_enabled" value="1" <?php checked( $enabled ); ?> />
								<?php esc_html_e( 'Log user messages and bot responses for admin review', 'humata-chatbot' ); ?>
							</label>
						</td>
					</tr>

					<tr>
						<th scope="row"><?php esc_html_e( 'Enable AI Processing', 'humata-chatbot' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="humata_analytics_processing_enabled" value="1" <?php checked( $processing_enabled ); ?> />
								<?php esc_html_e( 'Automatically analyze messages using AI to generate insights', 'humata-chatbot' ); ?>
							</label>
						</td>
					</tr>

					<tr>
						<th scope="row"><?php esc_html_e( 'AI Provider', 'humata-chatbot' ); ?></th>
						<td>
							<select name="humata_analytics_provider">
								<option value=""><?php esc_html_e( '-- Select Provider --', 'humata-chatbot' ); ?></option>
								<option value="anthropic" <?php selected( $provider, 'anthropic' ); ?>><?php esc_html_e( 'Anthropic (Claude)', 'humata-chatbot' ); ?></option>
								<option value="openrouter" <?php selected( $provider, 'openrouter' ); ?>><?php esc_html_e( 'OpenRouter', 'humata-chatbot' ); ?></option>
								<option value="straico" <?php selected( $provider, 'straico' ); ?>><?php esc_html_e( 'Straico', 'humata-chatbot' ); ?></option>
							</select>
						</td>
					</tr>

					<tr>
						<th scope="row"><?php esc_html_e( 'API Key', 'humata-chatbot' ); ?></th>
						<td>
							<input type="password" name="humata_analytics_api_key" value="<?php echo esc_attr( $api_key ); ?>" class="regular-text" />
							<p class="description"><?php esc_html_e( 'API key for the selected provider.', 'humata-chatbot' ); ?></p>
						</td>
					</tr>

					<tr>
						<th scope="row"><?php esc_html_e( 'Model', 'humata-chatbot' ); ?></th>
						<td>
							<input type="text" name="humata_analytics_model" value="<?php echo esc_attr( $model ); ?>" class="regular-text" placeholder="e.g., claude-3-haiku-20240307" />
							<p class="description"><?php esc_html_e( 'Model ID to use for analysis. Use a fast, cost-effective model.', 'humata-chatbot' ); ?></p>
						</td>
					</tr>

					<tr>
						<th scope="row"><?php esc_html_e( 'System Prompt', 'humata-chatbot' ); ?></th>
						<td>
							<textarea name="humata_analytics_system_prompt" rows="8" class="large-text code"><?php echo esc_textarea( $system_prompt ); ?></textarea>
							<p class="description">
								<?php esc_html_e( 'Custom prompt for AI analysis. Use {user_message} and {bot_response} as placeholders. Leave blank for default.', 'humata-chatbot' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row"><?php esc_html_e( 'Data Retention', 'humata-chatbot' ); ?></th>
						<td>
							<input type="number" name="humata_analytics_retention_days" value="<?php echo esc_attr( $retention_days ); ?>" min="0" max="365" style="width: 80px;" />
							<?php esc_html_e( 'days', 'humata-chatbot' ); ?>
							<p class="description"><?php esc_html_e( 'Automatically delete messages older than this. Set to 0 to keep forever.', 'humata-chatbot' ); ?></p>
						</td>
					</tr>
				</table>

				<p class="submit">
					<button type="submit" name="humata_analytics_save_settings" class="button button-primary">
						<?php esc_html_e( 'Save Settings', 'humata-chatbot' ); ?>
					</button>
				</p>
			</form>
		</div>
		<?php
	}

	/**
	 * Render statistics box.
	 *
	 * @since 1.2.0
	 * @param array|WP_Error $stats Statistics array.
	 * @return void
	 */
	private function render_stats_box( $stats ) {
		if ( is_wp_error( $stats ) ) {
			return;
		}

		?>
		<div class="card">
			<h2><?php esc_html_e( 'Statistics', 'humata-chatbot' ); ?></h2>
			<table class="form-table">
				<tr>
					<th><?php esc_html_e( 'Total Messages', 'humata-chatbot' ); ?></th>
					<td><?php echo esc_html( $stats['total_messages'] ?? 0 ); ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Processed', 'humata-chatbot' ); ?></th>
					<td><?php echo esc_html( $stats['processed_messages'] ?? 0 ); ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Pending', 'humata-chatbot' ); ?></th>
					<td><?php echo esc_html( $stats['pending_messages'] ?? 0 ); ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Total Sessions', 'humata-chatbot' ); ?></th>
					<td><?php echo esc_html( $stats['total_sessions'] ?? 0 ); ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Messages Today', 'humata-chatbot' ); ?></th>
					<td><?php echo esc_html( $stats['messages_today'] ?? 0 ); ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Messages This Week', 'humata-chatbot' ); ?></th>
					<td><?php echo esc_html( $stats['messages_this_week'] ?? 0 ); ?></td>
				</tr>
			</table>
		</div>
		<?php
	}

	/**
	 * Render message list.
	 *
	 * @since 1.2.0
	 * @param Humata_Chatbot_Rest_Message_Logger $logger Message logger instance.
	 * @return void
	 */
	private function render_message_list( $logger ) {
		$per_page     = 20;
		$current_page = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		$view_mode    = isset( $_GET['view'] ) && 'sessions' === $_GET['view'] ? 'sessions' : 'messages';

		// Get filters.
		$filters = array();
		if ( isset( $_GET['processed'] ) && '' !== $_GET['processed'] ) {
			$filters['processed'] = (bool) $_GET['processed'];
		}
		if ( ! empty( $_GET['search'] ) ) {
			$filters['search'] = sanitize_text_field( $_GET['search'] );
		}
		if ( ! empty( $_GET['date_from'] ) ) {
			$filters['date_from'] = sanitize_text_field( $_GET['date_from'] );
		}
		if ( ! empty( $_GET['date_to'] ) ) {
			$filters['date_to'] = sanitize_text_field( $_GET['date_to'] );
		}

		if ( 'sessions' === $view_mode ) {
			$result = $logger->get_sessions( $per_page, $current_page, $filters );
			$items  = is_wp_error( $result ) ? array() : $result['sessions'];
		} else {
			$result = $logger->get_messages( $per_page, $current_page, $filters );
			$items  = is_wp_error( $result ) ? array() : $result['messages'];
		}

		$total       = is_wp_error( $result ) ? 0 : $result['total'];
		$total_pages = is_wp_error( $result ) ? 0 : $result['pages'];

		?>
		<div class="card">
			<h2>
				<?php esc_html_e( 'Message History', 'humata-chatbot' ); ?>
				<?php if ( $total > 0 ) : ?>
					<span style="font-size: 13px; font-weight: normal;">(<?php echo esc_html( $total ); ?>)</span>
				<?php endif; ?>
			</h2>

			<!-- View Toggle -->
			<div style="margin-bottom: 15px;">
				<a href="<?php echo esc_url( add_query_arg( 'view', 'messages', remove_query_arg( 'paged' ) ) ); ?>" class="button <?php echo 'messages' === $view_mode ? 'button-primary' : ''; ?>">
					<?php esc_html_e( 'Individual Messages', 'humata-chatbot' ); ?>
				</a>
				<a href="<?php echo esc_url( add_query_arg( 'view', 'sessions', remove_query_arg( 'paged' ) ) ); ?>" class="button <?php echo 'sessions' === $view_mode ? 'button-primary' : ''; ?>">
					<?php esc_html_e( 'By Session', 'humata-chatbot' ); ?>
				</a>
			</div>

			<!-- Filters -->
			<form method="get" style="margin-bottom: 15px;">
				<input type="hidden" name="page" value="humata-chatbot" />
				<input type="hidden" name="tab" value="analytics" />
				<input type="hidden" name="view" value="<?php echo esc_attr( $view_mode ); ?>" />

				<input type="text" name="search" value="<?php echo esc_attr( $filters['search'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'Search messages...', 'humata-chatbot' ); ?>" style="width: 200px;" />

				<select name="processed">
					<option value=""><?php esc_html_e( 'All Status', 'humata-chatbot' ); ?></option>
					<option value="1" <?php selected( isset( $filters['processed'] ) && $filters['processed'] ); ?>><?php esc_html_e( 'Processed', 'humata-chatbot' ); ?></option>
					<option value="0" <?php selected( isset( $filters['processed'] ) && ! $filters['processed'] ); ?>><?php esc_html_e( 'Pending', 'humata-chatbot' ); ?></option>
				</select>

				<input type="date" name="date_from" value="<?php echo esc_attr( $filters['date_from'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'From', 'humata-chatbot' ); ?>" />
				<input type="date" name="date_to" value="<?php echo esc_attr( $filters['date_to'] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'To', 'humata-chatbot' ); ?>" />

				<button type="submit" class="button"><?php esc_html_e( 'Filter', 'humata-chatbot' ); ?></button>
				<a href="<?php echo esc_url( admin_url( 'options-general.php?page=humata-chatbot&tab=analytics&view=' . $view_mode ) ); ?>" class="button"><?php esc_html_e( 'Clear', 'humata-chatbot' ); ?></a>
			</form>

			<?php if ( empty( $items ) ) : ?>
				<p class="description"><?php esc_html_e( 'No messages found.', 'humata-chatbot' ); ?></p>
			<?php else : ?>

				<?php if ( $total_pages > 1 ) : ?>
					<?php $this->render_pagination( $current_page, $total_pages, $total, $view_mode, $filters ); ?>
				<?php endif; ?>

				<form method="post" id="humata-analytics-bulk-form">
					<?php wp_nonce_field( 'humata_analytics_bulk', 'humata_analytics_bulk_nonce' ); ?>

					<div style="margin-bottom: 10px;">
						<button type="submit" name="humata_analytics_bulk_delete" class="button" onclick="return confirm('<?php esc_attr_e( 'Delete selected messages?', 'humata-chatbot' ); ?>');">
							<?php esc_html_e( 'Delete Selected', 'humata-chatbot' ); ?>
						</button>
						<button type="submit" name="humata_analytics_bulk_reprocess" class="button">
							<?php esc_html_e( 'Reprocess Selected', 'humata-chatbot' ); ?>
						</button>
					</div>

					<?php if ( 'sessions' === $view_mode ) : ?>
						<?php $this->render_sessions_table( $items, $logger ); ?>
					<?php else : ?>
						<?php $this->render_messages_table( $items ); ?>
					<?php endif; ?>
				</form>

				<?php if ( $total_pages > 1 ) : ?>
					<?php $this->render_pagination( $current_page, $total_pages, $total, $view_mode, $filters ); ?>
				<?php endif; ?>

				<!-- Clear All -->
				<form method="post" style="margin-top: 20px; border-top: 1px solid #ccc; padding-top: 15px;">
					<?php wp_nonce_field( 'humata_analytics_clear_all', 'humata_analytics_clear_nonce' ); ?>
					<button type="submit" name="humata_analytics_clear_all" class="button" style="color: #a00;" onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to delete ALL messages? This cannot be undone.', 'humata-chatbot' ); ?>');">
						<?php esc_html_e( 'Clear All Messages', 'humata-chatbot' ); ?>
					</button>
				</form>

			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render messages table.
	 *
	 * @since 1.2.0
	 * @param array $messages Messages array.
	 * @return void
	 */
	private function render_messages_table( $messages ) {
		?>
		<table class="wp-list-table widefat striped" id="humata-analytics-messages-table">
			<thead>
				<tr>
					<th class="check-column"><input type="checkbox" id="humata-select-all-msgs" /></th>
					<th style="width: 140px;"><?php esc_html_e( 'Date/Time', 'humata-chatbot' ); ?></th>
					<th><?php esc_html_e( 'User Message', 'humata-chatbot' ); ?></th>
					<th style="width: 100px;"><?php esc_html_e( 'Status', 'humata-chatbot' ); ?></th>
					<th style="width: 150px;"><?php esc_html_e( 'Insight', 'humata-chatbot' ); ?></th>
					<th style="width: 80px;"><?php esc_html_e( 'Actions', 'humata-chatbot' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $messages as $msg ) : ?>
					<tr>
						<td class="check-column">
							<input type="checkbox" name="humata_msg_ids[]" value="<?php echo esc_attr( $msg['id'] ); ?>" />
						</td>
						<td>
							<?php echo esc_html( wp_date( 'M j, g:i a', strtotime( $msg['created_at'] ) ) ); ?>
						</td>
						<td>
							<strong title="<?php echo esc_attr( $msg['user_message'] ); ?>">
								<?php echo esc_html( wp_trim_words( $msg['user_message'], 15 ) ); ?>
							</strong>
							<?php if ( ! empty( $msg['page_url'] ) ) : ?>
								<br><small style="color: #666;"><?php echo esc_html( wp_parse_url( $msg['page_url'], PHP_URL_PATH ) ); ?></small>
							<?php endif; ?>
						</td>
						<td>
							<?php if ( ! empty( $msg['is_processed'] ) ) : ?>
								<span style="color: #00a32a;">&#10003; <?php esc_html_e( 'Processed', 'humata-chatbot' ); ?></span>
							<?php else : ?>
								<span style="color: #dba617;">&#9679; <?php esc_html_e( 'Pending', 'humata-chatbot' ); ?></span>
							<?php endif; ?>
						</td>
						<td>
							<?php if ( ! empty( $msg['intent'] ) ) : ?>
								<span class="humata-intent-badge"><?php echo esc_html( $msg['intent'] ); ?></span>
							<?php endif; ?>
							<?php if ( ! empty( $msg['sentiment'] ) ) : ?>
								<span class="humata-sentiment-<?php echo esc_attr( $msg['sentiment'] ); ?>"><?php echo esc_html( $msg['sentiment'] ); ?></span>
							<?php endif; ?>
						</td>
						<td>
							<?php
							$delete_url = wp_nonce_url(
								add_query_arg(
									array(
										'page'   => 'humata-chatbot',
										'tab'    => 'analytics',
										'action' => 'delete_message',
										'msg_id' => $msg['id'],
									),
									admin_url( 'options-general.php' )
								),
								'humata_delete_message_' . $msg['id']
							);
							?>
							<a href="#" class="humata-view-message" data-id="<?php echo esc_attr( $msg['id'] ); ?>"><?php esc_html_e( 'View', 'humata-chatbot' ); ?></a> |
							<a href="<?php echo esc_url( $delete_url ); ?>" onclick="return confirm('<?php esc_attr_e( 'Delete this message?', 'humata-chatbot' ); ?>');"><?php esc_html_e( 'Delete', 'humata-chatbot' ); ?></a>
						</td>
					</tr>
					<tr class="humata-message-detail" id="humata-msg-detail-<?php echo esc_attr( $msg['id'] ); ?>" style="display: none;">
						<td colspan="6" style="background: #f9f9f9; padding: 15px;">
							<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
								<div>
									<h4 style="margin-top: 0;"><?php esc_html_e( 'User Message', 'humata-chatbot' ); ?></h4>
									<p><?php echo esc_html( $msg['user_message'] ); ?></p>

									<h4><?php esc_html_e( 'Bot Response', 'humata-chatbot' ); ?></h4>
									<p><?php echo esc_html( wp_trim_words( $msg['bot_response'] ?? '', 100 ) ); ?></p>
								</div>
								<div>
									<?php if ( ! empty( $msg['summary'] ) ) : ?>
										<h4 style="margin-top: 0;"><?php esc_html_e( 'AI Summary', 'humata-chatbot' ); ?></h4>
										<p><?php echo esc_html( $msg['summary'] ); ?></p>
									<?php endif; ?>

									<?php if ( ! empty( $msg['intent'] ) ) : ?>
										<p><strong><?php esc_html_e( 'Intent:', 'humata-chatbot' ); ?></strong> <?php echo esc_html( $msg['intent'] ); ?></p>
									<?php endif; ?>

									<?php if ( ! empty( $msg['sentiment'] ) ) : ?>
										<p><strong><?php esc_html_e( 'Sentiment:', 'humata-chatbot' ); ?></strong> <?php echo esc_html( $msg['sentiment'] ); ?></p>
									<?php endif; ?>

									<p style="color: #666; font-size: 12px;">
										<?php esc_html_e( 'Provider:', 'humata-chatbot' ); ?> <?php echo esc_html( $msg['provider_used'] ?? 'N/A' ); ?>
										| <?php esc_html_e( 'Response Time:', 'humata-chatbot' ); ?> <?php echo esc_html( $msg['response_time_ms'] ?? 0 ); ?>ms
									</p>
								</div>
							</div>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<script>
		document.addEventListener('DOMContentLoaded', function() {
			// Toggle message details
			document.querySelectorAll('.humata-view-message').forEach(function(link) {
				link.addEventListener('click', function(e) {
					e.preventDefault();
					var id = this.getAttribute('data-id');
					var detail = document.getElementById('humata-msg-detail-' + id);
					if (detail) {
						detail.style.display = detail.style.display === 'none' ? 'table-row' : 'none';
					}
				});
			});

			// Select all checkbox
			var selectAll = document.getElementById('humata-select-all-msgs');
			if (selectAll) {
				selectAll.addEventListener('change', function() {
					document.querySelectorAll('input[name="humata_msg_ids[]"]').forEach(function(cb) {
						cb.checked = selectAll.checked;
					});
				});
			}
		});
		</script>
		<?php
	}

	/**
	 * Render sessions table.
	 *
	 * @since 1.2.0
	 * @param array                              $sessions Sessions array.
	 * @param Humata_Chatbot_Rest_Message_Logger $logger   Logger instance.
	 * @return void
	 */
	private function render_sessions_table( $sessions, $logger ) {
		?>
		<table class="wp-list-table widefat striped">
			<thead>
				<tr>
					<th style="width: 200px;"><?php esc_html_e( 'Session ID', 'humata-chatbot' ); ?></th>
					<th style="width: 100px;"><?php esc_html_e( 'Messages', 'humata-chatbot' ); ?></th>
					<th><?php esc_html_e( 'Time Range', 'humata-chatbot' ); ?></th>
					<th><?php esc_html_e( 'Page', 'humata-chatbot' ); ?></th>
					<th style="width: 80px;"><?php esc_html_e( 'Actions', 'humata-chatbot' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $sessions as $session ) : ?>
					<tr>
						<td>
							<code><?php echo esc_html( substr( $session['session_id'], 0, 20 ) ); ?>...</code>
						</td>
						<td><?php echo esc_html( $session['message_count'] ); ?></td>
						<td>
							<?php echo esc_html( wp_date( 'M j, g:i a', strtotime( $session['first_message'] ) ) ); ?>
							-
							<?php echo esc_html( wp_date( 'g:i a', strtotime( $session['last_message'] ) ) ); ?>
						</td>
						<td>
							<?php echo esc_html( wp_parse_url( $session['page_url'] ?? '', PHP_URL_PATH ) ?: 'N/A' ); ?>
						</td>
						<td>
							<a href="#" class="humata-view-session" data-session="<?php echo esc_attr( $session['session_id'] ); ?>"><?php esc_html_e( 'View', 'humata-chatbot' ); ?></a>
						</td>
					</tr>
					<tr class="humata-session-detail" id="humata-session-<?php echo esc_attr( md5( $session['session_id'] ) ); ?>" style="display: none;">
						<td colspan="5" style="background: #f9f9f9; padding: 15px;">
							<div class="humata-session-messages" data-session="<?php echo esc_attr( $session['session_id'] ); ?>">
								<em><?php esc_html_e( 'Loading...', 'humata-chatbot' ); ?></em>
							</div>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<script>
		document.addEventListener('DOMContentLoaded', function() {
			document.querySelectorAll('.humata-view-session').forEach(function(link) {
				link.addEventListener('click', function(e) {
					e.preventDefault();
					var sessionId = this.getAttribute('data-session');

					// Find the detail row by data attribute
					var rows = document.querySelectorAll('.humata-session-detail');
					rows.forEach(function(row) {
						var container = row.querySelector('.humata-session-messages');
						if (container && container.getAttribute('data-session') === sessionId) {
							if (row.style.display === 'none') {
								row.style.display = 'table-row';
								// Load messages if not already loaded
								if (container.querySelector('em')) {
									loadSessionMessages(sessionId, container);
								}
							} else {
								row.style.display = 'none';
							}
						}
					});
				});
			});

			function loadSessionMessages(sessionId, container) {
				container.innerHTML = '<em><?php echo esc_js( __( 'Loading...', 'humata-chatbot' ) ); ?></em>';

				var formData = new FormData();
				formData.append('action', 'humata_get_session_messages');
				formData.append('session_id', sessionId);
				formData.append('nonce', '<?php echo esc_js( wp_create_nonce( 'humata_admin_nonce' ) ); ?>');

				fetch(ajaxurl, {
					method: 'POST',
					body: formData
				})
				.then(function(response) { return response.json(); })
				.then(function(data) {
					if (data.success && data.data.html) {
						container.innerHTML = data.data.html;
					} else {
						container.innerHTML = '<p style="color: #a00;">' + (data.data && data.data.message ? data.data.message : '<?php echo esc_js( __( 'Failed to load messages.', 'humata-chatbot' ) ); ?>') + '</p>';
					}
				})
				.catch(function() {
					container.innerHTML = '<p style="color: #a00;"><?php echo esc_js( __( 'Failed to load messages.', 'humata-chatbot' ) ); ?></p>';
				});
			}
		});
		</script>
		<?php
	}

	/**
	 * Render pagination.
	 *
	 * @since 1.2.0
	 * @param int    $current   Current page.
	 * @param int    $total     Total pages.
	 * @param int    $items     Total items.
	 * @param string $view_mode View mode (messages/sessions).
	 * @param array  $filters   Current filters.
	 * @return void
	 */
	private function render_pagination( $current, $total, $items, $view_mode, $filters ) {
		$args = array(
			'page' => 'humata-chatbot',
			'tab'  => 'analytics',
			'view' => $view_mode,
		);

		// Preserve filters.
		$args = array_merge( $args, $filters );

		$base_url = add_query_arg( $args, admin_url( 'options-general.php' ) );
		?>
		<div class="tablenav">
			<div class="tablenav-pages">
				<span class="displaying-num">
					<?php
					printf(
						/* translators: %s: number of items */
						esc_html( _n( '%s item', '%s items', $items, 'humata-chatbot' ) ),
						esc_html( number_format_i18n( $items ) )
					);
					?>
				</span>
				<span class="pagination-links">
					<?php if ( $current > 1 ) : ?>
						<a class="first-page button" href="<?php echo esc_url( add_query_arg( 'paged', 1, $base_url ) ); ?>">&laquo;</a>
						<a class="prev-page button" href="<?php echo esc_url( add_query_arg( 'paged', $current - 1, $base_url ) ); ?>">&lsaquo;</a>
					<?php else : ?>
						<span class="tablenav-pages-navspan button disabled">&laquo;</span>
						<span class="tablenav-pages-navspan button disabled">&lsaquo;</span>
					<?php endif; ?>

					<span class="paging-input">
						<?php echo esc_html( $current ); ?> <?php esc_html_e( 'of', 'humata-chatbot' ); ?> <?php echo esc_html( $total ); ?>
					</span>

					<?php if ( $current < $total ) : ?>
						<a class="next-page button" href="<?php echo esc_url( add_query_arg( 'paged', $current + 1, $base_url ) ); ?>">&rsaquo;</a>
						<a class="last-page button" href="<?php echo esc_url( add_query_arg( 'paged', $total, $base_url ) ); ?>">&raquo;</a>
					<?php else : ?>
						<span class="tablenav-pages-navspan button disabled">&rsaquo;</span>
						<span class="tablenav-pages-navspan button disabled">&raquo;</span>
					<?php endif; ?>
				</span>
			</div>
		</div>
		<?php
	}

	/**
	 * Render export form.
	 *
	 * @since 1.2.0
	 * @return void
	 */
	private function render_export_form() {
		?>
		<div class="card">
			<h2><?php esc_html_e( 'Export Data', 'humata-chatbot' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Export message history to CSV format.', 'humata-chatbot' ); ?></p>

			<form method="post">
				<?php wp_nonce_field( 'humata_analytics_export', 'humata_analytics_export_nonce' ); ?>

				<p>
					<label><?php esc_html_e( 'Date Range (optional):', 'humata-chatbot' ); ?></label><br>
					<input type="date" name="export_date_from" placeholder="<?php esc_attr_e( 'From', 'humata-chatbot' ); ?>" />
					<input type="date" name="export_date_to" placeholder="<?php esc_attr_e( 'To', 'humata-chatbot' ); ?>" />
				</p>

				<p>
					<button type="submit" name="humata_analytics_export" class="button">
						<?php esc_html_e( 'Export to CSV', 'humata-chatbot' ); ?>
					</button>
				</p>
			</form>
		</div>
		<?php
	}
}
