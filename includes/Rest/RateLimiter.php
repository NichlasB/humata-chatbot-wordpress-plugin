<?php
/**
 * Rate limiter for REST requests
 *
 * @package Humata_Chatbot
 * @since 1.0.0
 */

defined( 'ABSPATH' ) || exit;

class Humata_Chatbot_Rest_Rate_Limiter {

    /**
     * Rate limit transient prefix.
     *
     * @var string
     */
    const RATE_LIMIT_PREFIX = 'humata_rate_limit_';

    /**
     * Check rate limit for a given IP.
     *
     * @since 1.0.0
     * @param string $ip Client IP.
     * @return bool|WP_Error True if within limit, WP_Error if exceeded.
     */
    public function check( $ip ) {
        $ip        = (string) $ip;
        $transient = self::RATE_LIMIT_PREFIX . md5( $ip );
        $limit     = absint( get_option( 'humata_rate_limit', 50 ) );
        $count     = get_transient( $transient );

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
}


