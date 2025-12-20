<?php
/**
 * Providers Settings Tab
 *
 * @package Humata_Chatbot
 * @since 1.0.0
 */
defined( 'ABSPATH' ) || exit;

class Humata_Chatbot_Settings_Tab_Providers extends Humata_Chatbot_Settings_Tab_Base {

    public function get_key() {
        return 'providers';
    }

    public function get_label() {
        return __( 'Providers', 'humata-chatbot' );
    }

    public function get_page_id() {
        return 'humata-chatbot-tab-providers';
    }

    public function register() {
        $page_id = $this->get_page_id();

        // Second LLM Processing Settings Section.
        add_settings_section(
            'humata_straico_section',
            __( 'Second LLM Processing', 'humata-chatbot' ),
            array( $this->admin, 'render_straico_section' ),
            $page_id
        );

        add_settings_field(
            'humata_second_llm_provider',
            __( 'Second-stage Provider', 'humata-chatbot' ),
            array( $this->admin, 'render_second_llm_provider_field' ),
            $page_id,
            'humata_straico_section'
        );

        add_settings_field(
            'humata_straico_api_key',
            __( 'Straico API Key', 'humata-chatbot' ),
            array( $this->admin, 'render_straico_api_key_field' ),
            $page_id,
            'humata_straico_section'
        );

        add_settings_field(
            'humata_straico_model',
            __( 'Straico Model', 'humata-chatbot' ),
            array( $this->admin, 'render_straico_model_field' ),
            $page_id,
            'humata_straico_section'
        );

        add_settings_field(
            'humata_anthropic_api_key',
            __( 'Anthropic API Key', 'humata-chatbot' ),
            array( $this->admin, 'render_anthropic_api_key_field' ),
            $page_id,
            'humata_straico_section'
        );

        add_settings_field(
            'humata_anthropic_model',
            __( 'Claude Model Selection', 'humata-chatbot' ),
            array( $this->admin, 'render_anthropic_model_field' ),
            $page_id,
            'humata_straico_section'
        );

        add_settings_field(
            'humata_anthropic_extended_thinking',
            __( 'Extended Thinking', 'humata-chatbot' ),
            array( $this->admin, 'render_anthropic_extended_thinking_field' ),
            $page_id,
            'humata_straico_section'
        );

        add_settings_field(
            'humata_straico_system_prompt',
            __( 'System Prompt', 'humata-chatbot' ),
            array( $this->admin, 'render_straico_system_prompt_field' ),
            $page_id,
            'humata_straico_section'
        );
    }
}


