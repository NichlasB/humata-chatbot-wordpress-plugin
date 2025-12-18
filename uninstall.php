<?php
/**
 * Uninstall Script
 *
 * Removes all plugin data when the plugin is deleted.
 *
 * @package Humata_Chatbot
 * @since 1.0.0
 */

// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Delete plugin options
delete_option( 'humata_api_key' );
delete_option( 'humata_document_ids' );
delete_option( 'humata_document_titles' );
delete_option( 'humata_folder_id' ); // Legacy option
delete_option( 'humata_chat_location' );
delete_option( 'humata_chat_page_slug' );
delete_option( 'humata_chat_theme' );
delete_option( 'humata_system_prompt' );
delete_option( 'humata_rate_limit' );
delete_option( 'humata_max_prompt_chars' );
delete_option( 'humata_medical_disclaimer_text' );
delete_option( 'humata_second_llm_provider' );
delete_option( 'humata_straico_review_enabled' );
delete_option( 'humata_straico_api_key' );
delete_option( 'humata_straico_model' );
delete_option( 'humata_straico_system_prompt' );
delete_option( 'humata_anthropic_api_key' );
delete_option( 'humata_anthropic_model' );
delete_option( 'humata_anthropic_extended_thinking' );

// Delete rate limit and conversation transients
global $wpdb;
$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s",
        '_transient_humata_rate_limit_%',
        '_transient_timeout_humata_rate_limit_%',
        '_transient_humata_conversation_%',
        '_transient_timeout_humata_conversation_%'
    )
);

// Flush rewrite rules to remove custom endpoints
flush_rewrite_rules();
