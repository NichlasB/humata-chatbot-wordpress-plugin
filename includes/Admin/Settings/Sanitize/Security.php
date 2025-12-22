<?php
/**
 * Security sanitizers
 *
 * @package Humata_Chatbot
 * @since 1.0.0
 */

defined( 'ABSPATH' ) || exit;

trait Humata_Chatbot_Admin_Settings_Sanitize_Security_Trait {

    /**
     * Sanitize Turnstile appearance option.
     *
     * @since 1.0.0
     * @param string $value Input value.
     * @return string Sanitized value.
     */
    public function sanitize_turnstile_appearance( $value ) {
        $valid = array( 'managed', 'non-interactive', 'interaction-only' );
        return in_array( $value, $valid, true ) ? $value : 'managed';
    }
}


