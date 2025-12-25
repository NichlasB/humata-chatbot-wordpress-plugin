<?php
/**
 * Query expander for contextual search.
 *
 * Extracts salient terms from conversation history to enhance
 * FTS5 search queries for better contextual understanding.
 *
 * @package Humata_Chatbot
 * @since 1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Query expander class.
 *
 * @since 1.0.0
 */
class Humata_Chatbot_Rest_Query_Expander {

	/**
	 * Follow-up patterns that trigger LLM reformulation.
	 *
	 * @var array
	 */
	private static $followup_patterns = array(
		'/^(what|how|and|or)\s+about\b/i',
		'/^(and|but|also|or)\s+/i',
		'/^what\s+if\b/i',
		'/^(same|similar)\s+(for|with)\b/i',
		'/^how\s+about\b/i',
		'/^(is|are|do|does|can|will)\s+(it|that|this|they)\b/i',
		'/^(what|where|when|why|how)\s+(is|are|about)\s+(it|that|this|they)\b/i',
	);

	/**
	 * Common stopwords to filter out.
	 *
	 * @var array
	 */
	private static $stopwords = array(
		'a', 'an', 'the', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for',
		'of', 'with', 'by', 'from', 'as', 'is', 'was', 'are', 'were', 'been',
		'be', 'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would',
		'could', 'should', 'may', 'might', 'must', 'shall', 'can', 'need',
		'it', 'its', 'this', 'that', 'these', 'those', 'i', 'you', 'he', 'she',
		'we', 'they', 'what', 'which', 'who', 'whom', 'where', 'when', 'why',
		'how', 'all', 'each', 'every', 'both', 'few', 'more', 'most', 'other',
		'some', 'such', 'no', 'nor', 'not', 'only', 'own', 'same', 'so',
		'than', 'too', 'very', 'just', 'about', 'also', 'into', 'over',
		'after', 'before', 'between', 'under', 'again', 'there', 'here',
		'up', 'down', 'out', 'off', 'if', 'then', 'else', 'because',
		'yes', 'yeah', 'ok', 'okay', 'hi', 'hello', 'thanks', 'thank', 'please',
		'sorry', 'well', 'now', 'get', 'got', 'like', 'know', 'think', 'see',
		'want', 'way', 'look', 'make', 'go', 'going', 'come', 'take', 'tell',
		'me', 'my', 'your', 'our', 'their', 'him', 'her', 'us', 'them',
	);

	/**
	 * Maximum number of history exchanges to consider.
	 *
	 * @var int
	 */
	private $max_history_exchanges = 3;

	/**
	 * Maximum terms to extract from history.
	 *
	 * @var int
	 */
	private $max_history_terms = 8;

	/**
	 * Expand a query with context from conversation history.
	 *
	 * Uses LLM reformulation for detected follow-up questions, falls back
	 * to keyword extraction for standalone questions or when LLM unavailable.
	 *
	 * @since 1.0.0
	 * @param string $message    Current user message.
	 * @param mixed  $history    Conversation history array.
	 * @param object $llm_client Optional LLM client (Straico or Anthropic) for reformulation.
	 * @param array  $llm_config Optional LLM config (provider, api_keys, model, extended_thinking).
	 * @return string Expanded query string for FTS5 search.
	 */
	public function expand( $message, $history, $llm_client = null, $llm_config = array() ) {
		$message = trim( (string) $message );

		if ( empty( $message ) ) {
			return '';
		}

		// If no history, return original message.
		if ( ! is_array( $history ) || empty( $history ) ) {
			return $message;
		}

		// Check if this looks like a follow-up question.
		if ( $this->is_followup( $message ) && null !== $llm_client && ! empty( $llm_config ) ) {
			$reformulated = $this->reformulate_with_llm( $message, $history, $llm_client, $llm_config );

			if ( ! empty( $reformulated ) ) {
				return $reformulated;
			}
			// Fall through to keyword expansion on failure.
		}

		// Keyword-based expansion fallback.
		return $this->expand_with_keywords( $message, $history );
	}

	/**
	 * Check if a message appears to be a follow-up question.
	 *
	 * @since 1.0.0
	 * @param string $message User message to check.
	 * @return bool True if message looks like a follow-up.
	 */
	public function is_followup( $message ) {
		$message = trim( (string) $message );

		if ( empty( $message ) ) {
			return false;
		}

		// Short messages (4 words or fewer) are likely follow-ups.
		$word_count = str_word_count( $message );
		if ( $word_count <= 4 ) {
			return true;
		}

		// Check against follow-up patterns.
		foreach ( self::$followup_patterns as $pattern ) {
			if ( preg_match( $pattern, $message ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Reformulate a follow-up question using LLM.
	 *
	 * @since 1.0.0
	 * @param string $message    Current user message.
	 * @param array  $history    Conversation history.
	 * @param object $llm_client LLM client instance.
	 * @param array  $llm_config LLM configuration.
	 * @return string|null Reformulated query or null on failure.
	 */
	private function reformulate_with_llm( $message, $history, $llm_client, $llm_config ) {
		$provider = isset( $llm_config['provider'] ) ? $llm_config['provider'] : '';
		$api_keys = isset( $llm_config['api_keys'] ) ? $llm_config['api_keys'] : array();
		$model    = isset( $llm_config['model'] ) ? $llm_config['model'] : '';

		if ( empty( $api_keys ) || empty( $model ) ) {
			return null;
		}

		// Build conversation context (last 3 exchanges max).
		$context_lines = array();
		$history       = array_values( $history );
		$count         = 0;
		$max_exchanges = 6; // 3 exchanges = 6 messages.

		for ( $i = count( $history ) - 1; $i >= 0 && $count < $max_exchanges; $i-- ) {
			$item = $history[ $i ];
			if ( ! is_array( $item ) ) {
				continue;
			}

			$type    = isset( $item['type'] ) ? strtolower( $item['type'] ) : '';
			$content = isset( $item['content'] ) ? trim( (string) $item['content'] ) : '';

			if ( 'assistant' === $type ) {
				$type = 'bot';
			}

			if ( ( 'user' === $type || 'bot' === $type ) && ! empty( $content ) ) {
				// Truncate long messages.
				if ( strlen( $content ) > 200 ) {
					$content = substr( $content, 0, 200 ) . '...';
				}
				$prefix          = ( 'user' === $type ) ? 'User' : 'Assistant';
				$context_lines[] = $prefix . ': ' . $content;
				$count++;
			}
		}

		if ( empty( $context_lines ) ) {
			return null;
		}

		$context_lines = array_reverse( $context_lines );
		$context_text  = implode( "\n", $context_lines );

		// Build reformulation prompt.
		$prompt = "Given this conversation history:\n\n" . $context_text . "\n\n" .
			"The user now asks: \"" . $message . "\"\n\n" .
			"Rewrite this as a standalone search query that includes the necessary context. " .
			"Output ONLY the reformulated query (under 15 words), nothing else.";

		// Call LLM.
		if ( 'anthropic' === $provider ) {
			$extended_thinking = isset( $llm_config['extended_thinking'] ) ? (int) $llm_config['extended_thinking'] : 0;
			$result            = $llm_client->review( $api_keys, $model, '', $extended_thinking, $prompt, '', 'query_reformulation' );
		} else {
			// Straico.
			$result = $llm_client->review( $api_keys, $model, '', $prompt, '', 'query_reformulation' );
		}

		if ( is_wp_error( $result ) ) {
			error_log( '[Humata Chatbot] Query reformulation failed: ' . $result->get_error_message() );
			return null;
		}

		$reformulated = trim( (string) $result );

		// Basic validation - should be reasonable length.
		if ( empty( $reformulated ) || strlen( $reformulated ) > 200 ) {
			return null;
		}

		// Remove quotes if LLM wrapped the response.
		$reformulated = trim( $reformulated, '"\'' );

		return $reformulated;
	}

	/**
	 * Expand query using keyword extraction from history.
	 *
	 * Fallback method when LLM reformulation is unavailable or fails.
	 *
	 * @since 1.0.0
	 * @param string $message Current user message.
	 * @param array  $history Conversation history.
	 * @return string Expanded query string.
	 */
	private function expand_with_keywords( $message, $history ) {
		// Extract terms from current message.
		$current_terms = $this->extract_terms( $message );

		// If no history, return original message terms.
		if ( ! is_array( $history ) || empty( $history ) ) {
			return implode( ' ', $current_terms );
		}

		// Extract terms from conversation history.
		$history_terms = $this->extract_terms_from_history( $history );

		// Merge terms, prioritizing current message.
		$merged = $this->merge_terms( $current_terms, $history_terms );

		return implode( ' ', $merged );
	}

	/**
	 * Extract significant terms from a text string.
	 *
	 * @since 1.0.0
	 * @param string $text Text to extract terms from.
	 * @return array Array of extracted terms.
	 */
	private function extract_terms( $text ) {
		$text = strtolower( trim( $text ) );

		// Remove punctuation and special characters.
		$text = preg_replace( '/[^\p{L}\p{N}\s]/u', ' ', $text );

		// Normalize whitespace.
		$text = preg_replace( '/\s+/', ' ', $text );

		// Split into words.
		$words = explode( ' ', $text );

		// Filter words.
		$terms = array();
		foreach ( $words as $word ) {
			$word = trim( $word );

			// Skip short words.
			if ( strlen( $word ) < 3 ) {
				continue;
			}

			// Skip stopwords.
			if ( in_array( $word, self::$stopwords, true ) ) {
				continue;
			}

			// Skip pure numbers.
			if ( is_numeric( $word ) ) {
				continue;
			}

			$terms[] = $word;
		}

		return array_unique( $terms );
	}

	/**
	 * Extract terms from conversation history.
	 *
	 * Processes recent messages to find contextually relevant terms.
	 *
	 * @since 1.0.0
	 * @param array $history Conversation history array.
	 * @return array Array of extracted terms from history.
	 */
	private function extract_terms_from_history( $history ) {
		$history = array_values( $history );
		$all_terms = array();
		$exchanges_processed = 0;

		// Process from most recent backwards.
		for ( $i = count( $history ) - 1; $i >= 0 && $exchanges_processed < $this->max_history_exchanges * 2; $i-- ) {
			$item = $history[ $i ];

			if ( ! is_array( $item ) ) {
				continue;
			}

			$type = isset( $item['type'] ) ? strtolower( sanitize_text_field( $item['type'] ) ) : '';
			if ( 'assistant' === $type ) {
				$type = 'bot';
			}

			// Only process user and bot messages.
			if ( 'user' !== $type && 'bot' !== $type ) {
				continue;
			}

			$content = isset( $item['content'] ) ? (string) $item['content'] : '';
			$content = trim( $content );

			if ( empty( $content ) ) {
				continue;
			}

			// Extract terms from this message.
			$terms = $this->extract_terms( $content );

			// Weight user messages higher (they contain the actual questions).
			if ( 'user' === $type ) {
				foreach ( $terms as $term ) {
					$all_terms[] = $term;
					// Add user terms twice to increase their weight.
					$all_terms[] = $term;
				}
			} else {
				// Bot responses - only take key terms.
				$all_terms = array_merge( $all_terms, array_slice( $terms, 0, 5 ) );
			}

			$exchanges_processed++;
		}

		// Count term frequency and sort by frequency.
		$term_counts = array_count_values( $all_terms );
		arsort( $term_counts );

		// Return top terms.
		return array_slice( array_keys( $term_counts ), 0, $this->max_history_terms );
	}

	/**
	 * Merge current message terms with history terms.
	 *
	 * @since 1.0.0
	 * @param array $current_terms Terms from current message.
	 * @param array $history_terms Terms from conversation history.
	 * @return array Merged array of terms.
	 */
	private function merge_terms( $current_terms, $history_terms ) {
		// Start with current terms (highest priority).
		$merged = $current_terms;

		// Add history terms that aren't already present.
		foreach ( $history_terms as $term ) {
			if ( ! in_array( $term, $merged, true ) ) {
				$merged[] = $term;
			}
		}

		// Limit total terms to prevent overly broad searches.
		return array_slice( $merged, 0, 15 );
	}
}
