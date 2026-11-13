<?php
/**
 * Paid resource value object.
 *
 * @package EZ37\Payments
 */

declare( strict_types=1 );

namespace EZ37\Payments\Resources;

/**
 * One deliverable resource, as defined in config/resources.php.
 */
final class PaidResource {

	/**
	 * Constructor.
	 *
	 * @param string $key         Resource key.
	 * @param string $label       Customer-facing label.
	 * @param string $path        Path relative to the private folder.
	 * @param bool   $attach      Attach to the customer email when small enough.
	 * @param int    $max_attach  Attachment size limit in bytes.
	 * @param int    $access_days Download link lifetime in days.
	 */
	public function __construct(
		public readonly string $key,
		public readonly string $label,
		public readonly string $path,
		public readonly bool $attach,
		public readonly int $max_attach,
		public readonly int $access_days
	) {}

	/**
	 * Absolute file path inside the private folder, or null when the file is
	 * missing or would resolve outside that folder (path traversal guard).
	 *
	 * @param string|null $private_dir Real path of the private folder.
	 */
	public function file_path( ?string $private_dir ): ?string {
		if ( null === $private_dir ) {
			return null;
		}

		$real = realpath( $private_dir . '/' . $this->path );

		if ( false === $real || ! is_file( $real ) || ! is_readable( $real ) ) {
			return null;
		}

		return str_starts_with( $real, rtrim( $private_dir, '/' ) . '/' ) ? $real : null;
	}

	/**
	 * File name offered to the customer when downloading or as an attachment.
	 */
	public function download_name(): string {
		return basename( $this->path );
	}
}
