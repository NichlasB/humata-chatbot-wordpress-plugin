<?php
/**
 * Straico API client (REST)
 *
 * @package Humata_Chatbot
 * @since 1.0.0
 */

defined( 'ABSPATH' ) || exit;

class Humata_Chatbot_Rest_Straico_Client {

    /**
     * Straico API base URL.
     *
     * @var string
     */
    const STRAICO_API_BASE = 'https://api.straico.com/v2';

    private function get_request_failed_message() {
        return __( 'Your message request failed. Try again. If problem persists, please contact us.', 'humata-chatbot' );
    }

    /**
     * Call Straico to review a Humata answer.
     *
     * @since 1.0.0
     * @param string $straico_api_key Straico API key.
     * @param string $model Straico model ID.
     * @param string $system_prompt Optional system prompt.
     * @param string $user_question User's original question.
     * @param string $humata_answer Humata's generated answer.
     * @return string|WP_Error Reviewed answer or error.
     */
    public function review( $straico_api_key, $model, $system_prompt, $user_question, $humata_answer ) {
        $straico_api_key = trim( (string) $straico_api_key );
        $model           = trim( (string) $model );
        $system_prompt   = trim( (string) $system_prompt );
        $user_question   = trim( (string) $user_question );
        $humata_answer   = trim( (string) $humata_answer );

        if ( '' === $straico_api_key || '' === $model ) {
            return new WP_Error(
                'configuration_error',
                $this->get_request_failed_message(),
                array( 'status' => 500 )
            );
        }

        $messages = array();
        if ( '' !== $system_prompt ) {
            $messages[] = array(
                'role'    => 'system',
                'content' => array(
                    array(
                        'type' => 'text',
                        'text' => $system_prompt,
                    ),
                ),
            );
        }

        $messages[] = array(
            'role'    => 'user',
            'content' => array(
                array(
                    'type' => 'text',
                    'text' => "User question:\n" . $user_question . "\n\nHumata answer:\n" . $humata_answer,
                ),
            ),
        );

        $payload = array(
            'model'    => $model,
            'messages' => $messages,
        );

        $headers = array(
            'Authorization' => 'Bearer ' . $straico_api_key,
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
        );

        // Straico API docs show chat completions at /v2/chat/completions.
        $endpoints = array(
            trailingslashit( self::STRAICO_API_BASE ) . 'chat/completions',
            'https://api.straico.com/v0/chat/completions',
        );

        /**
         * Allow overriding the Straico endpoints (in order) to try.
         *
         * @since 1.0.0
         * @param array $endpoints List of endpoint URLs.
         */
        $endpoints = apply_filters( 'humata_chatbot_straico_endpoints', $endpoints );

        if ( ! is_array( $endpoints ) || empty( $endpoints ) ) {
            $endpoints = array( trailingslashit( self::STRAICO_API_BASE ) . 'chat/completions' );
        }

        $last_error = null;

        foreach ( $endpoints as $endpoint ) {
            $endpoint = esc_url_raw( (string) $endpoint );
            if ( '' === $endpoint ) {
                continue;
            }

            $response = wp_remote_post(
                $endpoint,
                array(
                    'timeout' => 60,
                    'headers' => $headers,
                    'body'    => wp_json_encode( $payload ),
                )
            );

            if ( is_wp_error( $response ) ) {
                $last_error = new WP_Error(
                    'straico_api_error',
                    $this->get_request_failed_message(),
                    array( 'status' => 502 )
                );
                break;
            }

            $code = wp_remote_retrieve_response_code( $response );
            $body = wp_remote_retrieve_body( $response );

            if ( defined( 'WP_DEBUG' ) && WP_DEBUG && $code >= 400 ) {
                $body_snippet = is_string( $body ) ? substr( wp_strip_all_tags( $body ), 0, 500 ) : '';
                error_log( '[Humata Chatbot] Straico error: endpoint=' . $endpoint . ' status=' . (int) $code . ' body=' . $body_snippet );
            }

            // Try next endpoint if not found / method not allowed.
            if ( 404 === $code || 405 === $code ) {
                $last_error = new WP_Error(
                    'straico_api_error',
                    $this->get_request_failed_message(),
                    array( 'status' => $code )
                );
                continue;
            }

            if ( $code >= 400 ) {
                $last_error = new WP_Error(
                    'straico_api_error',
                    $this->get_request_failed_message(),
                    array( 'status' => $code )
                );
                break;
            }

            $data    = json_decode( $body, true );
            $content = '';

            if ( is_array( $data ) ) {
                if ( isset( $data['choices'][0]['message']['content'] ) && is_string( $data['choices'][0]['message']['content'] ) ) {
                    $content = $data['choices'][0]['message']['content'];
                } elseif ( isset( $data['choices'][0]['text'] ) && is_string( $data['choices'][0]['text'] ) ) {
                    $content = $data['choices'][0]['text'];
                } elseif ( isset( $data['answer'] ) && is_string( $data['answer'] ) ) {
                    $content = $data['answer'];
                } elseif ( isset( $data['response'] ) && is_string( $data['response'] ) ) {
                    $content = $data['response'];
                } elseif ( isset( $data['message'] ) && is_string( $data['message'] ) ) {
                    $content = $data['message'];
                } elseif ( isset( $data['output'] ) && is_string( $data['output'] ) ) {
                    $content = $data['output'];
                }
            } elseif ( is_string( $body ) ) {
                // Some APIs may return plain text.
                $content = $body;
            }

            $content = trim( (string) $content );

            if ( '' === $content ) {
                $last_error = new WP_Error(
                    'straico_api_error',
                    $this->get_request_failed_message(),
                    array( 'status' => 502 )
                );
                break;
            }

            return $content;
        }

        if ( is_wp_error( $last_error ) ) {
            return $last_error;
        }

        return new WP_Error(
            'straico_api_error',
            $this->get_request_failed_message(),
            array( 'status' => 502 )
        );
    }
}


