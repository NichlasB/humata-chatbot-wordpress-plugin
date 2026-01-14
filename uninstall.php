<?php
/**
 * Uninstall Script
 *
 * Removes all plugin data when the plugin is deleted.
 *
 * @package Humata_Chatbot
 * @since 1.0.0
 */

// Security guards.
defined( 'ABSPATH' ) || exit;
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

/**
 * Safely delete a file and log failures when WP_DEBUG is enabled.
 *
 * @param string $file_path Absolute path to the file.
 * @return bool True on success, false on failure.
 */
function humata_uninstall_delete_file( $file_path ) {
    if ( ! file_exists( $file_path ) ) {
        return true;
    }
    $result = wp_delete_file( $file_path );
    // wp_delete_file returns void, check if file still exists.
    if ( file_exists( $file_path ) ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'Humata Chatbot uninstall: Failed to delete file: ' . $file_path );
        }
        return false;
    }
    return true;
}

/**
 * Safely delete a directory and log failures when WP_DEBUG is enabled.
 *
 * @param string $dir_path Absolute path to the directory.
 * @return bool True on success, false on failure.
 */
function humata_uninstall_delete_dir( $dir_path ) {
    if ( ! is_dir( $dir_path ) ) {
        return true;
    }
    $result = rmdir( $dir_path );
    if ( ! $result ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            error_log( 'Humata Chatbot uninstall: Failed to delete directory: ' . $dir_path );
        }
        return false;
    }
    return true;
}

// Delete plugin options
delete_option( 'humata_api_key' );
delete_option( 'humata_document_ids' );
delete_option( 'humata_document_titles' );
delete_option( 'humata_folder_id' ); // Legacy option
delete_option( 'humata_chat_location' );
delete_option( 'humata_chat_page_slug' );
delete_option( 'humata_chat_theme' );
delete_option( 'humata_allow_seo_indexing' );
delete_option( 'humata_system_prompt' );
delete_option( 'humata_rate_limit' );
delete_option( 'humata_max_prompt_chars' );
delete_option( 'humata_trusted_proxies' );
delete_option( 'humata_security_headers_enabled' );
delete_option( 'humata_medical_disclaimer_text' );
delete_option( 'humata_footer_copyright_text' );
delete_option( 'humata_bot_response_disclaimer' );
delete_option( 'humata_second_llm_provider' );
delete_option( 'humata_straico_review_enabled' );
delete_option( 'humata_straico_api_key' );
delete_option( 'humata_straico_model' );
delete_option( 'humata_straico_system_prompt' );
delete_option( 'humata_anthropic_api_key' );
delete_option( 'humata_anthropic_model' );
delete_option( 'humata_anthropic_extended_thinking' );
delete_option( 'humata_openrouter_api_key' );
delete_option( 'humata_openrouter_model' );
delete_option( 'humata_floating_help' );
delete_option( 'humata_auto_links' );
delete_option( 'humata_intent_links' );
delete_option( 'humata_logo_url' );
delete_option( 'humata_logo_url_dark' );
delete_option( 'humata_user_avatar_url' );
delete_option( 'humata_bot_avatar_url' );
delete_option( 'humata_avatar_size' );
delete_option( 'humata_typography' );
delete_option( 'humata_trigger_pages' );
delete_option( 'humata_suggested_questions' );
delete_option( 'humata_followup_questions' );

// Delete local search options.
delete_option( 'humata_search_provider' );
delete_option( 'humata_search_db_version' );
delete_option( 'humata_local_search_system_prompt' );
delete_option( 'humata_local_second_stage_system_prompt' );

// Delete Local Search first-stage provider options.
delete_option( 'humata_local_first_llm_provider' );
delete_option( 'humata_local_first_straico_api_key' );
delete_option( 'humata_local_first_straico_model' );
delete_option( 'humata_local_first_anthropic_api_key' );
delete_option( 'humata_local_first_anthropic_model' );
delete_option( 'humata_local_first_anthropic_extended_thinking' );
delete_option( 'humata_local_first_openrouter_api_key' );
delete_option( 'humata_local_first_openrouter_model' );

// Delete Local Search second-stage provider options.
delete_option( 'humata_local_second_llm_provider' );
delete_option( 'humata_local_second_straico_api_key' );
delete_option( 'humata_local_second_straico_model' );
delete_option( 'humata_local_second_anthropic_api_key' );
delete_option( 'humata_local_second_anthropic_model' );
delete_option( 'humata_local_second_anthropic_extended_thinking' );
delete_option( 'humata_local_second_openrouter_api_key' );
delete_option( 'humata_local_second_openrouter_model' );

// Delete API key rotation counter options.
delete_option( 'humata_straico_second_stage_key_index' );
delete_option( 'humata_anthropic_second_stage_key_index' );
delete_option( 'humata_local_first_straico_key_index' );
delete_option( 'humata_local_first_anthropic_key_index' );
delete_option( 'humata_local_second_straico_key_index' );
delete_option( 'humata_local_second_anthropic_key_index' );
delete_option( 'humata_followup_straico_key_index' );
delete_option( 'humata_followup_anthropic_key_index' );
delete_option( 'humata_openrouter_second_stage_key_index' );
delete_option( 'humata_local_first_openrouter_key_index' );
delete_option( 'humata_local_second_openrouter_key_index' );
delete_option( 'humata_followup_openrouter_key_index' );

// Delete SQLite database file and directory.
$upload_dir = wp_upload_dir();
$db_dir     = $upload_dir['basedir'] . '/humata-search';
$db_path    = $db_dir . '/index.db';
$docs_dir   = $db_dir . '/documents';

// Delete database file.
humata_uninstall_delete_file( $db_path );
// Delete WAL and SHM files if they exist (SQLite journal files).
humata_uninstall_delete_file( $db_path . '-wal' );
humata_uninstall_delete_file( $db_path . '-shm' );

// Delete uploaded document files.
if ( is_dir( $docs_dir ) ) {
    $files = glob( $docs_dir . '/*' );
    if ( $files ) {
        foreach ( $files as $file ) {
            if ( is_file( $file ) ) {
                humata_uninstall_delete_file( $file );
            }
        }
    }
    humata_uninstall_delete_dir( $docs_dir );
}

// Delete security files in db directory.
humata_uninstall_delete_file( $db_dir . '/.htaccess' );
humata_uninstall_delete_file( $db_dir . '/index.php' );
humata_uninstall_delete_file( $db_dir . '/web.config' );

// Delete security files in documents directory.
humata_uninstall_delete_file( $docs_dir . '/.htaccess' );
humata_uninstall_delete_file( $docs_dir . '/index.php' );
humata_uninstall_delete_file( $docs_dir . '/web.config' );

// Remove database directory.
humata_uninstall_delete_dir( $db_dir );

// Delete bot protection options.
delete_option( 'humata_bot_protection_enabled' );
delete_option( 'humata_honeypot_enabled' );
delete_option( 'humata_pow_enabled' );
delete_option( 'humata_pow_difficulty' );
delete_option( 'humata_progressive_delays_enabled' );
delete_option( 'humata_delay_threshold_1_count' );
delete_option( 'humata_delay_threshold_1_delay' );
delete_option( 'humata_delay_threshold_2_count' );
delete_option( 'humata_delay_threshold_2_delay' );
delete_option( 'humata_delay_threshold_3_count' );
delete_option( 'humata_delay_threshold_3_delay' );
delete_option( 'humata_delay_cooldown_minutes' );

// Delete rate limit, conversation, and bot protection transients
global $wpdb;
$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s",
        '_transient_humata_rate_limit_%',
        '_transient_timeout_humata_rate_limit_%',
        '_transient_humata_conversation_%',
        '_transient_timeout_humata_conversation_%',
        '_transient_humata_pow_verified_%',
        '_transient_timeout_humata_pow_verified_%',
        '_transient_humata_bot_session_%',
        '_transient_timeout_humata_bot_session_%'
    )
);

// Delete PoW challenge transients
$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
        '_transient_humata_pow_challenge_%',
        '_transient_timeout_humata_pow_challenge_%'
    )
);

// Flush rewrite rules to remove custom endpoints
flush_rewrite_rules();
