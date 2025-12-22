<?php
/**
 * REST API Handler Class
 *
 * Handles the WordPress REST API endpoints for the chat functionality.
 *
 * @package Humata_Chatbot
 * @since 1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Humata_Chatbot_REST_API
 *
 * @since 1.0.0
 */
class Humata_Chatbot_REST_API {

    /**
     * API namespace.
     *
     * @var string
     */
    const API_NAMESPACE = 'humata-chat/v1';

    /**
     * Humata API base URL.
     *
     * @var string
     */
    const HUMATA_API_BASE = 'https://app.humata.ai/api/v1';

    /**
     * Straico API base URL.
     *
     * @var string
     */
    const STRAICO_API_BASE = 'https://api.straico.com/v2';

    /**
     * Anthropic API base URL.
     *
     * @var string
     */
    const ANTHROPIC_API_BASE = 'https://api.anthropic.com/v1';

    /**
     * Rate limit transient prefix.
     *
     * @var string
     */
    const RATE_LIMIT_PREFIX = 'humata_rate_limit_';

    /**
     * Conversation cache transient prefix.
     *
     * @var string
     */
    const CONVERSATION_PREFIX = 'humata_conversation_';

    /**
     * Turnstile verification transient prefix.
     *
     * @var string
     */
    const TURNSTILE_VERIFIED_PREFIX = 'humata_turnstile_verified_';

    /**
     * Cloudflare Turnstile siteverify endpoint.
     *
     * @var string
     */
    const TURNSTILE_VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    /**
     * Constructor.
     *
     * @since 1.0.0
     */
    public function __construct() {
        add_action( 'rest_api_init', array( $this, 'register_routes' ) );
        add_filter( 'rest_authentication_errors', array( $this, 'maybe_bypass_cookie_check' ), 101 );
    }

    private function get_request_failed_message() {
        return __( 'Your message request failed. Try again. If problem persists, please contact us.', 'humata-chatbot' );
    }

    public function maybe_bypass_cookie_check( $result ) {
        if ( ! is_wp_error( $result ) ) {
            return $result;
        }

        if ( 'rest_cookie_invalid_nonce' !== $result->get_error_code() ) {
            return $result;
        }

        $request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
        if ( strpos( $request_uri, '/wp-json/' . self::API_NAMESPACE . '/' ) === false ) {
            return $result;
        }

        return null;
    }

    /**
     * Register REST API routes.
     *
     * @since 1.0.0
     * @return void
     */
    public function register_routes() {
        register_rest_route(
            self::API_NAMESPACE,
            '/ask',
            array(
                'methods'             => 'POST',
                'callback'            => array( $this, 'handle_ask_request' ),
                'permission_callback' => array( $this, 'verify_request' ),
                'args'                => array(
                    'message'        => array(
                        'required'          => true,
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                        'validate_callback' => function( $param ) {
                            return ! empty( trim( $param ) );
                        },
                    ),
                    'conversationId' => array(
                        'required'          => false,
                        'type'              => 'string',
                        'sanitize_callback' => 'sanitize_text_field',
                    ),
                    'history'        => array(
                        'required'          => false,
                        'type'              => 'array',
                        'sanitize_callback' => function( $param ) {
                            return is_array( $param ) ? $param : array();
                        },
                    ),
                ),
            )
        );

        register_rest_route(
            self::API_NAMESPACE,
            '/clear-history',
            array(
                'methods'             => 'POST',
                'callback'            => array( $this, 'handle_clear_history' ),
                'permission_callback' => array( $this, 'verify_request' ),
            )
        );
    }

    /**
     * Verify request permissions.
     *
     * @since 1.0.0
     * @param WP_REST_Request $request Request object.
     * @return bool|WP_Error True if valid, WP_Error otherwise.
     */
    public function verify_request( $request ) {
        // Verify nonce
        $humata_nonce = $request->get_header( 'X-Humata-Nonce' );
        $wp_nonce     = $request->get_header( 'X-WP-Nonce' );

        if ( ! wp_verify_nonce( $humata_nonce, 'humata_chat' ) && ! wp_verify_nonce( $wp_nonce, 'wp_rest' ) ) {
            return new WP_Error(
                'rest_forbidden',
                __( 'Invalid security token. Please refresh the page and try again.', 'humata-chatbot' ),
                array( 'status' => 403 )
            );
        }

        // Check rate limits
        $rate_check = $this->check_rate_limit();
        if ( is_wp_error( $rate_check ) ) {
            return $rate_check;
        }

        // Check Turnstile verification
        $turnstile_check = $this->check_turnstile( $request );
        if ( is_wp_error( $turnstile_check ) ) {
            return $turnstile_check;
        }

        return true;
    }

    /**
     * Check Cloudflare Turnstile verification.
     *
     * @since 1.0.0
     * @param WP_REST_Request $request Request object.
     * @return bool|WP_Error True if verified or not required, WP_Error if verification failed.
     */
    private function check_turnstile( $request ) {
        // Check if Turnstile is enabled.
        $enabled = (int) get_option( 'humata_turnstile_enabled', 0 );
        if ( 1 !== $enabled ) {
            return true;
        }

        $secret_key = get_option( 'humata_turnstile_secret_key', '' );
        if ( empty( $secret_key ) ) {
            // If enabled but no secret key configured, skip verification.
            return true;
        }

        $ip        = $this->get_client_ip();
        $transient = self::TURNSTILE_VERIFIED_PREFIX . md5( $ip );

        // Check if already verified in this session.
        $verified = get_transient( $transient );
        if ( false !== $verified ) {
            return true;
        }

        // Get Turnstile token from request header.
        $token = $request->get_header( 'X-Turnstile-Token' );
        if ( empty( $token ) ) {
            return new WP_Error(
                'turnstile_required',
                __( 'Human verification required. Please complete the verification challenge.', 'humata-chatbot' ),
                array( 'status' => 403 )
            );
        }

        // Verify token with Cloudflare.
        $verify_result = $this->verify_turnstile_token( $token, $secret_key, $ip );
        if ( is_wp_error( $verify_result ) ) {
            return $verify_result;
        }

        // Mark as verified for 1 hour.
        set_transient( $transient, 1, HOUR_IN_SECONDS );

        return true;
    }

    /**
     * Verify Turnstile token with Cloudflare API.
     *
     * @since 1.0.0
     * @param string $token      The Turnstile response token.
     * @param string $secret_key The Turnstile secret key.
     * @param string $ip         The client IP address.
     * @return bool|WP_Error True if valid, WP_Error if invalid.
     */
    private function verify_turnstile_token( $token, $secret_key, $ip ) {
        $response = wp_remote_post(
            self::TURNSTILE_VERIFY_URL,
            array(
                'timeout' => 10,
                'body'    => array(
                    'secret'   => $secret_key,
                    'response' => $token,
                    'remoteip' => $ip,
                ),
            )
        );

        if ( is_wp_error( $response ) ) {
            // Log error but allow request to proceed to avoid blocking users.
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( '[Humata Chatbot] Turnstile verification failed: ' . $response->get_error_message() );
            }
            return new WP_Error(
                'turnstile_error',
                __( 'Verification service unavailable. Please try again.', 'humata-chatbot' ),
                array( 'status' => 503 )
            );
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( ! is_array( $data ) || empty( $data['success'] ) ) {
            $error_codes = isset( $data['error-codes'] ) ? implode( ', ', $data['error-codes'] ) : 'unknown';
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( '[Humata Chatbot] Turnstile verification rejected: ' . $error_codes );
            }
            return new WP_Error(
                'turnstile_failed',
                __( 'Human verification failed. Please try again.', 'humata-chatbot' ),
                array( 'status' => 403 )
            );
        }

        return true;
    }

    /**
     * Check rate limit for current IP.
     *
     * @since 1.0.0
     * @return bool|WP_Error True if within limit, WP_Error if exceeded.
     */
    private function check_rate_limit() {
        $ip         = $this->get_client_ip();
        $transient  = self::RATE_LIMIT_PREFIX . md5( $ip );
        $limit      = absint( get_option( 'humata_rate_limit', 50 ) );
        $count      = get_transient( $transient );

        if ( false === $count ) {
            set_transient( $transient, 1, HOUR_IN_SECONDS );
            return true;
        }

        if ( $count >= $limit ) {
            return new WP_Error(
                'rate_limit_exceeded',
                __( 'Too many requests. Please wait a while before trying again.', 'humata-chatbot' ),
                array( 'status' => 429 )
            );
        }

        set_transient( $transient, $count + 1, HOUR_IN_SECONDS );
        return true;
    }

    /**
     * Get client IP address.
     *
     * @since 1.0.0
     * @return string Client IP address.
     */
    private function get_client_ip() {
        $ip = '';

        if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
            $ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CLIENT_IP'] ) );
        } elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
            $ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
            // Get the first IP if multiple are provided
            $ip = explode( ',', $ip )[0];
        } elseif ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
            $ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
        }

        return trim( $ip );
    }

    /**
     * Handle the ask request.
     *
     * @since 1.0.0
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error Response object or error.
     */
    public function handle_ask_request( $request ) {
        // Get API credentials
        $api_key      = get_option( 'humata_api_key', '' );
        $document_ids = get_option( 'humata_document_ids', '' );

        if ( empty( $api_key ) || empty( $document_ids ) ) {
            return new WP_Error(
                'configuration_error',
                __( 'The chatbot is not properly configured. Please contact the site administrator.', 'humata-chatbot' ),
                array( 'status' => 500 )
            );
        }

        // Parse document IDs
        $doc_ids_array = array_filter( array_map( 'trim', explode( ',', $document_ids ) ) );
        if ( empty( $doc_ids_array ) ) {
            return new WP_Error(
                'configuration_error',
                __( 'No document IDs configured. Please add document IDs in the settings.', 'humata-chatbot' ),
                array( 'status' => 500 )
            );
        }

        // Get request parameters
        $message = (string) $request->get_param( 'message' );

        $max_prompt_chars = absint( get_option( 'humata_max_prompt_chars', 3000 ) );
        if ( $max_prompt_chars <= 0 ) {
            $max_prompt_chars = 3000;
        }
        if ( $max_prompt_chars > 100000 ) {
            $max_prompt_chars = 100000;
        }

        $message_len = function_exists( 'wp_strlen' )
            ? wp_strlen( $message )
            : ( function_exists( 'mb_strlen' ) ? mb_strlen( $message, '8bit' ) : strlen( $message ) );

        if ( $message_len > $max_prompt_chars ) {
            return new WP_Error(
                'prompt_too_long',
                sprintf( __( 'Message is too long. Maximum is %d characters.', 'humata-chatbot' ), $max_prompt_chars ),
                array( 'status' => 413 )
            );
        }

        $history         = $request->get_param( 'history' );
        $history_context = $this->build_chat_history_context( $history );

        $system_prompt = get_option( 'humata_system_prompt', '' );
        if ( ! is_string( $system_prompt ) ) {
            $system_prompt = '';
        }
        $system_prompt = trim( sanitize_textarea_field( $system_prompt ) );

        $question_parts = array();
        if ( '' !== $system_prompt ) {
            $question_parts[] = $system_prompt;
        }
        if ( '' !== $history_context ) {
            $question_parts[] = $history_context;
        }
        if ( '' !== $system_prompt || '' !== $history_context ) {
            $question_parts[] = 'User: ' . $message;
            $question = implode( "\n\n", $question_parts );
        } else {
            $question = $message;
        }

        // Step 1: Create a fresh conversation (same as working test)
        $conv_response = wp_remote_post(
            self::HUMATA_API_BASE . '/conversations',
            array(
                'timeout'     => 30,
                'headers'     => array(
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type'  => 'application/json',
                    'Accept'        => 'application/json',
                ),
                'body'        => wp_json_encode( array( 'documentIds' => $doc_ids_array ) ),
            )
        );

        if ( is_wp_error( $conv_response ) ) {
            return new WP_Error(
                'api_connection_error',
                __( 'Unable to connect to the AI service.', 'humata-chatbot' ),
                array( 'status' => 502 )
            );
        }

        $conv_code = wp_remote_retrieve_response_code( $conv_response );
        $conv_body = wp_remote_retrieve_body( $conv_response );
        $conv_data = json_decode( $conv_body, true );

        if ( $conv_code >= 400 || ! isset( $conv_data['id'] ) ) {
            return new WP_Error(
                'api_error',
                $this->get_request_failed_message(),
                array( 'status' => $conv_code )
            );
        }

        $conversation_id = $conv_data['id'];

        // Step 2: Ask the question (same as working test)
        $response = wp_remote_post(
            self::HUMATA_API_BASE . '/ask',
            array(
                'timeout'     => 60,
                'headers'     => array(
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type'  => 'application/json',
                    'Accept'        => 'text/event-stream',
                ),
                'body'        => wp_json_encode( array(
                    'conversationId' => $conversation_id,
                    'question'       => $question,
                    'model'          => 'gpt-4o',
                ) ),
            )
        );

        // Check for connection errors
        if ( is_wp_error( $response ) ) {
            return new WP_Error(
                'api_connection_error',
                __( 'Unable to connect to the AI service. Please try again later.', 'humata-chatbot' ),
                array( 'status' => 502 )
            );
        }

        // Get response code and body
        $response_code = wp_remote_retrieve_response_code( $response );
        $response_body = wp_remote_retrieve_body( $response );

        // Handle different response codes
        if ( 401 === $response_code ) {
            return new WP_Error(
                'api_auth_error',
                __( 'API authentication failed. Please check the API key configuration.', 'humata-chatbot' ),
                array( 'status' => 500 )
            );
        }

        if ( 429 === $response_code ) {
            return new WP_Error(
                'api_rate_limit',
                __( 'The AI service is currently busy. Please try again in a moment.', 'humata-chatbot' ),
                array( 'status' => 429 )
            );
        }

        // Check for error response first
        if ( $response_code >= 400 ) {
            return new WP_Error(
                'api_error',
                $this->get_request_failed_message(),
                array( 'status' => $response_code )
            );
        }

        // Parse SSE response - Humata returns server-sent events
        $answer = $this->parse_sse_response( $response_body );

        if ( empty( $answer ) ) {
            // Try parsing as JSON if SSE parsing failed
            $data = json_decode( $response_body, true );
            if ( isset( $data['message'] ) && ! isset( $data['answer'] ) ) {
                return new WP_Error(
                    'api_error',
                    $this->get_request_failed_message(),
                    array( 'status' => $response_code )
                );
            }

            // Try different response fields
            if ( isset( $data['answer'] ) ) {
                $answer = $data['answer'];
            } elseif ( isset( $data['response'] ) ) {
                $answer = $data['response'];
            } elseif ( isset( $data['message'] ) ) {
                $answer = $data['message'];
            } elseif ( isset( $data['text'] ) ) {
                $answer = $data['text'];
            }
        }

        if ( empty( $answer ) ) {
            return new WP_Error(
                'api_error',
                __( 'Received an empty response from the AI service.', 'humata-chatbot' ),
                array( 'status' => 500 )
            );
        }

        // Optional second-stage LLM processing step (second-pass response)
        $second_llm_provider = get_option( 'humata_second_llm_provider', '' );
        if ( ! is_string( $second_llm_provider ) ) {
            $second_llm_provider = '';
        }
        $second_llm_provider = trim( $second_llm_provider );

        // Back-compat: if new provider option is unset, respect the legacy Straico enabled flag.
        if ( '' === $second_llm_provider ) {
            $legacy_straico_enabled = (int) get_option( 'humata_straico_review_enabled', 0 );
            $second_llm_provider    = ( 1 === $legacy_straico_enabled ) ? 'straico' : 'none';
        }

        if ( 'straico' === $second_llm_provider ) {
            $straico_api_key = get_option( 'humata_straico_api_key', '' );
            $straico_model   = get_option( 'humata_straico_model', '' );
            $system_prompt   = get_option( 'humata_straico_system_prompt', '' );

            if ( ! is_string( $straico_api_key ) ) {
                $straico_api_key = '';
            }
            if ( ! is_string( $straico_model ) ) {
                $straico_model = '';
            }
            if ( ! is_string( $system_prompt ) ) {
                $system_prompt = '';
            }

            $reviewed = $this->call_straico_review(
                $straico_api_key,
                $straico_model,
                $system_prompt,
                $message,
                $answer
            );

            if ( is_wp_error( $reviewed ) ) {
                return $reviewed;
            }

            $answer = $reviewed;
        } elseif ( 'anthropic' === $second_llm_provider ) {
            $anthropic_api_key         = get_option( 'humata_anthropic_api_key', '' );
            $anthropic_model           = get_option( 'humata_anthropic_model', '' );
            $anthropic_extended_thinking = (int) get_option( 'humata_anthropic_extended_thinking', 0 );
            $system_prompt             = get_option( 'humata_straico_system_prompt', '' );

            if ( ! is_string( $anthropic_api_key ) ) {
                $anthropic_api_key = '';
            }
            if ( ! is_string( $anthropic_model ) ) {
                $anthropic_model = '';
            }
            if ( ! is_string( $system_prompt ) ) {
                $system_prompt = '';
            }

            $reviewed = $this->call_anthropic_review(
                $anthropic_api_key,
                $anthropic_model,
                $system_prompt,
                $anthropic_extended_thinking,
                $message,
                $answer
            );

            if ( is_wp_error( $reviewed ) ) {
                return $reviewed;
            }

            $answer = $reviewed;
        }

        // Return the response
        return rest_ensure_response(
            array(
                'success'        => true,
                'answer'         => $answer,
                'conversationId' => $conversation_id,
            )
        );
    }

    /**
     * Get or create a Humata conversation.
     *
     * @since 1.0.0
     * @param string $api_key API key.
     * @param array  $document_ids Array of document IDs.
     * @param string $cached_id Optional cached conversation ID from client.
     * @return string|WP_Error Conversation ID or error.
     */
    private function get_or_create_conversation( $api_key, $document_ids, $cached_id = '' ) {
        // Validate document IDs look like UUIDs
        foreach ( $document_ids as $doc_id ) {
            if ( ! preg_match( '/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/i', $doc_id ) ) {
                return new WP_Error(
                    'invalid_document_id',
                    sprintf(
                        /* translators: %s: invalid document ID */
                        __( 'Invalid document ID format: %s. Document IDs should be UUIDs (e.g., 63ea1432-d6aa-49d4-81ef-07cb1692f2ee). Please check your settings.', 'humata-chatbot' ),
                        $doc_id
                    ),
                    array( 'status' => 400 )
                );
            }
        }

        // Always create a fresh conversation to avoid stale session issues
        // The Humata API seems to have session-based validation

        // Create a new conversation
        $response = wp_remote_post(
            self::HUMATA_API_BASE . '/conversations',
            array(
                'timeout'     => 30,
                'httpversion' => '1.1',
                'headers'     => array(
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type'  => 'application/json',
                    'Accept'        => 'application/json',
                ),
                'body'        => wp_json_encode( array(
                    'documentIds' => $document_ids,
                ) ),
            )
        );

        if ( is_wp_error( $response ) ) {
            return new WP_Error(
                'api_connection_error',
                __( 'Unable to connect to the AI service. Please try again later.', 'humata-chatbot' ),
                array( 'status' => 502 )
            );
        }

        $response_code = wp_remote_retrieve_response_code( $response );
        $response_body = wp_remote_retrieve_body( $response );
        $data          = json_decode( $response_body, true );

        if ( 401 === $response_code ) {
            return new WP_Error(
                'api_auth_error',
                __( 'API authentication failed. Please check the API key.', 'humata-chatbot' ),
                array( 'status' => 500 )
            );
        }

        if ( $response_code >= 400 ) {
            $error_message = isset( $data['message'] ) ? $data['message'] : __( 'Failed to create conversation.', 'humata-chatbot' );
            
            // Provide more helpful error message for common issues
            if ( strpos( $error_message, 'Cookie check failed' ) !== false || strpos( $error_message, 'document' ) !== false ) {
                $error_message = __( 'Invalid document IDs. Please check your settings.', 'humata-chatbot' );
            }
            
            return new WP_Error(
                'api_error',
                $error_message,
                array( 'status' => $response_code )
            );
        }

        if ( ! isset( $data['id'] ) ) {
            return new WP_Error(
                'api_error',
                __( 'Invalid response from AI service when creating conversation.', 'humata-chatbot' ),
                array( 'status' => 500 )
            );
        }

        $conversation_id = $data['id'];

        return $conversation_id;
    }

    private function build_chat_history_context( $history ) {
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

    /**
     * Parse Server-Sent Events response from Humata.
     *
     * @since 1.0.0
     * @param string $response_body Raw response body.
     * @return string Parsed answer content.
     */
    private function parse_sse_response( $response_body ) {
        $answer = '';
        $lines  = explode( "\n", $response_body );

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
    private function call_straico_review( $straico_api_key, $model, $system_prompt, $user_question, $humata_answer ) {
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
    private function call_anthropic_review( $api_key, $model, $system_prompt, $extended_thinking, $user_question, $humata_answer ) {
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

    /**
     * Handle clear history request.
     *
     * @since 1.0.0
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response Response object.
     */
    public function handle_clear_history( $request ) {
        return rest_ensure_response(
            array(
                'success' => true,
                'message' => __( 'Chat history cleared.', 'humata-chatbot' ),
            )
        );
    }
}
