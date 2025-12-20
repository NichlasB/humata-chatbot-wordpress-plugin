<?php
/**
 * General Settings Tab
 *
 * @package Humata_Chatbot
 * @since 1.0.0
 */
defined( 'ABSPATH' ) || exit;

class Humata_Chatbot_Settings_Tab_General extends Humata_Chatbot_Settings_Tab_Base {

    public function get_key() {
        return 'general';
    }

    public function get_label() {
        return __( 'General', 'humata-chatbot' );
    }

    public function get_page_id() {
        return 'humata-chatbot-tab-general';
    }

    public function register() {
        $page_id = $this->get_page_id();

        // API Settings Section.
        add_settings_section(
            'humata_api_section',
            __( 'API Configuration', 'humata-chatbot' ),
            array( $this->admin, 'render_api_section' ),
            $page_id
        );

        add_settings_field(
            'humata_api_key',
            __( 'API Key', 'humata-chatbot' ),
            array( $this->admin, 'render_api_key_field' ),
            $page_id,
            'humata_api_section'
        );

        add_settings_field(
            'humata_document_ids',
            __( 'Document IDs', 'humata-chatbot' ),
            array( $this->admin, 'render_document_ids_field' ),
            $page_id,
            'humata_api_section'
        );

        // System Prompt Section.
        add_settings_section(
            'humata_prompt_section',
            __( 'System Prompt', 'humata-chatbot' ),
            array( $this->admin, 'render_prompt_section' ),
            $page_id
        );

        add_settings_field(
            'humata_system_prompt',
            __( 'System Prompt', 'humata-chatbot' ),
            array( $this->admin, 'render_system_prompt_field' ),
            $page_id,
            'humata_prompt_section'
        );
    }
}


