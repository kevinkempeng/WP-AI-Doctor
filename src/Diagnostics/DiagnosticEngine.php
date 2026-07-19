<?php
/**
 * Builds a sanitized, read-only diagnostic report.
 *
 * @package PressCareAIErrorDoctor
 */

declare(strict_types=1);

namespace PressCare\AIErrorDoctor\Diagnostics;

use PressCare\AIErrorDoctor\Privacy\Redactor;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class DiagnosticEngine {
	private const RECENT_WINDOW_DAYS = 7;

	private LogLocator $locator;
	private LogParser $parser;
	private Redactor $redactor;
	private SiteHealthInspector $health;

	public function __construct( LogLocator $locator, LogParser $parser, Redactor $redactor, SiteHealthInspector $health ) {
		$this->locator  = $locator;
		$this->parser   = $parser;
		$this->redactor = $redactor;
		$this->health   = $health;
	}

	/**
	 * @return array<string,mixed>|WP_Error
	 */
	public function scan(): array|WP_Error {
		$located = $this->locator->locate();
		if ( is_wp_error( $located ) ) {
			return $located;
		}

		$tail = $this->locator->read_tail( $located['path'] );
		if ( is_wp_error( $tail ) ) {
			return $tail;
		}

		$recent_after = time() - ( self::RECENT_WINDOW_DAYS * 86400 );
		$parsed       = $this->parser->parse( $tail['content'], $recent_after );
		$recency      = $parsed['recency'];
		$report       = array(
			'report_id'    => wp_generate_uuid4(),
			'generated_at' => gmdate( DATE_ATOM ),
			'environment'  => $this->environment(),
			'site_health'  => $this->health->inspect(),
			'log'          => array(
				'source'      => $located['source'],
				'file'        => sanitize_file_name( basename( $located['path'] ) ),
				'file_size'   => $tail['file_size'],
				'bytes_read'  => $tail['bytes_read'],
				'truncated'   => $tail['truncated'],
				'modified_at' => $tail['modified_at'] > 0 ? gmdate( DATE_ATOM, $tail['modified_at'] ) : null,
			),
			'summary'      => array(
				'events_total'            => $parsed['events_total'],
				'groups_total'            => count( $parsed['groups'] ),
				'counts'                  => $parsed['counts'],
				'recency_window_days'     => self::RECENT_WINDOW_DAYS,
				'recent_events_total'     => $recency['recent'],
				'historical_events_total' => $recency['historical'],
				'undated_events_total'    => $recency['undated'],
				'oldest_seen'             => $recency['oldest_seen'],
				'newest_seen'             => $recency['newest_seen'],
			),
			'groups'       => $parsed['groups'],
		);

		/**
		 * Filters the sanitized diagnostic report before it is stored or analyzed.
		 *
		 * Integrators must not add passwords, tokens, raw log contents, personal data,
		 * or other secrets to this report.
		 *
		 * @param array<string,mixed> $report Diagnostic report.
		 */
		$report = apply_filters( 'pcaied_diagnostic_report', $report );

		return is_array( $report ) ? $this->redactor->redact_value( $report ) : new WP_Error(
			'pcaied_invalid_report',
			__( 'A third-party integration returned an invalid diagnostic report.', 'presscare-ai-error-doctor' )
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	private function environment(): array {
		$theme          = wp_get_theme();
		$updates        = get_site_transient( 'update_plugins' );
		$update_count   = is_object( $updates ) && isset( $updates->response ) && is_array( $updates->response ) ? count( $updates->response ) : null;
		$active_plugins = (array) get_option( 'active_plugins', array() );

		return array(
			'wordpress_version'    => function_exists( 'wp_get_wp_version' ) ? wp_get_wp_version() : get_bloginfo( 'version' ),
			'php_version'          => PHP_VERSION,
			'multisite'            => is_multisite(),
			'active_theme'         => $theme->get( 'Name' ),
			'active_theme_version' => $theme->get( 'Version' ),
			'active_plugins_count' => count( $active_plugins ),
			'plugin_updates_count' => $update_count,
			'wp_debug'             => defined( 'WP_DEBUG' ) && true === WP_DEBUG,
			'wp_debug_log'         => defined( 'WP_DEBUG_LOG' ) && false !== WP_DEBUG_LOG,
			'ai_client_available'  => function_exists( 'wp_ai_client_prompt' ),
		);
	}
}
