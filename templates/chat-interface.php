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

$medical_disclaimer_text = get_option( 'humata_medical_disclaimer_text', '' );
if ( ! is_string( $medical_disclaimer_text ) ) {
    $medical_disclaimer_text = '';
}
$medical_disclaimer_text = trim( $medical_disclaimer_text );

$footer_copyright_text = get_option( 'humata_footer_copyright_text', '' );
if ( ! is_string( $footer_copyright_text ) ) {
    $footer_copyright_text = '';
}
$footer_copyright_text = trim( $footer_copyright_text );

// Trigger pages.
$trigger_pages = get_option( 'humata_trigger_pages', array() );
if ( ! is_array( $trigger_pages ) ) {
    $trigger_pages = array();
}
$trigger_pages_clean = array();
foreach ( $trigger_pages as $page ) {
    if ( ! is_array( $page ) ) {
        continue;
    }
    $title     = isset( $page['title'] ) ? trim( (string) $page['title'] ) : '';
    $link_text = isset( $page['link_text'] ) ? trim( (string) $page['link_text'] ) : '';
    $content   = isset( $page['content'] ) ? trim( (string) $page['content'] ) : '';
    if ( '' === $title || '' === $link_text || '' === $content ) {
        continue;
    }
    $trigger_pages_clean[] = array(
        'title'     => $title,
        'link_text' => $link_text,
        'content'   => $content,
    );
}
$has_trigger_pages = ! empty( $trigger_pages_clean );

// Logo settings.
$logo_url      = get_option( 'humata_logo_url', '' );
$logo_url_dark = get_option( 'humata_logo_url_dark', '' );

// Avatar settings.
$bot_avatar_url = get_option( 'humata_bot_avatar_url', '' );
$avatar_size    = absint( get_option( 'humata_avatar_size', 40 ) );
if ( $avatar_size < 32 ) {
    $avatar_size = 32;
}
if ( $avatar_size > 64 ) {
    $avatar_size = 64;
}

// SEO settings: determine if we should output noindex.
// Allow indexing only on homepage when the option is enabled.
$allow_seo_indexing = get_option( 'humata_allow_seo_indexing', false );
$is_homepage_mode   = is_front_page();
$should_noindex     = ! ( $is_homepage_mode && $allow_seo_indexing );
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?> class="<?php echo esc_attr( $theme_class ); ?>">
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, interactive-widget=resizes-content">
    <?php if ( $should_noindex ) : ?>
        <meta name="robots" content="noindex, nofollow">
    <?php endif; ?>
    <?php if ( $should_noindex ) : ?>
        <title><?php echo esc_html( get_bloginfo( 'name' ) ); ?> - <?php esc_html_e( 'Chat', 'humata-chatbot' ); ?></title>
    <?php endif; ?>
    <style>:root { --humata-avatar-size: <?php echo esc_attr( $avatar_size ); ?>px; }</style>
    <?php wp_head(); ?>
</head>
<body class="humata-chat-body <?php echo esc_attr( $theme_class ); ?>">
    <div id="humata-chat-wrapper">
        <header id="humata-chat-header">
            <div class="humata-header-left">
                <?php if ( ! empty( $logo_url ) || ! empty( $logo_url_dark ) ) : ?>
                    <?php if ( ! empty( $logo_url ) ) : ?>
                        <img class="humata-header-logo humata-header-logo--light" src="<?php echo esc_url( $logo_url ); ?>" alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>" />
                    <?php endif; ?>
                    <?php if ( ! empty( $logo_url_dark ) ) : ?>
                        <img class="humata-header-logo humata-header-logo--dark" src="<?php echo esc_url( $logo_url_dark ); ?>" alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>" />
                    <?php endif; ?>
                <?php else : ?>
                    <svg class="humata-logo" xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                    </svg>
                    <span class="humata-chat-title"><?php echo esc_html( get_bloginfo( 'name' ) ); ?></span>
                <?php endif; ?>
            </div>
            <div class="humata-header-center"></div>
            <div class="humata-header-right">
                <button type="button" id="humata-export-pdf" title="<?php esc_attr_e( 'Export PDF', 'humata-chatbot' ); ?>" aria-label="<?php esc_attr_e( 'Export PDF', 'humata-chatbot' ); ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                        <polyline points="7 10 12 15 17 10"></polyline>
                        <line x1="12" y1="15" x2="12" y2="3"></line>
                    </svg>
                </button>
                <button type="button" id="humata-clear-chat" title="<?php esc_attr_e( 'Clear Chat', 'humata-chatbot' ); ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M3 6h18"></path>
                        <path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"></path>
                        <path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"></path>
                    </svg>
                </button>
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
            </div>
        </header>
        <div id="humata-chat-container" class="humata-chat-fullpage">
            <main id="humata-chat-messages">
                <div id="humata-welcome-message" class="humata-message humata-message-bot">
                    <div class="humata-message-avatar">
                        <?php if ( ! empty( $bot_avatar_url ) ) : ?>
                            <img class="humata-avatar-img" src="<?php echo esc_url( $bot_avatar_url ); ?>" alt="<?php esc_attr_e( 'Bot', 'humata-chatbot' ); ?>" />
                        <?php else : ?>
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M12 8V4H8"></path>
                                <rect width="16" height="12" x="4" y="8" rx="2"></rect>
                                <path d="M2 14h2"></path>
                                <path d="M20 14h2"></path>
                                <path d="M15 13v2"></path>
                                <path d="M9 13v2"></path>
                            </svg>
                        <?php endif; ?>
                    </div>
                    <div class="humata-message-content">
                        <p><?php esc_html_e( 'Hi! Ask any Dr. Morse related question and I\'ll do my best to provide an answer. 🙂', 'humata-chatbot' ); ?></p>
                    </div>
                </div>
            </main>

            <footer id="humata-chat-input-container">
                <button type="button" id="humata-scroll-toggle" title="<?php esc_attr_e( 'Scroll to bottom', 'humata-chatbot' ); ?>" aria-label="<?php esc_attr_e( 'Scroll to bottom', 'humata-chatbot' ); ?>" data-label-bottom="<?php esc_attr_e( 'Scroll to bottom', 'humata-chatbot' ); ?>" data-label-top="<?php esc_attr_e( 'Scroll to top', 'humata-chatbot' ); ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="6 9 12 15 18 9"></polyline>
                    </svg>
                </button>
                <div id="humata-suggested-questions" class="humata-suggested-questions" style="display: none;"></div>
                <div id="humata-turnstile-container" style="display: none;"></div>
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
                    <?php if ( '' !== $medical_disclaimer_text ) : ?>
                        <button type="button" id="humata-medical-disclaimer-link" class="humata-chat-footer-link"><?php esc_html_e( 'Medical disclaimer', 'humata-chatbot' ); ?></button>
                    <?php endif; ?>
                </p>
            </footer>
        </div>
    </div>
    <?php if ( $has_trigger_pages ) : ?>
        <nav class="humata-trigger-pages" aria-label="<?php esc_attr_e( 'Page links', 'humata-chatbot' ); ?>">
            <ul class="humata-trigger-pages__list">
                <?php foreach ( $trigger_pages_clean as $idx => $tpage ) : ?>
                    <li>
                        <button type="button" class="humata-trigger-pages__link" data-humata-page-modal="<?php echo esc_attr( (string) $idx ); ?>">
                            <?php echo esc_html( $tpage['link_text'] ); ?>
                        </button>
                    </li>
                <?php endforeach; ?>
            </ul>
        </nav>
    <?php endif; ?>
    <?php if ( '' !== $footer_copyright_text ) : ?>
        <footer id="humata-chat-page-footer">
            <div id="humata-chat-page-footer-inner">
                <p><?php echo esc_html( $footer_copyright_text ); ?></p>
            </div>
        </footer>
    <?php endif; ?>
    <?php if ( '' !== $medical_disclaimer_text ) : ?>
        <div id="humata-medical-disclaimer-modal" class="humata-help-modal" hidden aria-hidden="true">
            <div class="humata-help-modal__overlay" data-humata-disclaimer-close></div>
            <div class="humata-help-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="humata-medical-disclaimer-title">
                <button type="button" class="humata-help-modal__close" data-humata-disclaimer-close aria-label="<?php esc_attr_e( 'Close', 'humata-chatbot' ); ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M18 6 6 18"></path>
                        <path d="m6 6 12 12"></path>
                    </svg>
                </button>
                <h2 id="humata-medical-disclaimer-title" class="humata-help-modal__title"><?php esc_html_e( 'Medical Disclaimer', 'humata-chatbot' ); ?></h2>
                <div class="humata-help-modal__content">
                    <?php echo wp_kses_post( wpautop( esc_html( $medical_disclaimer_text ) ) ); ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
    <?php if ( $has_trigger_pages ) : ?>
        <?php foreach ( $trigger_pages_clean as $idx => $tpage ) : ?>
            <div id="humata-page-modal-<?php echo esc_attr( (string) $idx ); ?>" class="humata-help-modal" hidden aria-hidden="true">
                <div class="humata-help-modal__overlay" data-humata-page-close-modal></div>
                <div class="humata-help-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="humata-page-modal-<?php echo esc_attr( (string) $idx ); ?>-title">
                    <button type="button" class="humata-help-modal__close" data-humata-page-close-modal aria-label="<?php esc_attr_e( 'Close', 'humata-chatbot' ); ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M18 6 6 18"></path>
                            <path d="m6 6 12 12"></path>
                        </svg>
                    </button>
                    <h2 id="humata-page-modal-<?php echo esc_attr( (string) $idx ); ?>-title" class="humata-help-modal__title"><?php echo esc_html( $tpage['title'] ); ?></h2>
                    <div class="humata-help-modal__content">
                        <?php echo wp_kses_post( wpautop( $tpage['content'] ) ); ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
    <?php wp_footer(); ?>
</body>
</html>
