<?php declare(strict_types=1);

namespace Nexcess\Banners;

use DateTimeImmutable;

/**
 * One marketing banner, printed wherever the client calls render().
 */
final class Banner {

	/**
	 * Prefix of the shared action fired when a banner renders. Every plugin bundles its own copy of
	 * this package, so the first copy to render an ID on a request fires it and later copies of the
	 * same ID stand down.
	 */
	private const RENDERED_ACTION = 'nx_banners_rendered:';

	/**
	 * Prefix of the filter that swaps a banner's template file. Shared across copies, so it applies
	 * whichever copy renders the ID.
	 */
	private const TEMPLATE_FILTER = 'nx_banners_template:';

	/**
	 * Prefix of the handle a banner's stylesheet registers under.
	 */
	private const STYLE_HANDLE = 'nx-banners-';

	/**
	 * The hooks core prints notices on. wp-admin's common.js moves a notice printed there under the
	 * page heading, unless it carries "inline".
	 */
	private const NOTICE_HOOKS = [ 'admin_notices', 'all_admin_notices', 'network_admin_notices', 'user_admin_notices' ];

	private string $id;

	private string $version;

	private BannerFields $fields;

	private string $packageUrl;

	private ?DateTimeImmutable $start;

	private ?DateTimeImmutable $end;

	/**
	 * @param string             $id      Shared across plugins: the same ID is the same banner and the same dismissal.
	 * @param string             $version Compared with version_compare(): the registry keeps the newest version of an ID.
	 * @param BannerFields       $fields     The banner fields.
	 * @param string             $packageUrl Where the registering plugin serves its copy of this package, such as
	 *                                       plugins_url( 'vendor-prefixed/stellarwp/banners/', MY_PLUGIN_FILE ).
	 * @param ?DateTimeImmutable $start      The banner shows from this moment. Null shows it from now.
	 * @param ?DateTimeImmutable $end        The banner stops showing at this moment. Null never ends it.
	 */
	public function __construct(
		string $id,
		string $version,
		BannerFields $fields,
		string $packageUrl,
		?DateTimeImmutable $start = null,
		?DateTimeImmutable $end = null
	) {
		$this->id         = $id;
		$this->version    = $version;
		$this->fields     = $fields;
		$this->packageUrl = $packageUrl;
		$this->start      = $start;
		$this->end        = $end;
	}

	public function id(): string {
		return $this->id;
	}

	public function version(): string {
		return $this->version;
	}

	/**
	 * Edit these to change what this banner shows, such as to set translated copy.
	 */
	public function fields(): BannerFields {
		return $this->fields;
	}

	/**
	 * Print the banner here unless this is not wp-admin, it is already rendered on this request, out
	 * of schedule or dismissed, or the callable declines.
	 *
	 * @param (callable(): bool)|null $shouldDisplay Omit to render unconditionally.
	 */
	public function render( ?callable $shouldDisplay = null ): void {
		Dismissals::handleRequest( $this->id );

		if (
			! is_admin()
			|| did_action( self::RENDERED_ACTION . $this->id ) > 0
			|| ! $this->isScheduled()
			|| Dismissals::isDismissed( $this->id )
			|| ( $shouldDisplay !== null && ! $shouldDisplay() )
		) {
			return;
		}

		do_action( self::RENDERED_ACTION . $this->id );

		// Let a banner on a notice hook move under the heading like any notice, and keep one rendered
		// anywhere else where the client called render().
		$inline = array_filter( self::NOTICE_HOOKS, 'doing_action' ) === [];

		$this->printStylesheet();
		$this->printTemplate( $this->fields, Dismissals::url( $this->id ), $inline );
	}

	/**
	 * Print the stylesheet's link right here. render() runs mid-page, after wp-admin printed the
	 * head's styles, so an enqueued one would print in the footer and leave the banner unstyled until then.
	 */
	private function printStylesheet(): void {
		wp_register_style(
			self::STYLE_HANDLE . $this->id,
			rtrim( $this->packageUrl, '/' ) . '/src/templates/banner.css',
			[],
			// A new release replaces the bundled file at the same URL, so its modification time busts caches.
			(string) filemtime( __DIR__ . '/templates/banner.css' )
		);
		wp_print_styles( self::STYLE_HANDLE . $this->id );
	}

	/**
	 * Print the bundled template, or the one a filter chose for this ID, with $fields, $dismiss_url and
	 * $inline in scope. They are snake_case so that templates pass the WordPress coding standards most
	 * plugins lint with.
	 */
	private function printTemplate( BannerFields $fields, string $dismiss_url, bool $inline ): void {
		$bundled  = __DIR__ . '/templates/banner.php';
		$template = apply_filters( self::TEMPLATE_FILTER . $this->id, $bundled );

		// Every other copy has already stood down for this ID, so a broken filter must not hide it.
		if ( ! is_string( $template ) || ! is_file( $template ) ) {
			$template = $bundled;
		}

		require $template;
	}

	private function isScheduled(): bool {
		$now = time();

		return ( $this->start === null || $now >= $this->start->getTimestamp() )
			&& ( $this->end === null || $now < $this->end->getTimestamp() );
	}

	/**
	 * A copy edits its own fields, never the original's.
	 */
	public function __clone() {
		$this->fields = clone $this->fields;
	}

}
