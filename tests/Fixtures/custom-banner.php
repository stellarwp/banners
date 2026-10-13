<?php declare(strict_types=1);

/**
 * A client's own template, chosen through the nx_banners_template:{id} filter.
 *
 * @var \Nexcess\Banners\BannerFields $fields
 */
?>
<p class="custom"><?php echo esc_html( $fields->heading ); ?></p>
