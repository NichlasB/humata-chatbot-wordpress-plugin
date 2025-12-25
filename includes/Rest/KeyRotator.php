<?php
/**
 * API Key Rotator service
 *
 * Handles round-robin rotation of API keys across multiple accounts
 * with automatic failover on rate limit or auth errors.
 *
 * @package Humata_Chatbot
 * @since 1.0.0
 */

defined( 'ABSPATH' ) || exit;

class Humata_Chatbot_Rest_Key_Rotator {

    /**
     * HTTP status codes that trigger failover to next key.
     *
     * @var array
     */
    const FAILOVER_STATUS_CODES = array( 401, 403, 429 );

    /**
     * Get the next API key from a pool using round-robin rotation.
     *
     * @since 1.0.0
     * @param string $pool_name Unique identifier for the key pool (e.g., 'straico_second_stage').
     * @param array  $keys      Array of API keys.
     * @return string|null The next API key, or null if pool is empty.
     */
    public function get_next_key( $pool_name, $keys ) {
        if ( ! is_array( $keys ) || empty( $keys ) ) {
            return null;
        }

        // Filter out empty keys.
        $keys = array_values( array_filter( $keys, function( $key ) {
            return is_string( $key ) && '' !== trim( $key );
        } ) );

        if ( empty( $keys ) ) {
            return null;
        }

        $count = count( $keys );
        if ( 1 === $count ) {
            return $keys[0];
        }

        $option_name = 'humata_' . sanitize_key( $pool_name ) . '_key_index';
        $index       = (int) get_option( $option_name, 0 );

        // Ensure index is within bounds.
        $index = $index % $count;

        // Increment for next request.
        $next_index = ( $index + 1 ) % $count;
        update_option( $option_name, $next_index, false );

        $this->log_key_usage( $pool_name, $index, $count );

        return $keys[ $index ];
    }

    /**
     * Get a key by specific index without incrementing the counter.
     *
     * @since 1.0.0
     * @param array $keys  Array of API keys.
     * @param int   $index Index to retrieve.
     * @return string|null The API key at index, or null if invalid.
     */
    public function get_key_by_index( $keys, $index ) {
        if ( ! is_array( $keys ) || empty( $keys ) ) {
            return null;
        }

        // Filter out empty keys.
        $keys = array_values( array_filter( $keys, function( $key ) {
            return is_string( $key ) && '' !== trim( $key );
        } ) );

        if ( empty( $keys ) ) {
            return null;
        }

        $index = (int) $index;
        $count = count( $keys );
        $index = $index % $count;

        return $keys[ $index ];
    }

    /**
     * Increment the rotation index for a pool.
     *
     * @since 1.0.0
     * @param string $pool_name Unique identifier for the key pool.
     * @param int    $key_count Total number of keys in the pool.
     * @return void
     */
    public function increment_index( $pool_name, $key_count ) {
        if ( $key_count <= 1 ) {
            return;
        }

        $option_name = 'humata_' . sanitize_key( $pool_name ) . '_key_index';
        $index       = (int) get_option( $option_name, 0 );
        $next_index  = ( $index + 1 ) % $key_count;
        update_option( $option_name, $next_index, false );
    }

    /**
     * Get the current rotation index for a pool.
     *
     * @since 1.0.0
     * @param string $pool_name Unique identifier for the key pool.
     * @return int Current index.
     */
    public function get_current_index( $pool_name ) {
        $option_name = 'humata_' . sanitize_key( $pool_name ) . '_key_index';
        return (int) get_option( $option_name, 0 );
    }

    /**
     * Reset the rotation index for a pool.
     *
     * @since 1.0.0
     * @param string $pool_name Unique identifier for the key pool.
     * @return void
     */
    public function reset_index( $pool_name ) {
        $option_name = 'humata_' . sanitize_key( $pool_name ) . '_key_index';
        update_option( $option_name, 0, false );
    }

    /**
     * Check if an HTTP status code should trigger failover.
     *
     * @since 1.0.0
     * @param int $status_code HTTP status code.
     * @return bool True if failover should be triggered.
     */
    public function should_failover( $status_code ) {
        return in_array( (int) $status_code, self::FAILOVER_STATUS_CODES, true );
    }

    /**
     * Normalize keys array from option value.
     *
     * Handles backward compatibility with single string values.
     *
     * @since 1.0.0
     * @param mixed $value Option value (string or array).
     * @return array Normalized array of keys.
     */
    public function normalize_keys( $value ) {
        if ( is_string( $value ) ) {
            $value = trim( $value );
            return '' !== $value ? array( $value ) : array();
        }

        if ( ! is_array( $value ) ) {
            return array();
        }

        // Filter and clean keys.
        $keys = array();
        foreach ( $value as $key ) {
            if ( is_string( $key ) ) {
                $key = trim( $key );
                if ( '' !== $key ) {
                    $keys[] = $key;
                }
            }
        }

        return $keys;
    }

    /**
     * Get the count of valid keys in a pool.
     *
     * @since 1.0.0
     * @param mixed $value Option value (string or array).
     * @return int Number of valid keys.
     */
    public function get_key_count( $value ) {
        return count( $this->normalize_keys( $value ) );
    }

    /**
     * Log key usage for debugging.
     *
     * @since 1.0.0
     * @param string $pool_name Pool identifier.
     * @param int    $index     Key index used.
     * @param int    $count     Total keys in pool.
     * @return void
     */
    private function log_key_usage( $pool_name, $index, $count ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log(
                sprintf(
                    '[Humata Chatbot] API key rotation: pool=%s, using key %d/%d',
                    $pool_name,
                    $index + 1,
                    $count
                )
            );
        }
    }

    /**
     * Log failover attempt for debugging.
     *
     * @since 1.0.0
     * @param string $pool_name   Pool identifier.
     * @param int    $failed_index Index of the failed key.
     * @param int    $status_code  HTTP status code that caused failover.
     * @param int    $count        Total keys in pool.
     * @return void
     */
    public function log_failover( $pool_name, $failed_index, $status_code, $count ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log(
                sprintf(
                    '[Humata Chatbot] API key failover: pool=%s, key %d/%d failed with status %d, trying next key',
                    $pool_name,
                    $failed_index + 1,
                    $count,
                    $status_code
                )
            );
        }
    }
}
