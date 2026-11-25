<?php
/**
 * Agreement value object.
 *
 * @package EZ37\Payments
 */

declare( strict_types=1 );

namespace EZ37\Payments\Agreements;

/**
 * A versioned agreement the customer must accept before paying
 * (for example the programme enrolment agreement).
 *
 * Immutable. Created only by AgreementCatalogue after validation.
 *
 * The acceptance stored with an order is the key, the version, a SHA-256 hash
 * of the exact text shown, and the time. Changing the wording therefore means
 * changing the version, and old orders still point to the text they accepted.
 */
final class Agreement {

	/**
	 * Constructor.
	 *
	 * @param string                                                  $key      Identifier, e.g. "pccp-enrolment".
	 * @param string                                                  $version  Version label, e.g. "2026-11-24".
	 * @param string                                                  $title    Descriptive title shown on the drawer.
	 * @param string                                                  $label    Text next to the checkbox.
	 * @param bool                                                    $draft    True while the wording is not approved by the client.
	 * @param list<array{heading: string, paragraphs: list<string>}> $sections Agreement text.
	 */
	public function __construct(
		public readonly string $key,
		public readonly string $version,
		public readonly string $title,
		public readonly string $label,
		public readonly bool $draft,
		public readonly array $sections
	) {}

	/**
	 * SHA-256 of the canonical text (version, title and every section), so an
	 * order can prove which wording was accepted.
	 */
	public function text_hash(): string {
		$canonical = wp_json_encode(
			array(
				'key'      => $this->key,
				'version'  => $this->version,
				'title'    => $this->title,
				'sections' => $this->sections,
			)
		);

		return hash( 'sha256', is_string( $canonical ) ? $canonical : '' );
	}
}
