<?php
/**
 * Auto-links + intent-links field renderers
 *
 * @package Humata_Chatbot
 * @since 1.0.0
 */

defined( 'ABSPATH' ) || exit;

trait Humata_Chatbot_Admin_Settings_Render_Links_Trait {

    /**
     * Render the auto-links repeater UI.
     *
     * @since 1.0.0
     * @return void
     */
    public function render_auto_links_field() {
        $rules = $this->get_auto_links_settings();

        // Show at least one empty row for UX.
        if ( empty( $rules ) ) {
            $rules = array(
                array( 'phrase' => '', 'url' => '' ),
            );
        }

        $next_index = count( $rules );
        ?>
        <div class="humata-floating-help-repeater" data-humata-repeater="auto_links" data-next-index="<?php echo esc_attr( (string) $next_index ); ?>">
            <p class="description" style="margin-top: 0;">
                <?php esc_html_e( 'Drag and drop rows to reorder. Matching is case-insensitive and prefers longer phrases when rules overlap.', 'humata-chatbot' ); ?>
            </p>
            <table class="widefat striped" style="max-width: 900px;">
                <thead>
                    <tr>
                        <th style="width: 36px;"><span class="screen-reader-text"><?php esc_html_e( 'Order', 'humata-chatbot' ); ?></span></th>
                        <th><?php esc_html_e( 'Phrase', 'humata-chatbot' ); ?></th>
                        <th><?php esc_html_e( 'URL (opens in a new tab)', 'humata-chatbot' ); ?></th>
                        <th><?php esc_html_e( 'Actions', 'humata-chatbot' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ( $rules as $i => $row ) : ?>
                        <?php
                        $row = is_array( $row ) ? $row : array();
                        $phrase = isset( $row['phrase'] ) ? (string) $row['phrase'] : '';
                        $url    = isset( $row['url'] ) ? (string) $row['url'] : '';
                        ?>
                        <tr class="humata-repeater-row">
                            <td class="humata-repeater-handle-cell">
                                <span class="dashicons dashicons-move humata-repeater-handle" aria-hidden="true"></span>
                                <span class="screen-reader-text"><?php esc_html_e( 'Drag to reorder', 'humata-chatbot' ); ?></span>
                            </td>
                            <td style="width: 38%;">
                                <input
                                    type="text"
                                    class="regular-text"
                                    name="humata_auto_links[<?php echo esc_attr( (string) $i ); ?>][phrase]"
                                    value="<?php echo esc_attr( $phrase ); ?>"
                                    placeholder="<?php esc_attr_e( 'Phrase (exact match)', 'humata-chatbot' ); ?>"
                                />
                            </td>
                            <td>
                                <input
                                    type="url"
                                    class="regular-text"
                                    name="humata_auto_links[<?php echo esc_attr( (string) $i ); ?>][url]"
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
                <button type="button" class="button button-secondary humata-repeater-add"><?php esc_html_e( 'Add Rule', 'humata-chatbot' ); ?></button>
            </p>
            <p class="description">
                <?php esc_html_e( 'Auto-links are applied to bot messages only. Existing Markdown links and code blocks/spans are not modified.', 'humata-chatbot' ); ?>
            </p>
        </div>
        <?php
    }

    /**
     * Render the intent-based links repeater UI.
     *
     * @since 1.0.0
     * @return void
     */
    public function render_intent_links_field() {
        $intents = $this->get_intent_links_settings();

        // Show at least one empty intent for UX.
        if ( empty( $intents ) ) {
            $intents = array(
                array(
                    'intent_name' => '',
                    'keywords'    => '',
                    'links'       => array(
                        array( 'title' => '', 'url' => '' ),
                    ),
                ),
            );
        }

        $next_index = count( $intents );
        ?>
        <div class="humata-intent-links-repeater" data-humata-repeater="intent_links" data-next-index="<?php echo esc_attr( (string) $next_index ); ?>">
            <p class="description" style="margin-top: 0;">
                <?php esc_html_e( 'Create intents with keywords and associated resource links. Keywords are matched case-insensitively as whole words.', 'humata-chatbot' ); ?>
            </p>

            <div class="humata-intent-links-container">
                <?php foreach ( $intents as $i => $intent ) : ?>
                    <?php
                    $intent      = is_array( $intent ) ? $intent : array();
                    $intent_name = isset( $intent['intent_name'] ) ? (string) $intent['intent_name'] : '';
                    $keywords    = isset( $intent['keywords'] ) ? (string) $intent['keywords'] : '';
                    $links       = isset( $intent['links'] ) && is_array( $intent['links'] ) ? $intent['links'] : array();
                    $accordions  = isset( $intent['accordions'] ) && is_array( $intent['accordions'] ) ? $intent['accordions'] : array();

                    if ( empty( $links ) ) {
                        $links = array( array( 'title' => '', 'url' => '' ) );
                    }

                    if ( empty( $accordions ) ) {
                        $accordions = array( array( 'title' => '', 'content' => '' ) );
                    }

                    $links_next_index      = count( $links );
                    $accordions_next_index = count( $accordions );
                    ?>
                    <div class="humata-intent-card" data-intent-index="<?php echo esc_attr( (string) $i ); ?>">
                        <div class="humata-intent-card-header">
                            <button type="button" class="humata-intent-toggle" aria-expanded="true" aria-label="<?php esc_attr_e( 'Toggle intent', 'humata-chatbot' ); ?>">
                                <span class="dashicons dashicons-arrow-down-alt2"></span>
                            </button>
                            <span class="humata-intent-card-title">
                                <?php
                                /* translators: %d: intent number */
                                printf( esc_html__( 'Intent #%d', 'humata-chatbot' ), ( $i + 1 ) );
                                ?>
                            </span>
                            <button type="button" class="button link-delete humata-intent-remove"><?php esc_html_e( 'Remove Intent', 'humata-chatbot' ); ?></button>
                        </div>
                        <div class="humata-intent-card-body">
                            <p>
                                <label><strong><?php esc_html_e( 'Intent Name', 'humata-chatbot' ); ?></strong></label><br>
                                <input
                                    type="text"
                                    class="regular-text"
                                    name="humata_intent_links[<?php echo esc_attr( (string) $i ); ?>][intent_name]"
                                    value="<?php echo esc_attr( $intent_name ); ?>"
                                    placeholder="<?php esc_attr_e( 'e.g., shipping_intent', 'humata-chatbot' ); ?>"
                                />
                                <span class="description"><?php esc_html_e( 'Internal label (not shown to users).', 'humata-chatbot' ); ?></span>
                            </p>
                            <p>
                                <label><strong><?php esc_html_e( 'Keywords', 'humata-chatbot' ); ?></strong></label><br>
                                <textarea
                                    class="large-text"
                                    rows="2"
                                    name="humata_intent_links[<?php echo esc_attr( (string) $i ); ?>][keywords]"
                                    placeholder="<?php esc_attr_e( 'shipping, ship, delivery, deliver, track, package', 'humata-chatbot' ); ?>"
                                ><?php echo esc_textarea( $keywords ); ?></textarea>
                                <span class="description"><?php esc_html_e( 'Comma-separated keywords. If any keyword appears in the user\'s question, the links below are shown.', 'humata-chatbot' ); ?></span>
                            </p>
                            <div class="humata-intent-links-sub" data-links-next-index="<?php echo esc_attr( (string) $links_next_index ); ?>">
                                <label><strong><?php esc_html_e( 'Resource Links', 'humata-chatbot' ); ?></strong></label>
                                <table class="widefat striped humata-intent-links-table" style="max-width: 800px; margin-top: 6px;">
                                    <thead>
                                        <tr>
                                            <th><?php esc_html_e( 'Title', 'humata-chatbot' ); ?></th>
                                            <th><?php esc_html_e( 'URL', 'humata-chatbot' ); ?></th>
                                            <th style="width: 80px;"><?php esc_html_e( 'Actions', 'humata-chatbot' ); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ( $links as $j => $link ) : ?>
                                            <?php
                                            $link  = is_array( $link ) ? $link : array();
                                            $title = isset( $link['title'] ) ? (string) $link['title'] : '';
                                            $url   = isset( $link['url'] ) ? (string) $link['url'] : '';
                                            ?>
                                            <tr class="humata-intent-link-row">
                                                <td>
                                                    <input
                                                        type="text"
                                                        class="regular-text"
                                                        name="humata_intent_links[<?php echo esc_attr( (string) $i ); ?>][links][<?php echo esc_attr( (string) $j ); ?>][title]"
                                                        value="<?php echo esc_attr( $title ); ?>"
                                                        placeholder="<?php esc_attr_e( 'Shipping Policy', 'humata-chatbot' ); ?>"
                                                    />
                                                </td>
                                                <td>
                                                    <input
                                                        type="url"
                                                        class="regular-text"
                                                        name="humata_intent_links[<?php echo esc_attr( (string) $i ); ?>][links][<?php echo esc_attr( (string) $j ); ?>][url]"
                                                        value="<?php echo esc_attr( $url ); ?>"
                                                        placeholder="<?php esc_attr_e( 'https://example.com/shipping', 'humata-chatbot' ); ?>"
                                                    />
                                                </td>
                                                <td>
                                                    <button type="button" class="button link-delete humata-intent-link-remove"><?php esc_html_e( 'Remove', 'humata-chatbot' ); ?></button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                                <p style="margin-top: 8px;">
                                    <button type="button" class="button button-secondary humata-intent-link-add"><?php esc_html_e( 'Add Link', 'humata-chatbot' ); ?></button>
                                </p>
                            </div>

                            <div class="humata-intent-accordions-sub" data-accordions-next-index="<?php echo esc_attr( (string) $accordions_next_index ); ?>" style="margin-top: 20px;">
                                <label><strong><?php esc_html_e( 'Custom Accordions', 'humata-chatbot' ); ?></strong></label>
                                <span class="description" style="display: block; margin-bottom: 6px;"><?php esc_html_e( 'FAQ-style collapsible toggles shown in the chat response.', 'humata-chatbot' ); ?></span>
                                <table class="widefat striped humata-intent-accordions-table" style="max-width: 800px; margin-top: 6px;">
                                    <thead>
                                        <tr>
                                            <th style="width: 200px;"><?php esc_html_e( 'Title', 'humata-chatbot' ); ?></th>
                                            <th><?php esc_html_e( 'Content', 'humata-chatbot' ); ?></th>
                                            <th style="width: 80px;"><?php esc_html_e( 'Actions', 'humata-chatbot' ); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ( $accordions as $k => $accordion ) : ?>
                                            <?php
                                            $accordion = is_array( $accordion ) ? $accordion : array();
                                            $acc_title   = isset( $accordion['title'] ) ? (string) $accordion['title'] : '';
                                            $acc_content = isset( $accordion['content'] ) ? (string) $accordion['content'] : '';
                                            ?>
                                            <tr class="humata-intent-accordion-row">
                                                <td>
                                                    <input
                                                        type="text"
                                                        class="regular-text"
                                                        name="humata_intent_links[<?php echo esc_attr( (string) $i ); ?>][accordions][<?php echo esc_attr( (string) $k ); ?>][title]"
                                                        value="<?php echo esc_attr( $acc_title ); ?>"
                                                        placeholder="<?php esc_attr_e( 'e.g., Shipping Restrictions', 'humata-chatbot' ); ?>"
                                                    />
                                                </td>
                                                <td>
                                                    <div class="humata-accordion-toolbar">
                                                        <button type="button" class="button button-small humata-format-bold" title="<?php esc_attr_e( 'Bold', 'humata-chatbot' ); ?>"><strong>B</strong></button>
                                                        <button type="button" class="button button-small humata-format-italic" title="<?php esc_attr_e( 'Italic', 'humata-chatbot' ); ?>"><em>I</em></button>
                                                        <button type="button" class="button button-small humata-format-link" title="<?php esc_attr_e( 'Insert Link', 'humata-chatbot' ); ?>">🔗</button>
                                                    </div>
                                                    <textarea
                                                        class="large-text humata-accordion-content-input"
                                                        rows="3"
                                                        name="humata_intent_links[<?php echo esc_attr( (string) $i ); ?>][accordions][<?php echo esc_attr( (string) $k ); ?>][content]"
                                                        placeholder="<?php esc_attr_e( 'Content shown when expanded... (supports formatting)', 'humata-chatbot' ); ?>"
                                                        maxlength="1000"
                                                    ><?php echo esc_textarea( $acc_content ); ?></textarea>
                                                </td>
                                                <td>
                                                    <button type="button" class="button link-delete humata-intent-accordion-remove"><?php esc_html_e( 'Remove', 'humata-chatbot' ); ?></button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                                <p style="margin-top: 8px;">
                                    <button type="button" class="button button-secondary humata-intent-accordion-add"><?php esc_html_e( 'Add Accordion', 'humata-chatbot' ); ?></button>
                                </p>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <p style="margin-top: 16px;">
                <button type="button" class="button button-secondary humata-intent-add"><?php esc_html_e( 'Add Intent', 'humata-chatbot' ); ?></button>
            </p>
            <p class="description">
                <?php esc_html_e( 'Resource links appear below the bot\'s response when matched. Links are deduplicated if multiple intents match.', 'humata-chatbot' ); ?>
            </p>
        </div>
        <?php
    }
}


