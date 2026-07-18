<?php
/**
 * Dependency-free smoke tests for the parser and privacy boundary.
 *
 * @package PressCareAIErrorDoctor
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

use PressCare\AIErrorDoctor\Diagnostics\LogParser;
use PressCare\AIErrorDoctor\Privacy\Redactor;

/**
 * @throws RuntimeException When a smoke-test condition fails.
 */
function pcaied_assert( bool $condition, string $message ): void {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

$redactor = new Redactor();
$private  = "admin@example.test 192.0.2.44 /home/customer/private.php Table 'private_database.wp_customer_records' api_key=sk-proj-abcdefghijklmnopqrstuvwxyz";
$clean    = $redactor->redact( $private );

pcaied_assert( ! str_contains( $clean, 'admin@example.test' ), 'Email address was not redacted.' );
pcaied_assert( ! str_contains( $clean, '192.0.2.44' ), 'IP address was not redacted.' );
pcaied_assert( ! str_contains( $clean, '/home/customer' ), 'Filesystem path was not redacted.' );
pcaied_assert( ! str_contains( $clean, 'private_database' ), 'Database identifier was not redacted.' );
pcaied_assert( str_contains( $clean, '[database-table-redacted]' ), 'Database table was not explicitly marked as redacted.' );
pcaied_assert( ! str_contains( $clean, 'abcdefghijklmnopqrstuvwxyz' ), 'API key was not redacted.' );

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
		'[18-Jul-2026 14:03:00 UTC] WordPress database error Duplicate entry for query INSERT INTO wp_private VALUES ("secret@example.test") made by do_action',
	)
);

$result = ( new LogParser( $redactor ) )->parse( $log );

pcaied_assert( 4 === $result['events_total'], 'Parser did not detect all events.' );
pcaied_assert( 1 === $result['counts']['critical'], 'Fatal-event severity count is incorrect.' );
pcaied_assert( 2 === $result['counts']['warning'], 'Warning-event severity count is incorrect.' );
pcaied_assert( 1 === $result['counts']['error'], 'Database-event severity count is incorrect.' );
pcaied_assert( 3 === count( $result['groups'] ), 'Repeated events were not grouped.' );

$samples = implode( "\n", array_column( $result['groups'], 'sample' ) );
pcaied_assert( ! str_contains( $samples, '/var/www/example' ), 'A private path remained in a stored sample.' );
pcaied_assert( ! str_contains( $samples, 'secret@example.test' ), 'A database query value remained in a stored sample.' );
pcaied_assert( str_contains( $samples, '[database-query-redacted]' ), 'Database query was not explicitly marked as redacted.' );

echo "PressCare AI Error Doctor smoke tests passed.\n";
