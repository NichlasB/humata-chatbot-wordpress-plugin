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
     * Call Straico to review a Humata answer with key rotation support.
     *
     * @since 1.0.0
     * @param array|string $api_keys      API key(s) - array for rotation, string for single key.
     * @param string       $model         Straico model ID.
     * @param string       $system_prompt Optional system prompt.
     * @param string       $user_question User's original question.
     * @param string       $humata_answer Humata's generated answer.
     * @param string       $pool_name     Optional pool name for rotation tracking.
     * @return string|WP_Error Reviewed answer or error.
     */
    public function review( $api_keys, $model, $system_prompt, $user_question, $humata_answer, $pool_name = 'straico' ) {
        $model         = trim( (string) $model );
        $system_prompt = trim( (string) $system_prompt );
        $user_question = trim( (string) $user_question );
        $humata_answer = trim( (string) $humata_answer );

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

        // Build request payload.
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

            $result = $this->make_request( $api_key, $payload );

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
            'straico_api_error',
            $this->get_request_failed_message(),
            array( 'status' => 502 )
        );
    }

    /**
     * Make the actual API request with a single key.
     *
     * @since 1.0.0
     * @param string $api_key API key.
     * @param array  $payload Request payload.
     * @return string|WP_Error Response content or error.
     */
    private function make_request( $api_key, $payload ) {
        $headers = array(
            'Authorization' => 'Bearer ' . $api_key,
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
        );

        $endpoints = array(
            trailingslashit( self::STRAICO_API_BASE ) . 'chat/completions',
            'https://api.straico.com/v0/chat/completions',
        );

        /** @see humata_chatbot_straico_endpoints filter */
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
                return new WP_Error(
                    'straico_api_error',
                    $this->get_request_failed_message(),
                    array( 'status' => $code )
                );
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

        return $last_error ? $last_error : new WP_Error(
            'straico_api_error',
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
                    '[Humata Chatbot] Straico API key rotation: pool=%s, using key %d/%d',
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
                    '[Humata Chatbot] Straico API key failover: pool=%s, key %d/%d failed with status %d, trying next key',
                    $pool_name,
                    $failed_index + 1,
                    $count,
                    $status_code
                )
            );
        }
    }
}


