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

	public function test_current_critical_finding_is_prominent_and_linked_to_support(): void {
		$report = array(
			'summary' => array( 'recency_window_days' => 7 ),
			'groups'  => array(
				array(
					'fingerprint'      => 'ordinary-warning',
					'severity'         => 'warning',
					'component_type'   => 'plugin',
					'component_slug'   => 'first-in-input',
					'count'            => 20,
					'recent_count'     => 20,
					'historical_count' => 0,
					'undated_count'    => 0,
					'first_seen'       => 100,
					'last_seen'        => 200,
					'sample'           => '[18-Jul-2026 08:00:44 UTC] PHP Warning: Ordinary warning in [plugins]/example.php on line 10',
				),
				array(
					'fingerprint'      => 'fatal-finding-123',
					'severity'         => 'critical',
					'component_type'   => 'plugin',
					'component_slug'   => 'woocommerce-payments',
					'count'            => 2,
					'recent_count'     => 2,
					'historical_count' => 0,
					'undated_count'    => 0,
					'first_seen'       => 100,
					'last_seen'        => 200,
					'sample'           => '[18-Jul-2026 08:00:44 UTC] PHP Fatal error: Payment task stopped in [plugins]/woocommerce-payments/example.php on line 10',
				),
			),
		);

		ob_start();
		$this->invoke( 'render_groups', array( $report, array() ) );
		$html = (string) ob_get_clean();

		self::assertStringContainsString( '1 current critical finding', $html );
		self::assertStringContainsString( 'Serious issues needing attention now', $html );
		self::assertStringContainsString( 'data-pcaied-jump-to="pcaied-finding-fatal-finding-123"', $html );
		self::assertStringContainsString( 'pcaied-finding-severity-critical-active', $html );
		self::assertStringContainsString( 'Critical · active now', $html );
		self::assertStringContainsString( 'id="pcaied-resolution-fatal-finding-123" class="pcaied-resolution">', $html );
		self::assertStringContainsString( 'Get expert help', $html );
		self::assertStringContainsString( 'finding_id=fatal-finding-123', $html );
		self::assertStringNotContainsString( 'Payment%20task%20stopped', $html );
		self::assertLessThan( strpos( $html, 'Ordinary warning' ), strpos( $html, 'Payment task stopped' ) );
	}

	public function test_summary_counts_only_recent_fatal_events_as_current_critical(): void {
		$report = array(
			'generated_at' => 200,
			'log'          => array(
				'source'      => 'WP_DEBUG_LOG',
				'bytes_read'  => 1000,
				'modified_at' => 200,
			),
			'environment'  => array(
				'wordpress_version' => '7.0',
				'php_version'       => '8.3',
			),
			'summary'      => array(
				'recent_events_total'     => 3,
				'historical_events_total' => 6,
				'undated_events_total'    => 0,
				'recency_window_days'     => 7,
				'counts'                  => array( 'critical' => 6 ),
			),
			'groups'       => array(
				array(
					'severity'         => 'critical',
					'recent_count'     => 0,
					'historical_count' => 6,
				),
				array(
					'severity'     => 'warning',
					'recent_count' => 3,
				),
			),
		);

		ob_start();
		$this->invoke( 'render_summary', array( $report ) );
		$html = (string) ob_get_clean();

		self::assertMatchesRegularExpression( '/<strong>0<\/strong>\s*<span>Current critical<\/span>/', $html );
		self::assertStringContainsString( 'None in the last 7 days', $html );
		self::assertStringNotContainsString( '<span>Critical</span>', $html );
	}

	public function test_historical_plugin_finding_does_not_recommend_an_immediate_update(): void {
		$steps = $this->invoke(
			'resolution_steps',
			array(
				array(
					'severity'         => 'critical',
					'component_type'   => 'plugin',
					'component_slug'   => 'woocommerce-payments',
					'recent_count'     => 0,
					'historical_count' => 2,
					'undated_count'    => 0,
				)
			)
		);
		$text = implode( ' ', $steps );

		self::assertStringContainsString( 'Do not update or change Woocommerce Payments solely because of this older record', $text );
		self::assertStringContainsString( 'No immediate change is recommended', $text );
		self::assertStringNotContainsString( 'Back up first, then update or test', $text );
	}

	public function test_report_explainer_describes_the_log_without_exposing_a_path(): void {
		$report = array(
			'log' => array(
				'source'     => 'WP_DEBUG_LOG',
				'file'       => 'debug.log',
				'bytes_read' => 2048,
				'truncated'  => true,
			),
		);

		ob_start();
		$this->invoke( 'render_report_explainer', array( $report ) );
		$html = (string) ob_get_clean();

		self::assertStringContainsString( 'A readable translation of your PHP error log', $html );
		self::assertStringContainsString( 'WordPress debug log configured by WP_DEBUG_LOG', $html );
		self::assertStringContainsString( 'View / save as PDF', $html );
		self::assertStringContainsString( 'not the complete raw log or its private server path', $html );
	}

	public function test_site_health_snapshot_is_read_only_and_avoids_alarmist_labels(): void {
		$health = array(
			'wordpress_version' => '7.0',
			'php_version'       => '8.3',
			'database_version'  => '8.0.36',
			'web_server'        => 'nginx/1.26',
			'multisite'         => false,
			'object_cache'      => true,
			'transients'        => array(
				'count'         => 42,
				'size_bytes'    => 4096,
				'expired_count' => 3,
				'available'     => true,
			),
			'autoload'          => array(
				'count'        => 620,
				'size_bytes'   => 920000,
				'review_bytes' => 800000,
				'status'       => 'review',
				'available'    => true,
				'largest'      => array(
					array(
						'name'       => 'woocommerce_helper_data',
						'size_bytes' => 320000,
					),
				),
			),
		);

		ob_start();
		$this->invoke( 'render_site_health', array( $health ) );
		$html = (string) ob_get_clean();

		self::assertStringContainsString( 'Useful context before you change anything', $html );
		self::assertStringContainsString( 'These figures are context—not errors', $html );
		self::assertStringContainsString( 'Cleanup available', $html );
		self::assertStringContainsString( 'Review recommended', $html );
		self::assertStringContainsString( 'will not flush it', $html );
		self::assertStringContainsString( 'Option values are never read into this report', $html );
		self::assertStringNotContainsString( 'Clear All Transients', $html );
		self::assertStringNotContainsString( 'Critical', $html );
	}

	public function test_support_options_keep_free_guidance_separate_from_advanced_care(): void {
		ob_start();
		$this->invoke( 'render_support_options', array() );
		$html = (string) ob_get_clean();

		self::assertStringContainsString( 'Free plugin guidance', $html );
		self::assertStringContainsString( 'Hands-on PressCare support', $html );
		self::assertStringContainsString( 'separate professional service', $html );
		self::assertStringContainsString( 'Scope and pricing are confirmed before any work begins', $html );
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
