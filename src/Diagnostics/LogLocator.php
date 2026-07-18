<?php
/**
 * Locates and safely reads the configured PHP or WordPress debug log.
 *
 * @package PressCareAIErrorDoctor
 */

declare(strict_types=1);

namespace PressCare\AIErrorDoctor\Diagnostics;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class LogLocator {
	private const MAX_BYTES = 2097152;

	/**
	 * @return array{path:string,source:string}|WP_Error
	 */
	public function locate(): array|WP_Error {
		$candidates = array();

		if ( defined( 'PCAIED_LOG_PATH' ) && is_string( PCAIED_LOG_PATH ) && '' !== PCAIED_LOG_PATH ) {
			$candidates['PCAIED_LOG_PATH'] = PCAIED_LOG_PATH;
		}

		if ( defined( 'WP_DEBUG_LOG' ) ) {
			if ( true === WP_DEBUG_LOG ) {
				$candidates['WP_DEBUG_LOG'] = WP_CONTENT_DIR . '/debug.log';
			} elseif ( is_string( WP_DEBUG_LOG ) && '' !== WP_DEBUG_LOG ) {
				$candidates['WP_DEBUG_LOG'] = WP_DEBUG_LOG;
			}
		}

		$error_log = ini_get( 'error_log' );
		if ( is_string( $error_log ) && '' !== $error_log && ! str_contains( $error_log, '://' ) ) {
			$candidates['PHP error_log'] = $error_log;
		}

		foreach ( array_unique( $candidates ) as $source => $candidate ) {
			$path = realpath( $candidate );
			if ( false === $path || ! is_file( $path ) || ! is_readable( $path ) ) {
				continue;
			}

			return array(
				'path'   => $path,
				'source' => (string) $source,
			);
		}

		return new WP_Error(
			'pcaied_log_not_found',
			__( 'No readable WordPress or PHP error log was found. Enable WP_DEBUG_LOG or define PCAIED_LOG_PATH in wp-config.php.', 'presscare-ai-error-doctor' )
		);
	}

	/**
	 * Reads only the end of a log so a large file cannot exhaust PHP memory.
	 *
	 * @return array{content:string,bytes_read:int,file_size:int,truncated:bool,modified_at:int}|WP_Error
	 */
	public function read_tail( string $path ): array|WP_Error {
		$handle = @fopen( $path, 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( false === $handle ) {
			return new WP_Error( 'pcaied_log_open_failed', __( 'The error log could not be opened.', 'presscare-ai-error-doctor' ) );
		}

		if ( ! flock( $handle, LOCK_SH ) ) {
			fclose( $handle );
			return new WP_Error( 'pcaied_log_lock_failed', __( 'The error log is currently busy. Try again shortly.', 'presscare-ai-error-doctor' ) );
		}

		$stats     = fstat( $handle );
		$file_size = is_array( $stats ) && isset( $stats['size'] ) ? (int) $stats['size'] : 0;
		$modified  = is_array( $stats ) && isset( $stats['mtime'] ) ? (int) $stats['mtime'] : 0;
		$offset    = max( 0, $file_size - self::MAX_BYTES );

		if ( 0 !== fseek( $handle, $offset ) ) {
			flock( $handle, LOCK_UN );
			fclose( $handle );
			return new WP_Error( 'pcaied_log_seek_failed', __( 'The error log could not be read safely.', 'presscare-ai-error-doctor' ) );
		}

		$content = stream_get_contents( $handle, self::MAX_BYTES );
		flock( $handle, LOCK_UN );
		fclose( $handle );

		if ( false === $content ) {
			return new WP_Error( 'pcaied_log_read_failed', __( 'The error log could not be read.', 'presscare-ai-error-doctor' ) );
		}

		if ( $offset > 0 ) {
			$first_newline = strpos( $content, "\n" );
			$content       = false === $first_newline ? '' : substr( $content, $first_newline + 1 );
		}

		return array(
			'content'     => wp_check_invalid_utf8( $content, true ),
			'bytes_read'  => strlen( $content ),
			'file_size'   => $file_size,
			'truncated'   => $offset > 0,
			'modified_at' => $modified,
		);
	}
}
