<?php
/**
 * Anthropic API client (REST)
 *
 * @package Humata_Chatbot
 * @since 1.0.0
 */

defined( 'ABSPATH' ) || exit;

class Humata_Chatbot_Rest_Anthropic_Client {

    /**
     * Anthropic API base URL.
     *
     * @var string
     */
    const ANTHROPIC_API_BASE = 'https://api.anthropic.com/v1';

    private function get_request_failed_message() {
        return __( 'Your message request failed. Try again. If problem persists, please contact us.', 'humata-chatbot' );
    }

    /**
     * Call Anthropic Claude to review a Humata answer.
     *
     * @since 1.0.0
     * @param string $api_key Anthropic API key.
     * @param string $model Claude model ID.
     * @param string $system_prompt Optional system prompt.
     * @param int    $extended_thinking Whether extended thinking is enabled (0/1).
     * @param string $user_question User's original question.
     * @param string $humata_answer Humata's generated answer.
     * @return string|WP_Error Reviewed answer or error.
     */
    public function review( $api_key, $model, $system_prompt, $extended_thinking, $user_question, $humata_answer ) {
        $api_key           = trim( (string) $api_key );
        $model             = trim( (string) $model );
        $system_prompt     = trim( (string) $system_prompt );
        $extended_thinking = (int) $extended_thinking;
        $user_question     = trim( (string) $user_question );
        $humata_answer     = trim( (string) $humata_answer );

        if ( '' === $api_key || '' === $model ) {
            return new WP_Error(
                'configuration_error',
                $this->get_request_failed_message(),
                array( 'status' => 500 )
            );
        }

        $max_tokens = ( 1 === $extended_thinking ) ? 2048 : 1024;

        /**
         * Allow overriding max_tokens for the Anthropic second-stage request.
         *
         * @since 1.0.0
         * @param int    $max_tokens Default max_tokens.
         * @param string $model Model ID.
         * @param int    $extended_thinking 0/1.
         */
        $max_tokens = (int) apply_filters( 'humata_chatbot_anthropic_max_tokens', $max_tokens, $model, $extended_thinking );
        if ( $max_tokens < 1 ) {
            $max_tokens = 1024;
        }

        $payload = array(
            'model'      => $model,
            'max_tokens' => $max_tokens,
            'messages'   => array(
                array(
                    'role'    => 'user',
                    'content' => "User question:\n" . $user_question . "\n\nHumata answer:\n" . $humata_answer,
                ),
            ),
        );

        if ( '' !== $system_prompt ) {
            $payload['system'] = $system_prompt;
        }

        if ( 1 === $extended_thinking ) {
            $thinking_budget_tokens = 1024;

            /**
             * Allow overriding the extended thinking budget tokens.
             *
             * @since 1.0.0
             * @param int    $budget_tokens Default budget tokens.
             * @param string $model Model ID.
             */
            $thinking_budget_tokens = (int) apply_filters( 'humata_chatbot_anthropic_thinking_budget_tokens', $thinking_budget_tokens, $model );
            if ( $thinking_budget_tokens < 0 ) {
                $thinking_budget_tokens = 0;
            }

            $payload['thinking'] = array(
                'type'          => 'enabled',
                'budget_tokens' => $thinking_budget_tokens,
            );
        }

        /**
         * Allow overriding the full Anthropic payload.
         *
         * @since 1.0.0
         * @param array  $payload Request payload.
         * @param string $model Model ID.
         * @param int    $extended_thinking 0/1.
         */
        $payload = apply_filters( 'humata_chatbot_anthropic_payload', $payload, $model, $extended_thinking );

        $headers = array(
            'x-api-key'         => $api_key,
            'anthropic-version' => '2023-06-01',
            'Content-Type'      => 'application/json',
            'Accept'            => 'application/json',
        );

        $endpoints = array(
            trailingslashit( self::ANTHROPIC_API_BASE ) . 'messages',
        );

        /**
         * Allow overriding the Anthropic endpoints (in order) to try.
         *
         * @since 1.0.0
         * @param array $endpoints List of endpoint URLs.
         */
        $endpoints = apply_filters( 'humata_chatbot_anthropic_endpoints', $endpoints );

        if ( ! is_array( $endpoints ) || empty( $endpoints ) ) {
            $endpoints = array( trailingslashit( self::ANTHROPIC_API_BASE ) . 'messages' );
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
                    'anthropic_api_error',
                    $this->get_request_failed_message(),
                    array( 'status' => 502 )
                );
                break;
            }

            $code = wp_remote_retrieve_response_code( $response );
            $body = wp_remote_retrieve_body( $response );

            if ( defined( 'WP_DEBUG' ) && WP_DEBUG && $code >= 400 ) {
                $body_snippet = is_string( $body ) ? substr( wp_strip_all_tags( $body ), 0, 500 ) : '';
                error_log( '[Humata Chatbot] Anthropic error: endpoint=' . $endpoint . ' status=' . (int) $code . ' body=' . $body_snippet );
            }

            if ( 404 === $code || 405 === $code ) {
                $last_error = new WP_Error(
                    'anthropic_api_error',
                    $this->get_request_failed_message(),
                    array( 'status' => $code )
                );
                continue;
            }

            if ( $code >= 400 ) {
                // If extended thinking fails due to incompatible model/feature, retry once without it.
                if ( 1 === $extended_thinking && is_array( $payload ) && isset( $payload['thinking'] ) ) {
                    $retry_payload = $payload;
                    unset( $retry_payload['thinking'] );

                    $retry = wp_remote_post(
                        $endpoint,
                        array(
                            'timeout' => 60,
                            'headers' => $headers,
                            'body'    => wp_json_encode( $retry_payload ),
                        )
                    );

                    if ( ! is_wp_error( $retry ) ) {
                        $retry_code = wp_remote_retrieve_response_code( $retry );
                        $retry_body = wp_remote_retrieve_body( $retry );

                        if ( $retry_code < 400 ) {
                            $code = $retry_code;
                            $body = $retry_body;
                        }
                    }
                }

                if ( $code >= 400 ) {
                    $last_error = new WP_Error(
                        'anthropic_api_error',
                        $this->get_request_failed_message(),
                        array( 'status' => $code )
                    );
                    break;
                }
            }

            $data    = json_decode( $body, true );
            $content = '';

            if ( is_array( $data ) ) {
                if ( isset( $data['content'] ) && is_array( $data['content'] ) ) {
                    foreach ( $data['content'] as $block ) {
                        if ( is_array( $block ) ) {
                            $type = isset( $block['type'] ) ? (string) $block['type'] : '';
                            if ( 'text' === $type && isset( $block['text'] ) && is_string( $block['text'] ) ) {
                                $content .= $block['text'];
                            } elseif ( isset( $block['text'] ) && is_string( $block['text'] ) ) {
                                $content .= $block['text'];
                            }
                        } elseif ( is_string( $block ) ) {
                            $content .= $block;
                        }
                    }
                } elseif ( isset( $data['completion'] ) && is_string( $data['completion'] ) ) {
                    // Legacy response shape fallback.
                    $content = $data['completion'];
                }
            } elseif ( is_string( $body ) ) {
                $content = $body;
            }

            $content = trim( (string) $content );
            if ( '' === $content ) {
                $last_error = new WP_Error(
                    'anthropic_api_error',
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
            'anthropic_api_error',
            $this->get_request_failed_message(),
            array( 'status' => 502 )
        );
    }
}


