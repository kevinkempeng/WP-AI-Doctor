<?php
/**
 * Minimal WordPress-function test bootstrap for isolated parser tests.
 *
 * @package PressCareAIErrorDoctor
 */

declare(strict_types=1);

define( 'ABSPATH', '/var/www/example/public/' );
define( 'WP_CONTENT_DIR', ABSPATH . 'wp-content' );
define( 'WP_PLUGIN_DIR', WP_CONTENT_DIR . '/plugins' );

if ( ! function_exists( 'wp_normalize_path' ) ) {
	function wp_normalize_path( string $path ): string {
		return str_replace( '\\', '/', $path );
	}
}

if ( ! function_exists( 'untrailingslashit' ) ) {
	function untrailingslashit( string $value ): string {
		return rtrim( $value, '/\\' );
	}
}

if ( ! function_exists( 'home_url' ) ) {
	function home_url(): string {
		return 'https://example.test';
	}
}

if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( string $key ): string {
		return strtolower( preg_replace( '/[^a-zA-Z0-9_-]/', '', $key ) ?? '' );
	}
}

require_once dirname( __DIR__ ) . '/src/Privacy/Redactor.php';
require_once dirname( __DIR__ ) . '/src/Diagnostics/LogParser.php';
require_once dirname( __DIR__ ) . '/src/AI/Analyzer.php';
