<?php declare(strict_types=1);

namespace Nexcess\Banners\Tests\Doubles;

/**
 * Banner as another plugin's prefixed copy defines it: the same methods under a different class name.
 */
final class ForeignBanner {

	public const OUTPUT = 'Rendered by the other copy';

	private string $version;

	public function __construct( string $version ) {
		$this->version = $version;
	}

	public function version(): string {
		return $this->version;
	}

	public function render(): void {
		echo self::OUTPUT;
	}

}
