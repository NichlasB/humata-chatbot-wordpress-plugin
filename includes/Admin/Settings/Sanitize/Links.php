<?php
/**
 * Auto-links + Intent links sanitizers
 *
 * @package Humata_Chatbot
 * @since 1.0.0
 */

defined( 'ABSPATH' ) || exit;

trait Humata_Chatbot_Admin_Settings_Sanitize_Links_Trait {

    /**
     * Sanitize auto-link phrase→URL mappings.
     *
     * Stored as an ordered array of rows: [ [ 'phrase' => string, 'url' => string ], ... ].
     *
     * @since 1.0.0
     * @param mixed $value
     * @return array
     */
    public function sanitize_auto_links( $value ) {
        if ( ! is_array( $value ) ) {
            return array();
        }

        $rows = array();
        foreach ( $value as $row ) {
            if ( ! is_array( $row ) ) {
                continue;
            }

            $phrase = isset( $row['phrase'] ) ? trim( sanitize_text_field( (string) $row['phrase'] ) ) : '';
            $url    = isset( $row['url'] ) ? trim( esc_url_raw( (string) $row['url'] ) ) : '';

            if ( '' === $phrase || '' === $url ) {
                continue;
            }

            $rows[] = array(
                'phrase' => $phrase,
                'url'    => $url,
            );

            if ( count( $rows ) >= 200 ) {
                break;
            }
        }

        return array_values( $rows );
    }

    /**
     * Get auto-link rules (phrase → URL), sanitized and normalized.
     *
     * @since 1.0.0
     * @return array
     */
    private function get_auto_links_settings() {
        $value = get_option( 'humata_auto_links', array() );
        if ( ! is_array( $value ) ) {
            $value = array();
        }

        // Reuse the sanitizer defensively to guarantee a stable shape.
        return $this->sanitize_auto_links( $value );
    }

    /**
     * Sanitize intent-based auto-link rules.
     *
     * Each intent has: intent_name, keywords (comma-separated), links array.
     *
     * @since 1.0.0
     * @param mixed $value Input value.
     * @return array Sanitized intents array.
     */
    public function sanitize_intent_links( $value ) {
        if ( ! is_array( $value ) ) {
            return array();
        }

        $intents = array();
        foreach ( $value as $intent ) {
            if ( ! is_array( $intent ) ) {
                continue;
            }

            $intent_name = isset( $intent['intent_name'] ) ? sanitize_text_field( trim( (string) $intent['intent_name'] ) ) : '';
            if ( '' === $intent_name ) {
                continue;
            }
            if ( strlen( $intent_name ) > 100 ) {
                $intent_name = substr( $intent_name, 0, 100 );
            }

            // Parse keywords (comma-separated).
            $keywords_raw = isset( $intent['keywords'] ) ? (string) $intent['keywords'] : '';
            $keywords_arr = array_map( 'trim', explode( ',', $keywords_raw ) );
            $keywords_arr = array_filter( $keywords_arr, function( $k ) {
                return '' !== $k;
            } );
            $keywords_arr = array_map( 'sanitize_text_field', $keywords_arr );
            $keywords_arr = array_unique( $keywords_arr );
            $keywords_arr = array_slice( $keywords_arr, 0, 50 ); // Max 50 keywords per intent.

            if ( empty( $keywords_arr ) ) {
                continue;
            }

            // Parse links.
            $links_raw = isset( $intent['links'] ) && is_array( $intent['links'] ) ? $intent['links'] : array();
            $links = array();
            foreach ( $links_raw as $link ) {
                if ( ! is_array( $link ) ) {
                    continue;
                }

                $title = isset( $link['title'] ) ? sanitize_text_field( trim( (string) $link['title'] ) ) : '';
                $url   = isset( $link['url'] ) ? esc_url_raw( trim( (string) $link['url'] ) ) : '';

                if ( '' === $title || '' === $url ) {
                    continue;
                }

                $links[] = array(
                    'title' => $title,
                    'url'   => $url,
                );

                if ( count( $links ) >= 10 ) {
                    break;
                }
            }

            // Parse accordions.
            $accordions_raw = isset( $intent['accordions'] ) && is_array( $intent['accordions'] ) ? $intent['accordions'] : array();
            $accordions = array();
            foreach ( $accordions_raw as $acc ) {
                if ( ! is_array( $acc ) ) {
                    continue;
                }

                $acc_title   = isset( $acc['title'] ) ? sanitize_text_field( trim( (string) $acc['title'] ) ) : '';
                $acc_content = isset( $acc['content'] ) ? wp_kses_post( trim( (string) $acc['content'] ) ) : '';

                // Limit content length to 1000 characters.
                if ( strlen( $acc_content ) > 1000 ) {
                    $acc_content = substr( $acc_content, 0, 1000 );
                }

                if ( '' === $acc_title || '' === $acc_content ) {
                    continue;
                }

                $accordions[] = array(
                    'title'   => $acc_title,
                    'content' => $acc_content,
                );

                if ( count( $accordions ) >= 5 ) {
                    break;
                }
            }

            // Intent must have at least links or accordions to be valid.
            if ( empty( $links ) && empty( $accordions ) ) {
                continue;
            }

            $intents[] = array(
                'intent_name' => $intent_name,
                'keywords'    => implode( ', ', $keywords_arr ),
                'links'       => $links,
                'accordions'  => $accordions,
            );

            if ( count( $intents ) >= 50 ) {
                break;
            }
        }

        return array_values( $intents );
    }

    /**
     * Get intent links settings, sanitized and normalized.
     *
     * @since 1.0.0
     * @return array
     */
    private function get_intent_links_settings() {
        $value = get_option( 'humata_intent_links', array() );
        if ( ! is_array( $value ) ) {
            $value = array();
        }

        return $this->sanitize_intent_links( $value );
    }
}








