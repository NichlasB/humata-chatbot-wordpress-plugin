<?php
/**
 * Local Documents Settings Tab
 *
 * Admin interface for managing local document search functionality.
 * Allows uploading, viewing, and managing .txt documents for FTS5 search.
 *
 * @package Humata_Chatbot
 * @since 1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Humata_Chatbot_Settings_Tab_Documents
 *
 * Handles the Local Documents admin tab.
 *
 * @since 1.0.0
 */
class Humata_Chatbot_Settings_Tab_Documents extends Humata_Chatbot_Settings_Tab_Base {

	/**
	 * Flag to prevent double initialization.
	 *
	 * @since 1.0.0
	 * @var bool
	 */
	private static $initialized = false;

	/**
	 * Get the tab key.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_key() {
		return 'documents';
	}

	/**
	 * Get the tab label.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_label() {
		return __( 'Local Documents', 'humata-chatbot' );
	}

	/**
	 * Get the Settings API page ID.
	 *
	 * @since 1.0.0
	 * @return string
	 */
	public function get_page_id() {
		return 'humata-chatbot-tab-documents';
	}

	/**
	 * This tab does not use the standard settings form.
	 *
	 * @since 1.0.0
	 * @return bool
	 */
	public function has_form() {
		return false;
	}

	/**
	 * Register settings (none for this tab).
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function register() {
		// No settings to register - this tab uses custom forms.
	}

	/**
	 * Initialize the tab - hook into admin_init for form processing.
	 *
	 * @since 1.0.0
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
	 * Handle form actions (upload, delete, reindex).
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function handle_actions() {
		if ( ! isset( $_GET['page'] ) || 'humata-chatbot' !== $_GET['page'] ) {
			return;
		}

		if ( ! isset( $_GET['tab'] ) || 'documents' !== $_GET['tab'] ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Handle upload.
		if ( isset( $_POST['humata_upload_document'] ) ) {
			$this->handle_upload();
		}

		// Handle delete.
		if ( isset( $_GET['action'] ) && 'delete' === $_GET['action'] && isset( $_GET['doc_id'] ) ) {
			$this->handle_delete();
		}

		// Handle reindex.
		if ( isset( $_POST['humata_reindex_all'] ) ) {
			$this->handle_reindex();
		}

		// Handle test search.
		if ( isset( $_POST['humata_test_search'] ) ) {
			$this->handle_test_search();
		}
	}

	/**
	 * Handle document upload.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function handle_upload() {
		if ( ! check_admin_referer( 'humata_upload_document', 'humata_upload_nonce' ) ) {
			add_settings_error(
				'humata_documents',
				'nonce_error',
				__( 'Security check failed. Please try again.', 'humata-chatbot' ),
				'error'
			);
			return;
		}

		if ( empty( $_FILES['humata_documents'] ) || empty( $_FILES['humata_documents']['tmp_name'][0] ) ) {
			add_settings_error(
				'humata_documents',
				'no_file',
				__( 'No files were uploaded.', 'humata-chatbot' ),
				'error'
			);
			return;
		}

		// Load classes.
		require_once HUMATA_CHATBOT_PATH . 'includes/Rest/SearchDatabase.php';
		require_once HUMATA_CHATBOT_PATH . 'includes/Rest/DocumentIndexer.php';

		$database = new Humata_Chatbot_Rest_Search_Database();

		if ( ! $database->is_available() ) {
			add_settings_error(
				'humata_documents',
				'sqlite_unavailable',
				__( 'SQLite3 is not available on this server.', 'humata-chatbot' ),
				'error'
			);
			return;
		}

		// Prepare upload directory.
		$upload_dir = wp_upload_dir();
		$dest_dir   = $upload_dir['basedir'] . '/humata-search/documents';

		if ( ! file_exists( $dest_dir ) ) {
			wp_mkdir_p( $dest_dir );
		}

		$indexer         = new Humata_Chatbot_Rest_Document_Indexer( $database );
		$files           = $_FILES['humata_documents'];
		$file_count      = count( $files['name'] );
		$success_count   = 0;
		$failed_files    = array();

		for ( $i = 0; $i < $file_count; $i++ ) {
			$tmp_name = $files['tmp_name'][ $i ];
			$name     = $files['name'][ $i ];
			$error    = $files['error'][ $i ];

			// Skip empty slots.
			if ( empty( $tmp_name ) ) {
				continue;
			}

			$filename = sanitize_file_name( $name );
			$ext      = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );

			// Check for upload errors.
			if ( UPLOAD_ERR_OK !== $error ) {
				$failed_files[] = $filename . ' (' . __( 'upload error', 'humata-chatbot' ) . ')';
				continue;
			}

			// Validate file type.
			if ( 'txt' !== $ext ) {
				$failed_files[] = $filename . ' (' . __( 'invalid type', 'humata-chatbot' ) . ')';
				continue;
			}

			$dest_path = $dest_dir . '/' . $filename;

			// Handle existing file with same name.
			if ( file_exists( $dest_path ) ) {
				unlink( $dest_path );
			}

			if ( ! move_uploaded_file( $tmp_name, $dest_path ) ) {
				$failed_files[] = $filename . ' (' . __( 'save failed', 'humata-chatbot' ) . ')';
				continue;
			}

			// Index the document.
			$result = $indexer->index_document( $dest_path );

			if ( is_wp_error( $result ) ) {
				unlink( $dest_path );
				$failed_files[] = $filename . ' (' . $result->get_error_message() . ')';
				continue;
			}

			$success_count++;
		}

		// Report results.
		if ( $success_count > 0 ) {
			add_settings_error(
				'humata_documents',
				'upload_success',
				sprintf(
					/* translators: %d: number of files */
					_n(
						'%d document uploaded and indexed successfully.',
						'%d documents uploaded and indexed successfully.',
						$success_count,
						'humata-chatbot'
					),
					$success_count
				),
				'success'
			);
		}

		if ( ! empty( $failed_files ) ) {
			add_settings_error(
				'humata_documents',
				'upload_failures',
				sprintf(
					/* translators: %s: list of failed files */
					__( 'Failed to upload: %s', 'humata-chatbot' ),
					implode( ', ', $failed_files )
				),
				'error'
			);
		}

		if ( 0 === $success_count && empty( $failed_files ) ) {
			add_settings_error(
				'humata_documents',
				'no_valid_files',
				__( 'No valid files were uploaded.', 'humata-chatbot' ),
				'error'
			);
		}
	}

	/**
	 * Handle document deletion.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function handle_delete() {
		$doc_id = absint( $_GET['doc_id'] );

		if ( ! wp_verify_nonce( $_GET['_wpnonce'], 'humata_delete_document_' . $doc_id ) ) {
			add_settings_error(
				'humata_documents',
				'nonce_error',
				__( 'Security check failed. Please try again.', 'humata-chatbot' ),
				'error'
			);
			return;
		}

		require_once HUMATA_CHATBOT_PATH . 'includes/Rest/SearchDatabase.php';
		require_once HUMATA_CHATBOT_PATH . 'includes/Rest/DocumentIndexer.php';

		$database = new Humata_Chatbot_Rest_Search_Database();
		$indexer  = new Humata_Chatbot_Rest_Document_Indexer( $database );

		// Get document info before deletion.
		$doc = $indexer->get_document( $doc_id );

		if ( is_wp_error( $doc ) || null === $doc ) {
			add_settings_error(
				'humata_documents',
				'not_found',
				__( 'Document not found.', 'humata-chatbot' ),
				'error'
			);
			return;
		}

		// Delete from index.
		$result = $indexer->delete_document( $doc_id );

		if ( is_wp_error( $result ) ) {
			add_settings_error(
				'humata_documents',
				'delete_error',
				$result->get_error_message(),
				'error'
			);
			return;
		}

		// Delete the file.
		if ( ! empty( $doc['file_path'] ) && file_exists( $doc['file_path'] ) ) {
			unlink( $doc['file_path'] );
		}

		add_settings_error(
			'humata_documents',
			'delete_success',
			sprintf(
				/* translators: %s: filename */
				__( 'Document "%s" deleted successfully.', 'humata-chatbot' ),
				esc_html( $doc['filename'] )
			),
			'success'
		);

		// Redirect to remove action from URL.
		wp_safe_redirect( admin_url( 'options-general.php?page=humata-chatbot&tab=documents' ) );
		exit;
	}

	/**
	 * Handle reindex all action.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function handle_reindex() {
		if ( ! check_admin_referer( 'humata_reindex_all', 'humata_reindex_nonce' ) ) {
			add_settings_error(
				'humata_documents',
				'nonce_error',
				__( 'Security check failed. Please try again.', 'humata-chatbot' ),
				'error'
			);
			return;
		}

		require_once HUMATA_CHATBOT_PATH . 'includes/Rest/SearchDatabase.php';
		require_once HUMATA_CHATBOT_PATH . 'includes/Rest/DocumentIndexer.php';

		$database = new Humata_Chatbot_Rest_Search_Database();
		$indexer  = new Humata_Chatbot_Rest_Document_Indexer( $database );

		$result = $indexer->reindex_all();

		if ( is_wp_error( $result ) ) {
			add_settings_error(
				'humata_documents',
				'reindex_error',
				$result->get_error_message(),
				'error'
			);
			return;
		}

		$message = sprintf(
			/* translators: 1: success count, 2: failed count */
			__( 'Reindex complete. %1$d documents indexed successfully, %2$d failed.', 'humata-chatbot' ),
			$result['success'],
			$result['failed']
		);

		add_settings_error(
			'humata_documents',
			'reindex_success',
			$message,
			$result['failed'] > 0 ? 'warning' : 'success'
		);
	}

	/**
	 * Handle test search.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function handle_test_search() {
		// Test search results are stored in transient for display.
		if ( ! check_admin_referer( 'humata_test_search', 'humata_test_nonce' ) ) {
			return;
		}

		$query = isset( $_POST['humata_test_query'] ) ? sanitize_text_field( $_POST['humata_test_query'] ) : '';

		if ( empty( $query ) ) {
			return;
		}

		require_once HUMATA_CHATBOT_PATH . 'includes/Rest/SearchDatabase.php';
		require_once HUMATA_CHATBOT_PATH . 'includes/Rest/SearchEngine.php';

		$database = new Humata_Chatbot_Rest_Search_Database();
		$engine   = new Humata_Chatbot_Rest_Search_Engine( $database );

		$results = $engine->search( $query, 5 );

		set_transient( 'humata_test_search_query', $query, 60 );
		set_transient( 'humata_test_search_results', $results, 60 );
	}

	/**
	 * Render the tab content.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render() {
		require_once HUMATA_CHATBOT_PATH . 'includes/Rest/SearchDatabase.php';

		$database = new Humata_Chatbot_Rest_Search_Database();

		if ( ! $database->is_available() ) {
			$this->render_sqlite_unavailable();
			return;
		}

		require_once HUMATA_CHATBOT_PATH . 'includes/Rest/DocumentIndexer.php';
		require_once HUMATA_CHATBOT_PATH . 'includes/Rest/SearchEngine.php';

		$indexer = new Humata_Chatbot_Rest_Document_Indexer( $database );
		$stats   = $database->get_stats();

		// Initialize database if needed.
		$database->maybe_init();

		?>
		<div class="humata-documents-tab">
			<?php $this->render_upload_form(); ?>
			<?php $this->render_document_list( $indexer ); ?>
			<?php $this->render_stats_box( $stats ); ?>
			<?php $this->render_test_search(); ?>
		</div>
		<?php
	}

	/**
	 * Render SQLite unavailable message.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function render_sqlite_unavailable() {
		?>
		<div class="notice notice-error">
			<p>
				<strong><?php esc_html_e( 'SQLite3 Extension Not Available', 'humata-chatbot' ); ?></strong>
			</p>
			<p>
				<?php esc_html_e( 'The local document search feature requires the SQLite3 PHP extension, which is not available on this server. Please contact your hosting provider to enable SQLite3.', 'humata-chatbot' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Render the upload form.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function render_upload_form() {
		?>
		<div class="card">
			<h2><?php esc_html_e( 'Upload Documents', 'humata-chatbot' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Upload .txt files formatted with ### section headers. Documents will be chunked and indexed for local search. You can select multiple files at once.', 'humata-chatbot' ); ?>
			</p>
			<form method="post" enctype="multipart/form-data">
				<?php wp_nonce_field( 'humata_upload_document', 'humata_upload_nonce' ); ?>
				<p>
					<input type="file" name="humata_documents[]" accept=".txt" multiple required />
				</p>
				<p>
					<button type="submit" name="humata_upload_document" class="button button-primary">
						<?php esc_html_e( 'Upload & Index', 'humata-chatbot' ); ?>
					</button>
				</p>
			</form>
		</div>
		<?php
	}

	/**
	 * Render the document list table with pagination.
	 *
	 * @since 1.0.0
	 * @param Humata_Chatbot_Rest_Document_Indexer $indexer Document indexer instance.
	 * @return void
	 */
	private function render_document_list( $indexer ) {
		$per_page     = 20;
		$current_page = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		$result       = $indexer->get_document_list( $per_page, $current_page );

		if ( is_wp_error( $result ) ) {
			$result = array(
				'documents' => array(),
				'total'     => 0,
				'pages'     => 0,
				'page'      => 1,
			);
		}

		$documents   = $result['documents'];
		$total       = $result['total'];
		$total_pages = $result['pages'];

		?>
		<div class="card">
			<h2>
				<?php esc_html_e( 'Indexed Documents', 'humata-chatbot' ); ?>
				<?php if ( $total > 0 ) : ?>
					<span class="title-count" style="font-size: 13px; font-weight: normal; color: #50575e;">
						(<?php echo esc_html( $total ); ?>)
					</span>
				<?php endif; ?>
			</h2>

			<?php if ( empty( $documents ) && 1 === $current_page ) : ?>
				<p class="description">
					<?php esc_html_e( 'No documents indexed yet. Upload a .txt file to get started.', 'humata-chatbot' ); ?>
				</p>
			<?php else : ?>
				<?php if ( $total_pages > 1 ) : ?>
					<?php $this->render_pagination( $current_page, $total_pages, $total ); ?>
				<?php endif; ?>

				<table class="wp-list-table widefat fixed striped" style="margin-top: 10px;">
					<thead>
						<tr>
							<th style="width: 35%;"><?php esc_html_e( 'Filename', 'humata-chatbot' ); ?></th>
							<th style="width: 18%;"><?php esc_html_e( 'Upload Date', 'humata-chatbot' ); ?></th>
							<th style="width: 10%;"><?php esc_html_e( 'Size', 'humata-chatbot' ); ?></th>
							<th style="width: 10%;"><?php esc_html_e( 'Sections', 'humata-chatbot' ); ?></th>
							<th style="width: 12%;"><?php esc_html_e( 'Status', 'humata-chatbot' ); ?></th>
							<th style="width: 15%;"><?php esc_html_e( 'Actions', 'humata-chatbot' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $documents as $doc ) : ?>
							<tr>
								<td>
									<strong title="<?php echo esc_attr( $doc['filename'] ); ?>">
										<?php echo esc_html( $doc['filename'] ); ?>
									</strong>
								</td>
								<td><?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $doc['upload_date'] ) ) ); ?></td>
								<td><?php echo esc_html( size_format( $doc['file_size'] ) ); ?></td>
								<td><?php echo esc_html( $doc['section_count'] ); ?></td>
								<td>
									<?php if ( $doc['file_exists'] ) : ?>
										<span class="dashicons dashicons-yes-alt" style="color: #00a32a;"></span>
										<?php esc_html_e( 'OK', 'humata-chatbot' ); ?>
									<?php else : ?>
										<span class="dashicons dashicons-warning" style="color: #dba617;"></span>
										<?php esc_html_e( 'Missing', 'humata-chatbot' ); ?>
									<?php endif; ?>
								</td>
								<td>
									<?php
									$delete_url = wp_nonce_url(
										add_query_arg(
											array(
												'page'   => 'humata-chatbot',
												'tab'    => 'documents',
												'action' => 'delete',
												'doc_id' => $doc['id'],
											),
											admin_url( 'options-general.php' )
										),
										'humata_delete_document_' . $doc['id']
									);
									?>
									<a href="<?php echo esc_url( $delete_url ); ?>" class="button button-small" onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to delete this document?', 'humata-chatbot' ); ?>');">
										<?php esc_html_e( 'Delete', 'humata-chatbot' ); ?>
									</a>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>

				<?php if ( $total_pages > 1 ) : ?>
					<?php $this->render_pagination( $current_page, $total_pages, $total ); ?>
				<?php endif; ?>

				<p style="margin-top: 15px;">
					<form method="post" style="display: inline;">
						<?php wp_nonce_field( 'humata_reindex_all', 'humata_reindex_nonce' ); ?>
						<button type="submit" name="humata_reindex_all" class="button">
							<?php esc_html_e( 'Reindex All Documents', 'humata-chatbot' ); ?>
						</button>
					</form>
				</p>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render pagination links.
	 *
	 * @since 1.0.0
	 * @param int $current Current page.
	 * @param int $total   Total pages.
	 * @param int $items   Total items.
	 * @return void
	 */
	private function render_pagination( $current, $total, $items ) {
		$base_url = add_query_arg(
			array(
				'page' => 'humata-chatbot',
				'tab'  => 'documents',
			),
			admin_url( 'options-general.php' )
		);
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
						<a class="first-page button" href="<?php echo esc_url( add_query_arg( 'paged', 1, $base_url ) ); ?>">
							<span aria-hidden="true">&laquo;</span>
						</a>
						<a class="prev-page button" href="<?php echo esc_url( add_query_arg( 'paged', $current - 1, $base_url ) ); ?>">
							<span aria-hidden="true">&lsaquo;</span>
						</a>
					<?php else : ?>
						<span class="tablenav-pages-navspan button disabled" aria-hidden="true">&laquo;</span>
						<span class="tablenav-pages-navspan button disabled" aria-hidden="true">&lsaquo;</span>
					<?php endif; ?>

					<span class="paging-input">
						<span class="tablenav-paging-text">
							<?php echo esc_html( $current ); ?>
							<?php esc_html_e( 'of', 'humata-chatbot' ); ?>
							<span class="total-pages"><?php echo esc_html( $total ); ?></span>
						</span>
					</span>

					<?php if ( $current < $total ) : ?>
						<a class="next-page button" href="<?php echo esc_url( add_query_arg( 'paged', $current + 1, $base_url ) ); ?>">
							<span aria-hidden="true">&rsaquo;</span>
						</a>
						<a class="last-page button" href="<?php echo esc_url( add_query_arg( 'paged', $total, $base_url ) ); ?>">
							<span aria-hidden="true">&raquo;</span>
						</a>
					<?php else : ?>
						<span class="tablenav-pages-navspan button disabled" aria-hidden="true">&rsaquo;</span>
						<span class="tablenav-pages-navspan button disabled" aria-hidden="true">&raquo;</span>
					<?php endif; ?>
				</span>
			</div>
		</div>
		<?php
	}

	/**
	 * Render database statistics box.
	 *
	 * @since 1.0.0
	 * @param array|WP_Error $stats Database stats.
	 * @return void
	 */
	private function render_stats_box( $stats ) {
		if ( is_wp_error( $stats ) ) {
			return;
		}

		?>
		<div class="card">
			<h2><?php esc_html_e( 'Database Statistics', 'humata-chatbot' ); ?></h2>
			<table class="form-table">
				<tr>
					<th><?php esc_html_e( 'Total Documents', 'humata-chatbot' ); ?></th>
					<td><?php echo esc_html( $stats['document_count'] ); ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Total Sections', 'humata-chatbot' ); ?></th>
					<td><?php echo esc_html( $stats['section_count'] ); ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Database Size', 'humata-chatbot' ); ?></th>
					<td><?php echo esc_html( $stats['db_exists'] ? size_format( $stats['db_file_size'] ) : __( 'Not created', 'humata-chatbot' ) ); ?></td>
				</tr>
			</table>
		</div>
		<?php
	}

	/**
	 * Render test search form and results.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	private function render_test_search() {
		$last_query   = get_transient( 'humata_test_search_query' );
		$last_results = get_transient( 'humata_test_search_results' );

		?>
		<div class="card">
			<h2><?php esc_html_e( 'Test Local Search', 'humata-chatbot' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Test the search functionality by entering a query below.', 'humata-chatbot' ); ?>
			</p>
			<form method="post">
				<?php wp_nonce_field( 'humata_test_search', 'humata_test_nonce' ); ?>
				<p>
					<input type="text" name="humata_test_query" value="<?php echo esc_attr( $last_query ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'Enter search query...', 'humata-chatbot' ); ?>" />
					<button type="submit" name="humata_test_search" class="button">
						<?php esc_html_e( 'Search', 'humata-chatbot' ); ?>
					</button>
				</p>
			</form>

			<?php if ( ! empty( $last_query ) && false !== $last_results ) : ?>
				<h3><?php esc_html_e( 'Search Results', 'humata-chatbot' ); ?></h3>
				<?php if ( is_wp_error( $last_results ) ) : ?>
					<p class="description" style="color: red;">
						<?php echo esc_html( $last_results->get_error_message() ); ?>
					</p>
				<?php elseif ( empty( $last_results ) ) : ?>
					<p class="description">
						<?php esc_html_e( 'No results found for your query.', 'humata-chatbot' ); ?>
					</p>
				<?php else : ?>
					<table class="wp-list-table widefat fixed striped">
						<thead>
							<tr>
								<th style="width: 20%;"><?php esc_html_e( 'Section', 'humata-chatbot' ); ?></th>
								<th style="width: 15%;"><?php esc_html_e( 'Document', 'humata-chatbot' ); ?></th>
								<th><?php esc_html_e( 'Content Preview', 'humata-chatbot' ); ?></th>
								<th style="width: 10%;"><?php esc_html_e( 'Score', 'humata-chatbot' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $last_results as $result ) : ?>
								<tr>
									<td><strong><?php echo esc_html( $result['section_header'] ); ?></strong></td>
									<td><?php echo esc_html( $result['doc_name'] ); ?></td>
									<td><?php echo esc_html( wp_trim_words( $result['content'], 30 ) ); ?></td>
									<td><?php echo esc_html( number_format( abs( $result['score'] ), 4 ) ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			<?php endif; ?>
		</div>
		<?php

		// Clear transients after display.
		delete_transient( 'humata_test_search_query' );
		delete_transient( 'humata_test_search_results' );
	}
}
