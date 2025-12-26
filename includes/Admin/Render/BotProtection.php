<?php
/**
 * Bot Protection Render Methods
 *
 * Field rendering methods for bot protection settings.
 *
 * @package Humata_Chatbot
 * @since 1.0.0
 */

defined( 'ABSPATH' ) || exit;

trait Humata_Chatbot_Admin_Settings_Render_Bot_Protection_Trait {

    /**
     * Render bot protection enabled checkbox.
     *
     * @since 1.0.0
     * @return void
     */
    public function render_bot_protection_enabled_field() {
        $value = (int) get_option( 'humata_bot_protection_enabled', 0 );
        ?>
        <label>
            <input
                type="checkbox"
                id="humata_bot_protection_enabled"
                name="humata_bot_protection_enabled"
                value="1"
                <?php checked( 1, $value ); ?>
            />
            <?php esc_html_e( 'Enable bot protection system', 'humata-chatbot' ); ?>
        </label>
        <p class="description">
            <?php esc_html_e( 'Enables the three-layer bot protection system (honeypot, proof-of-work, progressive delays). Works without external dependencies.', 'humata-chatbot' ); ?>
        </p>
        <?php
    }

    /**
     * Render honeypot enabled checkbox.
     *
     * @since 1.0.0
     * @return void
     */
    public function render_honeypot_enabled_field() {
        $value = (int) get_option( 'humata_honeypot_enabled', 1 );
        ?>
        <label>
            <input
                type="checkbox"
                id="humata_honeypot_enabled"
                name="humata_honeypot_enabled"
                value="1"
                <?php checked( 1, $value ); ?>
            />
            <?php esc_html_e( 'Enable honeypot fields', 'humata-chatbot' ); ?>
        </label>
        <p class="description">
            <?php esc_html_e( 'Hidden form fields that bots fill but humans do not see. Also checks submission timing to detect automated requests.', 'humata-chatbot' ); ?>
        </p>
        <?php
    }

    /**
     * Render PoW enabled checkbox.
     *
     * @since 1.0.0
     * @return void
     */
    public function render_pow_enabled_field() {
        $value = (int) get_option( 'humata_pow_enabled', 1 );
        ?>
        <label>
            <input
                type="checkbox"
                id="humata_pow_enabled"
                name="humata_pow_enabled"
                value="1"
                <?php checked( 1, $value ); ?>
            />
            <?php esc_html_e( 'Enable proof-of-work challenge', 'humata-chatbot' ); ?>
        </label>
        <p class="description">
            <?php esc_html_e( 'Browser solves a SHA256 puzzle on first message (~100-500ms). Session is verified for subsequent messages.', 'humata-chatbot' ); ?>
        </p>
        <?php
    }

    /**
     * Render PoW difficulty field.
     *
     * @since 1.0.0
     * @return void
     */
    public function render_pow_difficulty_field() {
        $value = absint( get_option( 'humata_pow_difficulty', 4 ) );
        if ( $value < 1 ) {
            $value = 4;
        }
        if ( $value > 8 ) {
            $value = 8;
        }
        ?>
        <input
            type="number"
            id="humata_pow_difficulty"
            name="humata_pow_difficulty"
            value="<?php echo esc_attr( $value ); ?>"
            min="1"
            max="8"
            class="small-text"
        />
        <span><?php esc_html_e( 'leading zeros required in hash', 'humata-chatbot' ); ?></span>
        <p class="description">
            <?php esc_html_e( 'Higher values = harder puzzle = longer solve time. 4 = ~100-500ms, 5 = ~1-2s, 6+ = several seconds. Default: 4.', 'humata-chatbot' ); ?>
        </p>
        <?php
    }

    /**
     * Render progressive delays enabled checkbox.
     *
     * @since 1.0.0
     * @return void
     */
    public function render_progressive_delays_enabled_field() {
        $value = (int) get_option( 'humata_progressive_delays_enabled', 0 );
        ?>
        <label>
            <input
                type="checkbox"
                id="humata_progressive_delays_enabled"
                name="humata_progressive_delays_enabled"
                value="1"
                <?php checked( 1, $value ); ?>
            />
            <?php esc_html_e( 'Enable progressive delays', 'humata-chatbot' ); ?>
        </label>
        <p class="description">
            <?php esc_html_e( 'Add increasing response delays after N messages per session to slow down automated abuse.', 'humata-chatbot' ); ?>
        </p>
        <?php
    }

    /**
     * Render delay threshold 1 fields.
     *
     * @since 1.0.0
     * @return void
     */
    public function render_delay_threshold_1_field() {
        $count = absint( get_option( 'humata_delay_threshold_1_count', 10 ) );
        $delay = absint( get_option( 'humata_delay_threshold_1_delay', 1 ) );
        ?>
        <span><?php esc_html_e( 'After', 'humata-chatbot' ); ?></span>
        <input
            type="number"
            id="humata_delay_threshold_1_count"
            name="humata_delay_threshold_1_count"
            value="<?php echo esc_attr( $count ); ?>"
            min="0"
            max="1000"
            class="small-text"
        />
        <span><?php esc_html_e( 'messages, add', 'humata-chatbot' ); ?></span>
        <input
            type="number"
            id="humata_delay_threshold_1_delay"
            name="humata_delay_threshold_1_delay"
            value="<?php echo esc_attr( $delay ); ?>"
            min="0"
            max="10"
            class="small-text"
        />
        <span><?php esc_html_e( 'second(s) delay', 'humata-chatbot' ); ?></span>
        <?php
    }

    /**
     * Render delay threshold 2 fields.
     *
     * @since 1.0.0
     * @return void
     */
    public function render_delay_threshold_2_field() {
        $count = absint( get_option( 'humata_delay_threshold_2_count', 20 ) );
        $delay = absint( get_option( 'humata_delay_threshold_2_delay', 3 ) );
        ?>
        <span><?php esc_html_e( 'After', 'humata-chatbot' ); ?></span>
        <input
            type="number"
            id="humata_delay_threshold_2_count"
            name="humata_delay_threshold_2_count"
            value="<?php echo esc_attr( $count ); ?>"
            min="0"
            max="1000"
            class="small-text"
        />
        <span><?php esc_html_e( 'messages, add', 'humata-chatbot' ); ?></span>
        <input
            type="number"
            id="humata_delay_threshold_2_delay"
            name="humata_delay_threshold_2_delay"
            value="<?php echo esc_attr( $delay ); ?>"
            min="0"
            max="10"
            class="small-text"
        />
        <span><?php esc_html_e( 'second(s) delay', 'humata-chatbot' ); ?></span>
        <?php
    }

    /**
     * Render delay threshold 3 fields.
     *
     * @since 1.0.0
     * @return void
     */
    public function render_delay_threshold_3_field() {
        $count = absint( get_option( 'humata_delay_threshold_3_count', 30 ) );
        $delay = absint( get_option( 'humata_delay_threshold_3_delay', 5 ) );
        ?>
        <span><?php esc_html_e( 'After', 'humata-chatbot' ); ?></span>
        <input
            type="number"
            id="humata_delay_threshold_3_count"
            name="humata_delay_threshold_3_count"
            value="<?php echo esc_attr( $count ); ?>"
            min="0"
            max="1000"
            class="small-text"
        />
        <span><?php esc_html_e( 'messages, add', 'humata-chatbot' ); ?></span>
        <input
            type="number"
            id="humata_delay_threshold_3_delay"
            name="humata_delay_threshold_3_delay"
            value="<?php echo esc_attr( $delay ); ?>"
            min="0"
            max="10"
            class="small-text"
        />
        <span><?php esc_html_e( 'second(s) delay', 'humata-chatbot' ); ?></span>
        <?php
    }

    /**
     * Render delay cooldown minutes field.
     *
     * @since 1.0.0
     * @return void
     */
    public function render_delay_cooldown_minutes_field() {
        $value = absint( get_option( 'humata_delay_cooldown_minutes', 30 ) );
        if ( $value < 1 ) {
            $value = 30;
        }
        ?>
        <input
            type="number"
            id="humata_delay_cooldown_minutes"
            name="humata_delay_cooldown_minutes"
            value="<?php echo esc_attr( $value ); ?>"
            min="1"
            max="1440"
            class="small-text"
        />
        <span><?php esc_html_e( 'minutes of inactivity', 'humata-chatbot' ); ?></span>
        <p class="description">
            <?php esc_html_e( 'Message count resets after this many minutes of inactivity. Default: 30 minutes.', 'humata-chatbot' ); ?>
        </p>
        <?php
    }

    /**
     * Render bot protection section description.
     *
     * @since 1.0.0
     * @return void
     */
    public function render_bot_protection_section() {
        ?>
        <p>
            <?php esc_html_e( 'Zero-friction bot protection that works without external services. Uses honeypot fields and proof-of-work challenges.', 'humata-chatbot' ); ?>
        </p>
        <?php
    }

    /**
     * Render progressive delays section description.
     *
     * @since 1.0.0
     * @return void
     */
    public function render_progressive_delays_section() {
        ?>
        <p>
            <?php esc_html_e( 'Add increasing response delays after a certain number of messages per session. Helps prevent automated abuse.', 'humata-chatbot' ); ?>
        </p>
        <?php
    }
}
