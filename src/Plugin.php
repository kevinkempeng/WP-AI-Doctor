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
use PressCare\AIErrorDoctor\Diagnostics\SiteHealthInspector;
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
		$health   = new SiteHealthInspector();
		$engine   = new DiagnosticEngine(
			new LogLocator(),
			new LogParser( $redactor ),
			$redactor,
			$health
		);

		$admin = new AdminPage( $engine, new Analyzer( $redactor ), $redactor, $health );
		$admin->register_hooks();

		$policy = new Policy();
		$policy->register_hooks();
	}

	private function __construct() {}
}
