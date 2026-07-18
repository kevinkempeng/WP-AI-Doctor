<?php
/**
 * Log parser tests.
 *
 * @package PressCareAIErrorDoctor
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PressCare\AIErrorDoctor\Diagnostics\LogParser;
use PressCare\AIErrorDoctor\Privacy\Redactor;

final class LogParserTest extends TestCase {
	public function test_groups_repeated_events_and_detects_components(): void {
		$log = implode(
			"\n",
			array(
				'[18-Jul-2026 14:00:00 UTC] PHP Warning: Undefined array key "mode" in /var/www/example/public/wp-content/plugins/sample-plugin/includes/class-runner.php on line 10',
				'[18-Jul-2026 14:01:00 UTC] PHP Warning: Undefined array key "mode" in /var/www/example/public/wp-content/plugins/sample-plugin/includes/class-runner.php on line 11',
				'[18-Jul-2026 14:02:00 UTC] PHP Fatal error: Uncaught Error: Call to undefined function missing_function() in /var/www/example/public/wp-content/themes/sample-theme/functions.php:42',
				'Stack trace:',
				'#0 /var/www/example/public/wp-includes/class-wp-hook.php(324): sample_callback()',
				'#1 {main}',
				'  thrown in /var/www/example/public/wp-content/themes/sample-theme/functions.php on line 42',
			)
		);

		$result = ( new LogParser( new Redactor() ) )->parse( $log );

		self::assertSame( 3, $result['events_total'] );
		self::assertSame( 2, count( $result['groups'] ) );
		self::assertSame( 1, $result['counts']['critical'] );
		self::assertSame( 2, $result['counts']['warning'] );
		self::assertSame( 'theme', $result['groups'][0]['component_type'] );
		self::assertSame( 'sample-theme', $result['groups'][0]['component_slug'] );
		self::assertSame( 'plugin', $result['groups'][1]['component_type'] );
		self::assertSame( 2, $result['groups'][1]['count'] );
		self::assertStringNotContainsString( '/var/www/example', $result['groups'][0]['sample'] );
	}
}

