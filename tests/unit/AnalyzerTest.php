<?php
/**
 * AI provider availability tests.
 *
 * @package PressCareAIErrorDoctor
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PressCare\AIErrorDoctor\AI\Analyzer;
use PressCare\AIErrorDoctor\Privacy\Redactor;

final class AnalyzerTest extends TestCase {
	public static bool $text_generation_supported = true;

	protected function setUp(): void {
		self::$text_generation_supported = true;
	}

	public function test_detects_text_generation_exposed_through_magic_call(): void {
		$builder = wp_ai_client_prompt();

		self::assertFalse( method_exists( $builder, 'is_supported_for_text_generation' ) );
		self::assertTrue( ( new Analyzer( new Redactor() ) )->is_available() );
	}

	public function test_reports_unavailable_when_provider_does_not_support_text_generation(): void {
		self::$text_generation_supported = false;

		self::assertFalse( ( new Analyzer( new Redactor() ) )->is_available() );
	}
}

if ( ! function_exists( 'wp_ai_client_prompt' ) ) {
	function wp_ai_client_prompt(): object {
		return new class() {
			/**
			 * @param mixed[] $arguments Method arguments.
			 */
			public function __call( string $name, array $arguments ): bool {
				unset( $arguments );

				if ( 'is_supported_for_text_generation' !== $name ) {
					throw new BadMethodCallException( 'Unexpected AI Client method.' );
				}

				return AnalyzerTest::$text_generation_supported;
			}
		};
	}
}
