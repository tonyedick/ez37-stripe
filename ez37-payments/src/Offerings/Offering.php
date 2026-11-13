<?php
/**
 * Offering value object.
 *
 * @package EZ37\Payments
 */

declare( strict_types=1 );

namespace EZ37\Payments\Offerings;

/**
 * One purchasable offering, as defined in config/offerings.php.
 *
 * Immutable. Created only by OfferingCatalogue after validation.
 */
final class Offering {

	/**
	 * Constructor.
	 *
	 * @param string                $key        Identifier, e.g. "leadership-coaching".
	 * @param string                $name       Customer-facing name.
	 * @param bool                  $active     Whether it can be purchased.
	 * @param string                $currency   Lowercase ISO 4217 code.
	 * @param int                   $amount     Price in the smallest currency unit.
	 * @param array<string, string> $price_ids  Stripe Price ID per mode ("test", "live").
	 * @param string                $tax_mode   Tax handling; "none" until confirmed (D7).
	 * @param array<string, mixed>  $fulfilment Fulfilment definition (Phase 6).
	 */
	public function __construct(
		public readonly string $key,
		public readonly string $name,
		public readonly bool $active,
		public readonly string $currency,
		public readonly int $amount,
		private readonly array $price_ids,
		public readonly string $tax_mode,
		public readonly array $fulfilment
	) {}

	/**
	 * Stripe Price ID for a mode, or null when not configured.
	 *
	 * @param string $mode "test" or "live".
	 */
	public function price_id( string $mode ): ?string {
		$id = $this->price_ids[ $mode ] ?? '';

		return '' === $id ? null : $id;
	}

	/**
	 * Whether the offering can be bought in the given mode.
	 *
	 * @param string $mode "test" or "live".
	 */
	public function is_purchasable( string $mode ): bool {
		return $this->active && null !== $this->price_id( $mode );
	}

	/**
	 * Price formatted for display, e.g. "$1.00 USD".
	 *
	 * Only two-decimal currencies are supported for now (USD — decision D2).
	 */
	public function formatted_price(): string {
		$symbol = 'usd' === $this->currency ? '$' : '';

		return sprintf( '%s%s %s', $symbol, number_format( $this->amount / 100, 2 ), strtoupper( $this->currency ) );
	}
}
