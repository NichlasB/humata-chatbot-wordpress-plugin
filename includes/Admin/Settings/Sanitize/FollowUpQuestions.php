<?php
/**
 * Follow-Up Questions Sanitizer
 *
 * @package Humata_Chatbot
 * @since 1.0.0
 */

defined( 'ABSPATH' ) || exit;

trait Humata_Chatbot_Admin_Settings_Sanitize_FollowUpQuestions_Trait {

	/**
	 * Sanitize follow-up questions settings.
	 *
	 * @since 1.0.0
	 * @param mixed $value Input value.
	 * @return array Sanitized settings.
	 */
	public function sanitize_followup_questions( $value ) {
		$defaults = array(
			'enabled'             => false,
			'provider'            => 'straico',
			'straico_api_keys'    => array(),
			'straico_model'       => '',
			'anthropic_api_keys'  => array(),
			'anthropic_model'     => 'claude-3-5-sonnet-20241022',
			'anthropic_extended_thinking' => 0,
			'openrouter_api_keys' => array(),
			'openrouter_model'    => HUMATA_DEFAULT_OPENROUTER_MODEL,
			'max_question_length' => 80,
			'topic_scope'         => '',
			'custom_instructions' => '',
		);

		if ( ! is_array( $value ) ) {
			return $defaults;
		}

		$sanitized = array();

		// Enabled toggle.
		$sanitized['enabled'] = ! empty( $value['enabled'] );

		// Provider: straico, anthropic, or openrouter.
		$provider = isset( $value['provider'] ) ? sanitize_key( $value['provider'] ) : 'straico';
		$sanitized['provider'] = in_array( $provider, array( 'straico', 'anthropic', 'openrouter' ), true ) ? $provider : 'straico';

		// Straico API keys (array for rotation).
		$sanitized['straico_api_keys'] = $this->sanitize_followup_api_keys(
			isset( $value['straico_api_keys'] ) ? $value['straico_api_keys'] : ''
		);

		// Straico model.
		$sanitized['straico_model'] = isset( $value['straico_model'] )
			? sanitize_text_field( trim( (string) $value['straico_model'] ) )
			: '';

		// Anthropic API keys (array for rotation).
		$sanitized['anthropic_api_keys'] = $this->sanitize_followup_api_keys(
			isset( $value['anthropic_api_keys'] ) ? $value['anthropic_api_keys'] : ''
		);

		// Anthropic model.
		$anthropic_model = isset( $value['anthropic_model'] )
			? sanitize_text_field( trim( (string) $value['anthropic_model'] ) )
			: 'claude-3-5-sonnet-20241022';
		$sanitized['anthropic_model'] = '' !== $anthropic_model ? $anthropic_model : 'claude-3-5-sonnet-20241022';

		// Anthropic extended thinking.
		$sanitized['anthropic_extended_thinking'] = ! empty( $value['anthropic_extended_thinking'] ) ? 1 : 0;

		// OpenRouter API keys (array for rotation).
		$sanitized['openrouter_api_keys'] = $this->sanitize_followup_api_keys(
			isset( $value['openrouter_api_keys'] ) ? $value['openrouter_api_keys'] : ''
		);

		// OpenRouter model.
		$openrouter_model = isset( $value['openrouter_model'] )
			? sanitize_text_field( trim( (string) $value['openrouter_model'] ) )
			: HUMATA_DEFAULT_OPENROUTER_MODEL;
		$sanitized['openrouter_model'] = '' !== $openrouter_model ? $openrouter_model : HUMATA_DEFAULT_OPENROUTER_MODEL;

		// Max question length (30-150 chars, default 80).
		$max_len = isset( $value['max_question_length'] ) ? absint( $value['max_question_length'] ) : 80;
		if ( $max_len < 30 ) {
			$max_len = 30;
		}
		if ( $max_len > 150 ) {
			$max_len = 150;
		}
		$sanitized['max_question_length'] = $max_len;

		// Topic scope (optional, max 500 chars) - defines the allowed topic boundary.
		$topic_scope = isset( $value['topic_scope'] )
			? sanitize_textarea_field( trim( (string) $value['topic_scope'] ) )
			: '';
		$sanitized['topic_scope'] = mb_substr( $topic_scope, 0, 500 );

		// Custom instructions (optional, max 1000 chars).
		$custom_instructions = isset( $value['custom_instructions'] )
			? sanitize_textarea_field( trim( (string) $value['custom_instructions'] ) )
			: '';
		$sanitized['custom_instructions'] = mb_substr( $custom_instructions, 0, 1000 );

		return $sanitized;
	}

	/**
	 * Sanitize API keys from textarea (one per line) to array.
	 *
	 * @since 1.0.0
	 * @param mixed $value Input value (string from textarea or array).
	 * @return array Sanitized array of API keys.
	 */
	private function sanitize_followup_api_keys( $value ) {
		// If already an array, sanitize each key.
		if ( is_array( $value ) ) {
			$keys = array();
			foreach ( $value as $key ) {
				$key = sanitize_text_field( trim( (string) $key ) );
				if ( '' !== $key ) {
					$keys[] = $key;
				}
			}
			return $keys;
		}

		// Parse from textarea (one key per line).
		if ( ! is_string( $value ) ) {
			return array();
		}

		$lines = explode( "\n", $value );
		$keys  = array();

		foreach ( $lines as $line ) {
			$key = sanitize_text_field( trim( $line ) );
			if ( '' !== $key ) {
				$keys[] = $key;
			}
		}

		return $keys;
	}

	/**
	 * Get follow-up questions settings, sanitized and normalized.
	 *
	 * @since 1.0.0
	 * @return array
	 */
	public function get_followup_questions_settings() {
		$value = get_option( 'humata_followup_questions', array() );
		if ( ! is_array( $value ) ) {
			$value = array();
		}

		return $this->sanitize_followup_questions( $value );
	}
}
