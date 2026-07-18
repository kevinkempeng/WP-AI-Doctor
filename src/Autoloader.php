<?php
/**
 * Internal class autoloader.
 *
 * @package PressCareAIErrorDoctor
 */

declare(strict_types=1);

namespace PressCare\AIErrorDoctor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Autoloader {
	private const PREFIX = 'PressCare\\AIErrorDoctor\\';

	public static function register(): void {
		spl_autoload_register( array( self::class, 'load' ) );
	}

	private static function load( string $class ): void {
		if ( ! str_starts_with( $class, self::PREFIX ) ) {
			return;
		}

		$relative = substr( $class, strlen( self::PREFIX ) );
		$file     = PCAIED_DIR . '/src/' . str_replace( '\\', '/', $relative ) . '.php';

		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
}

