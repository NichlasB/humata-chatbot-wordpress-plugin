<?php
/**
 * Display Settings Tab
 *
 * @package Humata_Chatbot
 * @since 1.0.0
 */
defined( 'ABSPATH' ) || exit;

class Humata_Chatbot_Settings_Tab_Display extends Humata_Chatbot_Settings_Tab_Base {

    public function get_key() {
        return 'display';
    }

    public function get_label() {
        return __( 'Display', 'humata-chatbot' );
    }

    public function get_page_id() {
        return 'humata-chatbot-tab-display';
    }

    public function register() {
        $page_id = $this->get_page_id();

        // Display Settings Section.
        add_settings_section(
            'humata_display_section',
            __( 'Display Settings', 'humata-chatbot' ),
            array( $this->admin, 'render_display_section' ),
            $page_id
        );

        add_settings_field(
            'humata_chat_location',
            __( 'Display Location', 'humata-chatbot' ),
            array( $this->admin, 'render_location_field' ),
            $page_id,
            'humata_display_section'
        );

        add_settings_field(
            'humata_chat_theme',
            __( 'Interface Theme', 'humata-chatbot' ),
            array( $this->admin, 'render_theme_field' ),
            $page_id,
            'humata_display_section'
        );

        // Disclaimers Section.
        add_settings_section(
            'humata_disclaimer_section',
            __( 'Disclaimers', 'humata-chatbot' ),
            array( $this->admin, 'render_disclaimer_section' ),
            $page_id
        );

        add_settings_field(
            'humata_medical_disclaimer_text',
            __( 'Medical Disclaimer', 'humata-chatbot' ),
            array( $this->admin, 'render_medical_disclaimer_text_field' ),
            $page_id,
            'humata_disclaimer_section'
        );

        add_settings_field(
            'humata_footer_copyright_text',
            __( 'Footer Copyright Text', 'humata-chatbot' ),
            array( $this->admin, 'render_footer_copyright_text_field' ),
            $page_id,
            'humata_disclaimer_section'
        );
    }
}


