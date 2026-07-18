<?php
/**
 * Redactor tests.
 *
 * @package PressCareAIErrorDoctor
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PressCare\AIErrorDoctor\Privacy\Redactor;

final class RedactorTest extends TestCase {
	public function test_redacts_common_sensitive_values(): void {
		$input = implode(
			' ',
			array(
				'/var/www/example/public/wp-content/plugins/sample/plugin.php',
				'https://example.test/account',
				'admin@example.test',
				'192.0.2.44',
				'Authorization: Bearer secret-token-value',
				'api_key=sk-proj-abcdefghijklmnopqrstuvwxyz',
			)
		);

		$output = ( new Redactor() )->redact( $input );

		self::assertStringContainsString( '[plugins]/sample/plugin.php', $output );
		self::assertStringContainsString( '[site-url]/account', $output );
		self::assertStringContainsString( '[email-redacted]', $output );
		self::assertStringContainsString( '[ip-redacted]', $output );
		self::assertStringNotContainsString( 'secret-token-value', $output );
		self::assertStringNotContainsString( 'abcdefghijklmnopqrstuvwxyz', $output );
	}
}

