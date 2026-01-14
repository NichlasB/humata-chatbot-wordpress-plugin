<?php
/**
 * Importer (Import/Export feature)
 *
 * Orchestrates importing settings JSON and (optionally) restoring local data from a ZIP export.
 *
 * @package Humata_Chatbot
 * @since 1.3.0
 */

defined( 'ABSPATH' ) || exit;

final class Humata_Chatbot_Admin_Settings_Export_Import_Importer {

	/**
	 * Maximum accepted upload size in bytes (default 100MB).
	 *
	 * @since 1.3.0
	 * @var int
	 */
	const MAX_UPLOAD_BYTES = 104857600;

	/**
	 * Handle uploaded file and import.
	 *
	 * @since 1.3.0
	 * @param array $files                 $_FILES array.
	 * @param bool  $keep_existing_secrets Keep existing secrets when omitted from import.
	 * @param bool  $overwrite_data        Whether to overwrite DB/documents data when present.
	 * @return true|WP_Error
	 */
	public function handle_upload_and_import( $files, $keep_existing_secrets, $overwrite_data ) {
		$keep_existing_secrets = (bool) $keep_existing_secrets;
		$overwrite_data        = (bool) $overwrite_data;

		$file = $this->get_uploaded_file( $files );
		if ( is_wp_error( $file ) ) {
			return $file;
		}

		$ext = $this->detect_extension( $file['tmp_name'], $file['name'] );

		if ( 'json' === $ext ) {
			$result = $this->import_settings_json_file( $file['tmp_name'], $keep_existing_secrets );
			if ( is_wp_error( $result ) ) {
				return $result;
			}

			flush_rewrite_rules();
			return true;
		}

		if ( 'zip' === $ext ) {
			$result = $this->import_zip_package( $file['tmp_name'], $keep_existing_secrets, $overwrite_data );
			if ( is_wp_error( $result ) ) {
				return $result;
			}

			flush_rewrite_rules();
			return true;
		}

		return new WP_Error( 'import_invalid_type', __( 'Invalid file type. Please upload a .zip or .json export.', 'humata-chatbot' ) );
	}

	/**
	 * Validate and normalize the uploaded file array.
	 *
	 * @since 1.3.0
	 * @param mixed $files
	 * @return array|WP_Error { tmp_name: string, name: string, size: int }
	 */
	private function get_uploaded_file( $files ) {
		if ( ! is_array( $files ) || empty( $files['humata_import_file'] ) || ! is_array( $files['humata_import_file'] ) ) {
			return new WP_Error( 'import_no_file', __( 'No import file was provided.', 'humata-chatbot' ) );
		}

		$file  = $files['humata_import_file'];
		$error = isset( $file['error'] ) ? (int) $file['error'] : UPLOAD_ERR_NO_FILE;
		if ( UPLOAD_ERR_OK !== $error ) {
			return new WP_Error( 'import_upload_error', __( 'Upload failed. Please try again.', 'humata-chatbot' ) );
		}

		$tmp_name = isset( $file['tmp_name'] ) ? (string) $file['tmp_name'] : '';
		$name     = isset( $file['name'] ) ? (string) $file['name'] : '';
		$size     = isset( $file['size'] ) ? absint( $file['size'] ) : 0;

		if ( '' === $tmp_name || ! file_exists( $tmp_name ) ) {
			return new WP_Error( 'import_tmp_missing', __( 'Upload failed (temporary file missing).', 'humata-chatbot' ) );
		}

		if ( $size <= 0 ) {
			return new WP_Error( 'import_empty', __( 'The uploaded file is empty.', 'humata-chatbot' ) );
		}

		if ( $size > self::MAX_UPLOAD_BYTES ) {
			return new WP_Error(
				'import_too_large',
				sprintf(
					/* translators: %s: size limit */
					__( 'Import file is too large. Maximum size is %s.', 'humata-chatbot' ),
					size_format( self::MAX_UPLOAD_BYTES )
				)
			);
		}

		return array(
			'tmp_name' => $tmp_name,
			'name'     => sanitize_file_name( $name ),
			'size'     => $size,
		);
	}

	/**
	 * Detect the uploaded file extension using WordPress helpers.
	 *
	 * @since 1.3.0
	 * @param string $tmp_name
	 * @param string $name
	 * @return string Lowercase extension (zip/json) or empty string.
	 */
	private function detect_extension( $tmp_name, $name ) {
		$type = wp_check_filetype_and_ext( (string) $tmp_name, (string) $name );
		return isset( $type['ext'] ) ? strtolower( (string) $type['ext'] ) : '';
	}

	/**
	 * Import settings from a JSON file.
	 *
	 * @since 1.3.0
	 * @param string $json_path
	 * @param bool   $keep_existing_secrets
	 * @return true|WP_Error
	 */
	private function import_settings_json_file( $json_path, $keep_existing_secrets ) {
		$contents = file_get_contents( (string) $json_path );
		if ( false === $contents ) {
			return new WP_Error( 'import_read_error', __( 'Failed to read the uploaded file.', 'humata-chatbot' ) );
		}

		$data = json_decode( (string) $contents, true );
		if ( ! is_array( $data ) ) {
			return new WP_Error( 'import_json_invalid', __( 'Invalid JSON export file.', 'humata-chatbot' ) );
		}

		$options = new Humata_Chatbot_Admin_Settings_Export_Import_Options();
		return $options->import_settings_data( $data, (bool) $keep_existing_secrets );
	}

	/**
	 * Import a full ZIP package (settings + optional local DB/docs).
	 *
	 * @since 1.3.0
	 * @param string $zip_path
	 * @param bool   $keep_existing_secrets
	 * @param bool   $overwrite_data
	 * @return true|WP_Error
	 */
	private function import_zip_package( $zip_path, $keep_existing_secrets, $overwrite_data ) {
		$zip_path              = (string) $zip_path;
		$keep_existing_secrets = (bool) $keep_existing_secrets;
		$overwrite_data        = (bool) $overwrite_data;

		$zip_pkg  = new Humata_Chatbot_Admin_Settings_Export_Import_Zip_Package();
		$work_dir = $zip_pkg->extract_to_work_dir( $zip_path );
		if ( is_wp_error( $work_dir ) ) {
			return $work_dir;
		}

		$settings_path = (string) $work_dir . '/humata-settings.json';
		if ( ! file_exists( $settings_path ) ) {
			$zip_pkg->cleanup_work_dir( $work_dir );
			return new WP_Error( 'import_missing_settings', __( 'ZIP export is missing humata-settings.json.', 'humata-chatbot' ) );
		}

		$contents = file_get_contents( $settings_path );
		if ( false === $contents ) {
			$zip_pkg->cleanup_work_dir( $work_dir );
			return new WP_Error( 'import_read_error', __( 'Failed to read settings from ZIP export.', 'humata-chatbot' ) );
		}

		$data = json_decode( (string) $contents, true );
		if ( ! is_array( $data ) ) {
			$zip_pkg->cleanup_work_dir( $work_dir );
			return new WP_Error( 'import_json_invalid', __( 'Invalid settings JSON inside ZIP export.', 'humata-chatbot' ) );
		}

		$options = new Humata_Chatbot_Admin_Settings_Export_Import_Options();
		$result  = $options->import_settings_data( $data, $keep_existing_secrets );
		if ( is_wp_error( $result ) ) {
			$zip_pkg->cleanup_work_dir( $work_dir );
			return $result;
		}

		if ( $overwrite_data ) {
			$local  = new Humata_Chatbot_Admin_Settings_Export_Import_Local_Data();
			$stored = $local->restore_from_work_dir( $work_dir );
			if ( is_wp_error( $stored ) ) {
				$zip_pkg->cleanup_work_dir( $work_dir );
				return $stored;
			}
		}

		$zip_pkg->cleanup_work_dir( $work_dir );
		return true;
	}
}


