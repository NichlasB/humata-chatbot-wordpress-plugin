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
     * Render Turnstile enabled checkbox.
     *
     * @since 1.0.0
     * @return void
     */
    public function render_turnstile_enabled_field() {
        $value = (int) get_option( 'humata_turnstile_enabled', 0 );
        ?>
        <label>
            <input
                type="checkbox"
                id="humata_turnstile_enabled"
                name="humata_turnstile_enabled"
                value="1"
                <?php checked( 1, $value ); ?>
            />
            <?php esc_html_e( 'Require human verification before first message', 'humata-chatbot' ); ?>
        </label>
        <?php
    }

    /**
     * Render Turnstile site key field.
     *
     * @since 1.0.0
     * @return void
     */
    public function render_turnstile_site_key_field() {
        $value = get_option( 'humata_turnstile_site_key', '' );
        ?>
        <input
            type="text"
            id="humata_turnstile_site_key"
            name="humata_turnstile_site_key"
            value="<?php echo esc_attr( $value ); ?>"
            class="regular-text"
            autocomplete="off"
        />
        <p class="description">
            <?php esc_html_e( 'The site key from your Cloudflare Turnstile widget configuration.', 'humata-chatbot' ); ?>
        </p>
        <?php
    }

    /**
     * Render Turnstile secret key field.
     *
     * @since 1.0.0
     * @return void
     */
    public function render_turnstile_secret_key_field() {
        $value = get_option( 'humata_turnstile_secret_key', '' );
        ?>
        <input
            type="password"
            id="humata_turnstile_secret_key"
            name="humata_turnstile_secret_key"
            value="<?php echo esc_attr( $value ); ?>"
            class="regular-text"
            autocomplete="off"
        />
        <p class="description">
            <?php esc_html_e( 'The secret key from your Cloudflare Turnstile widget configuration. This is used for server-side verification.', 'humata-chatbot' ); ?>
        </p>
        <?php
    }

    /**
     * Render Turnstile appearance field.
     *
     * @since 1.0.0
     * @return void
     */
    public function render_turnstile_appearance_field() {
        $value = get_option( 'humata_turnstile_appearance', 'managed' );
        ?>
        <select id="humata_turnstile_appearance" name="humata_turnstile_appearance">
            <option value="managed" <?php selected( 'managed', $value ); ?>>
                <?php esc_html_e( 'Managed (recommended)', 'humata-chatbot' ); ?>
            </option>
            <option value="non-interactive" <?php selected( 'non-interactive', $value ); ?>>
                <?php esc_html_e( 'Non-interactive (always invisible)', 'humata-chatbot' ); ?>
            </option>
            <option value="interaction-only" <?php selected( 'interaction-only', $value ); ?>>
                <?php esc_html_e( 'Interaction-only (shows checkbox only when needed)', 'humata-chatbot' ); ?>
            </option>
        </select>
        <p class="description">
            <?php esc_html_e( 'How the Turnstile widget appears to users. Note: Cloudflare may verify users invisibly using browser signals (like Private Access Tokens) even in "interaction-only" mode if it determines no interaction is needed.', 'humata-chatbot' ); ?>
        </p>
        <?php
    }
}


