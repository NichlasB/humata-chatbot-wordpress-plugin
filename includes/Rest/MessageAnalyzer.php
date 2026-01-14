<?php
/**
 * Message Analyzer for AI-powered insights
 *
 * Processes chat messages using AI to generate summaries, intents, and other insights.
 *
 * @package Humata_Chatbot
 * @since 1.2.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Humata_Chatbot_Rest_Message_Analyzer
 *
 * Handles AI-powered analysis of chat messages for the analytics feature.
 *
 * @since 1.2.0
 */
class Humata_Chatbot_Rest_Message_Analyzer {

	/**
	 * Message logger instance.
	 *
	 * @since 1.2.0
	 * @var Humata_Chatbot_Rest_Message_Logger
	 */
	private $logger;

	/**
	 * Default system prompt for analysis.
	 *
	 * @since 1.2.0
	 * @var string
	 */
	const DEFAULT_SYSTEM_PROMPT = 'Analyze this chatbot conversation and provide a structured analysis.

USER MESSAGE: {user_message}
BOT RESPONSE: {bot_response}

Provide your analysis in the following JSON format:
{
  "summary": "1-2 sentence summary of what the user was asking",
  "intent": "Primary user intent category (e.g., information_request, support, feedback, complaint, purchase_inquiry, general_question)",
  "sentiment": "User sentiment: positive, neutral, negative, or mixed",
  "topics": ["array", "of", "key", "topics"],
  "unanswered_questions": ["any questions the bot did not fully address, or empty array if all addressed"]
}

Respond ONLY with the JSON object, no additional text or markdown formatting.';

	/**
	 * Constructor.
	 *
	 * @since 1.2.0
	 * @param Humata_Chatbot_Rest_Message_Logger $logger Message logger instance.
	 */
	public function __construct( Humata_Chatbot_Rest_Message_Logger $logger ) {
		$this->logger = $logger;
	}

	/**
	 * Check if AI processing is enabled.
	 *
	 * @since 1.2.0
	 * @return bool True if AI processing is enabled.
	 */
	public function is_enabled() {
		return (bool) get_option( 'humata_analytics_processing_enabled', false );
	}

	/**
	 * Get the configured provider.
	 *
	 * @since 1.2.0
	 * @return string Provider name (anthropic, openrouter, straico).
	 */
	public function get_provider() {
		return get_option( 'humata_analytics_provider', '' );
	}

	/**
	 * Get the system prompt for analysis.
	 *
	 * @since 1.2.0
	 * @return string System prompt.
	 */
	public function get_system_prompt() {
		$custom_prompt = get_option( 'humata_analytics_system_prompt', '' );
		return ! empty( $custom_prompt ) ? $custom_prompt : self::DEFAULT_SYSTEM_PROMPT;
	}

	/**
	 * Schedule analysis for a message.
	 *
	 * @since 1.2.0
	 * @param int $message_id Message ID to analyze.
	 * @return bool True if scheduled successfully.
	 */
	public function schedule_analysis( $message_id ) {
		if ( ! $this->is_enabled() ) {
			return false;
		}

		// Schedule for immediate execution.
		$scheduled = wp_schedule_single_event(
			time(),
			'humata_process_message_analysis',
			array( $message_id )
		);

		return false !== $scheduled;
	}

	/**
	 * Analyze a message and store insights.
	 *
	 * @since 1.2.0
	 * @param int $message_id Message ID to analyze.
	 * @return array|WP_Error Insights array or WP_Error on failure.
	 */
	public function analyze_message( $message_id ) {
		if ( ! $this->is_enabled() ) {
			return new WP_Error( 'processing_disabled', __( 'AI processing is disabled.', 'humata-chatbot' ) );
		}

		// Get the message.
		$message = $this->logger->get_message( $message_id );

		if ( is_wp_error( $message ) ) {
			return $message;
		}

		if ( null === $message ) {
			return new WP_Error( 'message_not_found', __( 'Message not found.', 'humata-chatbot' ) );
		}

		// Build the analysis prompt.
		$prompt = $this->build_analysis_prompt( $message['user_message'], $message['bot_response'] ?? '' );

		// Get the analysis from AI.
		$provider = $this->get_provider();
		$result   = $this->call_provider( $provider, $prompt );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Parse the response.
		$insights = $this->parse_analysis_response( $result );

		if ( is_wp_error( $insights ) ) {
			return $insights;
		}

		// Store the raw analysis.
		$insights['raw_analysis'] = $result;

		// Save to database.
		$insight_id = $this->logger->add_insight( $message_id, $insights, $provider );

		if ( is_wp_error( $insight_id ) ) {
			return $insight_id;
		}

		return $insights;
	}

	/**
	 * Build the analysis prompt.
	 *
	 * @since 1.2.0
	 * @param string $user_message User's message.
	 * @param string $bot_response Bot's response.
	 * @return string Complete prompt for analysis.
	 */
	private function build_analysis_prompt( $user_message, $bot_response ) {
		$system_prompt = $this->get_system_prompt();

		// Replace placeholders.
		$prompt = str_replace(
			array( '{user_message}', '{bot_response}' ),
			array( $user_message, $bot_response ),
			$system_prompt
		);

		return $prompt;
	}

	/**
	 * Call the configured AI provider.
	 *
	 * @since 1.2.0
	 * @param string $provider Provider name.
	 * @param string $prompt   Analysis prompt.
	 * @return string|WP_Error AI response or error.
	 */
	private function call_provider( $provider, $prompt ) {
		switch ( $provider ) {
			case 'anthropic':
				return $this->call_anthropic( $prompt );

			case 'openrouter':
				return $this->call_openrouter( $prompt );

			case 'straico':
				return $this->call_straico( $prompt );

			default:
				return new WP_Error(
					'invalid_provider',
					__( 'Invalid analytics provider configured.', 'humata-chatbot' )
				);
		}
	}

	/**
	 * Call Anthropic API for analysis.
	 *
	 * @since 1.2.0
	 * @param string $prompt Analysis prompt.
	 * @return string|WP_Error AI response or error.
	 */
	private function call_anthropic( $prompt ) {
		$api_keys = get_option( 'humata_analytics_api_key', array() );
		$model    = get_option( 'humata_analytics_model', 'claude-3-haiku-20240307' );

		if ( empty( $api_keys ) ) {
			return new WP_Error( 'no_api_key', __( 'Analytics API key not configured.', 'humata-chatbot' ) );
		}

		// Normalize keys to array.
		if ( is_string( $api_keys ) ) {
			$api_keys = array( $api_keys );
		}

		$api_key = reset( $api_keys );

		$payload = array(
			'model'      => $model,
			'max_tokens' => 1024,
			'messages'   => array(
				array(
					'role'    => 'user',
					'content' => $prompt,
				),
			),
		);

		$response = wp_remote_post(
			'https://api.anthropic.com/v1/messages',
			array(
				'timeout' => 30,
				'headers' => array(
					'x-api-key'         => $api_key,
					'anthropic-version' => '2023-06-01',
					'Content-Type'      => 'application/json',
				),
				'body'    => wp_json_encode( $payload ),
			)
		);

		return $this->parse_anthropic_response( $response );
	}

	/**
	 * Parse Anthropic API response.
	 *
	 * @since 1.2.0
	 * @param array|WP_Error $response HTTP response.
	 * @return string|WP_Error Content or error.
	 */
	private function parse_anthropic_response( $response ) {
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'api_error', __( 'Failed to connect to Anthropic API.', 'humata-chatbot' ) );
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );

		if ( $code >= 400 ) {
			error_log( '[Humata Chatbot] Analytics Anthropic API error: ' . $code . ' - ' . substr( $body, 0, 200 ) );
			return new WP_Error( 'api_error', __( 'Anthropic API returned an error.', 'humata-chatbot' ) );
		}

		$data = json_decode( $body, true );

		if ( ! is_array( $data ) || empty( $data['content'] ) ) {
			return new WP_Error( 'parse_error', __( 'Invalid response from Anthropic API.', 'humata-chatbot' ) );
		}

		$content = '';
		foreach ( $data['content'] as $block ) {
			if ( is_array( $block ) && 'text' === ( $block['type'] ?? '' ) ) {
				$content .= $block['text'];
			}
		}

		return trim( $content );
	}

	/**
	 * Call OpenRouter API for analysis.
	 *
	 * @since 1.2.0
	 * @param string $prompt Analysis prompt.
	 * @return string|WP_Error AI response or error.
	 */
	private function call_openrouter( $prompt ) {
		$api_keys = get_option( 'humata_analytics_api_key', array() );
		$model    = get_option( 'humata_analytics_model', 'anthropic/claude-3-haiku' );

		if ( empty( $api_keys ) ) {
			return new WP_Error( 'no_api_key', __( 'Analytics API key not configured.', 'humata-chatbot' ) );
		}

		if ( is_string( $api_keys ) ) {
			$api_keys = array( $api_keys );
		}

		$api_key = reset( $api_keys );

		$payload = array(
			'model'    => $model,
			'messages' => array(
				array(
					'role'    => 'user',
					'content' => $prompt,
				),
			),
		);

		$response = wp_remote_post(
			'https://openrouter.ai/api/v1/chat/completions',
			array(
				'timeout' => 30,
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
					'HTTP-Referer'  => home_url(),
				),
				'body'    => wp_json_encode( $payload ),
			)
		);

		return $this->parse_openai_style_response( $response, 'OpenRouter' );
	}

	/**
	 * Call Straico API for analysis.
	 *
	 * @since 1.2.0
	 * @param string $prompt Analysis prompt.
	 * @return string|WP_Error AI response or error.
	 */
	private function call_straico( $prompt ) {
		$api_keys = get_option( 'humata_analytics_api_key', array() );
		$model    = get_option( 'humata_analytics_model', '' );

		if ( empty( $api_keys ) ) {
			return new WP_Error( 'no_api_key', __( 'Analytics API key not configured.', 'humata-chatbot' ) );
		}

		if ( is_string( $api_keys ) ) {
			$api_keys = array( $api_keys );
		}

		$api_key = reset( $api_keys );

		$payload = array(
			'model'   => $model,
			'message' => $prompt,
		);

		$response = wp_remote_post(
			'https://api.straico.com/v0/prompt/completion',
			array(
				'timeout' => 30,
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $payload ),
			)
		);

		return $this->parse_straico_response( $response );
	}

	/**
	 * Parse OpenAI-style API response (used by OpenRouter).
	 *
	 * @since 1.2.0
	 * @param array|WP_Error $response     HTTP response.
	 * @param string         $provider_name Provider name for error messages.
	 * @return string|WP_Error Content or error.
	 */
	private function parse_openai_style_response( $response, $provider_name ) {
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'api_error', sprintf( __( 'Failed to connect to %s API.', 'humata-chatbot' ), $provider_name ) );
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );

		if ( $code >= 400 ) {
			error_log( "[Humata Chatbot] Analytics {$provider_name} API error: {$code} - " . substr( $body, 0, 200 ) );
			return new WP_Error( 'api_error', sprintf( __( '%s API returned an error.', 'humata-chatbot' ), $provider_name ) );
		}

		$data = json_decode( $body, true );

		if ( ! is_array( $data ) || empty( $data['choices'][0]['message']['content'] ) ) {
			return new WP_Error( 'parse_error', sprintf( __( 'Invalid response from %s API.', 'humata-chatbot' ), $provider_name ) );
		}

		return trim( $data['choices'][0]['message']['content'] );
	}

	/**
	 * Parse Straico API response.
	 *
	 * @since 1.2.0
	 * @param array|WP_Error $response HTTP response.
	 * @return string|WP_Error Content or error.
	 */
	private function parse_straico_response( $response ) {
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'api_error', __( 'Failed to connect to Straico API.', 'humata-chatbot' ) );
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );

		if ( $code >= 400 ) {
			error_log( '[Humata Chatbot] Analytics Straico API error: ' . $code . ' - ' . substr( $body, 0, 200 ) );
			return new WP_Error( 'api_error', __( 'Straico API returned an error.', 'humata-chatbot' ) );
		}

		$data = json_decode( $body, true );

		if ( ! is_array( $data ) ) {
			return new WP_Error( 'parse_error', __( 'Invalid response from Straico API.', 'humata-chatbot' ) );
		}

		// Straico response structure.
		$content = '';
		if ( isset( $data['data']['completion']['choices'][0]['message']['content'] ) ) {
			$content = $data['data']['completion']['choices'][0]['message']['content'];
		} elseif ( isset( $data['completion'] ) ) {
			$content = $data['completion'];
		}

		return trim( $content );
	}

	/**
	 * Parse the AI analysis response into structured data.
	 *
	 * @since 1.2.0
	 * @param string $response Raw AI response.
	 * @return array|WP_Error Parsed insights or error.
	 */
	private function parse_analysis_response( $response ) {
		// Try to extract JSON from response.
		$response = trim( $response );

		// Remove markdown code blocks if present.
		$response = preg_replace( '/^```json\s*/i', '', $response );
		$response = preg_replace( '/\s*```$/i', '', $response );
		$response = preg_replace( '/^```\s*/i', '', $response );

		// Try to parse JSON.
		$data = json_decode( $response, true );

		if ( ! is_array( $data ) ) {
			// Try to find JSON object in response.
			if ( preg_match( '/\{[^{}]*\}/', $response, $matches ) ) {
				$data = json_decode( $matches[0], true );
			}
		}

		if ( ! is_array( $data ) ) {
			// Return basic structure with the raw response as summary.
			return array(
				'summary'              => substr( $response, 0, 500 ),
				'intent'               => 'unknown',
				'sentiment'            => 'neutral',
				'topics'               => array(),
				'unanswered_questions' => array(),
			);
		}

		// Ensure all expected fields exist.
		$insights = array(
			'summary'              => isset( $data['summary'] ) ? sanitize_textarea_field( $data['summary'] ) : '',
			'intent'               => isset( $data['intent'] ) ? sanitize_text_field( $data['intent'] ) : 'unknown',
			'sentiment'            => isset( $data['sentiment'] ) ? sanitize_text_field( $data['sentiment'] ) : 'neutral',
			'topics'               => array(),
			'unanswered_questions' => array(),
		);

		// Handle topics array.
		if ( isset( $data['topics'] ) && is_array( $data['topics'] ) ) {
			$insights['topics'] = array_map( 'sanitize_text_field', $data['topics'] );
		}

		// Handle unanswered questions array.
		if ( isset( $data['unanswered_questions'] ) && is_array( $data['unanswered_questions'] ) ) {
			$insights['unanswered_questions'] = array_map( 'sanitize_text_field', $data['unanswered_questions'] );
		}

		return $insights;
	}

	/**
	 * Process a batch of unprocessed messages.
	 *
	 * @since 1.2.0
	 * @param int $limit Maximum messages to process.
	 * @return array Results with 'processed' and 'failed' counts.
	 */
	public function process_batch( $limit = 10 ) {
		$results = array(
			'processed' => 0,
			'failed'    => 0,
		);

		if ( ! $this->is_enabled() ) {
			return $results;
		}

		$messages = $this->logger->get_unprocessed_messages( $limit );

		if ( is_wp_error( $messages ) || empty( $messages ) ) {
			return $results;
		}

		foreach ( $messages as $message ) {
			$result = $this->analyze_message( $message['id'] );

			if ( is_wp_error( $result ) ) {
				$results['failed']++;
			} else {
				$results['processed']++;
			}

			// Small delay to avoid rate limiting.
			usleep( 100000 ); // 100ms
		}

		return $results;
	}

	/**
	 * Re-process a specific message.
	 *
	 * @since 1.2.0
	 * @param int $message_id Message ID to re-process.
	 * @return array|WP_Error New insights or error.
	 */
	public function reprocess_message( $message_id ) {
		return $this->analyze_message( $message_id );
	}
}
