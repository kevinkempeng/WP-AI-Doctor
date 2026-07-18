<?php
/**
 * Suggested site privacy-policy content.
 *
 * @package PressCareAIErrorDoctor
 */

declare(strict_types=1);

namespace PressCare\AIErrorDoctor\Privacy;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Policy {
	public function register_hooks(): void {
		add_action( 'admin_init', array( $this, 'add_policy_content' ) );
	}

	public function add_policy_content(): void {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}

		$content = '<p class="privacy-policy-tutorial">'
			. esc_html__( 'This suggested text describes the optional AI feature. Review and adapt it for the AI provider configured on your site.', 'presscare-ai-error-doctor' )
			. '</p>';

		$content .= '<strong class="privacy-policy-tutorial">'
			. esc_html__( 'Suggested text:', 'presscare-ai-error-doctor' )
			. '</strong>';

		$content .= '<p>'
			. esc_html__( 'When an administrator runs a local diagnostic scan, PressCare AI Error Doctor reads a bounded portion of a configured server error log and stores a sanitized, grouped report in that administrator’s user metadata. Raw log contents are not stored by the plugin.', 'presscare-ai-error-doctor' )
			. '</p>';

		$content .= '<p>'
			. esc_html__( 'If an administrator explicitly approves an AI analysis request, the plugin sends the sanitized report to the AI provider configured in WordPress. The report may include WordPress and PHP versions, active theme details, plugin and update counts, error severity counts, component slugs, fingerprints, occurrence counts, and redacted error samples. The selected provider may retain or process that data under its own terms and privacy policy.', 'presscare-ai-error-doctor' )
			. '</p>';

		$content .= '<p>'
			. esc_html__( 'The stored local and AI reports remain in the administrator’s user metadata until that administrator clears the report or the plugin is uninstalled. Site administrators should review the privacy terms of their configured AI provider and should not enable AI analysis if their policies prohibit sending diagnostic data to that provider.', 'presscare-ai-error-doctor' )
			. '</p>';

		wp_add_privacy_policy_content(
			__( 'PressCare AI Error Doctor', 'presscare-ai-error-doctor' ),
			wp_kses_post( $content )
		);
	}
}
