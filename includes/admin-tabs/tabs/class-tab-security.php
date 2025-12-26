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

        // Bot Protection Section.
        add_settings_section(
            'humata_bot_protection_section',
            __( 'Bot Protection', 'humata-chatbot' ),
            array( $this->admin, 'render_bot_protection_section' ),
            $page_id
        );

        add_settings_field(
            'humata_bot_protection_enabled',
            __( 'Enable Bot Protection', 'humata-chatbot' ),
            array( $this->admin, 'render_bot_protection_enabled_field' ),
            $page_id,
            'humata_bot_protection_section'
        );

        add_settings_field(
            'humata_honeypot_enabled',
            __( 'Honeypot Fields', 'humata-chatbot' ),
            array( $this->admin, 'render_honeypot_enabled_field' ),
            $page_id,
            'humata_bot_protection_section'
        );

        add_settings_field(
            'humata_pow_enabled',
            __( 'Proof-of-Work Challenge', 'humata-chatbot' ),
            array( $this->admin, 'render_pow_enabled_field' ),
            $page_id,
            'humata_bot_protection_section'
        );

        add_settings_field(
            'humata_pow_difficulty',
            __( 'PoW Difficulty', 'humata-chatbot' ),
            array( $this->admin, 'render_pow_difficulty_field' ),
            $page_id,
            'humata_bot_protection_section'
        );

        // Progressive Delays Section.
        add_settings_section(
            'humata_progressive_delays_section',
            __( 'Progressive Delays', 'humata-chatbot' ),
            array( $this->admin, 'render_progressive_delays_section' ),
            $page_id
        );

        add_settings_field(
            'humata_progressive_delays_enabled',
            __( 'Enable Progressive Delays', 'humata-chatbot' ),
            array( $this->admin, 'render_progressive_delays_enabled_field' ),
            $page_id,
            'humata_progressive_delays_section'
        );

        add_settings_field(
            'humata_delay_threshold_1',
            __( 'Threshold 1', 'humata-chatbot' ),
            array( $this->admin, 'render_delay_threshold_1_field' ),
            $page_id,
            'humata_progressive_delays_section'
        );

        add_settings_field(
            'humata_delay_threshold_2',
            __( 'Threshold 2', 'humata-chatbot' ),
            array( $this->admin, 'render_delay_threshold_2_field' ),
            $page_id,
            'humata_progressive_delays_section'
        );

        add_settings_field(
            'humata_delay_threshold_3',
            __( 'Threshold 3', 'humata-chatbot' ),
            array( $this->admin, 'render_delay_threshold_3_field' ),
            $page_id,
            'humata_progressive_delays_section'
        );

        add_settings_field(
            'humata_delay_cooldown_minutes',
            __( 'Cooldown Period', 'humata-chatbot' ),
            array( $this->admin, 'render_delay_cooldown_minutes_field' ),
            $page_id,
            'humata_progressive_delays_section'
        );
    }
}


