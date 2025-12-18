<?php
/**
 * Chat Interface Template
 *
 * Full-page chat interface template.
 *
 * @package Humata_Chatbot
 * @since 1.0.0
 */

defined( 'ABSPATH' ) || exit;

// Get theme setting
$theme = get_option( 'humata_chat_theme', 'auto' );
$theme_class = '';
if ( 'dark' === $theme ) {
    $theme_class = 'humata-theme-dark';
} elseif ( 'light' === $theme ) {
    $theme_class = 'humata-theme-light';
}

$max_prompt_chars = absint( get_option( 'humata_max_prompt_chars', 3000 ) );
if ( $max_prompt_chars <= 0 ) {
    $max_prompt_chars = 3000;
}
if ( $max_prompt_chars > 100000 ) {
    $max_prompt_chars = 100000;
}
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?> class="<?php echo esc_attr( $theme_class ); ?>">
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?php echo esc_html( get_bloginfo( 'name' ) ); ?> - <?php esc_html_e( 'Chat', 'humata-chatbot' ); ?></title>
    <?php wp_head(); ?>
</head>
<body class="humata-chat-body <?php echo esc_attr( $theme_class ); ?>">
    <div id="humata-chat-wrapper">
        <div id="humata-chat-container" class="humata-chat-fullpage">
            <header id="humata-chat-header">
                <div class="humata-header-left">
                    <svg class="humata-logo" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                    </svg>
                    <span class="humata-chat-title"><?php echo esc_html( get_bloginfo( 'name' ) ); ?></span>
                </div>
                <div class="humata-header-right">
                    <button type="button" id="humata-theme-toggle" title="<?php esc_attr_e( 'Toggle Theme', 'humata-chatbot' ); ?>">
                        <svg class="humata-icon-sun" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="5"></circle>
                            <line x1="12" y1="1" x2="12" y2="3"></line>
                            <line x1="12" y1="21" x2="12" y2="23"></line>
                            <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line>
                            <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line>
                            <line x1="1" y1="12" x2="3" y2="12"></line>
                            <line x1="21" y1="12" x2="23" y2="12"></line>
                            <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line>
                            <line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line>
                        </svg>
                        <svg class="humata-icon-moon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>
                        </svg>
                    </button>
                    <button type="button" id="humata-clear-chat" title="<?php esc_attr_e( 'Clear Chat', 'humata-chatbot' ); ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M3 6h18"></path>
                            <path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path>
                            <path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path>
                        </svg>
                    </button>
                </div>
            </header>

            <main id="humata-chat-messages">
                <div id="humata-welcome-message" class="humata-message humata-message-bot">
                    <div class="humata-message-avatar">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 8V4H8"></path>
                            <rect width="16" height="12" x="4" y="8" rx="2"></rect>
                            <path d="M2 14h2"></path>
                            <path d="M20 14h2"></path>
                            <path d="M15 13v2"></path>
                            <path d="M9 13v2"></path>
                        </svg>
                    </div>
                    <div class="humata-message-content">
                        <p><?php esc_html_e( 'Hello! I\'m here to help answer your questions. What would you like to know?', 'humata-chatbot' ); ?></p>
                    </div>
                </div>
            </main>

            <footer id="humata-chat-input-container">
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
                        autofocus
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
                <p class="humata-disclaimer">
                    <?php esc_html_e( 'AI responses may not always be accurate. Please verify important information.', 'humata-chatbot' ); ?>
                </p>
            </footer>
        </div>
    </div>
    <?php wp_footer(); ?>
</body>
</html>
