<?php
/**
 * Chat history context builder (REST)
 *
 * @package Humata_Chatbot
 * @since 1.0.0
 */

defined( 'ABSPATH' ) || exit;

class Humata_Chatbot_Rest_History_Builder {

    /**
     * Build a compact plain-text context string from frontend chat history.
     *
     * @since 1.0.0
     * @param mixed $history History array from the request.
     * @return string
     */
    public function build_context( $history ) {
        if ( ! is_array( $history ) ) {
            return '';
        }

        $max_chars = 12000;
        $max_items = 50;

        $history  = array_values( $history );
        $lines    = array();
        $used     = 0;
        $included = 0;

        for ( $i = count( $history ) - 1; $i >= 0; $i-- ) {
            if ( $included >= $max_items ) {
                break;
            }

            $item = $history[ $i ];
            if ( ! is_array( $item ) ) {
                continue;
            }

            $type = isset( $item['type'] ) ? strtolower( sanitize_text_field( $item['type'] ) ) : '';
            if ( 'assistant' === $type ) {
                $type = 'bot';
            }
            if ( 'user' !== $type && 'bot' !== $type ) {
                continue;
            }

            $content = isset( $item['content'] ) ? sanitize_textarea_field( $item['content'] ) : '';
            if ( ! is_string( $content ) ) {
                $content = '';
            }
            $content = trim( $content );
            if ( '' === $content ) {
                continue;
            }

            $content = str_replace( array( "\r\n", "\r" ), "\n", $content );
            $content = str_replace( "\n", "\n    ", $content );

            $prefix = ( 'user' === $type ) ? 'User: ' : 'Assistant: ';
            $line   = $prefix . $content;

            $line_len = strlen( $line );
            if ( $used + $line_len + 1 > $max_chars ) {
                if ( 0 === $used ) {
                    $available = $max_chars - strlen( $prefix );
                    if ( $available < 0 ) {
                        $available = 0;
                    }
                    $line = $prefix . substr( $content, -$available );
                    $lines[] = $line;
                }
                break;
            }

            $lines[] = $line;
            $used += $line_len + 1;
            $included++;
        }

        if ( empty( $lines ) ) {
            return '';
        }

        $lines = array_reverse( $lines );
        array_unshift( $lines, 'Conversation so far:' );

        return implode( "\n", $lines );
    }
}


