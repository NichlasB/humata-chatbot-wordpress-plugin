<?php
/**
 * Cloudflare Turnstile verification service for REST requests
 *
 * @package Humata_Chatbot
 * @since 1.0.0
 */

defined( 'ABSPATH' ) || exit;

class Humata_Chatbot_Rest_Turnstile_Verifier {

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
     * Check Cloudflare Turnstile verification.
     *
     * @since 1.0.0
     * @param WP_REST_Request $request Request object.
     * @param string          $ip      Client IP address.
     * @return bool|WP_Error True if verified or not required, WP_Error if verification failed.
     */
    public function check( $request, $ip ) {
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

        $ip        = (string) $ip;
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
}


