<?php
/**
 * Typography sanitization trait
 *
 * @package Humata_Chatbot
 * @since 1.0.0
 */

defined( 'ABSPATH' ) || exit;

trait Humata_Chatbot_Admin_Settings_Sanitize_Typography_Trait {

	/**
	 * Get default typography option structure.
	 *
	 * @since 1.0.0
	 * @return array
	 */
	public static function get_default_typography_option() {
		return array(
			'heading_font' => array(
				'enabled'        => false,
				'family_name'    => '',
				'font_type'      => 'variable',
				'variable_url'   => '',
				'static_weights' => array(),
			),
			'body_font'    => array(
				'enabled'        => false,
				'family_name'    => '',
				'font_type'      => 'variable',
				'variable_url'   => '',
				'static_weights' => array(),
			),
		);
	}

	/**
	 * Sanitize typography settings.
	 *
	 * @since 1.0.0
	 * @param mixed $value Raw input.
	 * @return array Sanitized typography settings.
	 */
	public function sanitize_typography( $value ) {
		$defaults = self::get_default_typography_option();

		if ( ! is_array( $value ) ) {
			return $defaults;
		}

		$sanitized = array();

		foreach ( array( 'heading_font', 'body_font' ) as $slot ) {
			$font = isset( $value[ $slot ] ) && is_array( $value[ $slot ] )
				? $value[ $slot ]
				: array();

			$sanitized[ $slot ] = array(
				'enabled'        => ! empty( $font['enabled'] ),
				'family_name'    => isset( $font['family_name'] )
					? sanitize_text_field( trim( $font['family_name'] ) )
					: '',
				'font_type'      => isset( $font['font_type'] ) &&
									in_array( $font['font_type'], array( 'variable', 'static' ), true )
					? $font['font_type']
					: 'variable',
				'variable_url'   => isset( $font['variable_url'] )
					? esc_url_raw( $font['variable_url'] )
					: '',
				'static_weights' => array(),
			);

			// Sanitize static weights.
			if ( isset( $font['static_weights'] ) && is_array( $font['static_weights'] ) ) {
				foreach ( $font['static_weights'] as $weight_entry ) {
					if ( ! is_array( $weight_entry ) ) {
						continue;
					}
					$weight = isset( $weight_entry['weight'] )
						? sanitize_text_field( $weight_entry['weight'] )
						: '';
					$url    = isset( $weight_entry['url'] )
						? esc_url_raw( $weight_entry['url'] )
						: '';

					// Validate weight is numeric (100-900 range).
					if ( preg_match( '/^[1-9]00$/', $weight ) && '' !== $url ) {
						$sanitized[ $slot ]['static_weights'][] = array(
							'weight' => $weight,
							'url'    => $url,
						);
					}
				}
			}
		}

		return $sanitized;
	}
}
