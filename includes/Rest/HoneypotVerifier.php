<?php
/**
 * Honeypot Verification Service
 *
 * Layer 1 of bot protection: Hidden form fields that bots fill but humans don't,
 * plus submission timing check.
 *
 * @package Humata_Chatbot
 * @since 1.0.0
 */

defined( 'ABSPATH' ) || exit;

class Humata_Chatbot_Rest_Honeypot_Verifier {

    /**
     * Minimum time in seconds between page load and first submission.
     * Submissions faster than this are likely bots.
     *
     * @var int
     */
    const MIN_SUBMISSION_TIME = 1;

    /**
     * Check honeypot fields in the request.
     *
     * @since 1.0.0
     * @param WP_REST_Request $request Request object.
     * @return bool|WP_Error True if valid, WP_Error if bot detected.
     */
    public function check( $request ) {
        // Check honeypot field (should be empty).
        $honeypot_value = $request->get_header( 'X-Humata-HP' );
        if ( null !== $honeypot_value && '' !== $honeypot_value ) {
            // Honeypot field was filled - likely a bot.
            if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                error_log( '[Humata Chatbot] Bot detected: honeypot field filled' );
            }
            return new WP_Error(
                'bot_detected',
                __( 'Request blocked. Please try again.', 'humata-chatbot' ),
                array( 'status' => 403 )
            );
        }

        // Check submission timing.
        $timestamp = $request->get_header( 'X-Humata-TS' );
        if ( ! empty( $timestamp ) ) {
            $timestamp = absint( $timestamp );
            $now       = time();

            // Timestamp should be in the past but not too far in the past (1 hour max).
            if ( $timestamp > $now ) {
                // Future timestamp - suspicious.
                if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                    error_log( '[Humata Chatbot] Bot detected: future timestamp' );
                }
                return new WP_Error(
                    'bot_detected',
                    __( 'Request blocked. Please try again.', 'humata-chatbot' ),
                    array( 'status' => 403 )
                );
            }

            $elapsed = $now - $timestamp;

            // Check if submission was too fast.
            if ( $elapsed < self::MIN_SUBMISSION_TIME ) {
                if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                    error_log( '[Humata Chatbot] Bot detected: submission too fast (' . $elapsed . 's)' );
                }
                return new WP_Error(
                    'bot_detected',
                    __( 'Request blocked. Please try again.', 'humata-chatbot' ),
                    array( 'status' => 403 )
                );
            }

            // Check if timestamp is too old (more than 1 hour).
            if ( $elapsed > HOUR_IN_SECONDS ) {
                if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
                    error_log( '[Humata Chatbot] Bot detected: timestamp too old (' . $elapsed . 's)' );
                }
                return new WP_Error(
                    'bot_detected',
                    __( 'Session expired. Please refresh the page.', 'humata-chatbot' ),
                    array( 'status' => 403 )
                );
            }
        }

        return true;
    }
}
