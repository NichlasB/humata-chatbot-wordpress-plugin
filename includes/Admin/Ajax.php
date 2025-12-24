<?php
/**
 * Admin AJAX Handlers
 *
 * @package Humata_Chatbot
 * @since 1.0.0
 */

defined( 'ABSPATH' ) || exit;

trait Humata_Chatbot_Admin_Ajax_Trait {

    public function ajax_fetch_titles() {
        if ( ! check_ajax_referer( 'humata_admin_nonce', 'nonce', false ) ) {
            wp_send_json_error( array( 'message' => __( 'Security check failed.', 'humata-chatbot' ) ) );
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'humata-chatbot' ) ) );
        }

        $api_key = get_option( 'humata_api_key', '' );
        $document_ids = isset( $_POST['document_ids'] ) ? sanitize_text_field( wp_unslash( $_POST['document_ids'] ) ) : get_option( 'humata_document_ids', '' );

        if ( empty( $api_key ) ) {
            wp_send_json_error( array( 'message' => __( 'API key not configured.', 'humata-chatbot' ) ) );
        }

        if ( empty( $document_ids ) ) {
            wp_send_json_error( array( 'message' => __( 'Document IDs not configured.', 'humata-chatbot' ) ) );
        }

        $doc_ids_array = $this->parse_document_ids( $document_ids );
        $total         = count( $doc_ids_array );

        if ( 0 === $total ) {
            wp_send_json_error( array( 'message' => __( 'No valid document IDs found.', 'humata-chatbot' ) ) );
        }

        $offset = isset( $_POST['offset'] ) ? absint( $_POST['offset'] ) : 0;
        $limit  = isset( $_POST['limit'] ) ? absint( $_POST['limit'] ) : 20;
        $limit  = max( 1, min( 50, $limit ) );

        $batch = array_slice( $doc_ids_array, $offset, $limit );

        $titles = get_option( 'humata_document_titles', array() );
        if ( ! is_array( $titles ) ) {
            $titles = array();
        }

        $fetched = 0;
        $errors  = 0;
        $updated_titles = array();
        $first_error_message = '';

        foreach ( $batch as $doc_id ) {
            if ( isset( $titles[ $doc_id ] ) && '' !== $titles[ $doc_id ] ) {
                continue;
            }

            $response = wp_remote_get(
                self::HUMATA_API_BASE . '/pdf/' . $doc_id,
                array(
                    'timeout' => 30,
                    'httpversion' => '1.1',
                    'headers' => array(
                        'Authorization' => 'Bearer ' . $api_key,
                        'Accept'        => '*/*',
                    ),
                )
            );

            if ( is_wp_error( $response ) ) {
                if ( '' === $first_error_message ) {
                    $first_error_message = $response->get_error_message();
                }
                $errors++;
                continue;
            }

            $code = wp_remote_retrieve_response_code( $response );
            if ( 401 === $code ) {
                wp_send_json_error( array( 'message' => __( 'Invalid API key.', 'humata-chatbot' ) ) );
            }

            $body = wp_remote_retrieve_body( $response );
            $data = json_decode( $body, true );

            $pdf_title = '';
            if ( is_array( $data ) ) {
                if ( ! empty( $data['name'] ) ) {
                    $pdf_title = $data['name'];
                } elseif ( isset( $data['pdf'] ) && is_array( $data['pdf'] ) && ! empty( $data['pdf']['name'] ) ) {
                    $pdf_title = $data['pdf']['name'];
                }
            }

            if ( 200 !== $code || ! is_array( $data ) || '' === $pdf_title ) {
                if ( '' === $first_error_message ) {
                    $first_error_message = 'HTTP ' . (int) $code;
                    if ( is_string( $body ) && '' !== $body ) {
                        $body = wp_strip_all_tags( $body );
                        $first_error_message .= ': ' . substr( $body, 0, 200 );
                    }
                }
                $errors++;
                continue;
            }

            $titles[ $doc_id ] = sanitize_text_field( $pdf_title );
            $updated_titles[ $doc_id ] = $titles[ $doc_id ];
            $fetched++;
        }

        update_option( 'humata_document_titles', $titles );

        $next_offset = $offset + $limit;
        $done        = $next_offset >= $total;
        $processed   = min( $next_offset, $total );

        wp_send_json_success(
            array(
                'total'      => $total,
                'nextOffset' => $done ? $total : $next_offset,
                'done'       => $done,
                'fetched'    => $fetched,
                'errors'     => $errors,
                'titles'     => $updated_titles,
                'errorMessage' => $first_error_message,
                'message'    => sprintf( __( 'Titles updated. Processed %1$d/%2$d. Fetched %3$d, errors %4$d.', 'humata-chatbot' ), $processed, $total, $fetched, $errors ),
            )
        );
    }

    /**
     * AJAX handler for testing API connection.
     *
     * @since 1.0.0
     * @return void
     */
    public function ajax_test_api() {
        if ( ! check_ajax_referer( 'humata_admin_nonce', 'nonce', false ) ) {
            wp_send_json_error( array( 'message' => __( 'Security check failed.', 'humata-chatbot' ) ) );
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'humata-chatbot' ) ) );
        }

        $api_key      = get_option( 'humata_api_key', '' );
        $document_ids = get_option( 'humata_document_ids', '' );

        if ( empty( $api_key ) ) {
            wp_send_json_error( array( 'message' => __( 'API key not configured.', 'humata-chatbot' ) ) );
        }

        if ( empty( $document_ids ) ) {
            wp_send_json_error( array( 'message' => __( 'Document IDs not configured.', 'humata-chatbot' ) ) );
        }

        $doc_ids_array = $this->parse_document_ids( $document_ids );
        if ( empty( $doc_ids_array ) ) {
            wp_send_json_error( array( 'message' => __( 'No valid document IDs found.', 'humata-chatbot' ) ) );
        }

        // Try to create a conversation
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
                    'documentIds' => $doc_ids_array,
                ) ),
            )
        );

        if ( is_wp_error( $response ) ) {
            wp_send_json_error( array( 'message' => __( 'Connection failed: ', 'humata-chatbot' ) . $response->get_error_message() ) );
        }

        $response_code = wp_remote_retrieve_response_code( $response );
        $response_body = wp_remote_retrieve_body( $response );
        $data          = json_decode( $response_body, true );

        if ( 401 === $response_code ) {
            wp_send_json_error( array( 'message' => __( 'Invalid API key.', 'humata-chatbot' ) ) );
        }

        if ( $response_code >= 400 ) {
            $error_msg = isset( $data['message'] ) ? $data['message'] : sprintf( __( 'API error (HTTP %d)', 'humata-chatbot' ), $response_code );
            wp_send_json_error( array( 'message' => $error_msg ) );
        }

        if ( isset( $data['id'] ) ) {
            // Cache the conversation ID
            $cache_key = 'humata_conversation_' . md5( implode( ',', $doc_ids_array ) );
            set_transient( $cache_key, $data['id'], DAY_IN_SECONDS );
            
            wp_send_json_success( array( 
                'message' => sprintf( 
                    __( 'Success! Conversation created (ID: %s)', 'humata-chatbot' ),
                    substr( $data['id'], 0, 8 ) . '...'
                ) 
            ) );
        } else {
            wp_send_json_error( array( 'message' => __( 'Unexpected response from API.', 'humata-chatbot' ) ) );
        }
    }

    /**
     * AJAX handler for testing ask functionality.
     *
     * @since 1.0.0
     * @return void
     */
    public function ajax_test_ask() {
        if ( ! check_ajax_referer( 'humata_admin_nonce', 'nonce', false ) ) {
            wp_send_json_error( array( 'message' => __( 'Security check failed.', 'humata-chatbot' ) ) );
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'humata-chatbot' ) ) );
        }

        $api_key      = get_option( 'humata_api_key', '' );
        $document_ids = get_option( 'humata_document_ids', '' );

        if ( empty( $api_key ) || empty( $document_ids ) ) {
            wp_send_json_error( array( 'message' => __( 'API key or document IDs not configured.', 'humata-chatbot' ) ) );
        }

        $doc_ids_array = $this->parse_document_ids( $document_ids );
        if ( empty( $doc_ids_array ) ) {
            wp_send_json_error( array( 'message' => __( 'No valid document IDs found.', 'humata-chatbot' ) ) );
        }

        // Step 1: Create conversation
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
            wp_send_json_error( array( 'message' => 'Connection error: ' . $conv_response->get_error_message() ) );
        }

        $conv_code = wp_remote_retrieve_response_code( $conv_response );
        $conv_body = wp_remote_retrieve_body( $conv_response );
        $conv_data = json_decode( $conv_body, true );

        if ( $conv_code >= 400 || ! isset( $conv_data['id'] ) ) {
            wp_send_json_error( array( 'message' => 'Conversation failed: ' . $conv_body ) );
        }

        $conversation_id = $conv_data['id'];

        // Step 2: Ask a test question
        $ask_response = wp_remote_post(
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
                    'question'       => 'Hello, what is this document about?',
                    'model'          => 'gpt-4o',
                ) ),
            )
        );

        if ( is_wp_error( $ask_response ) ) {
            wp_send_json_error( array( 'message' => 'Ask connection error: ' . $ask_response->get_error_message() ) );
        }

        $ask_code = wp_remote_retrieve_response_code( $ask_response );
        $ask_body = wp_remote_retrieve_body( $ask_response );

        if ( $ask_code >= 400 ) {
            wp_send_json_error( array( 'message' => 'Ask failed (HTTP ' . $ask_code . '): ' . substr( $ask_body, 0, 200 ) ) );
        }

        // Try to parse SSE response
        $answer = '';
        $lines = explode( "\n", $ask_body );
        foreach ( $lines as $line ) {
            if ( strpos( $line, 'data: ' ) === 0 ) {
                $json_str = substr( $line, 6 );
                if ( ! empty( $json_str ) && $json_str !== '[DONE]' ) {
                    $data = json_decode( $json_str, true );
                    if ( isset( $data['content'] ) ) {
                        $answer .= $data['content'];
                    }
                }
            }
        }

        if ( ! empty( $answer ) ) {
            wp_send_json_success( array( 'message' => 'Ask works! Response: ' . substr( $answer, 0, 100 ) . '...' ) );
        } else {
            wp_send_json_error( array( 'message' => 'Empty response. Raw: ' . substr( $ask_body, 0, 300 ) ) );
        }
    }

    /**
     * AJAX handler for clearing cache.
     *
     * @since 1.0.0
     * @return void
     */
    public function ajax_clear_cache() {
        if ( ! check_ajax_referer( 'humata_admin_nonce', 'nonce', false ) ) {
            wp_send_json_error( array( 'message' => __( 'Security check failed.', 'humata-chatbot' ) ) );
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'humata-chatbot' ) ) );
        }

        global $wpdb;
        $wpdb->query(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_humata_conversation_%' OR option_name LIKE '_transient_timeout_humata_conversation_%'"
        );

        delete_option( 'humata_document_titles' );

        wp_send_json_success( array( 'message' => __( 'Cache cleared.', 'humata-chatbot' ) ) );
    }
}






