<?php
/**
 * Read-only Tools screen and action handlers.
 *
 * @package PressCareAIErrorDoctor
 */

declare(strict_types=1);

namespace PressCare\AIErrorDoctor\Admin;

use PressCare\AIErrorDoctor\AI\Analyzer;
use PressCare\AIErrorDoctor\Diagnostics\DiagnosticEngine;
use PressCare\AIErrorDoctor\Privacy\Redactor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class AdminPage {
	private const PAGE_SLUG    = 'presscare-ai-error-doctor';
	private const REPORT_META  = '_pcaied_last_report';
	private const AI_META      = '_pcaied_last_ai_report';
	private const HANDLED_META = '_pcaied_handled_findings';

	private DiagnosticEngine $engine;
	private Analyzer $analyzer;
	private Redactor $redactor;

	public function __construct( DiagnosticEngine $engine, Analyzer $analyzer, Redactor $redactor ) {
		$this->engine   = $engine;
		$this->analyzer = $analyzer;
		$this->redactor = $redactor;
	}

	public function register_hooks(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_post_pcaied_scan', array( $this, 'handle_scan' ) );
		add_action( 'admin_post_pcaied_analyze', array( $this, 'handle_analyze' ) );
		add_action( 'admin_post_pcaied_clear', array( $this, 'handle_clear' ) );
		add_action( 'admin_post_pcaied_export', array( $this, 'handle_export' ) );
		add_action( 'admin_post_pcaied_review', array( $this, 'handle_review' ) );
	}

	public function register_menu(): void {
		add_management_page(
			__( 'PressCare AI Error Doctor', 'presscare-ai-error-doctor' ),
			__( 'AI Error Doctor', 'presscare-ai-error-doctor' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render' )
		);
	}

	public function enqueue_assets( string $hook_suffix ): void {
		if ( 'tools_page_' . self::PAGE_SLUG !== $hook_suffix ) {
			return;
		}

		wp_enqueue_style( 'pcaied-admin', PCAIED_URL . 'assets/admin.css', array(), PCAIED_VERSION );
		wp_enqueue_script( 'pcaied-admin', PCAIED_URL . 'assets/admin.js', array(), PCAIED_VERSION, true );
	}

	public function render(): void {
		$this->authorize();

		$user_id   = get_current_user_id();
		$report    = get_user_meta( $user_id, self::REPORT_META, true );
		$ai_report = get_user_meta( $user_id, self::AI_META, true );
		$notice    = get_transient( 'pcaied_notice_' . $user_id );

		if ( false !== $notice ) {
			delete_transient( 'pcaied_notice_' . $user_id );
		}

		$report    = is_array( $report ) ? $this->sanitize_saved_report( $report, self::REPORT_META ) : array();
		$ai_report = is_array( $ai_report ) ? $this->sanitize_saved_report( $ai_report, self::AI_META ) : array();
		$handled   = get_user_meta( $user_id, self::HANDLED_META, true );
		$handled   = is_array( $handled ) ? $handled : array();
		?>
		<div class="wrap pcaied-wrap">
			<section class="pcaied-hero">
				<div class="pcaied-hero-copy">
					<div class="pcaied-brand-row">
						<span class="pcaied-brand-mark" aria-hidden="true">PC</span>
						<p class="pcaied-eyebrow"><?php esc_html_e( 'PressCare AI · WordPress diagnostics', 'presscare-ai-error-doctor' ); ?></p>
					</div>
					<h1><?php esc_html_e( 'Turn a noisy error log into a clear plan.', 'presscare-ai-error-doctor' ); ?></h1>
					<p class="pcaied-hero-lede"><?php esc_html_e( 'AI Error Doctor organizes repeated WordPress and PHP errors, protects sensitive details, and helps you focus on the findings that matter now.', 'presscare-ai-error-doctor' ); ?></p>
					<div class="pcaied-trust-row">
						<span><?php esc_html_e( 'Read-only', 'presscare-ai-error-doctor' ); ?></span>
						<span><?php esc_html_e( 'Privacy-first', 'presscare-ai-error-doctor' ); ?></span>
						<span><?php esc_html_e( 'Provider-independent', 'presscare-ai-error-doctor' ); ?></span>
					</div>
				</div>
				<div class="pcaied-hero-card">
					<span><?php esc_html_e( 'PressCare AI', 'presscare-ai-error-doctor' ); ?></span>
					<strong><?php esc_html_e( 'AI Error Doctor', 'presscare-ai-error-doctor' ); ?></strong>
					<p><?php esc_html_e( 'Part of a growing collection of practical AI tools for WordPress professionals.', 'presscare-ai-error-doctor' ); ?></p>
				</div>
			</section>

			<?php if ( is_array( $notice ) && isset( $notice['type'], $notice['message'] ) ) : ?>
				<div class="notice notice-<?php echo esc_attr( $notice['type'] ); ?> is-dismissible"><p><?php echo esc_html( $notice['message'] ); ?></p></div>
			<?php endif; ?>

			<section class="pcaied-actions pcaied-panel">
				<div class="pcaied-section-copy">
					<p class="pcaied-section-kicker"><?php esc_html_e( 'Step 1 · Private local review', 'presscare-ai-error-doctor' ); ?></p>
					<h2><?php esc_html_e( 'See what your error log is really saying', 'presscare-ai-error-doctor' ); ?></h2>
					<p><?php esc_html_e( 'Scan the final 2 MB of the configured log, group repeated events, and separate recent activity from older history. Nothing is sent to an AI provider during this step.', 'presscare-ai-error-doctor' ); ?></p>
				</div>
				<div class="pcaied-button-row">
					<?php $this->action_form( 'pcaied_scan', __( 'Run local scan', 'presscare-ai-error-doctor' ), 'button button-primary' ); ?>
					<?php if ( $report ) : ?>
						<?php $this->action_form( 'pcaied_export', __( 'Export JSON', 'presscare-ai-error-doctor' ), 'button' ); ?>
						<?php $this->action_form( 'pcaied_clear', __( 'Clear report', 'presscare-ai-error-doctor' ), 'button pcaied-clear' ); ?>
					<?php endif; ?>
				</div>
			</section>

			<?php if ( $report ) : ?>
				<?php $this->render_summary( $report ); ?>
				<?php $this->render_groups( $report, $handled ); ?>
				<?php $this->render_ai_action( $report ); ?>
			<?php else : ?>
				<section class="pcaied-panel pcaied-empty">
					<div class="pcaied-empty-icon" aria-hidden="true">01</div>
					<h2><?php esc_html_e( 'Your first private scan is ready when you are', 'presscare-ai-error-doctor' ); ?></h2>
					<p><?php esc_html_e( 'Start locally. AI Error Doctor will organize the log and protect sensitive details before any optional AI step becomes available.', 'presscare-ai-error-doctor' ); ?></p>
				</section>
			<?php endif; ?>

			<?php if ( $ai_report ) : ?>
				<?php $this->render_ai_report( $ai_report ); ?>
			<?php endif; ?>

			<?php $this->render_presscare_ai_promo(); ?>

			<footer class="pcaied-footer">
				<p><strong><?php esc_html_e( 'Built by PressCare AI.', 'presscare-ai-error-doctor' ); ?></strong> <?php esc_html_e( 'Thoughtful automation for safer, clearer WordPress work.', 'presscare-ai-error-doctor' ); ?></p>
				<a href="<?php echo esc_url( 'https://presscare.com/contact/' ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Talk with PressCare', 'presscare-ai-error-doctor' ); ?></a>
			</footer>
		</div>
		<?php
	}

	public function handle_scan(): void {
		$this->authorize();
		check_admin_referer( 'pcaied_scan' );

		$report = $this->engine->scan();
		if ( is_wp_error( $report ) ) {
			$this->redirect_with_notice( 'error', $report->get_error_message() );
		}

		update_user_meta( get_current_user_id(), self::REPORT_META, $report );
		delete_user_meta( get_current_user_id(), self::AI_META );
		$this->redirect_with_notice( 'success', __( 'The local diagnostic scan completed.', 'presscare-ai-error-doctor' ) );
	}

	public function handle_analyze(): void {
		$this->authorize();
		check_admin_referer( 'pcaied_analyze' );

		$consent = isset( $_POST['pcaied_ai_consent'] ) ? sanitize_text_field( wp_unslash( $_POST['pcaied_ai_consent'] ) ) : '';
		if ( '1' !== $consent ) {
			$this->redirect_with_notice( 'error', __( 'Confirm the data-sharing notice before requesting AI analysis.', 'presscare-ai-error-doctor' ) );
		}

		$report = get_user_meta( get_current_user_id(), self::REPORT_META, true );
		if ( ! is_array( $report ) || ! $report ) {
			$this->redirect_with_notice( 'error', __( 'Run a local scan before requesting AI analysis.', 'presscare-ai-error-doctor' ) );
		}
		$report      = $this->sanitize_saved_report( $report, self::REPORT_META );
		$fingerprint = isset( $_POST['pcaied_fingerprint'] ) ? sanitize_key( wp_unslash( $_POST['pcaied_fingerprint'] ) ) : '';
		if ( isset( $report['groups'] ) && is_array( $report['groups'] ) ) {
			usort( $report['groups'], array( $this, 'compare_group_priority' ) );
		}

		if ( '' !== $fingerprint ) {
			$report = $this->focus_report( $report, $fingerprint );
			if ( ! $report ) {
				$this->redirect_with_notice( 'error', __( 'The selected finding is no longer in the local report. Run a fresh scan and try again.', 'presscare-ai-error-doctor' ) );
			}
		}

		$analysis = $this->analyzer->analyze( $report );
		if ( is_wp_error( $analysis ) ) {
			$this->redirect_with_notice( 'error', $analysis->get_error_message() );
		}

		if ( '' !== $fingerprint ) {
			$analysis['requested_fingerprint'] = $fingerprint;
		}

		update_user_meta( get_current_user_id(), self::AI_META, $analysis );
		$this->redirect_with_notice( 'success', __( 'The AI explanation is ready.', 'presscare-ai-error-doctor' ), 'pcaied-ai-report' );
	}

	public function handle_clear(): void {
		$this->authorize();
		check_admin_referer( 'pcaied_clear' );
		delete_user_meta( get_current_user_id(), self::REPORT_META );
		delete_user_meta( get_current_user_id(), self::AI_META );
		delete_user_meta( get_current_user_id(), self::HANDLED_META );
		$this->redirect_with_notice( 'success', __( 'The stored diagnostic reports were removed.', 'presscare-ai-error-doctor' ) );
	}

	public function handle_review(): void {
		$this->authorize();
		check_admin_referer( 'pcaied_review' );

		$fingerprint = isset( $_POST['pcaied_fingerprint'] ) ? sanitize_key( wp_unslash( $_POST['pcaied_fingerprint'] ) ) : '';
		$state       = isset( $_POST['pcaied_review_state'] ) ? sanitize_key( wp_unslash( $_POST['pcaied_review_state'] ) ) : '';
		$report      = get_user_meta( get_current_user_id(), self::REPORT_META, true );

		if ( ! in_array( $state, array( 'handled', 'open' ), true ) || ! is_array( $report ) || ! $this->report_has_fingerprint( $report, $fingerprint ) ) {
			$this->redirect_with_notice( 'error', __( 'That finding is no longer available. Run a fresh local scan.', 'presscare-ai-error-doctor' ) );
		}

		$handled = get_user_meta( get_current_user_id(), self::HANDLED_META, true );
		$handled = is_array( $handled ) ? $handled : array();

		if ( 'handled' === $state ) {
			$handled[ $fingerprint ] = time();
			update_user_meta( get_current_user_id(), self::HANDLED_META, array_slice( $handled, -200, null, true ) );
			$this->redirect_with_notice( 'success', __( 'The finding was moved to Handled. A newer occurrence will return it to the action list.', 'presscare-ai-error-doctor' ), 'pcaied-action-plan' );
		}

		unset( $handled[ $fingerprint ] );
		if ( $handled ) {
			update_user_meta( get_current_user_id(), self::HANDLED_META, $handled );
		} else {
			delete_user_meta( get_current_user_id(), self::HANDLED_META );
		}
		$this->redirect_with_notice( 'success', __( 'The finding was returned to the review list.', 'presscare-ai-error-doctor' ), 'pcaied-action-plan' );
	}

	public function handle_export(): void {
		$this->authorize();
		check_admin_referer( 'pcaied_export' );

		$report = get_user_meta( get_current_user_id(), self::REPORT_META, true );
		if ( ! is_array( $report ) || ! $report ) {
			$this->redirect_with_notice( 'error', __( 'There is no diagnostic report to export.', 'presscare-ai-error-doctor' ) );
		}
		$report = $this->sanitize_saved_report( $report, self::REPORT_META );

		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="presscare-error-doctor-report-' . gmdate( 'Y-m-d-His' ) . '.json"' );
		echo wp_json_encode( $report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	/**
	 * @param array<string,mixed> $report Report data.
	 */
	private function render_summary( array $report ): void {
		$counts         = $report['summary']['counts'] ?? array();
		$summary        = $report['summary'] ?? array();
		$window_days    = (int) ( $summary['recency_window_days'] ?? 7 );
		$recent_events  = (int) ( $summary['recent_events_total'] ?? 0 );
		$older_events   = (int) ( $summary['historical_events_total'] ?? 0 );
		$undated_events = (int) ( $summary['undated_events_total'] ?? 0 );
		$has_timeline   = array_key_exists( 'recent_events_total', $summary );
		?>
		<section class="pcaied-metrics" aria-label="<?php esc_attr_e( 'Diagnostic summary', 'presscare-ai-error-doctor' ); ?>">
			<?php
			if ( $has_timeline ) {
				/* translators: %d: Number of days considered recent. */
				$recent_context = sprintf( __( 'Last %d days', 'presscare-ai-error-doctor' ), $window_days );
				$this->metric( __( 'Recent events', 'presscare-ai-error-doctor' ), $recent_events, 'recent', $recent_context );
				$this->metric( __( 'Older events', 'presscare-ai-error-doctor' ), $older_events, 'historical', __( 'Existing log history', 'presscare-ai-error-doctor' ) );
			} else {
				$this->metric( __( 'Events found', 'presscare-ai-error-doctor' ), (int) ( $summary['events_total'] ?? 0 ), 'recent', __( 'Saved earlier report', 'presscare-ai-error-doctor' ) );
				$this->metric( __( 'Groups', 'presscare-ai-error-doctor' ), (int) ( $summary['groups_total'] ?? 0 ), 'historical', __( 'Repeated events combined', 'presscare-ai-error-doctor' ) );
			}
			$this->metric( __( 'Critical', 'presscare-ai-error-doctor' ), (int) ( $counts['critical'] ?? 0 ), 'critical', __( 'Across the scanned log', 'presscare-ai-error-doctor' ) );
			$this->metric( __( 'Errors', 'presscare-ai-error-doctor' ), (int) ( $counts['error'] ?? 0 ), 'error', __( 'Across the scanned log', 'presscare-ai-error-doctor' ) );
			$this->metric( __( 'Warnings', 'presscare-ai-error-doctor' ), (int) ( $counts['warning'] ?? 0 ), 'warning', __( 'Across the scanned log', 'presscare-ai-error-doctor' ) );
			?>
		</section>
		<section class="pcaied-timeline-note <?php echo esc_attr( $has_timeline && $older_events <= $recent_events ? 'pcaied-timeline-current' : 'pcaied-timeline-history' ); ?>">
			<div class="pcaied-timeline-icon" aria-hidden="true">i</div>
			<div>
				<?php if ( ! $has_timeline ) : ?>
					<strong><?php esc_html_e( 'Refresh this saved report for the new timing view.', 'presscare-ai-error-doctor' ); ?></strong>
					<p><?php esc_html_e( 'This report was created by an earlier version of AI Error Doctor. Run a local scan to label recent and historical activity accurately.', 'presscare-ai-error-doctor' ); ?></p>
				<?php elseif ( $older_events > $recent_events ) : ?>
					<strong><?php esc_html_e( 'Most of this report is historical.', 'presscare-ai-error-doctor' ); ?></strong>
					<p><?php esc_html_e( 'AI Error Doctor reads existing log history; it does not create these entries. Start with findings marked Recent when deciding what needs attention now.', 'presscare-ai-error-doctor' ); ?></p>
				<?php elseif ( $recent_events > 0 ) : ?>
					<strong><?php esc_html_e( 'Recent activity deserves the first look.', 'presscare-ai-error-doctor' ); ?></strong>
					<p><?php esc_html_e( 'Use the timing labels below to separate current activity from older context before making changes.', 'presscare-ai-error-doctor' ); ?></p>
				<?php else : ?>
					<strong><?php esc_html_e( 'No recent dated events were found.', 'presscare-ai-error-doctor' ); ?></strong>
					<p><?php esc_html_e( 'The report may contain older or undated entries. Review the timestamps before treating them as active problems.', 'presscare-ai-error-doctor' ); ?></p>
				<?php endif; ?>
				<?php if ( $has_timeline && $undated_events > 0 ) : ?>
					<p class="pcaied-timeline-undated">
						<?php
						/* translators: %d: Number of log events without timestamps. */
						echo esc_html( sprintf( _n( '%d event has no usable timestamp.', '%d events have no usable timestamp.', $undated_events, 'presscare-ai-error-doctor' ), $undated_events ) );
						?>
					</p>
				<?php endif; ?>
			</div>
		</section>
		<section class="pcaied-panel pcaied-meta">
			<div><strong><?php esc_html_e( 'Report generated', 'presscare-ai-error-doctor' ); ?></strong><span><?php echo esc_html( $this->format_timestamp( $report['generated_at'] ?? null ) ); ?></span></div>
			<div><strong><?php esc_html_e( 'Log last updated', 'presscare-ai-error-doctor' ); ?></strong><span><?php echo esc_html( $this->format_timestamp( $report['log']['modified_at'] ?? null ) ); ?></span></div>
			<div><strong><?php esc_html_e( 'Event date range', 'presscare-ai-error-doctor' ); ?></strong><span><?php echo esc_html( $this->format_date_range( $summary['oldest_seen'] ?? null, $summary['newest_seen'] ?? null ) ); ?></span></div>
			<div><strong><?php esc_html_e( 'Log source', 'presscare-ai-error-doctor' ); ?></strong><span><?php echo esc_html( (string) ( $report['log']['source'] ?? '' ) . ' · ' . size_format( (int) ( $report['log']['bytes_read'] ?? 0 ) ) ); ?></span></div>
			<div><strong><?php esc_html_e( 'Environment', 'presscare-ai-error-doctor' ); ?></strong><span><?php echo esc_html( 'WordPress ' . (string) ( $report['environment']['wordpress_version'] ?? '' ) . ' · PHP ' . (string) ( $report['environment']['php_version'] ?? '' ) ); ?></span></div>
		</section>
		<?php
	}

	/**
	 * @param array<string,mixed> $report  Report data.
	 * @param array<string,mixed> $handled Findings marked handled by the current administrator.
	 */
	private function render_groups( array $report, array $handled ): void {
		$groups      = isset( $report['groups'] ) && is_array( $report['groups'] ) ? array_values( array_filter( $report['groups'], 'is_array' ) ) : array();
		$window_days = (int) ( $report['summary']['recency_window_days'] ?? 7 );

		usort( $groups, array( $this, 'compare_group_priority' ) );

		$current_groups    = array();
		$historical_groups = array();
		$handled_groups    = array();

		foreach ( $groups as $group ) {
			if ( $this->is_group_handled( $group, $handled ) ) {
				$handled_groups[] = $group;
			} elseif ( (int) ( $group['recent_count'] ?? 0 ) > 0 || (int) ( $group['undated_count'] ?? 0 ) > 0 ) {
				$current_groups[] = $group;
			} else {
				$historical_groups[] = $group;
			}
		}

		$current_events     = array_sum(
			array_map(
				static fn ( array $group ): int => (int) ( $group['recent_count'] ?? 0 ) + (int) ( $group['undated_count'] ?? 0 ),
				$current_groups
			)
		);
		$current_components = count( $this->group_by_component( $current_groups ) );
		?>
		<section id="pcaied-action-plan" class="pcaied-panel pcaied-findings-panel">
			<div class="pcaied-section-heading">
				<div>
					<p class="pcaied-section-kicker"><?php esc_html_e( 'Step 2 · Work the action plan', 'presscare-ai-error-doctor' ); ?></p>
					<h2><?php esc_html_e( 'What needs attention now', 'presscare-ai-error-doctor' ); ?></h2>
					<p><?php esc_html_e( 'Current findings are ranked first and grouped by the plugin, theme, or WordPress area involved. Older log history stays collapsed until you need it.', 'presscare-ai-error-doctor' ); ?></p>
				</div>
				<span class="pcaied-group-total">
					<?php
					/* translators: %d: Number of current grouped findings. */
					echo esc_html( sprintf( _n( '%d current finding', '%d current findings', count( $current_groups ), 'presscare-ai-error-doctor' ), count( $current_groups ) ) );
					?>
				</span>
			</div>

			<?php if ( ! $groups ) : ?>
				<p class="pcaied-good"><?php esc_html_e( 'No recognized PHP or WordPress error events were found in the analyzed portion of the log.', 'presscare-ai-error-doctor' ); ?></p>
			<?php else : ?>
				<div class="pcaied-focus-summary">
					<div><strong><?php echo esc_html( (string) $current_events ); ?></strong><span><?php esc_html_e( 'current events', 'presscare-ai-error-doctor' ); ?></span></div>
					<div><strong><?php echo esc_html( (string) $current_components ); ?></strong><span><?php esc_html_e( 'areas involved', 'presscare-ai-error-doctor' ); ?></span></div>
					<div><strong><?php echo esc_html( (string) count( $historical_groups ) ); ?></strong><span><?php esc_html_e( 'older groups tucked away', 'presscare-ai-error-doctor' ); ?></span></div>
				</div>

				<?php if ( $current_groups ) : ?>
					<div class="pcaied-component-list">
						<?php $this->render_component_collection( $current_groups, $window_days ); ?>
					</div>
				<?php else : ?>
					<div class="pcaied-caught-up">
						<strong><?php esc_html_e( 'Nothing is currently asking for attention.', 'presscare-ai-error-doctor' ); ?></strong>
						<p><?php esc_html_e( 'The scan found only older log history or findings you already handled. That is useful context, not an urgent repair list.', 'presscare-ai-error-doctor' ); ?></p>
					</div>
				<?php endif; ?>

				<?php if ( $historical_groups ) : ?>
					<details class="pcaied-archive">
						<summary>
							<span><strong><?php esc_html_e( 'Older log history', 'presscare-ai-error-doctor' ); ?></strong><small><?php esc_html_e( 'Review only if you are investigating a past incident.', 'presscare-ai-error-doctor' ); ?></small></span>
							<b><?php echo esc_html( (string) count( $historical_groups ) ); ?></b>
						</summary>
						<div class="pcaied-component-list pcaied-component-list-archive">
							<?php $this->render_component_collection( $historical_groups, $window_days, true ); ?>
						</div>
					</details>
				<?php endif; ?>

				<?php if ( $handled_groups ) : ?>
					<details class="pcaied-archive pcaied-handled-archive">
						<summary>
							<span><strong><?php esc_html_e( 'Handled for now', 'presscare-ai-error-doctor' ); ?></strong><small><?php esc_html_e( 'These return automatically if a newer occurrence appears.', 'presscare-ai-error-doctor' ); ?></small></span>
							<b><?php echo esc_html( (string) count( $handled_groups ) ); ?></b>
						</summary>
						<div class="pcaied-component-list pcaied-component-list-archive">
							<?php $this->render_component_collection( $handled_groups, $window_days, true, true ); ?>
						</div>
					</details>
				<?php endif; ?>
			<?php endif; ?>
		</section>
		<?php
	}

	/**
	 * @param array<int,array<string,mixed>> $groups      Finding groups.
	 * @param int                            $window_days Recent window length.
	 * @param bool                           $collapsed   Whether component sections start collapsed.
	 * @param bool                           $handled     Whether findings were marked handled.
	 */
	private function render_component_collection( array $groups, int $window_days, bool $collapsed = false, bool $handled = false ): void {
		foreach ( $this->group_by_component( $groups ) as $component_groups ) {
			$first_group     = $component_groups[0];
			$component_type  = sanitize_key( (string) ( $first_group['component_type'] ?? 'unknown' ) );
			$component_name  = $this->component_name( $first_group );
			$event_count     = array_sum( array_map( static fn ( array $group ): int => (int) ( $group['count'] ?? 0 ), $component_groups ) );
			$component_count = count( $component_groups );
			$findings_label  = sprintf(
				/* translators: %d: Number of grouped findings. */
				_n( '%d finding', '%d findings', $component_count, 'presscare-ai-error-doctor' ),
				$component_count
			);
			$events_label = sprintf(
				/* translators: %d: Number of log events. */
				_n( '%d event', '%d events', $event_count, 'presscare-ai-error-doctor' ),
				$event_count
			);
			$component_meta = $findings_label . ' · ' . $events_label;

			if ( $collapsed ) :
				?>
				<details class="pcaied-component-group pcaied-component-collapsible">
					<summary>
						<span class="pcaied-component-icon pcaied-component-<?php echo esc_attr( $component_type ); ?>" aria-hidden="true"><?php echo esc_html( $this->component_icon( $component_type ) ); ?></span>
						<span><strong><?php echo esc_html( $component_name ); ?></strong><small><?php echo esc_html( $component_meta ); ?></small></span>
					</summary>
					<div class="pcaied-component-findings">
						<?php foreach ( $component_groups as $group ) : ?>
							<?php $this->render_finding( $group, $window_days, $handled ); ?>
						<?php endforeach; ?>
					</div>
				</details>
				<?php
			else :
				?>
				<section class="pcaied-component-group">
					<header class="pcaied-component-heading">
						<span class="pcaied-component-icon pcaied-component-<?php echo esc_attr( $component_type ); ?>" aria-hidden="true"><?php echo esc_html( $this->component_icon( $component_type ) ); ?></span>
						<span><strong><?php echo esc_html( $component_name ); ?></strong><small><?php echo esc_html( $component_meta ); ?></small></span>
					</header>
					<div class="pcaied-component-findings">
						<?php foreach ( $component_groups as $group ) : ?>
							<?php $this->render_finding( $group, $window_days ); ?>
						<?php endforeach; ?>
					</div>
				</section>
				<?php
			endif;
		}
	}

	/**
	 * @param array<string,mixed> $group       Finding group.
	 * @param int                 $window_days Recent window length.
	 * @param bool                $handled     Whether the finding is handled.
	 */
	private function render_finding( array $group, int $window_days, bool $handled = false ): void {
		$fingerprint      = sanitize_key( (string) ( $group['fingerprint'] ?? '' ) );
		$severity         = sanitize_key( (string) ( $group['severity'] ?? 'info' ) );
		$priority         = $handled ? array(
			'key'   => 'handled',
			'label' => __( 'Handled', 'presscare-ai-error-doctor' ),
		) : $this->finding_priority( $group );
		$title            = $this->finding_title( $group );
		$occurrence_count = (int) ( $group['count'] ?? 0 );
		$recent_count     = (int) ( $group['recent_count'] ?? 0 );
		$historical_count = (int) ( $group['historical_count'] ?? 0 );
		$undated_count    = (int) ( $group['undated_count'] ?? 0 );
		$resolution_id    = 'pcaied-resolution-' . $fingerprint;
		$technical_id     = 'pcaied-technical-' . $fingerprint;
		$occurrence_label = sprintf(
			/* translators: %d: Number of occurrences for the grouped error. */
			_n( '%d occurrence', '%d occurrences', $occurrence_count, 'presscare-ai-error-doctor' ),
			$occurrence_count
		);
		/* translators: %d: Number of recent occurrences. */
		$recent_label = sprintf( __( '%d recent', 'presscare-ai-error-doctor' ), $recent_count );
		/* translators: %d: Number of older occurrences. */
		$historical_label = sprintf( __( '%d older', 'presscare-ai-error-doctor' ), $historical_count );
		/* translators: %d: Number of occurrences without a timestamp. */
		$undated_label = sprintf( __( '%d undated', 'presscare-ai-error-doctor' ), $undated_count );
		/* translators: %s: Date when the finding last occurred. */
		$last_seen_label = sprintf( __( 'Last seen %s', 'presscare-ai-error-doctor' ), $this->format_timestamp( $group['last_seen'] ?? null, true ) );
		?>
		<article class="pcaied-finding pcaied-finding-<?php echo esc_attr( $priority['key'] ); ?>">
			<div class="pcaied-finding-heading">
				<div class="pcaied-finding-title-row">
					<span class="pcaied-priority pcaied-priority-<?php echo esc_attr( $priority['key'] ); ?>"><?php echo esc_html( $priority['label'] ); ?></span>
					<span class="pcaied-badge pcaied-<?php echo esc_attr( $severity ); ?>"><?php echo esc_html( ucfirst( $severity ) ); ?></span>
				</div>
				<span class="pcaied-occurrences"><?php echo esc_html( $occurrence_label ); ?></span>
			</div>
			<h4><?php echo esc_html( $title ); ?></h4>
			<p class="pcaied-finding-explanation"><?php echo esc_html( $this->finding_explanation( $group, $window_days ) ); ?></p>
			<div class="pcaied-finding-timing">
				<?php if ( $recent_count > 0 ) : ?>
					<span class="pcaied-timing-recent"><?php echo esc_html( $recent_label ); ?></span>
				<?php endif; ?>
				<?php if ( $historical_count > 0 ) : ?>
					<span><?php echo esc_html( $historical_label ); ?></span>
				<?php endif; ?>
				<?php if ( $undated_count > 0 ) : ?>
					<span><?php echo esc_html( $undated_label ); ?></span>
				<?php endif; ?>
				<span><?php echo esc_html( $last_seen_label ); ?></span>
			</div>
			<div class="pcaied-finding-actions">
				<button type="button" class="button button-primary" data-pcaied-toggle="<?php echo esc_attr( $resolution_id ); ?>" data-label-open="<?php esc_attr_e( 'Hide resolution steps', 'presscare-ai-error-doctor' ); ?>" data-label-closed="<?php esc_attr_e( 'Resolution steps', 'presscare-ai-error-doctor' ); ?>" aria-controls="<?php echo esc_attr( $resolution_id ); ?>" aria-expanded="false"><?php esc_html_e( 'Resolution steps', 'presscare-ai-error-doctor' ); ?></button>
				<button type="button" class="button" data-pcaied-ai-fingerprint="<?php echo esc_attr( $fingerprint ); ?>" data-pcaied-ai-title="<?php echo esc_attr( $title ); ?>"><?php esc_html_e( 'Ask PressCare AI', 'presscare-ai-error-doctor' ); ?></button>
				<button type="button" class="button" data-pcaied-toggle="<?php echo esc_attr( $technical_id ); ?>" data-label-open="<?php esc_attr_e( 'Hide technical details', 'presscare-ai-error-doctor' ); ?>" data-label-closed="<?php esc_attr_e( 'Technical details', 'presscare-ai-error-doctor' ); ?>" aria-controls="<?php echo esc_attr( $technical_id ); ?>" aria-expanded="false"><?php esc_html_e( 'Technical details', 'presscare-ai-error-doctor' ); ?></button>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<input type="hidden" name="action" value="pcaied_review">
					<input type="hidden" name="pcaied_fingerprint" value="<?php echo esc_attr( $fingerprint ); ?>">
					<input type="hidden" name="pcaied_review_state" value="<?php echo esc_attr( $handled ? 'open' : 'handled' ); ?>">
					<?php wp_nonce_field( 'pcaied_review' ); ?>
					<button type="submit" class="button pcaied-handle-button"><?php echo esc_html( $handled ? __( 'Return to review', 'presscare-ai-error-doctor' ) : __( 'Mark handled', 'presscare-ai-error-doctor' ) ); ?></button>
				</form>
			</div>
			<div id="<?php echo esc_attr( $resolution_id ); ?>" class="pcaied-resolution" hidden>
				<h5><?php esc_html_e( 'A safe path toward resolution', 'presscare-ai-error-doctor' ); ?></h5>
				<ol>
					<?php foreach ( $this->resolution_steps( $group ) as $step ) : ?>
						<li><?php echo esc_html( $step ); ?></li>
					<?php endforeach; ?>
				</ol>
				<p><?php esc_html_e( '“Mark handled” only organizes this report. It never changes code, settings, or the error log.', 'presscare-ai-error-doctor' ); ?></p>
			</div>
			<div id="<?php echo esc_attr( $technical_id ); ?>" class="pcaied-technical" hidden>
				<pre><?php echo esc_html( (string) ( $group['sample'] ?? '' ) ); ?></pre>
				<small class="pcaied-fingerprint"><?php echo esc_html( 'Finding ID ' . $fingerprint ); ?></small>
			</div>
		</article>
		<?php
	}

	/**
	 * @param array<int,array<string,mixed>> $groups Finding groups.
	 * @return array<string,array<int,array<string,mixed>>>
	 */
	private function group_by_component( array $groups ): array {
		$components = array();

		foreach ( $groups as $group ) {
			$type                 = sanitize_key( (string) ( $group['component_type'] ?? 'unknown' ) );
			$slug                 = sanitize_key( (string) ( $group['component_slug'] ?? 'unknown' ) );
			$key                  = $type . '|' . $slug;
			$components[ $key ][] = $group;
		}

		return $components;
	}

	/**
	 * @param array<string,mixed> $a First finding.
	 * @param array<string,mixed> $b Second finding.
	 */
	private function compare_group_priority( array $a, array $b ): int {
		$score_comparison = $this->group_priority_score( $b ) <=> $this->group_priority_score( $a );
		if ( 0 !== $score_comparison ) {
			return $score_comparison;
		}

		$last_a = $this->normalize_timestamp( $a['last_seen'] ?? null ) ?? 0;
		$last_b = $this->normalize_timestamp( $b['last_seen'] ?? null ) ?? 0;

		return $last_b <=> $last_a;
	}

	/**
	 * @param array<string,mixed> $group Finding group.
	 */
	private function group_priority_score( array $group ): int {
		$severity_rank = array(
			'critical' => 4,
			'error'    => 3,
			'warning'  => 2,
			'info'     => 1,
		);
		$severity      = sanitize_key( (string) ( $group['severity'] ?? 'info' ) );
		$rank          = $severity_rank[ $severity ] ?? 1;
		$recent        = (int) ( $group['recent_count'] ?? 0 );
		$undated       = (int) ( $group['undated_count'] ?? 0 );
		$count         = (int) ( $group['count'] ?? 0 );

		if ( $recent > 0 ) {
			return 10000 + ( $rank * 1000 ) + min( $recent, 999 );
		}

		if ( $undated > 0 ) {
			return 5000 + ( $rank * 1000 ) + min( $undated, 999 );
		}

		return ( $rank * 1000 ) + min( $count, 999 );
	}

	/**
	 * @param array<string,mixed> $group   Finding group.
	 * @param array<string,mixed> $handled Handled timestamps keyed by fingerprint.
	 */
	private function is_group_handled( array $group, array $handled ): bool {
		$fingerprint = sanitize_key( (string) ( $group['fingerprint'] ?? '' ) );
		$handled_at  = isset( $handled[ $fingerprint ] ) && is_numeric( $handled[ $fingerprint ] ) ? (int) $handled[ $fingerprint ] : 0;
		$last_seen   = $this->normalize_timestamp( $group['last_seen'] ?? null );

		return $handled_at > 0 && ( null === $last_seen || $handled_at >= $last_seen );
	}

	/**
	 * @param array<string,mixed> $group Finding group.
	 */
	private function component_name( array $group ): string {
		$type   = sanitize_key( (string) ( $group['component_type'] ?? 'unknown' ) );
		$slug   = sanitize_key( (string) ( $group['component_slug'] ?? 'unknown' ) );
		$sample = strtolower( (string) ( $group['sample'] ?? '' ) );

		if ( 'core' === $type ) {
			return __( 'WordPress core area', 'presscare-ai-error-doctor' );
		}

		if ( 'unknown' === $type || 'unknown' === $slug || '' === $slug ) {
			return str_contains( $sample, 'database error' )
				? __( 'Database / source not identified', 'presscare-ai-error-doctor' )
				: __( 'Source not identified', 'presscare-ai-error-doctor' );
		}

		return ucwords( str_replace( array( '-', '_' ), ' ', $slug ) );
	}

	private function component_icon( string $component_type ): string {
		$icons = array(
			'plugin' => 'P',
			'theme'  => 'T',
			'core'   => 'WP',
		);

		return $icons[ $component_type ] ?? '?';
	}

	/**
	 * @param array<string,mixed> $group Finding group.
	 * @return array{key:string,label:string}
	 */
	private function finding_priority( array $group ): array {
		$severity = sanitize_key( (string) ( $group['severity'] ?? 'info' ) );
		$recent   = (int) ( $group['recent_count'] ?? 0 );
		$undated  = (int) ( $group['undated_count'] ?? 0 );

		if ( $recent > 0 ) {
			if ( 'critical' === $severity ) {
				return array(
					'key'   => 'urgent',
					'label' => __( 'Urgent', 'presscare-ai-error-doctor' ),
				);
			}
			if ( 'error' === $severity ) {
				return array(
					'key'   => 'high',
					'label' => __( 'High priority', 'presscare-ai-error-doctor' ),
				);
			}

			return array(
				'key'   => 'review',
				'label' => __( 'Review', 'presscare-ai-error-doctor' ),
			);
		}

		if ( $undated > 0 ) {
			return array(
				'key'   => 'check',
				'label' => __( 'Check timing', 'presscare-ai-error-doctor' ),
			);
		}

		return array(
			'key'   => 'history',
			'label' => __( 'Historical', 'presscare-ai-error-doctor' ),
		);
	}

	/**
	 * @param array<string,mixed> $group Finding group.
	 */
	private function finding_title( array $group ): string {
		$sample = strtok( (string) ( $group['sample'] ?? '' ), "\n" );
		$sample = is_string( $sample ) ? $sample : '';
		$sample = preg_replace( '/^\[[^\]]+\]\s*/', '', $sample ) ?? $sample;
		$sample = preg_replace( '/^(?:PHP\s+)?(?:Fatal error|Parse error|Recoverable fatal error|Warning|Notice|Deprecated|Error):?\s*/i', '', $sample ) ?? $sample;
		$sample = preg_replace( '/\s+(?:in\s+\[(?:filesystem-path|plugins|wp-content|WordPress)\]|for query\s+\[database-query-redacted\]|made by\s+).*/i', '', $sample ) ?? $sample;
		$sample = trim( wp_strip_all_tags( $sample ) );

		if ( '' === $sample ) {
			return __( 'Technical event requiring review', 'presscare-ai-error-doctor' );
		}

		$sample = function_exists( 'mb_substr' ) ? mb_substr( $sample, 0, 140 ) : substr( $sample, 0, 140 );
		return ucfirst( rtrim( $sample, '. ' ) );
	}

	/**
	 * @param array<string,mixed> $group       Finding group.
	 * @param int                 $window_days Recent window length.
	 */
	private function finding_explanation( array $group, int $window_days ): string {
		$severity = sanitize_key( (string) ( $group['severity'] ?? 'info' ) );
		$recent   = (int) ( $group['recent_count'] ?? 0 );
		$undated  = (int) ( $group['undated_count'] ?? 0 );

		if ( $recent > 0 ) {
			$timing = sprintf(
				/* translators: 1: Number of recent occurrences, 2: Number of days in the recent window. */
				_n( '%1$d occurrence was recorded in the last %2$d days.', '%1$d occurrences were recorded in the last %2$d days.', $recent, 'presscare-ai-error-doctor' ),
				$recent,
				$window_days
			);
		} elseif ( $undated > 0 ) {
			$timing = __( 'The log does not provide a usable timestamp, so confirm whether this is still happening.', 'presscare-ai-error-doctor' );
		} else {
			$timing = __( 'This appears only in older log history and is not currently active in the seven-day review window.', 'presscare-ai-error-doctor' );
		}

		$impact = array(
			'critical' => __( 'Fatal errors can interrupt a request or background task.', 'presscare-ai-error-doctor' ),
			'error'    => __( 'Errors can prevent a database or application operation from completing.', 'presscare-ai-error-doctor' ),
			'warning'  => __( 'Warnings may not break the site, but repeated current warnings deserve investigation.', 'presscare-ai-error-doctor' ),
			'info'     => __( 'This is lower-priority diagnostic context.', 'presscare-ai-error-doctor' ),
		);

		return $timing . ' ' . ( $impact[ $severity ] ?? $impact['info'] );
	}

	/**
	 * @param array<string,mixed> $group Finding group.
	 * @return string[]
	 */
	private function resolution_steps( array $group ): array {
		$type           = sanitize_key( (string) ( $group['component_type'] ?? 'unknown' ) );
		$recent         = (int) ( $group['recent_count'] ?? 0 );
		$component_name = $this->component_name( $group );
		$steps          = array();

		if ( $recent > 0 ) {
			$steps[] = __( 'Reproduce the related action on staging and note exactly what the user or scheduled task was doing.', 'presscare-ai-error-doctor' );
		} else {
			$steps[] = __( 'Confirm the event has not returned recently. If the site is behaving normally, an old entry may require no repair.', 'presscare-ai-error-doctor' );
		}

		if ( 'plugin' === $type || 'theme' === $type ) {
			$steps[] = sprintf(
				/* translators: %s: Plugin or theme name inferred from the log path. */
				__( 'Back up first, then update or test %s on staging. If the event returns, share the finding ID with its developer.', 'presscare-ai-error-doctor' ),
				$component_name
			);
		} elseif ( 'core' === $type ) {
			$steps[] = __( 'Confirm WordPress and all extensions are current. A core file can be where a warning surfaced even when a plugin triggered it.', 'presscare-ai-error-doctor' );
		} else {
			$steps[] = __( 'Open Technical details and use callback names to identify the responsible extension. Back up the database before following any schema or table-repair instructions.', 'presscare-ai-error-doctor' );
		}

		$steps[] = __( 'Run another local scan after testing. If no newer occurrence appears, mark the finding handled to keep the action list focused.', 'presscare-ai-error-doctor' );

		return $steps;
	}

	/**
	 * @param array<string,mixed> $report Report data.
	 */
	private function render_ai_action( array $report ): void {
		$events             = (int) ( $report['summary']['events_total'] ?? 0 );
		$provider_available = $this->analyzer->is_available();
		?>
		<section id="pcaied-ai-action" class="pcaied-panel pcaied-ai-action">
			<div class="pcaied-ai-copy">
				<p class="pcaied-section-kicker"><?php esc_html_e( 'Step 3 · Optional PressCare AI explanation', 'presscare-ai-error-doctor' ); ?></p>
				<h2><?php esc_html_e( 'Get a focused explanation, not another wall of errors', 'presscare-ai-error-doctor' ); ?></h2>
				<p><?php esc_html_e( 'Choose “Ask PressCare AI” on any finding for focused guidance, or request one concise brief covering the complete sanitized report.', 'presscare-ai-error-doctor' ); ?></p>
				<div class="pcaied-status-row">
					<span class="pcaied-status pcaied-status-ready"><?php esc_html_e( 'Local report ready', 'presscare-ai-error-doctor' ); ?></span>
					<span class="pcaied-status <?php echo esc_attr( $provider_available ? 'pcaied-status-ready' : 'pcaied-status-needed' ); ?>">
						<?php echo esc_html( $provider_available ? __( 'AI provider ready', 'presscare-ai-error-doctor' ) : __( 'Provider setup needed', 'presscare-ai-error-doctor' ) ); ?>
					</span>
				</div>
				<p class="pcaied-provider-link"><a href="<?php echo esc_url( admin_url( 'options-connectors.php' ) ); ?>"><?php esc_html_e( 'Review providers in Settings → Connectors', 'presscare-ai-error-doctor' ); ?></a></p>
			</div>
			<form class="pcaied-ai-form-card" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="pcaied_analyze">
				<input id="pcaied-ai-fingerprint" type="hidden" name="pcaied_fingerprint" value="">
				<?php wp_nonce_field( 'pcaied_analyze' ); ?>
				<h3><?php esc_html_e( 'Request an AI explanation', 'presscare-ai-error-doctor' ); ?></h3>
				<div id="pcaied-ai-selection" class="pcaied-ai-selection" hidden>
					<span><?php esc_html_e( 'Focused on:', 'presscare-ai-error-doctor' ); ?></span>
					<strong id="pcaied-ai-selection-title"></strong>
					<button id="pcaied-ai-selection-clear" type="button"><?php esc_html_e( 'Use complete report instead', 'presscare-ai-error-doctor' ); ?></button>
				</div>
				<label class="pcaied-consent">
					<input type="checkbox" name="pcaied_ai_consent" value="1" required <?php disabled( 0 === $events ); ?>>
					<span><?php esc_html_e( 'I approve sending the selected sanitized evidence to the AI provider configured for this site.', 'presscare-ai-error-doctor' ); ?></span>
				</label>
				<button id="pcaied-ai-submit" type="submit" class="button button-primary pcaied-ai-button" data-default-label="<?php esc_attr_e( 'Explain complete report', 'presscare-ai-error-doctor' ); ?>" data-focused-label="<?php esc_attr_e( 'Explain selected finding', 'presscare-ai-error-doctor' ); ?>" <?php disabled( 0 === $events || ! $provider_available ); ?>><?php esc_html_e( 'Explain complete report', 'presscare-ai-error-doctor' ); ?></button>
				<?php if ( 0 === $events ) : ?>
					<p class="pcaied-form-help pcaied-form-help-needed"><?php esc_html_e( 'AI analysis becomes available when the local scan contains recognized findings.', 'presscare-ai-error-doctor' ); ?></p>
				<?php elseif ( ! $provider_available ) : ?>
					<p class="pcaied-form-help pcaied-form-help-needed"><?php esc_html_e( 'WordPress does not currently report a connected provider with text-generation support. Save the provider under Settings → Connectors, then refresh this page.', 'presscare-ai-error-doctor' ); ?></p>
				<?php else : ?>
					<p class="pcaied-form-help"><?php esc_html_e( 'Ready when you are. Focused requests use less evidence and are easier to act on.', 'presscare-ai-error-doctor' ); ?></p>
				<?php endif; ?>
				<p class="pcaied-cost-note"><?php esc_html_e( 'Local diagnostics are free. Your selected AI provider may charge for this optional request.', 'presscare-ai-error-doctor' ); ?></p>
			</form>
		</section>
		<?php
	}

	/**
	 * @param array<string,mixed> $analysis AI analysis.
	 */
	private function render_ai_report( array $analysis ): void {
		$severity   = sanitize_key( (string) ( $analysis['overall_severity'] ?? 'info' ) );
		$findings   = isset( $analysis['findings'] ) && is_array( $analysis['findings'] ) ? $analysis['findings'] : array();
		$is_focused = ! empty( $analysis['requested_fingerprint'] );
		/* translators: 1: AI confidence level, 2: Local diagnostic finding ID. */
		$confidence_label = __( 'Confidence: %1$s · Finding ID: %2$s', 'presscare-ai-error-doctor' );
		?>
		<section id="pcaied-ai-report" class="pcaied-panel pcaied-ai-report">
			<div class="pcaied-report-title">
				<p class="pcaied-eyebrow"><?php echo esc_html( $is_focused ? __( 'Focused AI explanation', 'presscare-ai-error-doctor' ) : __( 'AI action brief', 'presscare-ai-error-doctor' ) ); ?></p>
				<span class="pcaied-badge pcaied-<?php echo esc_attr( $severity ); ?>"><?php echo esc_html( ucfirst( $severity ) ); ?></span>
			</div>
			<h2><?php echo esc_html( (string) ( $analysis['summary'] ?? '' ) ); ?></h2>
			<div class="pcaied-ai-findings">
				<?php foreach ( $findings as $finding ) : ?>
					<article>
						<h3><?php echo esc_html( (string) ( $finding['title'] ?? '' ) ); ?></h3>
						<p><?php echo esc_html( (string) ( $finding['explanation'] ?? '' ) ); ?></p>
						<p><strong><?php esc_html_e( 'Likely cause:', 'presscare-ai-error-doctor' ); ?></strong> <?php echo esc_html( (string) ( $finding['likely_cause'] ?? '' ) ); ?></p>
						<?php if ( ! empty( $finding['recommended_steps'] ) && is_array( $finding['recommended_steps'] ) ) : ?>
							<ol>
								<?php foreach ( $finding['recommended_steps'] as $step ) : ?>
									<li><?php echo esc_html( (string) $step ); ?></li>
								<?php endforeach; ?>
							</ol>
						<?php endif; ?>
						<small><?php echo esc_html( sprintf( $confidence_label, (string) ( $finding['confidence'] ?? 'low' ), (string) ( $finding['fingerprint'] ?? '' ) ) ); ?></small>
					</article>
				<?php endforeach; ?>
			</div>
		</section>
		<?php
	}

	private function render_presscare_ai_promo(): void {
		?>
		<section class="pcaied-presscare-ai">
			<div class="pcaied-presscare-orbit" aria-hidden="true"><span>AI</span></div>
			<div class="pcaied-presscare-copy">
				<p class="pcaied-eyebrow"><?php esc_html_e( 'More from PressCare AI', 'presscare-ai-error-doctor' ); ?></p>
				<h2><?php esc_html_e( 'Practical AI tools for people who keep WordPress running', 'presscare-ai-error-doctor' ); ?></h2>
				<p><?php esc_html_e( 'AI Error Doctor is the beginning. PressCare is actively developing more privacy-conscious AI tools for diagnostics, maintenance, and everyday WordPress operations.', 'presscare-ai-error-doctor' ); ?></p>
				<div class="pcaied-roadmap-row">
					<span><?php esc_html_e( 'Clear evidence', 'presscare-ai-error-doctor' ); ?></span>
					<span><?php esc_html_e( 'Safer workflows', 'presscare-ai-error-doctor' ); ?></span>
					<span><?php esc_html_e( 'Human-first guidance', 'presscare-ai-error-doctor' ); ?></span>
				</div>
			</div>
			<a class="pcaied-presscare-link" href="<?php echo esc_url( 'https://presscare.com/' ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Explore PressCare', 'presscare-ai-error-doctor' ); ?><span aria-hidden="true">→</span></a>
		</section>
		<?php
	}

	private function metric( string $label, int $value, string $severity, string $context ): void {
		?>
		<div class="pcaied-metric pcaied-metric-<?php echo esc_attr( $severity ); ?>">
			<strong><?php echo esc_html( (string) $value ); ?></strong>
			<span><?php echo esc_html( $label ); ?></span>
			<small><?php echo esc_html( $context ); ?></small>
		</div>
		<?php
	}

	private function format_timestamp( mixed $value, bool $date_only = false ): string {
		$timestamp = $this->normalize_timestamp( $value );
		if ( null === $timestamp ) {
			return __( 'Not available', 'presscare-ai-error-doctor' );
		}

		return wp_date( $date_only ? 'M j, Y' : 'M j, Y · g:i a T', $timestamp );
	}

	private function format_date_range( mixed $oldest, mixed $newest ): string {
		$oldest_timestamp = $this->normalize_timestamp( $oldest );
		$newest_timestamp = $this->normalize_timestamp( $newest );

		if ( null === $oldest_timestamp || null === $newest_timestamp ) {
			return __( 'No usable timestamps', 'presscare-ai-error-doctor' );
		}

		$oldest_label = wp_date( 'M j, Y', $oldest_timestamp );
		$newest_label = wp_date( 'M j, Y', $newest_timestamp );

		return $oldest_label === $newest_label ? $oldest_label : $oldest_label . ' → ' . $newest_label;
	}

	private function normalize_timestamp( mixed $value ): ?int {
		if ( is_int( $value ) && $value > 0 ) {
			return $value;
		}

		if ( is_numeric( $value ) && (int) $value > 0 ) {
			return (int) $value;
		}

		if ( is_string( $value ) && '' !== $value ) {
			$timestamp = strtotime( $value );
			return false === $timestamp ? null : $timestamp;
		}

		return null;
	}

	private function action_form( string $action, string $label, string $css_class ): void {
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="<?php echo esc_attr( $action ); ?>">
			<?php wp_nonce_field( $action ); ?>
			<button type="submit" class="<?php echo esc_attr( $css_class ); ?>"><?php echo esc_html( $label ); ?></button>
		</form>
		<?php
	}

	/**
	 * Applies the current privacy rules to reports saved by earlier versions.
	 *
	 * @param array<string,mixed> $report   Saved report.
	 * @param string              $meta_key User-meta key.
	 * @return array<string,mixed>
	 */
	private function sanitize_saved_report( array $report, string $meta_key ): array {
		$sanitized = $this->redactor->redact_value( $report );
		$sanitized = is_array( $sanitized ) ? $sanitized : array();

		if ( $sanitized !== $report ) {
			update_user_meta( get_current_user_id(), $meta_key, $sanitized );
		}

		return $sanitized;
	}

	/**
	 * @param array<string,mixed> $report      Diagnostic report.
	 * @param string              $fingerprint Selected finding fingerprint.
	 * @return array<string,mixed>
	 */
	private function focus_report( array $report, string $fingerprint ): array {
		$groups = isset( $report['groups'] ) && is_array( $report['groups'] ) ? $report['groups'] : array();
		$match  = null;

		foreach ( $groups as $group ) {
			if ( is_array( $group ) && sanitize_key( (string) ( $group['fingerprint'] ?? '' ) ) === $fingerprint ) {
				$match = $group;
				break;
			}
		}

		if ( null === $match ) {
			return array();
		}

		$severity = sanitize_key( (string) ( $match['severity'] ?? 'info' ) );
		$counts   = array_fill_keys( array( 'critical', 'error', 'warning', 'info' ), 0 );
		if ( isset( $counts[ $severity ] ) ) {
			$counts[ $severity ] = (int) ( $match['count'] ?? 0 );
		}

		$report['groups']                             = array( $match );
		$report['summary']['events_total']            = (int) ( $match['count'] ?? 0 );
		$report['summary']['groups_total']            = 1;
		$report['summary']['counts']                  = $counts;
		$report['summary']['recent_events_total']     = (int) ( $match['recent_count'] ?? 0 );
		$report['summary']['historical_events_total'] = (int) ( $match['historical_count'] ?? 0 );
		$report['summary']['undated_events_total']    = (int) ( $match['undated_count'] ?? 0 );
		$report['summary']['oldest_seen']             = $match['first_seen'] ?? null;
		$report['summary']['newest_seen']             = $match['last_seen'] ?? null;

		return $report;
	}

	/**
	 * @param array<string,mixed> $report      Diagnostic report.
	 * @param string              $fingerprint Finding fingerprint.
	 */
	private function report_has_fingerprint( array $report, string $fingerprint ): bool {
		if ( '' === $fingerprint || ! isset( $report['groups'] ) || ! is_array( $report['groups'] ) ) {
			return false;
		}

		foreach ( $report['groups'] as $group ) {
			if ( is_array( $group ) && sanitize_key( (string) ( $group['fingerprint'] ?? '' ) ) === $fingerprint ) {
				return true;
			}
		}

		return false;
	}

	private function authorize(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to use PressCare AI Error Doctor.', 'presscare-ai-error-doctor' ), '', array( 'response' => 403 ) );
		}
	}

	private function redirect_with_notice( string $type, string $message, string $anchor = '' ): void {
		set_transient(
			'pcaied_notice_' . get_current_user_id(),
			array(
				'type'    => in_array( $type, array( 'success', 'error', 'warning', 'info' ), true ) ? $type : 'info',
				'message' => $message,
			),
			MINUTE_IN_SECONDS
		);

		$url = admin_url( 'tools.php?page=' . self::PAGE_SLUG );
		if ( '' !== $anchor ) {
			$url .= '#' . sanitize_html_class( $anchor );
		}

		wp_safe_redirect( $url );
		exit;
	}
}
