<?php
/**
 * Base Settings Tab
 *
 * Shared utilities for Humata settings tabs.
 *
 * @package Humata_Chatbot
 * @since 1.0.0
 */
defined( 'ABSPATH' ) || exit;

abstract class Humata_Chatbot_Settings_Tab_Base {

    /**
     * Admin settings instance.
     *
     * @since 1.0.0
     * @var object
     */
    protected $admin;

    /**
     * Constructor.
     *
     * @since 1.0.0
     * @param object $admin Admin settings instance (Humata_Chatbot_Admin_Settings).
     */
    public function __construct( $admin ) {
        $this->admin = $admin;
    }

    /**
     * Tab key used in `?tab=`.
     *
     * @since 1.0.0
     * @return string
     */
    abstract public function get_key();

    /**
     * Tab label for the navigation UI.
     *
     * @since 1.0.0
     * @return string
     */
    abstract public function get_label();

    /**
     * Settings API page ID used for `do_settings_sections()`.
     *
     * @since 1.0.0
     * @return string
     */
    abstract public function get_page_id();

    /**
     * Whether this tab renders a settings form.
     *
     * @since 1.0.0
     * @return bool
     */
    public function has_form() {
        return true;
    }

    /**
     * Register Settings API sections and fields for this tab.
     *
     * @since 1.0.0
     * @return void
     */
    abstract public function register();

    /**
     * Render the tab contents.
     *
     * @since 1.0.0
     * @return void
     */
    public function render() {
        if ( ! $this->has_form() ) {
            return;
        }

        if ( is_object( $this->admin ) && method_exists( $this->admin, 'render_tab_form' ) ) {
            $this->admin->render_tab_form( $this->get_page_id(), $this->get_key() );
            return;
        }

        // Fallback if the admin helper is not available.
        ?>
        <form action="options.php" method="post">
            <?php
            settings_fields( 'humata_chatbot_settings' );
            do_settings_sections( $this->get_page_id() );
            submit_button( __( 'Save Settings', 'humata-chatbot' ) );
            ?>
        </form>
        <?php
    }
}


