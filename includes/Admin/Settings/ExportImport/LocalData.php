<?php
/**
 * Local data restorer (Import/Export feature)
 *
 * Restores the local SQLite DB and uploaded documents from an extracted export.
 *
 * @package Humata_Chatbot
 * @since 1.3.0
 */

defined( 'ABSPATH' ) || exit;

final class Humata_Chatbot_Admin_Settings_Export_Import_Local_Data {

	/**
	 * Restore local DB + documents from extracted ZIP contents (best effort, with backups).
	 *
	 * @since 1.3.0
	 * @param string $work_dir Path where the ZIP was extracted.
	 * @return true|WP_Error
	 */
	public function restore_from_work_dir( $work_dir ) {
		$work_dir = (string) $work_dir;

		$uploads  = wp_upload_dir();
		$base_dir = isset( $uploads['basedir'] ) ? (string) $uploads['basedir'] : '';

		if ( '' === $base_dir ) {
			return new WP_Error( 'import_upload_dir', __( 'Could not resolve the uploads directory.', 'humata-chatbot' ) );
		}

		$src_db_dir = $work_dir . '/humata-search';
		$src_db     = $src_db_dir . '/index.db';
		$src_wal    = $src_db_dir . '/index.db-wal';
		$src_shm    = $src_db_dir . '/index.db-shm';
		$src_docs   = $src_db_dir . '/documents';

		$has_db   = file_exists( $src_db );
		$has_docs = is_dir( $src_docs );

		if ( ! $has_db && ! $has_docs ) {
			return true;
		}

		$dest_db_dir = $base_dir . '/humata-search';
		$dest_db     = $dest_db_dir . '/index.db';
		$dest_docs   = $dest_db_dir . '/documents';

		if ( ! wp_mkdir_p( $dest_db_dir ) ) {
			return new WP_Error( 'import_mkdir', __( 'Failed to create the destination directory.', 'humata-chatbot' ) );
		}
		wp_mkdir_p( $dest_docs );

		$this->ensure_directory_protected_if_possible( $dest_db_dir );
		$this->ensure_directory_protected_if_possible( $dest_docs );

		$ts = gmdate( 'Ymd-His' );

		// Backup DB files if present.
		if ( file_exists( $dest_db ) ) {
			$bak = $dest_db_dir . '/index.db.bak-' . $ts;
			if ( ! @copy( $dest_db, $bak ) ) {
				return new WP_Error( 'import_backup_failed', __( 'Failed to backup existing database file.', 'humata-chatbot' ) );
			}
		}
		if ( file_exists( $dest_db . '-wal' ) ) {
			@copy( $dest_db . '-wal', $dest_db_dir . '/index.db-wal.bak-' . $ts );
		}
		if ( file_exists( $dest_db . '-shm' ) ) {
			@copy( $dest_db . '-shm', $dest_db_dir . '/index.db-shm.bak-' . $ts );
		}

		// Backup documents directory if present (best effort).
		if ( is_dir( $dest_docs ) ) {
			$docs_bak = $dest_db_dir . '/documents.bak-' . $ts;
			if ( @rename( $dest_docs, $docs_bak ) ) {
				wp_mkdir_p( $dest_docs );
			}
		}

		// Copy DB files from export.
		if ( $has_db ) {
			if ( ! @copy( $src_db, $dest_db ) ) {
				return new WP_Error( 'import_db_copy', __( 'Failed to restore the database file.', 'humata-chatbot' ) );
			}

			// Replace sidecars if included.
			if ( file_exists( $src_wal ) ) {
				@copy( $src_wal, $dest_db . '-wal' );
			} else {
				@unlink( $dest_db . '-wal' );
			}

			if ( file_exists( $src_shm ) ) {
				@copy( $src_shm, $dest_db . '-shm' );
			} else {
				@unlink( $dest_db . '-shm' );
			}

			$this->normalize_document_paths_in_db( $dest_db, $dest_docs );
		}

		// Copy docs if included.
		if ( $has_docs ) {
			$files = scandir( $src_docs );
			if ( is_array( $files ) ) {
				foreach ( $files as $f ) {
					if ( '.' === $f || '..' === $f ) {
						continue;
					}
					$full = $src_docs . '/' . $f;
					if ( ! is_file( $full ) ) {
						continue;
					}
					@copy( $full, $dest_docs . '/' . basename( $full ) );
				}
			}
		}

		return true;
	}

	/**
	 * Normalize documents_meta.file_path values for the current site.
	 *
	 * @since 1.3.0
	 * @param string $db_path
	 * @param string $docs_dir
	 * @return void
	 */
	private function normalize_document_paths_in_db( $db_path, $docs_dir ) {
		$db_path  = (string) $db_path;
		$docs_dir = (string) $docs_dir;

		if ( '' === $db_path || '' === $docs_dir || ! file_exists( $db_path ) || ! class_exists( 'SQLite3' ) ) {
			return;
		}

		try {
			$db = new SQLite3( $db_path );
			$db->enableExceptions( true );

			$docs_dir = wp_normalize_path( $docs_dir );
			$prefix   = SQLite3::escapeString( rtrim( $docs_dir, '/' ) . '/' );

			$db->exec( "UPDATE documents_meta SET file_path = '{$prefix}' || filename" );
			$db->close();
		} catch ( Exception $e ) {
			// Best-effort: ignore normalization failures.
		}
	}

	/**
	 * Ensure a directory is protected using the existing SearchDatabase helper if available.
	 *
	 * @since 1.3.0
	 * @param string $dir
	 * @return void
	 */
	private function ensure_directory_protected_if_possible( $dir ) {
		$dir = (string) $dir;
		if ( '' === $dir ) {
			return;
		}

		if ( ! class_exists( 'Humata_Chatbot_Rest_Search_Database' ) && defined( 'HUMATA_CHATBOT_PATH' ) ) {
			$path = HUMATA_CHATBOT_PATH . 'includes/Rest/SearchDatabase.php';
			if ( file_exists( $path ) ) {
				require_once $path;
			}
		}

		if ( class_exists( 'Humata_Chatbot_Rest_Search_Database' ) ) {
			$db = new Humata_Chatbot_Rest_Search_Database();
			$db->ensure_directory_protected( $dir );
		}
	}
}


