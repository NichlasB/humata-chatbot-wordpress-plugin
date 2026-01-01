<?php
/**
 * Bot Protection Service
 *
 * Main orchestrator for the three-layer bot protection system.
 * Coordinates honeypot, proof-of-work, and progressive delay checks.
 *
 * @package Humata_Chatbot
 * @since 1.0.0
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/HoneypotVerifier.php';
require_once __DIR__ . '/ProofOfWorkVerifier.php';

class Humata_Chatbot_Rest_Bot_Protection {

    /**
     * Session message count transient prefix.
     *
     * @var string
     */
    const SESSION_COUNT_PREFIX = 'humata_bot_session_';

    /**
     * Honeypot verifier instance.
     *
     * @var Humata_Chatbot_Rest_Honeypot_Verifier
     */
    private $honeypot_verifier;

    /**
     * Proof-of-work verifier instance.
     *
     * @var Humata_Chatbot_Rest_Proof_Of_Work_Verifier
     */
    private $pow_verifier;

    /**
     * Constructor.
     *
     * @since 1.0.0
     */
    public function __construct() {
        $this->honeypot_verifier = new Humata_Chatbot_Rest_Honeypot_Verifier();
        $this->pow_verifier      = new Humata_Chatbot_Rest_Proof_Of_Work_Verifier();
    }

    /**
     * Check if bot protection is enabled.
     *
     * @since 1.0.0
     * @return bool
     */
    public function is_enabled() {
        return (bool) get_option( 'humata_bot_protection_enabled', false );
    }

    /**
     * Run all enabled bot protection checks.
     *
     * @since 1.0.0
     * @param WP_REST_Request $request Request object.
     * @param string          $ip      Client IP address.
     * @return bool|WP_Error True if all checks pass, WP_Error otherwise.
     */
    public function check( $request, $ip ) {
        if ( ! $this->is_enabled() ) {
            return true;
        }

        // Layer 1: Honeypot check.
        $honeypot_enabled = (bool) get_option( 'humata_honeypot_enabled', true );
        if ( $honeypot_enabled ) {
            $honeypot_result = $this->honeypot_verifier->check( $request );
            if ( is_wp_error( $honeypot_result ) ) {
                return $honeypot_result;
            }
        }

        // Layer 2: Proof-of-work check.
        $pow_enabled = (bool) get_option( 'humata_pow_enabled', true );
        if ( $pow_enabled ) {
            $pow_result = $this->pow_verifier->check( $request, $ip );
            if ( is_wp_error( $pow_result ) ) {
                return $pow_result;
            }
        }

        return true;
    }

    /**
     * Check progressive delay based on session message count.
     *
     * Returns WP_Error with 429 status if throttling is required,
     * allowing caller to respond without blocking PHP workers.
     *
     * @since 1.0.0
     * @param string $ip Client IP address.
     * @return true|WP_Error True if no delay needed, WP_Error with retry_after if throttled.
     */
    public function apply_progressive_delay( $ip ) {
        if ( ! $this->is_enabled() ) {
            return true;
        }

        $delays_enabled = (bool) get_option( 'humata_progressive_delays_enabled', false );
        if ( ! $delays_enabled ) {
            return true;
        }

        $session_key = self::SESSION_COUNT_PREFIX . md5( $ip );
        $session_data = get_transient( $session_key );

        if ( false === $session_data || ! is_array( $session_data ) ) {
            $session_data = array(
                'count'      => 0,
                'last_time'  => time(),
            );
        }

        // Check cooldown reset.
        $cooldown_minutes = absint( get_option( 'humata_delay_cooldown_minutes', 30 ) );
        if ( $cooldown_minutes < 1 ) {
            $cooldown_minutes = 30;
        }

        $cooldown_seconds = $cooldown_minutes * 60;
        $time_since_last  = time() - (int) $session_data['last_time'];

        if ( $time_since_last > $cooldown_seconds ) {
            // Reset count after cooldown.
            $session_data['count'] = 0;
        }

        // Increment message count.
        $session_data['count']     = (int) $session_data['count'] + 1;
        $session_data['last_time'] = time();

        // Calculate delay based on thresholds.
        $delay_seconds = $this->calculate_delay( $session_data['count'] );

        // Store updated session data (expires after cooldown period + buffer).
        set_transient( $session_key, $session_data, $cooldown_seconds + 300 );

        // Return 429 error with Retry-After if throttling is required.
        if ( $delay_seconds > 0 ) {
            return new WP_Error(
                'rate_limited',
                __( 'Too many requests. Please wait before sending another message.', 'humata-chatbot' ),
                array(
                    'status'      => 429,
                    'retry_after' => $delay_seconds,
                )
            );
        }

        return true;
    }

    /**
     * Calculate delay based on message count and configured thresholds.
     *
     * @since 1.0.0
     * @param int $message_count Current session message count.
     * @return int Delay in seconds.
     */
    private function calculate_delay( $message_count ) {
        $threshold_1_count = absint( get_option( 'humata_delay_threshold_1_count', 10 ) );
        $threshold_1_delay = absint( get_option( 'humata_delay_threshold_1_delay', 1 ) );
        $threshold_2_count = absint( get_option( 'humata_delay_threshold_2_count', 20 ) );
        $threshold_2_delay = absint( get_option( 'humata_delay_threshold_2_delay', 3 ) );
        $threshold_3_count = absint( get_option( 'humata_delay_threshold_3_count', 30 ) );
        $threshold_3_delay = absint( get_option( 'humata_delay_threshold_3_delay', 5 ) );

        // Cap delays at 10 seconds max for UX.
        $threshold_1_delay = min( $threshold_1_delay, 10 );
        $threshold_2_delay = min( $threshold_2_delay, 10 );
        $threshold_3_delay = min( $threshold_3_delay, 10 );

        if ( $message_count >= $threshold_3_count && $threshold_3_count > 0 ) {
            return $threshold_3_delay;
        }

        if ( $message_count >= $threshold_2_count && $threshold_2_count > 0 ) {
            return $threshold_2_delay;
        }

        if ( $message_count >= $threshold_1_count && $threshold_1_count > 0 ) {
            return $threshold_1_delay;
        }

        return 0;
    }

    /**
     * Get bot protection configuration for frontend.
     *
     * @since 1.0.0
     * @return array
     */
    public function get_frontend_config() {
        if ( ! $this->is_enabled() ) {
            return array(
                'enabled' => false,
            );
        }

        $honeypot_enabled = (bool) get_option( 'humata_honeypot_enabled', true );
        $pow_enabled      = (bool) get_option( 'humata_pow_enabled', true );
        $pow_difficulty   = humata_get_pow_difficulty();

        return array(
            'enabled'          => true,
            'honeypotEnabled'  => $honeypot_enabled,
            'powEnabled'       => $pow_enabled,
            'powDifficulty'    => $pow_difficulty,
        );
    }
}
