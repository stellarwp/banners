<?php declare(strict_types=1);

namespace Nexcess\Banners\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use Nexcess\Banners\Banner;
use Nexcess\Banners\BannerFields;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;

abstract class TestCase extends PHPUnitTestCase {

	protected const USER_ID = 7;

	/**
	 * Where a plugin serves its copy of the package.
	 */
	protected const PACKAGE_URL = 'https://example.test/wp-content/plugins/my-plugin/vendor/stellarwp/banners/';

	/**
	 * @param array<string, string> $values Keyed by BannerFields property name.
	 */
	protected static function fields( array $values ): BannerFields {
		$fields = new BannerFields();

		foreach ( $values as $property => $value ) {
			// A misspelled name would add a property the template never reads.
			self::assertTrue( property_exists( $fields, $property ), sprintf( 'BannerFields has no "%s".', $property ) );
			$fields->{$property} = $value;
		}

		return $fields;
	}

	protected function setUp(): void {
		parent::setUp();

		Monkey\setUp();

		Functions\stubEscapeFunctions();
		Functions\stubTranslationFunctions();
		$this->stubStyles();
		Functions\when( 'get_current_user_id' )->justReturn( self::USER_ID );
		// A logged-in user in wp-admin; the tests of the other cases override these.
		Functions\when( 'is_admin' )->justReturn( true );
		Functions\when( 'is_user_logged_in' )->justReturn( true );
	}

	protected function tearDown(): void {
		unset( $_GET['nx-banners-dismiss'], $_GET['nx-banners-nonce'], $GLOBALS['nx_banners'] );

		// Brain\Monkey expectations are Mockery assertions; count them so tests that only assert
		// through expect() are not reported as risky.
		$container = Mockery::getContainer();

		if ( $container !== null ) {
			$this->addToAssertionCount( $container->mockery_getExpectationCount() );
		}

		Monkey\tearDown();

		parent::tearDown();
	}

	/**
	 * Stub the current user's stored dismissals.
	 *
	 * @param array<string, int> $value When each banner was dismissed, keyed by banner ID.
	 */
	protected function stubDismissed( array $value ): void {
		Functions\when( 'get_user_meta' )->alias(
			static function ( int $userId, string $key ) use ( $value ) {
				return $userId === self::USER_ID && $key === 'nx_banners_dismissed' ? $value : '';
			}
		);
	}

	/**
	 * WordPress's style registry, reduced to what render() relies on: printing a registered handle
	 * prints a link to its file, carrying its version when it has one.
	 */
	private function stubStyles(): void {
		$styles = [];

		Functions\when( 'wp_register_style' )->alias(
			static function ( string $handle, string $src, array $deps = [], ?string $ver = null ) use ( &$styles ): bool {
				$styles[ $handle ] = $ver === null ? $src : $src . '?ver=' . $ver;

				return true;
			}
		);
		Functions\when( 'wp_print_styles' )->alias(
			static function ( string $handle ) use ( &$styles ): void {
				echo '<link rel="stylesheet" href="' . $styles[ $handle ] . '">';
			}
		);
	}

	/**
	 * Stub everything the render path needs beyond the dismissal state.
	 */
	protected function stubRenderable(): void {
		Functions\when( 'add_query_arg' )->justReturn( '/wp-admin/?nx-banners-dismiss=x' );
		Functions\when( 'wp_nonce_url' )->returnArg();
	}

	protected function render(
		Banner $banner,
		?callable $shouldDisplay = null,
		?string $template = null,
		?string $stylesheet = null
	): string {
		ob_start();
		$banner->render( $shouldDisplay, $template, $stylesheet );

		return (string) ob_get_clean();
	}

}
