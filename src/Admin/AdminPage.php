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
	private const PAGE_SLUG   = 'presscare-ai-error-doctor';
	private const REPORT_META = '_pcaied_last_report';
	private const AI_META     = '_pcaied_last_ai_report';

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
				<?php $this->render_groups( $report ); ?>
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
		$report = $this->sanitize_saved_report( $report, self::REPORT_META );

		$analysis = $this->analyzer->analyze( $report );
		if ( is_wp_error( $analysis ) ) {
			$this->redirect_with_notice( 'error', $analysis->get_error_message() );
		}

		update_user_meta( get_current_user_id(), self::AI_META, $analysis );
		$this->redirect_with_notice( 'success', __( 'The AI explanation is ready.', 'presscare-ai-error-doctor' ) );
	}

	public function handle_clear(): void {
		$this->authorize();
		check_admin_referer( 'pcaied_clear' );
		delete_user_meta( get_current_user_id(), self::REPORT_META );
		delete_user_meta( get_current_user_id(), self::AI_META );
		$this->redirect_with_notice( 'success', __( 'The stored diagnostic reports were removed.', 'presscare-ai-error-doctor' ) );
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
	 * @param array<string,mixed> $report Report data.
	 */
	private function render_groups( array $report ): void {
		$groups = isset( $report['groups'] ) && is_array( $report['groups'] ) ? $report['groups'] : array();
		?>
		<section class="pcaied-panel pcaied-findings-panel">
			<div class="pcaied-section-heading">
				<div>
					<p class="pcaied-section-kicker"><?php esc_html_e( 'Step 2 · Review the evidence', 'presscare-ai-error-doctor' ); ?></p>
					<h2><?php esc_html_e( 'Grouped findings', 'presscare-ai-error-doctor' ); ?></h2>
					<p><?php esc_html_e( 'Repeated entries are combined so you can see the signal without reading the same warning hundreds of times.', 'presscare-ai-error-doctor' ); ?></p>
				</div>
				<span class="pcaied-group-total">
					<?php
					/* translators: %d: Number of grouped findings. */
					echo esc_html( sprintf( _n( '%d group', '%d groups', count( $groups ), 'presscare-ai-error-doctor' ), count( $groups ) ) );
					?>
				</span>
			</div>
			<?php if ( ! $groups ) : ?>
				<p class="pcaied-good"><?php esc_html_e( 'No recognized PHP or WordPress error events were found in the analyzed portion of the log.', 'presscare-ai-error-doctor' ); ?></p>
			<?php else : ?>
				<div class="pcaied-findings">
					<?php foreach ( $groups as $group ) : ?>
						<?php
						$occurrence_count = (int) ( $group['count'] ?? 0 );
						$occurrence_label = sprintf(
							/* translators: %d: Number of occurrences for the grouped error. */
							_n( '%d occurrence', '%d occurrences', $occurrence_count, 'presscare-ai-error-doctor' ),
							$occurrence_count
						);
						$recent_count     = (int) ( $group['recent_count'] ?? 0 );
						$historical_count = (int) ( $group['historical_count'] ?? 0 );
						$undated_count    = (int) ( $group['undated_count'] ?? 0 );
						/* translators: %d: Number of occurrences from the last seven days. */
						$recent_label = sprintf( __( '%d recent', 'presscare-ai-error-doctor' ), $recent_count );
						/* translators: %d: Number of older occurrences. */
						$historical_label = sprintf( __( '%d older', 'presscare-ai-error-doctor' ), $historical_count );
						/* translators: %d: Number of occurrences without a usable timestamp. */
						$undated_label = sprintf( __( '%d undated', 'presscare-ai-error-doctor' ), $undated_count );
						/* translators: %s: Date of the first occurrence. */
						$first_seen_label = sprintf( __( 'First: %s', 'presscare-ai-error-doctor' ), $this->format_timestamp( $group['first_seen'] ?? null, true ) );
						/* translators: %s: Date of the most recent occurrence. */
						$last_seen_label = sprintf( __( 'Last: %s', 'presscare-ai-error-doctor' ), $this->format_timestamp( $group['last_seen'] ?? null, true ) );
						?>
						<article class="pcaied-finding">
							<div class="pcaied-finding-heading">
								<div class="pcaied-finding-identity">
									<span class="pcaied-badge pcaied-<?php echo esc_attr( sanitize_key( (string) ( $group['severity'] ?? 'info' ) ) ); ?>"><?php echo esc_html( ucfirst( (string) ( $group['severity'] ?? 'info' ) ) ); ?></span>
									<strong><?php echo esc_html( ucfirst( (string) ( $group['component_type'] ?? 'unknown' ) ) . ' · ' . (string) ( $group['component_slug'] ?? 'unknown' ) ); ?></strong>
								</div>
								<span class="pcaied-occurrences"><?php echo esc_html( $occurrence_label ); ?></span>
							</div>
							<div class="pcaied-finding-timing">
								<?php
								if ( $recent_count > 0 ) :
									?>
								<span class="pcaied-timing-recent"><?php echo esc_html( $recent_label ); ?></span><?php endif; ?>
								<?php
								if ( $historical_count > 0 ) :
									?>
								<span><?php echo esc_html( $historical_label ); ?></span><?php endif; ?>
								<?php
								if ( $undated_count > 0 ) :
									?>
								<span><?php echo esc_html( $undated_label ); ?></span><?php endif; ?>
								<span><?php echo esc_html( $first_seen_label ); ?></span>
								<span><?php echo esc_html( $last_seen_label ); ?></span>
							</div>
							<pre><?php echo esc_html( (string) ( $group['sample'] ?? '' ) ); ?></pre>
							<small class="pcaied-fingerprint"><?php echo esc_html( 'Finding ID ' . (string) ( $group['fingerprint'] ?? '' ) ); ?></small>
						</article>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</section>
		<?php
	}

	/**
	 * @param array<string,mixed> $report Report data.
	 */
	private function render_ai_action( array $report ): void {
		$events             = (int) ( $report['summary']['events_total'] ?? 0 );
		$provider_available = $this->analyzer->is_available();
		?>
		<section class="pcaied-panel pcaied-ai-action">
			<div class="pcaied-ai-copy">
				<p class="pcaied-section-kicker"><?php esc_html_e( 'Step 3 · Optional PressCare AI explanation', 'presscare-ai-error-doctor' ); ?></p>
				<h2><?php esc_html_e( 'Turn the sanitized findings into a practical next-step brief', 'presscare-ai-error-doctor' ); ?></h2>
				<p><?php esc_html_e( 'Your connected provider receives only the environment summary and sanitized groups shown in this report. Raw logs and provider credentials are never sent by AI Error Doctor.', 'presscare-ai-error-doctor' ); ?></p>
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
				<?php wp_nonce_field( 'pcaied_analyze' ); ?>
				<h3><?php esc_html_e( 'Request an AI explanation', 'presscare-ai-error-doctor' ); ?></h3>
				<label class="pcaied-consent">
					<input type="checkbox" name="pcaied_ai_consent" value="1" required <?php disabled( 0 === $events ); ?>>
					<span><?php esc_html_e( 'I approve sending this sanitized report to the AI provider configured for this site.', 'presscare-ai-error-doctor' ); ?></span>
				</label>
				<button type="submit" class="button button-primary pcaied-ai-button" <?php disabled( 0 === $events || ! $provider_available ); ?>><?php esc_html_e( 'Create AI explanation', 'presscare-ai-error-doctor' ); ?></button>
				<?php if ( 0 === $events ) : ?>
					<p class="pcaied-form-help pcaied-form-help-needed"><?php esc_html_e( 'AI analysis becomes available when the local scan contains recognized findings.', 'presscare-ai-error-doctor' ); ?></p>
				<?php elseif ( ! $provider_available ) : ?>
					<p class="pcaied-form-help pcaied-form-help-needed"><?php esc_html_e( 'WordPress does not currently report a connected provider with text-generation support. Save the provider under Settings → Connectors, then refresh this page.', 'presscare-ai-error-doctor' ); ?></p>
				<?php else : ?>
					<p class="pcaied-form-help"><?php esc_html_e( 'Ready when you are. The response will appear below this section.', 'presscare-ai-error-doctor' ); ?></p>
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
		$severity = sanitize_key( (string) ( $analysis['overall_severity'] ?? 'info' ) );
		$findings = isset( $analysis['findings'] ) && is_array( $analysis['findings'] ) ? $analysis['findings'] : array();
		/* translators: 1: AI confidence level, 2: Local diagnostic finding ID. */
		$confidence_label = __( 'Confidence: %1$s · Finding ID: %2$s', 'presscare-ai-error-doctor' );
		?>
		<section class="pcaied-panel pcaied-ai-report">
			<div class="pcaied-report-title">
				<p class="pcaied-eyebrow"><?php esc_html_e( 'AI explanation', 'presscare-ai-error-doctor' ); ?></p>
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

	private function authorize(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to use PressCare AI Error Doctor.', 'presscare-ai-error-doctor' ), '', array( 'response' => 403 ) );
		}
	}

	private function redirect_with_notice( string $type, string $message ): void {
		set_transient(
			'pcaied_notice_' . get_current_user_id(),
			array(
				'type'    => in_array( $type, array( 'success', 'error', 'warning', 'info' ), true ) ? $type : 'info',
				'message' => $message,
			),
			MINUTE_IN_SECONDS
		);

		wp_safe_redirect( admin_url( 'tools.php?page=' . self::PAGE_SLUG ) );
		exit;
	}
}
