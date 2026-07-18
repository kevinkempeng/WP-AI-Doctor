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

		$result = ( new LogParser( new Redactor() ) )->parse( $log, (int) strtotime( '12-Jul-2026 00:00:00 UTC' ) );

		self::assertSame( 3, $result['events_total'] );
		self::assertSame( 2, count( $result['groups'] ) );
		self::assertSame( 1, $result['counts']['critical'] );
		self::assertSame( 2, $result['counts']['warning'] );
		self::assertSame( 'theme', $result['groups'][0]['component_type'] );
		self::assertSame( 'sample-theme', $result['groups'][0]['component_slug'] );
		self::assertSame( 'plugin', $result['groups'][1]['component_type'] );
		self::assertSame( 2, $result['groups'][1]['count'] );
		self::assertSame( 3, $result['recency']['recent'] );
		self::assertSame( 0, $result['recency']['historical'] );
		self::assertStringNotContainsString( '/var/www/example', $result['groups'][0]['sample'] );
	}

	public function test_separates_recent_historical_and_undated_events(): void {
		$log = implode(
			"\n",
			array(
				'[18-Jul-2026 14:00:00 UTC] PHP Warning: Recent warning in /var/www/example/public/wp-content/plugins/recent-plugin/recent.php on line 10',
				'[01-Jul-2026 14:00:00 UTC] PHP Warning: Historical warning in /var/www/example/public/wp-content/plugins/older-plugin/older.php on line 20',
				'PHP Warning: Undated warning in /var/www/example/public/wp-content/plugins/undated-plugin/undated.php on line 30',
			)
		);

		$result = ( new LogParser( new Redactor() ) )->parse( $log, (int) strtotime( '12-Jul-2026 00:00:00 UTC' ) );

		self::assertSame( 1, $result['recency']['recent'] );
		self::assertSame( 1, $result['recency']['historical'] );
		self::assertSame( 1, $result['recency']['undated'] );
		self::assertSame( (int) strtotime( '01-Jul-2026 14:00:00 UTC' ), $result['recency']['oldest_seen'] );
		self::assertSame( (int) strtotime( '18-Jul-2026 14:00:00 UTC' ), $result['recency']['newest_seen'] );
	}
}
