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

if ( ! function_exists( '__' ) ) {
	function __( string $text ): string {
		return $text;
	}
}

if ( ! function_exists( '_n' ) ) {
	function _n( string $single, string $plural, int $number ): string {
		return 1 === $number ? $single : $plural;
	}
}

if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	function wp_strip_all_tags( string $text ): string {
		return strip_tags( $text );
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( string $text ): string {
		return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( string $text ): string {
		return esc_html( $text );
	}
}

if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( string $url ): string {
		return esc_attr( $url );
	}
}

if ( ! function_exists( 'esc_html_e' ) ) {
	function esc_html_e( string $text ): void {
		echo esc_html( $text );
	}
}

if ( ! function_exists( 'esc_attr_e' ) ) {
	function esc_attr_e( string $text ): void {
		echo esc_attr( $text );
	}
}

if ( ! function_exists( 'admin_url' ) ) {
	function admin_url( string $path = '' ): string {
		return 'https://example.test/wp-admin/' . ltrim( $path, '/' );
	}
}

if ( ! function_exists( 'add_query_arg' ) ) {
	/**
	 * @param array<string,string> $args Query arguments.
	 */
	function add_query_arg( array $args, string $url ): string {
		return $url . '?' . http_build_query( $args, '', '&', PHP_QUERY_RFC3986 );
	}
}

if ( ! function_exists( 'wp_nonce_field' ) ) {
	function wp_nonce_field( string $action ): void {
		echo '<input type="hidden" name="_wpnonce" value="' . esc_attr( $action ) . '">';
	}
}

if ( ! function_exists( 'wp_date' ) ) {
	function wp_date( string $format, int $timestamp ): string {
		return gmdate( $format, $timestamp );
	}
}

if ( ! function_exists( 'size_format' ) ) {
	function size_format( int $bytes ): string {
		return $bytes . ' B';
	}
}

if ( ! function_exists( 'get_option' ) ) {
	/**
	 * @return mixed
	 */
	function get_option( string $name, mixed $default = false ) {
		return $default;
	}
}

require_once dirname( __DIR__ ) . '/src/Privacy/Redactor.php';
require_once dirname( __DIR__ ) . '/src/Diagnostics/LogParser.php';
require_once dirname( __DIR__ ) . '/src/AI/Analyzer.php';
require_once dirname( __DIR__ ) . '/src/Admin/AdminPage.php';
