<?php declare(strict_types=1);

namespace Nexcess\Banners\Tests;

use Brain\Monkey;
use Brain\Monkey\Expectation\EscapeHelper;
use Brain\Monkey\Functions;
use Mockery;
use Nexcess\Banners\Banner;
use Nexcess\Banners\BannerFields;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;

abstract class TestCase extends PHPUnitTestCase {

	protected const USER_ID = 7;

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
		Functions\when( 'esc_url' )->alias(
			static function ( string $url, ?array $protocols = null ): string {
				// WordPress drops a data: URI unless the caller allows that protocol explicitly.
				if ( strpos( $url, 'data:' ) === 0 && ! in_array( 'data', $protocols ?? [], true ) ) {
					return '';
				}

				return EscapeHelper::escUrl( $url );
			}
		);
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
	 * Stub everything the render path needs beyond the dismissal state.
	 */
	protected function stubRenderable(): void {
		Functions\when( 'add_query_arg' )->justReturn( '/wp-admin/?nx-banners-dismiss=x' );
		Functions\when( 'wp_nonce_url' )->returnArg();
	}

	protected function render( Banner $banner, ?callable $shouldDisplay = null ): string {
		ob_start();
		$banner->render( $shouldDisplay );

		return (string) ob_get_clean();
	}

}
