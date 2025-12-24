<?php
/**
 * API/settings field renderers
 *
 * @package Humata_Chatbot
 * @since 1.0.0
 */

defined( 'ABSPATH' ) || exit;

trait Humata_Chatbot_Admin_Settings_Render_Api_Trait {

    /**
     * Render API key field.
     *
     * @since 1.0.0
     * @return void
     */
    public function render_api_key_field() {
        $value = get_option( 'humata_api_key', '' );
        ?>
        <input
            type="password"
            id="humata_api_key"
            name="humata_api_key"
            value="<?php echo esc_attr( $value ); ?>"
            class="regular-text"
            autocomplete="off"
        />
        <p class="description">
            <?php esc_html_e( 'Your Humata API key. This will be stored securely and never exposed to the frontend.', 'humata-chatbot' ); ?>
        </p>
        <?php
    }

    /**
     * Render document IDs field.
     *
     * @since 1.0.0
     * @return void
     */
    public function render_document_ids_field() {
        $value = get_option( 'humata_document_ids', '' );
        $doc_ids_array = $this->parse_document_ids( $value );
        $titles = get_option( 'humata_document_titles', array() );
        if ( ! is_array( $titles ) ) {
            $titles = array();
        }
        $titles_cached = 0;
        foreach ( $doc_ids_array as $doc_id ) {
            if ( isset( $titles[ $doc_id ] ) && '' !== $titles[ $doc_id ] ) {
                $titles_cached++;
            }
        }

        if ( count( $doc_ids_array ) > 1 ) {
            usort(
                $doc_ids_array,
                function( $a, $b ) use ( $titles ) {
                    $label_a = ( isset( $titles[ $a ] ) && '' !== $titles[ $a ] ) ? $titles[ $a ] : $a;
                    $label_b = ( isset( $titles[ $b ] ) && '' !== $titles[ $b ] ) ? $titles[ $b ] : $b;
                    $cmp     = strcasecmp( $label_a, $label_b );
                    if ( 0 !== $cmp ) {
                        return -$cmp;
                    }
                    return -strcasecmp( $a, $b );
                }
            );
        }
        ?>
        <div id="humata-document-ids-control" class="humata-document-ids-control">
            <div id="humata-document-ids-tokens" class="humata-document-ids-tokens"></div>
            <input
                type="text"
                id="humata_document_ids_input"
                class="regular-text"
                placeholder="<?php esc_attr_e( 'e.g., 63ea1432-d6aa-49d4-81ef-07cb1692f2ee, cbec39d9-1e01-409c-9fc2-f19f5d01005c', 'humata-chatbot' ); ?>"
                autocomplete="off"
            />
        </div>
        <input
            type="hidden"
            id="humata_document_ids"
            name="humata_document_ids"
            value="<?php echo esc_attr( $value ); ?>"
        />
        <p class="description">
            <strong><?php esc_html_e( 'Document count:', 'humata-chatbot' ); ?></strong>
            <span id="humata-document-count"><?php echo esc_html( (string) count( $doc_ids_array ) ); ?></span>
            <?php if ( $titles_cached > 0 ) : ?>
                <span><?php echo esc_html( sprintf( __( '(%d titles cached)', 'humata-chatbot' ), $titles_cached ) ); ?></span>
            <?php endif; ?>
        </p>
        <div class="humata-document-ids-io">
            <div class="humata-document-ids-io-row">
                <span class="humata-document-ids-io-label"><?php esc_html_e( 'Export', 'humata-chatbot' ); ?></span>
                <button type="button" id="humata-document-ids-export-copy" class="button button-secondary">
                    <?php esc_html_e( 'Copy IDs', 'humata-chatbot' ); ?>
                </button>
                <button type="button" id="humata-document-ids-export-download" class="button button-secondary">
                    <?php esc_html_e( 'Download TXT', 'humata-chatbot' ); ?>
                </button>
                <button type="button" id="humata-document-ids-export-download-json" class="button button-secondary">
                    <?php esc_html_e( 'Download JSON', 'humata-chatbot' ); ?>
                </button>
            </div>

            <div class="humata-document-ids-io-row humata-document-ids-io-row-import">
                <span class="humata-document-ids-io-label"><?php esc_html_e( 'Import', 'humata-chatbot' ); ?></span>
                <fieldset class="humata-document-ids-import-mode">
                    <label>
                        <input type="radio" name="humata_document_ids_import_mode" value="merge" checked />
                        <?php esc_html_e( 'Merge', 'humata-chatbot' ); ?>
                    </label>
                    <label>
                        <input type="radio" name="humata_document_ids_import_mode" value="replace" />
                        <?php esc_html_e( 'Replace', 'humata-chatbot' ); ?>
                    </label>
                </fieldset>
                <label class="humata-document-ids-import-file-label" for="humata-document-ids-import-file">
                    <?php esc_html_e( 'File:', 'humata-chatbot' ); ?>
                </label>
                <input
                    type="file"
                    id="humata-document-ids-import-file"
                    class="humata-document-ids-import-file"
                    accept=".txt,.csv,.json,text/plain,application/json,text/csv"
                />
            </div>

            <textarea
                id="humata-document-ids-import-text"
                class="large-text code"
                rows="4"
                placeholder="<?php esc_attr_e( 'Paste document IDs here (one per line, comma-separated, CSV, JSON, URLs, etc.)', 'humata-chatbot' ); ?>"
            ></textarea>

            <div class="humata-document-ids-io-row humata-document-ids-io-actions">
                <button type="button" id="humata-document-ids-import-apply" class="button button-primary">
                    <?php esc_html_e( 'Apply Import', 'humata-chatbot' ); ?>
                </button>
                <button type="button" id="humata-document-ids-clear-all" class="button button-secondary">
                    <?php esc_html_e( 'Clear All', 'humata-chatbot' ); ?>
                </button>
                <span id="humata-document-ids-io-status" class="humata-document-ids-io-status" aria-live="polite"></span>
            </div>

            <p class="description">
                <?php esc_html_e( 'Tip: valid UUIDs will be extracted and deduplicated. Click “Save Settings” to persist changes.', 'humata-chatbot' ); ?>
            </p>
        </div>
        <?php if ( ! empty( $doc_ids_array ) ) : ?>
            <details class="humata-documents-details">
                <summary><?php esc_html_e( 'Show documents', 'humata-chatbot' ); ?></summary>
                <div id="humata-documents-pagination" class="humata-documents-pagination"></div>
                <div class="humata-documents-table-wrapper">
                    <table class="widefat striped" style="margin-top:10px; max-width: 900px;">
                        <thead>
                            <tr>
                                <th><?php esc_html_e( 'Title', 'humata-chatbot' ); ?></th>
                                <th><?php esc_html_e( 'Document ID', 'humata-chatbot' ); ?></th>
                            </tr>
                        </thead>
                        <tbody id="humata-documents-table-body">
                            <?php foreach ( array_slice( $doc_ids_array, 0, 100 ) as $doc_id ) : ?>
                                <?php $title = isset( $titles[ $doc_id ] ) ? $titles[ $doc_id ] : ''; ?>
                                <tr data-doc-id="<?php echo esc_attr( $doc_id ); ?>">
                                    <td class="humata-doc-title"><?php echo esc_html( $title ? $title : __( '(title not fetched)', 'humata-chatbot' ) ); ?></td>
                                    <td><code><?php echo esc_html( $doc_id ); ?></code></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </details>
        <?php endif; ?>
        <p class="description">
            <?php esc_html_e( 'Enter Humata document IDs separated by commas. Each ID must be a UUID (e.g., 63ea1432-d6aa-49d4-81ef-07cb1692f2ee).', 'humata-chatbot' ); ?>
        </p>
        <p class="description">
            <strong><?php esc_html_e( 'How to find document IDs:', 'humata-chatbot' ); ?></strong><br>
            <?php esc_html_e( '1. Open your Humata dashboard and click on a document', 'humata-chatbot' ); ?><br>
            <?php esc_html_e( '2. Look at the URL - the document ID is the UUID after /pdf/ (e.g., app.humata.ai/pdf/YOUR-DOC-ID-HERE)', 'humata-chatbot' ); ?><br>
            <?php esc_html_e( '3. Or use browser DevTools (F12) → Network tab to see document IDs in API requests', 'humata-chatbot' ); ?>
        </p>
        <div class="humata-api-actions">
            <button type="button" id="humata-test-api" class="button button-secondary">
                <?php esc_html_e( 'Test Connection', 'humata-chatbot' ); ?>
            </button>
            <button type="button" id="humata-test-ask" class="button button-secondary">
                <?php esc_html_e( 'Test Ask', 'humata-chatbot' ); ?>
            </button>
            <button type="button" id="humata-fetch-titles" class="button button-secondary">
                <?php esc_html_e( 'Fetch Titles', 'humata-chatbot' ); ?>
            </button>
            <button type="button" id="humata-clear-cache" class="button button-secondary">
                <?php esc_html_e( 'Clear Cache', 'humata-chatbot' ); ?>
            </button>
            <span id="humata-test-result"></span>
        </div>
        <?php
    }

    public function render_system_prompt_field() {
        $value = get_option( 'humata_system_prompt', '' );
        ?>
        <textarea
            id="humata_system_prompt"
            name="humata_system_prompt"
            rows="4"
            class="large-text"
        ><?php echo esc_textarea( $value ); ?></textarea>
        <p class="description">
            <?php esc_html_e( 'Leave blank to disable. If set, this text is prepended to every user message before sending it to Humata.', 'humata-chatbot' ); ?>
        </p>
        <?php
    }

    /**
     * Render local search system prompt field.
     *
     * @since 1.0.0
     * @return void
     */
    public function render_local_search_system_prompt_field() {
        $value = get_option( 'humata_local_search_system_prompt', '' );
        ?>
        <textarea
            id="humata_local_search_system_prompt"
            name="humata_local_search_system_prompt"
            rows="4"
            class="large-text"
        ><?php echo esc_textarea( $value ); ?></textarea>
        <p class="description">
            <?php esc_html_e( 'Instructions for the LLM when answering questions from local documents. This is prepended to the RAG prompt containing matched document sections.', 'humata-chatbot' ); ?>
        </p>
        <?php
    }
}


