<?php declare(strict_types=1);

namespace Nexcess\Banners\Tests;

use Brain\Monkey\Actions;
use Brain\Monkey\Functions;
use DateTimeImmutable;
use DateTimeZone;
use Nexcess\Banners\Banner;
use Nexcess\Banners\BannerFields;
use Nexcess\Banners\Package;

final class BannerTest extends TestCase {

	private const FIELDS = [
		'heading'  => 'Go Pro',
		'body'     => 'Unlock every block.',
		'ctaLabel' => 'Upgrade',
		'ctaUrl'   => 'https://example.test/pro',
	];

	/**
	 * @return array<string, array{string}>
	 */
	public static function brokenTemplates(): array {
		return [
			'an empty path'  => [ '' ],
			'a missing file' => [ __DIR__ . '/Fixtures/no-such-template.php' ],
			'a directory'    => [ __DIR__ ],
		];
	}

	/**
	 * @return array<string, array{string}>
	 */
	public static function packageUrls(): array {
		return [
			'with a trailing slash'    => [ self::PACKAGE_URL ],
			'without a trailing slash' => [ rtrim( self::PACKAGE_URL, '/' ) ],
		];
	}

	/**
	 * @return array<string, array{string}>
	 */
	public static function noticeHooks(): array {
		return [
			'admin_notices'         => [ 'admin_notices' ],
			'all_admin_notices'     => [ 'all_admin_notices' ],
			'network_admin_notices' => [ 'network_admin_notices' ],
			'user_admin_notices'    => [ 'user_admin_notices' ],
		];
	}

	public function testRendersTheCopyCtaAndDismissLink(): void {
		$this->stubDismissed( [] );
		$this->stubRenderable();

		$output = $this->render( new Banner( 'promo', '1.0.0', self::fields( self::FIELDS ), self::PACKAGE_URL ) );

		$this->assertMatchesRegularExpression( '/>\s*Go Pro\s*<span class="nx-banner__accent">Unlock every block\.<\/span>/', $output );
		$this->assertMatchesRegularExpression(
			'/<a class="nx-banner__cta" href="https:\/\/example\.test\/pro" target="_blank" rel="noopener noreferrer">\s*Upgrade/',
			$output
		);
		$this->assertStringContainsString( '(opens in a new tab)', $output );
		$this->assertMatchesRegularExpression( '/<a class="nx-banner__dismiss" href="[^"]*\/wp-admin\/\?nx-banners-dismiss=x">/', $output );
		$this->assertStringContainsString( 'Dismiss this banner.', $output );
	}

	/**
	 * render() runs mid-page, after wp-admin printed the head's styles: an enqueued style would print
	 * in the footer and leave the banner unstyled until then, so its link prints right before it.
	 *
	 * @dataProvider packageUrls
	 */
	public function testPrintsTheBundledStylesheetFromThePackageUrlBeforeTheBanner( string $packageUrl ): void {
		$this->stubDismissed( [] );
		$this->stubRenderable();

		$this->assertMatchesRegularExpression(
			'/^<link rel="stylesheet" href="' . preg_quote( self::PACKAGE_URL . 'src/templates/banner.css?ver=' . Package::VERSION, '/' ) . '">\s*<div class="notice /',
			$this->render( new Banner( 'promo', '1.0.0', self::fields( self::FIELDS ), $packageUrl ) )
		);
	}

	public function testPrintsTheStylesheetItIsGiven(): void {
		$this->stubDismissed( [] );
		$this->stubRenderable();

		$output = $this->render(
			new Banner( 'promo', '1.0.0', self::fields( self::FIELDS ), self::PACKAGE_URL ),
			null,
			null,
			'https://example.test/wp-content/plugins/my-plugin/banner.css'
		);

		$this->assertStringContainsString( '<link rel="stylesheet" href="https://example.test/wp-content/plugins/my-plugin/banner.css">', $output );
		$this->assertStringNotContainsString( 'src/templates/banner.css', $output );
	}

	/**
	 * Without "inline", wp-admin's common.js moves every .notice under the page heading, which would
	 * undo rendering the banner where the client called it.
	 */
	public function testStaysWhereItIsRendered(): void {
		$this->stubDismissed( [] );
		$this->stubRenderable();

		$this->assertMatchesRegularExpression(
			'/<div class="notice [^"]*\binline\b/',
			$this->render( new Banner( 'promo', '1.0.0', self::fields( self::FIELDS ), self::PACKAGE_URL ) )
		);
	}

	/**
	 * A plugin page's own hook is not a notice hook: the banner must stay where it was rendered.
	 */
	public function testInsideAnotherHookItStaysWhereItIsRendered(): void {
		$this->assertMatchesRegularExpression( '/<div class="notice [^"]*\binline\b/', $this->renderDuring( 'my_plugin_settings_page' ) );
	}

	/**
	 * On a notice hook, wp-admin's common.js moves the banner under the page heading like any other
	 * notice, unless it carries "inline".
	 *
	 * @dataProvider noticeHooks
	 */
	public function testOnANoticeHookCoreMovesItUnderTheHeading( string $hook ): void {
		$output = $this->renderDuring( $hook );

		$this->assertStringContainsString( 'Go Pro', $output );
		$this->assertDoesNotMatchRegularExpression( '/<div class="[^"]*\binline\b/', $output );
	}

	public function testOmitsTheCtaWithoutBothALabelAndAUrl(): void {
		$this->stubDismissed( [] );
		$this->stubRenderable();

		$output = $this->render( new Banner( 'promo', '1.0.0', self::fields( [ 'heading' => 'Go Pro', 'ctaLabel' => 'Upgrade' ] ), self::PACKAGE_URL ) );

		$this->assertStringNotContainsString( 'nx-banner__cta', $output );
	}

	public function testEscapesEveryField(): void {
		$copy = [
			'heading'      => '<script>heading</script>',
			'body'         => '<img src=x onerror=alert(1)>',
			'ctaLabel'     => '<b>cta</b>',
			'dismissLabel' => '<svg onload=alert(1)>',
		];

		$this->stubDismissed( [] );
		$this->stubRenderable();

		$output = $this->render(
			new Banner(
				'promo',
				'1.0.0',
				self::fields( $copy + [ 'ctaUrl' => "https://example.test/?a='b'" ] ),
				self::PACKAGE_URL
			)
		);

		foreach ( $copy as $value ) {
			$this->assertStringNotContainsString( $value, $output );
			$this->assertStringContainsString( htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' ), $output );
		}
		$this->assertStringContainsString( 'href="https://example.test/?a=&#039;b&#039;"', $output );
	}

	public function testRendersNothingOutsideTheAdmin(): void {
		Functions\when( 'is_admin' )->justReturn( false );
		$this->stubDismissed( [] );
		$this->stubRenderable();

		$this->assertSame( '', $this->render( new Banner( 'promo', '1.0.0', self::fields( self::FIELDS ), self::PACKAGE_URL ) ) );
	}

	public function testRendersWithTheTemplateItIsGivenAndPassesItTheDismissUrl(): void {
		$this->stubDismissed( [] );
		$this->stubRenderable();

		$output = $this->render(
			new Banner( 'promo', '1.0.0', self::fields( self::FIELDS ), self::PACKAGE_URL ),
			null,
			__DIR__ . '/Fixtures/custom-banner.php'
		);

		$this->assertStringContainsString( '<p class="custom">Go Pro</p>', $output );
		$this->assertMatchesRegularExpression( '/<a class="custom-dismiss" href="[^"]*\/wp-admin\/\?nx-banners-dismiss=x">/', $output );
		$this->assertStringNotContainsString( 'nx-banner__inner', $output );
	}

	/**
	 * A broken path must neither fatal the page nor hide the banner: by the time the template
	 * loads, every other copy has already stood down for this ID.
	 *
	 * @dataProvider brokenTemplates
	 */
	public function testABrokenTemplatePathFallsBackToTheBundledOne( string $template ): void {
		$this->stubDismissed( [] );
		$this->stubRenderable();

		$output = $this->render( new Banner( 'promo', '1.0.0', self::fields( self::FIELDS ), self::PACKAGE_URL ), null, $template );

		$this->assertStringContainsString( 'nx-banner__inner', $output );
	}

	public function testRendersWhenTheCallableReturnsTrue(): void {
		$this->stubDismissed( [] );
		$this->stubRenderable();

		$this->assertNotSame( '', $this->render( new Banner( 'promo', '1.0.0', self::fields( self::FIELDS ), self::PACKAGE_URL ), static fn(): bool => true ) );
	}

	public function testRendersNothingWhenTheCallableReturnsFalse(): void {
		$this->stubDismissed( [] );

		$this->assertSame( '', $this->render( new Banner( 'promo', '1.0.0', self::fields( self::FIELDS ), self::PACKAGE_URL ), static fn(): bool => false ) );
	}

	public function testRendersNothingBeforeTheStart(): void {
		$this->stubDismissed( [] );

		$this->assertSame( '', $this->render( new Banner( 'promo', '1.0.0', new BannerFields(), self::PACKAGE_URL, new DateTimeImmutable( '+1 hour' ) ) ) );
	}

	public function testRendersNothingAfterTheEnd(): void {
		$this->stubDismissed( [] );

		$this->assertSame( '', $this->render( new Banner( 'promo', '1.0.0', new BannerFields(), self::PACKAGE_URL, null, new DateTimeImmutable( '-1 hour' ) ) ) );
	}

	public function testRendersInsideTheSchedule(): void {
		$this->stubDismissed( [] );
		$this->stubRenderable();

		$banner = new Banner( 'promo', '1.0.0', new BannerFields(), self::PACKAGE_URL, new DateTimeImmutable( '-1 hour' ), new DateTimeImmutable( '+1 hour' ) );

		$this->assertNotSame( '', $this->render( $banner ) );
	}

	public function testTheScheduleComparesMomentsNotWallClocksAcrossTimeZones(): void {
		$this->stubDismissed( [] );
		$this->stubRenderable();

		// Started an hour ago at UTC+14 and ends in an hour at UTC-11: the wall clocks read backwards.
		$banner = new Banner(
			'promo',
			'1.0.0',
			new BannerFields(),
			self::PACKAGE_URL,
			new DateTimeImmutable( '-1 hour', new DateTimeZone( 'Pacific/Kiritimati' ) ),
			new DateTimeImmutable( '+1 hour', new DateTimeZone( 'Pacific/Pago_Pago' ) )
		);

		$this->assertNotSame( '', $this->render( $banner ) );
	}

	/**
	 * Every plugin bundles its own prefixed copy, so the second Banner here stands in for another
	 * plugin rendering the same campaign later on the same request.
	 */
	public function testTheFirstCopyToRenderABannerWinsTheRequest(): void {
		$this->stubDismissed( [] );
		$this->stubRenderable();

		$first  = $this->render( new Banner( 'campaign', '1.0.0', self::fields( [ 'heading' => 'First' ] ), self::PACKAGE_URL ) );
		$second = $this->render(
			new Banner( 'campaign', '1.0.0', self::fields( [ 'heading' => 'Second' ] ), self::PACKAGE_URL ),
			function (): bool {
				$this->fail( 'A banner that already rendered asked its callable again.' );
			}
		);

		$this->assertStringContainsString( 'First', $first );
		$this->assertSame( '', $second );
		// The name every older copy checks: renaming it breaks the dedupe with them.
		$this->assertSame( 1, did_action( 'nx_banners_rendered:campaign' ) );
	}

	public function testDifferentBannersEachRender(): void {
		$this->stubDismissed( [] );
		$this->stubRenderable();

		$this->assertNotSame( '', $this->render( new Banner( 'upsell', '1.0.0', self::fields( self::FIELDS ), self::PACKAGE_URL ) ) );
		$this->assertNotSame( '', $this->render( new Banner( 'campaign', '1.0.0', self::fields( self::FIELDS ), self::PACKAGE_URL ) ) );
	}

	public function testRendersNothingOnceTheUserDismissedIt(): void {
		$this->stubDismissed( [ 'promo' => true ] );

		$this->assertSame( '', $this->render( new Banner( 'promo', '1.0.0', self::fields( self::FIELDS ), self::PACKAGE_URL ) ) );
	}

	public function testDismissingAnotherBannerDoesNotHideThisOne(): void {
		$this->stubDismissed( [ 'other' => true ] );
		$this->stubRenderable();

		$this->assertNotSame( '', $this->render( new Banner( 'promo', '1.0.0', self::fields( self::FIELDS ), self::PACKAGE_URL ) ) );
	}

	/**
	 * Render while WordPress runs the given action, as a client hooking render() onto it would.
	 */
	private function renderDuring( string $hook ): string {
		$this->stubDismissed( [] );
		$this->stubRenderable();
		$output = '';
		Actions\expectDone( $hook )->once()->whenHappen(
			function () use ( &$output ): void {
				$output = $this->render( new Banner( 'promo', '1.0.0', self::fields( self::FIELDS ), self::PACKAGE_URL ) );
			}
		);

		do_action( $hook );

		return $output;
	}

}
