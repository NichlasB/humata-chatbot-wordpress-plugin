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

        // Cloudflare Turnstile Section.
        add_settings_section(
            'humata_turnstile_section',
            __( 'Cloudflare Turnstile', 'humata-chatbot' ),
            array( $this->admin, 'render_turnstile_section' ),
            $page_id
        );

        add_settings_field(
            'humata_turnstile_enabled',
            __( 'Enable Turnstile', 'humata-chatbot' ),
            array( $this->admin, 'render_turnstile_enabled_field' ),
            $page_id,
            'humata_turnstile_section'
        );

        add_settings_field(
            'humata_turnstile_site_key',
            __( 'Site Key', 'humata-chatbot' ),
            array( $this->admin, 'render_turnstile_site_key_field' ),
            $page_id,
            'humata_turnstile_section'
        );

        add_settings_field(
            'humata_turnstile_secret_key',
            __( 'Secret Key', 'humata-chatbot' ),
            array( $this->admin, 'render_turnstile_secret_key_field' ),
            $page_id,
            'humata_turnstile_section'
        );

        add_settings_field(
            'humata_turnstile_appearance',
            __( 'Widget Appearance', 'humata-chatbot' ),
            array( $this->admin, 'render_turnstile_appearance_field' ),
            $page_id,
            'humata_turnstile_section'
        );
    }
}


