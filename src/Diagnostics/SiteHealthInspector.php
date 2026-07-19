<?php
/**
 * Collects a bounded, read-only WordPress health snapshot.
 *
 * @package PressCareAIErrorDoctor
 */

declare(strict_types=1);

namespace PressCare\AIErrorDoctor\Diagnostics;

use Throwable;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class SiteHealthInspector {
	private const AUTOLOAD_REVIEW_BYTES = 800000;
	private const TOP_AUTOLOAD_LIMIT    = 10;

	/**
	 * @return array<string,mixed>
	 */
	public function inspect(): array {
		global $wpdb;

		return array(
			'wordpress_version' => function_exists( 'wp_get_wp_version' ) ? wp_get_wp_version() : get_bloginfo( 'version' ),
			'php_version'       => PHP_VERSION,
			'database_version'  => is_object( $wpdb ) && method_exists( $wpdb, 'db_version' ) ? sanitize_text_field( (string) $wpdb->db_version() ) : '',
			'web_server'        => isset( $_SERVER['SERVER_SOFTWARE'] ) ? substr( sanitize_text_field( wp_unslash( (string) $_SERVER['SERVER_SOFTWARE'] ) ), 0, 191 ) : '',
			'multisite'         => is_multisite(),
			'object_cache'      => function_exists( 'wp_using_ext_object_cache' ) && wp_using_ext_object_cache(),
			'transients'        => $this->transient_stats(),
			'autoload'          => $this->autoload_stats(),
		);
	}

	/**
	 * @return array{count:int,size_bytes:int,expired_count:int,available:bool}
	 */
	private function transient_stats(): array {
		global $wpdb;

		$empty = array(
			'count'         => 0,
			'size_bytes'    => 0,
			'expired_count' => 0,
			'available'     => false,
		);

		if ( ! is_object( $wpdb ) || ! isset( $wpdb->options ) || ! method_exists( $wpdb, 'esc_like' ) || ! method_exists( $wpdb, 'prepare' ) || ! method_exists( $wpdb, 'get_row' ) || ! method_exists( $wpdb, 'get_var' ) ) {
			return $empty;
		}

		try {
			$transient_like      = $wpdb->esc_like( '_transient_' ) . '%';
			$transient_timeout   = $wpdb->esc_like( '_transient_timeout_' ) . '%';
			$site_transient_like = $wpdb->esc_like( '_site_transient_' ) . '%';
			$site_timeout        = $wpdb->esc_like( '_site_transient_timeout_' ) . '%';
			$stats_query         = $wpdb->prepare(
				"SELECT COUNT(*) AS transient_count, COALESCE(SUM(LENGTH(option_value)), 0) AS transient_bytes FROM {$wpdb->options} WHERE (option_name LIKE %s AND option_name NOT LIKE %s) OR (option_name LIKE %s AND option_name NOT LIKE %s)",
				$transient_like,
				$transient_timeout,
				$site_transient_like,
				$site_timeout
			);

			// This diagnostic intentionally performs a bounded, read-only aggregate query.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
			$stats = $wpdb->get_row( $stats_query, ARRAY_A );

			$expired_query = $wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->options} WHERE (option_name LIKE %s OR option_name LIKE %s) AND CAST(option_value AS UNSIGNED) > 0 AND CAST(option_value AS UNSIGNED) < %d",
				$transient_timeout,
				$site_timeout,
				time()
			);

			// This diagnostic intentionally performs a bounded, read-only aggregate query.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
			$expired = $wpdb->get_var( $expired_query );

			return array(
				'count'         => max( 0, (int) ( is_array( $stats ) ? $stats['transient_count'] ?? 0 : 0 ) ),
				'size_bytes'    => max( 0, (int) ( is_array( $stats ) ? $stats['transient_bytes'] ?? 0 : 0 ) ),
				'expired_count' => max( 0, (int) $expired ),
				'available'     => true,
			);
		} catch ( Throwable ) {
			return $empty;
		}
	}

	/**
	 * @return array{count:int,size_bytes:int,review_bytes:int,status:string,largest:array<int,array{name:string,size_bytes:int}>,available:bool}
	 */
	private function autoload_stats(): array {
		global $wpdb;

		$empty = array(
			'count'        => 0,
			'size_bytes'   => 0,
			'review_bytes' => self::AUTOLOAD_REVIEW_BYTES,
			'status'       => 'unavailable',
			'largest'      => array(),
			'available'    => false,
		);

		if ( ! is_object( $wpdb ) || ! isset( $wpdb->options ) || ! method_exists( $wpdb, 'prepare' ) || ! method_exists( $wpdb, 'get_row' ) || ! method_exists( $wpdb, 'get_results' ) ) {
			return $empty;
		}

		try {
			$values       = function_exists( 'wp_autoload_values_to_autoload' ) ? wp_autoload_values_to_autoload() : array( 'yes', 'on', 'auto-on', 'auto' );
			$values       = array_values( array_filter( array_map( 'sanitize_key', (array) $values ) ) );
			$placeholders = implode( ', ', array_fill( 0, count( $values ), '%s' ) );

			if ( '' === $placeholders ) {
				return $empty;
			}

			$stats_sql = "SELECT COUNT(*) AS option_count, COALESCE(SUM(LENGTH(option_value)), 0) AS option_bytes FROM {$wpdb->options} WHERE autoload IN ({$placeholders})";
			// The placeholder list is generated internally and each value is prepared below.
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$stats_query = $wpdb->prepare( $stats_sql, $values );

			// This diagnostic intentionally performs a bounded, read-only aggregate query.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
			$stats = $wpdb->get_row( $stats_query, ARRAY_A );

			$largest_sql = "SELECT option_name, LENGTH(option_value) AS option_bytes FROM {$wpdb->options} WHERE autoload IN ({$placeholders}) ORDER BY option_bytes DESC LIMIT " . self::TOP_AUTOLOAD_LIMIT;
			// The placeholder list and numeric limit are generated internally.
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$largest_query = $wpdb->prepare( $largest_sql, $values );

			// The result is limited to ten option names and never reads option values.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared
			$rows = $wpdb->get_results( $largest_query, ARRAY_A );

			$largest = array();
			foreach ( is_array( $rows ) ? $rows : array() as $row ) {
				if ( ! is_array( $row ) || empty( $row['option_name'] ) ) {
					continue;
				}

				$largest[] = array(
					'name'       => substr( sanitize_text_field( (string) $row['option_name'] ), 0, 191 ),
					'size_bytes' => max( 0, (int) ( $row['option_bytes'] ?? 0 ) ),
				);
			}

			$size = max( 0, (int) ( is_array( $stats ) ? $stats['option_bytes'] ?? 0 : 0 ) );

			return array(
				'count'        => max( 0, (int) ( is_array( $stats ) ? $stats['option_count'] ?? 0 : 0 ) ),
				'size_bytes'   => $size,
				'review_bytes' => self::AUTOLOAD_REVIEW_BYTES,
				'status'       => $size >= self::AUTOLOAD_REVIEW_BYTES ? 'review' : 'normal',
				'largest'      => $largest,
				'available'    => true,
			);
		} catch ( Throwable ) {
			return $empty;
		}
	}
}
