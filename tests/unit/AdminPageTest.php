<?php
/**
 * Admin finding-organization tests.
 *
 * @package PressCareAIErrorDoctor
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PressCare\AIErrorDoctor\Admin\AdminPage;

final class AdminPageTest extends TestCase {
	private AdminPage $page;

	protected function setUp(): void {
		$this->page = ( new ReflectionClass( AdminPage::class ) )->newInstanceWithoutConstructor();
	}

	public function test_current_warning_ranks_before_historical_critical_event(): void {
		$current_warning = array(
			'severity'         => 'warning',
			'recent_count'     => 1,
			'historical_count' => 0,
			'count'            => 1,
			'last_seen'        => 200,
		);
		$historical_fatal = array(
			'severity'         => 'critical',
			'recent_count'     => 0,
			'historical_count' => 1,
			'count'            => 1,
			'last_seen'        => 100,
		);

		self::assertLessThan( 0, $this->invoke( 'compare_group_priority', array( $current_warning, $historical_fatal ) ) );
	}

	public function test_finding_title_removes_timestamp_severity_and_path_noise(): void {
		$group = array(
			'sample' => '[18-Jul-2026 08:00:44 UTC] PHP Warning: Array to string conversion in [plugins]/example/plugin.php on line 10',
		);

		self::assertSame( 'Array to string conversion', $this->invoke( 'finding_title', array( $group ) ) );
	}

	public function test_focused_report_contains_only_selected_group(): void {
		$report = array(
			'summary' => array(),
			'groups'  => array(
				array(
					'fingerprint'      => 'first',
					'severity'         => 'warning',
					'count'            => 5,
					'recent_count'     => 2,
					'historical_count' => 3,
					'undated_count'    => 0,
					'first_seen'       => 100,
					'last_seen'        => 200,
				),
				array(
					'fingerprint' => 'second',
					'severity'    => 'critical',
					'count'       => 1,
				),
			),
		);

		$focused = $this->invoke( 'focus_report', array( $report, 'first' ) );

		self::assertSame( 1, $focused['summary']['groups_total'] );
		self::assertSame( 5, $focused['summary']['events_total'] );
		self::assertSame( 'first', $focused['groups'][0]['fingerprint'] );
	}

	public function test_action_plan_renders_current_work_and_collapses_history(): void {
		$report = array(
			'summary' => array( 'recency_window_days' => 7 ),
			'groups'  => array(
				array(
					'fingerprint'      => 'current',
					'severity'         => 'warning',
					'component_type'   => 'plugin',
					'component_slug'   => 'sample-plugin',
					'count'            => 2,
					'recent_count'     => 2,
					'historical_count' => 0,
					'undated_count'    => 0,
					'first_seen'       => 100,
					'last_seen'        => 200,
					'sample'           => '[18-Jul-2026 08:00:44 UTC] PHP Warning: Current warning in [plugins]/sample/plugin.php on line 10',
				),
				array(
					'fingerprint'      => 'history',
					'severity'         => 'critical',
					'component_type'   => 'theme',
					'component_slug'   => 'sample-theme',
					'count'            => 1,
					'recent_count'     => 0,
					'historical_count' => 1,
					'undated_count'    => 0,
					'first_seen'       => 50,
					'last_seen'        => 50,
					'sample'           => '[01-Jul-2025 08:00:44 UTC] PHP Fatal error: Historical failure in [wp-content]/themes/sample/functions.php on line 20',
				),
			),
		);

		ob_start();
		$this->invoke( 'render_groups', array( $report, array() ) );
		$html = (string) ob_get_clean();

		self::assertStringContainsString( 'What needs attention now', $html );
		self::assertStringContainsString( 'Resolution steps', $html );
		self::assertStringContainsString( 'Ask PressCare AI', $html );
		self::assertStringContainsString( 'Older log history', $html );
		self::assertStringContainsString( '<details class="pcaied-archive">', $html );
	}

	/**
	 * @param mixed[] $arguments Method arguments.
	 * @return mixed
	 */
	private function invoke( string $method_name, array $arguments ) {
		$method = new ReflectionMethod( AdminPage::class, $method_name );
		$method->setAccessible( true );

		return $method->invokeArgs( $this->page, $arguments );
	}
}
