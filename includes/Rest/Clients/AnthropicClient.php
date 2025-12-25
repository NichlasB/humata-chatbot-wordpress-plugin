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

    /**
     * HTTP status codes that trigger key failover.
     *
     * @var array
     */
    const FAILOVER_STATUS_CODES = array( 401, 403, 429 );

    /**
     * Key rotator instance.
     *
     * @var Humata_Chatbot_Rest_Key_Rotator|null
     */
    private $key_rotator = null;

    /**
     * Set the key rotator instance.
     *
     * @since 1.0.0
     * @param Humata_Chatbot_Rest_Key_Rotator $rotator Key rotator instance.
     * @return void
     */
    public function set_key_rotator( $rotator ) {
        $this->key_rotator = $rotator;
    }

    private function get_request_failed_message() {
        return __( 'Your message request failed. Try again. If problem persists, please contact us.', 'humata-chatbot' );
    }

    /**
     * Call Anthropic Claude to review a Humata answer with key rotation support.
     *
     * @since 1.0.0
     * @param array|string $api_keys          API key(s) - array for rotation, string for single key.
     * @param string       $model             Claude model ID.
     * @param string       $system_prompt     Optional system prompt.
     * @param int          $extended_thinking Whether extended thinking is enabled (0/1).
     * @param string       $user_question     User's original question.
     * @param string       $humata_answer     Humata's generated answer.
     * @param string       $pool_name         Optional pool name for rotation tracking.
     * @return string|WP_Error Reviewed answer or error.
     */
    public function review( $api_keys, $model, $system_prompt, $extended_thinking, $user_question, $humata_answer, $pool_name = 'anthropic' ) {
        $model             = trim( (string) $model );
        $system_prompt     = trim( (string) $system_prompt );
        $extended_thinking = (int) $extended_thinking;
        $user_question     = trim( (string) $user_question );
        $humata_answer     = trim( (string) $humata_answer );

        // Normalize keys to array.
        if ( is_string( $api_keys ) ) {
            $api_keys = '' !== trim( $api_keys ) ? array( trim( $api_keys ) ) : array();
        }
        if ( ! is_array( $api_keys ) ) {
            $api_keys = array();
        }
        $api_keys = array_values( array_filter( $api_keys, function( $k ) {
            return is_string( $k ) && '' !== trim( $k );
        } ) );

        if ( empty( $api_keys ) || '' === $model ) {
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

        // Build payload.
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

        // Get starting index for rotation.
        $key_count   = count( $api_keys );
        $start_index = 0;
        if ( $this->key_rotator && $key_count > 1 ) {
            $start_index = $this->key_rotator->get_current_index( $pool_name ) % $key_count;
        }

        // Try keys with failover.
        $last_error = null;
        for ( $i = 0; $i < $key_count; $i++ ) {
            $key_index = ( $start_index + $i ) % $key_count;
            $api_key   = trim( $api_keys[ $key_index ] );

            if ( $key_count > 1 ) {
                $this->log_key_usage( $pool_name, $key_index, $key_count );
            }

            $result = $this->make_request( $api_key, $payload, $extended_thinking );

            // Check if we should failover to next key.
            if ( is_wp_error( $result ) ) {
                $error_data = $result->get_error_data();
                $status     = isset( $error_data['status'] ) ? (int) $error_data['status'] : 0;

                if ( in_array( $status, self::FAILOVER_STATUS_CODES, true ) && $i < $key_count - 1 ) {
                    $this->log_failover( $pool_name, $key_index, $status, $key_count );
                    $last_error = $result;
                    continue;
                }

                return $result;
            }

            // Success - increment rotation index for next request.
            if ( $this->key_rotator && $key_count > 1 ) {
                $this->key_rotator->increment_index( $pool_name, $key_count );
            }

            return $result;
        }

        return $last_error ? $last_error : new WP_Error(
            'anthropic_api_error',
            $this->get_request_failed_message(),
            array( 'status' => 502 )
        );
    }

    /**
     * Make the actual API request with a single key.
     *
     * @since 1.0.0
     * @param string $api_key           API key.
     * @param array  $payload           Request payload.
     * @param int    $extended_thinking Whether extended thinking is enabled.
     * @return string|WP_Error Response content or error.
     */
    private function make_request( $api_key, $payload, $extended_thinking = 0 ) {
        $headers = array(
            'x-api-key'         => $api_key,
            'anthropic-version' => '2023-06-01',
            'Content-Type'      => 'application/json',
            'Accept'            => 'application/json',
        );

        $endpoints = array(
            trailingslashit( self::ANTHROPIC_API_BASE ) . 'messages',
        );

        /** @see humata_chatbot_anthropic_endpoints filter */
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
                    return new WP_Error(
                        'anthropic_api_error',
                        $this->get_request_failed_message(),
                        array( 'status' => $code )
                    );
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

        return $last_error ? $last_error : new WP_Error(
            'anthropic_api_error',
            $this->get_request_failed_message(),
            array( 'status' => 502 )
        );
    }

    /**
     * Log key usage for debugging.
     *
     * @since 1.0.0
     * @param string $pool_name Pool identifier.
     * @param int    $index     Key index used.
     * @param int    $count     Total keys in pool.
     * @return void
     */
    private function log_key_usage( $pool_name, $index, $count ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log(
                sprintf(
                    '[Humata Chatbot] Anthropic API key rotation: pool=%s, using key %d/%d',
                    $pool_name,
                    $index + 1,
                    $count
                )
            );
        }
    }

    /**
     * Log failover attempt for debugging.
     *
     * @since 1.0.0
     * @param string $pool_name    Pool identifier.
     * @param int    $failed_index Index of the failed key.
     * @param int    $status_code  HTTP status code that caused failover.
     * @param int    $count        Total keys in pool.
     * @return void
     */
    private function log_failover( $pool_name, $failed_index, $status_code, $count ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log(
                sprintf(
                    '[Humata Chatbot] Anthropic API key failover: pool=%s, key %d/%d failed with status %d, trying next key',
                    $pool_name,
                    $failed_index + 1,
                    $count,
                    $status_code
                )
            );
        }
    }
}


