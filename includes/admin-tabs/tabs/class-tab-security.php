<?php
/**
 * Security Settings Tab
 *
 * @package Humata_Chatbot
 * @since 1.0.0
 */
defined( 'ABSPATH' ) || exit;

class Humata_Chatbot_Settings_Tab_Security extends Humata_Chatbot_Settings_Tab_Base {

    public function get_key() {
        return 'security';
    }

    public function get_label() {
        return __( 'Security', 'humata-chatbot' );
    }

    public function get_page_id() {
        return 'humata-chatbot-tab-security';
    }

    public function register() {
        $page_id = $this->get_page_id();

        // Security Settings Section.
        add_settings_section(
            'humata_security_section',
            __( 'Security Settings', 'humata-chatbot' ),
            array( $this->admin, 'render_security_section' ),
            $page_id
        );

        add_settings_field(
            'humata_max_prompt_chars',
            __( 'Max Prompt Characters', 'humata-chatbot' ),
            array( $this->admin, 'render_max_prompt_chars_field' ),
            $page_id,
            'humata_security_section'
        );

        add_settings_field(
            'humata_rate_limit',
            __( 'Rate Limit', 'humata-chatbot' ),
            array( $this->admin, 'render_rate_limit_field' ),
            $page_id,
            'humata_security_section'
        );
    }
}


