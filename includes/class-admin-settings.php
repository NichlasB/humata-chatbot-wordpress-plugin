<?php
/**
 * Admin Settings Class
 *
 * Handles the plugin settings page in WordPress admin.
 *
 * @package Humata_Chatbot
 * @since 1.0.0
 */

defined( 'ABSPATH' ) || exit;

// Admin settings tabs (WooCommerce-style navigation).
require_once __DIR__ . '/admin-tabs/class-humata-settings-tabs.php';
require_once __DIR__ . '/admin-tabs/tabs/class-tab-base.php';
require_once __DIR__ . '/admin-tabs/tabs/class-tab-general.php';
require_once __DIR__ . '/admin-tabs/tabs/class-tab-providers.php';
require_once __DIR__ . '/admin-tabs/tabs/class-tab-display.php';
require_once __DIR__ . '/admin-tabs/tabs/class-tab-security.php';
require_once __DIR__ . '/admin-tabs/tabs/class-tab-floating-help.php';
require_once __DIR__ . '/admin-tabs/tabs/class-tab-auto-links.php';
require_once __DIR__ . '/admin-tabs/tabs/class-tab-pages.php';
require_once __DIR__ . '/admin-tabs/tabs/class-tab-usage.php';
// Admin settings schema/helpers.
require_once __DIR__ . '/Admin/Settings/Schema.php';
// Admin AJAX handlers.
require_once __DIR__ . '/Admin/Ajax.php';
// Settings registration.
require_once __DIR__ . '/Admin/Settings/Register.php';
// Document IDs helpers/sanitizers.
require_once __DIR__ . '/Admin/Settings/DocumentIds.php';
// Sanitizers.
require_once __DIR__ . '/Admin/Settings/Sanitize/Core.php';
require_once __DIR__ . '/Admin/Settings/Sanitize/FloatingHelp.php';
require_once __DIR__ . '/Admin/Settings/Sanitize/Links.php';
require_once __DIR__ . '/Admin/Settings/Sanitize/Providers.php';
require_once __DIR__ . '/Admin/Settings/Sanitize/Security.php';
require_once __DIR__ . '/Admin/Settings/Sanitize/TriggerPages.php';
// Render helpers.
require_once __DIR__ . '/Admin/Render/Sections.php';
require_once __DIR__ . '/Admin/Render/Providers.php';
require_once __DIR__ . '/Admin/Render/Security.php';
require_once __DIR__ . '/Admin/Render/Page.php';
require_once __DIR__ . '/Admin/Render/Api.php';
require_once __DIR__ . '/Admin/Render/Display.php';
require_once __DIR__ . '/Admin/Render/Links.php';
require_once __DIR__ . '/Admin/Render/FloatingHelp.php';
require_once __DIR__ . '/Admin/Render/TriggerPages.php';

/**
 * Class Humata_Chatbot_Admin_Settings
 *
 * @since 1.0.0
 */
class Humata_Chatbot_Admin_Settings {
    use Humata_Chatbot_Admin_Ajax_Trait;
    use Humata_Chatbot_Admin_Settings_Register_Trait;
    use Humata_Chatbot_Admin_Settings_Sanitize_Core_Trait;
    use Humata_Chatbot_Admin_Settings_DocumentIds_Trait;
    use Humata_Chatbot_Admin_Settings_Sanitize_Providers_Trait;
    use Humata_Chatbot_Admin_Settings_Sanitize_Security_Trait;
    use Humata_Chatbot_Admin_Settings_Sanitize_FloatingHelp_Trait;
    use Humata_Chatbot_Admin_Settings_Sanitize_Links_Trait;
    use Humata_Chatbot_Admin_Settings_Render_Sections_Trait;
    use Humata_Chatbot_Admin_Settings_Render_Providers_Trait;
    use Humata_Chatbot_Admin_Settings_Render_Security_Trait;
    use Humata_Chatbot_Admin_Settings_Render_Page_Trait;
    use Humata_Chatbot_Admin_Settings_Render_Api_Trait;
    use Humata_Chatbot_Admin_Settings_Render_Display_Trait;
    use Humata_Chatbot_Admin_Settings_Render_Links_Trait;
    use Humata_Chatbot_Admin_Settings_Render_FloatingHelp_Trait;
    use Humata_Chatbot_Admin_Settings_Sanitize_TriggerPages_Trait;
    use Humata_Chatbot_Admin_Settings_Render_TriggerPages_Trait;

    /**
     * Humata API base URL.
     *
     * @var string
     */
    const HUMATA_API_BASE = 'https://app.humata.ai/api/v1';

    /**
     * Settings tabs controller.
     *
     * @since 1.0.0
     * @var Humata_Chatbot_Settings_Tabs|null
     */
    private $tabs_controller = null;

    /**
     * Tab module instances keyed by tab key.
     *
     * @since 1.0.0
     * @var array|null
     */
    private $tab_modules = null;

    /**
     * Constructor.
     *
     * @since 1.0.0
     */
    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
        add_filter( 'wp_redirect', array( $this, 'preserve_tab_on_settings_redirect' ), 10, 2 );
        // Ensure tabbed settings only update options for the active tab (prevents other tabs being wiped).
        add_filter( 'allowed_options', array( $this, 'filter_allowed_options_for_active_tab' ) );
        // Back-compat for older WP versions that still use the legacy filter name.
        add_filter( 'whitelist_options', array( $this, 'filter_allowed_options_for_active_tab' ) );
        // Hard-stop guard: even if WordPress attempts to update all options in the group, prevent cross-tab wipes.
        add_filter( 'pre_update_option', array( $this, 'prevent_cross_tab_option_wipe' ), 9999, 3 );
        add_action( 'wp_ajax_humata_test_api', array( $this, 'ajax_test_api' ) );
        add_action( 'wp_ajax_humata_test_ask', array( $this, 'ajax_test_ask' ) );
        add_action( 'wp_ajax_humata_fetch_titles', array( $this, 'ajax_fetch_titles' ) );
        add_action( 'wp_ajax_humata_clear_cache', array( $this, 'ajax_clear_cache' ) );
    }

    /**
     * Add settings page to WordPress admin menu.
     *
     * @since 1.0.0
     * @return void
     */
    public function add_settings_page() {
        add_options_page(
            __( 'Humata Chatbot Settings', 'humata-chatbot' ),
            __( 'Humata Chatbot', 'humata-chatbot' ),
            'manage_options',
            'humata-chatbot',
            array( $this, 'render_settings_page' )
        );
    }

    /**
     * Enqueue admin assets.
     *
     * @since 1.0.0
     * @param string $hook Current admin page hook.
     * @return void
     */
    public function enqueue_admin_assets( $hook ) {
        if ( 'settings_page_humata-chatbot' !== $hook ) {
            return;
        }

        wp_enqueue_script( 'jquery' );
        wp_enqueue_script( 'jquery-ui-sortable' );
        wp_enqueue_media();

        // Prefer bundled admin assets (built from assets/src/admin/settings/*).
        $script_rel  = 'assets/dist/admin-settings.js';
        $style_rel   = 'assets/dist/admin-settings.css';
        $script_file = HUMATA_CHATBOT_PATH . $script_rel;
        $style_file  = HUMATA_CHATBOT_PATH . $style_rel;

        if ( file_exists( $style_file ) ) {
            wp_enqueue_style(
                'humata-admin-settings',
                HUMATA_CHATBOT_URL . $style_rel,
                array(),
                filemtime( $style_file )
            );
        }

        if ( file_exists( $script_file ) ) {
            wp_enqueue_script(
                'humata-admin-settings',
                HUMATA_CHATBOT_URL . $script_rel,
                array( 'jquery', 'jquery-ui-sortable' ),
                filemtime( $script_file ),
                true
            );

            $titles_for_js = get_option( 'humata_document_titles', array() );
            if ( ! is_array( $titles_for_js ) ) {
                $titles_for_js = array();
            }

            wp_localize_script(
                'humata-admin-settings',
                'humataAdminConfig',
                array(
                    'ajaxUrl'        => esc_url_raw( admin_url( 'admin-ajax.php' ) ),
                    'nonce'          => wp_create_nonce( 'humata_admin_nonce' ),
                    'documentTitles' => $titles_for_js,
                    'i18n'           => array(
                        'titleNotFetchedText' => __( '(title not fetched)', 'humata-chatbot' ),
                    ),
                )
            );
        }
    }

    /**
     * Get settings tabs controller.
     *
     * @since 1.0.0
     * @return Humata_Chatbot_Settings_Tabs
     */
    private function get_tabs_controller() {
        if ( null === $this->tabs_controller ) {
            $this->tabs_controller = new Humata_Chatbot_Settings_Tabs();
        }

        return $this->tabs_controller;
    }

    /**
     * Get tab module instances keyed by tab key.
     *
     * @since 1.0.0
     * @return array
     */
    private function get_tab_modules() {
        if ( null !== $this->tab_modules ) {
            return $this->tab_modules;
        }

        $this->tab_modules = array(
            'general'       => new Humata_Chatbot_Settings_Tab_General( $this ),
            'providers'     => new Humata_Chatbot_Settings_Tab_Providers( $this ),
            'display'       => new Humata_Chatbot_Settings_Tab_Display( $this ),
            'security'      => new Humata_Chatbot_Settings_Tab_Security( $this ),
            'floating_help' => new Humata_Chatbot_Settings_Tab_Floating_Help( $this ),
            'auto_links'    => new Humata_Chatbot_Settings_Tab_Auto_Links( $this ),
            'pages'         => new Humata_Chatbot_Settings_Tab_Pages( $this ),
            'usage'         => new Humata_Chatbot_Settings_Tab_Usage( $this ),
        );

        return $this->tab_modules;
    }

    /**
     * Preserve the active settings tab on Settings API redirects.
     *
     * WordPress redirects back to the settings page after saving via `options.php`.
     * We ensure `tab=` is preserved even if the referer is stripped/modified.
     *
     * @since 1.0.0
     * @param string $location Redirect location.
     * @param int    $status   HTTP status code.
     * @return string
     */
    public function preserve_tab_on_settings_redirect( $location, $status ) {
        if ( ! is_admin() ) {
            return $location;
        }

        if ( empty( $_POST['option_page'] ) ) {
            return $location;
        }

        $option_page = sanitize_key( (string) wp_unslash( $_POST['option_page'] ) );
        if ( 'humata_chatbot_settings' !== $option_page ) {
            return $location;
        }

        if ( empty( $_POST['humata_active_tab'] ) ) {
            return $location;
        }

        $tab = sanitize_key( (string) wp_unslash( $_POST['humata_active_tab'] ) );
        if ( '' === $tab ) {
            return $location;
        }

        $location_str = (string) $location;
        if ( false === strpos( $location_str, 'options-general.php' ) || false === strpos( $location_str, 'page=humata-chatbot' ) ) {
            return $location;
        }

        $tabs = $this->get_tabs_controller();
        if ( ! $tabs->is_valid_tab( $tab ) ) {
            $tab = $tabs->get_default_tab();
        }

        if ( '' === $tab ) {
            return $location;
        }

        return add_query_arg( 'tab', $tab, $location_str );
    }

    /**
     * Filter which options are updated on submit when using tabbed Settings API forms.
     *
     * WordPress updates every option registered to a given Settings API group when posting to `options.php`.
     * Since this plugin uses tabs (each tab shows only a subset of fields), we must restrict updates to
     * the options that belong to the submitted tab. Otherwise, fields not present in the form can be
     * saved as empty, wiping settings from other tabs.
     *
     * @since 1.0.0
     * @param array $allowed_options Map of option_page => option names.
     * @return array
     */
    public function filter_allowed_options_for_active_tab( $allowed_options ) {
        if ( ! is_admin() || ! is_array( $allowed_options ) ) {
            return $allowed_options;
        }

        if ( empty( $_POST['option_page'] ) ) {
            return $allowed_options;
        }

        $option_page = sanitize_key( (string) wp_unslash( $_POST['option_page'] ) );
        if ( 'humata_chatbot_settings' !== $option_page ) {
            return $allowed_options;
        }

        if ( empty( $_POST['humata_active_tab'] ) ) {
            return $allowed_options;
        }

        $tab = sanitize_key( (string) wp_unslash( $_POST['humata_active_tab'] ) );
        if ( '' === $tab ) {
            return $allowed_options;
        }

        $options_for_tab = Humata_Chatbot_Admin_Settings_Schema::get_options_for_tab( $tab );
        if ( ! empty( $options_for_tab ) ) {
            $allowed_options['humata_chatbot_settings'] = $options_for_tab;
        }

        return $allowed_options;
    }

    /**
     * Prevent options from other tabs being wiped when saving a single tab.
     *
     * Some WordPress setups still end up calling update_option() for every option in a settings group,
     * even when only one tab's fields are present in the submitted form. This guard preserves the old
     * value for plugin options that do not belong to the active tab being saved.
     *
     * @since 1.0.0
     * @param mixed  $value     The new, unsanitized option value.
     * @param string $option    Option name.
     * @param mixed  $old_value The old option value.
     * @return mixed
     */
    public function prevent_cross_tab_option_wipe( $value, $option, $old_value ) {
        if ( ! is_admin() ) {
            return $value;
        }

        if ( empty( $_POST['option_page'] ) ) {
            return $value;
        }

        $option_page = sanitize_key( (string) wp_unslash( $_POST['option_page'] ) );
        if ( 'humata_chatbot_settings' !== $option_page ) {
            return $value;
        }

        // Determine active tab: prefer explicit hidden field, fallback to referer querystring.
        $tab = '';
        if ( ! empty( $_POST['humata_active_tab'] ) ) {
            $tab = sanitize_key( (string) wp_unslash( $_POST['humata_active_tab'] ) );
        } elseif ( ! empty( $_POST['_wp_http_referer'] ) ) {
            $ref = (string) wp_unslash( $_POST['_wp_http_referer'] );
            $parts = wp_parse_url( $ref );
            if ( is_array( $parts ) && ! empty( $parts['query'] ) ) {
                $query_vars = array();
                wp_parse_str( (string) $parts['query'], $query_vars );
                if ( isset( $query_vars['tab'] ) ) {
                    $tab = sanitize_key( (string) $query_vars['tab'] );
                }
            }
        }

        if ( '' === $tab ) {
            return $value;
        }

        // Only apply to this plugin's option names.
        $plugin_options = array(
            'humata_api_key',
            'humata_document_ids',
            'humata_document_titles',
            'humata_folder_id',
            'humata_chat_location',
            'humata_chat_page_slug',
            'humata_chat_theme',
            'humata_system_prompt',
            'humata_rate_limit',
            'humata_max_prompt_chars',
            'humata_medical_disclaimer_text',
            'humata_footer_copyright_text',
            'humata_second_llm_provider',
            'humata_straico_review_enabled',
            'humata_straico_api_key',
            'humata_straico_model',
            'humata_straico_system_prompt',
            'humata_anthropic_api_key',
            'humata_anthropic_model',
            'humata_anthropic_extended_thinking',
            'humata_floating_help',
            'humata_auto_links',
            'humata_intent_links',
            'humata_user_avatar_url',
            'humata_bot_avatar_url',
            'humata_avatar_size',
            'humata_bot_response_disclaimer',
            'humata_turnstile_enabled',
            'humata_turnstile_site_key',
            'humata_turnstile_secret_key',
            'humata_turnstile_appearance',
            'humata_trigger_pages',
        );

        if ( ! in_array( (string) $option, $plugin_options, true ) ) {
            return $value;
        }

        $options_for_tab = Humata_Chatbot_Admin_Settings_Schema::get_options_for_tab( $tab );
        if ( empty( $options_for_tab ) ) {
            return $value;
        }

        // If this option isn't part of the active tab, preserve it.
        if ( ! in_array( (string) $option, $options_for_tab, true ) ) {
            return $old_value;
        }

        return $value;
    }

    // Settings registration moved to `includes/Admin/Settings/Register.php` (Humata_Chatbot_Admin_Settings_Register_Trait).

    // Sanitizers moved to `includes/Admin/Settings/Sanitize/*` traits.

    // Floating help defaults/sanitizers moved to `includes/Admin/Settings/Sanitize/FloatingHelp.php`.

    // Auto-links + intent-links sanitizers moved to `includes/Admin/Settings/Sanitize/Links.php`.

    // AJAX handlers moved to `includes/Admin/Ajax.php` (Humata_Chatbot_Admin_Ajax_Trait).
}
