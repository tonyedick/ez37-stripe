<?php
/**
 * Template rendering helper.
 *
 * @package EZ37\Payments
 */

declare( strict_types=1 );

namespace EZ37\Payments\Frontend;

/**
 * Renders a PHP template from the plugin's templates/ folder into a string.
 * Templates are responsible for escaping everything they print.
 */
final class Template {

	/**
	 * Render a template with variables.
	 *
	 * @param string               $template File name inside templates/.
	 * @param array<string, mixed> $vars     Variables for the template.
	 */
	public static function render( string $template, array $vars = array() ): string {
		$path = EZ37_PAYMENTS_DIR . 'templates/' . $template;

		if ( ! is_readable( $path ) ) {
			return '';
		}

		ob_start();
		( static function ( string $ez37_template_path, array $ez37_vars ): void {
			// phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- Controlled, plugin-internal variables only.
			extract( $ez37_vars, EXTR_SKIP );
			require $ez37_template_path;
		} )( $path, $vars );

		return (string) ob_get_clean();
	}
}
