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
     * Render an API key pool repeater field.
     *
     * @since 1.0.0
     * @param string $option_name The option name.
     * @param string $field_id    Base ID for the field.
     * @param string $description Field description.
     * @return void
     */
    private function render_api_key_pool_field( $option_name, $field_id, $description ) {
        $value = get_option( $option_name, array() );

        // Backward compatibility: convert string to array.
        if ( is_string( $value ) ) {
            $value = '' !== trim( $value ) ? array( trim( $value ) ) : array();
        }
        if ( ! is_array( $value ) ) {
            $value = array();
        }

        // Ensure at least one empty row.
        if ( empty( $value ) ) {
            $value = array( '' );
        }

        $key_count = count( array_filter( $value, function( $k ) {
            return is_string( $k ) && '' !== trim( $k );
        } ) );
        ?>
        <div class="humata-api-key-pool" data-option="<?php echo esc_attr( $option_name ); ?>">
            <div class="humata-api-key-pool__keys">
                <?php foreach ( $value as $index => $key ) : ?>
                    <div class="humata-api-key-pool__row">
                        <input
                            type="password"
                            id="<?php echo esc_attr( $field_id . '_' . $index ); ?>"
                            name="<?php echo esc_attr( $option_name ); ?>[]"
                            value="<?php echo esc_attr( $key ); ?>"
                            class="regular-text humata-api-key-pool__input"
                            autocomplete="off"
                            placeholder="<?php esc_attr_e( 'API Key', 'humata-chatbot' ); ?>"
                        />
                        <button type="button" class="button humata-api-key-pool__remove" title="<?php esc_attr_e( 'Remove', 'humata-chatbot' ); ?>">
                            <span class="dashicons dashicons-trash"></span>
                        </button>
                    </div>
                <?php endforeach; ?>
            </div>
            <button type="button" class="button humata-api-key-pool__add">
                <span class="dashicons dashicons-plus-alt2"></span>
                <?php esc_html_e( 'Add API Key', 'humata-chatbot' ); ?>
            </button>
            <?php if ( $key_count > 1 ) : ?>
                <span class="humata-api-key-pool__count">
                    <?php
                    printf(
                        /* translators: %d: number of API keys */
                        esc_html( _n( '%d key configured (rotation enabled)', '%d keys configured (rotation enabled)', $key_count, 'humata-chatbot' ) ),
                        $key_count
                    );
                    ?>
                </span>
            <?php endif; ?>
        </div>
        <p class="description">
            <?php echo esc_html( $description ); ?>
            <?php esc_html_e( ' Add multiple keys to enable round-robin rotation across accounts.', 'humata-chatbot' ); ?>
        </p>
        <?php
    }

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
        $this->render_api_key_pool_field(
            'humata_straico_api_key',
            'humata_straico_api_key',
            __( 'Your Straico API key(s). Stored server-side and never exposed to the frontend.', 'humata-chatbot' )
        );
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
        $this->render_api_key_pool_field(
            'humata_anthropic_api_key',
            'humata_anthropic_api_key',
            __( 'Your Anthropic API key(s). Stored server-side and never exposed to the frontend.', 'humata-chatbot' )
        );
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

    /**
     * Render Local Search second-stage system prompt field.
     *
     * @since 1.0.0
     * @return void
     */
    public function render_local_second_stage_system_prompt_field() {
        $value = get_option( 'humata_local_second_stage_system_prompt', '' );
        ?>
        <textarea
            id="humata_local_second_stage_system_prompt"
            name="humata_local_second_stage_system_prompt"
            rows="6"
            class="large-text"
        ><?php echo esc_textarea( $value ); ?></textarea>
        <p class="description">
            <?php esc_html_e( 'Optional instructions for the second-stage model. This prompt is sent as the system message alongside the first-stage answer.', 'humata-chatbot' ); ?>
        </p>
        <?php
    }

    /**
     * Render Local Search first-stage LLM provider field.
     *
     * @since 1.0.0
     * @return void
     */
    public function render_local_first_llm_provider_field() {
        $provider = get_option( 'humata_local_first_llm_provider', 'straico' );
        if ( ! is_string( $provider ) ) {
            $provider = 'straico';
        }
        $provider = $this->sanitize_local_first_llm_provider( $provider );
        ?>
        <fieldset class="radio-group">
            <label>
                <input
                    type="radio"
                    id="humata_local_first_llm_provider_straico"
                    name="humata_local_first_llm_provider"
                    value="straico"
                    <?php checked( $provider, 'straico' ); ?>
                />
                <?php esc_html_e( 'Straico', 'humata-chatbot' ); ?>
            </label>

            <label>
                <input
                    type="radio"
                    id="humata_local_first_llm_provider_anthropic"
                    name="humata_local_first_llm_provider"
                    value="anthropic"
                    <?php checked( $provider, 'anthropic' ); ?>
                />
                <?php esc_html_e( 'Anthropic Claude', 'humata-chatbot' ); ?>
            </label>
        </fieldset>
        <p class="description">
            <?php esc_html_e( 'Select the LLM provider to generate the initial response from local document search results.', 'humata-chatbot' ); ?>
        </p>
        <?php
    }

    /**
     * Render Local Search first-stage Straico API key field.
     *
     * @since 1.0.0
     * @return void
     */
    public function render_local_first_straico_api_key_field() {
        $this->render_api_key_pool_field(
            'humata_local_first_straico_api_key',
            'humata_local_first_straico_api_key',
            __( 'Your Straico API key(s) for first-stage processing.', 'humata-chatbot' )
        );
    }

    /**
     * Render Local Search first-stage Straico model field.
     *
     * @since 1.0.0
     * @return void
     */
    public function render_local_first_straico_model_field() {
        $value = get_option( 'humata_local_first_straico_model', '' );
        ?>
        <input
            type="text"
            id="humata_local_first_straico_model"
            name="humata_local_first_straico_model"
            value="<?php echo esc_attr( $value ); ?>"
            class="regular-text"
            placeholder="<?php esc_attr_e( 'e.g., anthropic/claude-sonnet-4.5', 'humata-chatbot' ); ?>"
            autocomplete="off"
        />
        <p class="description">
            <?php esc_html_e( 'Enter the Straico model identifier to use for first-stage processing.', 'humata-chatbot' ); ?>
        </p>
        <?php
    }

    /**
     * Render Local Search first-stage Anthropic API key field.
     *
     * @since 1.0.0
     * @return void
     */
    public function render_local_first_anthropic_api_key_field() {
        $this->render_api_key_pool_field(
            'humata_local_first_anthropic_api_key',
            'humata_local_first_anthropic_api_key',
            __( 'Your Anthropic API key(s) for first-stage processing.', 'humata-chatbot' )
        );
    }

    /**
     * Render Local Search first-stage Anthropic model field.
     *
     * @since 1.0.0
     * @return void
     */
    public function render_local_first_anthropic_model_field() {
        $value = get_option( 'humata_local_first_anthropic_model', '' );
        if ( ! is_string( $value ) ) {
            $value = '';
        }

        $models = $this->get_anthropic_model_options();
        if ( '' === $value || ! isset( $models[ $value ] ) ) {
            $keys  = array_keys( $models );
            $value = isset( $keys[0] ) ? $keys[0] : '';
        }
        ?>
        <select id="humata_local_first_anthropic_model" name="humata_local_first_anthropic_model" class="regular-text">
            <?php foreach ( $models as $model_id => $label ) : ?>
                <option value="<?php echo esc_attr( $model_id ); ?>" <?php selected( $value, $model_id ); ?>>
                    <?php echo esc_html( $label ); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <p class="description">
            <?php esc_html_e( 'Select the Claude model to use for first-stage processing.', 'humata-chatbot' ); ?>
        </p>
        <?php
    }

    /**
     * Render Local Search first-stage Anthropic extended thinking field.
     *
     * @since 1.0.0
     * @return void
     */
    public function render_local_first_anthropic_extended_thinking_field() {
        $enabled = (int) get_option( 'humata_local_first_anthropic_extended_thinking', 0 );
        ?>
        <label>
            <input
                type="checkbox"
                id="humata_local_first_anthropic_extended_thinking"
                name="humata_local_first_anthropic_extended_thinking"
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
     * Render Local Search second-stage LLM provider field.
     *
     * @since 1.0.0
     * @return void
     */
    public function render_local_second_llm_provider_field() {
        $provider = get_option( 'humata_local_second_llm_provider', 'none' );
        if ( ! is_string( $provider ) ) {
            $provider = 'none';
        }
        $provider = $this->sanitize_local_second_llm_provider( $provider );
        ?>
        <fieldset class="radio-group">
            <label>
                <input
                    type="radio"
                    id="humata_local_second_llm_provider_none"
                    name="humata_local_second_llm_provider"
                    value="none"
                    <?php checked( $provider, 'none' ); ?>
                />
                <?php esc_html_e( 'None (skip second processing)', 'humata-chatbot' ); ?>
            </label>

            <label>
                <input
                    type="radio"
                    id="humata_local_second_llm_provider_straico"
                    name="humata_local_second_llm_provider"
                    value="straico"
                    <?php checked( $provider, 'straico' ); ?>
                />
                <?php esc_html_e( 'Straico', 'humata-chatbot' ); ?>
            </label>

            <label>
                <input
                    type="radio"
                    id="humata_local_second_llm_provider_anthropic"
                    name="humata_local_second_llm_provider"
                    value="anthropic"
                    <?php checked( $provider, 'anthropic' ); ?>
                />
                <?php esc_html_e( 'Anthropic Claude', 'humata-chatbot' ); ?>
            </label>
        </fieldset>
        <p class="description">
            <?php esc_html_e( 'When enabled, the first-stage response is sent to a second LLM for additional processing.', 'humata-chatbot' ); ?>
        </p>
        <?php
    }

    /**
     * Render Local Search second-stage Straico API key field.
     *
     * @since 1.0.0
     * @return void
     */
    public function render_local_second_straico_api_key_field() {
        $this->render_api_key_pool_field(
            'humata_local_second_straico_api_key',
            'humata_local_second_straico_api_key',
            __( 'Your Straico API key(s) for second-stage processing.', 'humata-chatbot' )
        );
    }

    /**
     * Render Local Search second-stage Straico model field.
     *
     * @since 1.0.0
     * @return void
     */
    public function render_local_second_straico_model_field() {
        $value = get_option( 'humata_local_second_straico_model', '' );
        ?>
        <input
            type="text"
            id="humata_local_second_straico_model"
            name="humata_local_second_straico_model"
            value="<?php echo esc_attr( $value ); ?>"
            class="regular-text"
            placeholder="<?php esc_attr_e( 'e.g., anthropic/claude-sonnet-4.5', 'humata-chatbot' ); ?>"
            autocomplete="off"
        />
        <p class="description">
            <?php esc_html_e( 'Enter the Straico model identifier to use for second-stage processing.', 'humata-chatbot' ); ?>
        </p>
        <?php
    }

    /**
     * Render Local Search second-stage Anthropic API key field.
     *
     * @since 1.0.0
     * @return void
     */
    public function render_local_second_anthropic_api_key_field() {
        $this->render_api_key_pool_field(
            'humata_local_second_anthropic_api_key',
            'humata_local_second_anthropic_api_key',
            __( 'Your Anthropic API key(s) for second-stage processing.', 'humata-chatbot' )
        );
    }

    /**
     * Render Local Search second-stage Anthropic model field.
     *
     * @since 1.0.0
     * @return void
     */
    public function render_local_second_anthropic_model_field() {
        $value = get_option( 'humata_local_second_anthropic_model', '' );
        if ( ! is_string( $value ) ) {
            $value = '';
        }

        $models = $this->get_anthropic_model_options();
        if ( '' === $value || ! isset( $models[ $value ] ) ) {
            $keys  = array_keys( $models );
            $value = isset( $keys[0] ) ? $keys[0] : '';
        }
        ?>
        <select id="humata_local_second_anthropic_model" name="humata_local_second_anthropic_model" class="regular-text">
            <?php foreach ( $models as $model_id => $label ) : ?>
                <option value="<?php echo esc_attr( $model_id ); ?>" <?php selected( $value, $model_id ); ?>>
                    <?php echo esc_html( $label ); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <p class="description">
            <?php esc_html_e( 'Select the Claude model to use for second-stage processing.', 'humata-chatbot' ); ?>
        </p>
        <?php
    }

    /**
     * Render Local Search second-stage Anthropic extended thinking field.
     *
     * @since 1.0.0
     * @return void
     */
    public function render_local_second_anthropic_extended_thinking_field() {
        $enabled = (int) get_option( 'humata_local_second_anthropic_extended_thinking', 0 );
        ?>
        <label>
            <input
                type="checkbox"
                id="humata_local_second_anthropic_extended_thinking"
                name="humata_local_second_anthropic_extended_thinking"
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
}


