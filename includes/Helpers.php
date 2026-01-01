<?php
/**
 * Helper Functions
 *
 * Centralized utility functions for common option validation and retrieval.
 *
 * @package Humata_Chatbot
 * @since 1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Get the max prompt characters setting with validation.
 *
 * @since 1.0.0
 * @return int Clamped value between 1 and 100000, default 3000.
 */
function humata_get_max_prompt_chars() {
	$value = absint( get_option( 'humata_max_prompt_chars', 3000 ) );
	if ( $value < 1 ) {
		return 3000;
	}
	if ( $value > 100000 ) {
		return 100000;
	}
	return $value;
}

/**
 * Get the proof-of-work difficulty setting with validation.
 *
 * @since 1.0.0
 * @return int Clamped value between 1 and 8, default 4.
 */
function humata_get_pow_difficulty() {
	$value = absint( get_option( 'humata_pow_difficulty', 4 ) );
	if ( $value < 1 ) {
		return 4;
	}
	if ( $value > 8 ) {
		return 8;
	}
	return $value;
}
