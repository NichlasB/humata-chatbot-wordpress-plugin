<?php
/**
 * Message Logger for Analytics
 *
 * Handles logging and retrieval of chat messages for admin analytics.
 *
 * @package Humata_Chatbot
 * @since 1.2.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Humata_Chatbot_Rest_Message_Logger
 *
 * Manages message logging operations for the analytics feature.
 *
 * @since 1.2.0
 */
class Humata_Chatbot_Rest_Message_Logger {

	/**
	 * Database instance.
	 *
	 * @since 1.2.0
	 * @var Humata_Chatbot_Rest_Search_Database
	 */
	private $database;

	/**
	 * Constructor.
	 *
	 * @since 1.2.0
	 * @param Humata_Chatbot_Rest_Search_Database $database Database instance.
	 */
	public function __construct( Humata_Chatbot_Rest_Search_Database $database ) {
		$this->database = $database;
	}

	/**
	 * Check if message logging is enabled.
	 *
	 * @since 1.2.0
	 * @return bool True if logging is enabled.
	 */
	public function is_enabled() {
		return (bool) get_option( 'humata_analytics_enabled', false );
	}

	/**
	 * Log a chat message.
	 *
	 * @since 1.2.0
	 * @param array $data Message data with keys:
	 *                    - session_id: (string) Unique session identifier.
	 *                    - user_message: (string) The user's message.
	 *                    - bot_response: (string) The bot's response.
	 *                    - client_ip: (string) Client IP address (will be hashed).
	 *                    - page_url: (string) Page URL where chat occurred.
	 *                    - referer: (string) HTTP referer.
	 *                    - provider_used: (string) AI provider used.
	 *                    - response_time_ms: (int) Response time in milliseconds.
	 * @return int|WP_Error Message ID on success, WP_Error on failure.
	 */
	public function log_message( array $data ) {
		if ( ! $this->is_enabled() ) {
			return new WP_Error( 'logging_disabled', __( 'Message logging is disabled.', 'humata-chatbot' ) );
		}

		$db = $this->database->get_connection();

		if ( is_wp_error( $db ) ) {
			return $db;
		}

		// Ensure tables exist.
		$this->database->maybe_init();

		// Hash the IP for privacy.
		$ip_hash = '';
		if ( ! empty( $data['client_ip'] ) ) {
			$ip_hash = hash( 'sha256', $data['client_ip'] . wp_salt( 'auth' ) );
		}

		try {
			$stmt = $db->prepare( "
				INSERT INTO message_logs (
					session_id,
					user_message,
					bot_response,
					client_ip_hash,
					page_url,
					referer,
					provider_used,
					response_time_ms,
					is_processed
				) VALUES (
					:session_id,
					:user_message,
					:bot_response,
					:client_ip_hash,
					:page_url,
					:referer,
					:provider_used,
					:response_time_ms,
					0
				)
			" );

			$stmt->bindValue( ':session_id', sanitize_text_field( $data['session_id'] ?? '' ), SQLITE3_TEXT );
			$stmt->bindValue( ':user_message', sanitize_textarea_field( $data['user_message'] ?? '' ), SQLITE3_TEXT );
			$stmt->bindValue( ':bot_response', $data['bot_response'] ?? '', SQLITE3_TEXT );
			$stmt->bindValue( ':client_ip_hash', $ip_hash, SQLITE3_TEXT );
			$stmt->bindValue( ':page_url', esc_url_raw( $data['page_url'] ?? '' ), SQLITE3_TEXT );
			$stmt->bindValue( ':referer', esc_url_raw( $data['referer'] ?? '' ), SQLITE3_TEXT );
			$stmt->bindValue( ':provider_used', sanitize_text_field( $data['provider_used'] ?? '' ), SQLITE3_TEXT );
			$stmt->bindValue( ':response_time_ms', absint( $data['response_time_ms'] ?? 0 ), SQLITE3_INTEGER );

			$stmt->execute();
			$message_id = $db->lastInsertRowID();

			// Add to FTS index.
			$this->add_to_fts_index( $message_id, $data['user_message'] ?? '', $data['bot_response'] ?? '' );

			return $message_id;
		} catch ( Exception $e ) {
			error_log( '[Humata Chatbot] Failed to log message: ' . $e->getMessage() );
			return new WP_Error( 'log_error', __( 'Failed to log message.', 'humata-chatbot' ) );
		}
	}

	/**
	 * Add message to FTS index.
	 *
	 * @since 1.2.0
	 * @param int    $message_id   Message ID.
	 * @param string $user_message User message.
	 * @param string $bot_response Bot response.
	 * @return bool True on success.
	 */
	private function add_to_fts_index( $message_id, $user_message, $bot_response ) {
		$db = $this->database->get_connection();

		if ( is_wp_error( $db ) ) {
			return false;
		}

		try {
			$stmt = $db->prepare( "
				INSERT INTO message_logs_fts (message_log_id, user_message, bot_response)
				VALUES (:message_id, :user_message, :bot_response)
			" );

			$stmt->bindValue( ':message_id', $message_id, SQLITE3_INTEGER );
			$stmt->bindValue( ':user_message', $user_message, SQLITE3_TEXT );
			$stmt->bindValue( ':bot_response', $bot_response, SQLITE3_TEXT );

			$stmt->execute();
			return true;
		} catch ( Exception $e ) {
			error_log( '[Humata Chatbot] Failed to add message to FTS index: ' . $e->getMessage() );
			return false;
		}
	}

	/**
	 * Get a single message by ID.
	 *
	 * @since 1.2.0
	 * @param int $message_id Message ID.
	 * @return array|WP_Error|null Message data, null if not found, WP_Error on failure.
	 */
	public function get_message( $message_id ) {
		$db = $this->database->get_connection();

		if ( is_wp_error( $db ) ) {
			return $db;
		}

		try {
			$stmt = $db->prepare( "
				SELECT m.*, i.summary, i.intent, i.sentiment, i.topics, i.unanswered_questions, i.raw_analysis
				FROM message_logs m
				LEFT JOIN message_insights i ON m.id = i.message_log_id
				WHERE m.id = :id
			" );

			$stmt->bindValue( ':id', $message_id, SQLITE3_INTEGER );
			$result = $stmt->execute();
			$row    = $result->fetchArray( SQLITE3_ASSOC );

			return $row ? $row : null;
		} catch ( Exception $e ) {
			error_log( '[Humata Chatbot] Failed to get message: ' . $e->getMessage() );
			return new WP_Error( 'query_error', __( 'Failed to retrieve message.', 'humata-chatbot' ) );
		}
	}

	/**
	 * Get paginated list of messages.
	 *
	 * @since 1.2.0
	 * @param int   $per_page Items per page.
	 * @param int   $page     Current page number.
	 * @param array $filters  Optional filters:
	 *                        - processed: (bool) Filter by processed status.
	 *                        - date_from: (string) Start date (Y-m-d).
	 *                        - date_to: (string) End date (Y-m-d).
	 *                        - search: (string) Search query.
	 * @return array|WP_Error Array with 'messages', 'total', 'pages', 'page' keys.
	 */
	public function get_messages( $per_page = 20, $page = 1, array $filters = array() ) {
		$db = $this->database->get_connection();

		if ( is_wp_error( $db ) ) {
			return $db;
		}

		try {
			$where_clauses = array();
			$params        = array();

			// Filter by processed status.
			if ( isset( $filters['processed'] ) ) {
				$where_clauses[]       = 'is_processed = :processed';
				$params[':processed']  = $filters['processed'] ? 1 : 0;
			}

			// Filter by date range.
			if ( ! empty( $filters['date_from'] ) ) {
				$where_clauses[]        = 'created_at >= :date_from';
				$params[':date_from']   = $filters['date_from'] . ' 00:00:00';
			}

			if ( ! empty( $filters['date_to'] ) ) {
				$where_clauses[]      = 'created_at <= :date_to';
				$params[':date_to']   = $filters['date_to'] . ' 23:59:59';
			}

			// Build WHERE clause.
			$where_sql = '';
			if ( ! empty( $where_clauses ) ) {
				$where_sql = 'WHERE ' . implode( ' AND ', $where_clauses );
			}

			// Handle search using FTS.
			if ( ! empty( $filters['search'] ) ) {
				$search_query = $this->prepare_fts_query( $filters['search'] );

				// Get message IDs from FTS.
				$fts_stmt = $db->prepare( "
					SELECT message_log_id FROM message_logs_fts
					WHERE message_logs_fts MATCH :search
				" );
				$fts_stmt->bindValue( ':search', $search_query, SQLITE3_TEXT );
				$fts_result = $fts_stmt->execute();

				$message_ids = array();
				while ( $row = $fts_result->fetchArray( SQLITE3_ASSOC ) ) {
					$message_ids[] = (int) $row['message_log_id'];
				}

				if ( empty( $message_ids ) ) {
					return array(
						'messages' => array(),
						'total'    => 0,
						'pages'    => 0,
						'page'     => $page,
					);
				}

				$ids_placeholder = implode( ',', $message_ids );
				if ( empty( $where_sql ) ) {
					$where_sql = "WHERE id IN ($ids_placeholder)";
				} else {
					$where_sql .= " AND id IN ($ids_placeholder)";
				}
			}

			// Count total.
			$count_sql  = "SELECT COUNT(*) FROM message_logs $where_sql";
			$count_stmt = $db->prepare( $count_sql );

			foreach ( $params as $key => $value ) {
				$type = is_int( $value ) ? SQLITE3_INTEGER : SQLITE3_TEXT;
				$count_stmt->bindValue( $key, $value, $type );
			}

			$total = (int) $count_stmt->execute()->fetchArray()[0];
			$pages = (int) ceil( $total / $per_page );

			// Get messages with insights.
			$offset = ( $page - 1 ) * $per_page;
			$sql    = "
				SELECT m.*, i.summary, i.intent, i.sentiment
				FROM message_logs m
				LEFT JOIN message_insights i ON m.id = i.message_log_id
				$where_sql
				ORDER BY m.created_at DESC
				LIMIT :limit OFFSET :offset
			";

			$stmt = $db->prepare( $sql );

			foreach ( $params as $key => $value ) {
				$type = is_int( $value ) ? SQLITE3_INTEGER : SQLITE3_TEXT;
				$stmt->bindValue( $key, $value, $type );
			}

			$stmt->bindValue( ':limit', $per_page, SQLITE3_INTEGER );
			$stmt->bindValue( ':offset', $offset, SQLITE3_INTEGER );

			$result   = $stmt->execute();
			$messages = array();

			while ( $row = $result->fetchArray( SQLITE3_ASSOC ) ) {
				$messages[] = $row;
			}

			return array(
				'messages' => $messages,
				'total'    => $total,
				'pages'    => $pages,
				'page'     => $page,
			);
		} catch ( Exception $e ) {
			error_log( '[Humata Chatbot] Failed to get messages: ' . $e->getMessage() );
			return new WP_Error( 'query_error', __( 'Failed to retrieve messages.', 'humata-chatbot' ) );
		}
	}

	/**
	 * Get messages grouped by session.
	 *
	 * @since 1.2.0
	 * @param int   $per_page Sessions per page.
	 * @param int   $page     Current page number.
	 * @param array $filters  Optional filters.
	 * @return array|WP_Error Array with 'sessions', 'total', 'pages', 'page' keys.
	 */
	public function get_sessions( $per_page = 20, $page = 1, array $filters = array() ) {
		$db = $this->database->get_connection();

		if ( is_wp_error( $db ) ) {
			return $db;
		}

		try {
			$where_clauses = array();
			$params        = array();

			// Filter by date range.
			if ( ! empty( $filters['date_from'] ) ) {
				$where_clauses[]        = 'created_at >= :date_from';
				$params[':date_from']   = $filters['date_from'] . ' 00:00:00';
			}

			if ( ! empty( $filters['date_to'] ) ) {
				$where_clauses[]      = 'created_at <= :date_to';
				$params[':date_to']   = $filters['date_to'] . ' 23:59:59';
			}

			$where_sql = '';
			if ( ! empty( $where_clauses ) ) {
				$where_sql = 'WHERE ' . implode( ' AND ', $where_clauses );
			}

			// Count unique sessions.
			$count_sql  = "SELECT COUNT(DISTINCT session_id) FROM message_logs $where_sql";
			$count_stmt = $db->prepare( $count_sql );

			foreach ( $params as $key => $value ) {
				$count_stmt->bindValue( $key, $value, SQLITE3_TEXT );
			}

			$total = (int) $count_stmt->execute()->fetchArray()[0];
			$pages = (int) ceil( $total / $per_page );

			// Get unique sessions with latest message time.
			$offset = ( $page - 1 ) * $per_page;
			$sql    = "
				SELECT
					session_id,
					COUNT(*) as message_count,
					MIN(created_at) as first_message,
					MAX(created_at) as last_message,
					page_url
				FROM message_logs
				$where_sql
				GROUP BY session_id
				ORDER BY last_message DESC
				LIMIT :limit OFFSET :offset
			";

			$stmt = $db->prepare( $sql );

			foreach ( $params as $key => $value ) {
				$stmt->bindValue( $key, $value, SQLITE3_TEXT );
			}

			$stmt->bindValue( ':limit', $per_page, SQLITE3_INTEGER );
			$stmt->bindValue( ':offset', $offset, SQLITE3_INTEGER );

			$result   = $stmt->execute();
			$sessions = array();

			while ( $row = $result->fetchArray( SQLITE3_ASSOC ) ) {
				$sessions[] = $row;
			}

			return array(
				'sessions' => $sessions,
				'total'    => $total,
				'pages'    => $pages,
				'page'     => $page,
			);
		} catch ( Exception $e ) {
			error_log( '[Humata Chatbot] Failed to get sessions: ' . $e->getMessage() );
			return new WP_Error( 'query_error', __( 'Failed to retrieve sessions.', 'humata-chatbot' ) );
		}
	}

	/**
	 * Get all messages for a specific session.
	 *
	 * @since 1.2.0
	 * @param string $session_id Session ID.
	 * @return array|WP_Error Array of messages or WP_Error on failure.
	 */
	public function get_session_messages( $session_id ) {
		$db = $this->database->get_connection();

		if ( is_wp_error( $db ) ) {
			return $db;
		}

		try {
			$stmt = $db->prepare( "
				SELECT m.*, i.summary, i.intent, i.sentiment, i.topics, i.unanswered_questions
				FROM message_logs m
				LEFT JOIN message_insights i ON m.id = i.message_log_id
				WHERE m.session_id = :session_id
				ORDER BY m.created_at ASC
			" );

			$stmt->bindValue( ':session_id', $session_id, SQLITE3_TEXT );
			$result   = $stmt->execute();
			$messages = array();

			while ( $row = $result->fetchArray( SQLITE3_ASSOC ) ) {
				$messages[] = $row;
			}

			return $messages;
		} catch ( Exception $e ) {
			error_log( '[Humata Chatbot] Failed to get session messages: ' . $e->getMessage() );
			return new WP_Error( 'query_error', __( 'Failed to retrieve session messages.', 'humata-chatbot' ) );
		}
	}

	/**
	 * Mark a message as processed.
	 *
	 * @since 1.2.0
	 * @param int $message_id Message ID.
	 * @return bool|WP_Error True on success, WP_Error on failure.
	 */
	public function mark_processed( $message_id ) {
		$db = $this->database->get_connection();

		if ( is_wp_error( $db ) ) {
			return $db;
		}

		try {
			$stmt = $db->prepare( 'UPDATE message_logs SET is_processed = 1 WHERE id = :id' );
			$stmt->bindValue( ':id', $message_id, SQLITE3_INTEGER );
			$stmt->execute();

			return true;
		} catch ( Exception $e ) {
			error_log( '[Humata Chatbot] Failed to mark message as processed: ' . $e->getMessage() );
			return new WP_Error( 'update_error', __( 'Failed to update message.', 'humata-chatbot' ) );
		}
	}

	/**
	 * Add insight for a message.
	 *
	 * @since 1.2.0
	 * @param int   $message_id Message ID.
	 * @param array $insight    Insight data with keys: summary, intent, sentiment, topics, unanswered_questions, raw_analysis.
	 * @param string $provider  Provider used for analysis.
	 * @return int|WP_Error Insight ID on success, WP_Error on failure.
	 */
	public function add_insight( $message_id, array $insight, $provider = '' ) {
		$db = $this->database->get_connection();

		if ( is_wp_error( $db ) ) {
			return $db;
		}

		try {
			// Delete existing insight if any.
			$delete_stmt = $db->prepare( 'DELETE FROM message_insights WHERE message_log_id = :id' );
			$delete_stmt->bindValue( ':id', $message_id, SQLITE3_INTEGER );
			$delete_stmt->execute();

			// Insert new insight.
			$stmt = $db->prepare( "
				INSERT INTO message_insights (
					message_log_id,
					summary,
					intent,
					sentiment,
					topics,
					unanswered_questions,
					raw_analysis,
					provider_used
				) VALUES (
					:message_log_id,
					:summary,
					:intent,
					:sentiment,
					:topics,
					:unanswered_questions,
					:raw_analysis,
					:provider_used
				)
			" );

			$stmt->bindValue( ':message_log_id', $message_id, SQLITE3_INTEGER );
			$stmt->bindValue( ':summary', $insight['summary'] ?? '', SQLITE3_TEXT );
			$stmt->bindValue( ':intent', $insight['intent'] ?? '', SQLITE3_TEXT );
			$stmt->bindValue( ':sentiment', $insight['sentiment'] ?? '', SQLITE3_TEXT );
			$stmt->bindValue( ':topics', wp_json_encode( $insight['topics'] ?? array() ), SQLITE3_TEXT );
			$stmt->bindValue( ':unanswered_questions', wp_json_encode( $insight['unanswered_questions'] ?? array() ), SQLITE3_TEXT );
			$stmt->bindValue( ':raw_analysis', $insight['raw_analysis'] ?? '', SQLITE3_TEXT );
			$stmt->bindValue( ':provider_used', $provider, SQLITE3_TEXT );

			$stmt->execute();

			// Mark message as processed.
			$this->mark_processed( $message_id );

			return $db->lastInsertRowID();
		} catch ( Exception $e ) {
			error_log( '[Humata Chatbot] Failed to add insight: ' . $e->getMessage() );
			return new WP_Error( 'insert_error', __( 'Failed to add insight.', 'humata-chatbot' ) );
		}
	}

	/**
	 * Delete messages by IDs.
	 *
	 * @since 1.2.0
	 * @param array $message_ids Array of message IDs to delete.
	 * @return int|WP_Error Number of deleted messages or WP_Error on failure.
	 */
	public function delete_messages( array $message_ids ) {
		$db = $this->database->get_connection();

		if ( is_wp_error( $db ) ) {
			return $db;
		}

		$message_ids = array_map( 'absint', $message_ids );
		$message_ids = array_filter( $message_ids );

		if ( empty( $message_ids ) ) {
			return 0;
		}

		try {
			$db->exec( 'BEGIN TRANSACTION' );

			$ids_placeholder = implode( ',', $message_ids );

			// Delete from FTS.
			$db->exec( "DELETE FROM message_logs_fts WHERE message_log_id IN ($ids_placeholder)" );

			// Delete insights (cascade should handle this, but be explicit).
			$db->exec( "DELETE FROM message_insights WHERE message_log_id IN ($ids_placeholder)" );

			// Delete messages.
			$db->exec( "DELETE FROM message_logs WHERE id IN ($ids_placeholder)" );

			$deleted = $db->changes();

			$db->exec( 'COMMIT' );

			return $deleted;
		} catch ( Exception $e ) {
			$db->exec( 'ROLLBACK' );
			error_log( '[Humata Chatbot] Failed to delete messages: ' . $e->getMessage() );
			return new WP_Error( 'delete_error', __( 'Failed to delete messages.', 'humata-chatbot' ) );
		}
	}

	/**
	 * Delete all messages.
	 *
	 * @since 1.2.0
	 * @return int|WP_Error Number of deleted messages or WP_Error on failure.
	 */
	public function delete_all_messages() {
		$db = $this->database->get_connection();

		if ( is_wp_error( $db ) ) {
			return $db;
		}

		try {
			$db->exec( 'BEGIN TRANSACTION' );

			// Count before delete.
			$count = (int) $db->querySingle( 'SELECT COUNT(*) FROM message_logs' );

			// Delete all.
			$db->exec( 'DELETE FROM message_logs_fts' );
			$db->exec( 'DELETE FROM message_insights' );
			$db->exec( 'DELETE FROM message_logs' );

			$db->exec( 'COMMIT' );

			return $count;
		} catch ( Exception $e ) {
			$db->exec( 'ROLLBACK' );
			error_log( '[Humata Chatbot] Failed to delete all messages: ' . $e->getMessage() );
			return new WP_Error( 'delete_error', __( 'Failed to delete messages.', 'humata-chatbot' ) );
		}
	}

	/**
	 * Cleanup old messages based on retention period.
	 *
	 * @since 1.2.0
	 * @param int $days Number of days to keep messages (0 = keep forever).
	 * @return int|WP_Error Number of deleted messages or WP_Error on failure.
	 */
	public function cleanup_old_messages( $days ) {
		if ( $days <= 0 ) {
			return 0;
		}

		$db = $this->database->get_connection();

		if ( is_wp_error( $db ) ) {
			return $db;
		}

		try {
			$cutoff_date = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

			$db->exec( 'BEGIN TRANSACTION' );

			// Get IDs of old messages.
			$stmt = $db->prepare( 'SELECT id FROM message_logs WHERE created_at < :cutoff' );
			$stmt->bindValue( ':cutoff', $cutoff_date, SQLITE3_TEXT );
			$result = $stmt->execute();

			$old_ids = array();
			while ( $row = $result->fetchArray( SQLITE3_ASSOC ) ) {
				$old_ids[] = (int) $row['id'];
			}

			if ( empty( $old_ids ) ) {
				$db->exec( 'COMMIT' );
				return 0;
			}

			$ids_placeholder = implode( ',', $old_ids );

			// Delete from FTS.
			$db->exec( "DELETE FROM message_logs_fts WHERE message_log_id IN ($ids_placeholder)" );

			// Delete insights.
			$db->exec( "DELETE FROM message_insights WHERE message_log_id IN ($ids_placeholder)" );

			// Delete messages.
			$db->exec( "DELETE FROM message_logs WHERE id IN ($ids_placeholder)" );

			$deleted = count( $old_ids );

			$db->exec( 'COMMIT' );

			return $deleted;
		} catch ( Exception $e ) {
			$db->exec( 'ROLLBACK' );
			error_log( '[Humata Chatbot] Failed to cleanup old messages: ' . $e->getMessage() );
			return new WP_Error( 'cleanup_error', __( 'Failed to cleanup old messages.', 'humata-chatbot' ) );
		}
	}

	/**
	 * Get unprocessed messages for batch processing.
	 *
	 * @since 1.2.0
	 * @param int $limit Maximum number of messages to return.
	 * @return array|WP_Error Array of messages or WP_Error on failure.
	 */
	public function get_unprocessed_messages( $limit = 10 ) {
		$db = $this->database->get_connection();

		if ( is_wp_error( $db ) ) {
			return $db;
		}

		try {
			$stmt = $db->prepare( "
				SELECT * FROM message_logs
				WHERE is_processed = 0
				ORDER BY created_at ASC
				LIMIT :limit
			" );

			$stmt->bindValue( ':limit', $limit, SQLITE3_INTEGER );
			$result   = $stmt->execute();
			$messages = array();

			while ( $row = $result->fetchArray( SQLITE3_ASSOC ) ) {
				$messages[] = $row;
			}

			return $messages;
		} catch ( Exception $e ) {
			error_log( '[Humata Chatbot] Failed to get unprocessed messages: ' . $e->getMessage() );
			return new WP_Error( 'query_error', __( 'Failed to retrieve unprocessed messages.', 'humata-chatbot' ) );
		}
	}

	/**
	 * Export messages to CSV format.
	 *
	 * @since 1.2.0
	 * @param array $filters Optional filters (same as get_messages).
	 * @return string|WP_Error CSV content or WP_Error on failure.
	 */
	public function export_csv( array $filters = array() ) {
		// Get all messages matching filters (no pagination).
		$db = $this->database->get_connection();

		if ( is_wp_error( $db ) ) {
			return $db;
		}

		try {
			$where_clauses = array();
			$params        = array();

			if ( ! empty( $filters['date_from'] ) ) {
				$where_clauses[]        = 'created_at >= :date_from';
				$params[':date_from']   = $filters['date_from'] . ' 00:00:00';
			}

			if ( ! empty( $filters['date_to'] ) ) {
				$where_clauses[]      = 'created_at <= :date_to';
				$params[':date_to']   = $filters['date_to'] . ' 23:59:59';
			}

			$where_sql = '';
			if ( ! empty( $where_clauses ) ) {
				$where_sql = 'WHERE ' . implode( ' AND ', $where_clauses );
			}

			$sql = "
				SELECT
					m.id,
					m.session_id,
					m.created_at,
					m.user_message,
					m.bot_response,
					m.page_url,
					m.provider_used,
					m.response_time_ms,
					i.summary,
					i.intent,
					i.sentiment,
					i.topics,
					i.unanswered_questions
				FROM message_logs m
				LEFT JOIN message_insights i ON m.id = i.message_log_id
				$where_sql
				ORDER BY m.created_at DESC
			";

			$stmt = $db->prepare( $sql );

			foreach ( $params as $key => $value ) {
				$stmt->bindValue( $key, $value, SQLITE3_TEXT );
			}

			$result = $stmt->execute();

			// Build CSV.
			$output = fopen( 'php://temp', 'r+' );

			// Header row.
			fputcsv( $output, array(
				'ID',
				'Session ID',
				'Date/Time',
				'User Message',
				'Bot Response',
				'Page URL',
				'Provider',
				'Response Time (ms)',
				'Summary',
				'Intent',
				'Sentiment',
				'Topics',
				'Unanswered Questions',
			) );

			// Data rows.
			while ( $row = $result->fetchArray( SQLITE3_ASSOC ) ) {
				fputcsv( $output, array(
					$row['id'],
					$row['session_id'],
					$row['created_at'],
					$row['user_message'],
					$row['bot_response'],
					$row['page_url'],
					$row['provider_used'],
					$row['response_time_ms'],
					$row['summary'],
					$row['intent'],
					$row['sentiment'],
					$row['topics'],
					$row['unanswered_questions'],
				) );
			}

			rewind( $output );
			$csv = stream_get_contents( $output );
			fclose( $output );

			return $csv;
		} catch ( Exception $e ) {
			error_log( '[Humata Chatbot] Failed to export messages: ' . $e->getMessage() );
			return new WP_Error( 'export_error', __( 'Failed to export messages.', 'humata-chatbot' ) );
		}
	}

	/**
	 * Prepare a search query for FTS5.
	 *
	 * @since 1.2.0
	 * @param string $query Raw search query.
	 * @return string FTS5-compatible query.
	 */
	private function prepare_fts_query( $query ) {
		// Escape special FTS5 characters and add wildcards.
		$query = trim( $query );

		// Remove special characters that could break FTS5.
		$query = preg_replace( '/[^\w\s]/', '', $query );

		// Add wildcard to each word for partial matching.
		$words = preg_split( '/\s+/', $query );
		$words = array_filter( $words );
		$words = array_map( function( $word ) {
			return $word . '*';
		}, $words );

		return implode( ' ', $words );
	}

	/**
	 * Get message statistics.
	 *
	 * @since 1.2.0
	 * @return array|WP_Error Statistics array or WP_Error on failure.
	 */
	public function get_stats() {
		$db = $this->database->get_connection();

		if ( is_wp_error( $db ) ) {
			return $db;
		}

		try {
			$stats = array(
				'total_messages'      => 0,
				'processed_messages'  => 0,
				'pending_messages'    => 0,
				'total_sessions'      => 0,
				'messages_today'      => 0,
				'messages_this_week'  => 0,
			);

			// Total messages.
			$stats['total_messages'] = (int) $db->querySingle( 'SELECT COUNT(*) FROM message_logs' );

			// Processed messages.
			$stats['processed_messages'] = (int) $db->querySingle( 'SELECT COUNT(*) FROM message_logs WHERE is_processed = 1' );

			// Pending messages.
			$stats['pending_messages'] = $stats['total_messages'] - $stats['processed_messages'];

			// Total unique sessions.
			$stats['total_sessions'] = (int) $db->querySingle( 'SELECT COUNT(DISTINCT session_id) FROM message_logs' );

			// Messages today.
			$today = gmdate( 'Y-m-d' );
			$stmt  = $db->prepare( "SELECT COUNT(*) FROM message_logs WHERE created_at >= :today" );
			$stmt->bindValue( ':today', $today . ' 00:00:00', SQLITE3_TEXT );
			$stats['messages_today'] = (int) $stmt->execute()->fetchArray()[0];

			// Messages this week.
			$week_ago = gmdate( 'Y-m-d', strtotime( '-7 days' ) );
			$stmt     = $db->prepare( "SELECT COUNT(*) FROM message_logs WHERE created_at >= :week_ago" );
			$stmt->bindValue( ':week_ago', $week_ago . ' 00:00:00', SQLITE3_TEXT );
			$stats['messages_this_week'] = (int) $stmt->execute()->fetchArray()[0];

			return $stats;
		} catch ( Exception $e ) {
			error_log( '[Humata Chatbot] Failed to get message stats: ' . $e->getMessage() );
			return new WP_Error( 'stats_error', __( 'Failed to retrieve statistics.', 'humata-chatbot' ) );
		}
	}
}
