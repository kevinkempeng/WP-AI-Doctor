<?php
/**
 * Generates a structured explanation through the WordPress AI Client.
 *
 * @package PressCareAIErrorDoctor
 */

declare(strict_types=1);

namespace PressCare\AIErrorDoctor\AI;

use PressCare\AIErrorDoctor\Privacy\Redactor;
use Throwable;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Analyzer {
	private const MAX_OUTPUT_TOKENS = 6000;

	private Redactor $redactor;

	public function __construct( Redactor $redactor ) {
		$this->redactor = $redactor;
	}

	public function is_available(): bool {
		if ( ! function_exists( 'wp_ai_client_prompt' ) ) {
			return false;
		}

		try {
			$builder = wp_ai_client_prompt();
			return method_exists( $builder, 'is_supported_for_text_generation' ) && $builder->is_supported_for_text_generation();
		} catch ( Throwable ) {
			return false;
		}
	}

	/**
	 * @param array<string,mixed> $report Sanitized local report.
	 * @return array<string,mixed>|WP_Error
	 */
	public function analyze( array $report ): array|WP_Error {
		if ( ! $this->is_available() ) {
			return new WP_Error(
				'pcaied_ai_unavailable',
				__( 'No compatible AI provider is connected. Open Settings > Connectors to configure one.', 'presscare-ai-error-doctor' )
			);
		}

		$payload      = $this->redactor->redact_value(
			array(
				'environment' => $report['environment'] ?? array(),
				'summary'     => $report['summary'] ?? array(),
				'groups'      => $report['groups'] ?? array(),
			)
		);
		$fingerprints = array_values(
			array_filter(
				array_map(
					static fn ( mixed $group ): string => is_array( $group ) ? sanitize_key( (string) ( $group['fingerprint'] ?? '' ) ) : '',
					is_array( $payload['groups'] ?? null ) ? $payload['groups'] : array()
				)
			)
		);

		if ( ! $fingerprints ) {
			return new WP_Error(
				'pcaied_ai_no_findings',
				__( 'The local report contains no grouped findings for AI analysis.', 'presscare-ai-error-doctor' )
			);
		}

		$prompt = implode(
			"\n\n",
			array(
				'You are PressCare AI Error Doctor, a cautious senior WordPress diagnostic analyst.',
				'Analyze only the supplied sanitized evidence. Do not invent files, versions, causes, vulnerabilities, or fixes. Every finding must use the exact fingerprint of the evidence group it explains; do not add unrelated findings. Distinguish observations from likely causes. Recommend verification steps before changes. Never recommend directly editing WordPress core. Keep steps concise and safe for a production site. If evidence is insufficient, say so and use low confidence.',
				'Diagnostic evidence:',
				(string) wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ),
			)
		);

		try {
			$result = wp_ai_client_prompt( $prompt )
				->using_max_tokens( self::MAX_OUTPUT_TOKENS )
				->as_json_response( $this->schema( $fingerprints ) )
				->generate_text();
		} catch ( Throwable ) {
			return new WP_Error(
				'pcaied_ai_exception',
				__( 'The configured AI provider could not complete the analysis.', 'presscare-ai-error-doctor' )
			);
		}

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$decoded = json_decode( (string) $result, true );
		if ( ! is_array( $decoded ) || ! isset( $decoded['summary'], $decoded['overall_severity'], $decoded['findings'] ) || ! is_array( $decoded['findings'] ) ) {
			return new WP_Error(
				'pcaied_ai_invalid_response',
				__( 'The AI provider returned an invalid diagnostic response.', 'presscare-ai-error-doctor' )
			);
		}

		return $this->normalize_response( $decoded, $fingerprints );
	}

	/**
	 * @param array<string,mixed> $response Provider response.
	 * @param string[]            $fingerprints Allowed local finding fingerprints.
	 * @return array<string,mixed>
	 */
	private function normalize_response( array $response, array $fingerprints ): array {
		$allowed_severities = array( 'critical', 'error', 'warning', 'info', 'healthy' );
		$severity           = sanitize_key( (string) $response['overall_severity'] );
		$severity           = in_array( $severity, $allowed_severities, true ) ? $severity : 'info';
		$findings           = array();

		foreach ( array_slice( $response['findings'], 0, 25 ) as $finding ) {
			if ( ! is_array( $finding ) ) {
				continue;
			}

			$fingerprint = sanitize_key( (string) ( $finding['fingerprint'] ?? '' ) );
			if ( ! in_array( $fingerprint, $fingerprints, true ) ) {
				continue;
			}

			$confidence = sanitize_key( (string) ( $finding['confidence'] ?? 'low' ) );
			$confidence = in_array( $confidence, array( 'high', 'medium', 'low' ), true ) ? $confidence : 'low';
			$steps      = array();

			foreach ( array_slice( (array) ( $finding['recommended_steps'] ?? array() ), 0, 8 ) as $step ) {
				$steps[] = $this->limit_text( (string) $step, 600 );
			}

			$findings[] = array(
				'fingerprint'             => substr( $fingerprint, 0, 64 ),
				'title'                   => $this->limit_text( (string) ( $finding['title'] ?? '' ), 240 ),
				'explanation'             => $this->limit_text( (string) ( $finding['explanation'] ?? '' ), 2400 ),
				'likely_cause'            => $this->limit_text( (string) ( $finding['likely_cause'] ?? '' ), 1600 ),
				'recommended_steps'       => $steps,
				'confidence'              => $confidence,
				'needs_professional_help' => ! empty( $finding['needs_professional_help'] ),
			);
		}

		return array(
			'summary'          => $this->limit_text( (string) $response['summary'], 3000 ),
			'overall_severity' => $severity,
			'findings'         => $findings,
		);
	}

	private function limit_text( string $value, int $length ): string {
		$value = sanitize_textarea_field( $this->redactor->redact( $value ) );
		return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $length ) : substr( $value, 0, $length );
	}

	/**
	 * @param string[] $fingerprints Allowed local finding fingerprints.
	 * @return array<string,mixed>
	 */
	private function schema( array $fingerprints ): array {
		return array(
			'type'                 => 'object',
			'additionalProperties' => false,
			'properties'           => array(
				'summary'          => array(
					'type'      => 'string',
					'maxLength' => 1200,
				),
				'overall_severity' => array(
					'type' => 'string',
					'enum' => array( 'critical', 'error', 'warning', 'info', 'healthy' ),
				),
				'findings'         => array(
					'type'     => 'array',
					'maxItems' => 25,
					'items'    => array(
						'type'                 => 'object',
						'additionalProperties' => false,
						'properties'           => array(
							'fingerprint'             => array(
								'type' => 'string',
								'enum' => $fingerprints,
							),
							'title'                   => array(
								'type'      => 'string',
								'maxLength' => 160,
							),
							'explanation'             => array(
								'type'      => 'string',
								'maxLength' => 900,
							),
							'likely_cause'            => array(
								'type'      => 'string',
								'maxLength' => 600,
							),
							'recommended_steps'       => array(
								'type'     => 'array',
								'maxItems' => 5,
								'items'    => array(
									'type'      => 'string',
									'maxLength' => 300,
								),
							),
							'confidence'              => array(
								'type' => 'string',
								'enum' => array( 'high', 'medium', 'low' ),
							),
							'needs_professional_help' => array( 'type' => 'boolean' ),
						),
						'required'             => array(
							'fingerprint',
							'title',
							'explanation',
							'likely_cause',
							'recommended_steps',
							'confidence',
							'needs_professional_help',
						),
					),
				),
			),
			'required'             => array( 'summary', 'overall_severity', 'findings' ),
		);
	}
}
