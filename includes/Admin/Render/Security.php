<?php
/**
 * Security-related field renderers
 *
 * @package Humata_Chatbot
 * @since 1.0.0
 */

defined( 'ABSPATH' ) || exit;

trait Humata_Chatbot_Admin_Settings_Render_Security_Trait {

    /**
     * Render rate limit field.
     *
     * @since 1.0.0
     * @return void
     */
    public function render_rate_limit_field() {
        $value = get_option( 'humata_rate_limit', 50 );
        ?>
        <input
            type="number"
            id="humata_rate_limit"
            name="humata_rate_limit"
            value="<?php echo esc_attr( $value ); ?>"
            min="1"
            max="1000"
            class="small-text"
        />
        <span><?php esc_html_e( 'requests per hour per IP address', 'humata-chatbot' ); ?></span>
        <p class="description">
            <?php esc_html_e( 'Limit the number of API requests to prevent abuse. Default is 50.', 'humata-chatbot' ); ?>
        </p>
        <?php
    }

    /**
     * Render trusted proxies field.
     *
     * @since 1.0.0
     * @return void
     */
    public function render_trusted_proxies_field() {
        $value = get_option( 'humata_trusted_proxies', '' );
        ?>
        <textarea
            id="humata_trusted_proxies"
            name="humata_trusted_proxies"
            rows="3"
            cols="50"
            class="large-text code"
        ><?php echo esc_textarea( $value ); ?></textarea>
        <p class="description">
            <?php esc_html_e( 'IP addresses or CIDR ranges of trusted reverse proxies (one per line or comma-separated). Only trusted proxies can set X-Forwarded-For headers for rate limiting. Leave empty to use REMOTE_ADDR directly.', 'humata-chatbot' ); ?>
        </p>
        <p class="description">
            <?php esc_html_e( 'Examples: 10.0.0.0/8, 172.16.0.0/12, 192.168.0.0/16, 127.0.0.1', 'humata-chatbot' ); ?>
        </p>
        <?php
    }

    /**
     * Render security headers enabled field.
     *
     * @since 1.0.0
     * @return void
     */
    public function render_security_headers_enabled_field() {
        $value = get_option( 'humata_security_headers_enabled', 0 );
        ?>
        <label>
            <input
                type="checkbox"
                id="humata_security_headers_enabled"
                name="humata_security_headers_enabled"
                value="1"
                <?php checked( $value, 1 ); ?>
            />
            <?php esc_html_e( 'Enable security headers on chat pages', 'humata-chatbot' ); ?>
        </label>
        <p class="description">
            <?php esc_html_e( 'Sends X-Frame-Options, X-Content-Type-Options, and Referrer-Policy headers on dedicated chat and homepage chat pages.', 'humata-chatbot' ); ?>
        </p>
        <?php
    }

}


