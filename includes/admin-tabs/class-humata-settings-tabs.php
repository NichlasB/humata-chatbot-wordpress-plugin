<?php
/**
 * Settings Tabs Controller
 *
 * Provides WooCommerce-style tab navigation for the Humata settings page and
 * resolves the active tab from the request.
 *
 * @package Humata_Chatbot
 * @since 1.0.0
 */
defined( 'ABSPATH' ) || exit;

class Humata_Chatbot_Settings_Tabs {

    /**
     * Filter name for extending tabs.
     *
     * @since 1.0.0
     * @var string
     */
    const FILTER_TABS = 'humata_chatbot_settings_tabs';

    /**
     * Cached normalized tabs array.
     *
     * @since 1.0.0
     * @var array|null
     */
    private $tabs = null;

    /**
     * Cached active tab key.
     *
     * @since 1.0.0
     * @var string|null
     */
    private $active_tab = null;

    /**
     * Get the base settings URL (without tab).
     *
     * @since 1.0.0
     * @return string
     */
    public function get_base_url() {
        return admin_url( 'options-general.php?page=humata-chatbot' );
    }

    /**
     * Default tab key.
     *
     * @since 1.0.0
     * @return string
     */
    public function get_default_tab() {
        return 'general';
    }

    /**
     * Get tab definitions.
     *
     * Each tab is: [ 'label' => string, 'page_id' => string, 'has_form' => bool ].
     *
     * @since 1.0.0
     * @return array
     */
    public function get_tabs() {
        if ( null !== $this->tabs ) {
            return $this->tabs;
        }

        $tabs = array(
            'general'       => array(
                'label'    => __( 'General', 'humata-chatbot' ),
                'page_id'  => 'humata-chatbot-tab-general',
                'has_form' => true,
            ),
            'providers'     => array(
                'label'    => __( 'Providers', 'humata-chatbot' ),
                'page_id'  => 'humata-chatbot-tab-providers',
                'has_form' => true,
            ),
            'display'       => array(
                'label'    => __( 'Display', 'humata-chatbot' ),
                'page_id'  => 'humata-chatbot-tab-display',
                'has_form' => true,
            ),
            'security'      => array(
                'label'    => __( 'Security', 'humata-chatbot' ),
                'page_id'  => 'humata-chatbot-tab-security',
                'has_form' => true,
            ),
            'floating_help' => array(
                'label'    => __( 'Floating Help', 'humata-chatbot' ),
                'page_id'  => 'humata-chatbot-tab-floating-help',
                'has_form' => true,
            ),
            'auto_links'    => array(
                'label'    => __( 'Auto-Links', 'humata-chatbot' ),
                'page_id'  => 'humata-chatbot-tab-auto-links',
                'has_form' => true,
            ),
            'pages'         => array(
                'label'    => __( 'Pages', 'humata-chatbot' ),
                'page_id'  => 'humata-chatbot-tab-pages',
                'has_form' => true,
            ),
            'usage'         => array(
                'label'    => __( 'Usage', 'humata-chatbot' ),
                'page_id'  => 'humata-chatbot-tab-usage',
                'has_form' => false,
            ),
            'documents'     => array(
                'label'    => __( 'Local Documents', 'humata-chatbot' ),
                'page_id'  => 'humata-chatbot-tab-documents',
                'has_form' => false,
            ),
            'suggested_questions' => array(
                'label'    => __( 'Suggested Questions', 'humata-chatbot' ),
                'page_id'  => 'humata-chatbot-tab-suggested-questions',
                'has_form' => true,
            ),
        );

        /**
         * Filter settings tabs.
         *
         * @since 1.0.0
         * @param array $tabs Tab array keyed by tab key.
         */
        $tabs = apply_filters( self::FILTER_TABS, $tabs );

        $clean = array();
        foreach ( (array) $tabs as $key => $tab ) {
            $key = sanitize_key( (string) $key );
            if ( '' === $key ) {
                continue;
            }

            $label = isset( $tab['label'] ) ? $tab['label'] : $key;

            $page_id = isset( $tab['page_id'] ) ? sanitize_key( (string) $tab['page_id'] ) : '';
            if ( '' === $page_id ) {
                $page_id = 'humata-chatbot-tab-' . $key;
            }

            $has_form = isset( $tab['has_form'] ) ? (bool) $tab['has_form'] : true;

            $clean[ $key ] = array(
                'label'    => $label,
                'page_id'  => $page_id,
                'has_form' => $has_form,
            );
        }

        // Ensure there is always a valid default tab.
        if ( empty( $clean ) ) {
            $clean = array(
                'general' => array(
                    'label'    => __( 'General', 'humata-chatbot' ),
                    'page_id'  => 'humata-chatbot-tab-general',
                    'has_form' => true,
                ),
            );
        }

        $this->tabs = $clean;
        return $this->tabs;
    }

    /**
     * Check whether a tab key is valid.
     *
     * @since 1.0.0
     * @param string $tab_key Tab key.
     * @return bool
     */
    public function is_valid_tab( $tab_key ) {
        $tabs    = $this->get_tabs();
        $tab_key = sanitize_key( (string) $tab_key );
        return isset( $tabs[ $tab_key ] );
    }

    /**
     * Get the active tab key.
     *
     * @since 1.0.0
     * @return string
     */
    public function get_active_tab() {
        if ( null !== $this->active_tab ) {
            return $this->active_tab;
        }

        $requested = '';
        if ( isset( $_GET['tab'] ) ) {
            $requested = sanitize_key( (string) wp_unslash( $_GET['tab'] ) );
        }

        if ( '' !== $requested && $this->is_valid_tab( $requested ) ) {
            $this->active_tab = $requested;
            return $this->active_tab;
        }

        $this->active_tab = $this->get_default_tab();
        if ( ! $this->is_valid_tab( $this->active_tab ) ) {
            $tabs            = $this->get_tabs();
            $first_key = '';
            foreach ( $tabs as $key => $_tab ) {
                $first_key = $key;
                break;
            }
            $this->active_tab = '' !== $first_key ? (string) $first_key : $this->get_default_tab();
        }

        return $this->active_tab;
    }

    /**
     * Get Settings API page ID for a tab key.
     *
     * @since 1.0.0
     * @param string $tab_key Tab key.
     * @return string
     */
    public function get_tab_page_id( $tab_key ) {
        $tabs    = $this->get_tabs();
        $tab_key = sanitize_key( (string) $tab_key );

        if ( isset( $tabs[ $tab_key ]['page_id'] ) ) {
            return (string) $tabs[ $tab_key ]['page_id'];
        }

        $default = $this->get_default_tab();
        return isset( $tabs[ $default ]['page_id'] ) ? (string) $tabs[ $default ]['page_id'] : 'humata-chatbot-tab-general';
    }

    /**
     * Whether a given tab renders a settings form.
     *
     * @since 1.0.0
     * @param string $tab_key Tab key.
     * @return bool
     */
    public function tab_has_form( $tab_key ) {
        $tabs    = $this->get_tabs();
        $tab_key = sanitize_key( (string) $tab_key );
        return isset( $tabs[ $tab_key ]['has_form'] ) ? (bool) $tabs[ $tab_key ]['has_form'] : true;
    }

    /**
     * Build a URL for a tab.
     *
     * @since 1.0.0
     * @param string $tab_key Tab key.
     * @return string
     */
    public function get_tab_url( $tab_key ) {
        $tab_key = sanitize_key( (string) $tab_key );
        return add_query_arg(
            array(
                'page' => 'humata-chatbot',
                'tab'  => $tab_key,
            ),
            admin_url( 'options-general.php' )
        );
    }

    /**
     * Render admin navigation tabs.
     *
     * @since 1.0.0
     * @param string $active_tab Active tab key.
     * @return void
     */
    public function render_tabs_nav( $active_tab ) {
        $tabs       = $this->get_tabs();
        $active_tab = sanitize_key( (string) $active_tab );
        ?>
        <h2 class="nav-tab-wrapper wp-clearfix">
            <?php foreach ( $tabs as $key => $tab ) : ?>
                <?php
                $classes = array( 'nav-tab' );
                if ( $key === $active_tab ) {
                    $classes[] = 'nav-tab-active';
                }
                ?>
                <a
                    href="<?php echo esc_url( $this->get_tab_url( $key ) ); ?>"
                    class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>"
                >
                    <?php echo esc_html( (string) $tab['label'] ); ?>
                </a>
            <?php endforeach; ?>
        </h2>
        <?php
    }
}


