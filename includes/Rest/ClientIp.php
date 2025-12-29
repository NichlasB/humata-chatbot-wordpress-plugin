<?php
/**
 * Client IP resolver
 *
 * @package Humata_Chatbot
 * @since 1.0.0
 */

defined( 'ABSPATH' ) || exit;

class Humata_Chatbot_Rest_Client_Ip {

    /**
     * Get client IP address.
     *
     * Only trusts proxy headers (X-Forwarded-For, HTTP_CLIENT_IP) when
     * REMOTE_ADDR is in the configured trusted proxies list.
     *
     * @since 1.0.0
     * @return string Client IP address (validated) or empty string.
     */
    public static function get_client_ip() {
        $remote_addr = '';
        if ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
            $remote_addr = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
            $remote_addr = self::validate_ip( $remote_addr );
        }

        // If REMOTE_ADDR is not a trusted proxy, use it directly.
        if ( ! self::is_trusted_proxy( $remote_addr ) ) {
            return $remote_addr;
        }

        // REMOTE_ADDR is trusted, check proxy headers.
        $ip = '';

        if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
            $ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CLIENT_IP'] ) );
            $ip = self::validate_ip( $ip );
        }

        if ( empty( $ip ) && ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
            $forwarded = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
            $ip        = self::parse_forwarded_for( $forwarded );
        }

        // Fallback to REMOTE_ADDR if proxy headers are empty/invalid.
        if ( empty( $ip ) ) {
            $ip = $remote_addr;
        }

        return $ip;
    }

    /**
     * Validate an IP address.
     *
     * @since 1.0.0
     * @param string $ip IP address to validate.
     * @return string Valid IP address or empty string.
     */
    public static function validate_ip( $ip ) {
        $ip = trim( (string) $ip );

        if ( '' === $ip ) {
            return '';
        }

        // Validate IPv4 or IPv6.
        if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6 ) ) {
            return $ip;
        }

        return '';
    }

    /**
     * Parse X-Forwarded-For header and return first valid IP.
     *
     * @since 1.0.0
     * @param string $header X-Forwarded-For header value.
     * @return string First valid IP or empty string.
     */
    private static function parse_forwarded_for( $header ) {
        $header = trim( (string) $header );

        if ( '' === $header ) {
            return '';
        }

        // X-Forwarded-For can contain: client, proxy1, proxy2, ...
        // The leftmost (first) is the original client IP.
        $ips = array_map( 'trim', explode( ',', $header ) );

        foreach ( $ips as $ip ) {
            $valid = self::validate_ip( $ip );
            if ( '' !== $valid ) {
                return $valid;
            }
        }

        return '';
    }

    /**
     * Check if an IP is in the trusted proxies list.
     *
     * @since 1.0.0
     * @param string $ip IP address to check.
     * @return bool True if IP is a trusted proxy.
     */
    private static function is_trusted_proxy( $ip ) {
        $ip = trim( (string) $ip );

        if ( '' === $ip ) {
            return false;
        }

        $trusted = get_option( 'humata_trusted_proxies', '' );

        // If no trusted proxies configured, don't trust any proxy headers.
        if ( empty( $trusted ) ) {
            return false;
        }

        // Parse trusted proxies (comma or newline separated).
        $trusted_list = preg_split( '/[\s,]+/', $trusted, -1, PREG_SPLIT_NO_EMPTY );

        if ( empty( $trusted_list ) ) {
            return false;
        }

        foreach ( $trusted_list as $trusted_ip ) {
            $trusted_ip = trim( $trusted_ip );

            // Exact match.
            if ( $trusted_ip === $ip ) {
                return true;
            }

            // CIDR match (e.g., 10.0.0.0/8, 192.168.0.0/16).
            if ( false !== strpos( $trusted_ip, '/' ) && self::ip_in_cidr( $ip, $trusted_ip ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if an IP is within a CIDR range.
     *
     * @since 1.0.0
     * @param string $ip   IP address to check.
     * @param string $cidr CIDR notation (e.g., 10.0.0.0/8).
     * @return bool True if IP is in CIDR range.
     */
    private static function ip_in_cidr( $ip, $cidr ) {
        list( $subnet, $mask ) = array_pad( explode( '/', $cidr, 2 ), 2, null );

        if ( null === $mask ) {
            return $ip === $subnet;
        }

        $mask = (int) $mask;

        // IPv4.
        if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) &&
             filter_var( $subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
            $ip_long     = ip2long( $ip );
            $subnet_long = ip2long( $subnet );

            if ( false === $ip_long || false === $subnet_long ) {
                return false;
            }

            $mask_long = -1 << ( 32 - $mask );
            return ( $ip_long & $mask_long ) === ( $subnet_long & $mask_long );
        }

        return false;
    }
}


