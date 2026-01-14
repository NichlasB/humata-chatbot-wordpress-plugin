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

        add_settings_field(
            'humata_allow_seo_indexing',
            __( 'Search Engine Indexing', 'humata-chatbot' ),
            array( $this->admin, 'render_allow_seo_indexing_field' ),
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

        add_settings_field(
            'humata_bot_response_disclaimer',
            __( 'Bot Response Disclaimer', 'humata-chatbot' ),
            array( $this->admin, 'render_bot_response_disclaimer_field' ),
            $page_id,
            'humata_disclaimer_section'
        );

        // Logo Settings Section.
        add_settings_section(
            'humata_logo_section',
            __( 'Logo Settings', 'humata-chatbot' ),
            array( $this->admin, 'render_logo_section' ),
            $page_id
        );

        add_settings_field(
            'humata_logo_url',
            __( 'Logo (Light Theme)', 'humata-chatbot' ),
            array( $this->admin, 'render_logo_field' ),
            $page_id,
            'humata_logo_section'
        );

        add_settings_field(
            'humata_logo_url_dark',
            __( 'Logo (Dark Theme)', 'humata-chatbot' ),
            array( $this->admin, 'render_logo_dark_field' ),
            $page_id,
            'humata_logo_section'
        );

        // Avatar Settings Section.
        add_settings_section(
            'humata_avatar_section',
            __( 'Avatar Settings', 'humata-chatbot' ),
            array( $this->admin, 'render_avatar_section' ),
            $page_id
        );

        add_settings_field(
            'humata_user_avatar_url',
            __( 'User Avatar Image', 'humata-chatbot' ),
            array( $this->admin, 'render_user_avatar_field' ),
            $page_id,
            'humata_avatar_section'
        );

        add_settings_field(
            'humata_bot_avatar_url',
            __( 'Bot Avatar Image', 'humata-chatbot' ),
            array( $this->admin, 'render_bot_avatar_field' ),
            $page_id,
            'humata_avatar_section'
        );

        add_settings_field(
            'humata_avatar_size',
            __( 'Avatar Size (px)', 'humata-chatbot' ),
            array( $this->admin, 'render_avatar_size_field' ),
            $page_id,
            'humata_avatar_section'
        );

        // Typography Section.
        add_settings_section(
            'humata_typography_section',
            __( 'Typography', 'humata-chatbot' ),
            array( $this->admin, 'render_typography_section' ),
            $page_id
        );

        add_settings_field(
            'humata_typography',
            __( 'Custom Fonts', 'humata-chatbot' ),
            array( $this->admin, 'render_typography_field' ),
            $page_id,
            'humata_typography_section'
        );
    }
}


