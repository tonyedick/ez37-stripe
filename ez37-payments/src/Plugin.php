<?php
/**
 * Plugin composition root.
 *
 * @package EZ37\Payments
 */

declare( strict_types=1 );

namespace EZ37\Payments;

use EZ37\Payments\Checkout\CheckoutRequestHandler;
use EZ37\Payments\Checkout\CheckoutService;
use EZ37\Payments\Checkout\RateLimiter;
use EZ37\Payments\Frontend\CheckoutShortcode;
use EZ37\Payments\Install\Schema;
use EZ37\Payments\Offerings\OfferingCatalogue;
use EZ37\Payments\Stripe\StripeGateway;
use EZ37\Payments\Support\Logger;
use EZ37\Payments\Transactions\TransactionRepository;
use EZ37\Payments\Webhooks\EventProcessor;
use EZ37\Payments\Webhooks\EventRepository;
use EZ37\Payments\Webhooks\WebhookController;

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

	/**
	 * The booted instance (one per request).
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;

	/**
	 * Constructor.
	 *
	 * @param Config            $config    Configuration.
	 * @param Logger            $logger    Logger.
	 * @param OfferingCatalogue $catalogue Offerings.
	 */
	private function __construct(
		private readonly Config $config,
		private readonly Logger $logger,
		private readonly OfferingCatalogue $catalogue
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

		self::$instance = new self( $config, $logger, OfferingCatalogue::from_file( EZ37_PAYMENTS_DIR . 'config/offerings.php' ) );
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

		$service = new CheckoutService(
			$this->config,
			$this->catalogue,
			new StripeGateway( $this->config ),
			new TransactionRepository(),
			$this->logger
		);

		( new CheckoutRequestHandler( $service, new RateLimiter(), $this->logger ) )->register();
		( new CheckoutShortcode( $this->config, $this->catalogue ) )->register();

		$processor = new EventProcessor( $this->config, new TransactionRepository(), new EventRepository(), $this->logger );
		( new WebhookController( $this->config, $processor, $this->logger ) )->register();
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
		$warnings = array_merge( $this->config->warnings(), $this->catalogue->errors() );

		if ( array() === $errors && ! $this->config->is_checkout_enabled() ) {
			$warnings[] = __( 'Checkout is switched off (EZ37_PAYMENTS_CHECKOUT_ENABLED).', 'ez37-payments' );
		}

		foreach ( $this->catalogue->all() as $offering ) {
			if ( $offering->active && null === $offering->price_id( $this->config->mode() ) ) {
				$warnings[] = sprintf(
					/* translators: 1: offering key, 2: test or live */
					__( 'Offering "%1$s" has no Stripe Price ID for %2$s mode and cannot be bought.', 'ez37-payments' ),
					$offering->key,
					$this->config->mode()
				);
			}
		}

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

		if ( array() !== $errors ) {
			echo '</ul><p>' . esc_html__( 'Checkout stays disabled until all errors are resolved.', 'ez37-payments' ) . '</p></div>';
			return;
		}

		echo '</ul></div>';
	}
}
