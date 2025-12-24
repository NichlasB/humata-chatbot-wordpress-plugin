<?php
/**
 * FTS5 Search Engine
 *
 * Performs full-text search queries against the SQLite FTS5 database
 * using BM25 ranking with weighted columns.
 *
 * @package Humata_Chatbot
 * @since 1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Humata_Chatbot_Rest_Search_Engine
 *
 * Handles search queries against the FTS5 index.
 *
 * @since 1.0.0
 */
class Humata_Chatbot_Rest_Search_Engine {

	/**
	 * Search database instance.
	 *
	 * @since 1.0.0
	 * @var Humata_Chatbot_Rest_Search_Database
	 */
	private $database;

	/**
	 * Minimum BM25 score threshold.
	 *
	 * Results with scores worse (closer to 0) than this are filtered out.
	 * BM25 scores are negative; more negative = better match.
	 *
	 * @since 1.0.0
	 * @var float
	 */
	const MIN_SCORE_THRESHOLD = 0.0;

	/**
	 * Common stopwords to filter from search queries.
	 *
	 * @since 1.0.0
	 * @var array
	 */
	private static $stopwords = array(
		'a', 'an', 'the', 'and', 'or', 'but', 'is', 'are', 'was', 'were',
		'be', 'been', 'being', 'have', 'has', 'had', 'do', 'does', 'did',
		'will', 'would', 'could', 'should', 'may', 'might', 'must', 'shall',
		'can', 'need', 'dare', 'ought', 'used', 'to', 'of', 'in', 'for',
		'on', 'with', 'at', 'by', 'from', 'as', 'into', 'through', 'during',
		'before', 'after', 'above', 'below', 'between', 'under', 'again',
		'further', 'then', 'once', 'here', 'there', 'when', 'where', 'why',
		'how', 'all', 'each', 'few', 'more', 'most', 'other', 'some', 'such',
		'no', 'nor', 'not', 'only', 'own', 'same', 'so', 'than', 'too',
		'very', 'just', 'also', 'now', 'what', 'which', 'who', 'whom',
		'this', 'that', 'these', 'those', 'am', 'i', 'me', 'my', 'myself',
		'we', 'our', 'ours', 'ourselves', 'you', 'your', 'yours', 'yourself',
		'yourselves', 'he', 'him', 'his', 'himself', 'she', 'her', 'hers',
		'herself', 'it', 'its', 'itself', 'they', 'them', 'their', 'theirs',
		'themselves', 'about', 'against', 'any', 'because', 'both', 'but',
		'if', 'while', 'until', 'unless', 'although', 'though', 'since',
		'tell', 'explain', 'describe', 'please', 'help', 'know', 'understand',
	);

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
	 * Sanitize query string for FTS5 MATCH syntax.
	 *
	 * Escapes special characters and prepares query for FTS5.
	 *
	 * @since 1.0.0
	 * @param string $query Raw query string.
	 * @return string Sanitized query string.
	 */
	private function sanitize_query( $query ) {
		// Trim and normalize whitespace.
		$query = trim( preg_replace( '/\s+/', ' ', $query ) );

		if ( empty( $query ) ) {
			return '';
		}

		// Remove FTS5 special characters that could break the query.
		// FTS5 operators: AND, OR, NOT, NEAR, quotes, parentheses, *, ^
		$special_chars = array( '"', "'", '(', ')', '*', '^', '-', '+', ':', '@', '?', '!', '.', ',', ';' );
		$query = str_replace( $special_chars, ' ', $query );

		// Normalize whitespace again after removal.
		$query = trim( preg_replace( '/\s+/', ' ', $query ) );

		if ( empty( $query ) ) {
			return '';
		}

		// Split into words and filter.
		$words = explode( ' ', $query );
		$words = array_filter( $words, function( $word ) {
			$word = strtolower( trim( $word ) );
			// Filter out empty words, short words (< 2 chars), and stopwords.
			if ( strlen( $word ) < 2 ) {
				return false;
			}
			if ( in_array( $word, self::$stopwords, true ) ) {
				return false;
			}
			return true;
		} );

		if ( empty( $words ) ) {
			return '';
		}

		// Join words with OR for broader matching.
		// Each word is quoted to handle special characters.
		$escaped_words = array_map( function( $word ) {
			// Double any quotes within the word (FTS5 escape).
			$word = str_replace( '"', '""', $word );
			return '"' . $word . '"';
		}, $words );

		return implode( ' OR ', $escaped_words );
	}

	/**
	 * Perform a search query.
	 *
	 * Searches the FTS5 index using BM25 ranking with weighted columns:
	 * - doc_id: 0 (UNINDEXED)
	 * - doc_name: 1.0
	 * - section_header: 5.0
	 * - keywords: 10.0 (highest weight)
	 * - content: 2.0
	 * - chunk_index: 0 (UNINDEXED)
	 *
	 * @since 1.0.0
	 * @param string $query Search query.
	 * @param int    $limit Maximum number of results (default 5).
	 * @return array|WP_Error Array of results or WP_Error on failure.
	 */
	public function search( $query, $limit = 5 ) {
		$limit = absint( $limit );
		if ( $limit <= 0 ) {
			$limit = 5;
		}
		if ( $limit > 20 ) {
			$limit = 20;
		}

		$sanitized_query = $this->sanitize_query( $query );

		if ( empty( $sanitized_query ) ) {
			return array();
		}

		$db = $this->database->get_connection();
		if ( is_wp_error( $db ) ) {
			return $db;
		}

		try {
			// BM25 weights: doc_id=0, doc_name=1, section_header=5, keywords=10, content=2, chunk_index=0
			// Higher weight = more important for ranking.
			$sql = "
				SELECT 
					doc_id,
					doc_name,
					section_header,
					keywords,
					content,
					chunk_index,
					bm25(documents_fts, 0.0, 1.0, 5.0, 10.0, 2.0, 0.0) AS score
				FROM documents_fts
				WHERE documents_fts MATCH :query
				ORDER BY score
				LIMIT :limit
			";

			$stmt = $db->prepare( $sql );
			$stmt->bindValue( ':query', $sanitized_query, SQLITE3_TEXT );
			$stmt->bindValue( ':limit', $limit, SQLITE3_INTEGER );

			$result = $stmt->execute();
			$results = array();

			while ( $row = $result->fetchArray( SQLITE3_ASSOC ) ) {
				$score = (float) $row['score'];

				// Filter out results below minimum relevance threshold.
				// BM25 scores are negative; more negative = better match.
				if ( $score > self::MIN_SCORE_THRESHOLD ) {
					continue;
				}

				$results[] = array(
					'doc_id'         => (int) $row['doc_id'],
					'doc_name'       => $row['doc_name'],
					'section_header' => $row['section_header'],
					'keywords'       => $row['keywords'],
					'content'        => $row['content'],
					'chunk_index'    => (int) $row['chunk_index'],
					'score'          => $score,
				);
			}

			return $results;
		} catch ( Exception $e ) {
			error_log( '[Humata Chatbot] Search error: ' . $e->getMessage() );
			return new WP_Error(
				'search_error',
				__( 'Search query failed.', 'humata-chatbot' )
			);
		}
	}

	/**
	 * Search and format results for LLM context.
	 *
	 * Performs search and formats results as a context string suitable
	 * for inclusion in an LLM prompt.
	 *
	 * @since 1.0.0
	 * @param string $query Search query.
	 * @param int    $limit Maximum number of results (default 5).
	 * @return string|WP_Error Formatted context string or WP_Error on failure.
	 */
	public function search_with_context( $query, $limit = 5 ) {
		$results = $this->search( $query, $limit );

		if ( is_wp_error( $results ) ) {
			return $results;
		}

		if ( empty( $results ) ) {
			return '';
		}

		$context_parts = array();

		foreach ( $results as $index => $result ) {
			$section_num = $index + 1;
			$parts = array();

			// Add section header.
			$header = ! empty( $result['section_header'] )
				? $result['section_header']
				: sprintf( 'Section %d', $section_num );

			$parts[] = sprintf( '[SECTION %d: %s]', $section_num, $header );

			// Add source document.
			if ( ! empty( $result['doc_name'] ) ) {
				$parts[] = sprintf( 'Source: %s', $result['doc_name'] );
			}

			// Add keywords if present.
			if ( ! empty( $result['keywords'] ) ) {
				$parts[] = sprintf( 'Keywords: %s', $result['keywords'] );
			}

			// Add content.
			if ( ! empty( $result['content'] ) ) {
				$parts[] = '';
				$parts[] = $result['content'];
			}

			$context_parts[] = implode( "\n", $parts );
		}

		return implode( "\n\n---\n\n", $context_parts );
	}

	/**
	 * Get total number of indexed sections.
	 *
	 * @since 1.0.0
	 * @return int|WP_Error Section count or WP_Error on failure.
	 */
	public function get_section_count() {
		$db = $this->database->get_connection();
		if ( is_wp_error( $db ) ) {
			return $db;
		}

		try {
			$count = $db->querySingle( 'SELECT COUNT(*) FROM documents_fts' );
			return (int) $count;
		} catch ( Exception $e ) {
			return 0;
		}
	}

	/**
	 * Check if the search index has any content.
	 *
	 * @since 1.0.0
	 * @return bool True if index has content, false otherwise.
	 */
	public function has_content() {
		$count = $this->get_section_count();
		return ! is_wp_error( $count ) && $count > 0;
	}

	/**
	 * Get sections for a specific document.
	 *
	 * @since 1.0.0
	 * @param int $doc_id Document ID.
	 * @return array|WP_Error Array of sections or WP_Error on failure.
	 */
	public function get_document_sections( $doc_id ) {
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
				SELECT doc_name, section_header, keywords, content, chunk_index
				FROM documents_fts
				WHERE doc_id = :doc_id
				ORDER BY chunk_index
			" );
			$stmt->bindValue( ':doc_id', $doc_id, SQLITE3_INTEGER );

			$result = $stmt->execute();
			$sections = array();

			while ( $row = $result->fetchArray( SQLITE3_ASSOC ) ) {
				$sections[] = array(
					'doc_name'       => $row['doc_name'],
					'section_header' => $row['section_header'],
					'keywords'       => $row['keywords'],
					'content'        => $row['content'],
					'chunk_index'    => (int) $row['chunk_index'],
				);
			}

			return $sections;
		} catch ( Exception $e ) {
			error_log( '[Humata Chatbot] Get sections error: ' . $e->getMessage() );
			return new WP_Error(
				'sections_error',
				__( 'Failed to retrieve document sections.', 'humata-chatbot' )
			);
		}
	}
}
