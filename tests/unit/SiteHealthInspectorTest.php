<?php
/**
 * Read-only site health inspector tests.
 *
 * @package PressCareAIErrorDoctor
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use PressCare\AIErrorDoctor\Diagnostics\SiteHealthInspector;

final class SiteHealthInspectorTest extends TestCase {
	/** @var mixed */
	private $previous_wpdb;

	protected function setUp(): void {
		global $wpdb;

		$this->previous_wpdb = $wpdb ?? null;
		$wpdb                = new PressCareTestDatabase();
		$_SERVER['SERVER_SOFTWARE'] = 'nginx/1.26';
	}

	protected function tearDown(): void {
		global $wpdb;

		$wpdb = $this->previous_wpdb;
		unset( $_SERVER['SERVER_SOFTWARE'] );
	}

	public function test_collects_bounded_database_totals_without_option_values(): void {
		$health = ( new SiteHealthInspector() )->inspect();

		self::assertSame( '7.0', $health['wordpress_version'] );
		self::assertSame( '8.0.36', $health['database_version'] );
		self::assertSame( 'nginx/1.26', $health['web_server'] );
		self::assertSame( 42, $health['transients']['count'] );
		self::assertSame( 4096, $health['transients']['size_bytes'] );
		self::assertSame( 3, $health['transients']['expired_count'] );
		self::assertSame( 620, $health['autoload']['count'] );
		self::assertSame( 920000, $health['autoload']['size_bytes'] );
		self::assertSame( 'review', $health['autoload']['status'] );
		self::assertSame( 'woocommerce_helper_data', $health['autoload']['largest'][0]['name'] );
		self::assertArrayNotHasKey( 'value', $health['autoload']['largest'][0] );
	}
}

final class PressCareTestDatabase {
	public string $options = 'wp_options';

	public function db_version(): string {
		return '8.0.36';
	}

	public function esc_like( string $value ): string {
		return addcslashes( $value, '_%\\' );
	}

	/**
	 * @param mixed ...$values Prepared values.
	 */
	public function prepare( string $query, mixed ...$values ): string {
		unset( $values );
		return $query;
	}

	/**
	 * @return array<string,int>
	 */
	public function get_row( string $query, string $format ): array {
		unset( $format );

		if ( str_contains( $query, 'transient_count' ) ) {
			return array(
				'transient_count' => 42,
				'transient_bytes' => 4096,
			);
		}

		return array(
			'option_count' => 620,
			'option_bytes' => 920000,
		);
	}

	public function get_var( string $query ): int {
		unset( $query );
		return 3;
	}

	/**
	 * @return array<int,array<string,int|string>>
	 */
	public function get_results( string $query, string $format ): array {
		unset( $query, $format );

		return array(
			array(
				'option_name'  => 'woocommerce_helper_data',
				'option_bytes' => 320000,
			),
			array(
				'option_name'  => 'rewrite_rules',
				'option_bytes' => 120000,
			),
		);
	}
}
