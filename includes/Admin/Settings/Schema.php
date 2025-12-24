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
            ),
            'providers'     => array(
                'humata_second_llm_provider',
                'humata_straico_api_key',
                'humata_straico_model',
                'humata_anthropic_api_key',
                'humata_anthropic_model',
                'humata_anthropic_extended_thinking',
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
                'humata_turnstile_enabled',
                'humata_turnstile_site_key',
                'humata_turnstile_secret_key',
                'humata_turnstile_appearance',
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






