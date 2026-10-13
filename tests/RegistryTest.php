<?php declare(strict_types=1);

namespace Nexcess\Banners\Tests;

use Nexcess\Banners\Banner;
use Nexcess\Banners\Registry;
use Nexcess\Banners\Tests\Doubles\ForeignBanner;
use OutOfBoundsException;

/**
 * Each Registry instance below stands in for a different plugin: they share one global registry.
 */
final class RegistryTest extends TestCase {

	private static function banner( string $version, string $heading ): Banner {
		return new Banner( 'promo', $version, self::fields( [ 'heading' => $heading ] ) );
	}

	public function testANewerVersionReplacesTheRegisteredOne(): void {
		$this->stubDismissed( [] );
		$this->stubRenderable();

		( new Registry() )->register( self::banner( '1.0.0', 'Product A copy' ) );
		( new Registry() )->register( self::banner( '1.0.1', 'Product B copy' ) );

		$output = $this->renderFetched( 'promo' );

		$this->assertStringContainsString( 'Product B copy', $output );
		$this->assertStringNotContainsString( 'Product A copy', $output );
	}

	public function testAnOlderVersionDoesNotReplaceANewerOne(): void {
		$this->stubDismissed( [] );
		$this->stubRenderable();

		( new Registry() )->register( self::banner( '1.0.1', 'Product B copy' ) );
		( new Registry() )->register( self::banner( '1.0.0', 'Product A copy' ) );

		$this->assertStringContainsString( 'Product B copy', $this->renderFetched( 'promo' ) );
	}

	public function testAnEqualVersionKeepsTheFirstRegistered(): void {
		$this->stubDismissed( [] );
		$this->stubRenderable();

		( new Registry() )->register( self::banner( '1.0.0', 'First' ) );
		( new Registry() )->register( self::banner( '1.0.0', 'Second' ) );

		$this->assertStringContainsString( 'First', $this->renderFetched( 'promo' ) );
	}

	/**
	 * Strauss renames the classes in every plugin's copy, so the registry can hold a banner whose
	 * class is not this copy's Banner. It must be compared and rendered through its own methods.
	 */
	public function testABannerFromAnotherCopyIsComparedAndRenderedByItsOwnCode(): void {
		$GLOBALS['nx_banners']['promo'] = new ForeignBanner( '2.0.0' );

		( new Registry() )->register( self::banner( '1.0.0', 'Older, from this copy' ) );

		$this->assertSame( ForeignBanner::OUTPUT, $this->renderFetched( 'promo' ) );
	}

	public function testANewerBannerReplacesOneFromAnotherCopy(): void {
		$this->stubDismissed( [] );
		$this->stubRenderable();
		$GLOBALS['nx_banners']['promo'] = new ForeignBanner( '2.0.0' );

		( new Registry() )->register( self::banner( '2.0.1', 'Newer, from this copy' ) );

		$this->assertStringContainsString( 'Newer, from this copy', $this->renderFetched( 'promo' ) );
	}

	public function testEditedFieldsRenderButStayWithTheCaller(): void {
		$this->stubDismissed( [] );
		$this->stubRenderable();
		( new Registry() )->register( self::banner( '1.0.0', 'Registered heading' ) );

		$banner                    = ( new Registry() )->get( 'promo' );
		$banner->fields()->heading = 'Traduzido';

		ob_start();
		$banner->render();
		$output = (string) ob_get_clean();

		$this->assertStringContainsString( 'Traduzido', $output );
		$this->assertSame( 'Registered heading', ( new Registry() )->get( 'promo' )->fields()->heading );
	}

	public function testHasReportsOnlyRegisteredIds(): void {
		$registry = new Registry();

		$this->assertFalse( $registry->has( 'promo' ) );

		$registry->register( self::banner( '1.0.0', 'Heading' ) );

		$this->assertTrue( $registry->has( 'promo' ) );
		$this->assertFalse( $registry->has( 'no-such-banner' ) );
	}

	public function testAnUnknownIdThrows(): void {
		$this->expectException( OutOfBoundsException::class );
		$this->expectExceptionMessage( 'Unknown banner "no-such-banner"' );

		( new Registry() )->get( 'no-such-banner' );
	}

	private function renderFetched( string $id ): string {
		ob_start();
		( new Registry() )->get( $id )->render();

		return (string) ob_get_clean();
	}

}
