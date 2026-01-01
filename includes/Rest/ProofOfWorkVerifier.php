<?php
/**
 * Proof-of-Work Verification Service
 *
 * Layer 2 of bot protection: Browser solves SHA256 puzzle on first message,
 * server validates, session is verified for subsequent messages.
 *
 * @package Humata_Chatbot
 * @since 1.0.0
 */

defined( 'ABSPATH' ) || exit;

class Humata_Chatbot_Rest_Proof_Of_Work_Verifier {

    /**
     * PoW verified session transient prefix.
     *
     * @var string
     */
    const POW_VERIFIED_PREFIX = 'humata_pow_verified_';

    /**
     * PoW challenge transient prefix.
     *
     * @var string
     */
    const POW_CHALLENGE_PREFIX = 'humata_pow_challenge_';

    /**
     * Check proof-of-work verification.
     *
     * @since 1.0.0
     * @param WP_REST_Request $request Request object.
     * @param string          $ip      Client IP address.
     * @return bool|WP_Error True if valid, WP_Error if verification failed.
     */
    public function check( $request, $ip ) {
        $ip        = (string) $ip;
        $transient = self::POW_VERIFIED_PREFIX . md5( $ip );

        // Check if already verified in this session.
        $verified = get_transient( $transient );
        if ( false !== $verified ) {
            return true;
        }

        // Get PoW solution from request headers.
        $pow_nonce    = $request->get_header( 'X-Humata-PoW-Nonce' );
        $pow_solution = $request->get_header( 'X-Humata-PoW-Solution' );

        if ( empty( $pow_nonce ) || empty( $pow_solution ) ) {
            // No PoW provided - generate a challenge for the client.
            return $this->generate_challenge_error( $ip );
        }

        // Verify the solution.
        $verify_result = $this->verify_solution( $pow_nonce, $pow_solution, $ip );
        if ( is_wp_error( $verify_result ) ) {
            return $verify_result;
        }

        // Mark as verified for 1 hour.
        set_transient( $transient, 1, HOUR_IN_SECONDS );

        // Clean up the challenge transient.
        delete_transient( self::POW_CHALLENGE_PREFIX . md5( $ip ) );

        return true;
    }

    /**
     * Generate a PoW challenge error response.
     *
     * @since 1.0.0
     * @param string $ip Client IP address.
     * @return WP_Error Error with challenge data.
     */
    private function generate_challenge_error( $ip ) {
        $challenge = $this->generate_challenge( $ip );

        return new WP_Error(
            'pow_required',
            __( 'Verification required. Please wait...', 'humata-chatbot' ),
            array(
                'status'    => 403,
                'challenge' => $challenge,
            )
        );
    }

    /**
     * Generate a new PoW challenge.
     *
     * @since 1.0.0
     * @param string $ip Client IP address.
     * @return array Challenge data.
     */
    public function generate_challenge( $ip ) {
        $challenge_key = self::POW_CHALLENGE_PREFIX . md5( $ip );
        
        // Check if a valid challenge already exists (prevents race conditions).
        $existing = get_transient( $challenge_key );
        if ( false !== $existing && is_array( $existing ) && isset( $existing['timestamp'] ) ) {
            // Only reuse if less than 2 minutes old.
            $age = time() - (int) $existing['timestamp'];
            if ( $age < 2 * MINUTE_IN_SECONDS ) {
                return $existing;
            }
        }

        $nonce      = wp_generate_password( 32, false );
        $timestamp  = time();
        $difficulty = humata_get_pow_difficulty();

        $challenge = array(
            'nonce'      => $nonce,
            'timestamp'  => $timestamp,
            'difficulty' => $difficulty,
        );

        // Store challenge for verification (expires in 5 minutes).
        set_transient( $challenge_key, $challenge, 5 * MINUTE_IN_SECONDS );

        return $challenge;
    }

    /**
     * Verify a PoW solution.
     *
     * @since 1.0.0
     * @param string $nonce    The challenge nonce.
     * @param string $solution The solution (counter value).
     * @param string $ip       Client IP address.
     * @return bool|WP_Error True if valid, WP_Error otherwise.
     */
    private function verify_solution( $nonce, $solution, $ip ) {
        $challenge_key = self::POW_CHALLENGE_PREFIX . md5( $ip );
        $challenge     = get_transient( $challenge_key );

        if ( false === $challenge || ! is_array( $challenge ) ) {
            // Challenge expired or doesn't exist.
            return new WP_Error(
                'pow_expired',
                __( 'Verification expired. Please try again.', 'humata-chatbot' ),
                array(
                    'status'    => 403,
                    'challenge' => $this->generate_challenge( $ip ),
                )
            );
        }

        // Verify the nonce matches.
        if ( $nonce !== $challenge['nonce'] ) {
            return new WP_Error(
                'pow_invalid',
                __( 'Verification failed. Please try again.', 'humata-chatbot' ),
                array(
                    'status'    => 403,
                    'challenge' => $this->generate_challenge( $ip ),
                )
            );
        }

        // Verify the timestamp isn't too old (5 minutes max).
        $timestamp = (int) $challenge['timestamp'];
        if ( time() - $timestamp > 5 * MINUTE_IN_SECONDS ) {
            return new WP_Error(
                'pow_expired',
                __( 'Verification expired. Please try again.', 'humata-chatbot' ),
                array(
                    'status'    => 403,
                    'challenge' => $this->generate_challenge( $ip ),
                )
            );
        }

        $difficulty = (int) $challenge['difficulty'];

        // Verify the hash meets difficulty requirement.
        $hash_input = $nonce . ':' . $solution;
        $hash       = hash( 'sha256', $hash_input );

        // Check for required leading zeros.
        $required_prefix = str_repeat( '0', $difficulty );
        if ( substr( $hash, 0, $difficulty ) !== $required_prefix ) {
            return new WP_Error(
                'pow_invalid',
                __( 'Verification failed. Please try again.', 'humata-chatbot' ),
                array(
                    'status'    => 403,
                    'challenge' => $this->generate_challenge( $ip ),
                )
            );
        }

        return true;
    }

    /**
     * Check if a session is already verified.
     *
     * @since 1.0.0
     * @param string $ip Client IP address.
     * @return bool
     */
    public function is_verified( $ip ) {
        $transient = self::POW_VERIFIED_PREFIX . md5( $ip );
        return false !== get_transient( $transient );
    }
}
