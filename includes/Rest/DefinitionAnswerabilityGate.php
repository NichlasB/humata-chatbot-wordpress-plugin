<?php

defined( 'ABSPATH' ) || exit;

class Humata_Chatbot_Rest_Definition_Answerability_Gate {

	public function get_definition_intent( $message ) {
		$message = trim( (string) $message );

		if ( '' === $message ) {
			return array(
				'is_definition' => false,
				'term'          => '',
			);
		}

		$patterns = array(
			'/^\s*define\s+(.+?)[\?\.!]*\s*$/i',
			'/^\s*what\s+does\s+(.+?)\s+mean[\?\.!]*\s*$/i',
			'/^\s*(?:meaning|definition)\s+of\s+(.+?)[\?\.!]*\s*$/i',
			'/^\s*what\s+is\s+(?:a|an)\s+(.+?)[\?\.!]*\s*$/i',
			'/^\s*what\s+is\s+([a-z0-9][a-z0-9_\-]*)[\?\.!]*\s*$/i',
		);

		foreach ( $patterns as $pattern ) {
			if ( preg_match( $pattern, $message, $matches ) ) {
				$term = isset( $matches[1] ) ? $matches[1] : '';
				$term = $this->normalize_term( $term );

				if ( '' === $term || $this->is_pronoun_term( $term ) ) {
					return array(
						'is_definition' => false,
						'term'          => '',
					);
				}

				$word_count = str_word_count( $term );
				if ( $word_count <= 0 || $word_count > 4 ) {
					return array(
						'is_definition' => false,
						'term'          => '',
					);
				}

				return array(
					'is_definition' => true,
					'term'          => $term,
				);
			}
		}

		return array(
			'is_definition' => false,
			'term'          => '',
		);
	}

	public function filter_context( $message, $context, $max_sections = 5 ) {
		$context = (string) $context;

		$intent = $this->get_definition_intent( $message );
		if ( empty( $intent['is_definition'] ) || empty( $intent['term'] ) ) {
			return array(
				'is_definition'    => false,
				'term'             => '',
				'context'          => $context,
				'total_sections'   => 0,
				'matched_sections' => 0,
			);
		}

		$term = (string) $intent['term'];
		$max_sections = absint( $max_sections );
		if ( $max_sections <= 0 ) {
			$max_sections = 5;
		}
		if ( $max_sections > 20 ) {
			$max_sections = 20;
		}

		$sections = preg_split( '/\n\s*---\s*\n/s', $context );
		if ( ! is_array( $sections ) ) {
			$sections = array( $context );
		}

		$matched = array();
		foreach ( $sections as $section ) {
			$section = trim( (string) $section );
			if ( '' === $section ) {
				continue;
			}

			if ( $this->has_definition_evidence( $section, $term ) ) {
				$matched[] = $section;
				if ( count( $matched ) >= $max_sections ) {
					break;
				}
			}
		}

		return array(
			'is_definition'    => true,
			'term'             => $term,
			'context'          => implode( "\n\n---\n\n", $matched ),
			'total_sections'   => count( $sections ),
			'matched_sections' => count( $matched ),
		);
	}

	private function normalize_term( $term ) {
		$term = trim( (string) $term );
		$term = preg_replace( '/[\?\.!]+\s*$/', '', $term );
		$term = trim( $term, " \t\n\r\0\x0B\"'" );
		$term = preg_replace( '/\s+/', ' ', $term );
		$term = preg_replace( '/^(?:a|an|the)\s+/i', '', $term );
		$term = trim( $term );

		return strtolower( $term );
	}

	private function is_pronoun_term( $term ) {
		$term = strtolower( trim( (string) $term ) );
		return in_array( $term, array( 'it', 'this', 'that', 'they', 'them', 'he', 'she', 'him', 'her', 'there', 'here' ), true );
	}

	private function has_definition_evidence( $text, $term ) {
		$text = (string) $text;
		$term = $this->normalize_term( $term );

		if ( '' === $text || '' === $term ) {
			return false;
		}

		$term_core = $this->build_term_core_pattern( $term );
		if ( '' === $term_core ) {
			return false;
		}

		$patterns = array(
			'/\b(?:a|an|the)\s+' . $term_core . '\b\s+(?:is|are)\b/i',
			'/\b' . $term_core . '\b\s+(?:is|are)\s+(?:a|an|the)\b/i',
			'/\b' . $term_core . '\b\s+(?:refers\s+to|means|is\s+defined\s+as)\b/i',
			'/\b(?:definition|meaning)\s+of\s+' . $term_core . '\b/i',
		);

		foreach ( $patterns as $pattern ) {
			if ( preg_match( $pattern, $text ) ) {
				return true;
			}
		}

		return false;
	}

	private function build_term_core_pattern( $term ) {
		$term = strtolower( trim( (string) $term ) );
		$term = preg_replace( '/\s+/', ' ', $term );
		if ( '' === $term ) {
			return '';
		}

		$words = preg_split( '/\s+/', $term );
		if ( ! is_array( $words ) || empty( $words ) ) {
			return '';
		}

		$escaped = array();
		foreach ( $words as $word ) {
			$word = trim( (string) $word );
			if ( '' === $word ) {
				continue;
			}
			$escaped[] = preg_quote( $word, '/' );
		}

		if ( empty( $escaped ) ) {
			return '';
		}

		if ( 1 === count( $escaped ) ) {
			$core = $escaped[0];
			if ( ! preg_match( '/s$/i', $term ) && preg_match( '/^[a-z0-9]+$/i', $term ) ) {
				$core .= 's?';
			}
			return $core;
		}

		return implode( '\\s+', $escaped );
	}
}
