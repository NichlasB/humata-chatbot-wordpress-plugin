<?php
/**
 * Provider-related field renderers
 *
 * @package Humata_Chatbot
 * @since 1.0.0
 */

defined( 'ABSPATH' ) || exit;

trait Humata_Chatbot_Admin_Settings_Render_Providers_Trait {

    /**
     * Render search provider selection field.
     *
     * @since 1.0.0
     * @return void
     */
    public function render_search_provider_field() {
        $provider = get_option( 'humata_search_provider', 'humata' );
        if ( ! is_string( $provider ) ) {
            $provider = 'humata';
        }
        $provider = $this->sanitize_search_provider( $provider );

        // Check SQLite availability.
        $sqlite_available = class_exists( 'SQLite3' );

        // Check if local docs are indexed.
        $local_docs_count = 0;
        if ( $sqlite_available ) {
            require_once HUMATA_CHATBOT_PATH . 'includes/Rest/SearchDatabase.php';
            $db = new Humata_Chatbot_Rest_Search_Database();
            $stats = $db->get_stats();
            if ( ! is_wp_error( $stats ) ) {
                $local_docs_count = $stats['document_count'];
            }
        }
        ?>
        <fieldset class="radio-group">
            <label>
                <input
                    type="radio"
                    id="humata_search_provider_humata"
                    name="humata_search_provider"
                    value="humata"
                    <?php checked( $provider, 'humata' ); ?>
                />
                <?php esc_html_e( 'Humata API', 'humata-chatbot' ); ?>
            </label>

            <label>
                <input
                    type="radio"
                    id="humata_search_provider_local"
                    name="humata_search_provider"
                    value="local"
                    <?php checked( $provider, 'local' ); ?>
                    <?php disabled( ! $sqlite_available ); ?>
                />
                <?php esc_html_e( 'Local Search (SQLite FTS5)', 'humata-chatbot' ); ?>
                <?php if ( ! $sqlite_available ) : ?>
                    <span style="color: #d63638; margin-left: 5px;">
                        <?php esc_html_e( '(SQLite3 not available)', 'humata-chatbot' ); ?>
                    </span>
                <?php endif; ?>
            </label>
        </fieldset>

        <p class="description">
            <?php esc_html_e( 'Choose between Humata AI cloud API or local SQLite-based document search.', 'humata-chatbot' ); ?>
        </p>

        <?php if ( 'local' === $provider && $sqlite_available && 0 === $local_docs_count ) : ?>
            <p class="description" style="color: #dba617;">
                <span class="dashicons dashicons-warning"></span>
                <?php
                printf(
                    /* translators: %s: URL to documents tab */
                    esc_html__( 'Warning: No local documents indexed. Please %s to upload documents.', 'humata-chatbot' ),
                    '<a href="' . esc_url( admin_url( 'options-general.php?page=humata-chatbot&tab=documents' ) ) . '">' . esc_html__( 'go to Local Documents', 'humata-chatbot' ) . '</a>'
                );
                ?>
            </p>
        <?php elseif ( 'local' === $provider && $sqlite_available && $local_docs_count > 0 ) : ?>
            <p class="description" style="color: #00a32a;">
                <span class="dashicons dashicons-yes-alt"></span>
                <?php
                printf(
                    /* translators: %d: number of documents */
                    esc_html( _n( '%d document indexed for local search.', '%d documents indexed for local search.', $local_docs_count, 'humata-chatbot' ) ),
                    $local_docs_count
                );
                ?>
            </p>
        <?php endif; ?>
        <?php
    }

    public function render_second_llm_provider_field() {
        $provider = get_option( 'humata_second_llm_provider', '' );
        if ( ! is_string( $provider ) ) {
            $provider = '';
        }
        $provider = trim( $provider );

        // Back-compat: if new provider option is unset, reflect the legacy Straico enabled flag.
        if ( '' === $provider ) {
            $legacy_enabled = (int) get_option( 'humata_straico_review_enabled', 0 );
            $provider       = ( 1 === $legacy_enabled ) ? 'straico' : 'none';
        }

        $provider = $this->sanitize_second_llm_provider( $provider );
        ?>
        <fieldset class="radio-group">
            <label>
                <input
                    type="radio"
                    id="humata_second_llm_provider_none"
                    name="humata_second_llm_provider"
                    value="none"
                    <?php checked( $provider, 'none' ); ?>
                />
                <?php esc_html_e( 'None (skip second processing)', 'humata-chatbot' ); ?>
            </label>

            <label>
                <input
                    type="radio"
                    id="humata_second_llm_provider_straico"
                    name="humata_second_llm_provider"
                    value="straico"
                    <?php checked( $provider, 'straico' ); ?>
                />
                <?php esc_html_e( 'Straico', 'humata-chatbot' ); ?>
            </label>

            <label>
                <input
                    type="radio"
                    id="humata_second_llm_provider_anthropic"
                    name="humata_second_llm_provider"
                    value="anthropic"
                    <?php checked( $provider, 'anthropic' ); ?>
                />
                <?php esc_html_e( 'Anthropic Claude', 'humata-chatbot' ); ?>
            </label>
        </fieldset>
        <p class="description">
            <?php esc_html_e( 'When enabled, responses may take longer because the plugin waits for both Humata and the selected provider.', 'humata-chatbot' ); ?>
        </p>
        <?php
    }

    /**
     * Render Straico enabled field.
     *
     * @since 1.0.0
     * @return void
     */
    public function render_straico_enabled_field() {
        $enabled = (int) get_option( 'humata_straico_review_enabled', 0 );
        ?>
        <label>
            <input
                type="checkbox"
                id="humata_straico_review_enabled"
                name="humata_straico_review_enabled"
                value="1"
                <?php checked( $enabled, 1 ); ?>
            />
            <?php esc_html_e( 'Send Humata answers to Straico for a second-pass review before returning them to users.', 'humata-chatbot' ); ?>
        </label>
        <p class="description">
            <?php esc_html_e( 'When enabled, responses may take longer because the plugin waits for both Humata and Straico.', 'humata-chatbot' ); ?>
        </p>
        <?php
    }

    /**
     * Render Straico API key field.
     *
     * @since 1.0.0
     * @return void
     */
    public function render_straico_api_key_field() {
        $value = get_option( 'humata_straico_api_key', '' );
        ?>
        <input
            type="password"
            id="humata_straico_api_key"
            name="humata_straico_api_key"
            value="<?php echo esc_attr( $value ); ?>"
            class="regular-text"
            autocomplete="off"
        />
        <p class="description">
            <?php esc_html_e( 'Your Straico API key. This is stored server-side and never exposed to the frontend.', 'humata-chatbot' ); ?>
        </p>
        <?php
    }

    /**
     * Render Straico model field.
     *
     * @since 1.0.0
     * @return void
     */
    public function render_straico_model_field() {
        $value = get_option( 'humata_straico_model', '' );
        ?>
        <input
            type="text"
            id="humata_straico_model"
            name="humata_straico_model"
            value="<?php echo esc_attr( $value ); ?>"
            class="regular-text"
            placeholder="<?php esc_attr_e( 'e.g., anthropic/claude-sonnet-4.5', 'humata-chatbot' ); ?>"
            autocomplete="off"
        />
        <p class="description">
            <?php esc_html_e( 'Enter the Straico model identifier to use for the review step.', 'humata-chatbot' ); ?>
        </p>
        <?php
    }

    public function render_anthropic_api_key_field() {
        $value = get_option( 'humata_anthropic_api_key', '' );
        ?>
        <input
            type="password"
            id="humata_anthropic_api_key"
            name="humata_anthropic_api_key"
            value="<?php echo esc_attr( $value ); ?>"
            class="regular-text"
            autocomplete="off"
        />
        <p class="description">
            <?php esc_html_e( 'Your Anthropic API key. This is stored server-side and never exposed to the frontend.', 'humata-chatbot' ); ?>
        </p>
        <?php
    }

    public function render_anthropic_model_field() {
        $value = get_option( 'humata_anthropic_model', '' );
        if ( ! is_string( $value ) ) {
            $value = '';
        }

        $models = $this->get_anthropic_model_options();
        if ( '' === $value || ! isset( $models[ $value ] ) ) {
            $keys  = array_keys( $models );
            $value = isset( $keys[0] ) ? $keys[0] : '';
        }
        ?>
        <select id="humata_anthropic_model" name="humata_anthropic_model" class="regular-text">
            <?php foreach ( $models as $model_id => $label ) : ?>
                <option value="<?php echo esc_attr( $model_id ); ?>" <?php selected( $value, $model_id ); ?>>
                    <?php echo esc_html( $label ); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <p class="description">
            <?php esc_html_e( 'Select the Claude model to use for the second-stage review step.', 'humata-chatbot' ); ?>
        </p>
        <?php
    }

    public function render_anthropic_extended_thinking_field() {
        $enabled = (int) get_option( 'humata_anthropic_extended_thinking', 0 );
        ?>
        <label>
            <input
                type="checkbox"
                id="humata_anthropic_extended_thinking"
                name="humata_anthropic_extended_thinking"
                value="1"
                <?php checked( $enabled, 1 ); ?>
            />
            <?php esc_html_e( 'Enable Extended Thinking Mode', 'humata-chatbot' ); ?>
        </label>
        <p class="description">
            <?php esc_html_e( 'Uses more tokens but significantly improves instruction adherence.', 'humata-chatbot' ); ?>
        </p>
        <?php
    }

    /**
     * Render Straico system prompt field.
     *
     * @since 1.0.0
     * @return void
     */
    public function render_straico_system_prompt_field() {
        $value = get_option( 'humata_straico_system_prompt', '' );
        ?>
        <textarea
            id="humata_straico_system_prompt"
            name="humata_straico_system_prompt"
            rows="6"
            class="large-text"
        ><?php echo esc_textarea( $value ); ?></textarea>
        <p class="description">
            <?php esc_html_e( 'Optional instructions for the second-stage model. This prompt is sent as the system message alongside the Humata answer.', 'humata-chatbot' ); ?>
        </p>
        <?php
    }
}


