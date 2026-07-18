<?php
/**
 * Plugin composition root.
 *
 * @package PressCareAIErrorDoctor
 */

declare(strict_types=1);

namespace PressCare\AIErrorDoctor;

use PressCare\AIErrorDoctor\Admin\AdminPage;
use PressCare\AIErrorDoctor\AI\Analyzer;
use PressCare\AIErrorDoctor\Diagnostics\DiagnosticEngine;
use PressCare\AIErrorDoctor\Diagnostics\LogLocator;
use PressCare\AIErrorDoctor\Diagnostics\LogParser;
use PressCare\AIErrorDoctor\Privacy\Policy;
use PressCare\AIErrorDoctor\Privacy\Redactor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Plugin {
	private static ?self $instance = null;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function boot(): void {
		$redactor = new Redactor();
		$engine   = new DiagnosticEngine(
			new LogLocator(),
			new LogParser( $redactor ),
			$redactor
		);

		$admin = new AdminPage( $engine, new Analyzer( $redactor ), $redactor );
		$admin->register_hooks();

		$policy = new Policy();
		$policy->register_hooks();
	}

	private function __construct() {}
}
