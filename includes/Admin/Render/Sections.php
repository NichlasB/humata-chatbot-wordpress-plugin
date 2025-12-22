<?php
/**
 * Admin settings section renderers
 *
 * @package Humata_Chatbot
 * @since 1.0.0
 */

defined( 'ABSPATH' ) || exit;

trait Humata_Chatbot_Admin_Settings_Render_Sections_Trait {

    /**
     * Auto-Links section description.
     *
     * @since 1.0.0
     * @return void
     */
    public function render_auto_links_section() {
        echo '<p>' . esc_html__( 'Define phrase → URL rules. When a phrase appears in bot messages, it will be automatically linked.', 'humata-chatbot' ) . '</p>';
    }

    /**
     * Intent-Based Links section description.
     *
     * @since 1.0.0
     * @return void
     */
    public function render_intent_links_section() {
        echo '<p>' . esc_html__( 'When keywords are detected in a user\'s question, resource links appear as pill buttons at the end of the bot\'s response.', 'humata-chatbot' ) . '</p>';
    }

    /**
     * Floating Help Menu section description.
     *
     * @since 1.0.0
     * @return void
     */
    public function render_floating_help_section() {
        echo '<p>' . esc_html__( 'Configure an optional floating Help button that reveals a menu on hover (desktop) or tap (touch devices).', 'humata-chatbot' ) . '</p>';
    }

    /**
     * Render API section description.
     *
     * @since 1.0.0
     * @return void
     */
    public function render_api_section() {
        echo '<p>' . esc_html__( 'Enter your Humata API credentials. You can find these in your Humata account settings.', 'humata-chatbot' ) . '</p>';
    }

    public function render_prompt_section() {
        echo '<p>' . esc_html__( 'Optional instructions that will be prepended to every user message sent to Humata.', 'humata-chatbot' ) . '</p>';
    }

    /**
     * Render Straico section description.
     *
     * @since 1.0.0
     * @return void
     */
    public function render_straico_section() {
        echo '<p>' . esc_html__( 'Optionally send Humata responses to a second LLM provider for a second-pass review before returning them to users.', 'humata-chatbot' ) . '</p>';
    }

    /**
     * Render display section description.
     *
     * @since 1.0.0
     * @return void
     */
    public function render_display_section() {
        echo '<p>' . esc_html__( 'Configure how and where the chat interface is displayed.', 'humata-chatbot' ) . '</p>';
    }

    /**
     * Render security section description.
     *
     * @since 1.0.0
     * @return void
     */
    public function render_security_section() {
        echo '<p>' . esc_html__( 'Configure security settings to protect against abuse.', 'humata-chatbot' ) . '</p>';
    }

    public function render_disclaimer_section() {
        echo '<p>' . esc_html__( 'Configure disclaimer text shown in the chat interface.', 'humata-chatbot' ) . '</p>';
    }

    /**
     * Render Cloudflare Turnstile section description.
     *
     * @since 1.0.0
     * @return void
     */
    public function render_turnstile_section() {
        ?>
        <p>
            <?php esc_html_e( 'Cloudflare Turnstile provides human verification to protect against bots. Users must complete a challenge before sending their first message.', 'humata-chatbot' ); ?>
        </p>
        <p>
            <a href="https://dash.cloudflare.com/?to=/:account/turnstile" target="_blank" rel="noopener noreferrer">
                <?php esc_html_e( 'Get your Turnstile keys from Cloudflare Dashboard', 'humata-chatbot' ); ?>
            </a>
        </p>
        <?php
    }

    /**
     * Render avatar settings section description.
     *
     * @since 1.0.0
     * @return void
     */
    public function render_avatar_section() {
        echo '<p>' . esc_html__( 'Customize the avatar images displayed for user and bot messages. Upload square images (e.g., 512×512) for best results.', 'humata-chatbot' ) . '</p>';
    }
}


