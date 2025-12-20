<?php
/**
 * Usage Settings Tab (read-only)
 *
 * @package Humata_Chatbot
 * @since 1.0.0
 */
defined( 'ABSPATH' ) || exit;

class Humata_Chatbot_Settings_Tab_Usage extends Humata_Chatbot_Settings_Tab_Base {

    public function get_key() {
        return 'usage';
    }

    public function get_label() {
        return __( 'Usage', 'humata-chatbot' );
    }

    public function get_page_id() {
        return 'humata-chatbot-tab-usage';
    }

    public function has_form() {
        return false;
    }

    public function register() {
        // No settings to register on this tab (read-only).
    }

    public function render() {
        ?>
        <h2><?php esc_html_e( 'Usage Information', 'humata-chatbot' ); ?></h2>
        <table class="form-table">
            <tr>
                <th scope="row"><?php esc_html_e( 'Shortcode', 'humata-chatbot' ); ?></th>
                <td>
                    <code>[humata_chat]</code>
                    <p class="description">
                        <?php esc_html_e( 'Use this shortcode to embed the chat interface on any page or post.', 'humata-chatbot' ); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th scope="row"><?php esc_html_e( 'Chat Page URL', 'humata-chatbot' ); ?></th>
                <td>
                    <?php
                    $location = get_option( 'humata_chat_location', 'dedicated' );
                    if ( 'dedicated' === $location ) {
                        $slug = get_option( 'humata_chat_page_slug', 'chat' );
                        $url  = home_url( '/' . $slug . '/' );
                        echo '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $url ) . '</a>';
                    } elseif ( 'homepage' === $location ) {
                        echo '<a href="' . esc_url( home_url( '/' ) ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( home_url( '/' ) ) . '</a>';
                        echo '<p class="description">' . esc_html__( 'Chat is displayed on the homepage.', 'humata-chatbot' ) . '</p>';
                    } else {
                        echo '<em>' . esc_html__( 'Use the shortcode to display the chat.', 'humata-chatbot' ) . '</em>';
                    }
                    ?>
                </td>
            </tr>
        </table>
        <?php
    }
}


