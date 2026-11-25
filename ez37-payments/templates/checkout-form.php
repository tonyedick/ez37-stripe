<?php
/**
 * Checkout form.
 *
 * Available variables (from CheckoutShortcode):
 *
 * @var \EZ37\Payments\Offerings\Offering $offering
 * @var \EZ37\Payments\Agreements\Agreement|null $agreement
 * @var string $action_url
 * @var string $action
 * @var string $nonce_field
 * @var string $honeypot_field
 * @var string $error_message
 * @var bool   $is_test_mode
 *
 * @package EZ37\Payments
 */

defined( 'ABSPATH' ) || exit;

$ez37_fields     = $offering->enrolment_fields;
$ez37_field_keys = array_column( $ez37_fields, 'key' );
// Programmes that collect first and last name separately do not also ask for a full name.
$ez37_split_name = in_array( 'first_name', $ez37_field_keys, true ) && in_array( 'last_name', $ez37_field_keys, true );
?>
<div class="ez37-checkout<?php echo array() !== $ez37_fields ? ' ez37-checkout--wide' : ''; ?>" id="ez37-checkout">
	<?php if ( $is_test_mode && ! $offering->free ) : ?>
		<p class="ez37-checkout__test-notice" role="note">
			<?php esc_html_e( 'Test mode: no real payment will be taken.', 'ez37-payments' ); ?>
		</p>
	<?php endif; ?>

	<h3 class="ez37-checkout__title"><?php echo esc_html( $offering->name ); ?></h3>
	<p class="ez37-checkout__price"><?php echo $offering->free ? esc_html__( 'Free for EZ Coaching alumni', 'ez37-payments' ) : esc_html( $offering->formatted_price() ); ?></p>

	<?php if ( '' !== $error_message ) : ?>
		<div class="ez37-checkout__error" role="alert"><?php echo esc_html( $error_message ); ?></div>
	<?php endif; ?>

	<?php /* Disabling the button on submit prevents accidental double submissions. */ ?>
	<form class="ez37-checkout__form" method="post" action="<?php echo esc_url( $action_url ); ?>" onsubmit="var b=this.querySelector('button[type=submit]');if(b){b.disabled=true;}">
		<input type="hidden" name="action" value="<?php echo esc_attr( $action ); ?>">
		<input type="hidden" name="offering" value="<?php echo esc_attr( $offering->key ); ?>">
		<?php wp_nonce_field( $action, $nonce_field ); ?>

		<div class="ez37-checkout__grid">
			<?php if ( ! $ez37_split_name ) : ?>
				<p class="ez37-checkout__field">
					<label for="ez37-customer-name"><?php esc_html_e( 'Full name', 'ez37-payments' ); ?></label>
					<input type="text" id="ez37-customer-name" placeholder="<?php esc_attr_e( 'Your answer', 'ez37-payments' ); ?>" name="customer_name" required minlength="2" maxlength="100" autocomplete="name">
				</p>
			<?php endif; ?>

			<?php foreach ( $ez37_fields as $ez37_field ) : ?>
				<?php
				$ez37_id    = 'ez37-enrol-' . $ez37_field['key'];
				$ez37_name  = 'enrol[' . $ez37_field['key'] . ']';
				$ez37_class = 'ez37-checkout__field' . ( $ez37_field['half'] ? ' ez37-checkout__field--half' : '' );
				?>
				<p class="<?php echo esc_attr( $ez37_class ); ?>">
					<label for="<?php echo esc_attr( $ez37_id ); ?>">
						<?php echo esc_html( $ez37_field['label'] ); ?>
						<?php if ( ! $ez37_field['required'] ) : ?>
							<span class="ez37-checkout__optional"><?php esc_html_e( '(optional)', 'ez37-payments' ); ?></span>
						<?php endif; ?>
					</label>
					<?php if ( 'select' === $ez37_field['type'] ) : ?>
						<select id="<?php echo esc_attr( $ez37_id ); ?>" name="<?php echo esc_attr( $ez37_name ); ?>"<?php echo $ez37_field['required'] ? ' required' : ''; ?>>
							<option value=""><?php esc_html_e( 'Select…', 'ez37-payments' ); ?></option>
							<?php foreach ( $ez37_field['options'] as $ez37_option ) : ?>
								<option value="<?php echo esc_attr( $ez37_option ); ?>"><?php echo esc_html( $ez37_option ); ?></option>
							<?php endforeach; ?>
						</select>
					<?php elseif ( 'textarea' === $ez37_field['type'] ) : ?>
						<textarea id="<?php echo esc_attr( $ez37_id ); ?>" placeholder="<?php esc_attr_e( 'Your answer', 'ez37-payments' ); ?>" name="<?php echo esc_attr( $ez37_name ); ?>" rows="3" maxlength="<?php echo esc_attr( (string) $ez37_field['max'] ); ?>"<?php echo $ez37_field['required'] ? ' required' : ''; ?>></textarea>
					<?php else : ?>
						<input type="<?php echo 'tel' === $ez37_field['type'] ? 'tel' : 'text'; ?>" placeholder="<?php esc_attr_e( 'Your answer', 'ez37-payments' ); ?>" id="<?php echo esc_attr( $ez37_id ); ?>" name="<?php echo esc_attr( $ez37_name ); ?>" maxlength="<?php echo esc_attr( (string) $ez37_field['max'] ); ?>"<?php echo $ez37_field['required'] ? ' required' : ''; ?><?php echo '' !== $ez37_field['autocomplete'] ? ' autocomplete="' . esc_attr( $ez37_field['autocomplete'] ) . '"' : ''; ?>>
					<?php endif; ?>
				</p>
			<?php endforeach; ?>

			<p class="ez37-checkout__field">
				<label for="ez37-customer-email"><?php esc_html_e( 'Email address', 'ez37-payments' ); ?></label>
				<input type="email" id="ez37-customer-email" placeholder="<?php esc_attr_e( 'Your answer', 'ez37-payments' ); ?>" name="customer_email" required maxlength="254" autocomplete="email">
			</p>
		</div>

		<?php if ( null !== $agreement ) : ?>
			<?php /* The drawer holds the full text. With JavaScript the checkbox opens it; without, the link jumps to it (:target). */ ?>
			<input type="hidden" name="agreement_version" value="<?php echo esc_attr( $agreement->version ); ?>">
			<p class="ez37-checkout__agree">
				<input type="checkbox" id="ez37-agreement-check" name="agreement_accepted" value="1" required data-ez37-agreement="ez37-agreement-drawer">
				<label for="ez37-agreement-check">
					<?php echo esc_html( $agreement->label ); ?>
					(<a href="#ez37-agreement-drawer" data-ez37-agreement-open="ez37-agreement-drawer"><?php esc_html_e( 'read the agreement', 'ez37-payments' ); ?></a>)
				</label>
			</p>
		<?php endif; ?>

		<?php /* Honeypot: hidden from people; bots tend to fill it in. */ ?>
		<p class="ez37-checkout__hp" aria-hidden="true" style="position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden;">
			<label for="ez37-website"><?php esc_html_e( 'Leave this field empty', 'ez37-payments' ); ?></label>
			<input type="text" id="ez37-website" name="<?php echo esc_attr( $honeypot_field ); ?>" value="" tabindex="-1" autocomplete="off">
		</p>

		<p class="ez37-checkout__submit">
			<button type="submit"<?php echo $offering->free ? ' class="ez37-checkout__submit--invoice"' : ''; ?>><?php echo $offering->free ? esc_html__( 'Submit registration', 'ez37-payments' ) : esc_html__( 'Continue to secure payment', 'ez37-payments' ); ?></button>
		</p>

		<p class="ez37-checkout__small">
			<?php echo $offering->free ? esc_html__( 'There is nothing to pay. Our team will email you the list of participants after you register.', 'ez37-payments' ) : esc_html__( 'You will be taken to Stripe to pay securely. Your card details are never shared with us.', 'ez37-payments' ); ?>
		</p>

		<?php /* The payment option decides the next step: full payment goes to Stripe; anything else sends the form to our team, who invoice. The server re-checks this; the script only changes the wording. */ ?>
		<script>
		( function () {
			var sel = document.getElementById( 'ez37-enrol-payment_option' );
			if ( ! sel ) { return; }
			var btn = document.querySelector( '.ez37-checkout__submit button' );
			var note = document.querySelector( '.ez37-checkout__small' );
			var payLabel = btn.textContent, payNote = note.textContent;
			var invLabel = <?php echo wp_json_encode( __( 'Submit enrolment request', 'ez37-payments' ) ); ?>;
			var invNote = <?php echo wp_json_encode( __( 'You will not be charged now. Our team will send you an invoice based on the payment option you chose.', 'ez37-payments' ) ); ?>;
			function sync() {
				var inv = sel.value !== '' && sel.value !== 'Full payment';
				btn.textContent = inv ? invLabel : payLabel;
				btn.classList.toggle( 'ez37-checkout__submit--invoice', inv );
				note.textContent = inv ? invNote : payNote;
			}
			sel.addEventListener( 'change', sync );
			sync();
		}() );
		</script>
	</form>

	<?php if ( null !== $agreement ) : ?>
		<?php /* Moved to <body> by assets/agreement-drawer.js so position:fixed is never trapped by a page container. */ ?>
		<aside class="ez37-drawer" id="ez37-agreement-drawer" role="dialog" aria-modal="true" aria-labelledby="ez37-agreement-title" tabindex="-1" data-ez37-drawer>
			<div class="ez37-drawer__panel">
				<header class="ez37-drawer__head">
					<h2 class="ez37-drawer__title" id="ez37-agreement-title"><?php echo esc_html( $agreement->title ); ?></h2>
					<a class="ez37-drawer__close" href="#ez37-checkout" data-ez37-drawer-close aria-label="<?php esc_attr_e( 'Close the agreement', 'ez37-payments' ); ?>">&times;</a>
				</header>
				<div class="ez37-drawer__body">
					<?php if ( $agreement->draft ) : ?>
						<p class="ez37-drawer__draft" role="note"><?php esc_html_e( 'Draft wording: the final terms are being confirmed by EZ37 Solutions.', 'ez37-payments' ); ?></p>
					<?php endif; ?>
					<?php foreach ( $agreement->sections as $ez37_section ) : ?>
						<h3><?php echo esc_html( $ez37_section['heading'] ); ?></h3>
						<?php foreach ( $ez37_section['paragraphs'] as $ez37_paragraph ) : ?>
							<p><?php echo esc_html( $ez37_paragraph ); ?></p>
						<?php endforeach; ?>
					<?php endforeach; ?>
					<p class="ez37-drawer__version"><?php echo esc_html( sprintf( /* translators: %s: agreement version */ __( 'Version %s', 'ez37-payments' ), $agreement->version ) ); ?></p>
				</div>
				<footer class="ez37-drawer__foot">
					<button type="button" class="ez37-drawer__accept" data-ez37-drawer-accept><?php esc_html_e( 'I Agree', 'ez37-payments' ); ?></button>
					<a class="ez37-drawer__cancel" href="#ez37-checkout" data-ez37-drawer-close><?php esc_html_e( 'Close', 'ez37-payments' ); ?></a>
				</footer>
			</div>
		</aside>
		<div class="ez37-drawer-backdrop" data-ez37-drawer-backdrop hidden></div>
	<?php endif; ?>

	<script>
		/* Re-enable the button if the customer comes back with the browser's Back button. */
		window.addEventListener( 'pageshow', function () {
			document.querySelectorAll( '.ez37-checkout__form button[type=submit]' ).forEach( function ( b ) { b.disabled = false; } );
		} );
	</script>
</div>
