<?php
/**
 * Removes common personal data and secrets before diagnostic data is stored or sent.
 *
 * @package PressCareAIErrorDoctor
 */

declare(strict_types=1);

namespace PressCare\AIErrorDoctor\Privacy;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Redactor {
	public function redact( string $value ): string {
		$value = $this->redact_paths( $value );

		$patterns = array(
			'/\bBearer\s+[A-Za-z0-9._~+\/-]+=*/i'                                               => 'Bearer [redacted]',
			'/\bsk-(?:proj-|ant-)?[A-Za-z0-9_-]{16,}\b/'                                       => '[api-key-redacted]',
			'/\bAIza[0-9A-Za-z_-]{20,}\b/'                                                     => '[api-key-redacted]',
			'/\beyJ[A-Za-z0-9_-]{8,}\.[A-Za-z0-9_-]{8,}\.[A-Za-z0-9_-]{8,}\b/'                => '[jwt-redacted]',
			'/\b[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}\b/i'                                     => '[email-redacted]',
			'/(?<![\d.])(?:\d{1,3}\.){3}\d{1,3}(?![\d.])/'                                   => '[ip-redacted]',
			'/((?:api[_ -]?key|access[_ -]?token|secret|password|authorization)\s*[:=]\s*)[^\s&,;]+/i' => '$1[redacted]',
			'/([?&](?:key|api_key|token|secret|password|auth)=)[^&#\s]+/i'                       => '$1[redacted]',
		);

		$redacted = preg_replace( array_keys( $patterns ), array_values( $patterns ), $value );
		return is_string( $redacted ) ? $redacted : $value;
	}

	/**
	 * @param mixed $value Value to sanitize recursively.
	 * @return mixed
	 */
	public function redact_value( mixed $value ): mixed {
		if ( is_string( $value ) ) {
			return $this->redact( $value );
		}

		if ( is_array( $value ) ) {
			foreach ( $value as $key => $item ) {
				$value[ $key ] = $this->redact_value( $item );
			}
		}

		return $value;
	}

	private function redact_paths( string $value ): string {
		$paths = array();

		if ( defined( 'WP_PLUGIN_DIR' ) ) {
			$paths[ wp_normalize_path( WP_PLUGIN_DIR ) ] = '[plugins]';
		}
		if ( defined( 'WP_CONTENT_DIR' ) ) {
			$paths[ wp_normalize_path( WP_CONTENT_DIR ) ] = '[wp-content]';
		}
		if ( defined( 'ABSPATH' ) ) {
			$paths[ wp_normalize_path( ABSPATH ) ] = '[wordpress]/';
		}

		$normalized = wp_normalize_path( $value );
		uksort( $paths, static fn ( string $a, string $b ): int => strlen( $b ) <=> strlen( $a ) );

		foreach ( $paths as $path => $replacement ) {
			$normalized = str_replace( untrailingslashit( $path ), $replacement, $normalized );
		}

		$site_url = home_url();
		if ( is_string( $site_url ) && '' !== $site_url ) {
			$normalized = str_ireplace( untrailingslashit( $site_url ), '[site-url]', $normalized );
		}

		$normalized = preg_replace(
			'#(?<![A-Za-z0-9])/(?:home|var|srv|opt|usr|Users|Volumes|mnt|tmp)/(?:[^\s\'\"]+/)*[^\s\'\"]+#',
			'[filesystem-path]',
			$normalized
		) ?? $normalized;
		$normalized = preg_replace(
			'#\b[A-Za-z]:/(?:[^\s\'\"]+/)*[^\s\'\"]+#',
			'[filesystem-path]',
			$normalized
		) ?? $normalized;

		return $normalized;
	}
}
