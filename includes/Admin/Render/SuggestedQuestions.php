<?php
/**
 * Suggested Questions Render Trait
 *
 * @package Humata_Chatbot
 * @since 1.0.0
 */

defined( 'ABSPATH' ) || exit;

trait Humata_Chatbot_Admin_Settings_Render_SuggestedQuestions_Trait {

	/**
	 * Render the suggested questions section description.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_suggested_questions_section() {
		echo '<p>' . esc_html__( 'Configure suggested question prompts that appear above the chat input to help users get started.', 'humata-chatbot' ) . '</p>';
	}

	/**
	 * Render the enable suggested questions checkbox.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_suggested_questions_enabled_field() {
		$settings = $this->get_suggested_questions_settings();
		$enabled  = ! empty( $settings['enabled'] );
		?>
		<label>
			<input
				type="checkbox"
				name="humata_suggested_questions[enabled]"
				value="1"
				<?php checked( $enabled ); ?>
			/>
			<?php esc_html_e( 'Enable Suggested Questions', 'humata-chatbot' ); ?>
		</label>
		<p class="description">
			<?php esc_html_e( 'When enabled, suggested question buttons appear above the chat input on initial load.', 'humata-chatbot' ); ?>
		</p>
		<?php
	}

	/**
	 * Render the mode selection field.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_suggested_questions_mode_field() {
		$settings = $this->get_suggested_questions_settings();
		$mode     = isset( $settings['mode'] ) ? $settings['mode'] : 'fixed';
		?>
		<fieldset>
			<label>
				<input
					type="radio"
					name="humata_suggested_questions[mode]"
					value="fixed"
					<?php checked( $mode, 'fixed' ); ?>
					class="humata-sq-mode-radio"
				/>
				<?php esc_html_e( 'Fixed Mode', 'humata-chatbot' ); ?>
			</label>
			<p class="description" style="margin-left: 24px; margin-top: 4px;">
				<?php esc_html_e( 'Display up to 4 specific questions in a defined order.', 'humata-chatbot' ); ?>
			</p>
			<br>
			<label>
				<input
					type="radio"
					name="humata_suggested_questions[mode]"
					value="randomized"
					<?php checked( $mode, 'randomized' ); ?>
					class="humata-sq-mode-radio"
				/>
				<?php esc_html_e( 'Randomized Mode', 'humata-chatbot' ); ?>
			</label>
			<p class="description" style="margin-left: 24px; margin-top: 4px;">
				<?php esc_html_e( 'Randomly select questions from categories on each page load.', 'humata-chatbot' ); ?>
			</p>
		</fieldset>
		<?php
	}

	/**
	 * Render the fixed questions repeater.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_fixed_questions_field() {
		$settings  = $this->get_suggested_questions_settings();
		$questions = isset( $settings['fixed_questions'] ) ? $settings['fixed_questions'] : array();

		// Show at least one empty row.
		if ( empty( $questions ) ) {
			$questions = array( array( 'text' => '', 'order' => 0 ) );
		}

		$next_index = count( $questions );
		?>
		<div class="humata-sq-fixed-section" data-humata-repeater="sq_fixed" data-next-index="<?php echo esc_attr( (string) $next_index ); ?>">
			<p class="description" style="margin-top: 0;">
				<?php esc_html_e( 'Drag and drop to reorder. Maximum 4 questions.', 'humata-chatbot' ); ?>
			</p>
			<table class="widefat striped" style="max-width: 700px;">
				<thead>
					<tr>
						<th style="width: 36px;"><span class="screen-reader-text"><?php esc_html_e( 'Order', 'humata-chatbot' ); ?></span></th>
						<th><?php esc_html_e( 'Question Text', 'humata-chatbot' ); ?></th>
						<th style="width: 90px;"><?php esc_html_e( 'Actions', 'humata-chatbot' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $questions as $i => $q ) : ?>
						<?php
						$q    = is_array( $q ) ? $q : array();
						$text = isset( $q['text'] ) ? (string) $q['text'] : '';
						?>
						<tr class="humata-repeater-row humata-sq-fixed-row">
							<td class="humata-repeater-handle-cell">
								<span class="dashicons dashicons-move humata-repeater-handle" aria-hidden="true"></span>
								<span class="screen-reader-text"><?php esc_html_e( 'Drag to reorder', 'humata-chatbot' ); ?></span>
							</td>
							<td>
								<input
									type="text"
									class="large-text"
									name="humata_suggested_questions[fixed_questions][<?php echo esc_attr( (string) $i ); ?>][text]"
									value="<?php echo esc_attr( $text ); ?>"
									placeholder="<?php esc_attr_e( 'e.g., What are your business hours?', 'humata-chatbot' ); ?>"
									maxlength="150"
								/>
							</td>
							<td>
								<button type="button" class="button link-delete humata-repeater-remove"><?php esc_html_e( 'Remove', 'humata-chatbot' ); ?></button>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<p style="margin-top: 10px;">
				<button type="button" class="button button-secondary humata-sq-fixed-add"><?php esc_html_e( 'Add Question', 'humata-chatbot' ); ?></button>
			</p>
			<p class="description">
				<?php esc_html_e( 'Questions appear in a 2×2 grid: top-left, top-right, bottom-left, bottom-right.', 'humata-chatbot' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Render the randomized categories repeater.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_randomized_categories_field() {
		$settings   = $this->get_suggested_questions_settings();
		$categories = isset( $settings['categories'] ) ? $settings['categories'] : array();

		// Show at least one empty category.
		if ( empty( $categories ) ) {
			$categories = array(
				array(
					'name'      => '',
					'questions' => array( '' ),
				),
			);
		}

		$next_cat_index = count( $categories );
		?>
		<div class="humata-sq-randomized-section" data-next-cat-index="<?php echo esc_attr( (string) $next_cat_index ); ?>">
			<p class="description" style="margin-top: 0;">
				<?php esc_html_e( 'Create up to 4 categories. Questions are randomly selected from each category on page load.', 'humata-chatbot' ); ?>
			</p>

			<div class="humata-sq-categories-container">
				<?php foreach ( $categories as $i => $cat ) : ?>
					<?php
					$cat       = is_array( $cat ) ? $cat : array();
					$cat_name  = isset( $cat['name'] ) ? (string) $cat['name'] : '';
					$questions = isset( $cat['questions'] ) && is_array( $cat['questions'] ) ? $cat['questions'] : array( '' );

					if ( empty( $questions ) ) {
						$questions = array( '' );
					}

					$next_q_index = count( $questions );
					?>
					<div class="humata-sq-category-card" data-cat-index="<?php echo esc_attr( (string) $i ); ?>">
						<div class="humata-sq-category-header">
							<button type="button" class="humata-sq-category-toggle" aria-expanded="true" aria-label="<?php esc_attr_e( 'Toggle category', 'humata-chatbot' ); ?>">
								<span class="dashicons dashicons-arrow-down-alt2"></span>
							</button>
							<span class="humata-sq-category-title">
								<?php
								/* translators: %d: category number */
								printf( esc_html__( 'Category #%d', 'humata-chatbot' ), ( $i + 1 ) );
								?>
							</span>
							<button type="button" class="button link-delete humata-sq-category-remove"><?php esc_html_e( 'Remove Category', 'humata-chatbot' ); ?></button>
						</div>
						<div class="humata-sq-category-body">
							<p>
								<label><strong><?php esc_html_e( 'Category Name', 'humata-chatbot' ); ?></strong> <span class="description"><?php esc_html_e( '(optional)', 'humata-chatbot' ); ?></span></label><br>
								<input
									type="text"
									class="regular-text"
									name="humata_suggested_questions[categories][<?php echo esc_attr( (string) $i ); ?>][name]"
									value="<?php echo esc_attr( $cat_name ); ?>"
									placeholder="<?php esc_attr_e( 'e.g., Product Questions', 'humata-chatbot' ); ?>"
									maxlength="50"
								/>
							</p>
							<div class="humata-sq-questions-sub" data-next-q-index="<?php echo esc_attr( (string) $next_q_index ); ?>">
								<label><strong><?php esc_html_e( 'Questions', 'humata-chatbot' ); ?></strong></label>
								<p class="description"><?php esc_html_e( 'At least 1 question required per category.', 'humata-chatbot' ); ?></p>
								<table class="widefat striped humata-sq-questions-table" style="max-width: 650px; margin-top: 6px;">
									<thead>
										<tr>
											<th><?php esc_html_e( 'Question Text', 'humata-chatbot' ); ?></th>
											<th style="width: 80px;"><?php esc_html_e( 'Actions', 'humata-chatbot' ); ?></th>
										</tr>
									</thead>
									<tbody>
										<?php foreach ( $questions as $j => $q_text ) : ?>
											<?php $q_text = is_string( $q_text ) ? $q_text : ''; ?>
											<tr class="humata-sq-question-row">
												<td>
													<input
														type="text"
														class="large-text"
														name="humata_suggested_questions[categories][<?php echo esc_attr( (string) $i ); ?>][questions][<?php echo esc_attr( (string) $j ); ?>]"
														value="<?php echo esc_attr( $q_text ); ?>"
														placeholder="<?php esc_attr_e( 'e.g., How do I track my order?', 'humata-chatbot' ); ?>"
														maxlength="150"
													/>
												</td>
												<td>
													<button type="button" class="button link-delete humata-sq-question-remove"><?php esc_html_e( 'Remove', 'humata-chatbot' ); ?></button>
												</td>
											</tr>
										<?php endforeach; ?>
									</tbody>
								</table>
								<p style="margin-top: 8px;">
									<button type="button" class="button button-secondary humata-sq-question-add"><?php esc_html_e( 'Add Question', 'humata-chatbot' ); ?></button>
								</p>
							</div>
						</div>
					</div>
				<?php endforeach; ?>
			</div>

			<p style="margin-top: 16px;">
				<button type="button" class="button button-secondary humata-sq-category-add"><?php esc_html_e( 'Add Category', 'humata-chatbot' ); ?></button>
			</p>
			<p class="description">
				<?php esc_html_e( 'Distribution: 1 category = all 4 slots; 2 categories = 2 slots each; 3 categories = one gets 2 slots (random); 4 categories = 1 slot each.', 'humata-chatbot' ); ?>
			</p>
		</div>
		<?php
	}
}
