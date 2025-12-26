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
require_once __DIR__ . '/Rest/BotProtection.php';
require_once __DIR__ . '/Rest/HistoryBuilder.php';
require_once __DIR__ . '/Rest/SseParser.php';
require_once __DIR__ . '/Rest/KeyRotator.php';
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
     * Key rotator for API key rotation.
     *
     * @since 1.0.0
     * @var Humata_Chatbot_Rest_Key_Rotator
     */
    private $key_rotator;

    /**
     * Bot protection service.
     *
     * @since 1.0.0
     * @var Humata_Chatbot_Rest_Bot_Protection
     */
    private $bot_protection;

    /**
     * Constructor.
     *
     * @since 1.0.0
     */
    public function __construct() {
        $this->rate_limiter   = new Humata_Chatbot_Rest_Rate_Limiter();
        $this->bot_protection = new Humata_Chatbot_Rest_Bot_Protection();
        $this->history_builder    = new Humata_Chatbot_Rest_History_Builder();
        $this->sse_parser         = new Humata_Chatbot_Rest_Sse_Parser();
        $this->key_rotator        = new Humata_Chatbot_Rest_Key_Rotator();
        $this->straico_client     = new Humata_Chatbot_Rest_Straico_Client();
        $this->anthropic_client   = new Humata_Chatbot_Rest_Anthropic_Client();

        // Inject key rotator into clients.
        $this->straico_client->set_key_rotator( $this->key_rotator );
        $this->anthropic_client->set_key_rotator( $this->key_rotator );

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

        // Check bot protection (honeypot + proof-of-work)
        $bot_check = $this->bot_protection->check( $request, $ip );
        if ( is_wp_error( $bot_check ) ) {
            return $bot_check;
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
        // Apply progressive delays if enabled.
        $ip = Humata_Chatbot_Rest_Client_Ip::get_client_ip();
        $this->bot_protection->apply_progressive_delay( $ip );

        // Check search provider setting.
        $search_provider = get_option( 'humata_search_provider', 'humata' );

        if ( 'local' === $search_provider ) {
            return $this->handle_local_search_request( $request );
        }

        // Continue with Humata API flow.
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
            $straico_api_keys = get_option( 'humata_straico_api_key', array() );
            $straico_model    = get_option( 'humata_straico_model', '' );
            $system_prompt    = get_option( 'humata_straico_system_prompt', '' );

            if ( ! is_array( $straico_api_keys ) ) {
                $straico_api_keys = is_string( $straico_api_keys ) && '' !== $straico_api_keys ? array( $straico_api_keys ) : array();
            }
            if ( ! is_string( $straico_model ) ) {
                $straico_model = '';
            }
            if ( ! is_string( $system_prompt ) ) {
                $system_prompt = '';
            }

            $reviewed = $this->straico_client->review(
                $straico_api_keys,
                $straico_model,
                $system_prompt,
                $message,
                $answer,
                'straico_second_stage'
            );

            if ( is_wp_error( $reviewed ) ) {
                return $reviewed;
            }

            $answer = $reviewed;
        } elseif ( 'anthropic' === $second_llm_provider ) {
            $anthropic_api_keys        = get_option( 'humata_anthropic_api_key', array() );
            $anthropic_model           = get_option( 'humata_anthropic_model', '' );
            $anthropic_extended_thinking = (int) get_option( 'humata_anthropic_extended_thinking', 0 );
            $system_prompt             = get_option( 'humata_straico_system_prompt', '' );

            if ( ! is_array( $anthropic_api_keys ) ) {
                $anthropic_api_keys = is_string( $anthropic_api_keys ) && '' !== $anthropic_api_keys ? array( $anthropic_api_keys ) : array();
            }
            if ( ! is_string( $anthropic_model ) ) {
                $anthropic_model = '';
            }
            if ( ! is_string( $system_prompt ) ) {
                $system_prompt = '';
            }

            $reviewed = $this->anthropic_client->review(
                $anthropic_api_keys,
                $anthropic_model,
                $system_prompt,
                $anthropic_extended_thinking,
                $message,
                $answer,
                'anthropic_second_stage'
            );

            if ( is_wp_error( $reviewed ) ) {
                return $reviewed;
            }

            $answer = $reviewed;
        }

        // Generate follow-up questions if enabled.
        $followup_questions = $this->generate_followup_questions( $message, $answer );

        // Return the response
        return rest_ensure_response(
            array(
                'success'           => true,
                'answer'            => $answer,
                'conversationId'    => $conversation_id,
                'followUpQuestions' => $followup_questions,
            )
        );
    }

    /**
     * Handle local search request.
     *
     * Uses SQLite FTS5 for document retrieval and sends context to LLM.
     * Supports two-stage LLM processing with independent provider configurations.
     *
     * @since 1.0.0
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error Response object or error.
     */
    private function handle_local_search_request( $request ) {
        // Load local search classes.
        require_once __DIR__ . '/Rest/SearchDatabase.php';
        require_once __DIR__ . '/Rest/SearchEngine.php';
        require_once __DIR__ . '/Rest/QueryExpander.php';
        require_once __DIR__ . '/Rest/DefinitionAnswerabilityGate.php';

        $db = new Humata_Chatbot_Rest_Search_Database();

        if ( ! $db->is_available() ) {
            return new WP_Error(
                'sqlite_unavailable',
                $this->get_request_failed_message(),
                array( 'status' => 500 )
            );
        }

        // Get message from request.
        $message = (string) $request->get_param( 'message' );

        // Validate message length.
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

        // Get first-stage LLM provider config for query reformulation.
        $first_llm_provider = get_option( 'humata_local_first_llm_provider', 'straico' );
        if ( ! is_string( $first_llm_provider ) ) {
            $first_llm_provider = 'straico';
        }
        if ( ! in_array( $first_llm_provider, array( 'straico', 'anthropic' ), true ) ) {
            $first_llm_provider = 'straico';
        }

        // Prepare LLM client and config for query reformulation.
        $llm_client = null;
        $llm_config = array();

        if ( 'anthropic' === $first_llm_provider ) {
            $anthropic_api_keys = get_option( 'humata_local_first_anthropic_api_key', array() );
            $anthropic_model    = get_option( 'humata_local_first_anthropic_model', '' );
            $extended_thinking  = (int) get_option( 'humata_local_first_anthropic_extended_thinking', 0 );

            if ( ! is_array( $anthropic_api_keys ) ) {
                $anthropic_api_keys = is_string( $anthropic_api_keys ) && '' !== $anthropic_api_keys ? array( $anthropic_api_keys ) : array();
            }

            if ( ! empty( $anthropic_api_keys ) && ! empty( $anthropic_model ) ) {
                $llm_client = $this->anthropic_client;
                $llm_config = array(
                    'provider'          => 'anthropic',
                    'api_keys'          => $anthropic_api_keys,
                    'model'             => $anthropic_model,
                    'extended_thinking' => $extended_thinking,
                );
            }
        } else {
            $straico_api_keys = get_option( 'humata_local_first_straico_api_key', array() );
            $straico_model    = get_option( 'humata_local_first_straico_model', '' );

            if ( ! is_array( $straico_api_keys ) ) {
                $straico_api_keys = is_string( $straico_api_keys ) && '' !== $straico_api_keys ? array( $straico_api_keys ) : array();
            }

            if ( ! empty( $straico_api_keys ) && ! empty( $straico_model ) ) {
                $llm_client = $this->straico_client;
                $llm_config = array(
                    'provider' => 'straico',
                    'api_keys' => $straico_api_keys,
                    'model'    => $straico_model,
                );
            }
        }

        // Expand query with conversation history for better contextual search.
        // Uses LLM reformulation for follow-up questions when available.
        $history = $request->get_param( 'history' );
        $query_expander = new Humata_Chatbot_Rest_Query_Expander();
        $search_query = $query_expander->expand( $message, $history, $llm_client, $llm_config );

        // Search for relevant context using expanded query.
        $engine  = new Humata_Chatbot_Rest_Search_Engine( $db );
        $context = $engine->search_with_context( $search_query, 5 );

        if ( is_wp_error( $context ) ) {
            error_log( '[Humata Chatbot] Local search error: ' . $context->get_error_message() );
            return new WP_Error(
                'search_error',
                $this->get_request_failed_message(),
                array( 'status' => 500 )
            );
        }

        $definition_gate   = new Humata_Chatbot_Rest_Definition_Answerability_Gate();
        $definition_filter = $definition_gate->filter_context( $message, $context, 5 );
        if ( ! empty( $definition_filter['is_definition'] ) ) {
            $context = isset( $definition_filter['context'] ) ? (string) $definition_filter['context'] : '';
        }

        if ( empty( trim( $context ) ) ) {
            return new WP_Error(
                'no_local_results',
                __( 'Sorry. Your question is outside the scope of my documentation material.', 'humata-chatbot' ),
                array( 'status' => 404 )
            );
        }

        // Build RAG prompt for first-stage LLM.
        $system_prompt = get_option( 'humata_local_search_system_prompt', '' );
        if ( ! is_string( $system_prompt ) ) {
            $system_prompt = '';
        }
        $system_prompt = trim( sanitize_textarea_field( $system_prompt ) );

        $rag_prompt = "You are a helpful assistant that ONLY answers questions using the reference materials provided below.\n\n" .
            "CRITICAL RULES:\n" .
            "1. If the question is NOT covered by the reference materials, respond with: \"Sorry, your question is outside the scope of my knowledge base.\"\n" .
            "2. Do NOT use any outside knowledge - only the provided materials.\n" .
            "3. If the reference materials seem unrelated to the question, that means you cannot answer it.\n\n" .
            "=== REFERENCE MATERIALS ===\n" . $context . "\n=== END REFERENCE MATERIALS ===";

        $full_prompt = '';
        if ( ! empty( $system_prompt ) ) {
            $full_prompt .= $system_prompt . "\n\n";
        }
        $full_prompt .= $rag_prompt . "\n\nUser Question: " . $message;

        // =====================================================
        // FIRST-STAGE LLM (Local Search Mode)
        // =====================================================
        // Reuses $first_llm_provider and config from query reformulation setup above.

        // Validate configuration before calling LLM.
        if ( null === $llm_client || empty( $llm_config ) ) {
            return new WP_Error(
                'configuration_error',
                __( 'The chatbot is not properly configured. Please contact the site administrator.', 'humata-chatbot' ),
                array( 'status' => 500 )
            );
        }

        // Call the first-stage LLM.
        if ( 'anthropic' === $first_llm_provider ) {
            $first_stage_answer = $this->anthropic_client->review(
                $llm_config['api_keys'],
                $llm_config['model'],
                '',
                $llm_config['extended_thinking'],
                $full_prompt,
                '',
                'local_first_anthropic'
            );
        } else {
            $first_stage_answer = $this->straico_client->review(
                $llm_config['api_keys'],
                $llm_config['model'],
                '',
                $full_prompt,
                '',
                'local_first_straico'
            );
        }

        if ( is_wp_error( $first_stage_answer ) ) {
            return $first_stage_answer;
        }

        $answer = $first_stage_answer;

        // =====================================================
        // SECOND-STAGE LLM (Local Search Mode) - Optional
        // =====================================================
        $second_llm_provider = get_option( 'humata_local_second_llm_provider', 'none' );
        if ( ! is_string( $second_llm_provider ) ) {
            $second_llm_provider = 'none';
        }
        if ( ! in_array( $second_llm_provider, array( 'none', 'straico', 'anthropic' ), true ) ) {
            $second_llm_provider = 'none';
        }

        if ( 'none' !== $second_llm_provider ) {
            $second_stage_system_prompt = get_option( 'humata_local_second_stage_system_prompt', '' );
            if ( ! is_string( $second_stage_system_prompt ) ) {
                $second_stage_system_prompt = '';
            }
            $second_stage_system_prompt = trim( sanitize_textarea_field( $second_stage_system_prompt ) );

            if ( 'straico' === $second_llm_provider ) {
                $straico_api_keys = get_option( 'humata_local_second_straico_api_key', array() );
                $straico_model    = get_option( 'humata_local_second_straico_model', '' );

                if ( ! is_array( $straico_api_keys ) ) {
                    $straico_api_keys = is_string( $straico_api_keys ) && '' !== $straico_api_keys ? array( $straico_api_keys ) : array();
                }

                if ( ! empty( $straico_api_keys ) && ! empty( $straico_model ) ) {
                    $reviewed = $this->straico_client->review(
                        $straico_api_keys,
                        $straico_model,
                        $second_stage_system_prompt,
                        $message,
                        $first_stage_answer,
                        'local_second_straico'
                    );

                    if ( ! is_wp_error( $reviewed ) ) {
                        $answer = $reviewed;
                    }
                }
            } elseif ( 'anthropic' === $second_llm_provider ) {
                $anthropic_api_keys = get_option( 'humata_local_second_anthropic_api_key', array() );
                $anthropic_model    = get_option( 'humata_local_second_anthropic_model', '' );
                $extended_thinking  = (int) get_option( 'humata_local_second_anthropic_extended_thinking', 0 );

                if ( ! is_array( $anthropic_api_keys ) ) {
                    $anthropic_api_keys = is_string( $anthropic_api_keys ) && '' !== $anthropic_api_keys ? array( $anthropic_api_keys ) : array();
                }

                if ( ! empty( $anthropic_api_keys ) && ! empty( $anthropic_model ) ) {
                    $reviewed = $this->anthropic_client->review(
                        $anthropic_api_keys,
                        $anthropic_model,
                        $second_stage_system_prompt,
                        $extended_thinking,
                        $message,
                        $first_stage_answer,
                        'local_second_anthropic'
                    );

                    if ( ! is_wp_error( $reviewed ) ) {
                        $answer = $reviewed;
                    }
                }
            }
        }

        // Generate follow-up questions if enabled.
        $followup_questions = $this->generate_followup_questions( $message, $answer );

        return rest_ensure_response(
            array(
                'success'           => true,
                'answer'            => $answer,
                'conversationId'    => 'local-' . wp_generate_uuid4(),
                'followUpQuestions' => $followup_questions,
            )
        );
    }

    /**
     * Generate follow-up questions using LLM.
     *
     * @since 1.0.0
     * @param string $user_question The user's original question.
     * @param string $bot_answer    The bot's response.
     * @return array Array of follow-up question strings (max 4).
     */
    private function generate_followup_questions( $user_question, $bot_answer ) {
        $settings = get_option( 'humata_followup_questions', array() );
        if ( ! is_array( $settings ) ) {
            $settings = array();
        }

        // Check if enabled.
        if ( empty( $settings['enabled'] ) ) {
            return array();
        }

        $provider            = isset( $settings['provider'] ) ? $settings['provider'] : 'straico';
        $max_question_length = isset( $settings['max_question_length'] ) ? absint( $settings['max_question_length'] ) : 80;
        $topic_scope         = isset( $settings['topic_scope'] ) ? trim( $settings['topic_scope'] ) : '';
        $custom_instructions = isset( $settings['custom_instructions'] ) ? trim( $settings['custom_instructions'] ) : '';

        if ( $max_question_length < 30 ) {
            $max_question_length = 30;
        }
        if ( $max_question_length > 150 ) {
            $max_question_length = 150;
        }

        // Build the prompt for generating follow-up questions.
        $prompt = "Based on the following conversation, generate exactly 4 brief follow-up questions that the user might want to ask next.\n\n";

        // Add topic scope guidance at the top if provided.
        if ( '' !== $topic_scope ) {
            $prompt .= "TOPIC GUIDANCE:\n" .
                $topic_scope . "\n\n";
        }

        $prompt .= "User Question: " . $user_question . "\n\n" .
            "Assistant Answer: " . mb_substr( $bot_answer, 0, 1500 ) . "\n\n" .
            "Requirements:\n" .
            "- Generate exactly 4 questions\n" .
            "- Each question must be " . $max_question_length . " characters or less\n" .
            "- Questions should be relevant follow-ups to the conversation\n" .
            "- Questions should be diverse and explore different aspects\n";

        $prompt .= "- Output ONLY the questions, one per line, numbered 1-4\n" .
            "- Do not include any other text or explanation\n";

        // Add custom instructions if provided.
        if ( '' !== $custom_instructions ) {
            $prompt .= "\nAdditional Instructions:\n" . $custom_instructions . "\n";
        }

        $prompt .= "\nOutput format:\n" .
            "1. [question]\n" .
            "2. [question]\n" .
            "3. [question]\n" .
            "4. [question]";

        $result = null;

        if ( 'anthropic' === $provider ) {
            $api_keys = isset( $settings['anthropic_api_keys'] ) ? $settings['anthropic_api_keys'] : array();
            $model    = isset( $settings['anthropic_model'] ) ? $settings['anthropic_model'] : 'claude-3-5-sonnet-20241022';
            $extended_thinking = ! empty( $settings['anthropic_extended_thinking'] ) ? 1 : 0;

            if ( ! is_array( $api_keys ) ) {
                $api_keys = is_string( $api_keys ) && '' !== $api_keys ? array( $api_keys ) : array();
            }

            if ( empty( $api_keys ) || empty( $model ) ) {
                return array();
            }

            $result = $this->anthropic_client->review(
                $api_keys,
                $model,
                '',
                $extended_thinking,
                $prompt,
                '',
                'followup_anthropic'
            );
        } else {
            // Straico (default).
            $api_keys = isset( $settings['straico_api_keys'] ) ? $settings['straico_api_keys'] : array();
            $model    = isset( $settings['straico_model'] ) ? $settings['straico_model'] : '';

            if ( ! is_array( $api_keys ) ) {
                $api_keys = is_string( $api_keys ) && '' !== $api_keys ? array( $api_keys ) : array();
            }

            if ( empty( $api_keys ) || empty( $model ) ) {
                return array();
            }

            $result = $this->straico_client->review(
                $api_keys,
                $model,
                '',
                $prompt,
                '',
                'followup_straico'
            );
        }

        if ( is_wp_error( $result ) ) {
            error_log( '[Humata Chatbot] Follow-up questions generation failed: ' . $result->get_error_message() );
            return array();
        }

        // Parse the response to extract questions.
        return $this->parse_followup_questions( $result, $max_question_length );
    }

    /**
     * Parse LLM response to extract follow-up questions.
     *
     * @since 1.0.0
     * @param string $response           LLM response text.
     * @param int    $max_question_length Maximum allowed question length.
     * @return array Array of question strings (max 4).
     */
    private function parse_followup_questions( $response, $max_question_length ) {
        $questions = array();

        if ( empty( $response ) || ! is_string( $response ) ) {
            return $questions;
        }

        // Split by newlines and look for numbered items.
        $lines = preg_split( '/\r?\n/', trim( $response ) );

        foreach ( $lines as $line ) {
            $line = trim( $line );

            if ( '' === $line ) {
                continue;
            }

            // Remove common prefixes: "1.", "1)", "1:", "- ", "* ", etc.
            $cleaned = preg_replace( '/^[\d]+[\.\)\:]\s*/', '', $line );
            $cleaned = preg_replace( '/^[\-\*]\s*/', '', $cleaned );
            $cleaned = trim( $cleaned );

            if ( '' === $cleaned ) {
                continue;
            }

            // Skip if too long (let CSS handle display truncation, but filter out extremely long responses).
            // The max_question_length is used as a soft guide - questions over 2x the limit are likely LLM errors.
            if ( mb_strlen( $cleaned ) > $max_question_length * 2 ) {
                continue;
            }

            // Ensure it ends with a question mark if it looks like a question.
            if ( ! preg_match( '/[?!.]$/', $cleaned ) ) {
                $cleaned .= '?';
            }

            $questions[] = $cleaned;

            // Max 4 questions.
            if ( count( $questions ) >= 4 ) {
                break;
            }
        }

        return $questions;
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
