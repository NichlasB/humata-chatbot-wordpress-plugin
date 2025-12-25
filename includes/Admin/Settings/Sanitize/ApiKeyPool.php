<?php
/**
 * API Key Pool sanitization trait
 *
 * Handles sanitization for API key arrays used in rotation.
 *
 * @package Humata_Chatbot
 * @since 1.0.0
 */

defined( 'ABSPATH' ) || exit;

trait Humata_Chatbot_Admin_Settings_Sanitize_Api_Key_Pool_Trait {

    /**
     * Sanitize an API key pool (array of keys).
     *
     * Handles backward compatibility with single string values.
     *
     * @since 1.0.0
     * @param mixed $value Input value.
     * @return array Sanitized array of API keys.
     */
    public function sanitize_api_key_pool( $value ) {
        // Handle backward compatibility with single string.
        if ( is_string( $value ) ) {
            $value = trim( sanitize_text_field( $value ) );
            return '' !== $value ? array( $value ) : array();
        }

        if ( ! is_array( $value ) ) {
            return array();
        }

        $sanitized = array();
        foreach ( $value as $key ) {
            if ( is_string( $key ) ) {
                $key = trim( sanitize_text_field( $key ) );
                if ( '' !== $key ) {
                    $sanitized[] = $key;
                }
            }
        }

        return $sanitized;
    }
}
