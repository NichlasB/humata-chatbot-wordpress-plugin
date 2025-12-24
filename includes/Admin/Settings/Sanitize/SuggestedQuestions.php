<?php
/**
 * Suggested Questions Sanitizer
 *
 * @package Humata_Chatbot
 * @since 1.0.0
 */

defined( 'ABSPATH' ) || exit;

trait Humata_Chatbot_Admin_Settings_Sanitize_SuggestedQuestions_Trait {

	/**
	 * Sanitize suggested questions settings.
	 *
	 * @since 1.0.0
	 * @param mixed $value Input value.
	 * @return array Sanitized settings.
	 */
	public function sanitize_suggested_questions( $value ) {
		$defaults = array(
			'enabled'         => false,
			'mode'            => 'fixed',
			'fixed_questions' => array(),
			'categories'      => array(),
		);

		if ( ! is_array( $value ) ) {
			return $defaults;
		}

		$sanitized = array();

		// Enabled toggle.
		$sanitized['enabled'] = ! empty( $value['enabled'] );

		// Mode: fixed or randomized.
		$mode = isset( $value['mode'] ) ? sanitize_key( $value['mode'] ) : 'fixed';
		$sanitized['mode'] = in_array( $mode, array( 'fixed', 'randomized' ), true ) ? $mode : 'fixed';

		// Fixed questions (max 4).
		$sanitized['fixed_questions'] = $this->sanitize_fixed_questions(
			isset( $value['fixed_questions'] ) ? $value['fixed_questions'] : array()
		);

		// Categories (max 4).
		$sanitized['categories'] = $this->sanitize_question_categories(
			isset( $value['categories'] ) ? $value['categories'] : array()
		);

		return $sanitized;
	}

	/**
	 * Sanitize fixed questions array.
	 *
	 * @since 1.0.0
	 * @param mixed $questions Input questions.
	 * @return array Sanitized questions.
	 */
	private function sanitize_fixed_questions( $questions ) {
		if ( ! is_array( $questions ) ) {
			return array();
		}

		$sanitized = array();
		$order     = 0;

		foreach ( $questions as $question ) {
			if ( ! is_array( $question ) ) {
				continue;
			}

			$text = isset( $question['text'] ) ? sanitize_text_field( trim( (string) $question['text'] ) ) : '';

			// Skip empty questions.
			if ( '' === $text ) {
				continue;
			}

			// Limit text length to 150 characters.
			if ( mb_strlen( $text ) > 150 ) {
				$text = mb_substr( $text, 0, 150 );
			}

			$sanitized[] = array(
				'text'  => $text,
				'order' => $order,
			);

			$order++;

			// Max 4 questions.
			if ( count( $sanitized ) >= 4 ) {
				break;
			}
		}

		return $sanitized;
	}

	/**
	 * Sanitize question categories array.
	 *
	 * @since 1.0.0
	 * @param mixed $categories Input categories.
	 * @return array Sanitized categories.
	 */
	private function sanitize_question_categories( $categories ) {
		if ( ! is_array( $categories ) ) {
			return array();
		}

		$sanitized = array();

		foreach ( $categories as $category ) {
			if ( ! is_array( $category ) ) {
				continue;
			}

			$name = isset( $category['name'] ) ? sanitize_text_field( trim( (string) $category['name'] ) ) : '';

			// Limit name length to 50 characters.
			if ( mb_strlen( $name ) > 50 ) {
				$name = mb_substr( $name, 0, 50 );
			}

			// Parse questions.
			$questions_raw = isset( $category['questions'] ) && is_array( $category['questions'] )
				? $category['questions']
				: array();

			$questions = array();
			foreach ( $questions_raw as $q ) {
				$q_text = is_string( $q ) ? sanitize_text_field( trim( $q ) ) : '';

				if ( '' === $q_text ) {
					continue;
				}

				// Limit question text to 150 characters.
				if ( mb_strlen( $q_text ) > 150 ) {
					$q_text = mb_substr( $q_text, 0, 150 );
				}

				$questions[] = $q_text;

				// Max 20 questions per category.
				if ( count( $questions ) >= 20 ) {
					break;
				}
			}

			// Category must have at least 1 question with content.
			if ( empty( $questions ) ) {
				continue;
			}

			// Name is optional but useful; allow empty name with questions.
			$sanitized[] = array(
				'name'      => $name,
				'questions' => $questions,
			);

			// Max 4 categories.
			if ( count( $sanitized ) >= 4 ) {
				break;
			}
		}

		return $sanitized;
	}

	/**
	 * Get suggested questions settings, sanitized and normalized.
	 *
	 * @since 1.0.0
	 * @return array
	 */
	public function get_suggested_questions_settings() {
		$value = get_option( 'humata_suggested_questions', array() );
		if ( ! is_array( $value ) ) {
			$value = array();
		}

		return $this->sanitize_suggested_questions( $value );
	}
}
