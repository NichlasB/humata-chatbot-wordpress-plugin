<?php
/**
 * Document Indexer
 *
 * Parses and indexes .txt documents into the SQLite FTS5 database.
 * Supports two document formats:
 * - Legacy: ### section headers with KEYWORDS: lines
 * - Structured: --- delimited chunks with TITLE:, KEYWORDS:, CONTENT:, QUESTION:, ANSWER: fields
 *
 * @package Humata_Chatbot
 * @since 1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Humata_Chatbot_Rest_Document_Indexer
 *
 * Handles document parsing, chunking, and indexing operations.
 *
 * @since 1.0.0
 */
class Humata_Chatbot_Rest_Document_Indexer {

	/**
	 * Search database instance.
	 *
	 * @since 1.0.0
	 * @var Humata_Chatbot_Rest_Search_Database
	 */
	private $database;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 * @param Humata_Chatbot_Rest_Search_Database $database Search database instance.
	 */
	public function __construct( Humata_Chatbot_Rest_Search_Database $database ) {
		$this->database = $database;
	}

	/**
	 * Index a document from file path.
	 *
	 * Reads a .txt file, parses it into sections, and indexes into FTS5.
	 *
	 * @since 1.0.0
	 * @param string $file_path Full path to the .txt file.
	 * @return int|WP_Error Document ID on success, WP_Error on failure.
	 */
	public function index_document( $file_path ) {
		if ( ! file_exists( $file_path ) ) {
			return new WP_Error(
				'file_not_found',
				__( 'The specified file does not exist.', 'humata-chatbot' )
			);
		}

		if ( ! is_readable( $file_path ) ) {
			return new WP_Error(
				'file_not_readable',
				__( 'The specified file is not readable.', 'humata-chatbot' )
			);
		}

		// Read file content.
		$content = file_get_contents( $file_path );

		if ( false === $content ) {
			return new WP_Error(
				'file_read_error',
				__( 'Failed to read file content.', 'humata-chatbot' )
			);
		}

		// Normalize line endings.
		$content = str_replace( array( "\r\n", "\r" ), "\n", $content );

		// Get file info.
		$filename  = basename( $file_path );
		$file_size = filesize( $file_path );

		// Parse sections from content.
		$sections = $this->parse_sections( $content, $filename );

		if ( empty( $sections ) ) {
			return new WP_Error(
				'no_sections',
				__( 'No valid sections found in the document.', 'humata-chatbot' )
			);
		}

		// Ensure database is initialized.
		$init_result = $this->database->maybe_init();
		if ( is_wp_error( $init_result ) ) {
			return $init_result;
		}

		$db = $this->database->get_connection();
		if ( is_wp_error( $db ) ) {
			return $db;
		}

		try {
			// Begin transaction.
			$db->exec( 'BEGIN TRANSACTION' );

			// Check for existing document with same filename.
			$stmt = $db->prepare( 'SELECT id FROM documents_meta WHERE filename = :filename' );
			$stmt->bindValue( ':filename', $filename, SQLITE3_TEXT );
			$result = $stmt->execute();
			$existing = $result->fetchArray( SQLITE3_ASSOC );

			if ( $existing ) {
				// Delete existing document and its sections.
				$this->delete_document_internal( $db, (int) $existing['id'] );
			}

			// Insert document metadata.
			$stmt = $db->prepare( "
				INSERT INTO documents_meta (filename, upload_date, file_size, file_path, section_count)
				VALUES (:filename, :upload_date, :file_size, :file_path, :section_count)
			" );

			$stmt->bindValue( ':filename', $filename, SQLITE3_TEXT );
			$stmt->bindValue( ':upload_date', gmdate( 'Y-m-d H:i:s' ), SQLITE3_TEXT );
			$stmt->bindValue( ':file_size', $file_size, SQLITE3_INTEGER );
			$stmt->bindValue( ':file_path', $file_path, SQLITE3_TEXT );
			$stmt->bindValue( ':section_count', count( $sections ), SQLITE3_INTEGER );
			$stmt->execute();

			$doc_id = $db->lastInsertRowID();

			// Insert sections into FTS5 table.
			$stmt = $db->prepare( "
				INSERT INTO documents_fts (doc_id, doc_name, section_header, keywords, content, chunk_index)
				VALUES (:doc_id, :doc_name, :section_header, :keywords, :content, :chunk_index)
			" );

			foreach ( $sections as $index => $section ) {
				$stmt->bindValue( ':doc_id', $doc_id, SQLITE3_INTEGER );
				$stmt->bindValue( ':doc_name', $filename, SQLITE3_TEXT );
				$stmt->bindValue( ':section_header', $section['header'], SQLITE3_TEXT );
				$stmt->bindValue( ':keywords', $section['keywords'], SQLITE3_TEXT );
				$stmt->bindValue( ':content', $section['content'], SQLITE3_TEXT );
				$stmt->bindValue( ':chunk_index', $index, SQLITE3_INTEGER );
				$stmt->execute();
				$stmt->reset();
			}

			// Commit transaction.
			$db->exec( 'COMMIT' );

			return $doc_id;
		} catch ( Exception $e ) {
			$db->exec( 'ROLLBACK' );
			error_log( '[Humata Chatbot] Document indexing error: ' . $e->getMessage() );
			return new WP_Error(
				'indexing_error',
				__( 'Failed to index document.', 'humata-chatbot' )
			);
		}
	}

	/**
	 * Parse document content into sections.
	 *
	 * Supports two formats:
	 * - Legacy: ### section headers with KEYWORDS: lines
	 * - Structured: --- delimited chunks with labeled fields
	 *
	 * @since 1.0.0
	 * @param string $content Full document content.
	 * @param string $filename Document filename for reference.
	 * @return array Array of section arrays with header, keywords, content.
	 */
	private function parse_sections( $content, $filename ) {
		// Detect format and delegate to appropriate parser.
		if ( $this->is_structured_format( $content ) ) {
			return $this->parse_structured_sections( $content, $filename );
		}

		return $this->parse_legacy_sections( $content, $filename );
	}

	/**
	 * Detect if content uses structured format (--- delimiters).
	 *
	 * @since 1.0.0
	 * @param string $content Document content.
	 * @return bool True if structured format, false for legacy.
	 */
	private function is_structured_format( $content ) {
		// Check for --- delimiter pattern with labeled fields.
		// Must have at least one --- followed by a field like TITLE: or CONTENT:.
		return (bool) preg_match( '/^---\s*$/m', $content )
			&& ( preg_match( '/^TITLE:\s*.+$/mi', $content )
				|| preg_match( '/^CONTENT:\s*/mi', $content )
				|| preg_match( '/^ANSWER:\s*/mi', $content ) );
	}

	/**
	 * Parse structured format with --- delimiters.
	 *
	 * Recognized fields:
	 * - TITLE: → section_header
	 * - KEYWORDS: → keywords
	 * - QUESTION: → appended to keywords for better matching
	 * - CONTENT: or ANSWER: → content
	 *
	 * Ignored fields: CHUNK_ID, CATEGORY, RELATED, TYPE, SOURCE_CHUNK, SUMMARY
	 *
	 * @since 1.0.0
	 * @param string $content Full document content.
	 * @param string $filename Document filename for reference.
	 * @return array Array of section arrays.
	 */
	private function parse_structured_sections( $content, $filename ) {
		$sections = array();

		// Split on --- delimiter lines.
		$chunks = preg_split( '/^---\s*$/m', $content );

		foreach ( $chunks as $chunk ) {
			$chunk = trim( $chunk );

			if ( empty( $chunk ) ) {
				continue;
			}

			$section = $this->parse_structured_chunk( $chunk, $filename );

			if ( null !== $section ) {
				$sections[] = $section;
			}
		}

		return $sections;
	}

	/**
	 * Parse a single structured chunk into section data.
	 *
	 * @since 1.0.0
	 * @param string $chunk Raw chunk content.
	 * @param string $filename Document filename for fallback header.
	 * @return array|null Section array or null if empty.
	 */
	private function parse_structured_chunk( $chunk, $filename ) {
		$header   = '';
		$keywords = '';
		$question = '';
		$content  = '';

		// Extract TITLE: field.
		if ( preg_match( '/^TITLE:\s*(.+?)$/mi', $chunk, $match ) ) {
			$header = trim( $match[1] );
		}

		// Extract KEYWORDS: field.
		if ( preg_match( '/^KEYWORDS:\s*(.+?)$/mi', $chunk, $match ) ) {
			$keywords = trim( $match[1] );
		}

		// Extract QUESTION: field (for Q&A chunks).
		if ( preg_match( '/^QUESTION:\s*(.+?)$/mi', $chunk, $match ) ) {
			$question = trim( $match[1] );
		}

		// Extract CONTENT: field (may be multi-line).
		if ( preg_match( '/^CONTENT:\s*\n?(.*?)(?=^[A-Z_]+:|\z)/msi', $chunk, $match ) ) {
			$content = trim( $match[1] );
		}

		// If no CONTENT:, try ANSWER: field (for Q&A chunks).
		if ( empty( $content ) && preg_match( '/^ANSWER:\s*\n?(.*?)(?=^[A-Z_]+:|\z)/msi', $chunk, $match ) ) {
			$content = trim( $match[1] );
		}

		// If still no content, use the whole chunk minus known fields.
		if ( empty( $content ) ) {
			$content = $this->extract_remaining_content( $chunk );
		}

		// Skip empty chunks.
		if ( empty( $content ) && empty( $header ) ) {
			return null;
		}

		// Use filename as header if none found.
		if ( empty( $header ) && ! empty( $content ) ) {
			$header = pathinfo( $filename, PATHINFO_FILENAME );
		}

		// Append question to keywords for better search matching.
		if ( ! empty( $question ) ) {
			$keywords = ! empty( $keywords )
				? $keywords . ', ' . $question
				: $question;
		}

		return array(
			'header'   => $header,
			'keywords' => $keywords,
			'content'  => $content,
		);
	}

	/**
	 * Extract remaining content after removing known field labels.
	 *
	 * @since 1.0.0
	 * @param string $chunk Raw chunk content.
	 * @return string Cleaned content.
	 */
	private function extract_remaining_content( $chunk ) {
		// List of field prefixes to remove.
		$fields_to_remove = array(
			'CHUNK_ID',
			'TITLE',
			'KEYWORDS',
			'CATEGORY',
			'RELATED',
			'TYPE',
			'SOURCE_CHUNK',
			'SUMMARY',
			'QUESTION',
			'ANSWER',
			'CONTENT',
		);

		$pattern = '/^(' . implode( '|', $fields_to_remove ) . '):\s*.+?$/mi';
		$content = preg_replace( $pattern, '', $chunk );

		return trim( $content );
	}

	/**
	 * Parse legacy format with ### section headers.
	 *
	 * @since 1.0.0
	 * @param string $content Full document content.
	 * @param string $filename Document filename for reference.
	 * @return array Array of section arrays with header, keywords, content.
	 */
	private function parse_legacy_sections( $content, $filename ) {
		$sections = array();

		// Extract and remove === header block if present.
		if ( preg_match( '/^===+\s*\n(.*?)\n===+/s', $content, $header_match ) ) {
			$content = substr( $content, strlen( $header_match[0] ) );
		}

		// Split on ### markers (section headers).
		$parts = preg_split( '/(?=^###\s)/m', $content );

		foreach ( $parts as $part ) {
			$part = trim( $part );

			if ( empty( $part ) ) {
				continue;
			}

			$header   = '';
			$keywords = '';
			$body     = $part;

			// Extract section header (### line).
			if ( preg_match( '/^###\s*(.+?)$/m', $part, $header_match ) ) {
				$header = trim( $header_match[1] );
				// Remove the header line from body.
				$body = trim( preg_replace( '/^###\s*.+?\n/m', '', $part, 1 ) );
			}

			// Extract KEYWORDS: line if present.
			if ( preg_match( '/^KEYWORDS:\s*(.+?)$/mi', $body, $keywords_match ) ) {
				$keywords = trim( $keywords_match[1] );
				// Remove KEYWORDS line from body.
				$body = trim( preg_replace( '/^KEYWORDS:\s*.+?\n?/mi', '', $body ) );
			}

			// Skip empty sections.
			$body = trim( $body );
			if ( empty( $body ) && empty( $header ) ) {
				continue;
			}

			// If no header but content exists, use filename as header.
			if ( empty( $header ) && ! empty( $body ) ) {
				$header = pathinfo( $filename, PATHINFO_FILENAME );
			}

			$sections[] = array(
				'header'   => $header,
				'keywords' => $keywords,
				'content'  => $body,
			);
		}

		return $sections;
	}

	/**
	 * Delete a document by ID.
	 *
	 * Removes document from both metadata and FTS tables.
	 *
	 * @since 1.0.0
	 * @param int $doc_id Document ID.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function delete_document( $doc_id ) {
		$doc_id = absint( $doc_id );

		if ( $doc_id <= 0 ) {
			return new WP_Error(
				'invalid_doc_id',
				__( 'Invalid document ID.', 'humata-chatbot' )
			);
		}

		$db = $this->database->get_connection();
		if ( is_wp_error( $db ) ) {
			return $db;
		}

		try {
			$db->exec( 'BEGIN TRANSACTION' );
			$this->delete_document_internal( $db, $doc_id );
			$db->exec( 'COMMIT' );

			return true;
		} catch ( Exception $e ) {
			$db->exec( 'ROLLBACK' );
			error_log( '[Humata Chatbot] Document deletion error: ' . $e->getMessage() );
			return new WP_Error(
				'deletion_error',
				__( 'Failed to delete document.', 'humata-chatbot' )
			);
		}
	}

	/**
	 * Internal document deletion (within transaction).
	 *
	 * @since 1.0.0
	 * @param SQLite3 $db Database connection.
	 * @param int     $doc_id Document ID.
	 * @return void
	 */
	private function delete_document_internal( $db, $doc_id ) {
		// Delete from FTS table.
		$stmt = $db->prepare( 'DELETE FROM documents_fts WHERE doc_id = :doc_id' );
		$stmt->bindValue( ':doc_id', $doc_id, SQLITE3_INTEGER );
		$stmt->execute();

		// Delete from metadata table.
		$stmt = $db->prepare( 'DELETE FROM documents_meta WHERE id = :doc_id' );
		$stmt->bindValue( ':doc_id', $doc_id, SQLITE3_INTEGER );
		$stmt->execute();
	}

	/**
	 * Reindex all documents.
	 *
	 * Clears and rebuilds the entire index from stored file paths.
	 *
	 * @since 1.0.0
	 * @return array|WP_Error Results array with success/failed counts, or WP_Error.
	 */
	public function reindex_all() {
		$db = $this->database->get_connection();
		if ( is_wp_error( $db ) ) {
			return $db;
		}

		$results = array(
			'success' => 0,
			'failed'  => 0,
			'errors'  => array(),
		);

		try {
			// Get all documents with their file paths.
			$query = $db->query( 'SELECT id, filename, file_path FROM documents_meta ORDER BY id' );
			$documents = array();

			while ( $row = $query->fetchArray( SQLITE3_ASSOC ) ) {
				$documents[] = $row;
			}

			if ( empty( $documents ) ) {
				return $results;
			}

			// Drop and recreate tables.
			$drop_result = $this->database->drop_tables();
			if ( is_wp_error( $drop_result ) ) {
				return $drop_result;
			}

			$create_result = $this->database->create_tables();
			if ( is_wp_error( $create_result ) ) {
				return $create_result;
			}

			// Reindex each document.
			foreach ( $documents as $doc ) {
				$file_path = $doc['file_path'];

				if ( ! file_exists( $file_path ) ) {
					++$results['failed'];
					$results['errors'][] = sprintf(
						/* translators: %s: filename */
						__( 'File not found: %s', 'humata-chatbot' ),
						$doc['filename']
					);
					continue;
				}

				$index_result = $this->index_document( $file_path );

				if ( is_wp_error( $index_result ) ) {
					++$results['failed'];
					$results['errors'][] = sprintf(
						/* translators: 1: filename, 2: error message */
						__( 'Failed to index %1$s: %2$s', 'humata-chatbot' ),
						$doc['filename'],
						$index_result->get_error_message()
					);
				} else {
					++$results['success'];
				}
			}

			return $results;
		} catch ( Exception $e ) {
			error_log( '[Humata Chatbot] Reindex error: ' . $e->getMessage() );
			return new WP_Error(
				'reindex_error',
				__( 'Failed to reindex documents.', 'humata-chatbot' )
			);
		}
	}

	/**
	 * Get list of all indexed documents.
	 *
	 * @since 1.0.0
	 * @param int $per_page Items per page. 0 for all.
	 * @param int $page     Page number (1-indexed).
	 * @return array|WP_Error Array with 'documents', 'total', 'pages' or WP_Error.
	 */
	public function get_document_list( $per_page = 0, $page = 1 ) {
		$db = $this->database->get_connection();
		if ( is_wp_error( $db ) ) {
			return $db;
		}

		try {
			// Get total count.
			$count_result = $db->querySingle( 'SELECT COUNT(*) FROM documents_meta' );
			$total        = (int) $count_result;

			// Build query with optional pagination.
			$sql = "
				SELECT id, filename, upload_date, file_size, file_path, section_count
				FROM documents_meta
				ORDER BY upload_date DESC
			";

			if ( $per_page > 0 ) {
				$offset = max( 0, ( $page - 1 ) * $per_page );
				$sql   .= " LIMIT {$per_page} OFFSET {$offset}";
			}

			$query = $db->query( $sql );

			$documents = array();
			while ( $row = $query->fetchArray( SQLITE3_ASSOC ) ) {
				$documents[] = array(
					'id'            => (int) $row['id'],
					'filename'      => $row['filename'],
					'upload_date'   => $row['upload_date'],
					'file_size'     => (int) $row['file_size'],
					'file_path'     => $row['file_path'],
					'section_count' => (int) $row['section_count'],
					'file_exists'   => file_exists( $row['file_path'] ),
				);
			}

			$pages = $per_page > 0 ? (int) ceil( $total / $per_page ) : 1;

			return array(
				'documents' => $documents,
				'total'     => $total,
				'pages'     => $pages,
				'page'      => $page,
				'per_page'  => $per_page,
			);
		} catch ( Exception $e ) {
			error_log( '[Humata Chatbot] Get document list error: ' . $e->getMessage() );
			return new WP_Error(
				'list_error',
				__( 'Failed to retrieve document list.', 'humata-chatbot' )
			);
		}
	}

	/**
	 * Get a single document by ID.
	 *
	 * @since 1.0.0
	 * @param int $doc_id Document ID.
	 * @return array|null|WP_Error Document array, null if not found, or WP_Error.
	 */
	public function get_document( $doc_id ) {
		$doc_id = absint( $doc_id );

		if ( $doc_id <= 0 ) {
			return new WP_Error(
				'invalid_doc_id',
				__( 'Invalid document ID.', 'humata-chatbot' )
			);
		}

		$db = $this->database->get_connection();
		if ( is_wp_error( $db ) ) {
			return $db;
		}

		try {
			$stmt = $db->prepare( "
				SELECT id, filename, upload_date, file_size, file_path, section_count
				FROM documents_meta
				WHERE id = :doc_id
			" );
			$stmt->bindValue( ':doc_id', $doc_id, SQLITE3_INTEGER );
			$result = $stmt->execute();
			$row = $result->fetchArray( SQLITE3_ASSOC );

			if ( ! $row ) {
				return null;
			}

			return array(
				'id'            => (int) $row['id'],
				'filename'      => $row['filename'],
				'upload_date'   => $row['upload_date'],
				'file_size'     => (int) $row['file_size'],
				'file_path'     => $row['file_path'],
				'section_count' => (int) $row['section_count'],
				'file_exists'   => file_exists( $row['file_path'] ),
			);
		} catch ( Exception $e ) {
			error_log( '[Humata Chatbot] Get document error: ' . $e->getMessage() );
			return new WP_Error(
				'get_error',
				__( 'Failed to retrieve document.', 'humata-chatbot' )
			);
		}
	}
}
