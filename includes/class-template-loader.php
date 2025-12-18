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
        if ( ! $this->should_load_assets() ) {
            return;
        }

        $this->enqueue_chat_assets();
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

        $css_version = file_exists( $css_file ) ? filemtime( $css_file ) : HUMATA_CHATBOT_VERSION;
        $js_version  = file_exists( $js_file ) ? filemtime( $js_file ) : HUMATA_CHATBOT_VERSION;

        // Enqueue CSS
        wp_enqueue_style(
            'humata-chat-widget',
            HUMATA_CHATBOT_URL . 'assets/css/chat-widget.css',
            array(),
            $css_version
        );

        // Enqueue JavaScript
        wp_enqueue_script(
            'humata-chat-widget',
            HUMATA_CHATBOT_URL . 'assets/js/chat-widget.js',
            array(),
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
                <span class="humata-chat-title"><?php esc_html_e( 'Chat with AI', 'humata-chatbot' ); ?></span>
                <button type="button" id="humata-clear-chat" title="<?php esc_attr_e( 'Clear Chat', 'humata-chatbot' ); ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M3 6h18"></path>
                        <path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path>
                        <path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path>
                    </svg>
                </button>
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
