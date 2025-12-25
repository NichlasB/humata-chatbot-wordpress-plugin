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

        // Search Provider Section.
        add_settings_section(
            'humata_search_provider_section',
            __( 'Search Provider', 'humata-chatbot' ),
            array( $this->admin, 'render_search_provider_section' ),
            $page_id
        );

        add_settings_field(
            'humata_search_provider',
            __( 'Search Provider', 'humata-chatbot' ),
            array( $this->admin, 'render_search_provider_field' ),
            $page_id,
            'humata_search_provider_section'
        );

        // =====================================================
        // HUMATA API SECTIONS (shown when Search Provider = Humata)
        // =====================================================

        // API Settings Section.
        add_settings_section(
            'humata_api_section',
            __( 'Humata API Configuration', 'humata-chatbot' ),
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

        // Humata System Prompt Section.
        add_settings_section(
            'humata_prompt_section',
            __( 'Humata System Prompt', 'humata-chatbot' ),
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

        // Second-Stage LLM System Prompt Section (Humata only).
        add_settings_section(
            'humata_second_stage_prompt_section',
            __( 'Second-Stage LLM System Prompt', 'humata-chatbot' ),
            array( $this->admin, 'render_second_stage_prompt_section' ),
            $page_id
        );

        add_settings_field(
            'humata_straico_system_prompt',
            __( 'System Prompt', 'humata-chatbot' ),
            array( $this->admin, 'render_straico_system_prompt_field' ),
            $page_id,
            'humata_second_stage_prompt_section'
        );

        // =====================================================
        // LOCAL SEARCH SECTIONS (shown when Search Provider = Local)
        // =====================================================

        // Local Search System Prompt Section (First-Stage).
        add_settings_section(
            'humata_local_search_prompt_section',
            __( 'Local Search System Prompt (First-Stage)', 'humata-chatbot' ),
            array( $this->admin, 'render_local_search_prompt_section' ),
            $page_id
        );

        add_settings_field(
            'humata_local_search_system_prompt',
            __( 'System Prompt', 'humata-chatbot' ),
            array( $this->admin, 'render_local_search_system_prompt_field' ),
            $page_id,
            'humata_local_search_prompt_section'
        );

        // Local Search Second-Stage System Prompt Section.
        add_settings_section(
            'humata_local_second_stage_prompt_section',
            __( 'Local Search System Prompt (Second-Stage)', 'humata-chatbot' ),
            array( $this->admin, 'render_local_second_stage_prompt_section' ),
            $page_id
        );

        add_settings_field(
            'humata_local_second_stage_system_prompt',
            __( 'System Prompt', 'humata-chatbot' ),
            array( $this->admin, 'render_local_second_stage_system_prompt_field' ),
            $page_id,
            'humata_local_second_stage_prompt_section'
        );
    }
}


