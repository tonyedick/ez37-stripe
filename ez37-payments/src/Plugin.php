<?php
/**
 * Plugin composition root.
 *
 * @package EZ37\Payments
 */

declare( strict_types=1 );

namespace EZ37\Payments;

use EZ37\Payments\Install\Schema;
use EZ37\Payments\Support\Logger;

/**
 * Creates the shared services and registers WordPress hooks.
 *
 * Contains wiring only — no business logic. Each later phase registers its
 * own controllers here (checkout, webhooks, fulfilment, admin).
 */
final class Plugin {

	/**
	 * Capability required to view payment data in wp-admin.
	 * Granted to administrators on activation.
	 */
	public const CAPABILITY = 'ez37_view_payments';

	private static ?self $instance = null;

	/**
	 * Constructor.
	 *
	 * @param Config $config Configuration.
	 * @param Logger $logger Logger.
	 */
	private function __construct(
		private readonly Config $config,
		private readonly Logger $logger
	) {}

	/**
	 * Boot the plugin (plugins_loaded).
	 */
	public static function boot(): void {
		if ( null !== self::$instance ) {
			return;
		}

		$config = Config::from_environment();
		$logger = new Logger( $config->log_level() );

		self::$instance = new self( $config, $logger );
		self::$instance->register_hooks();
	}

	/**
	 * The booted instance.
	 *
	 * @throws \LogicException If called before boot().
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			throw new \LogicException( 'EZ37 Payments has not been booted yet.' );
		}

		return self::$instance;
	}

	/**
	 * Configuration.
	 */
	public function config(): Config {
		return $this->config;
	}

	/**
	 * Logger.
	 */
	public function logger(): Logger {
		return $this->logger;
	}

	/**
	 * Register hooks.
	 */
	private function register_hooks(): void {
		Schema::maybe_upgrade();

		add_action( 'init', array( $this, 'load_textdomain' ) );
		add_action( 'admin_notices', array( $this, 'render_config_notice' ) );
	}

	/**
	 * Load translations.
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain( 'ez37-payments', false, dirname( plugin_basename( EZ37_PAYMENTS_FILE ) ) . '/languages' );
	}

	/**
	 * Show configuration problems to administrators. Messages name settings
	 * but never contain their values.
	 */
	public function render_config_notice(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$errors   = $this->config->errors();
		$warnings = $this->config->warnings();

		if ( array() === $errors && array() === $warnings ) {
			return;
		}

		$class = array() === $errors ? 'notice-warning' : 'notice-error';
		$items = array_merge( $errors, $warnings );

		echo '<div class="notice ' . esc_attr( $class ) . '"><p><strong>'
			. esc_html(
				sprintf(
					/* translators: %s: test or live */
					__( 'EZ37 Payments configuration (%s mode):', 'ez37-payments' ),
					$this->config->mode()
				)
			)
			. '</strong></p><ul style="list-style:disc;margin-left:1.5em">';

		foreach ( $items as $item ) {
			echo '<li>' . esc_html( $item ) . '</li>';
		}

		echo '</ul><p>' . esc_html__( 'Checkout stays disabled until all errors are resolved.', 'ez37-payments' ) . '</p></div>';
	}
}
