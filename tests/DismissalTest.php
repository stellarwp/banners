<?php declare(strict_types=1);

namespace Nexcess\Banners\Tests;

use Brain\Monkey\Functions;
use Mockery;
use Nexcess\Banners\Banner;
use Nexcess\Banners\BannerFields;

/**
 * The dismiss link returns to the page that rendered the banner, and the banner's own render()
 * records the dismissal, because a client calling render() mid-page leaves no earlier hook to do it.
 */
final class DismissalTest extends TestCase {

	public function testTheDismissLinkCarriesTheBannerAndANonceBoundToIt(): void {
		$this->stubDismissed( [] );
		Functions\expect( 'add_query_arg' )
			->once()
			->with( 'nx-banners-dismiss', 'promo' )
			->andReturn( 'https://example.test/wp-admin/?nx-banners-dismiss=promo' );
		Functions\expect( 'wp_nonce_url' )
			->once()
			->with( 'https://example.test/wp-admin/?nx-banners-dismiss=promo', 'nx-banners-dismiss:promo', 'nx-banners-nonce' )
			->andReturn( 'https://example.test/wp-admin/?nx-banners-dismiss=promo&nx-banners-nonce=signed' );

		$this->assertStringContainsString(
			'href="https://example.test/wp-admin/?nx-banners-dismiss=promo&nx-banners-nonce=signed"',
			$this->render( new Banner( 'promo', '1.0.0', new BannerFields() ) )
		);
	}

	public function testAValidDismissalIsStoredForTheUserAndHidesTheBanner(): void {
		$_GET['nx-banners-dismiss'] = 'promo';
		$_GET['nx-banners-nonce']   = 'signed';

		$stored = [ 'other' => 1700000000 ];
		Functions\when( 'get_user_meta' )->alias(
			static function () use ( &$stored ) {
				return $stored;
			}
		);
		Functions\expect( 'wp_verify_nonce' )->once()->with( 'signed', 'nx-banners-dismiss:promo' )->andReturn( 1 );
		Functions\expect( 'update_user_meta' )
			->once()
			->with( self::USER_ID, 'nx_banners_dismissed', Mockery::type( 'array' ) )
			->andReturnUsing(
				static function ( int $userId, string $key, array $value ) use ( &$stored ): bool {
					$stored = $value;

					return true;
				}
			);

		$before = time();

		$this->assertSame( '', $this->render( new Banner( 'promo', '1.0.0', new BannerFields() ) ) );
		$this->assertSame( 1700000000, $stored['other'] );
		$this->assertIsInt( $stored['promo'] );
		$this->assertGreaterThanOrEqual( $before, $stored['promo'] );
		$this->assertLessThanOrEqual( time(), $stored['promo'] );
	}

	public function testARepeatedDismissalKeepsTheFirstTimestamp(): void {
		$_GET['nx-banners-dismiss'] = 'promo';
		$_GET['nx-banners-nonce']   = 'signed';

		$this->stubDismissed( [ 'promo' => 1700000000 ] );
		Functions\when( 'wp_verify_nonce' )->justReturn( 1 );
		Functions\expect( 'update_user_meta' )->never();

		$this->render( new Banner( 'promo', '1.0.0', new BannerFields() ) );
	}

	public function testALoggedOutDismissalIsIgnored(): void {
		$_GET['nx-banners-dismiss'] = 'promo';
		$_GET['nx-banners-nonce']   = 'signed';

		Functions\when( 'is_user_logged_in' )->justReturn( false );
		$this->stubDismissed( [] );
		$this->stubRenderable();
		Functions\expect( 'wp_verify_nonce' )->never();
		Functions\expect( 'update_user_meta' )->never();

		$this->render( new Banner( 'promo', '1.0.0', new BannerFields() ) );
	}

	public function testAnInvalidNonceIsIgnoredAndTheBannerStillRenders(): void {
		$_GET['nx-banners-dismiss'] = 'promo';
		$_GET['nx-banners-nonce']   = 'forged';

		$this->stubDismissed( [] );
		$this->stubRenderable();
		Functions\expect( 'wp_verify_nonce' )->once()->andReturn( false );
		Functions\expect( 'update_user_meta' )->never();

		$this->assertNotSame( '', $this->render( new Banner( 'promo', '1.0.0', new BannerFields() ) ) );
	}

	public function testADismissalForAnotherBannerIsIgnored(): void {
		$_GET['nx-banners-dismiss'] = 'other';
		$_GET['nx-banners-nonce']   = 'signed';

		$this->stubDismissed( [] );
		$this->stubRenderable();
		Functions\expect( 'wp_verify_nonce' )->never();
		Functions\expect( 'update_user_meta' )->never();

		$this->assertNotSame( '', $this->render( new Banner( 'promo', '1.0.0', new BannerFields() ) ) );
	}

	public function testANonStringDismissalIsIgnored(): void {
		$_GET['nx-banners-dismiss'] = [ 'promo' ];
		$_GET['nx-banners-nonce']   = 'signed';

		$this->stubDismissed( [] );
		$this->stubRenderable();
		Functions\expect( 'wp_verify_nonce' )->never();
		Functions\expect( 'update_user_meta' )->never();

		$this->render( new Banner( 'promo', '1.0.0', new BannerFields() ) );
	}

	public function testANonStringNonceIsIgnored(): void {
		$_GET['nx-banners-dismiss'] = 'promo';
		$_GET['nx-banners-nonce']   = [ 'signed' ];

		$this->stubDismissed( [] );
		$this->stubRenderable();
		Functions\expect( 'wp_verify_nonce' )->never();
		Functions\expect( 'update_user_meta' )->never();

		$this->render( new Banner( 'promo', '1.0.0', new BannerFields() ) );
	}

}
