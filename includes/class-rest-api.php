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

require_once __DIR__ . '/Rest/ClientIp.php';
require_once __DIR__ . '/Rest/RateLimiter.php';
require_once __DIR__ . '/Rest/TurnstileVerifier.php';
require_once __DIR__ . '/Rest/HistoryBuilder.php';
require_once __DIR__ . '/Rest/SseParser.php';
require_once __DIR__ . '/Rest/Clients/StraicoClient.php';
require_once __DIR__ . '/Rest/Clients/AnthropicClient.php';

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
     * Rate limiter.
     *
     * @since 1.0.0
     * @var Humata_Chatbot_Rest_Rate_Limiter
     */
    private $rate_limiter;

    /**
     * Turnstile verifier.
     *
     * @since 1.0.0
     * @var Humata_Chatbot_Rest_Turnstile_Verifier
     */
    private $turnstile_verifier;

    /**
     * History context builder.
     *
     * @since 1.0.0
     * @var Humata_Chatbot_Rest_History_Builder
     */
    private $history_builder;

    /**
     * SSE parser for Humata responses.
     *
     * @since 1.0.0
     * @var Humata_Chatbot_Rest_Sse_Parser
     */
    private $sse_parser;

    /**
     * Straico client (second-stage review).
     *
     * @since 1.0.0
     * @var Humata_Chatbot_Rest_Straico_Client
     */
    private $straico_client;

    /**
     * Anthropic client (second-stage review).
     *
     * @since 1.0.0
     * @var Humata_Chatbot_Rest_Anthropic_Client
     */
    private $anthropic_client;

    /**
     * Constructor.
     *
     * @since 1.0.0
     */
    public function __construct() {
        $this->rate_limiter       = new Humata_Chatbot_Rest_Rate_Limiter();
        $this->turnstile_verifier = new Humata_Chatbot_Rest_Turnstile_Verifier();
        $this->history_builder    = new Humata_Chatbot_Rest_History_Builder();
        $this->sse_parser         = new Humata_Chatbot_Rest_Sse_Parser();
        $this->straico_client     = new Humata_Chatbot_Rest_Straico_Client();
        $this->anthropic_client   = new Humata_Chatbot_Rest_Anthropic_Client();

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

        $ip = Humata_Chatbot_Rest_Client_Ip::get_client_ip();

        // Check rate limits
        $rate_check = $this->rate_limiter->check( $ip );
        if ( is_wp_error( $rate_check ) ) {
            return $rate_check;
        }

        // Check Turnstile verification
        $turnstile_check = $this->turnstile_verifier->check( $request, $ip );
        if ( is_wp_error( $turnstile_check ) ) {
            return $turnstile_check;
        }

        return true;
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
        $history_context = $this->history_builder->build_context( $history );

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
        $answer = $this->sse_parser->parse( $response_body );

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

            $reviewed = $this->straico_client->review(
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

            $reviewed = $this->anthropic_client->review(
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
