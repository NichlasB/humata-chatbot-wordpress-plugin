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
	const SCHEMA_VERSION = '1.0';

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

		// Create directory if it doesn't exist.
		if ( ! file_exists( $db_dir ) ) {
			if ( ! wp_mkdir_p( $db_dir ) ) {
				error_log( '[Humata Chatbot] Failed to create database directory: ' . $db_dir );
				return new WP_Error(
					'db_dir_error',
					__( 'Failed to create database directory.', 'humata-chatbot' )
				);
			}

			// Add .htaccess to protect the directory.
			$htaccess_path = $db_dir . '/.htaccess';
			if ( ! file_exists( $htaccess_path ) ) {
				file_put_contents( $htaccess_path, "Deny from all\n" );
			}

			// Add index.php for extra protection.
			$index_path = $db_dir . '/index.php';
			if ( ! file_exists( $index_path ) ) {
				file_put_contents( $index_path, "<?php\n// Silence is golden.\n" );
			}
		}

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
			// Create documents_meta table for file metadata.
			$db->exec( "
				CREATE TABLE IF NOT EXISTS documents_meta (
					id INTEGER PRIMARY KEY AUTOINCREMENT,
					filename TEXT NOT NULL,
					upload_date TEXT NOT NULL,
					file_size INTEGER NOT NULL DEFAULT 0,
					file_path TEXT NOT NULL,
					section_count INTEGER NOT NULL DEFAULT 0,
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
			'document_count' => 0,
			'section_count'  => 0,
			'db_file_size'   => 0,
			'db_exists'      => false,
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
		if ( ! $this->needs_init() ) {
			return true;
		}

		return $this->create_tables();
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
