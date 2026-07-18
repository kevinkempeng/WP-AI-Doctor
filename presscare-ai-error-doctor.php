<?php
/**
 * Plugin Name: PressCare AI Error Doctor
 * Plugin URI: https://github.com/kevinkempeng/WP-AI-Doctor
 * Description: Read-only WordPress error diagnostics with privacy-first, provider-independent AI explanations.
 * Version: 1.1.2
 * Requires at least: 7.0
 * Requires PHP: 8.0
 * Author: Kevin Kemp - PressCare
 * Author URI: https://presscare.com
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: presscare-ai-error-doctor
 * Domain Path: /languages
 *
 * @package PressCareAIErrorDoctor
 */

declare(strict_types=1);

namespace PressCare\AIErrorDoctor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'PCAIED_VERSION', '1.1.2' );
define( 'PCAIED_FILE', __FILE__ );
define( 'PCAIED_DIR', __DIR__ );
define( 'PCAIED_URL', plugin_dir_url( __FILE__ ) );

require_once PCAIED_DIR . '/src/Autoloader.php';

Autoloader::register();

add_action(
	'plugins_loaded',
	static function (): void {
		Plugin::instance()->boot();
	}
);
