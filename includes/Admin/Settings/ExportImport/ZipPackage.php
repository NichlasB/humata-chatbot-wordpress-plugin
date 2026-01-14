<?php
/**
 * ZIP package helper (Import/Export feature)
 *
 * Validates ZIP contents and extracts only allowed entries to a temp directory.
 *
 * @package Humata_Chatbot
 * @since 1.3.0
 */

defined( 'ABSPATH' ) || exit;

final class Humata_Chatbot_Admin_Settings_Export_Import_Zip_Package {

	/**
	 * Extract allowed entries from a ZIP file to a temporary directory.
	 *
	 * @since 1.3.0
	 * @param string $zip_path
	 * @return string|WP_Error Working directory path.
	 */
	public function extract_to_work_dir( $zip_path ) {
		$zip_path = (string) $zip_path;

		if ( '' === $zip_path || ! file_exists( $zip_path ) ) {
			return new WP_Error( 'import_zip_missing', __( 'ZIP file not found.', 'humata-chatbot' ) );
		}

		if ( ! class_exists( 'ZipArchive' ) ) {
			return new WP_Error( 'import_zip_unavailable', __( 'ZIP import is not available on this server.', 'humata-chatbot' ) );
		}

		$zip = new ZipArchive();
		$res = $zip->open( $zip_path );
		if ( true !== $res ) {
			return new WP_Error( 'import_zip_open', __( 'Failed to open ZIP export.', 'humata-chatbot' ) );
		}

		$allowed = $this->get_allowed_zip_entries( $zip );
		if ( is_wp_error( $allowed ) ) {
			$zip->close();
			return $allowed;
		}

		$work_dir = $this->create_work_dir();
		if ( is_wp_error( $work_dir ) ) {
			$zip->close();
			return $work_dir;
		}

		foreach ( $allowed as $entry ) {
			$ok = $zip->extractTo( $work_dir, array( $entry ) );
			if ( true !== $ok ) {
				$zip->close();
				$this->delete_dir_recursive( $work_dir );
				return new WP_Error( 'import_zip_extract', __( 'Failed to extract ZIP export.', 'humata-chatbot' ) );
			}
		}

		$zip->close();
		return $work_dir;
	}

	/**
	 * Cleanup a work directory (best-effort).
	 *
	 * @since 1.3.0
	 * @param string $dir
	 * @return void
	 */
	public function cleanup_work_dir( $dir ) {
		$this->delete_dir_recursive( (string) $dir );
	}

	/**
	 * Validate and compute allowed ZIP entries.
	 *
	 * @since 1.3.0
	 * @param ZipArchive $zip
	 * @return array|WP_Error
	 */
	private function get_allowed_zip_entries( ZipArchive $zip ) {
		$allowed = array();

		for ( $i = 0; $i < $zip->numFiles; $i++ ) {
			$name = (string) $zip->getNameIndex( $i );
			if ( '' === $name ) {
				continue;
			}

			// Ignore directory entries.
			if ( '/' === substr( $name, -1 ) ) {
				continue;
			}

			if ( ! $this->is_safe_zip_entry_name( $name ) ) {
				return new WP_Error( 'import_zip_unsafe', __( 'ZIP export contains unsafe file paths.', 'humata-chatbot' ) );
			}

			if ( $this->is_allowed_zip_entry_name( $name ) ) {
				$allowed[] = $name;
				continue;
			}

			return new WP_Error(
				'import_zip_unexpected',
				sprintf(
					/* translators: %s: zip entry name */
					__( 'ZIP export contains an unexpected file: %s', 'humata-chatbot' ),
					$name
				)
			);
		}

		return array_values( array_unique( $allowed ) );
	}

	/**
	 * Whether a ZIP entry name is safe (no traversal / absolute paths).
	 *
	 * @since 1.3.0
	 * @param string $name
	 * @return bool
	 */
	private function is_safe_zip_entry_name( $name ) {
		$name = (string) $name;

		// Disallow absolute paths and Windows drive letters.
		if ( 0 === strpos( $name, '/' ) || 0 === strpos( $name, '\\' ) ) {
			return false;
		}
		if ( preg_match( '/^[a-zA-Z]:[\\/]/', $name ) ) {
			return false;
		}

		// Disallow null bytes.
		if ( false !== strpos( $name, "\0" ) ) {
			return false;
		}

		// Disallow traversal segments.
		return 1 !== preg_match( '#(^|[\\/])\\.\\.([\\/]|$)#', $name );
	}

	/**
	 * Whether an entry name is allowed in the import ZIP.
	 *
	 * @since 1.3.0
	 * @param string $name
	 * @return bool
	 */
	private function is_allowed_zip_entry_name( $name ) {
		$name = (string) $name;

		if ( 'humata-settings.json' === $name ) {
			return true;
		}

		if ( 'humata-search/index.db' === $name ) {
			return true;
		}

		// SQLite sidecars are expected when DB is in WAL mode.
		if ( 'humata-search/index.db-wal' === $name || 'humata-search/index.db-shm' === $name ) {
			return true;
		}

		$prefix = 'humata-search/documents/';
		if ( 0 === strpos( $name, $prefix ) ) {
			$rest = substr( $name, strlen( $prefix ) );
			if ( '' === $rest ) {
				return false;
			}
			return false === strpos( $rest, '/' ) && false === strpos( $rest, '\\' );
		}

		return false;
	}

	/**
	 * Create a temporary working directory.
	 *
	 * @since 1.3.0
	 * @return string|WP_Error
	 */
	private function create_work_dir() {
		$base = function_exists( 'get_temp_dir' ) ? get_temp_dir() : sys_get_temp_dir();
		$base = rtrim( (string) $base, '/\\' );

		$suffix   = wp_generate_password( 12, false, false );
		$work_dir = $base . '/humata-import-' . $suffix;

		if ( ! wp_mkdir_p( $work_dir ) ) {
			return new WP_Error( 'import_tmpdir', __( 'Failed to create a temporary working directory.', 'humata-chatbot' ) );
		}

		return $work_dir;
	}

	/**
	 * Recursively delete a directory (best effort).
	 *
	 * @since 1.3.0
	 * @param string $dir
	 * @return void
	 */
	private function delete_dir_recursive( $dir ) {
		$dir = (string) $dir;
		if ( '' === $dir || ! is_dir( $dir ) ) {
			return;
		}

		$items = scandir( $dir );
		if ( ! is_array( $items ) ) {
			return;
		}

		foreach ( $items as $item ) {
			if ( '.' === $item || '..' === $item ) {
				continue;
			}

			$path = $dir . '/' . $item;
			if ( is_dir( $path ) ) {
				$this->delete_dir_recursive( $path );
				@rmdir( $path );
				continue;
			}

			@unlink( $path );
		}

		@rmdir( $dir );
	}
}


