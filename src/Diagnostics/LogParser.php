<?php
/**
 * Parses, classifies, and groups common WordPress/PHP log events.
 *
 * @package PressCareAIErrorDoctor
 */

declare(strict_types=1);

namespace PressCare\AIErrorDoctor\Diagnostics;

use PressCare\AIErrorDoctor\Privacy\Redactor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class LogParser {
	private Redactor $redactor;

	public function __construct( Redactor $redactor ) {
		$this->redactor = $redactor;
	}

	/**
	 * @return array{events_total:int,groups:array<int,array<string,mixed>>,counts:array<string,int>}
	 */
	public function parse( string $content ): array {
		$events = $this->extract_events( $content );
		$groups = array();
		$counts = array_fill_keys( array( 'critical', 'error', 'warning', 'info' ), 0 );

		foreach ( $events as $event ) {
			$severity    = $this->classify_severity( $event );
			$component   = $this->detect_component( $event );
			$normalized  = $this->normalize_message( $event );
			$fingerprint = substr( hash( 'sha256', $severity . '|' . $component['slug'] . '|' . $normalized ), 0, 16 );
			$timestamp   = $this->extract_timestamp( $event );

			++$counts[ $severity ];

			if ( ! isset( $groups[ $fingerprint ] ) ) {
				$groups[ $fingerprint ] = array(
					'fingerprint'    => $fingerprint,
					'severity'       => $severity,
					'component_type' => $component['type'],
					'component_slug' => $component['slug'],
					'count'          => 0,
					'first_seen'     => $timestamp,
					'last_seen'      => $timestamp,
					'sample'         => $this->redactor->redact( $this->limit_sample( $event ) ),
				);
			}

			++$groups[ $fingerprint ]['count'];
			if ( null !== $timestamp ) {
				$groups[ $fingerprint ]['last_seen']  ??= $timestamp;
				$groups[ $fingerprint ]['first_seen'] ??= $timestamp;
				if ( $timestamp > $groups[ $fingerprint ]['last_seen'] ) {
					$groups[ $fingerprint ]['last_seen'] = $timestamp;
				}
				if ( $timestamp < $groups[ $fingerprint ]['first_seen'] ) {
					$groups[ $fingerprint ]['first_seen'] = $timestamp;
				}
			}
		}

		$groups = array_values( $groups );
		usort(
			$groups,
			static function ( array $a, array $b ): int {
				$rank                = array(
					'critical' => 4,
					'error'    => 3,
					'warning'  => 2,
					'info'     => 1,
				);
				$severity_comparison = $rank[ $b['severity'] ] <=> $rank[ $a['severity'] ];

				return 0 !== $severity_comparison ? $severity_comparison : ( $b['count'] <=> $a['count'] );
			}
		);

		return array(
			'events_total' => count( $events ),
			'groups'       => array_slice( $groups, 0, 25 ),
			'counts'       => $counts,
		);
	}

	/**
	 * @return string[]
	 */
	private function extract_events( string $content ): array {
		$lines   = preg_split( '/\R/', $content );
		$lines   = is_array( $lines ) ? $lines : array();
		$events  = array();
		$current = '';

		foreach ( $lines as $line ) {
			if ( $this->is_event_start( $line ) ) {
				if ( '' !== $current ) {
					$events[] = trim( $current );
				}
				$current = $line;
				continue;
			}

			if ( '' !== $current && $this->is_continuation( $line ) ) {
				$current .= "\n" . $line;
			}
		}

		if ( '' !== $current ) {
			$events[] = trim( $current );
		}

		return $events;
	}

	private function is_event_start( string $line ): bool {
		return 1 === preg_match(
			'/^(?:\[[^\]]+\]\s*)?(?:PHP\s+)?(?:Fatal error|Parse error|Recoverable fatal error|Warning|Notice|Deprecated|Strict Standards|Error|WordPress database error|Uncaught\s+(?:Error|Exception))/i',
			trim( $line )
		);
	}

	private function is_continuation( string $line ): bool {
		$line = ltrim( $line );
		return '' !== $line && (
			str_starts_with( $line, '#' ) ||
			str_starts_with( $line, 'Stack trace:' ) ||
			str_starts_with( $line, 'thrown in ' ) ||
			str_starts_with( $line, 'called in ' ) ||
			str_starts_with( $line, 'Next ' )
		);
	}

	private function classify_severity( string $event ): string {
		if ( preg_match( '/Fatal error|Parse error|Recoverable fatal error|Uncaught\s+(?:Error|Exception)/i', $event ) ) {
			return 'critical';
		}
		if ( preg_match( '/WordPress database error|(?:PHP\s+)?Error:/i', $event ) ) {
			return 'error';
		}
		if ( preg_match( '/Warning/i', $event ) ) {
			return 'warning';
		}
		return 'info';
	}

	/**
	 * @return array{type:string,slug:string}
	 */
	private function detect_component( string $event ): array {
		$normalized = wp_normalize_path( $event );
		$patterns   = array(
			'plugin' => '#/wp-content/(?:mu-)?plugins/([^/\s:]+)#i',
			'theme'  => '#/wp-content/themes/([^/\s:]+)#i',
		);

		foreach ( $patterns as $type => $pattern ) {
			if ( preg_match( $pattern, $normalized, $matches ) ) {
				return array(
					'type' => $type,
					'slug' => sanitize_key( $matches[1] ),
				);
			}
		}

		if ( preg_match( '#/wp-(?:admin|includes)/#i', $normalized ) ) {
			return array(
				'type' => 'core',
				'slug' => 'wordpress-core',
			);
		}

		return array(
			'type' => 'unknown',
			'slug' => 'unknown',
		);
	}

	private function normalize_message( string $event ): string {
		$normalized = preg_replace( '/^\[[^\]]+\]\s*/', '', $event );
		$normalized = preg_replace( '#(?:[A-Za-z]:)?/[^\s]+#', '[path]', (string) $normalized );
		$normalized = preg_replace( '/\b(?:on line|line|at)\s+\d+\b/i', 'line #', (string) $normalized );
		$normalized = preg_replace( '/:\d+\b/', ':#', (string) $normalized );
		$normalized = preg_replace( '/\s+/', ' ', (string) $normalized );
		return strtolower( trim( (string) $normalized ) );
	}

	private function extract_timestamp( string $event ): ?int {
		if ( ! preg_match( '/^\[([^\]]+)\]/', $event, $matches ) ) {
			return null;
		}

		$timestamp = strtotime( $matches[1] );
		return false === $timestamp ? null : $timestamp;
	}

	private function limit_sample( string $event ): string {
		if ( str_contains( strtolower( $event ), 'wordpress database error' ) ) { // phpcs:ignore WordPress.WP.CapitalPDangit.MisspelledInText -- Lowercase comparison value.
			$event = preg_replace(
				'/\s+for query\s+.*?(?=\s+made by\s+|$)/is',
				' for query [database-query-redacted]',
				$event
			) ?? $event;
		}

		$lines  = preg_split( '/\R/', $event );
		$lines  = is_array( $lines ) ? array_slice( $lines, 0, 8 ) : array();
		$sample = implode( "\n", $lines );
		return function_exists( 'mb_substr' ) ? mb_substr( $sample, 0, 2000 ) : substr( $sample, 0, 2000 );
	}
}
