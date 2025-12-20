<?php
/**
 * Floating Help Settings Tab
 *
 * @package Humata_Chatbot
 * @since 1.0.0
 */
defined( 'ABSPATH' ) || exit;

class Humata_Chatbot_Settings_Tab_Floating_Help extends Humata_Chatbot_Settings_Tab_Base {

    public function get_key() {
        return 'floating_help';
    }

    public function get_label() {
        return __( 'Floating Help', 'humata-chatbot' );
    }

    public function get_page_id() {
        return 'humata-chatbot-tab-floating-help';
    }

    public function register() {
        $page_id = $this->get_page_id();

        // Floating Help Menu Section.
        add_settings_section(
            'humata_floating_help_section',
            __( 'Floating Help Menu', 'humata-chatbot' ),
            array( $this->admin, 'render_floating_help_section' ),
            $page_id
        );

        add_settings_field(
            'humata_floating_help_enabled',
            __( 'Enable', 'humata-chatbot' ),
            array( $this->admin, 'render_floating_help_enabled_field' ),
            $page_id,
            'humata_floating_help_section'
        );

        add_settings_field(
            'humata_floating_help_external_links',
            __( 'External Links', 'humata-chatbot' ),
            array( $this->admin, 'render_floating_help_external_links_field' ),
            $page_id,
            'humata_floating_help_section'
        );

        add_settings_field(
            'humata_floating_help_modals',
            __( 'Modal Triggers', 'humata-chatbot' ),
            array( $this->admin, 'render_floating_help_modals_field' ),
            $page_id,
            'humata_floating_help_section'
        );

        add_settings_field(
            'humata_floating_help_social',
            __( 'Social Links', 'humata-chatbot' ),
            array( $this->admin, 'render_floating_help_social_field' ),
            $page_id,
            'humata_floating_help_section'
        );

        add_settings_field(
            'humata_floating_help_footer_text',
            __( 'Footer Text', 'humata-chatbot' ),
            array( $this->admin, 'render_floating_help_footer_text_field' ),
            $page_id,
            'humata_floating_help_section'
        );

        add_settings_field(
            'humata_floating_help_faq_items',
            __( 'FAQ Items', 'humata-chatbot' ),
            array( $this->admin, 'render_floating_help_faq_items_field' ),
            $page_id,
            'humata_floating_help_section'
        );

        add_settings_field(
            'humata_floating_help_contact_html',
            __( 'Contact Modal Content', 'humata-chatbot' ),
            array( $this->admin, 'render_floating_help_contact_html_field' ),
            $page_id,
            'humata_floating_help_section'
        );
    }
}


