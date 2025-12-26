<?php
/**
 * Bot Protection Sanitizers
 *
 * Sanitization methods for bot protection settings.
 *
 * @package Humata_Chatbot
 * @since 1.0.0
 */

defined( 'ABSPATH' ) || exit;

trait Humata_Chatbot_Admin_Settings_Sanitize_Bot_Protection_Trait {

    /**
     * Sanitize PoW difficulty value.
     *
     * @since 1.0.0
     * @param mixed $value Value to sanitize.
     * @return int Sanitized value (1-8).
     */
    public function sanitize_pow_difficulty( $value ) {
        $value = absint( $value );
        if ( $value < 1 ) {
            return 4; // Default.
        }
        if ( $value > 8 ) {
            return 8; // Max.
        }
        return $value;
    }

    /**
     * Sanitize delay threshold count value.
     *
     * @since 1.0.0
     * @param mixed $value Value to sanitize.
     * @return int Sanitized value (0-1000).
     */
    public function sanitize_delay_threshold_count( $value ) {
        $value = absint( $value );
        if ( $value > 1000 ) {
            return 1000;
        }
        return $value;
    }

    /**
     * Sanitize delay seconds value.
     *
     * @since 1.0.0
     * @param mixed $value Value to sanitize.
     * @return int Sanitized value (0-10).
     */
    public function sanitize_delay_seconds( $value ) {
        $value = absint( $value );
        if ( $value > 10 ) {
            return 10;
        }
        return $value;
    }

    /**
     * Sanitize cooldown minutes value.
     *
     * @since 1.0.0
     * @param mixed $value Value to sanitize.
     * @return int Sanitized value (1-1440).
     */
    public function sanitize_cooldown_minutes( $value ) {
        $value = absint( $value );
        if ( $value < 1 ) {
            return 30; // Default.
        }
        if ( $value > 1440 ) {
            return 1440; // Max 24 hours.
        }
        return $value;
    }
}
