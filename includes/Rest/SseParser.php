<?php
/**
 * Server-Sent Events (SSE) parser for Humata responses
 *
 * @package Humata_Chatbot
 * @since 1.0.0
 */

defined( 'ABSPATH' ) || exit;

class Humata_Chatbot_Rest_Sse_Parser {

    /**
     * Parse Server-Sent Events response from Humata.
     *
     * @since 1.0.0
     * @param string $response_body Raw response body.
     * @return string Parsed answer content.
     */
    public function parse( $response_body ) {
        $answer = '';
        $lines  = explode( "\n", (string) $response_body );

        foreach ( $lines as $line ) {
            $line = trim( $line );

            // SSE data lines start with "data: "
            if ( strpos( $line, 'data: ' ) === 0 ) {
                $json_str = substr( $line, 6 ); // Remove "data: " prefix

                // Skip empty data or [DONE] signals
                if ( empty( $json_str ) || $json_str === '[DONE]' ) {
                    continue;
                }

                $data = json_decode( $json_str, true );
                if ( $data && isset( $data['content'] ) ) {
                    $answer .= $data['content'];
                }
            }
        }

        return $answer;
    }
}


