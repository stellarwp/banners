<?php declare(strict_types=1);

namespace Nexcess\Banners;

/**
 * What a banner says and shows. Pass translated copy from the consuming plugin.
 */
final class BannerFields {

	/**
	 * Bold first line.
	 */
	public string $heading = '';

	public string $body = '';

	/**
	 * The button renders only when both ctaLabel and ctaUrl are set.
	 */
	public string $ctaLabel = '';

	public string $ctaUrl = '';

	/**
	 * A URL or a data: URI, shown at most 64px wide.
	 */
	public string $image = '';

	/**
	 * Empty marks the image decorative, so screen readers skip it.
	 */
	public string $imageAlt = '';

	/**
	 * Screen-reader label of the dismiss control.
	 */
	public string $dismissLabel = 'Dismiss this banner.';

}
