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

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class AdminPage {
	private const PAGE_SLUG   = 'presscare-ai-error-doctor';
	private const REPORT_META = '_pcaied_last_report';
	private const AI_META     = '_pcaied_last_ai_report';

	private DiagnosticEngine $engine;
	private Analyzer $analyzer;

	public function __construct( DiagnosticEngine $engine, Analyzer $analyzer ) {
		$this->engine   = $engine;
		$this->analyzer = $analyzer;
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

		$report    = is_array( $report ) ? $report : array();
		$ai_report = is_array( $ai_report ) ? $ai_report : array();
		?>
		<div class="wrap pcaied-wrap">
			<section class="pcaied-hero">
				<div>
					<p class="pcaied-eyebrow"><?php esc_html_e( 'PressCare diagnostics', 'presscare-ai-error-doctor' ); ?></p>
					<h1><?php esc_html_e( 'AI Error Doctor', 'presscare-ai-error-doctor' ); ?></h1>
					<p><?php esc_html_e( 'Group noisy WordPress errors, remove sensitive data, and ask your connected AI provider for a cautious explanation.', 'presscare-ai-error-doctor' ); ?></p>
				</div>
				<div class="pcaied-readonly"><?php esc_html_e( 'Read-only by design', 'presscare-ai-error-doctor' ); ?></div>
			</section>

			<?php if ( is_array( $notice ) && isset( $notice['type'], $notice['message'] ) ) : ?>
				<div class="notice notice-<?php echo esc_attr( $notice['type'] ); ?> is-dismissible"><p><?php echo esc_html( $notice['message'] ); ?></p></div>
			<?php endif; ?>

			<section class="pcaied-actions pcaied-panel">
				<div>
					<h2><?php esc_html_e( 'Local diagnostic scan', 'presscare-ai-error-doctor' ); ?></h2>
					<p><?php esc_html_e( 'Reads only the final 2 MB of the configured log, groups similar events, and stores sanitized samples for your account.', 'presscare-ai-error-doctor' ); ?></p>
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
					<h2><?php esc_html_e( 'No report yet', 'presscare-ai-error-doctor' ); ?></h2>
					<p><?php esc_html_e( 'Run the local scan first. No diagnostic data is sent to an AI provider during a local scan.', 'presscare-ai-error-doctor' ); ?></p>
				</section>
			<?php endif; ?>

			<?php if ( $ai_report ) : ?>
				<?php $this->render_ai_report( $ai_report ); ?>
			<?php endif; ?>

			<footer class="pcaied-footer">
				<p><?php esc_html_e( 'Raw log contents are never stored by this plugin. AI analysis occurs only after explicit approval and sends the sanitized grouped report to the provider configured in WordPress.', 'presscare-ai-error-doctor' ); ?></p>
				<a href="<?php echo esc_url( 'https://presscare.com/contact/' ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'PressCare support', 'presscare-ai-error-doctor' ); ?></a>
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
		$counts  = $report['summary']['counts'] ?? array();
		$summary = $report['summary'] ?? array();
		?>
		<section class="pcaied-metrics" aria-label="<?php esc_attr_e( 'Diagnostic summary', 'presscare-ai-error-doctor' ); ?>">
			<?php
			$this->metric( __( 'Critical', 'presscare-ai-error-doctor' ), (int) ( $counts['critical'] ?? 0 ), 'critical' );
			$this->metric( __( 'Errors', 'presscare-ai-error-doctor' ), (int) ( $counts['error'] ?? 0 ), 'error' );
			$this->metric( __( 'Warnings', 'presscare-ai-error-doctor' ), (int) ( $counts['warning'] ?? 0 ), 'warning' );
			$this->metric( __( 'Grouped issues', 'presscare-ai-error-doctor' ), (int) ( $summary['groups_total'] ?? 0 ), 'info' );
			?>
		</section>
		<section class="pcaied-panel pcaied-meta">
			<div><strong><?php esc_html_e( 'Generated', 'presscare-ai-error-doctor' ); ?></strong><span><?php echo esc_html( (string) ( $report['generated_at'] ?? '' ) ); ?></span></div>
			<div><strong><?php esc_html_e( 'Log source', 'presscare-ai-error-doctor' ); ?></strong><span><?php echo esc_html( (string) ( $report['log']['source'] ?? '' ) ); ?></span></div>
			<div><strong><?php esc_html_e( 'Data read', 'presscare-ai-error-doctor' ); ?></strong><span><?php echo esc_html( size_format( (int) ( $report['log']['bytes_read'] ?? 0 ) ) ); ?></span></div>
			<div><strong><?php esc_html_e( 'WordPress / PHP', 'presscare-ai-error-doctor' ); ?></strong><span><?php echo esc_html( (string) ( $report['environment']['wordpress_version'] ?? '' ) . ' / ' . (string) ( $report['environment']['php_version'] ?? '' ) ); ?></span></div>
		</section>
		<?php
	}

	/**
	 * @param array<string,mixed> $report Report data.
	 */
	private function render_groups( array $report ): void {
		$groups = isset( $report['groups'] ) && is_array( $report['groups'] ) ? $report['groups'] : array();
		?>
		<section class="pcaied-panel">
			<h2><?php esc_html_e( 'Grouped log findings', 'presscare-ai-error-doctor' ); ?></h2>
			<?php if ( ! $groups ) : ?>
				<p class="pcaied-good"><?php esc_html_e( 'No recognized PHP or WordPress error events were found in the analyzed portion of the log.', 'presscare-ai-error-doctor' ); ?></p>
			<?php else : ?>
				<div class="pcaied-findings">
					<?php foreach ( $groups as $group ) : ?>
						<article class="pcaied-finding">
							<div class="pcaied-finding-heading">
								<span class="pcaied-badge pcaied-<?php echo esc_attr( sanitize_key( (string) ( $group['severity'] ?? 'info' ) ) ); ?>"><?php echo esc_html( ucfirst( (string) ( $group['severity'] ?? 'info' ) ) ); ?></span>
								<strong><?php echo esc_html( (string) ( $group['component_type'] ?? 'unknown' ) . ': ' . (string) ( $group['component_slug'] ?? 'unknown' ) ); ?></strong>
								<span><?php echo esc_html( sprintf( _n( '%d occurrence', '%d occurrences', (int) ( $group['count'] ?? 0 ), 'presscare-ai-error-doctor' ), (int) ( $group['count'] ?? 0 ) ) ); ?></span>
							</div>
							<pre><?php echo esc_html( (string) ( $group['sample'] ?? '' ) ); ?></pre>
							<small><?php echo esc_html( 'ID ' . (string) ( $group['fingerprint'] ?? '' ) ); ?></small>
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
		$events = (int) ( $report['summary']['events_total'] ?? 0 );
		?>
		<section class="pcaied-panel pcaied-ai-action">
			<div>
				<p class="pcaied-eyebrow"><?php esc_html_e( 'Optional AI explanation', 'presscare-ai-error-doctor' ); ?></p>
				<h2><?php esc_html_e( 'Ask your connected provider to interpret the findings', 'presscare-ai-error-doctor' ); ?></h2>
				<p><?php esc_html_e( 'Only the environment summary and sanitized grouped samples shown above are sent. The raw log and API credentials are not sent by this plugin.', 'presscare-ai-error-doctor' ); ?></p>
				<p><a href="<?php echo esc_url( admin_url( 'options-connectors.php' ) ); ?>"><?php esc_html_e( 'Manage AI providers in Settings > Connectors', 'presscare-ai-error-doctor' ); ?></a></p>
			</div>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="pcaied_analyze">
				<?php wp_nonce_field( 'pcaied_analyze' ); ?>
				<label class="pcaied-consent">
					<input type="checkbox" name="pcaied_ai_consent" value="1" required <?php disabled( 0 === $events ); ?>>
					<span><?php esc_html_e( 'I approve sending this sanitized report to my configured AI provider.', 'presscare-ai-error-doctor' ); ?></span>
				</label>
				<button type="submit" class="button button-primary" <?php disabled( 0 === $events || ! $this->analyzer->is_available() ); ?>><?php esc_html_e( 'Explain with AI', 'presscare-ai-error-doctor' ); ?></button>
				<?php if ( ! $this->analyzer->is_available() ) : ?>
					<p class="description"><?php esc_html_e( 'Connect and activate a compatible provider before requesting analysis.', 'presscare-ai-error-doctor' ); ?></p>
				<?php endif; ?>
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
						<small><?php echo esc_html( sprintf( __( 'Confidence: %1$s · Finding ID: %2$s', 'presscare-ai-error-doctor' ), (string) ( $finding['confidence'] ?? 'low' ), (string) ( $finding['fingerprint'] ?? '' ) ) ); ?></small>
					</article>
				<?php endforeach; ?>
			</div>
		</section>
		<?php
	}

	private function metric( string $label, int $value, string $severity ): void {
		?>
		<div class="pcaied-metric pcaied-metric-<?php echo esc_attr( $severity ); ?>">
			<strong><?php echo esc_html( (string) $value ); ?></strong>
			<span><?php echo esc_html( $label ); ?></span>
		</div>
		<?php
	}

	private function action_form( string $action, string $label, string $class ): void {
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="<?php echo esc_attr( $action ); ?>">
			<?php wp_nonce_field( $action ); ?>
			<button type="submit" class="<?php echo esc_attr( $class ); ?>"><?php echo esc_html( $label ); ?></button>
		</form>
		<?php
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
