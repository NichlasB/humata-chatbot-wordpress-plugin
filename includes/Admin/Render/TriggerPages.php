<?php
/**
 * Trigger Pages render methods
 *
 * @package Humata_Chatbot
 * @since 1.0.0
 */

defined( 'ABSPATH' ) || exit;

trait Humata_Chatbot_Admin_Settings_Render_TriggerPages_Trait {

    /**
     * Render the Trigger Pages section description.
     *
     * @since 1.0.0
     * @return void
     */
    public function render_trigger_pages_section() {
        ?>
        <p><?php esc_html_e( 'Create pages that appear as clickable links above the footer. Each link opens a modal overlay with rich content.', 'humata-chatbot' ); ?></p>
        <?php
    }

    /**
     * Render the trigger pages repeater field.
     *
     * @since 1.0.0
     * @return void
     */
    public function render_trigger_pages_field() {
        $pages = $this->get_trigger_pages_settings();

        // Always add an empty row at the end for adding new pages.
        $pages[] = array( 'title' => '', 'link_text' => '', 'content' => '' );

        $next_index = count( $pages );
        ?>
        <div class="humata-trigger-pages-repeater" data-humata-repeater="trigger_pages" data-next-index="<?php echo esc_attr( (string) $next_index ); ?>">
            <p class="description" style="margin-top: 0;">
                <?php esc_html_e( 'Drag and drop pages to reorder. Each page appears as a link that opens a modal overlay.', 'humata-chatbot' ); ?>
            </p>

            <div class="humata-trigger-pages-list">
                <?php foreach ( $pages as $i => $page ) : ?>
                    <?php
                    $page      = is_array( $page ) ? $page : array();
                    $title     = isset( $page['title'] ) ? (string) $page['title'] : '';
                    $link_text = isset( $page['link_text'] ) ? (string) $page['link_text'] : '';
                    $content   = isset( $page['content'] ) ? (string) $page['content'] : '';
                    ?>
                    <div class="humata-trigger-page-item humata-repeater-row" style="border: 1px solid #ccd0d4; background: #fff; padding: 16px; margin-bottom: 16px; border-radius: 4px;">
                        <div style="display: flex; align-items: flex-start; gap: 12px; margin-bottom: 12px;">
                            <span class="dashicons dashicons-move humata-repeater-handle" style="cursor: move; color: #999; margin-top: 4px;" aria-hidden="true"></span>
                            <div style="flex: 1;">
                                <p style="margin: 0 0 12px 0;">
                                    <label><strong><?php esc_html_e( 'Modal Title', 'humata-chatbot' ); ?></strong></label><br>
                                    <input
                                        type="text"
                                        class="regular-text"
                                        name="humata_trigger_pages[<?php echo esc_attr( (string) $i ); ?>][title]"
                                        value="<?php echo esc_attr( $title ); ?>"
                                        placeholder="<?php esc_attr_e( 'e.g. Terms of Use', 'humata-chatbot' ); ?>"
                                        style="width: 100%;"
                                    />
                                </p>
                                <p style="margin: 0 0 12px 0;">
                                    <label><strong><?php esc_html_e( 'Link Text', 'humata-chatbot' ); ?></strong></label><br>
                                    <input
                                        type="text"
                                        class="regular-text"
                                        name="humata_trigger_pages[<?php echo esc_attr( (string) $i ); ?>][link_text]"
                                        value="<?php echo esc_attr( $link_text ); ?>"
                                        placeholder="<?php esc_attr_e( 'e.g. Terms of Use', 'humata-chatbot' ); ?>"
                                        style="width: 100%;"
                                    />
                                    <span class="description"><?php esc_html_e( 'Text displayed as the clickable link on the frontend.', 'humata-chatbot' ); ?></span>
                                </p>
                                <p style="margin: 0 0 12px 0;">
                                    <label><strong><?php esc_html_e( 'Content', 'humata-chatbot' ); ?></strong></label>
                                </p>
                                <?php
                                $editor_id = 'humata_trigger_page_content_' . $i;
                                $args      = array(
                                    'textarea_name' => 'humata_trigger_pages[' . $i . '][content]',
                                    'textarea_rows' => 10,
                                    'media_buttons' => false,
                                    'teeny'         => false,
                                    'quicktags'     => true,
                                    'tinymce'       => array(
                                        'toolbar1' => 'formatselect,bold,italic,link,unlink,bullist,numlist,outdent,indent,undo,redo',
                                        'toolbar2' => '',
                                    ),
                                );
                                wp_editor( $content, $editor_id, $args );
                                ?>
                            </div>
                            <button type="button" class="button link-delete humata-repeater-remove" style="flex-shrink: 0;"><?php esc_html_e( 'Remove', 'humata-chatbot' ); ?></button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <p style="margin-top: 10px;">
                <button type="button" class="button button-secondary humata-trigger-pages-add"><?php esc_html_e( 'Add Page', 'humata-chatbot' ); ?></button>
            </p>
            <p class="description">
                <?php esc_html_e( 'Maximum 20 pages. Fill in the empty row at the bottom to add a new page. Leave a page empty to remove it on save.', 'humata-chatbot' ); ?>
            </p>
        </div>
        <?php
    }
}
