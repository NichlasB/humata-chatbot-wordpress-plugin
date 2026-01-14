<?php
/**
 * Options importer (Import/Export feature)
 *
 * Applies imported options with sanitization and secret-handling semantics.
 *
 * @package Humata_Chatbot
 * @since 1.3.0
 */

defined( 'ABSPATH' ) || exit;

final class Humata_Chatbot_Admin_Settings_Export_Import_Options {

	/**
	 * Import options from decoded settings JSON.
	 *
	 * @since 1.3.0
	 * @param array $data
	 * @param bool  $keep_existing_secrets
	 * @return true|WP_Error
	 */
	public function import_settings_data( array $data, $keep_existing_secrets ) {
		$keep_existing_secrets = (bool) $keep_existing_secrets;

		if ( empty( $data['options'] ) || ! is_array( $data['options'] ) ) {
			return new WP_Error( 'import_missing_options', __( 'Export file is missing options.', 'humata-chatbot' ) );
		}

		$options          = (array) $data['options'];
		$secrets_excluded = $this->parse_secrets_excluded( $data );

		return $this->apply_options( $options, $secrets_excluded, $keep_existing_secrets );
	}

	/**
	 * Apply imported options to the site.
	 *
	 * @since 1.3.0
	 * @param array $options
	 * @param array $secrets_excluded
	 * @param bool  $keep_existing_secrets
	 * @return true|WP_Error
	 */
	private function apply_options( array $options, array $secrets_excluded, $keep_existing_secrets ) {
		$keep_existing_secrets = (bool) $keep_existing_secrets;
		$allowed_option_names  = $this->get_allowed_option_names();

		$options = $this->maybe_merge_followup_secrets( $options, $secrets_excluded, $keep_existing_secrets );

		foreach ( $options as $name => $value ) {
			$name = (string) $name;
			if ( '' === $name || ! in_array( $name, $allowed_option_names, true ) ) {
				continue;
			}

			if ( 0 === strpos( $name, 'humata_analytics_' ) ) {
				update_option( $name, $this->sanitize_analytics_option( $name, $value ) );
				continue;
			}

			// Settings API options: sanitize callbacks are registered via register_setting().
			update_option( $name, $value );
		}

		// If the user does NOT want to keep existing secrets, clear secrets that were omitted.
		if ( ! $keep_existing_secrets ) {
			$this->clear_omitted_secrets( $options, $secrets_excluded );
		}

		return true;
	}

	/**
	 * Parse secrets_excluded from JSON data.
	 *
	 * @since 1.3.0
	 * @param array $data
	 * @return array
	 */
	private function parse_secrets_excluded( array $data ) {
		$out = array();
		if ( isset( $data['secrets_excluded'] ) && is_array( $data['secrets_excluded'] ) ) {
			foreach ( $data['secrets_excluded'] as $v ) {
				if ( is_string( $v ) && '' !== $v ) {
					$out[] = $v;
				}
			}
		}

		return array_values( array_unique( $out ) );
	}

	/**
	 * Preserve follow-up question API keys if the export omitted secrets and user wants to keep them.
	 *
	 * @since 1.3.0
	 * @param array $options
	 * @param array $secrets_excluded
	 * @param bool  $keep_existing_secrets
	 * @return array
	 */
	private function maybe_merge_followup_secrets( array $options, array $secrets_excluded, $keep_existing_secrets ) {
		if ( ! $keep_existing_secrets ) {
			return $options;
		}

		if ( ! isset( $options['humata_followup_questions'] ) || ! is_array( $options['humata_followup_questions'] ) ) {
			return $options;
		}

		$fq = $options['humata_followup_questions'];

		$existing = get_option( 'humata_followup_questions', array() );
		if ( ! is_array( $existing ) ) {
			$existing = array();
		}

		foreach ( array( 'straico_api_keys', 'anthropic_api_keys', 'openrouter_api_keys' ) as $k ) {
			$path = 'humata_followup_questions.' . $k;
			if ( in_array( $path, $secrets_excluded, true ) && isset( $existing[ $k ] ) ) {
				$fq[ $k ] = $existing[ $k ];
			}
		}

		$options['humata_followup_questions'] = $fq;
		return $options;
	}

	/**
	 * Clear secrets if user opted out of preserving omitted secrets.
	 *
	 * @since 1.3.0
	 * @param array $options
	 * @param array $secrets_excluded
	 * @return void
	 */
	private function clear_omitted_secrets( array $options, array $secrets_excluded ) {
		foreach ( $this->get_secret_option_names() as $secret_name ) {
			if ( isset( $options[ $secret_name ] ) ) {
				continue;
			}

			if ( in_array( $secret_name, $secrets_excluded, true ) ) {
				$this->clear_secret_option( $secret_name );
			}
		}

		// Clear follow-up nested keys if they were excluded.
		if ( isset( $options['humata_followup_questions'] ) && is_array( $options['humata_followup_questions'] ) ) {
			$fq = $options['humata_followup_questions'];
			foreach ( array( 'straico_api_keys', 'anthropic_api_keys', 'openrouter_api_keys' ) as $k ) {
				$path = 'humata_followup_questions.' . $k;
				if ( in_array( $path, $secrets_excluded, true ) ) {
					$fq[ $k ] = array();
				}
			}
			update_option( 'humata_followup_questions', $fq );
		}
	}

	/**
	 * Get allowed option names that can be imported.
	 *
	 * @since 1.3.0
	 * @return array
	 */
	private function get_allowed_option_names() {
		$names = array();

		if ( class_exists( 'Humata_Chatbot_Admin_Settings_Schema' ) ) {
			$map = Humata_Chatbot_Admin_Settings_Schema::get_tab_to_options();
			foreach ( (array) $map as $tab => $opts ) {
				foreach ( (array) $opts as $opt ) {
					$opt = (string) $opt;
					if ( '' !== $opt ) {
						$names[] = $opt;
					}
				}
			}
		}

		// Analytics options (not part of Settings API group).
		$names = array_merge(
			$names,
			array(
				'humata_analytics_enabled',
				'humata_analytics_processing_enabled',
				'humata_analytics_provider',
				'humata_analytics_api_key',
				'humata_analytics_model',
				'humata_analytics_system_prompt',
				'humata_analytics_retention_days',
			)
		);

		return array_values( array_unique( $names ) );
	}

	/**
	 * Secret option names (top-level) used for include/exclude semantics.
	 *
	 * @since 1.3.0
	 * @return array
	 */
	private function get_secret_option_names() {
		return array(
			'humata_api_key',
			'humata_straico_api_key',
			'humata_anthropic_api_key',
			'humata_openrouter_api_key',
			'humata_local_first_straico_api_key',
			'humata_local_first_anthropic_api_key',
			'humata_local_first_openrouter_api_key',
			'humata_local_second_straico_api_key',
			'humata_local_second_anthropic_api_key',
			'humata_local_second_openrouter_api_key',
			'humata_analytics_api_key',
		);
	}

	/**
	 * Clear a secret option to an empty value.
	 *
	 * @since 1.3.0
	 * @param string $option_name
	 * @return void
	 */
	private function clear_secret_option( $option_name ) {
		$option_name = (string) $option_name;

		if ( 'humata_api_key' === $option_name ) {
			update_option( $option_name, '' );
			return;
		}

		update_option( $option_name, array() );
	}

	/**
	 * Sanitize analytics option values (they are not registered via Settings API).
	 *
	 * @since 1.3.0
	 * @param string $name
	 * @param mixed  $value
	 * @return mixed
	 */
	private function sanitize_analytics_option( $name, $value ) {
		$name = (string) $name;

		if ( 'humata_analytics_enabled' === $name || 'humata_analytics_processing_enabled' === $name ) {
			return empty( $value ) ? 0 : 1;
		}

		if ( 'humata_analytics_provider' === $name ) {
			$provider = is_string( $value ) ? sanitize_key( $value ) : '';
			return in_array( $provider, array( 'anthropic', 'openrouter', 'straico' ), true ) ? $provider : '';
		}

		if ( 'humata_analytics_api_key' === $name ) {
			if ( is_array( $value ) ) {
				$keys = array();
				foreach ( $value as $k ) {
					$k = sanitize_text_field( trim( (string) $k ) );
					if ( '' !== $k ) {
						$keys[] = $k;
					}
				}
				return $keys;
			}

			$k = is_string( $value ) ? sanitize_text_field( trim( $value ) ) : '';
			return '' !== $k ? array( $k ) : array();
		}

		if ( 'humata_analytics_model' === $name ) {
			return is_string( $value ) ? sanitize_text_field( trim( $value ) ) : '';
		}

		if ( 'humata_analytics_system_prompt' === $name ) {
			return is_string( $value ) ? sanitize_textarea_field( $value ) : '';
		}

		if ( 'humata_analytics_retention_days' === $name ) {
			return absint( $value );
		}

		return $value;
	}
}


