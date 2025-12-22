<?php
/**
 * Settings Tab: Pages
 *
 * Trigger pages configuration tab.
 *
 * @package Humata_Chatbot
 * @since 1.0.0
 */

defined( 'ABSPATH' ) || exit;

class Humata_Chatbot_Settings_Tab_Pages extends Humata_Chatbot_Settings_Tab_Base {

    public function get_key() {
        return 'pages';
    }

    public function get_label() {
        return __( 'Pages', 'humata-chatbot' );
    }

    public function get_page_id() {
        return 'humata-chatbot-tab-pages';
    }

    public function register() {
        $page_id = $this->get_page_id();

        add_settings_section(
            'humata_trigger_pages_section',
            __( 'Trigger Pages', 'humata-chatbot' ),
            array( $this->admin, 'render_trigger_pages_section' ),
            $page_id
        );

        add_settings_field(
            'humata_trigger_pages',
            __( 'Pages', 'humata-chatbot' ),
            array( $this->admin, 'render_trigger_pages_field' ),
            $page_id,
            'humata_trigger_pages_section'
        );
    }
}
