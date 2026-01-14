<?php
/**
 * Typography field rendering trait
 *
 * @package Humata_Chatbot
 * @since 1.0.0
 */

defined( 'ABSPATH' ) || exit;

trait Humata_Chatbot_Admin_Settings_Render_Typography_Trait {

	/**
	 * Render typography section description.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_typography_section() {
		?>
		<p><?php esc_html_e( 'Upload custom fonts to use in the chat interface. Download font files from Google Fonts, then upload them here.', 'humata-chatbot' ); ?></p>
		<?php
	}

	/**
	 * Render typography settings field.
	 *
	 * @since 1.0.0
	 * @return void
	 */
	public function render_typography_field() {
		$typography = get_option( 'humata_typography', array() );
		$defaults   = Humata_Chatbot_Admin_Settings_Sanitize_Typography_Trait::get_default_typography_option();
		$typography = wp_parse_args( $typography, $defaults );

		$this->render_font_card( 'heading_font', __( 'Heading Font', 'humata-chatbot' ), $typography['heading_font'] );
		$this->render_font_card( 'body_font', __( 'Body Font', 'humata-chatbot' ), $typography['body_font'] );
	}

	/**
	 * Render a single font configuration card.
	 *
	 * @since 1.0.0
	 * @param string $slot  Font slot key ('heading_font' or 'body_font').
	 * @param string $title Card title.
	 * @param array  $font  Font configuration array.
	 * @return void
	 */
	private function render_font_card( $slot, $title, $font ) {
		$prefix = "humata_typography[{$slot}]";
		?>
		<div class="humata-typography-card" data-font-slot="<?php echo esc_attr( $slot ); ?>">
			<h4 class="humata-typography-card__title"><?php echo esc_html( $title ); ?></h4>

			<div class="humata-typography-card__row">
				<label>
					<input
						type="checkbox"
						name="<?php echo esc_attr( $prefix ); ?>[enabled]"
						value="1"
						<?php checked( $font['enabled'], true ); ?>
						class="humata-font-enabled-toggle"
					/>
					<?php esc_html_e( 'Enable custom font', 'humata-chatbot' ); ?>
				</label>
			</div>

			<div class="humata-typography-card__fields" <?php echo $font['enabled'] ? '' : 'style="display:none;"'; ?>>
				<div class="humata-typography-card__row">
					<label for="<?php echo esc_attr( $slot ); ?>_family_name">
						<?php esc_html_e( 'Font Family Name:', 'humata-chatbot' ); ?>
					</label>
					<input
						type="text"
						id="<?php echo esc_attr( $slot ); ?>_family_name"
						name="<?php echo esc_attr( $prefix ); ?>[family_name]"
						value="<?php echo esc_attr( $font['family_name'] ); ?>"
						class="regular-text"
						placeholder="<?php esc_attr_e( 'e.g., Inter, Roboto', 'humata-chatbot' ); ?>"
					/>
					<p class="description"><?php esc_html_e( 'Enter the exact font family name as shown on Google Fonts.', 'humata-chatbot' ); ?></p>
				</div>

				<div class="humata-typography-card__row">
					<span class="humata-typography-card__label"><?php esc_html_e( 'Font Type:', 'humata-chatbot' ); ?></span>
					<label>
						<input
							type="radio"
							name="<?php echo esc_attr( $prefix ); ?>[font_type]"
							value="variable"
							<?php checked( $font['font_type'], 'variable' ); ?>
							class="humata-font-type-radio"
						/>
						<?php esc_html_e( 'Variable Font (single file)', 'humata-chatbot' ); ?>
					</label>
					<label>
						<input
							type="radio"
							name="<?php echo esc_attr( $prefix ); ?>[font_type]"
							value="static"
							<?php checked( $font['font_type'], 'static' ); ?>
							class="humata-font-type-radio"
						/>
						<?php esc_html_e( 'Static Fonts (multiple weight files)', 'humata-chatbot' ); ?>
					</label>
				</div>

				<!-- Variable font upload -->
				<div class="humata-typography-card__variable" <?php echo 'variable' === $font['font_type'] ? '' : 'style="display:none;"'; ?>>
					<div class="humata-typography-card__row">
						<label><?php esc_html_e( 'Font File:', 'humata-chatbot' ); ?></label>
						<div class="humata-font-uploader">
							<input
								type="hidden"
								name="<?php echo esc_attr( $prefix ); ?>[variable_url]"
								value="<?php echo esc_attr( $font['variable_url'] ); ?>"
								class="humata-font-url-input"
							/>
							<button type="button" class="button humata-upload-font">
								<?php esc_html_e( 'Select Font File', 'humata-chatbot' ); ?>
							</button>
							<button
								type="button"
								class="button humata-remove-font"
								<?php echo empty( $font['variable_url'] ) ? 'style="display:none;"' : ''; ?>
							>
								<?php esc_html_e( 'Remove', 'humata-chatbot' ); ?>
							</button>
							<span class="humata-font-filename">
								<?php echo ! empty( $font['variable_url'] ) ? esc_html( basename( $font['variable_url'] ) ) : ''; ?>
							</span>
						</div>
					</div>
				</div>

				<!-- Static font weights -->
				<div class="humata-typography-card__static" <?php echo 'static' === $font['font_type'] ? '' : 'style="display:none;"'; ?>>
					<div class="humata-static-weights" data-prefix="<?php echo esc_attr( $prefix ); ?>">
						<?php
						$weights = ! empty( $font['static_weights'] ) ? $font['static_weights'] : array();
						if ( empty( $weights ) ) {
							// Show one empty row by default.
							$weights = array( array( 'weight' => '400', 'url' => '' ) );
						}
						foreach ( $weights as $index => $weight_entry ) :
							$this->render_static_weight_row( $prefix, $index, $weight_entry );
						endforeach;
						?>
					</div>
					<button type="button" class="button humata-add-weight">
						<?php esc_html_e( '+ Add Weight', 'humata-chatbot' ); ?>
					</button>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render a static weight row.
	 *
	 * @since 1.0.0
	 * @param string $prefix       Input name prefix.
	 * @param int    $index        Row index.
	 * @param array  $weight_entry Weight/URL pair.
	 * @return void
	 */
	private function render_static_weight_row( $prefix, $index, $weight_entry ) {
		$weight = isset( $weight_entry['weight'] ) ? $weight_entry['weight'] : '400';
		$url    = isset( $weight_entry['url'] ) ? $weight_entry['url'] : '';
		?>
		<div class="humata-static-weight-row">
			<select name="<?php echo esc_attr( $prefix ); ?>[static_weights][<?php echo esc_attr( $index ); ?>][weight]">
				<option value="100" <?php selected( $weight, '100' ); ?>>100 (Thin)</option>
				<option value="200" <?php selected( $weight, '200' ); ?>>200 (Extra Light)</option>
				<option value="300" <?php selected( $weight, '300' ); ?>>300 (Light)</option>
				<option value="400" <?php selected( $weight, '400' ); ?>>400 (Regular)</option>
				<option value="500" <?php selected( $weight, '500' ); ?>>500 (Medium)</option>
				<option value="600" <?php selected( $weight, '600' ); ?>>600 (Semi Bold)</option>
				<option value="700" <?php selected( $weight, '700' ); ?>>700 (Bold)</option>
				<option value="800" <?php selected( $weight, '800' ); ?>>800 (Extra Bold)</option>
				<option value="900" <?php selected( $weight, '900' ); ?>>900 (Black)</option>
			</select>
			<div class="humata-font-uploader">
				<input
					type="hidden"
					name="<?php echo esc_attr( $prefix ); ?>[static_weights][<?php echo esc_attr( $index ); ?>][url]"
					value="<?php echo esc_attr( $url ); ?>"
					class="humata-font-url-input"
				/>
				<button type="button" class="button humata-upload-font">
					<?php esc_html_e( 'Select File', 'humata-chatbot' ); ?>
				</button>
				<button
					type="button"
					class="button humata-remove-font"
					<?php echo empty( $url ) ? 'style="display:none;"' : ''; ?>
				>
					<?php esc_html_e( 'Remove', 'humata-chatbot' ); ?>
				</button>
				<span class="humata-font-filename">
					<?php echo ! empty( $url ) ? esc_html( basename( $url ) ) : ''; ?>
				</span>
			</div>
			<button type="button" class="button humata-remove-weight-row" title="<?php esc_attr_e( 'Remove weight', 'humata-chatbot' ); ?>">&times;</button>
		</div>
		<?php
	}
}
