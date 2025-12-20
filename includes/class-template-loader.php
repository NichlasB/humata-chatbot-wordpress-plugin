<?php
/**
 * Template Loader Class
 *
 * Handles template loading and shortcode registration.
 *
 * @package Humata_Chatbot
 * @since 1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Humata_Chatbot_Template_Loader
 *
 * @since 1.0.0
 */
class Humata_Chatbot_Template_Loader {

    /**
     * Constructor.
     *
     * @since 1.0.0
     */
    public function __construct() {
        add_filter( 'template_include', array( $this, 'load_chat_template' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_action( 'wp_footer', array( $this, 'render_floating_help' ) );
        add_shortcode( 'humata_chat', array( $this, 'render_shortcode' ) );
    }

    /**
     * Load chat template based on settings.
     *
     * @since 1.0.0
     * @param string $template Current template path.
     * @return string Modified template path.
     */
    public function load_chat_template( $template ) {
        $location = get_option( 'humata_chat_location', 'dedicated' );

        // Homepage override
        if ( 'homepage' === $location && is_front_page() ) {
            return HUMATA_CHATBOT_PATH . 'templates/chat-interface.php';
        }

        // Dedicated page via rewrite rule
        if ( 'dedicated' === $location && get_query_var( 'humata_chat_page' ) ) {
            return HUMATA_CHATBOT_PATH . 'templates/chat-interface.php';
        }

        return $template;
    }

    /**
     * Check if we should load chat assets.
     *
     * @since 1.0.0
     * @return bool True if assets should be loaded.
     */
    private function should_load_assets() {
        $location = get_option( 'humata_chat_location', 'dedicated' );

        // Always load for homepage mode on front page
        if ( 'homepage' === $location && is_front_page() ) {
            return true;
        }

        // Load for dedicated page
        if ( 'dedicated' === $location && get_query_var( 'humata_chat_page' ) ) {
            return true;
        }

        // For shortcode mode, we'll enqueue via shortcode
        return false;
    }

    /**
     * Enqueue chat assets.
     *
     * @since 1.0.0
     * @return void
     */
    public function enqueue_assets() {
        if ( $this->should_load_assets() ) {
            $this->enqueue_chat_assets();
        }

        $this->enqueue_floating_help_assets();
    }

    /**
     * Actually enqueue the chat assets.
     *
     * @since 1.0.0
     * @return void
     */
    private function enqueue_chat_assets() {
        $css_file = HUMATA_CHATBOT_PATH . 'assets/css/chat-widget.css';
        $js_file  = HUMATA_CHATBOT_PATH . 'assets/js/chat-widget.js';
        $jspdf_file = HUMATA_CHATBOT_PATH . 'assets/js/vendor/jspdf.umd.min.js';

        $css_version = file_exists( $css_file ) ? filemtime( $css_file ) : HUMATA_CHATBOT_VERSION;
        $js_version  = file_exists( $js_file ) ? filemtime( $js_file ) : HUMATA_CHATBOT_VERSION;
        $jspdf_version = file_exists( $jspdf_file ) ? filemtime( $jspdf_file ) : HUMATA_CHATBOT_VERSION;

        // Enqueue CSS
        wp_enqueue_style(
            'humata-chat-widget',
            HUMATA_CHATBOT_URL . 'assets/css/chat-widget.css',
            array(),
            $css_version
        );

        wp_enqueue_script(
            'humata-jspdf',
            HUMATA_CHATBOT_URL . 'assets/js/vendor/jspdf.umd.min.js',
            array(),
            $jspdf_version,
            true
        );

        // Enqueue JavaScript
        wp_enqueue_script(
            'humata-chat-widget',
            HUMATA_CHATBOT_URL . 'assets/js/chat-widget.js',
            array( 'humata-jspdf' ),
            $js_version,
            true
        );

        $second_llm_provider = get_option( 'humata_second_llm_provider', '' );
        if ( ! is_string( $second_llm_provider ) ) {
            $second_llm_provider = '';
        }
        $second_llm_provider = trim( $second_llm_provider );

        // Back-compat: if new provider option is unset, respect the legacy Straico enabled flag.
        if ( '' === $second_llm_provider ) {
            $legacy_straico_enabled = (int) get_option( 'humata_straico_review_enabled', 0 );
            $second_llm_provider    = ( 1 === $legacy_straico_enabled ) ? 'straico' : 'none';
        }

        $valid_providers = array( 'none', 'straico', 'anthropic' );
        if ( ! in_array( $second_llm_provider, $valid_providers, true ) ) {
            $second_llm_provider = 'none';
        }

        $max_prompt_chars = absint( get_option( 'humata_max_prompt_chars', 3000 ) );
        if ( $max_prompt_chars <= 0 ) {
            $max_prompt_chars = 3000;
        }
        if ( $max_prompt_chars > 100000 ) {
            $max_prompt_chars = 100000;
        }

        // Localize script with configuration
        wp_localize_script(
            'humata-chat-widget',
            'humataConfig',
            array(
                'apiUrl'   => esc_url_raw( rest_url( 'humata-chat/v1/ask' ) ),
                'clearUrl' => esc_url_raw( rest_url( 'humata-chat/v1/clear-history' ) ),
                'nonce'    => wp_create_nonce( 'humata_chat' ),
                'wpNonce'  => wp_create_nonce( 'wp_rest' ),
                'theme'    => get_option( 'humata_chat_theme', 'auto' ),
                'autoLinks' => $this->get_auto_links_for_frontend(),
                'maxPromptChars' => $max_prompt_chars,
                'secondLlmProvider' => $second_llm_provider,
                'i18n'     => array(
                    'placeholder'    => __( 'Ask a question...', 'humata-chatbot' ),
                    'send'           => __( 'Send', 'humata-chatbot' ),
                    'clearChat'      => __( 'Clear Chat', 'humata-chatbot' ),
                    'thinking'       => __( 'Thinking...', 'humata-chatbot' ),
                    'welcome'         => __( "Hello! I'm here to help answer your questions. What would you like to know?", 'humata-chatbot' ),
                    'editMessage'     => __( 'Edit message', 'humata-chatbot' ),
                    'regenerate'      => __( 'Regenerate', 'humata-chatbot' ),
                    'cancel'          => __( 'Cancel', 'humata-chatbot' ),
                    'save'            => __( 'Save', 'humata-chatbot' ),
                    'errorGeneric'   => __( 'An error occurred. Please try again.', 'humata-chatbot' ),
                    'errorRequestFailed' => __( 'Your message request failed. Try again. If problem persists, please contact us.', 'humata-chatbot' ),
                    'errorPromptTooLong' => sprintf( __( 'Message is too long. Maximum is %d characters.', 'humata-chatbot' ), $max_prompt_chars ),
                    'errorNetwork'   => __( 'Network error. Please check your connection.', 'humata-chatbot' ),
                    'errorRateLimit' => __( 'Too many requests. Please wait a moment.', 'humata-chatbot' ),
                    'chatCleared'    => __( 'Chat history cleared.', 'humata-chatbot' ),
                ),
            )
        );
    }

    /**
     * Get auto-link rules for frontend consumption.
     *
     * @since 1.0.0
     * @return array
     */
    private function get_auto_links_for_frontend() {
        $value = get_option( 'humata_auto_links', array() );
        if ( ! is_array( $value ) ) {
            $value = array();
        }

        $rules = array();
        foreach ( $value as $row ) {
            if ( ! is_array( $row ) ) {
                continue;
            }

            $phrase = isset( $row['phrase'] ) ? trim( (string) $row['phrase'] ) : '';
            $url    = isset( $row['url'] ) ? trim( (string) $row['url'] ) : '';

            if ( '' === $phrase || '' === $url ) {
                continue;
            }

            $rules[] = array(
                'phrase' => sanitize_text_field( $phrase ),
                'url'    => esc_url_raw( $url ),
            );

            if ( count( $rules ) >= 200 ) {
                break;
            }
        }

        return array_values( $rules );
    }

    /**
     * Get floating help settings merged with defaults.
     *
     * @since 1.0.0
     * @return array
     */
    private function get_floating_help_settings() {
        $value = get_option( 'humata_floating_help', array() );
        if ( ! is_array( $value ) ) {
            $value = array();
        }

        $defaults = Humata_Chatbot_Admin_Settings::get_default_floating_help_option();
        $value    = wp_parse_args( $value, $defaults );

        if ( ! isset( $value['social'] ) || ! is_array( $value['social'] ) ) {
            $value['social'] = array();
        }
        $value['social'] = wp_parse_args( $value['social'], $defaults['social'] );

        if ( ! isset( $value['external_links'] ) || ! is_array( $value['external_links'] ) ) {
            $value['external_links'] = array();
        }

        if ( ! isset( $value['faq_items'] ) || ! is_array( $value['faq_items'] ) ) {
            $value['faq_items'] = array();
        }

        return $value;
    }

    /**
     * Enqueue floating help menu assets site-wide when enabled.
     *
     * @since 1.0.0
     * @return void
     */
    private function enqueue_floating_help_assets() {
        $settings = $this->get_floating_help_settings();
        if ( empty( $settings['enabled'] ) ) {
            return;
        }

        $css_file = HUMATA_CHATBOT_PATH . 'assets/css/floating-help.css';
        $js_file  = HUMATA_CHATBOT_PATH . 'assets/js/floating-help.js';

        $css_version = file_exists( $css_file ) ? filemtime( $css_file ) : HUMATA_CHATBOT_VERSION;
        $js_version  = file_exists( $js_file ) ? filemtime( $js_file ) : HUMATA_CHATBOT_VERSION;

        wp_enqueue_style(
            'humata-floating-help',
            HUMATA_CHATBOT_URL . 'assets/css/floating-help.css',
            array(),
            $css_version
        );

        wp_enqueue_script(
            'humata-floating-help',
            HUMATA_CHATBOT_URL . 'assets/js/floating-help.js',
            array(),
            $js_version,
            true
        );
    }

    /**
     * Render the floating help button, menu, and modals.
     *
     * @since 1.0.0
     * @return void
     */
    public function render_floating_help() {
        $settings = $this->get_floating_help_settings();
        if ( empty( $settings['enabled'] ) ) {
            return;
        }

        $button_label = isset( $settings['button_label'] ) ? trim( (string) $settings['button_label'] ) : 'Help';
        if ( '' === $button_label ) {
            $button_label = 'Help';
        }

        $external_links = isset( $settings['external_links'] ) && is_array( $settings['external_links'] ) ? $settings['external_links'] : array();
        $external_links_clean = array();
        foreach ( $external_links as $row ) {
            if ( ! is_array( $row ) ) {
                continue;
            }
            $label = isset( $row['label'] ) ? trim( (string) $row['label'] ) : '';
            $url   = isset( $row['url'] ) ? trim( (string) $row['url'] ) : '';
            if ( '' === $label || '' === $url ) {
                continue;
            }
            $external_links_clean[] = array(
                'label' => $label,
                'url'   => $url,
            );
        }

        $faq_items = isset( $settings['faq_items'] ) && is_array( $settings['faq_items'] ) ? $settings['faq_items'] : array();
        $faq_items_clean = array();
        foreach ( $faq_items as $row ) {
            if ( ! is_array( $row ) ) {
                continue;
            }
            $q = isset( $row['question'] ) ? trim( (string) $row['question'] ) : '';
            $a = isset( $row['answer'] ) ? trim( (string) $row['answer'] ) : '';
            if ( '' === $q || '' === $a ) {
                continue;
            }
            $faq_items_clean[] = array(
                'question' => $q,
                'answer'   => $a,
            );
        }

        $show_faq = ! empty( $settings['show_faq'] ) && ! empty( $faq_items_clean );
        $faq_label = isset( $settings['faq_label'] ) ? trim( (string) $settings['faq_label'] ) : 'FAQs';
        if ( '' === $faq_label ) {
            $faq_label = 'FAQs';
        }

        $contact_html = isset( $settings['contact_html'] ) ? trim( (string) $settings['contact_html'] ) : '';
        $show_contact = ! empty( $settings['show_contact'] ) && '' !== $contact_html;
        $contact_label = isset( $settings['contact_label'] ) ? trim( (string) $settings['contact_label'] ) : 'Contact';
        if ( '' === $contact_label ) {
            $contact_label = 'Contact';
        }

        $social = isset( $settings['social'] ) && is_array( $settings['social'] ) ? $settings['social'] : array();
        $social_map = array(
            'facebook'  => isset( $social['facebook'] ) ? trim( (string) $social['facebook'] ) : '',
            'instagram' => isset( $social['instagram'] ) ? trim( (string) $social['instagram'] ) : '',
            'youtube'   => isset( $social['youtube'] ) ? trim( (string) $social['youtube'] ) : '',
            'x'         => isset( $social['x'] ) ? trim( (string) $social['x'] ) : '',
            'tiktok'    => isset( $social['tiktok'] ) ? trim( (string) $social['tiktok'] ) : '',
        );
        $has_social = false;
        foreach ( $social_map as $url ) {
            if ( '' !== $url ) {
                $has_social = true;
                break;
            }
        }

        $footer_text = isset( $settings['footer_text'] ) ? trim( (string) $settings['footer_text'] ) : '';
        $has_footer  = '' !== $footer_text;

        $has_external = ! empty( $external_links_clean );
        $has_modals   = $show_faq || $show_contact;
        $has_panel    = $has_external || $has_modals || $has_social || $has_footer;

        ?>
        <div id="humata-floating-help" class="humata-floating-help" aria-live="polite">
            <div class="humata-floating-help__wrapper">
                <button
                    type="button"
                    class="humata-floating-help__button"
                    aria-haspopup="true"
                    aria-expanded="false"
                    <?php echo $has_panel ? 'aria-controls="humata-floating-help-panel"' : ''; ?>
                >
                    <span class="humata-floating-help__icon" aria-hidden="true">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"></circle>
                            <path d="M12 16v-4"></path>
                            <path d="M12 8h.01"></path>
                        </svg>
                    </span>
                    <span class="humata-floating-help__label"><?php echo esc_html( $button_label ); ?></span>
                </button>

                <?php if ( $has_panel ) : ?>
                    <div
                        id="humata-floating-help-panel"
                        class="humata-floating-help__panel"
                        role="menu"
                        aria-hidden="true"
                    >
                        <?php if ( $has_external ) : ?>
                            <div class="humata-floating-help__section">
                                <ul class="humata-floating-help__list" role="none">
                                    <?php foreach ( $external_links_clean as $link ) : ?>
                                        <li role="none">
                                            <a
                                                class="humata-floating-help__link"
                                                role="menuitem"
                                                href="<?php echo esc_url( $link['url'] ); ?>"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                            ><?php echo esc_html( $link['label'] ); ?></a>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <?php if ( $has_modals ) : ?>
                            <div class="humata-floating-help__section">
                                <ul class="humata-floating-help__list" role="none">
                                    <?php if ( $show_faq ) : ?>
                                        <li role="none">
                                            <button type="button" class="humata-floating-help__link humata-floating-help__modal-trigger" role="menuitem" data-humata-help-open-modal="faq">
                                                <?php echo esc_html( $faq_label ); ?>
                                            </button>
                                        </li>
                                    <?php endif; ?>
                                    <?php if ( $show_contact ) : ?>
                                        <li role="none">
                                            <button type="button" class="humata-floating-help__link humata-floating-help__modal-trigger" role="menuitem" data-humata-help-open-modal="contact">
                                                <?php echo esc_html( $contact_label ); ?>
                                            </button>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <?php if ( $has_social ) : ?>
                            <div class="humata-floating-help__section">
                                <div class="humata-floating-help__social" aria-label="<?php esc_attr_e( 'Social links', 'humata-chatbot' ); ?>">
                                    <?php foreach ( $social_map as $network => $url ) : ?>
                                        <?php if ( '' === $url ) : ?>
                                            <?php continue; ?>
                                        <?php endif; ?>
                                        <a
                                            class="humata-floating-help__social-link humata-social-<?php echo esc_attr( $network ); ?>"
                                            href="<?php echo esc_url( $url ); ?>"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            aria-label="<?php echo esc_attr( ucfirst( $network ) ); ?>"
                                        >
                                            <?php echo $this->get_social_icon_svg( $network ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if ( $has_footer ) : ?>
                            <div class="humata-floating-help__section">
                                <div class="humata-floating-help__footer">
                                    <?php echo wp_kses_post( wpautop( $footer_text ) ); ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if ( $show_faq ) : ?>
            <div id="humata-help-modal-faq" class="humata-help-modal" hidden aria-hidden="true">
                <div class="humata-help-modal__overlay" data-humata-help-close-modal></div>
                <div class="humata-help-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="humata-help-modal-faq-title">
                    <button type="button" class="humata-help-modal__close" data-humata-help-close-modal aria-label="<?php esc_attr_e( 'Close', 'humata-chatbot' ); ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M18 6 6 18"></path>
                            <path d="m6 6 12 12"></path>
                        </svg>
                    </button>
                    <h2 id="humata-help-modal-faq-title" class="humata-help-modal__title"><?php echo esc_html( $faq_label ); ?></h2>
                    <div class="humata-help-modal__content">
                        <div class="humata-help-faq">
                            <?php foreach ( $faq_items_clean as $item ) : ?>
                                <details class="humata-help-faq__item">
                                    <summary class="humata-help-faq__question"><?php echo esc_html( $item['question'] ); ?></summary>
                                    <div class="humata-help-faq__answer"><?php echo wp_kses_post( wpautop( esc_html( $item['answer'] ) ) ); ?></div>
                                </details>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if ( $show_contact ) : ?>
            <div id="humata-help-modal-contact" class="humata-help-modal" hidden aria-hidden="true">
                <div class="humata-help-modal__overlay" data-humata-help-close-modal></div>
                <div class="humata-help-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="humata-help-modal-contact-title">
                    <button type="button" class="humata-help-modal__close" data-humata-help-close-modal aria-label="<?php esc_attr_e( 'Close', 'humata-chatbot' ); ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M18 6 6 18"></path>
                            <path d="m6 6 12 12"></path>
                        </svg>
                    </button>
                    <h2 id="humata-help-modal-contact-title" class="humata-help-modal__title"><?php echo esc_html( $contact_label ); ?></h2>
                    <div class="humata-help-modal__content">
                        <div class="humata-help-contact">
                            <?php echo wp_kses_post( wpautop( $contact_html ) ); ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        <?php
    }

    /**
     * Return inline SVG icons for supported social networks.
     *
     * @since 1.0.0
     * @param string $network
     * @return string
     */
    private function get_social_icon_svg( $network ) {
        $network = strtolower( (string) $network );

        // Simple, lightweight inline icons (stroke-based) to avoid external dependencies.
        $icons = array(
            'facebook'  => '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3V2z"/></svg>',
            'instagram' => '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="5" ry="5"/><path d="M16 11.37a4 4 0 1 1-7.37 2.63 4 4 0 0 1 7.37-2.63z"/><path d="M17.5 6.5h.01"/></svg>',
            'youtube'   => '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M22.54 6.42a2.78 2.78 0 0 0-1.94-2C18.88 4 12 4 12 4s-6.88 0-8.6.46a2.78 2.78 0 0 0-1.94 2A29 29 0 0 0 1 12a29 29 0 0 0 .46 5.58 2.78 2.78 0 0 0 1.94 2C5.12 20 12 20 12 20s6.88 0 8.6-.46a2.78 2.78 0 0 0 1.94-2A29 29 0 0 0 23 12a29 29 0 0 0-.46-5.58z"/><path d="m10 15 5-3-5-3z"/></svg>',
            'x'         => '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4l16 16"/><path d="M20 4 4 20"/></svg>',
            'tiktok'    => '<svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 3v10.5a4.5 4.5 0 1 1-4-4.47"/><path d="M14 3c1.2 3.6 3.6 6 7 6"/></svg>',
        );

        if ( isset( $icons[ $network ] ) ) {
            return $icons[ $network ];
        }

        return '';
    }

    /**
     * Render the shortcode.
     *
     * @since 1.0.0
     * @param array $atts Shortcode attributes.
     * @return string Shortcode output.
     */
    public function render_shortcode( $atts ) {
        // Parse attributes
        $atts = shortcode_atts(
            array(
                'height' => '600px',
            ),
            $atts,
            'humata_chat'
        );

        // Enqueue assets for shortcode
        $this->enqueue_chat_assets();

        // Build the chat container HTML
        $height = esc_attr( $atts['height'] );

        $max_prompt_chars = absint( get_option( 'humata_max_prompt_chars', 3000 ) );
        if ( $max_prompt_chars <= 0 ) {
            $max_prompt_chars = 3000;
        }
        if ( $max_prompt_chars > 100000 ) {
            $max_prompt_chars = 100000;
        }

        ob_start();
        ?>
        <div id="humata-chat-container" class="humata-chat-embedded" style="height: <?php echo $height; ?>;">
            <div id="humata-chat-header">
                <div class="humata-header-left">
                    <span class="humata-chat-title"><?php esc_html_e( 'Chat with AI', 'humata-chatbot' ); ?></span>
                </div>
                <div class="humata-header-right">
                    <button type="button" id="humata-export-pdf" title="<?php esc_attr_e( 'Export PDF', 'humata-chatbot' ); ?>" aria-label="<?php esc_attr_e( 'Export PDF', 'humata-chatbot' ); ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                            <polyline points="7 10 12 15 17 10"></polyline>
                            <line x1="12" y1="15" x2="12" y2="3"></line>
                        </svg>
                    </button>
                    <button type="button" id="humata-clear-chat" title="<?php esc_attr_e( 'Clear Chat', 'humata-chatbot' ); ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M3 6h18"></path>
                            <path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path>
                            <path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path>
                        </svg>
                    </button>
                </div>
            </div>
            <div id="humata-chat-messages"></div>
            <div id="humata-chat-input-container">
                <button type="button" id="humata-scroll-toggle" title="<?php esc_attr_e( 'Scroll to bottom', 'humata-chatbot' ); ?>" aria-label="<?php esc_attr_e( 'Scroll to bottom', 'humata-chatbot' ); ?>" data-label-bottom="<?php esc_attr_e( 'Scroll to bottom', 'humata-chatbot' ); ?>" data-label-top="<?php esc_attr_e( 'Scroll to top', 'humata-chatbot' ); ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="6 9 12 15 18 9"></polyline>
                    </svg>
                </button>
                <div class="humata-input-wrapper">
                    <textarea
                        id="humata-chat-input"
                        placeholder="<?php esc_attr_e( 'Ask a question...', 'humata-chatbot' ); ?>"
                        maxlength="<?php echo esc_attr( $max_prompt_chars ); ?>"
                        rows="1"
                    ></textarea>
                    <button type="button" id="humata-send-button" title="<?php esc_attr_e( 'Send', 'humata-chatbot' ); ?>" disabled>
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                            <line x1="22" y1="2" x2="11" y2="13"></line>
                            <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                        </svg>
                    </button>
                </div>
                <div class="humata-input-meta">
                    <span id="humata-chat-input-counter" class="humata-input-counter" aria-live="polite"><?php echo esc_html( '0/' . $max_prompt_chars ); ?></span>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}
