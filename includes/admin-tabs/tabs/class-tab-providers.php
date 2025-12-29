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

        // =====================================================
        // HUMATA MODE - Second LLM Processing
        // =====================================================
        add_settings_section(
            'humata_straico_section',
            __( 'Second LLM Processing (Humata Mode)', 'humata-chatbot' ),
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
            'humata_openrouter_api_key',
            __( 'OpenRouter API Key', 'humata-chatbot' ),
            array( $this->admin, 'render_openrouter_api_key_field' ),
            $page_id,
            'humata_straico_section'
        );

        add_settings_field(
            'humata_openrouter_model',
            __( 'OpenRouter Model', 'humata-chatbot' ),
            array( $this->admin, 'render_openrouter_model_field' ),
            $page_id,
            'humata_straico_section'
        );

        // =====================================================
        // LOCAL SEARCH MODE - First-Stage LLM
        // =====================================================
        add_settings_section(
            'humata_local_first_section',
            __( 'First-Stage LLM (Local Search Mode)', 'humata-chatbot' ),
            array( $this->admin, 'render_local_first_section' ),
            $page_id
        );

        add_settings_field(
            'humata_local_first_llm_provider',
            __( 'First-stage Provider', 'humata-chatbot' ),
            array( $this->admin, 'render_local_first_llm_provider_field' ),
            $page_id,
            'humata_local_first_section'
        );

        add_settings_field(
            'humata_local_first_straico_api_key',
            __( 'Straico API Key', 'humata-chatbot' ),
            array( $this->admin, 'render_local_first_straico_api_key_field' ),
            $page_id,
            'humata_local_first_section'
        );

        add_settings_field(
            'humata_local_first_straico_model',
            __( 'Straico Model', 'humata-chatbot' ),
            array( $this->admin, 'render_local_first_straico_model_field' ),
            $page_id,
            'humata_local_first_section'
        );

        add_settings_field(
            'humata_local_first_anthropic_api_key',
            __( 'Anthropic API Key', 'humata-chatbot' ),
            array( $this->admin, 'render_local_first_anthropic_api_key_field' ),
            $page_id,
            'humata_local_first_section'
        );

        add_settings_field(
            'humata_local_first_anthropic_model',
            __( 'Claude Model Selection', 'humata-chatbot' ),
            array( $this->admin, 'render_local_first_anthropic_model_field' ),
            $page_id,
            'humata_local_first_section'
        );

        add_settings_field(
            'humata_local_first_anthropic_extended_thinking',
            __( 'Extended Thinking', 'humata-chatbot' ),
            array( $this->admin, 'render_local_first_anthropic_extended_thinking_field' ),
            $page_id,
            'humata_local_first_section'
        );

        add_settings_field(
            'humata_local_first_openrouter_api_key',
            __( 'OpenRouter API Key', 'humata-chatbot' ),
            array( $this->admin, 'render_local_first_openrouter_api_key_field' ),
            $page_id,
            'humata_local_first_section'
        );

        add_settings_field(
            'humata_local_first_openrouter_model',
            __( 'OpenRouter Model', 'humata-chatbot' ),
            array( $this->admin, 'render_local_first_openrouter_model_field' ),
            $page_id,
            'humata_local_first_section'
        );

        // =====================================================
        // LOCAL SEARCH MODE - Second-Stage LLM
        // =====================================================
        add_settings_section(
            'humata_local_second_section',
            __( 'Second-Stage LLM (Local Search Mode)', 'humata-chatbot' ),
            array( $this->admin, 'render_local_second_section' ),
            $page_id
        );

        add_settings_field(
            'humata_local_second_llm_provider',
            __( 'Second-stage Provider', 'humata-chatbot' ),
            array( $this->admin, 'render_local_second_llm_provider_field' ),
            $page_id,
            'humata_local_second_section'
        );

        add_settings_field(
            'humata_local_second_straico_api_key',
            __( 'Straico API Key', 'humata-chatbot' ),
            array( $this->admin, 'render_local_second_straico_api_key_field' ),
            $page_id,
            'humata_local_second_section'
        );

        add_settings_field(
            'humata_local_second_straico_model',
            __( 'Straico Model', 'humata-chatbot' ),
            array( $this->admin, 'render_local_second_straico_model_field' ),
            $page_id,
            'humata_local_second_section'
        );

        add_settings_field(
            'humata_local_second_anthropic_api_key',
            __( 'Anthropic API Key', 'humata-chatbot' ),
            array( $this->admin, 'render_local_second_anthropic_api_key_field' ),
            $page_id,
            'humata_local_second_section'
        );

        add_settings_field(
            'humata_local_second_anthropic_model',
            __( 'Claude Model Selection', 'humata-chatbot' ),
            array( $this->admin, 'render_local_second_anthropic_model_field' ),
            $page_id,
            'humata_local_second_section'
        );

        add_settings_field(
            'humata_local_second_anthropic_extended_thinking',
            __( 'Extended Thinking', 'humata-chatbot' ),
            array( $this->admin, 'render_local_second_anthropic_extended_thinking_field' ),
            $page_id,
            'humata_local_second_section'
        );

        add_settings_field(
            'humata_local_second_openrouter_api_key',
            __( 'OpenRouter API Key', 'humata-chatbot' ),
            array( $this->admin, 'render_local_second_openrouter_api_key_field' ),
            $page_id,
            'humata_local_second_section'
        );

        add_settings_field(
            'humata_local_second_openrouter_model',
            __( 'OpenRouter Model', 'humata-chatbot' ),
            array( $this->admin, 'render_local_second_openrouter_model_field' ),
            $page_id,
            'humata_local_second_section'
        );
    }
}


