<?php
/**
 * Document Category Manager
 *
 * Handles CRUD operations for document categories in the SQLite database.
 *
 * @package Humata_Chatbot
 * @since 1.1.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Humata_Chatbot_Rest_Category_Manager
 *
 * Manages document categories for organizational purposes.
 *
 * @since 1.1.0
 */
class Humata_Chatbot_Rest_Category_Manager {

	/**
	 * Search database instance.
	 *
	 * @since 1.1.0
	 * @var Humata_Chatbot_Rest_Search_Database
	 */
	private $database;

	/**
	 * Constructor.
	 *
	 * @since 1.1.0
	 * @param Humata_Chatbot_Rest_Search_Database $database Search database instance.
	 */
	public function __construct( Humata_Chatbot_Rest_Search_Database $database ) {
		$this->database = $database;
	}

	/**
	 * Create a new category.
	 *
	 * @since 1.1.0
	 * @param string $name Category name.
	 * @return int|WP_Error Category ID on success, WP_Error on failure.
	 */
	public function create( $name ) {
		$name = trim( $name );

		if ( empty( $name ) ) {
			return new WP_Error(
				'empty_name',
				__( 'Category name cannot be empty.', 'humata-chatbot' )
			);
		}

		if ( strlen( $name ) > 100 ) {
			return new WP_Error(
				'name_too_long',
				__( 'Category name cannot exceed 100 characters.', 'humata-chatbot' )
			);
		}

		$db = $this->database->get_connection();

		if ( is_wp_error( $db ) ) {
			return $db;
		}

		try {
			// Get next sort order.
			$max_order  = $db->querySingle( 'SELECT MAX(sort_order) FROM document_categories' );
			$sort_order = ( null === $max_order ) ? 0 : (int) $max_order + 1;

			$stmt = $db->prepare( '
				INSERT INTO document_categories (name, sort_order)
				VALUES (:name, :sort_order)
			' );

			$stmt->bindValue( ':name', $name, SQLITE3_TEXT );
			$stmt->bindValue( ':sort_order', $sort_order, SQLITE3_INTEGER );
			$stmt->execute();

			return $db->lastInsertRowID();
		} catch ( Exception $e ) {
			// Check for unique constraint violation.
			if ( false !== strpos( $e->getMessage(), 'UNIQUE constraint' ) ) {
				return new WP_Error(
					'duplicate_name',
					__( 'A category with this name already exists.', 'humata-chatbot' )
				);
			}

			error_log( '[Humata Chatbot] Category create error: ' . $e->getMessage() );
			return new WP_Error(
				'create_error',
				__( 'Failed to create category.', 'humata-chatbot' )
			);
		}
	}

	/**
	 * Get a category by ID.
	 *
	 * @since 1.1.0
	 * @param int $id Category ID.
	 * @return array|null|WP_Error Category array, null if not found, or WP_Error.
	 */
	public function get( $id ) {
		$id = absint( $id );

		if ( $id <= 0 ) {
			return new WP_Error(
				'invalid_id',
				__( 'Invalid category ID.', 'humata-chatbot' )
			);
		}

		$db = $this->database->get_connection();

		if ( is_wp_error( $db ) ) {
			return $db;
		}

		try {
			$stmt = $db->prepare( '
				SELECT id, name, sort_order
				FROM document_categories
				WHERE id = :id
			' );

			$stmt->bindValue( ':id', $id, SQLITE3_INTEGER );
			$result = $stmt->execute();
			$row    = $result->fetchArray( SQLITE3_ASSOC );

			if ( ! $row ) {
				return null;
			}

			return array(
				'id'         => (int) $row['id'],
				'name'       => $row['name'],
				'sort_order' => (int) $row['sort_order'],
			);
		} catch ( Exception $e ) {
			error_log( '[Humata Chatbot] Category get error: ' . $e->getMessage() );
			return new WP_Error(
				'get_error',
				__( 'Failed to retrieve category.', 'humata-chatbot' )
			);
		}
	}

	/**
	 * Get all categories.
	 *
	 * @since 1.1.0
	 * @param bool $with_counts Include document counts per category.
	 * @return array|WP_Error Array of categories or WP_Error.
	 */
	public function get_all( $with_counts = false ) {
		$db = $this->database->get_connection();

		if ( is_wp_error( $db ) ) {
			return $db;
		}

		try {
			if ( $with_counts ) {
				$sql = '
					SELECT 
						c.id, 
						c.name, 
						c.sort_order,
						COUNT(d.id) as doc_count
					FROM document_categories c
					LEFT JOIN documents_meta d ON d.category_id = c.id
					GROUP BY c.id
					ORDER BY c.sort_order ASC, c.name ASC
				';
			} else {
				$sql = '
					SELECT id, name, sort_order
					FROM document_categories
					ORDER BY sort_order ASC, name ASC
				';
			}

			$query      = $db->query( $sql );
			$categories = array();

			while ( $row = $query->fetchArray( SQLITE3_ASSOC ) ) {
				$category = array(
					'id'         => (int) $row['id'],
					'name'       => $row['name'],
					'sort_order' => (int) $row['sort_order'],
				);

				if ( $with_counts ) {
					$category['doc_count'] = (int) $row['doc_count'];
				}

				$categories[] = $category;
			}

			return $categories;
		} catch ( Exception $e ) {
			error_log( '[Humata Chatbot] Category get_all error: ' . $e->getMessage() );
			return new WP_Error(
				'get_all_error',
				__( 'Failed to retrieve categories.', 'humata-chatbot' )
			);
		}
	}

	/**
	 * Update a category.
	 *
	 * @since 1.1.0
	 * @param int    $id   Category ID.
	 * @param string $name New category name.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function update( $id, $name ) {
		$id   = absint( $id );
		$name = trim( $name );

		if ( $id <= 0 ) {
			return new WP_Error(
				'invalid_id',
				__( 'Invalid category ID.', 'humata-chatbot' )
			);
		}

		if ( empty( $name ) ) {
			return new WP_Error(
				'empty_name',
				__( 'Category name cannot be empty.', 'humata-chatbot' )
			);
		}

		if ( strlen( $name ) > 100 ) {
			return new WP_Error(
				'name_too_long',
				__( 'Category name cannot exceed 100 characters.', 'humata-chatbot' )
			);
		}

		$db = $this->database->get_connection();

		if ( is_wp_error( $db ) ) {
			return $db;
		}

		try {
			$stmt = $db->prepare( '
				UPDATE document_categories
				SET name = :name
				WHERE id = :id
			' );

			$stmt->bindValue( ':name', $name, SQLITE3_TEXT );
			$stmt->bindValue( ':id', $id, SQLITE3_INTEGER );
			$stmt->execute();

			if ( 0 === $db->changes() ) {
				return new WP_Error(
					'not_found',
					__( 'Category not found.', 'humata-chatbot' )
				);
			}

			return true;
		} catch ( Exception $e ) {
			if ( false !== strpos( $e->getMessage(), 'UNIQUE constraint' ) ) {
				return new WP_Error(
					'duplicate_name',
					__( 'A category with this name already exists.', 'humata-chatbot' )
				);
			}

			error_log( '[Humata Chatbot] Category update error: ' . $e->getMessage() );
			return new WP_Error(
				'update_error',
				__( 'Failed to update category.', 'humata-chatbot' )
			);
		}
	}

	/**
	 * Delete a category.
	 *
	 * Documents in this category will become uncategorized (category_id = NULL).
	 *
	 * @since 1.1.0
	 * @param int $id Category ID.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function delete( $id ) {
		$id = absint( $id );

		if ( $id <= 0 ) {
			return new WP_Error(
				'invalid_id',
				__( 'Invalid category ID.', 'humata-chatbot' )
			);
		}

		$db = $this->database->get_connection();

		if ( is_wp_error( $db ) ) {
			return $db;
		}

		try {
			$db->exec( 'BEGIN TRANSACTION' );

			// Set documents in this category to uncategorized.
			$stmt = $db->prepare( '
				UPDATE documents_meta
				SET category_id = NULL
				WHERE category_id = :id
			' );
			$stmt->bindValue( ':id', $id, SQLITE3_INTEGER );
			$stmt->execute();

			// Delete the category.
			$stmt = $db->prepare( '
				DELETE FROM document_categories
				WHERE id = :id
			' );
			$stmt->bindValue( ':id', $id, SQLITE3_INTEGER );
			$stmt->execute();

			if ( 0 === $db->changes() ) {
				$db->exec( 'ROLLBACK' );
				return new WP_Error(
					'not_found',
					__( 'Category not found.', 'humata-chatbot' )
				);
			}

			$db->exec( 'COMMIT' );

			return true;
		} catch ( Exception $e ) {
			$db->exec( 'ROLLBACK' );
			error_log( '[Humata Chatbot] Category delete error: ' . $e->getMessage() );
			return new WP_Error(
				'delete_error',
				__( 'Failed to delete category.', 'humata-chatbot' )
			);
		}
	}

	/**
	 * Get count of uncategorized documents.
	 *
	 * @since 1.1.0
	 * @return int|WP_Error Document count or WP_Error.
	 */
	public function get_uncategorized_count() {
		$db = $this->database->get_connection();

		if ( is_wp_error( $db ) ) {
			return $db;
		}

		try {
			$count = $db->querySingle( '
				SELECT COUNT(*) FROM documents_meta WHERE category_id IS NULL
			' );

			return (int) $count;
		} catch ( Exception $e ) {
			error_log( '[Humata Chatbot] Uncategorized count error: ' . $e->getMessage() );
			return new WP_Error(
				'count_error',
				__( 'Failed to count uncategorized documents.', 'humata-chatbot' )
			);
		}
	}
}
