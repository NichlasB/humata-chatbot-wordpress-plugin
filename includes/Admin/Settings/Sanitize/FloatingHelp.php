<?php
/**
 * Floating Help sanitizers + defaults
 *
 * @package Humata_Chatbot
 * @since 1.0.0
 */

defined( 'ABSPATH' ) || exit;

trait Humata_Chatbot_Admin_Settings_Sanitize_FloatingHelp_Trait {

    /**
     * Get default floating help menu option shape/content.
     *
     * @since 1.0.0
     * @return array
     */
    public static function get_default_floating_help_option() {
        $contact_html = ''
            . '<h2>Dr. Morse Office Contact Information</h2>'
            . '<h3>Handcrafted Botanical Formulas</h3>'
            . '<p><strong>Function:</strong> Sells Dr. Morse&#8217;s &ldquo;Handcrafted Botanical Formulas&rdquo; herbal formula line and &ldquo;Doctor Morse&#8217;s&rdquo; herbal formula line</p>'
            . '<p><strong>Website:</strong> <a href="https://handcraftedbotanicalformulas.com/" target="_blank" rel="noopener noreferrer">https://handcraftedbotanicalformulas.com/</a></p>'
            . '<p><strong>Phone:</strong> <a href="tel:+19416231313">+1 (941) 623-1313</a></p>'
            . '<p><strong>Email:</strong> <a href="mailto:info@handcraftedbotanicals.com">info@handcraftedbotanicals.com</a></p>'
            . '<p><strong>Contact page:</strong> <a href="https://handcraftedbotanicalformulas.com/contact/" target="_blank" rel="noopener noreferrer">https://handcraftedbotanicalformulas.com/contact/</a></p>'
            . '<hr>'
            . '<h3>Morse&#8217;s Health Center</h3>'
            . '<p><strong>Function:</strong> Provides detoxification consultation services with a qualified Detoxification Specialist, sells Dr. Morse&#8217;s &ldquo;Handcrafted Botanical Formulas&rdquo; herbal formula line and &ldquo;Doctor Morse&#8217;s&rdquo; herbal formula line, sells glandular therapy supplements</p>'
            . '<p><strong>Website:</strong> <a href="https://morseshealthcenter.com" target="_blank" rel="noopener noreferrer">https://morseshealthcenter.com</a></p>'
            . '<p><strong>Phone:</strong> <a href="tel:+19412551970">+1 (941) 255-1970</a></p>'
            . '<p><strong>Email:</strong> <a href="mailto:info@morseshealthcenter.com">info@morseshealthcenter.com</a></p>'
            . '<hr>'
            . '<h3>International School of the Healing Arts (&#8220;ISHA&#8221;)</h3>'
            . '<p><strong>Function:</strong> Sells access to Dr. Morse&#8217;s online courses (including &ldquo;Level One&rdquo;, &ldquo;Level Two&rdquo;, and &ldquo;Lymphatic Iridology&rdquo;) as well as courses by other ISHA professors</p>'
            . '<p><strong>Website:</strong> <a href="https://internationalschoolofthehealingarts.com/" target="_blank" rel="noopener noreferrer">https://internationalschoolofthehealingarts.com/</a></p>'
            . '<p><strong>Contact page:</strong> <a href="https://internationalschoolofthehealingarts.com/contact/" target="_blank" rel="noopener noreferrer">https://internationalschoolofthehealingarts.com/contact/</a></p>'
            . '<hr>'
            . '<h3>Handcrafted.Health</h3>'
            . '<p><strong>Function:</strong> Web directory of all of Dr. Morse&#8217;s websites</p>'
            . '<p><strong>Website:</strong> <a href="https://handcrafted.health/" target="_blank" rel="noopener noreferrer">https://handcrafted.health/</a></p>'
            . '<hr>'
            . '<h3>DrMorses.tv</h3>'
            . '<p><strong>Function:</strong> Dr. Morse&#8217;s own video streaming platform where users can watch all his videos and submit health-related questions</p>'
            . '<p><strong>Website:</strong> <a href="https://drmorses.tv/" target="_blank" rel="noopener noreferrer">https://drmorses.tv/</a></p>'
            . '<p><strong>Email:</strong> <a href="mailto:questions@morses.tv">questions@morses.tv</a></p>'
            . '<p><strong>Contact page:</strong> <a href="https://drmorses.tv/contact/" target="_blank" rel="noopener noreferrer">https://drmorses.tv/contact/</a></p>'
            . '<p><strong>Ask a question form:</strong> <a href="https://drmorses.tv/ask/" target="_blank" rel="noopener noreferrer">https://drmorses.tv/ask/</a></p>'
            . '<hr>'
            . '<h3>GrapeGate</h3>'
            . '<p><strong>Function:</strong> International practitioner directory for certified Detoxification Specialists where visitors can find a Dr. Morse-trained practitioner to work with</p>'
            . '<p><strong>Website:</strong> <a href="https://grapegate.com/" target="_blank" rel="noopener noreferrer">https://grapegate.com/</a></p>'
            . '<p><strong>Directory page:</strong> <a href="https://grapegate.com/list-of-isod-practitioners/" target="_blank" rel="noopener noreferrer">https://grapegate.com/list-of-isod-practitioners/</a></p>'
            . '<hr>'
            . '<h3>Best places to contact</h3>'
            . '<ul>'
            . '<li><strong>Non-urgent health questions for Dr. Morse:</strong> Use <a href="https://drmorses.tv/ask/" target="_blank" rel="noopener noreferrer">https://drmorses.tv/ask/</a> or email <a href="mailto:questions@morses.tv">questions@morses.tv</a></li>'
            . '<li><strong>Questions about herbal formulas:</strong> Use <a href="https://handcraftedbotanicalformulas.com/contact/" target="_blank" rel="noopener noreferrer">https://handcraftedbotanicalformulas.com/contact/</a> or email <a href="mailto:info@handcraftedbotanicals.com">info@handcraftedbotanicals.com</a></li>'
            . '<li><strong>Consultation appointments:</strong> Use <a href="https://morseshealthcenter.com/contact/" target="_blank" rel="noopener noreferrer">https://morseshealthcenter.com/contact/</a> or email <a href="mailto:info@morseshealthcenter.com">info@morseshealthcenter.com</a></li>'
            . '<li><strong>Questions about ISHA courses:</strong> Use <a href="https://internationalschoolofthehealingarts.com/contact/" target="_blank" rel="noopener noreferrer">https://internationalschoolofthehealingarts.com/contact/</a></li>'
            . '</ul>'
            . '<p><strong>Urgent health situations:</strong> Contact your local emergency services.</p>';

        return array(
            'enabled'        => 0,
            'button_label'   => 'Help',
            'external_links' => array(),
            'show_faq'       => 1,
            'faq_label'      => 'FAQs',
            'show_contact'   => 1,
            'contact_label'  => 'Contact',
            'social'         => array(
                'facebook'  => '',
                'instagram' => '',
                'youtube'   => '',
                'x'         => '',
                'tiktok'    => '',
            ),
            'footer_text'   => '',
            'faq_items'     => array(
                array(
                    'question' => 'How do I use the Help menu?',
                    'answer'   => 'On desktop, hover over the Help button to open the menu. On mobile or touch devices, tap the Help button to open or close it.',
                ),
                array(
                    'question' => 'Where can I find answers to common questions?',
                    'answer'   => 'Open the Help menu and click the FAQs link to view common questions and answers in a full-screen popup.',
                ),
                array(
                    'question' => 'How do I contact support?',
                    'answer'   => 'Open the Help menu and click Contact to view the best ways to reach the appropriate team for your question.',
                ),
            ),
            'contact_html'  => $contact_html,
        );
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

        $defaults = self::get_default_floating_help_option();
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
     * Sanitize floating help settings.
     *
     * @since 1.0.0
     * @param mixed $value
     * @return array
     */
    public function sanitize_floating_help( $value ) {
        $defaults = self::get_default_floating_help_option();

        if ( ! is_array( $value ) ) {
            $value = array();
        }

        $clean = array();

        $clean['enabled']      = empty( $value['enabled'] ) ? 0 : 1;
        $clean['show_faq']     = empty( $value['show_faq'] ) ? 0 : 1;
        $clean['show_contact'] = empty( $value['show_contact'] ) ? 0 : 1;

        $button_label = isset( $value['button_label'] ) ? sanitize_text_field( (string) $value['button_label'] ) : $defaults['button_label'];
        $clean['button_label'] = '' !== $button_label ? $button_label : $defaults['button_label'];

        $faq_label = isset( $value['faq_label'] ) ? sanitize_text_field( (string) $value['faq_label'] ) : $defaults['faq_label'];
        $clean['faq_label'] = '' !== $faq_label ? $faq_label : $defaults['faq_label'];

        $contact_label = isset( $value['contact_label'] ) ? sanitize_text_field( (string) $value['contact_label'] ) : $defaults['contact_label'];
        $clean['contact_label'] = '' !== $contact_label ? $contact_label : $defaults['contact_label'];

        // External links
        $external_links = array();
        if ( isset( $value['external_links'] ) && is_array( $value['external_links'] ) ) {
            foreach ( $value['external_links'] as $row ) {
                if ( ! is_array( $row ) ) {
                    continue;
                }

                $label = isset( $row['label'] ) ? sanitize_text_field( (string) $row['label'] ) : '';
                $url   = isset( $row['url'] ) ? esc_url_raw( (string) $row['url'] ) : '';

                if ( '' === $label || '' === $url ) {
                    continue;
                }

                $external_links[] = array(
                    'label' => $label,
                    'url'   => $url,
                );

                if ( count( $external_links ) >= 50 ) {
                    break;
                }
            }
        }
        $clean['external_links'] = array_values( $external_links );

        // Social links
        $social_in = isset( $value['social'] ) && is_array( $value['social'] ) ? $value['social'] : array();
        $clean['social'] = array();
        foreach ( array( 'facebook', 'instagram', 'youtube', 'x', 'tiktok' ) as $key ) {
            $clean['social'][ $key ] = isset( $social_in[ $key ] ) ? esc_url_raw( (string) $social_in[ $key ] ) : '';
        }

        // Footer text (allow basic formatting/links).
        $footer_text = isset( $value['footer_text'] ) ? trim( (string) $value['footer_text'] ) : '';
        $clean['footer_text'] = '' === $footer_text ? '' : wp_kses_post( $footer_text );

        // FAQ items (plain text answers).
        $faq_items = array();
        if ( isset( $value['faq_items'] ) && is_array( $value['faq_items'] ) ) {
            foreach ( $value['faq_items'] as $row ) {
                if ( ! is_array( $row ) ) {
                    continue;
                }

                $question = isset( $row['question'] ) ? sanitize_text_field( (string) $row['question'] ) : '';
                $answer   = isset( $row['answer'] ) ? sanitize_textarea_field( (string) $row['answer'] ) : '';

                if ( '' === $question || '' === $answer ) {
                    continue;
                }

                $faq_items[] = array(
                    'question' => $question,
                    'answer'   => $answer,
                );

                if ( count( $faq_items ) >= 50 ) {
                    break;
                }
            }
        }
        $clean['faq_items'] = array_values( $faq_items );

        // Contact HTML (allow safe HTML).
        $contact_html = isset( $value['contact_html'] ) ? trim( (string) $value['contact_html'] ) : '';
        $clean['contact_html'] = '' === $contact_html ? '' : wp_kses_post( $contact_html );

        return $clean;
    }
}















