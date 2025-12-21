<?php
/**
 * Auto-Links Settings Tab
 *
 * @package Humata_Chatbot
 * @since 1.0.0
 */
defined( 'ABSPATH' ) || exit;

class Humata_Chatbot_Settings_Tab_Auto_Links extends Humata_Chatbot_Settings_Tab_Base {

    public function get_key() {
        return 'auto_links';
    }

    public function get_label() {
        return __( 'Auto-Links', 'humata-chatbot' );
    }

    public function get_page_id() {
        return 'humata-chatbot-tab-auto-links';
    }

    public function register() {
        $page_id = $this->get_page_id();

        add_settings_section(
            'humata_auto_links_section',
            __( 'Inline Auto-Links', 'humata-chatbot' ),
            array( $this->admin, 'render_auto_links_section' ),
            $page_id
        );

        add_settings_field(
            'humata_auto_links',
            __( 'Rules', 'humata-chatbot' ),
            array( $this->admin, 'render_auto_links_field' ),
            $page_id,
            'humata_auto_links_section'
        );

        add_settings_section(
            'humata_intent_links_section',
            __( 'Intent-Based Resource Links', 'humata-chatbot' ),
            array( $this->admin, 'render_intent_links_section' ),
            $page_id
        );

        add_settings_field(
            'humata_intent_links',
            __( 'Intents', 'humata-chatbot' ),
            array( $this->admin, 'render_intent_links_field' ),
            $page_id,
            'humata_intent_links_section'
        );
    }
}


