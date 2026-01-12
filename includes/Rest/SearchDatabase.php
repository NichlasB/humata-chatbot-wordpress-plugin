<?php
/**
 * SQLite FTS5 Search Database Manager
 *
 * Manages the SQLite database for local document search functionality.
 * Creates and maintains FTS5 virtual tables for full-text search.
 *
 * @package Humata_Chatbot
 * @since 1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Humata_Chatbot_Rest_Search_Database
 *
 * Handles SQLite database operations for local document search.
 *
 * @since 1.0.0
 */
class Humata_Chatbot_Rest_Search_Database {

	/**
	 * Database file path.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	private $db_path;

	/**
	 * SQLite3 connection instance (lazy-loaded singleton).
	 *
	 * @since 1.0.0
	 * @var SQLite3|null
	 */
	private $connection = null;

	/**
	 * Current database schema version.
	 *
	 * @since 1.0.0
	 * @var string
	 */
	const SCHEMA_VERSION = '1.1';

	/**
	 * Constructor.
	 *
	 * Sets up the database path and checks SQLite3 availability.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$upload_dir    = wp_upload_dir();
		$default_path  = $upload_dir['basedir'] . '/humata-search/index.db';

		/**
		 * Filter the SQLite database file path.
		 *
		 * @since 1.0.0
		 * @param string $default_path Default database path.
		 */
		$this->db_path = apply_filters( 'humata_chatbot_search_db_path', $default_path );
	}

	/**
	 * Get the database file path.
	 *
	 * @since 1.0.0
	 * @return string Database file path.
	 */
	public function get_db_path() {
		return $this->db_path;
	}

	/**
	 * Get the database directory path.
	 *
	 * @since 1.0.0
	 * @return string Database directory path.
	 */
	public function get_db_dir() {
		return dirname( $this->db_path );
	}

	/**
	 * Check if SQLite3 extension is available.
	 *
	 * @since 1.0.0
	 * @return bool True if SQLite3 is available, false otherwise.
	 */
	public function is_available() {
		return class_exists( 'SQLite3' );
	}

	/**
	 * Ensure a directory exists and is protected from direct web access.
	 *
	 * Creates the directory if needed, then ensures .htaccess, index.php,
	 * and web.config protection files exist (idempotent).
	 *
	 * @since 1.0.0
	 * @param string $dir Directory path to protect.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function ensure_directory_protected( $dir ) {
		$dir = untrailingslashit( $dir );

		if ( ! file_exists( $dir ) ) {
			if ( ! wp_mkdir_p( $dir ) ) {
				error_log( '[Humata Chatbot] Failed to create directory: ' . $dir );
				return new WP_Error(
					'dir_create_error',
					__( 'Failed to create directory.', 'humata-chatbot' )
				);
			}
		}

		// Apache: .htaccess
		$htaccess_path = $dir . '/.htaccess';
		if ( ! file_exists( $htaccess_path ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			$result = file_put_contents( $htaccess_path, "Deny from all\n", LOCK_EX );
			if ( false === $result && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'Humata Chatbot: Failed to create .htaccess in ' . $dir );
			}
		}

		// Fallback: index.php
		$index_path = $dir . '/index.php';
		if ( ! file_exists( $index_path ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			$result = file_put_contents( $index_path, "<?php\n// Silence is golden.\n", LOCK_EX );
			if ( false === $result && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'Humata Chatbot: Failed to create index.php in ' . $dir );
			}
		}

		// IIS: web.config
		$webconfig_path = $dir . '/web.config';
		if ( ! file_exists( $webconfig_path ) ) {
			$webconfig_content = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
				. '<configuration>' . "\n"
				. '  <system.webServer>' . "\n"
				. '    <authorization>' . "\n"
				. '      <deny users="*" />' . "\n"
				. '    </authorization>' . "\n"
				. '  </system.webServer>' . "\n"
				. '</configuration>' . "\n";
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			$result = file_put_contents( $webconfig_path, $webconfig_content, LOCK_EX );
			if ( false === $result && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'Humata Chatbot: Failed to create web.config in ' . $dir );
			}
		}

		return true;
	}

	/**
	 * Get the SQLite3 database connection.
	 *
	 * Lazy-loads and returns a singleton SQLite3 instance.
	 * Creates the database directory and file if they don't exist.
	 *
	 * @since 1.0.0
	 * @return SQLite3|WP_Error SQLite3 instance or WP_Error on failure.
	 */
	public function get_connection() {
		if ( null !== $this->connection ) {
			return $this->connection;
		}

		if ( ! $this->is_available() ) {
			return new WP_Error(
				'sqlite_unavailable',
				__( 'SQLite3 extension is not available on this server.', 'humata-chatbot' )
			);
		}

		$db_dir = $this->get_db_dir();

		// Ensure DB directory is protected (idempotent).
		$protect_result = $this->ensure_directory_protected( $db_dir );
		if ( is_wp_error( $protect_result ) ) {
			return $protect_result;
		}

		// Also protect the documents subdirectory.
		$docs_dir = $db_dir . '/documents';
		$this->ensure_directory_protected( $docs_dir );

		try {
			$this->connection = new SQLite3( $this->db_path );
			$this->connection->enableExceptions( true );

			// Enable WAL mode for better concurrent access.
			$this->connection->exec( 'PRAGMA journal_mode = WAL' );
			$this->connection->exec( 'PRAGMA synchronous = NORMAL' );

			return $this->connection;
		} catch ( Exception $e ) {
			error_log( '[Humata Chatbot] SQLite connection error: ' . $e->getMessage() );
			return new WP_Error(
				'db_connection_error',
				__( 'Failed to connect to the search database.', 'humata-chatbot' )
			);
		}
	}

	/**
	 * Create database tables.
	 *
	 * Creates the FTS5 virtual table and documents_meta table.
	 *
	 * @since 1.0.0
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function create_tables() {
		$db = $this->get_connection();

		if ( is_wp_error( $db ) ) {
			return $db;
		}

		try {
			// Create document_categories table.
			$db->exec( "
				CREATE TABLE IF NOT EXISTS document_categories (
					id INTEGER PRIMARY KEY AUTOINCREMENT,
					name TEXT NOT NULL UNIQUE,
					sort_order INTEGER DEFAULT 0
				)
			" );

			// Create documents_meta table for file metadata.
			$db->exec( "
				CREATE TABLE IF NOT EXISTS documents_meta (
					id INTEGER PRIMARY KEY AUTOINCREMENT,
					filename TEXT NOT NULL,
					upload_date TEXT NOT NULL,
					file_size INTEGER NOT NULL DEFAULT 0,
					file_path TEXT NOT NULL,
					section_count INTEGER NOT NULL DEFAULT 0,
					category_id INTEGER DEFAULT NULL,
					created_at TEXT DEFAULT CURRENT_TIMESTAMP
				)
			" );

			// Create unique index on filename to prevent duplicates.
			$db->exec( "
				CREATE UNIQUE INDEX IF NOT EXISTS idx_documents_meta_filename 
				ON documents_meta(filename)
			" );

			// Create FTS5 virtual table for full-text search.
			// Columns: doc_id (UNINDEXED), doc_name, section_header, keywords, content, chunk_index (UNINDEXED)
			// Using porter stemmer and unicode61 tokenizer for better search.
			$db->exec( "
				CREATE VIRTUAL TABLE IF NOT EXISTS documents_fts USING fts5(
					doc_id UNINDEXED,
					doc_name,
					section_header,
					keywords,
					content,
					chunk_index UNINDEXED,
					tokenize='porter unicode61'
				)
			" );

			// Store schema version.
			$this->set_db_version( self::SCHEMA_VERSION );

			return true;
		} catch ( Exception $e ) {
			error_log( '[Humata Chatbot] Failed to create tables: ' . $e->getMessage() );
			return new WP_Error(
				'table_creation_error',
				__( 'Failed to create database tables.', 'humata-chatbot' )
			);
		}
	}

	/**
	 * Drop all database tables.
	 *
	 * @since 1.0.0
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function drop_tables() {
		$db = $this->get_connection();

		if ( is_wp_error( $db ) ) {
			return $db;
		}

		try {
			$db->exec( 'DROP TABLE IF EXISTS documents_fts' );
			$db->exec( 'DROP TABLE IF EXISTS documents_meta' );
			$db->exec( 'DROP TABLE IF EXISTS document_categories' );

			return true;
		} catch ( Exception $e ) {
			error_log( '[Humata Chatbot] Failed to drop tables: ' . $e->getMessage() );
			return new WP_Error(
				'table_drop_error',
				__( 'Failed to drop database tables.', 'humata-chatbot' )
			);
		}
	}

	/**
	 * Get database statistics.
	 *
	 * @since 1.0.0
	 * @return array|WP_Error Stats array or WP_Error on failure.
	 */
	public function get_stats() {
		$stats = array(
			'document_count'  => 0,
			'section_count'   => 0,
			'category_count'  => 0,
			'db_file_size'    => 0,
			'db_exists'       => false,
		);

		if ( ! file_exists( $this->db_path ) ) {
			return $stats;
		}

		$stats['db_exists']    = true;
		$stats['db_file_size'] = filesize( $this->db_path );

		$db = $this->get_connection();

		if ( is_wp_error( $db ) ) {
			return $db;
		}

		try {
			// Count documents.
			$result = $db->querySingle( 'SELECT COUNT(*) FROM documents_meta' );
			$stats['document_count'] = (int) $result;

			// Count sections.
			$result = $db->querySingle( 'SELECT COUNT(*) FROM documents_fts' );
			$stats['section_count'] = (int) $result;

			// Count categories.
			$result = $db->querySingle( 'SELECT COUNT(*) FROM document_categories' );
			$stats['category_count'] = (int) $result;

			return $stats;
		} catch ( Exception $e ) {
			// Tables might not exist yet.
			return $stats;
		}
	}

	/**
	 * Get the stored database schema version.
	 *
	 * @since 1.0.0
	 * @return string Database version or empty string if not set.
	 */
	public function get_db_version() {
		return get_option( 'humata_search_db_version', '' );
	}

	/**
	 * Set the database schema version.
	 *
	 * @since 1.0.0
	 * @param string $version Version string.
	 * @return bool True on success.
	 */
	public function set_db_version( $version ) {
		return update_option( 'humata_search_db_version', sanitize_text_field( $version ) );
	}

	/**
	 * Check if the database needs initialization.
	 *
	 * @since 1.0.0
	 * @return bool True if database needs initialization.
	 */
	public function needs_init() {
		if ( ! file_exists( $this->db_path ) ) {
			return true;
		}

		$version = $this->get_db_version();
		return empty( $version );
	}

	/**
	 * Initialize the database if needed.
	 *
	 * @since 1.0.0
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function maybe_init() {
		$current_version = $this->get_db_version();

		// Fresh install.
		if ( empty( $current_version ) || ! file_exists( $this->db_path ) ) {
			return $this->create_tables();
		}

		// Check if migration needed.
		if ( version_compare( $current_version, self::SCHEMA_VERSION, '<' ) ) {
			return $this->migrate( $current_version );
		}

		return true;
	}

	/**
	 * Migrate database schema from older version.
	 *
	 * @since 1.1.0
	 * @param string $from_version Current schema version.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	private function migrate( $from_version ) {
		$db = $this->get_connection();

		if ( is_wp_error( $db ) ) {
			return $db;
		}

		try {
			// 1.0 → 1.1: Add document categories support.
			if ( version_compare( $from_version, '1.1', '<' ) ) {
				// Create categories table.
				$db->exec( "
					CREATE TABLE IF NOT EXISTS document_categories (
						id INTEGER PRIMARY KEY AUTOINCREMENT,
						name TEXT NOT NULL UNIQUE,
						sort_order INTEGER DEFAULT 0
					)
				" );

				// Check if category_id column exists in documents_meta.
				$columns      = $db->query( 'PRAGMA table_info(documents_meta)' );
				$has_category = false;

				while ( $row = $columns->fetchArray( SQLITE3_ASSOC ) ) {
					if ( 'category_id' === $row['name'] ) {
						$has_category = true;
						break;
					}
				}

				// Add category_id column if missing.
				if ( ! $has_category ) {
					$db->exec( 'ALTER TABLE documents_meta ADD COLUMN category_id INTEGER DEFAULT NULL' );
				}
			}

			// Update version.
			$this->set_db_version( self::SCHEMA_VERSION );

			return true;
		} catch ( Exception $e ) {
			error_log( '[Humata Chatbot] Migration error: ' . $e->getMessage() );
			return new WP_Error(
				'migration_error',
				__( 'Failed to migrate database schema.', 'humata-chatbot' )
			);
		}
	}

	/**
	 * Close the database connection.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function close() {
		if ( null !== $this->connection ) {
			$this->connection->close();
			$this->connection = null;
		}
	}

	/**
	 * Destructor - ensure connection is closed.
	 *
	 * @since 1.0.0
	 */
	public function __destruct() {
		$this->close();
	}
}
