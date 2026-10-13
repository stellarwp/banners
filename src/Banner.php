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
	 * The hooks core prints notices on. wp-admin's common.js moves a notice printed there under the
	 * page heading, unless it carries "inline".
	 */
	private const NOTICE_HOOKS = [ 'admin_notices', 'all_admin_notices', 'network_admin_notices', 'user_admin_notices' ];

	private string $id;

	private string $version;

	private BannerFields $fields;

	private ?DateTimeImmutable $start;

	private ?DateTimeImmutable $end;

	/**
	 * @param string             $id      Shared across plugins: the same ID is the same banner and the same dismissal.
	 * @param string             $version Compared with version_compare(): the registry keeps the newest version of an ID.
	 * @param BannerFields       $fields  The banner fields.
	 * @param ?DateTimeImmutable $start   The banner shows from this moment. Null shows it from now.
	 * @param ?DateTimeImmutable $end     The banner stops showing at this moment. Null never ends it.
	 */
	public function __construct(
		string $id,
		string $version,
		BannerFields $fields,
		?DateTimeImmutable $start = null,
		?DateTimeImmutable $end = null
	) {
		$this->id      = $id;
		$this->version = $version;
		$this->fields  = $fields;
		$this->start   = $start;
		$this->end     = $end;
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

		$this->printTemplate( $this->fields, Dismissals::url( $this->id ), $inline );
	}

	/**
	 * Print the template with only $fields, $dismissUrl and $inline in scope.
	 */
	private function printTemplate( BannerFields $fields, string $dismissUrl, bool $inline ): void {
		require __DIR__ . '/templates/banner.php';
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
