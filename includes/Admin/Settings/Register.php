<?php
/**
 * Settings Registration
 *
 * Contains the Settings API registration for plugin options.
 *
 * @package Humata_Chatbot
 * @since 1.0.0
 */

defined( 'ABSPATH' ) || exit;

trait Humata_Chatbot_Admin_Settings_Register_Trait {

    /**
     * Register plugin settings.
     *
     * @since 1.0.0
     * @return void
     */
    public function register_settings() {
        // Register settings
        register_setting(
            'humata_chatbot_settings',
            'humata_api_key',
            array(
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default'           => '',
            )
        );

        register_setting(
            'humata_chatbot_settings',
            'humata_document_ids',
            array(
                'type'              => 'string',
                'sanitize_callback' => array( $this, 'sanitize_document_ids' ),
                'default'           => '',
            )
        );

        register_setting(
            'humata_chatbot_settings',
            'humata_chat_location',
            array(
                'type'              => 'string',
                'sanitize_callback' => array( $this, 'sanitize_location' ),
                'default'           => 'dedicated',
            )
        );

        register_setting(
            'humata_chatbot_settings',
            'humata_chat_page_slug',
            array(
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_title',
                'default'           => 'chat',
            )
        );

        register_setting(
            'humata_chatbot_settings',
            'humata_chat_theme',
            array(
                'type'              => 'string',
                'sanitize_callback' => array( $this, 'sanitize_theme' ),
                'default'           => 'auto',
            )
        );

        register_setting(
            'humata_chatbot_settings',
            'humata_allow_seo_indexing',
            array(
                'type'              => 'boolean',
                'sanitize_callback' => 'rest_sanitize_boolean',
                'default'           => false,
            )
        );

        register_setting(
            'humata_chatbot_settings',
            'humata_system_prompt',
            array(
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_textarea_field',
                'default'           => '',
            )
        );

        register_setting(
            'humata_chatbot_settings',
            'humata_rate_limit',
            array(
                'type'              => 'integer',
                'sanitize_callback' => 'absint',
                'default'           => 50,
            )
        );

        register_setting(
            'humata_chatbot_settings',
            'humata_max_prompt_chars',
            array(
                'type'              => 'integer',
                'sanitize_callback' => array( $this, 'sanitize_max_prompt_chars' ),
                'default'           => 3000,
            )
        );

        register_setting(
            'humata_chatbot_settings',
            'humata_medical_disclaimer_text',
            array(
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_textarea_field',
                'default'           => '',
            )
        );

        register_setting(
            'humata_chatbot_settings',
            'humata_footer_copyright_text',
            array(
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default'           => '',
            )
        );

        register_setting(
            'humata_chatbot_settings',
            'humata_bot_response_disclaimer',
            array(
                'type'              => 'string',
                'sanitize_callback' => array( $this, 'sanitize_bot_response_disclaimer' ),
                'default'           => '',
            )
        );

        register_setting(
            'humata_chatbot_settings',
            'humata_second_llm_provider',
            array(
                'type'              => 'string',
                'sanitize_callback' => array( $this, 'sanitize_second_llm_provider' ),
                'default'           => 'none',
            )
        );

        register_setting(
            'humata_chatbot_settings',
            'humata_search_provider',
            array(
                'type'              => 'string',
                'sanitize_callback' => array( $this, 'sanitize_search_provider' ),
                'default'           => 'humata',
            )
        );

        register_setting(
            'humata_chatbot_settings',
            'humata_straico_review_enabled',
            array(
                'type'              => 'integer',
                'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
                'default'           => 0,
            )
        );

        register_setting(
            'humata_chatbot_settings',
            'humata_straico_api_key',
            array(
                'type'              => 'array',
                'sanitize_callback' => array( $this, 'sanitize_api_key_pool' ),
                'default'           => array(),
            )
        );

        register_setting(
            'humata_chatbot_settings',
            'humata_straico_model',
            array(
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default'           => '',
            )
        );

        register_setting(
            'humata_chatbot_settings',
            'humata_straico_system_prompt',
            array(
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_textarea_field',
                'default'           => '',
            )
        );

        register_setting(
            'humata_chatbot_settings',
            'humata_local_search_system_prompt',
            array(
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_textarea_field',
                'default'           => '',
            )
        );

        register_setting(
            'humata_chatbot_settings',
            'humata_anthropic_api_key',
            array(
                'type'              => 'array',
                'sanitize_callback' => array( $this, 'sanitize_api_key_pool' ),
                'default'           => array(),
            )
        );

        register_setting(
            'humata_chatbot_settings',
            'humata_anthropic_model',
            array(
                'type'              => 'string',
                'sanitize_callback' => array( $this, 'sanitize_anthropic_model' ),
                'default'           => 'claude-3-5-sonnet-20241022',
            )
        );

        register_setting(
            'humata_chatbot_settings',
            'humata_anthropic_extended_thinking',
            array(
                'type'              => 'integer',
                'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
                'default'           => 0,
            )
        );

        // Local Search Mode first-stage provider settings.
        register_setting(
            'humata_chatbot_settings',
            'humata_local_first_llm_provider',
            array(
                'type'              => 'string',
                'sanitize_callback' => array( $this, 'sanitize_local_first_llm_provider' ),
                'default'           => 'straico',
            )
        );

        register_setting(
            'humata_chatbot_settings',
            'humata_local_first_straico_api_key',
            array(
                'type'              => 'array',
                'sanitize_callback' => array( $this, 'sanitize_api_key_pool' ),
                'default'           => array(),
            )
        );

        register_setting(
            'humata_chatbot_settings',
            'humata_local_first_straico_model',
            array(
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default'           => '',
            )
        );

        register_setting(
            'humata_chatbot_settings',
            'humata_local_first_anthropic_api_key',
            array(
                'type'              => 'array',
                'sanitize_callback' => array( $this, 'sanitize_api_key_pool' ),
                'default'           => array(),
            )
        );

        register_setting(
            'humata_chatbot_settings',
            'humata_local_first_anthropic_model',
            array(
                'type'              => 'string',
                'sanitize_callback' => array( $this, 'sanitize_anthropic_model' ),
                'default'           => 'claude-3-5-sonnet-20241022',
            )
        );

        register_setting(
            'humata_chatbot_settings',
            'humata_local_first_anthropic_extended_thinking',
            array(
                'type'              => 'integer',
                'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
                'default'           => 0,
            )
        );

        // Local Search Mode second-stage provider settings.
        register_setting(
            'humata_chatbot_settings',
            'humata_local_second_llm_provider',
            array(
                'type'              => 'string',
                'sanitize_callback' => array( $this, 'sanitize_local_second_llm_provider' ),
                'default'           => 'none',
            )
        );

        register_setting(
            'humata_chatbot_settings',
            'humata_local_second_straico_api_key',
            array(
                'type'              => 'array',
                'sanitize_callback' => array( $this, 'sanitize_api_key_pool' ),
                'default'           => array(),
            )
        );

        register_setting(
            'humata_chatbot_settings',
            'humata_local_second_straico_model',
            array(
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default'           => '',
            )
        );

        register_setting(
            'humata_chatbot_settings',
            'humata_local_second_anthropic_api_key',
            array(
                'type'              => 'array',
                'sanitize_callback' => array( $this, 'sanitize_api_key_pool' ),
                'default'           => array(),
            )
        );

        register_setting(
            'humata_chatbot_settings',
            'humata_local_second_anthropic_model',
            array(
                'type'              => 'string',
                'sanitize_callback' => array( $this, 'sanitize_anthropic_model' ),
                'default'           => 'claude-3-5-sonnet-20241022',
            )
        );

        register_setting(
            'humata_chatbot_settings',
            'humata_local_second_anthropic_extended_thinking',
            array(
                'type'              => 'integer',
                'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
                'default'           => 0,
            )
        );

        // Local Search Mode second-stage system prompt.
        register_setting(
            'humata_chatbot_settings',
            'humata_local_second_stage_system_prompt',
            array(
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_textarea_field',
                'default'           => '',
            )
        );

        register_setting(
            'humata_chatbot_settings',
            'humata_floating_help',
            array(
                'type'              => 'array',
                'sanitize_callback' => array( $this, 'sanitize_floating_help' ),
                'default'           => self::get_default_floating_help_option(),
            )
        );

        register_setting(
            'humata_chatbot_settings',
            'humata_auto_links',
            array(
                'type'              => 'array',
                'sanitize_callback' => array( $this, 'sanitize_auto_links' ),
                'default'           => array(),
            )
        );

        register_setting(
            'humata_chatbot_settings',
            'humata_intent_links',
            array(
                'type'              => 'array',
                'sanitize_callback' => array( $this, 'sanitize_intent_links' ),
                'default'           => array(),
            )
        );

        register_setting(
            'humata_chatbot_settings',
            'humata_logo_url',
            array(
                'type'              => 'string',
                'sanitize_callback' => 'esc_url_raw',
                'default'           => '',
            )
        );

        register_setting(
            'humata_chatbot_settings',
            'humata_logo_url_dark',
            array(
                'type'              => 'string',
                'sanitize_callback' => 'esc_url_raw',
                'default'           => '',
            )
        );

        register_setting(
            'humata_chatbot_settings',
            'humata_user_avatar_url',
            array(
                'type'              => 'string',
                'sanitize_callback' => 'esc_url_raw',
                'default'           => '',
            )
        );

        register_setting(
            'humata_chatbot_settings',
            'humata_bot_avatar_url',
            array(
                'type'              => 'string',
                'sanitize_callback' => 'esc_url_raw',
                'default'           => '',
            )
        );

        register_setting(
            'humata_chatbot_settings',
            'humata_avatar_size',
            array(
                'type'              => 'integer',
                'sanitize_callback' => array( $this, 'sanitize_avatar_size' ),
                'default'           => 40,
            )
        );

        // Cloudflare Turnstile settings.
        register_setting(
            'humata_chatbot_settings',
            'humata_turnstile_enabled',
            array(
                'type'              => 'integer',
                'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
                'default'           => 0,
            )
        );

        register_setting(
            'humata_chatbot_settings',
            'humata_turnstile_site_key',
            array(
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default'           => '',
            )
        );

        register_setting(
            'humata_chatbot_settings',
            'humata_turnstile_secret_key',
            array(
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default'           => '',
            )
        );

        register_setting(
            'humata_chatbot_settings',
            'humata_turnstile_appearance',
            array(
                'type'              => 'string',
                'sanitize_callback' => array( $this, 'sanitize_turnstile_appearance' ),
                'default'           => 'managed',
            )
        );

        register_setting(
            'humata_chatbot_settings',
            'humata_trigger_pages',
            array(
                'type'              => 'array',
                'sanitize_callback' => array( $this, 'sanitize_trigger_pages' ),
                'default'           => array(),
            )
        );

        register_setting(
            'humata_chatbot_settings',
            'humata_suggested_questions',
            array(
                'type'              => 'array',
                'sanitize_callback' => array( $this, 'sanitize_suggested_questions' ),
                'default'           => array(
                    'enabled'         => false,
                    'mode'            => 'fixed',
                    'fixed_questions' => array(),
                    'categories'      => array(),
                ),
            )
        );

        // Register per-tab sections/fields.
        foreach ( $this->get_tab_modules() as $module ) {
            if ( is_object( $module ) && method_exists( $module, 'register' ) ) {
                $module->register();
            }
        }
    }
}









