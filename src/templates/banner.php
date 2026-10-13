<?php declare(strict_types=1);

/**
 * Banner markup, included by Banner::render(). Escape everything here, at output.
 *
 * "inline" stops wp-admin's common.js from moving the notice under the page heading, so it is left
 * off only on a notice hook, where the banner should move like any other notice. The dismiss
 * control is a link so the dismissal is stored server side, without a script; core's
 * makeNoticesDismissible() skips notices that already carry a .notice-dismiss.
 *
 * @var \Nexcess\Banners\BannerFields $fields
 * @var string $dismissUrl
 * @var bool $inline
 */

defined( 'ABSPATH' ) || exit;

?>
<div class="notice notice-info is-dismissible<?php echo $inline ? ' inline' : ''; ?>" style="display:flex;align-items:center;gap:12px;">
	<?php if ( $fields->image !== '' ) : ?>
		<img src="<?php echo esc_url( $fields->image, [ 'http', 'https', 'data' ] ); ?>" alt="<?php echo esc_attr( $fields->imageAlt ); ?>" style="flex:none;max-width:64px;height:auto;" />
	<?php endif; ?>
	<div>
		<p><strong><?php echo esc_html( $fields->heading ); ?></strong></p>
		<p><?php echo esc_html( $fields->body ); ?></p>
		<?php if ( $fields->ctaLabel !== '' && $fields->ctaUrl !== '' ) : ?>
			<p><a href="<?php echo esc_url( $fields->ctaUrl ); ?>" class="button button-primary"><?php echo esc_html( $fields->ctaLabel ); ?></a></p>
		<?php endif; ?>
	</div>
	<a href="<?php echo esc_url( $dismissUrl ); ?>" class="notice-dismiss" style="text-decoration:none;"><span class="screen-reader-text"><?php echo esc_html( $fields->dismissLabel ); ?></span></a>
</div>
