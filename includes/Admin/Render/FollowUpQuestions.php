<?php
/**
 * Follow-Up Questions Render Trait
 *
 * @package Humata_Chatbot
 * @since 1.0.0
 */

defined( 'ABSPATH' ) || exit;

trait Humata_Chatbot_Admin_Settings_Render_FollowUpQuestions_Trait {

	/**
	 * Render the follow-up questions section description.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_followup_questions_section() {
		echo '<p>' . esc_html__( 'Configure AI-generated follow-up question suggestions that appear after the bot responds.', 'humata-chatbot' ) . '</p>';
		echo '<p class="description">' . esc_html__( 'These questions help users continue the conversation with relevant follow-up topics based on the AI response.', 'humata-chatbot' ) . '</p>';
	}

	/**
	 * Render the enable follow-up questions checkbox.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_followup_questions_enabled_field() {
		$settings = $this->get_followup_questions_settings();
		$enabled  = ! empty( $settings['enabled'] );
		?>
		<label>
			<input
				type="checkbox"
				name="humata_followup_questions[enabled]"
				value="1"
				<?php checked( $enabled ); ?>
			/>
			<?php esc_html_e( 'Enable AI Follow-Up Questions', 'humata-chatbot' ); ?>
		</label>
		<p class="description">
			<?php esc_html_e( 'When enabled, the AI will generate 3-4 suggested follow-up questions after each bot response.', 'humata-chatbot' ); ?>
		</p>
		<?php
	}

	/**
	 * Render the follow-up questions provider selection field.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_followup_questions_provider_field() {
		$settings = $this->get_followup_questions_settings();
		$provider = isset( $settings['provider'] ) ? $settings['provider'] : 'straico';
		?>
		<fieldset>
			<label>
				<input
					type="radio"
					name="humata_followup_questions[provider]"
					value="straico"
					<?php checked( $provider, 'straico' ); ?>
					class="humata-followup-provider-radio"
				/>
				<?php esc_html_e( 'Straico', 'humata-chatbot' ); ?>
			</label>
			<br>
			<label>
				<input
					type="radio"
					name="humata_followup_questions[provider]"
					value="anthropic"
					<?php checked( $provider, 'anthropic' ); ?>
					class="humata-followup-provider-radio"
				/>
				<?php esc_html_e( 'Anthropic Claude', 'humata-chatbot' ); ?>
			</label>
			<br>
			<label>
				<input
					type="radio"
					name="humata_followup_questions[provider]"
					value="openrouter"
					<?php checked( $provider, 'openrouter' ); ?>
					class="humata-followup-provider-radio"
				/>
				<?php esc_html_e( 'OpenRouter', 'humata-chatbot' ); ?>
			</label>
		</fieldset>
		<p class="description">
			<?php esc_html_e( 'Select the LLM provider to generate follow-up questions. This uses separate API credentials from other provider settings.', 'humata-chatbot' ); ?>
		</p>
		<?php
	}

	/**
	 * Render the follow-up questions Straico API keys field.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_followup_straico_api_keys_field() {
		$settings = $this->get_followup_questions_settings();
		$api_keys = isset( $settings['straico_api_keys'] ) ? $settings['straico_api_keys'] : array();

		if ( ! is_array( $api_keys ) ) {
			$api_keys = array();
		}
		if ( empty( $api_keys ) ) {
			$api_keys = array( '' );
		}

		$key_count = count( array_filter( $api_keys, function( $k ) {
			return is_string( $k ) && '' !== trim( $k );
		} ) );
		?>
		<div class="humata-followup-straico-fields">
			<div class="humata-api-key-pool" data-option="humata_followup_questions[straico_api_keys]">
				<div class="humata-api-key-pool__keys">
					<?php foreach ( $api_keys as $index => $key ) : ?>
						<div class="humata-api-key-pool__row">
							<input
								type="password"
								id="humata_followup_straico_api_key_<?php echo esc_attr( $index ); ?>"
								name="humata_followup_questions[straico_api_keys][]"
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
				<?php esc_html_e( 'Add multiple keys to enable round-robin rotation across accounts.', 'humata-chatbot' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Render the follow-up questions Straico model field.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_followup_straico_model_field() {
		$settings = $this->get_followup_questions_settings();
		$model    = isset( $settings['straico_model'] ) ? $settings['straico_model'] : '';
		?>
		<div class="humata-followup-straico-fields">
			<input
				type="text"
				name="humata_followup_questions[straico_model]"
				value="<?php echo esc_attr( $model ); ?>"
				class="regular-text"
				placeholder="<?php esc_attr_e( 'e.g., openai/gpt-4o-mini', 'humata-chatbot' ); ?>"
			/>
			<p class="description">
				<?php esc_html_e( 'Straico model ID for generating follow-up questions. A fast, cost-effective model is recommended.', 'humata-chatbot' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Render the follow-up questions Anthropic API keys field.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_followup_anthropic_api_keys_field() {
		$settings = $this->get_followup_questions_settings();
		$api_keys = isset( $settings['anthropic_api_keys'] ) ? $settings['anthropic_api_keys'] : array();

		if ( ! is_array( $api_keys ) ) {
			$api_keys = array();
		}
		if ( empty( $api_keys ) ) {
			$api_keys = array( '' );
		}

		$key_count = count( array_filter( $api_keys, function( $k ) {
			return is_string( $k ) && '' !== trim( $k );
		} ) );
		?>
		<div class="humata-followup-anthropic-fields">
			<div class="humata-api-key-pool" data-option="humata_followup_questions[anthropic_api_keys]">
				<div class="humata-api-key-pool__keys">
					<?php foreach ( $api_keys as $index => $key ) : ?>
						<div class="humata-api-key-pool__row">
							<input
								type="password"
								id="humata_followup_anthropic_api_key_<?php echo esc_attr( $index ); ?>"
								name="humata_followup_questions[anthropic_api_keys][]"
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
				<?php esc_html_e( 'Add multiple keys to enable round-robin rotation across accounts.', 'humata-chatbot' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Render the follow-up questions Anthropic model field.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_followup_anthropic_model_field() {
		$settings = $this->get_followup_questions_settings();
		$model    = isset( $settings['anthropic_model'] ) ? $settings['anthropic_model'] : 'claude-3-5-sonnet-20241022';
		?>
		<div class="humata-followup-anthropic-fields">
			<select name="humata_followup_questions[anthropic_model]">
				<option value="claude-3-5-haiku-20241022" <?php selected( $model, 'claude-3-5-haiku-20241022' ); ?>>
					Claude 3.5 Haiku (Fast, Recommended)
				</option>
				<option value="claude-3-5-sonnet-20241022" <?php selected( $model, 'claude-3-5-sonnet-20241022' ); ?>>
					Claude 3.5 Sonnet
				</option>
				<option value="claude-3-opus-20240229" <?php selected( $model, 'claude-3-opus-20240229' ); ?>>
					Claude 3 Opus
				</option>
			</select>
			<p class="description">
				<?php esc_html_e( 'Claude 3.5 Haiku is recommended for fast, cost-effective question generation.', 'humata-chatbot' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Render the follow-up questions Anthropic extended thinking field.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_followup_anthropic_extended_thinking_field() {
		$settings          = $this->get_followup_questions_settings();
		$extended_thinking = ! empty( $settings['anthropic_extended_thinking'] );
		?>
		<div class="humata-followup-anthropic-fields">
			<label>
				<input
					type="checkbox"
					name="humata_followup_questions[anthropic_extended_thinking]"
					value="1"
					<?php checked( $extended_thinking ); ?>
				/>
				<?php esc_html_e( 'Enable Extended Thinking', 'humata-chatbot' ); ?>
			</label>
			<p class="description">
				<?php esc_html_e( 'Not recommended for follow-up questions as it increases latency. Leave disabled for faster responses.', 'humata-chatbot' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Render the follow-up questions OpenRouter API keys field.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_followup_openrouter_api_keys_field() {
		$settings = $this->get_followup_questions_settings();
		$api_keys = isset( $settings['openrouter_api_keys'] ) ? $settings['openrouter_api_keys'] : array();

		if ( ! is_array( $api_keys ) ) {
			$api_keys = array();
		}
		if ( empty( $api_keys ) ) {
			$api_keys = array( '' );
		}

		$key_count = count( array_filter( $api_keys, function( $k ) {
			return is_string( $k ) && '' !== trim( $k );
		} ) );
		?>
		<div class="humata-followup-openrouter-fields">
			<div class="humata-api-key-pool" data-option="humata_followup_questions[openrouter_api_keys]">
				<div class="humata-api-key-pool__keys">
					<?php foreach ( $api_keys as $index => $key ) : ?>
						<div class="humata-api-key-pool__row">
							<input
								type="password"
								id="humata_followup_openrouter_api_key_<?php echo esc_attr( $index ); ?>"
								name="humata_followup_questions[openrouter_api_keys][]"
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
				<?php esc_html_e( 'Add multiple keys to enable round-robin rotation across accounts.', 'humata-chatbot' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Render the follow-up questions OpenRouter model field.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_followup_openrouter_model_field() {
		$settings = $this->get_followup_questions_settings();
		$model    = isset( $settings['openrouter_model'] ) ? $settings['openrouter_model'] : HUMATA_DEFAULT_OPENROUTER_MODEL;

		$models = $this->get_openrouter_model_options();
		if ( '' === $model || ! isset( $models[ $model ] ) ) {
			$keys  = array_keys( $models );
			$model = isset( $keys[0] ) ? $keys[0] : '';
		}
		?>
		<div class="humata-followup-openrouter-fields">
			<select name="humata_followup_questions[openrouter_model]">
				<?php foreach ( $models as $model_id => $label ) : ?>
					<option value="<?php echo esc_attr( $model_id ); ?>" <?php selected( $model, $model_id ); ?>>
						<?php echo esc_html( $label ); ?>
					</option>
				<?php endforeach; ?>
			</select>
			<p class="description">
				<?php esc_html_e( 'A fast, cost-effective model is recommended for follow-up question generation.', 'humata-chatbot' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Render the max question length field.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_followup_max_question_length_field() {
		$settings   = $this->get_followup_questions_settings();
		$max_length = isset( $settings['max_question_length'] ) ? $settings['max_question_length'] : 80;
		?>
		<input
			type="number"
			name="humata_followup_questions[max_question_length]"
			value="<?php echo esc_attr( (string) $max_length ); ?>"
			min="30"
			max="150"
			step="1"
			class="small-text"
		/>
		<span class="description"><?php esc_html_e( 'characters (30-150)', 'humata-chatbot' ); ?></span>
		<p class="description">
			<?php esc_html_e( 'Maximum character length for each follow-up question. Shorter questions fit better in buttons. Default: 80.', 'humata-chatbot' ); ?>
		</p>
		<?php
	}

	/**
	 * Render the topic scope field.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_followup_topic_scope_field() {
		$settings    = $this->get_followup_questions_settings();
		$topic_scope = isset( $settings['topic_scope'] ) ? $settings['topic_scope'] : '';
		?>
		<textarea
			name="humata_followup_questions[topic_scope]"
			rows="3"
			cols="50"
			class="large-text"
			placeholder="<?php esc_attr_e( 'e.g., Your main topics, core services, key products, and related subjects. Prefer questions that deepen understanding rather than logistical matters.', 'humata-chatbot' ); ?>"
		><?php echo esc_textarea( $topic_scope ); ?></textarea>
		<p class="description">
			<?php esc_html_e( 'Guide follow-up questions to stay relevant to your chatbot\'s purpose. Use soft guidance (e.g., "Focus on..." or "Prefer questions about...") rather than hard restrictions for best results. Max 500 characters.', 'humata-chatbot' ); ?>
		</p>
		<?php
	}

	/**
	 * Render the custom instructions field.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_followup_custom_instructions_field() {
		$settings     = $this->get_followup_questions_settings();
		$instructions = isset( $settings['custom_instructions'] ) ? $settings['custom_instructions'] : '';
		?>
		<textarea
			name="humata_followup_questions[custom_instructions]"
			rows="4"
			cols="50"
			class="large-text"
			placeholder="<?php esc_attr_e( 'e.g., Focus on practical next steps. Avoid yes/no questions.', 'humata-chatbot' ); ?>"
		><?php echo esc_textarea( $instructions ); ?></textarea>
		<p class="description">
			<?php esc_html_e( 'Optional additional instructions for the AI when generating follow-up questions. Max 1000 characters.', 'humata-chatbot' ); ?>
		</p>
		<?php
	}
}
