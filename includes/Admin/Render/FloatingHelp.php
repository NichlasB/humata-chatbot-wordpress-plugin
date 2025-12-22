<?php
/**
 * Floating Help field renderers
 *
 * @package Humata_Chatbot
 * @since 1.0.0
 */

defined( 'ABSPATH' ) || exit;

trait Humata_Chatbot_Admin_Settings_Render_FloatingHelp_Trait {

    public function render_floating_help_enabled_field() {
        $settings = $this->get_floating_help_settings();
        $enabled  = ! empty( $settings['enabled'] ) ? 1 : 0;
        $label    = isset( $settings['button_label'] ) ? (string) $settings['button_label'] : 'Help';
        ?>
        <label>
            <input
                type="checkbox"
                name="humata_floating_help[enabled]"
                value="1"
                <?php checked( $enabled, 1 ); ?>
            />
            <?php esc_html_e( 'Enable the floating Help button and hover menu.', 'humata-chatbot' ); ?>
        </label>
        <p class="description">
            <?php esc_html_e( 'When disabled, no button, menu, or assets are rendered on the frontend.', 'humata-chatbot' ); ?>
        </p>
        <p>
            <label for="humata_floating_help_button_label"><strong><?php esc_html_e( 'Button label', 'humata-chatbot' ); ?></strong></label><br>
            <input
                type="text"
                id="humata_floating_help_button_label"
                name="humata_floating_help[button_label]"
                value="<?php echo esc_attr( $label ); ?>"
                class="regular-text"
            />
        </p>
        <?php
    }

    public function render_floating_help_external_links_field() {
        $settings = $this->get_floating_help_settings();
        $links    = isset( $settings['external_links'] ) && is_array( $settings['external_links'] ) ? $settings['external_links'] : array();

        // Show at least one empty row for UX.
        if ( empty( $links ) ) {
            $links = array(
                array( 'label' => '', 'url' => '' ),
            );
        }

        $next_index = count( $links );
        ?>
        <div class="humata-floating-help-repeater" data-humata-repeater="external_links" data-next-index="<?php echo esc_attr( (string) $next_index ); ?>">
            <p class="description" style="margin-top: 0;">
                <?php esc_html_e( 'Drag and drop rows to reorder.', 'humata-chatbot' ); ?>
            </p>
            <table class="widefat striped" style="max-width: 900px;">
                <thead>
                    <tr>
                        <th style="width: 36px;"><span class="screen-reader-text"><?php esc_html_e( 'Order', 'humata-chatbot' ); ?></span></th>
                        <th><?php esc_html_e( 'Label', 'humata-chatbot' ); ?></th>
                        <th><?php esc_html_e( 'URL (opens in a new tab)', 'humata-chatbot' ); ?></th>
                        <th><?php esc_html_e( 'Actions', 'humata-chatbot' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $links as $i => $row ) : ?>
                        <?php
                        $row = is_array( $row ) ? $row : array();
                        $label = isset( $row['label'] ) ? (string) $row['label'] : '';
                        $url   = isset( $row['url'] ) ? (string) $row['url'] : '';
                        ?>
                        <tr class="humata-repeater-row">
                            <td class="humata-repeater-handle-cell">
                                <span class="dashicons dashicons-move humata-repeater-handle" aria-hidden="true"></span>
                                <span class="screen-reader-text"><?php esc_html_e( 'Drag to reorder', 'humata-chatbot' ); ?></span>
                            </td>
                            <td style="width: 28%;">
                                <input
                                    type="text"
                                    class="regular-text"
                                    name="humata_floating_help[external_links][<?php echo esc_attr( (string) $i ); ?>][label]"
                                    value="<?php echo esc_attr( $label ); ?>"
                                    placeholder="<?php esc_attr_e( 'Label', 'humata-chatbot' ); ?>"
                                />
                            </td>
                            <td>
                                <input
                                    type="url"
                                    class="regular-text"
                                    name="humata_floating_help[external_links][<?php echo esc_attr( (string) $i ); ?>][url]"
                                    value="<?php echo esc_attr( $url ); ?>"
                                    placeholder="<?php esc_attr_e( 'https://example.com/', 'humata-chatbot' ); ?>"
                                />
                            </td>
                            <td style="width: 90px;">
                                <button type="button" class="button link-delete humata-repeater-remove"><?php esc_html_e( 'Remove', 'humata-chatbot' ); ?></button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <p style="margin-top: 10px;">
                <button type="button" class="button button-secondary humata-repeater-add"><?php esc_html_e( 'Add Link', 'humata-chatbot' ); ?></button>
            </p>
            <p class="description">
                <?php esc_html_e( 'These links appear in the top section of the menu and open in a new tab.', 'humata-chatbot' ); ?>
            </p>
        </div>
        <?php
    }

    public function render_floating_help_modals_field() {
        $settings = $this->get_floating_help_settings();

        $show_faq     = ! empty( $settings['show_faq'] ) ? 1 : 0;
        $faq_label    = isset( $settings['faq_label'] ) ? (string) $settings['faq_label'] : 'FAQs';
        $show_contact = ! empty( $settings['show_contact'] ) ? 1 : 0;
        $contact_label = isset( $settings['contact_label'] ) ? (string) $settings['contact_label'] : 'Contact';
        ?>
        <fieldset>
            <label style="display:block; margin-bottom: 10px;">
                <input
                    type="checkbox"
                    name="humata_floating_help[show_faq]"
                    value="1"
                    <?php checked( $show_faq, 1 ); ?>
                />
                <?php esc_html_e( 'Show FAQ modal trigger', 'humata-chatbot' ); ?>
            </label>
            <p style="margin: 0 0 16px 0;">
                <label for="humata_floating_help_faq_label"><strong><?php esc_html_e( 'FAQ link label', 'humata-chatbot' ); ?></strong></label><br>
                <input
                    type="text"
                    id="humata_floating_help_faq_label"
                    name="humata_floating_help[faq_label]"
                    value="<?php echo esc_attr( $faq_label ); ?>"
                    class="regular-text"
                />
            </p>

            <label style="display:block; margin-bottom: 10px;">
                <input
                    type="checkbox"
                    name="humata_floating_help[show_contact]"
                    value="1"
                    <?php checked( $show_contact, 1 ); ?>
                />
                <?php esc_html_e( 'Show Contact modal trigger', 'humata-chatbot' ); ?>
            </label>
            <p style="margin: 0;">
                <label for="humata_floating_help_contact_label"><strong><?php esc_html_e( 'Contact link label', 'humata-chatbot' ); ?></strong></label><br>
                <input
                    type="text"
                    id="humata_floating_help_contact_label"
                    name="humata_floating_help[contact_label]"
                    value="<?php echo esc_attr( $contact_label ); ?>"
                    class="regular-text"
                />
            </p>
        </fieldset>
        <p class="description">
            <?php esc_html_e( 'These links open full-screen modal overlays.', 'humata-chatbot' ); ?>
        </p>
        <?php
    }

    public function render_floating_help_social_field() {
        $settings = $this->get_floating_help_settings();
        $social   = isset( $settings['social'] ) && is_array( $settings['social'] ) ? $settings['social'] : array();
        $keys     = array(
            'facebook'  => __( 'Facebook', 'humata-chatbot' ),
            'instagram' => __( 'Instagram', 'humata-chatbot' ),
            'youtube'   => __( 'YouTube', 'humata-chatbot' ),
            'x'         => __( 'X', 'humata-chatbot' ),
            'tiktok'    => __( 'TikTok', 'humata-chatbot' ),
        );
        ?>
        <table class="form-table" role="presentation" style="margin-top: 0;">
            <tbody>
                <?php foreach ( $keys as $key => $label ) : ?>
                    <?php $url = isset( $social[ $key ] ) ? (string) $social[ $key ] : ''; ?>
                    <tr>
                        <th scope="row" style="padding: 10px 0 10px 0;"><?php echo esc_html( $label ); ?></th>
                        <td style="padding: 10px 0 10px 0;">
                            <input
                                type="url"
                                class="regular-text"
                                name="humata_floating_help[social][<?php echo esc_attr( $key ); ?>]"
                                value="<?php echo esc_attr( $url ); ?>"
                                placeholder="<?php esc_attr_e( 'https://', 'humata-chatbot' ); ?>"
                            />
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <p class="description">
            <?php esc_html_e( 'Only social icons with a URL set will be displayed.', 'humata-chatbot' ); ?>
        </p>
        <?php
    }

    public function render_floating_help_footer_text_field() {
        $settings = $this->get_floating_help_settings();
        $text     = isset( $settings['footer_text'] ) ? (string) $settings['footer_text'] : '';
        ?>
        <textarea
            class="large-text"
            rows="4"
            name="humata_floating_help[footer_text]"
        ><?php echo esc_textarea( $text ); ?></textarea>
        <p class="description">
            <?php esc_html_e( 'Optional text shown at the bottom of the menu. Basic formatting and links are allowed.', 'humata-chatbot' ); ?>
        </p>
        <?php
    }

    public function render_floating_help_faq_items_field() {
        $settings = $this->get_floating_help_settings();
        $items    = isset( $settings['faq_items'] ) && is_array( $settings['faq_items'] ) ? $settings['faq_items'] : array();

        if ( empty( $items ) ) {
            $items = array(
                array( 'question' => '', 'answer' => '' ),
            );
        }

        $next_index = count( $items );
        ?>
        <div class="humata-floating-help-repeater" data-humata-repeater="faq_items" data-next-index="<?php echo esc_attr( (string) $next_index ); ?>">
            <p class="description" style="margin-top: 0;">
                <?php esc_html_e( 'Drag and drop rows to reorder.', 'humata-chatbot' ); ?>
            </p>
            <table class="widefat striped" style="max-width: 900px;">
                <thead>
                    <tr>
                        <th style="width: 36px;"><span class="screen-reader-text"><?php esc_html_e( 'Order', 'humata-chatbot' ); ?></span></th>
                        <th><?php esc_html_e( 'Question', 'humata-chatbot' ); ?></th>
                        <th><?php esc_html_e( 'Answer', 'humata-chatbot' ); ?></th>
                        <th><?php esc_html_e( 'Actions', 'humata-chatbot' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $items as $i => $row ) : ?>
                        <?php
                        $row = is_array( $row ) ? $row : array();
                        $q = isset( $row['question'] ) ? (string) $row['question'] : '';
                        $a = isset( $row['answer'] ) ? (string) $row['answer'] : '';
                        ?>
                        <tr class="humata-repeater-row">
                            <td class="humata-repeater-handle-cell">
                                <span class="dashicons dashicons-move humata-repeater-handle" aria-hidden="true"></span>
                                <span class="screen-reader-text"><?php esc_html_e( 'Drag to reorder', 'humata-chatbot' ); ?></span>
                            </td>
                            <td style="width: 34%;">
                                <input
                                    type="text"
                                    class="regular-text"
                                    name="humata_floating_help[faq_items][<?php echo esc_attr( (string) $i ); ?>][question]"
                                    value="<?php echo esc_attr( $q ); ?>"
                                    placeholder="<?php esc_attr_e( 'Question', 'humata-chatbot' ); ?>"
                                />
                            </td>
                            <td>
                                <textarea
                                    class="large-text"
                                    rows="3"
                                    name="humata_floating_help[faq_items][<?php echo esc_attr( (string) $i ); ?>][answer]"
                                    placeholder="<?php esc_attr_e( 'Answer', 'humata-chatbot' ); ?>"
                                ><?php echo esc_textarea( $a ); ?></textarea>
                            </td>
                            <td style="width: 90px;">
                                <button type="button" class="button link-delete humata-repeater-remove"><?php esc_html_e( 'Remove', 'humata-chatbot' ); ?></button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <p style="margin-top: 10px;">
                <button type="button" class="button button-secondary humata-repeater-add"><?php esc_html_e( 'Add Q&A', 'humata-chatbot' ); ?></button>
            </p>
            <p class="description">
                <?php esc_html_e( 'If no FAQ items are configured, the FAQ trigger will be hidden on the frontend.', 'humata-chatbot' ); ?>
            </p>
        </div>
        <?php
    }

    public function render_floating_help_contact_html_field() {
        $settings = $this->get_floating_help_settings();
        $content  = isset( $settings['contact_html'] ) ? (string) $settings['contact_html'] : '';

        $editor_id = 'humata_floating_help_contact_html';
        $args = array(
            'textarea_name' => 'humata_floating_help[contact_html]',
            'textarea_rows' => 12,
            'media_buttons' => false,
            'teeny'         => true,
            'quicktags'     => true,
        );

        wp_editor( $content, $editor_id, $args );
        ?>
        <p class="description">
            <?php esc_html_e( 'Content shown inside the Contact modal. Links and basic formatting are supported.', 'humata-chatbot' ); ?>
        </p>
        <?php
    }
}


