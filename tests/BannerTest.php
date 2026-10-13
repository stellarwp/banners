<?php declare(strict_types=1);

namespace Nexcess\Banners\Tests;

use Brain\Monkey\Actions;
use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use DateTimeImmutable;
use DateTimeZone;
use Nexcess\Banners\Banner;
use Nexcess\Banners\BannerFields;

final class BannerTest extends TestCase {

	private const FIELDS = [
		'heading'  => 'Go Pro',
		'body'     => 'Unlock every block.',
		'ctaLabel' => 'Upgrade',
		'ctaUrl'   => 'https://example.test/pro',
		'image'    => 'data:image/svg+xml;base64,PHN2Zy8+',
	];

	/**
	 * @return array<string, array{mixed}>
	 */
	public static function brokenTemplates(): array {
		return [
			'false'          => [ false ],
			'null'           => [ null ],
			'an array'       => [ [] ],
			'a missing file' => [ __DIR__ . '/Fixtures/no-such-template.php' ],
			'a directory'    => [ __DIR__ ],
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

	public function testRendersTheCopyCtaImageAndDismissLink(): void {
		$this->stubDismissed( [] );
		$this->stubRenderable();

		$output = $this->render( new Banner( 'promo', '1.0.0', self::fields( self::FIELDS ) ) );

		$this->assertStringContainsString( '<strong>Go Pro</strong>', $output );
		$this->assertStringContainsString( 'Unlock every block.', $output );
		$this->assertStringContainsString(
			'<a href="https://example.test/pro" class="button button-primary">Upgrade</a>',
			$output
		);
		$this->assertStringContainsString( '<img src="data:image/svg+xml;base64,PHN2Zy8+" alt=""', $output );
		$this->assertStringContainsString( 'class="notice-dismiss"', $output );
		$this->assertStringContainsString( 'Dismiss this banner.', $output );
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
			$this->render( new Banner( 'promo', '1.0.0', self::fields( self::FIELDS ) ) )
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

		$this->assertStringContainsString( '<strong>Go Pro</strong>', $output );
		$this->assertDoesNotMatchRegularExpression( '/<div class="[^"]*\binline\b/', $output );
	}

	public function testOmitsTheCtaWithoutBothALabelAndAUrl(): void {
		$this->stubDismissed( [] );
		$this->stubRenderable();

		$output = $this->render( new Banner( 'promo', '1.0.0', self::fields( [ 'heading' => 'Go Pro', 'ctaLabel' => 'Upgrade' ] ) ) );

		$this->assertStringNotContainsString( 'button-primary', $output );
	}

	public function testOmitsTheImageWhenNoneIsGiven(): void {
		$this->stubDismissed( [] );
		$this->stubRenderable();

		$this->assertStringNotContainsString( '<img', $this->render( new Banner( 'promo', '1.0.0', self::fields( [ 'heading' => 'Go Pro' ] ) ) ) );
	}

	public function testDescribesTheImageWithItsAltText(): void {
		$this->stubDismissed( [] );
		$this->stubRenderable();

		$output = $this->render( new Banner( 'promo', '1.0.0', self::fields( self::FIELDS + [ 'imageAlt' => 'Pro logo' ] ) ) );

		$this->assertStringContainsString( '<img src="data:image/svg+xml;base64,PHN2Zy8+" alt="Pro logo"', $output );
	}

	public function testEscapesEveryField(): void {
		$copy = [
			'heading'      => '<script>heading</script>',
			'body'         => '<img src=x onerror=alert(1)>',
			'ctaLabel'     => '<b>cta</b>',
			'imageAlt'     => '" onerror="alert(1)',
			'dismissLabel' => '<svg onload=alert(1)>',
		];

		$this->stubDismissed( [] );
		$this->stubRenderable();

		$output = $this->render(
			new Banner(
				'promo',
				'1.0.0',
				self::fields(
					$copy + [
						'ctaUrl' => "https://example.test/?a='b'",
						'image'  => "https://example.test/logo.png?c='d'",
					]
				)
			)
		);

		foreach ( $copy as $value ) {
			$this->assertStringNotContainsString( $value, $output );
			$this->assertStringContainsString( htmlspecialchars( $value, ENT_QUOTES, 'UTF-8' ), $output );
		}
		$this->assertStringContainsString( 'href="https://example.test/?a=&#039;b&#039;"', $output );
		$this->assertStringContainsString( 'src="https://example.test/logo.png?c=&#039;d&#039;"', $output );
	}

	public function testRendersNothingOutsideTheAdmin(): void {
		Functions\when( 'is_admin' )->justReturn( false );
		$this->stubDismissed( [] );
		$this->stubRenderable();

		$this->assertSame( '', $this->render( new Banner( 'promo', '1.0.0', self::fields( self::FIELDS ) ) ) );
	}

	public function testAFilteredTemplateReplacesTheMarkup(): void {
		$this->stubDismissed( [] );
		$this->stubRenderable();
		Filters\expectApplied( 'nx_banners_template:promo' )->once()->andReturn( __DIR__ . '/Fixtures/custom-banner.php' );

		$output = $this->render( new Banner( 'promo', '1.0.0', self::fields( self::FIELDS ) ) );

		$this->assertSame( '<p class="custom">Go Pro</p>', trim( $output ) );
	}

	/**
	 * A broken filter must neither fatal the page nor hide the banner: by the time the template
	 * loads, every other copy has already stood down for this ID.
	 *
	 * @dataProvider brokenTemplates
	 *
	 * @param mixed $template What the filter returned.
	 */
	public function testABrokenFilteredTemplateFallsBackToTheBundledOne( $template ): void {
		$this->stubDismissed( [] );
		$this->stubRenderable();
		Filters\expectApplied( 'nx_banners_template:promo' )->andReturn( $template );

		$output = $this->render( new Banner( 'promo', '1.0.0', self::fields( self::FIELDS ) ) );

		$this->assertStringContainsString( '<strong>Go Pro</strong>', $output );
	}

	public function testRendersWhenTheCallableReturnsTrue(): void {
		$this->stubDismissed( [] );
		$this->stubRenderable();

		$this->assertNotSame( '', $this->render( new Banner( 'promo', '1.0.0', self::fields( self::FIELDS ) ), static fn(): bool => true ) );
	}

	public function testRendersNothingWhenTheCallableReturnsFalse(): void {
		$this->stubDismissed( [] );

		$this->assertSame( '', $this->render( new Banner( 'promo', '1.0.0', self::fields( self::FIELDS ) ), static fn(): bool => false ) );
	}

	public function testRendersNothingBeforeTheStart(): void {
		$this->stubDismissed( [] );

		$this->assertSame( '', $this->render( new Banner( 'promo', '1.0.0', new BannerFields(), new DateTimeImmutable( '+1 hour' ) ) ) );
	}

	public function testRendersNothingAfterTheEnd(): void {
		$this->stubDismissed( [] );

		$this->assertSame( '', $this->render( new Banner( 'promo', '1.0.0', new BannerFields(), null, new DateTimeImmutable( '-1 hour' ) ) ) );
	}

	public function testRendersInsideTheSchedule(): void {
		$this->stubDismissed( [] );
		$this->stubRenderable();

		$banner = new Banner( 'promo', '1.0.0', new BannerFields(), new DateTimeImmutable( '-1 hour' ), new DateTimeImmutable( '+1 hour' ) );

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

		$first  = $this->render( new Banner( 'campaign', '1.0.0', self::fields( [ 'heading' => 'First' ] ) ) );
		$second = $this->render(
			new Banner( 'campaign', '1.0.0', self::fields( [ 'heading' => 'Second' ] ) ),
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

		$this->assertNotSame( '', $this->render( new Banner( 'upsell', '1.0.0', self::fields( self::FIELDS ) ) ) );
		$this->assertNotSame( '', $this->render( new Banner( 'campaign', '1.0.0', self::fields( self::FIELDS ) ) ) );
	}

	public function testRendersNothingOnceTheUserDismissedIt(): void {
		$this->stubDismissed( [ 'promo' => true ] );

		$this->assertSame( '', $this->render( new Banner( 'promo', '1.0.0', self::fields( self::FIELDS ) ) ) );
	}

	public function testDismissingAnotherBannerDoesNotHideThisOne(): void {
		$this->stubDismissed( [ 'other' => true ] );
		$this->stubRenderable();

		$this->assertNotSame( '', $this->render( new Banner( 'promo', '1.0.0', self::fields( self::FIELDS ) ) ) );
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
				$output = $this->render( new Banner( 'promo', '1.0.0', self::fields( self::FIELDS ) ) );
			}
		);

		do_action( $hook );

		return $output;
	}

}
