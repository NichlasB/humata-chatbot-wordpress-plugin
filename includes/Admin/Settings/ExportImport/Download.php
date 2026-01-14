<?php
/**
 * Download helper (Import/Export feature)
 *
 * Centralizes streaming downloads to the browser.
 *
 * @package Humata_Chatbot
 * @since 1.3.0
 */

defined( 'ABSPATH' ) || exit;

final class Humata_Chatbot_Admin_Settings_Export_Import_Download {

	/**
	 * Send a string download to the browser.
	 *
	 * @since 1.3.0
	 * @param string $content_type
	 * @param string $filename
	 * @param string $body
	 * @return void
	 */
	public static function send_string( $content_type, $filename, $body ) {
		$content_type = (string) $content_type;
		$filename     = (string) $filename;
		$body         = (string) $body;

		nocache_headers();
		header( 'Content-Type: ' . $content_type );
		header( 'Content-Disposition: attachment; filename=' . $filename );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		echo $body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Stream a file download to the browser.
	 *
	 * @since 1.3.0
	 * @param string $content_type
	 * @param string $filename
	 * @param string $file_path
	 * @return void
	 */
	public static function send_file( $content_type, $filename, $file_path ) {
		$content_type = (string) $content_type;
		$filename     = (string) $filename;
		$file_path    = (string) $file_path;

		if ( '' === $file_path || ! file_exists( $file_path ) ) {
			return;
		}

		nocache_headers();
		header( 'Content-Type: ' . $content_type );
		header( 'Content-Disposition: attachment; filename=' . $filename );
		header( 'Content-Length: ' . (string) filesize( $file_path ) );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_readfile
		readfile( $file_path );
	}
}


