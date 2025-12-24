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
delete_option( 'humata_allow_seo_indexing' );
delete_option( 'humata_system_prompt' );
delete_option( 'humata_rate_limit' );
delete_option( 'humata_max_prompt_chars' );
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
delete_option( 'humata_floating_help' );
delete_option( 'humata_auto_links' );
delete_option( 'humata_intent_links' );
delete_option( 'humata_logo_url' );
delete_option( 'humata_logo_url_dark' );
delete_option( 'humata_user_avatar_url' );
delete_option( 'humata_bot_avatar_url' );
delete_option( 'humata_avatar_size' );
delete_option( 'humata_trigger_pages' );

// Delete local search options.
delete_option( 'humata_search_provider' );
delete_option( 'humata_search_db_version' );
delete_option( 'humata_local_search_system_prompt' );

// Delete SQLite database file and directory.
$upload_dir = wp_upload_dir();
$db_dir     = $upload_dir['basedir'] . '/humata-search';
$db_path    = $db_dir . '/index.db';
$docs_dir   = $db_dir . '/documents';

// Delete database file.
if ( file_exists( $db_path ) ) {
    @unlink( $db_path );
}
// Delete WAL and SHM files if they exist (SQLite journal files).
if ( file_exists( $db_path . '-wal' ) ) {
    @unlink( $db_path . '-wal' );
}
if ( file_exists( $db_path . '-shm' ) ) {
    @unlink( $db_path . '-shm' );
}

// Delete uploaded document files.
if ( is_dir( $docs_dir ) ) {
    $files = glob( $docs_dir . '/*' );
    if ( $files ) {
        foreach ( $files as $file ) {
            if ( is_file( $file ) ) {
                @unlink( $file );
            }
        }
    }
    @rmdir( $docs_dir );
}

// Delete security files in db directory.
if ( file_exists( $db_dir . '/.htaccess' ) ) {
    @unlink( $db_dir . '/.htaccess' );
}
if ( file_exists( $db_dir . '/index.php' ) ) {
    @unlink( $db_dir . '/index.php' );
}

// Remove database directory.
if ( is_dir( $db_dir ) ) {
    @rmdir( $db_dir );
}

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
