<?php
/**
 * Plugin Name: Humata Chatbot
 * Plugin URI: https://alynt.com
 * Description: AI-powered chat interface that connects to your Humata knowledge base
 * Version: 1.0.0
 * Author: Alynt
 * Author URI: https://alynt.com
 * Text Domain: humata-chatbot
 * Domain Path: /languages
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package Humata_Chatbot
 */

defined( 'ABSPATH' ) || exit;

// Plugin constants
define( 'HUMATA_CHATBOT_VERSION', '1.0.0' );
define( 'HUMATA_CHATBOT_PATH', plugin_dir_path( __FILE__ ) );
define( 'HUMATA_CHATBOT_URL', plugin_dir_url( __FILE__ ) );
define( 'HUMATA_CHATBOT_BASENAME', plugin_basename( __FILE__ ) );
define( 'HUMATA_DEFAULT_OPENROUTER_MODEL', 'mistralai/mistral-medium-3.1' );

/**
 * Register font MIME types for WordPress media uploads.
 *
 * @since 1.0.0
 * @param array $mimes Existing MIME types.
 * @return array Modified MIME types.
 */
function humata_register_font_mime_types( $mimes ) {
	$mimes['woff2'] = 'font/woff2';
	$mimes['woff']  = 'font/woff';
	$mimes['ttf']   = 'font/ttf';
	$mimes['otf']   = 'font/otf';
	return $mimes;
}
add_filter( 'upload_mimes', 'humata_register_font_mime_types' );

/**
 * Fix MIME type detection for font files.
 *
 * @since 1.0.0
 * @param array  $data     File data.
 * @param string $file     Full path to the file.
 * @param string $filename The name of the file.
 * @param array  $mimes    Allowed MIME types.
 * @return array Modified file data.
 */
function humata_fix_font_mime_type( $data, $file, $filename, $mimes ) {
	$ext = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
	$font_types = array(
		'woff2' => 'font/woff2',
		'woff'  => 'font/woff',
		'ttf'   => 'font/ttf',
		'otf'   => 'font/otf',
	);
	if ( isset( $font_types[ $ext ] ) ) {
		$data['ext']  = $ext;
		$data['type'] = $font_types[ $ext ];
	}
	return $data;
}
add_filter( 'wp_check_filetype_and_ext', 'humata_fix_font_mime_type', 10, 4 );

// Load helpers and classes
require_once HUMATA_CHATBOT_PATH . 'includes/Helpers.php';
require_once HUMATA_CHATBOT_PATH . 'includes/class-admin-settings.php';
require_once HUMATA_CHATBOT_PATH . 'includes/class-rest-api.php';
require_once HUMATA_CHATBOT_PATH . 'includes/class-template-loader.php';

/**
 * Initialize the plugin.
 *
 * @since 1.0.0
 * @return void
 */
function humata_chatbot_init() {
    // Load text domain for translations
    load_plugin_textdomain( 'humata-chatbot', false, dirname( HUMATA_CHATBOT_BASENAME ) . '/languages' );

    // Initialize plugin classes
    new Humata_Chatbot_Admin_Settings();
    new Humata_Chatbot_REST_API();
    new Humata_Chatbot_Template_Loader();
}
add_action( 'plugins_loaded', 'humata_chatbot_init' );

/**
 * Plugin activation hook.
 *
 * @since 1.0.0
 * @return void
 */
function humata_chatbot_activate() {
    // Set default options
    add_option( 'humata_api_key', '' );
    add_option( 'humata_document_ids', '' );
    add_option( 'humata_chat_location', 'dedicated' );
    add_option( 'humata_chat_page_slug', 'chat' );
    add_option( 'humata_chat_theme', 'auto' );
    add_option( 'humata_rate_limit', 50 );
    add_option( 'humata_second_llm_provider', 'none' );
    add_option( 'humata_straico_review_enabled', 0 );
    add_option( 'humata_straico_api_key', array() );
    add_option( 'humata_straico_model', '' );
    add_option( 'humata_straico_system_prompt', '' );
    add_option( 'humata_anthropic_api_key', array() );
    add_option( 'humata_anthropic_model', 'claude-3-5-sonnet-20241022' );
    add_option( 'humata_anthropic_extended_thinking', 0 );

    add_option( 'humata_openrouter_api_key', array() );
    add_option( 'humata_openrouter_model', HUMATA_DEFAULT_OPENROUTER_MODEL );

    // Local Search first-stage API keys (arrays for rotation).
    add_option( 'humata_local_first_straico_api_key', array() );
    add_option( 'humata_local_first_anthropic_api_key', array() );

    add_option( 'humata_local_first_openrouter_api_key', array() );
    add_option( 'humata_local_first_openrouter_model', HUMATA_DEFAULT_OPENROUTER_MODEL );

    // Local Search second-stage API keys (arrays for rotation).
    add_option( 'humata_local_second_straico_api_key', array() );
    add_option( 'humata_local_second_anthropic_api_key', array() );
    add_option( 'humata_local_second_openrouter_api_key', array() );
    add_option( 'humata_local_second_openrouter_model', HUMATA_DEFAULT_OPENROUTER_MODEL );

    // Floating help menu (disabled by default, with seeded FAQ + Contact content).
    if ( class_exists( 'Humata_Chatbot_Admin_Settings' ) ) {
        add_option( 'humata_floating_help', Humata_Chatbot_Admin_Settings::get_default_floating_help_option() );
    } else {
        add_option( 'humata_floating_help', array( 'enabled' => 0 ) );
    }

    // Trigger pages (empty by default).
    add_option( 'humata_trigger_pages', array() );

    // Logo URLs (empty by default).
    add_option( 'humata_logo_url', '' );
    add_option( 'humata_logo_url_dark', '' );

    // Local search options (Humata API is default).
    add_option( 'humata_search_provider', 'humata' );
    add_option( 'humata_search_db_version', '' );
    add_option( 'humata_local_search_system_prompt', 'You are a helpful assistant. Answer questions based only on the provided reference materials. If the information is not in the materials, say so clearly. Be concise and accurate.' );

    // Follow-up questions (disabled by default).
    add_option( 'humata_followup_questions', array(
        'enabled'             => false,
        'provider'            => 'straico',
        'straico_api_keys'    => array(),
        'straico_model'       => '',
        'anthropic_api_keys'  => array(),
        'anthropic_model'     => 'claude-3-5-sonnet-20241022',
        'anthropic_extended_thinking' => 0,
        'max_question_length' => 80,
        'topic_scope'         => '',
        'custom_instructions' => '',
    ) );

    // Typography (disabled by default).
    require_once HUMATA_CHATBOT_PATH . 'includes/Admin/Settings/Sanitize/Typography.php';
    add_option( 'humata_typography', Humata_Chatbot_Admin_Settings_Sanitize_Typography_Trait::get_default_typography_option() );

    // Add rewrite rules for dedicated page
    humata_chatbot_add_rewrite_rules();
    flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'humata_chatbot_activate' );

/**
 * Plugin deactivation hook.
 *
 * @since 1.0.0
 * @return void
 */
function humata_chatbot_deactivate() {
    flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'humata_chatbot_deactivate' );

/**
 * Add rewrite rules for the dedicated chat page.
 *
 * @since 1.0.0
 * @return void
 */
function humata_chatbot_add_rewrite_rules() {
    $slug = get_option( 'humata_chat_page_slug', 'chat' );
    add_rewrite_rule(
        '^' . preg_quote( $slug, '/' ) . '/?$',
        'index.php?humata_chat_page=1',
        'top'
    );
}
add_action( 'init', 'humata_chatbot_add_rewrite_rules' );

/**
 * Register query vars.
 *
 * @since 1.0.0
 * @param array $vars Existing query vars.
 * @return array Modified query vars.
 */
function humata_chatbot_query_vars( $vars ) {
    $vars[] = 'humata_chat_page';
    return $vars;
}
add_filter( 'query_vars', 'humata_chatbot_query_vars' );

/**
 * Display admin notice if API credentials are missing.
 *
 * @since 1.0.0
 * @return void
 */
function humata_chatbot_admin_notices() {
    $api_key      = get_option( 'humata_api_key', '' );
    $document_ids = get_option( 'humata_document_ids', '' );

    if ( empty( $api_key ) || empty( $document_ids ) ) {
        $settings_url = admin_url( 'options-general.php?page=humata-chatbot' );
        ?>
        <div class="notice notice-warning is-dismissible">
            <p>
                <?php
                printf(
                    /* translators: %s: Settings page URL */
                    esc_html__( 'Humata Chatbot requires API credentials. Please configure them in the %s.', 'humata-chatbot' ),
                    '<a href="' . esc_url( $settings_url ) . '">' . esc_html__( 'settings page', 'humata-chatbot' ) . '</a>'
                );
                ?>
            </p>
        </div>
        <?php
    }
}
add_action( 'admin_notices', 'humata_chatbot_admin_notices' );

/**
 * Add settings link to plugin action links.
 *
 * @since 1.0.0
 * @param array $links Existing plugin action links.
 * @return array Modified plugin action links.
 */
function humata_chatbot_plugin_action_links( $links ) {
    $settings_link = '<a href="' . esc_url( admin_url( 'options-general.php?page=humata-chatbot' ) ) . '">' . esc_html__( 'Settings', 'humata-chatbot' ) . '</a>';
    array_unshift( $links, $settings_link );
    return $links;
}
add_filter( 'plugin_action_links_' . HUMATA_CHATBOT_BASENAME, 'humata_chatbot_plugin_action_links' );
