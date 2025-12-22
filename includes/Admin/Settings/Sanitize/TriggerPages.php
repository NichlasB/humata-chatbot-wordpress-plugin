<?php
/**
 * Trigger Pages sanitizers + defaults
 *
 * @package Humata_Chatbot
 * @since 1.0.0
 */

defined( 'ABSPATH' ) || exit;

trait Humata_Chatbot_Admin_Settings_Sanitize_TriggerPages_Trait {

    /**
     * Get default trigger pages option.
     *
     * @since 1.0.0
     * @return array
     */
    public static function get_default_trigger_pages_option() {
        return array();
    }

    /**
     * Get trigger pages settings merged with defaults.
     *
     * @since 1.0.0
     * @return array
     */
    private function get_trigger_pages_settings() {
        $value = get_option( 'humata_trigger_pages', array() );
        if ( ! is_array( $value ) ) {
            $value = array();
        }

        return $value;
    }

    /**
     * Sanitize trigger pages settings.
     *
     * @since 1.0.0
     * @param mixed $value
     * @return array
     */
    public function sanitize_trigger_pages( $value ) {
        if ( ! is_array( $value ) ) {
            return array();
        }

        $pages = array();
        foreach ( $value as $row ) {
            if ( ! is_array( $row ) ) {
                continue;
            }

            $title     = isset( $row['title'] ) ? sanitize_text_field( (string) $row['title'] ) : '';
            $link_text = isset( $row['link_text'] ) ? sanitize_text_field( (string) $row['link_text'] ) : '';
            $content   = isset( $row['content'] ) ? trim( (string) $row['content'] ) : '';

            // Skip if required fields are empty.
            if ( '' === $title || '' === $link_text || '' === $content ) {
                continue;
            }

            // Allow safe HTML in content.
            $content = wp_kses_post( $content );

            $pages[] = array(
                'title'     => $title,
                'link_text' => $link_text,
                'content'   => $content,
            );

            // Limit to 20 pages.
            if ( count( $pages ) >= 20 ) {
                break;
            }
        }

        return array_values( $pages );
    }
}
