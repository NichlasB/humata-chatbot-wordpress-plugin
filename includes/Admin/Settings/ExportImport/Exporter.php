<?php
/**
 * Exporter (Import/Export feature)
 *
 * Builds an export package containing plugin settings and local data.
 *
 * @package Humata_Chatbot
 * @since 1.3.0
 */

defined( 'ABSPATH' ) || exit;

final class Humata_Chatbot_Admin_Settings_Export_Import_Exporter {

	/**
	 * Settings JSON schema version.
	 *
	 * @since 1.3.0
	 * @var int
	 */
	const SETTINGS_SCHEMA_VERSION = 1;

	/**
	 * Stream a ZIP export to the browser and exit.
	 *
	 * Falls back to a settings-only JSON download if ZipArchive is unavailable.
	 *
	 * @since 1.3.0
	 * @param bool $include_secrets Whether to include API keys/secrets in the export.
	 * @return void|WP_Error
	 */
	public function send_export_zip( $include_secrets ) {
		$include_secrets = (bool) $include_secrets;

		$build = $this->build_settings_json( $include_secrets );
		if ( is_wp_error( $build ) ) {
			return $build;
		}

		$settings_json  = (string) $build['json'];
		$settings_array = (array) $build['data'];

		// If ZipArchive isn't available, deliver settings-only JSON.
		if ( ! class_exists( 'ZipArchive' ) ) {
			$filename = 'humata-settings-' . gmdate( 'Y-m-d' ) . '.json';
			Humata_Chatbot_Admin_Settings_Export_Import_Download::send_string( 'application/json; charset=utf-8', $filename, $settings_json );
			exit;
		}

		$tmp = wp_tempnam( 'humata-export.zip' );
		if ( ! $tmp ) {
			return new WP_Error( 'export_tempfile_error', __( 'Failed to create a temporary export file.', 'humata-chatbot' ) );
		}

		$zip = new ZipArchive();
		$res = $zip->open( $tmp, ZipArchive::OVERWRITE );
		if ( true !== $res ) {
			@unlink( $tmp );
			return new WP_Error( 'export_zip_open_error', __( 'Failed to create ZIP export.', 'humata-chatbot' ) );
		}

		$zip->addFromString( 'humata-settings.json', $settings_json );

		// Include local DB + docs if present.
		$uploads = wp_upload_dir();
		$db_dir  = isset( $uploads['basedir'] ) ? (string) $uploads['basedir'] . '/humata-search' : '';
		$db_path = '' !== $db_dir ? $db_dir . '/index.db' : '';

		// Attempt to checkpoint WAL into the main DB to reduce sidecar size (best-effort).
		$this->maybe_checkpoint_wal( $db_path );

		if ( '' !== $db_path && file_exists( $db_path ) ) {
			$zip->addFile( $db_path, 'humata-search/index.db' );
		}

		// SQLite WAL mode uses sidecar files; include them if present for consistency.
		if ( '' !== $db_path && file_exists( $db_path . '-wal' ) ) {
			$zip->addFile( $db_path . '-wal', 'humata-search/index.db-wal' );
		}
		if ( '' !== $db_path && file_exists( $db_path . '-shm' ) ) {
			$zip->addFile( $db_path . '-shm', 'humata-search/index.db-shm' );
		}

		$docs_dir = '' !== $db_dir ? $db_dir . '/documents' : '';
		if ( '' !== $docs_dir && is_dir( $docs_dir ) ) {
			$files = scandir( $docs_dir );
			if ( is_array( $files ) ) {
				foreach ( $files as $file ) {
					if ( '.' === $file || '..' === $file ) {
						continue;
					}

					$full = $docs_dir . '/' . $file;
					if ( ! is_file( $full ) ) {
						continue;
					}

					// Store as a flat file list under humata-search/documents/.
					$zip->addFile( $full, 'humata-search/documents/' . basename( $full ) );
				}
			}
		}

		$zip->close();

		$filename = 'humata-export-' . gmdate( 'Y-m-d' ) . '.zip';
		Humata_Chatbot_Admin_Settings_Export_Import_Download::send_file( 'application/zip', $filename, $tmp );

		@unlink( $tmp );
		exit;
	}

	/**
	 * Build settings JSON payload.
	 *
	 * @since 1.3.0
	 * @param bool $include_secrets
	 * @return array|WP_Error { json: string, data: array }
	 */
	private function build_settings_json( $include_secrets ) {
		if ( ! class_exists( 'Humata_Chatbot_Admin_Settings_Schema' ) ) {
			return new WP_Error( 'export_schema_missing', __( 'Settings schema is not available.', 'humata-chatbot' ) );
		}

		$include_secrets = (bool) $include_secrets;

		$option_names = array();
		$tab_map      = Humata_Chatbot_Admin_Settings_Schema::get_tab_to_options();
		foreach ( (array) $tab_map as $tab => $opts ) {
			foreach ( (array) $opts as $opt ) {
				$opt = (string) $opt;
				if ( '' !== $opt ) {
					$option_names[] = $opt;
				}
			}
		}
		$option_names = array_values( array_unique( $option_names ) );

		// Also include analytics settings (not part of Settings API group).
		$analytics_options = array(
			'humata_analytics_enabled',
			'humata_analytics_processing_enabled',
			'humata_analytics_provider',
			'humata_analytics_api_key',
			'humata_analytics_model',
			'humata_analytics_system_prompt',
			'humata_analytics_retention_days',
		);

		$options = array();
		foreach ( $option_names as $name ) {
			$options[ $name ] = get_option( $name );
		}
		foreach ( $analytics_options as $name ) {
			$options[ $name ] = get_option( $name );
		}

		$secrets_excluded = array();
		if ( ! $include_secrets ) {
			$options = $this->exclude_secrets( $options, $secrets_excluded );
		}

		$uploads = wp_upload_dir();
		$db_dir  = isset( $uploads['basedir'] ) ? (string) $uploads['basedir'] . '/humata-search' : '';
		$db_path = '' !== $db_dir ? $db_dir . '/index.db' : '';

		$data = array(
			'schema_version' => self::SETTINGS_SCHEMA_VERSION,
			'exported_at'    => gmdate( 'c' ),
			'plugin_version' => defined( 'HUMATA_CHATBOT_VERSION' ) ? HUMATA_CHATBOT_VERSION : '',
			'source_site'    => array(
				'home_url' => home_url(),
				'site_url' => site_url(),
			),
			'options'         => $options,
			'secrets_excluded' => array_values( array_unique( $secrets_excluded ) ),
			'includes'        => array(
				'db'        => ( '' !== $db_path && file_exists( $db_path ) ),
				'documents' => ( '' !== $db_dir && is_dir( $db_dir . '/documents' ) ),
			),
		);

		$json = wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
		if ( ! is_string( $json ) || '' === $json ) {
			return new WP_Error( 'export_json_error', __( 'Failed to encode settings export.', 'humata-chatbot' ) );
		}

		return array(
			'json' => $json,
			'data' => $data,
		);
	}

	/**
	 * Remove or redact secrets from exported options.
	 *
	 * @since 1.3.0
	 * @param array $options
	 * @param array $secrets_excluded Output list of excluded secret keys/paths.
	 * @return array
	 */
	private function exclude_secrets( array $options, array &$secrets_excluded ) {
		// Options that are entirely secrets: omit them from export.
		$secret_option_names = array(
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

		foreach ( $secret_option_names as $name ) {
			if ( array_key_exists( $name, $options ) ) {
				unset( $options[ $name ] );
				$secrets_excluded[] = $name;
			}
		}

		// Follow-up question settings contain nested API keys; keep the rest but blank the keys.
		if ( isset( $options['humata_followup_questions'] ) && is_array( $options['humata_followup_questions'] ) ) {
			$fq = $options['humata_followup_questions'];

			foreach ( array( 'straico_api_keys', 'anthropic_api_keys', 'openrouter_api_keys' ) as $k ) {
				$secrets_excluded[] = 'humata_followup_questions.' . $k;
				$fq[ $k ] = array();
			}

			$options['humata_followup_questions'] = $fq;
		}

		return $options;
	}

	/**
	 * Best-effort WAL checkpoint to reduce sidecar size before export.
	 *
	 * @since 1.3.0
	 * @param string $db_path
	 * @return void
	 */
	private function maybe_checkpoint_wal( $db_path ) {
		$db_path = (string) $db_path;
		if ( '' === $db_path || ! file_exists( $db_path ) || ! class_exists( 'SQLite3' ) ) {
			return;
		}

		try {
			$db = new SQLite3( $db_path );
			$db->enableExceptions( true );
			$db->exec( 'PRAGMA wal_checkpoint(TRUNCATE)' );
			$db->close();
		} catch ( Exception $e ) {
			// Best-effort: ignore checkpoint failures.
		}
	}

}


