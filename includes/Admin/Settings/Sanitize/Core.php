<?php
/**
 * Core sanitizers
 *
 * @package Humata_Chatbot
 * @since 1.0.0
 */

defined( 'ABSPATH' ) || exit;

trait Humata_Chatbot_Admin_Settings_Sanitize_Core_Trait {

    /**
     * Sanitize location option.
     *
     * @since 1.0.0
     * @param string $value Input value.
     * @return string Sanitized value.
     */
    public function sanitize_location( $value ) {
        $valid = array( 'homepage', 'dedicated', 'shortcode' );
        return in_array( $value, $valid, true ) ? $value : 'dedicated';
    }

    /**
     * Sanitize theme option.
     *
     * @since 1.0.0
     * @param string $value Input value.
     * @return string Sanitized value.
     */
    public function sanitize_theme( $value ) {
        $valid = array( 'dark', 'light', 'auto' );
        return in_array( $value, $valid, true ) ? $value : 'auto';
    }

    /**
     * Sanitize a checkbox value to 0/1.
     *
     * @since 1.0.0
     * @param mixed $value Input value.
     * @return int 0 or 1.
     */
    public function sanitize_checkbox( $value ) {
        return empty( $value ) ? 0 : 1;
    }

    public function sanitize_max_prompt_chars( $value ) {
        $value = absint( $value );

        if ( $value <= 0 ) {
            return 3000;
        }

        if ( $value > 100000 ) {
            return 100000;
        }

        return $value;
    }

    /**
     * Sanitize bot response disclaimer HTML.
     * Allows limited HTML: links, bold, italic, line breaks.
     *
     * @since 1.0.0
     * @param string $value Input value.
     * @return string Sanitized HTML.
     */
    public function sanitize_bot_response_disclaimer( $value ) {
        if ( ! is_string( $value ) ) {
            return '';
        }

        $allowed_html = array(
            'a'      => array(
                'href'   => array(),
                'target' => array(),
                'rel'    => array(),
                'title'  => array(),
            ),
            'strong' => array(),
            'b'      => array(),
            'em'     => array(),
            'i'      => array(),
            'br'     => array(),
        );

        return wp_kses( $value, $allowed_html );
    }

    /**
     * Sanitize avatar size option.
     *
     * @since 1.0.0
     * @param mixed $value Input value.
     * @return int Clamped value between 32 and 64.
     */
    public function sanitize_avatar_size( $value ) {
        $value = absint( $value );
        if ( $value < 32 ) {
            $value = 32;
        }
        if ( $value > 64 ) {
            $value = 64;
        }
        return $value;
    }

    /**
     * Sanitize second-stage LLM provider selection.
     *
     * @since 1.0.0
     * @param string $value Input value.
     * @return string Sanitized provider value.
     */
    public function sanitize_second_llm_provider( $value ) {
        $value = sanitize_text_field( (string) $value );
        $valid = array( 'none', 'straico', 'anthropic', 'openrouter' );
        return in_array( $value, $valid, true ) ? $value : 'none';
    }

    /**
     * Sanitize search provider selection.
     *
     * @since 1.0.0
     * @param string $value Input value.
     * @return string Sanitized provider value ('humata' or 'local').
     */
    public function sanitize_search_provider( $value ) {
        $value = sanitize_text_field( (string) $value );
        $valid = array( 'humata', 'local' );
        return in_array( $value, $valid, true ) ? $value : 'humata';
    }

    /**
     * Sanitize Local Search first-stage LLM provider selection.
     *
     * First-stage requires an LLM, so 'none' is not valid.
     *
     * @since 1.0.0
     * @param string $value Input value.
     * @return string Sanitized provider value ('straico' or 'anthropic').
     */
    public function sanitize_local_first_llm_provider( $value ) {
        $value = sanitize_text_field( (string) $value );
        $valid = array( 'straico', 'anthropic', 'openrouter' );
        return in_array( $value, $valid, true ) ? $value : 'straico';
    }

    /**
     * Sanitize Local Search second-stage LLM provider selection.
     *
     * @since 1.0.0
     * @param string $value Input value.
     * @return string Sanitized provider value ('none', 'straico', or 'anthropic').
     */
    public function sanitize_local_second_llm_provider( $value ) {
        $value = sanitize_text_field( (string) $value );
        $valid = array( 'none', 'straico', 'anthropic', 'openrouter' );
        return in_array( $value, $valid, true ) ? $value : 'none';
    }
}















