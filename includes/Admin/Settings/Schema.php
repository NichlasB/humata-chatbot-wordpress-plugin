<?php
/**
 * Settings Schema
 *
 * Centralizes option groupings used by the tabbed Settings API UI.
 *
 * @package Humata_Chatbot
 * @since 1.0.0
 */

defined( 'ABSPATH' ) || exit;

final class Humata_Chatbot_Admin_Settings_Schema {

    /**
     * Map of tab key => list of option names.
     *
     * @since 1.0.0
     * @return array<string, array<int, string>>
     */
    public static function get_tab_to_options() {
        return array(
            'general'       => array(
                'humata_search_provider',
                'humata_api_key',
                'humata_document_ids',
                'humata_system_prompt',
                'humata_straico_system_prompt',
                'humata_local_search_system_prompt',
                'humata_local_second_stage_system_prompt',
            ),
            'providers'     => array(
                // Humata Mode second-stage.
                'humata_second_llm_provider',
                'humata_straico_api_key',
                'humata_straico_model',
                'humata_anthropic_api_key',
                'humata_anthropic_model',
                'humata_anthropic_extended_thinking',
                'humata_openrouter_api_key',
                'humata_openrouter_model',
                // Local Search Mode first-stage.
                'humata_local_first_llm_provider',
                'humata_local_first_straico_api_key',
                'humata_local_first_straico_model',
                'humata_local_first_anthropic_api_key',
                'humata_local_first_anthropic_model',
                'humata_local_first_anthropic_extended_thinking',
                'humata_local_first_openrouter_api_key',
                'humata_local_first_openrouter_model',
                // Local Search Mode second-stage.
                'humata_local_second_llm_provider',
                'humata_local_second_straico_api_key',
                'humata_local_second_straico_model',
                'humata_local_second_anthropic_api_key',
                'humata_local_second_anthropic_model',
                'humata_local_second_anthropic_extended_thinking',
                'humata_local_second_openrouter_api_key',
                'humata_local_second_openrouter_model',
            ),
            'display'       => array(
                'humata_chat_location',
                'humata_chat_page_slug',
                'humata_chat_theme',
                'humata_logo_url',
                'humata_logo_url_dark',
                'humata_medical_disclaimer_text',
                'humata_footer_copyright_text',
                'humata_bot_response_disclaimer',
                'humata_user_avatar_url',
                'humata_bot_avatar_url',
                'humata_avatar_size',
            ),
            'security'      => array(
                'humata_max_prompt_chars',
                'humata_rate_limit',
                'humata_trusted_proxies',
                'humata_security_headers_enabled',
                'humata_bot_protection_enabled',
                'humata_honeypot_enabled',
                'humata_pow_enabled',
                'humata_pow_difficulty',
                'humata_progressive_delays_enabled',
                'humata_delay_threshold_1_count',
                'humata_delay_threshold_1_delay',
                'humata_delay_threshold_2_count',
                'humata_delay_threshold_2_delay',
                'humata_delay_threshold_3_count',
                'humata_delay_threshold_3_delay',
                'humata_delay_cooldown_minutes',
            ),
            'floating_help' => array(
                'humata_floating_help',
            ),
            'auto_links'    => array(
                'humata_auto_links',
                'humata_intent_links',
            ),
            'pages'         => array(
                'humata_trigger_pages',
            ),
            'suggested_questions' => array(
                'humata_suggested_questions',
                'humata_followup_questions',
            ),
        );
    }

    /**
     * Get option list for a tab key.
     *
     * @since 1.0.0
     * @param string $tab Tab key.
     * @return array<int, string>
     */
    public static function get_options_for_tab( $tab ) {
        $tab = sanitize_key( (string) $tab );
        $map = self::get_tab_to_options();
        return isset( $map[ $tab ] ) ? (array) $map[ $tab ] : array();
    }

    /**
     * Whether an option belongs to a tab.
     *
     * @since 1.0.0
     * @param string $tab Tab key.
     * @param string $option Option name.
     * @return bool
     */
    public static function tab_has_option( $tab, $option ) {
        $option = (string) $option;
        return in_array( $option, self::get_options_for_tab( $tab ), true );
    }
}














