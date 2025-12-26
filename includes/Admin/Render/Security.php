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

}


