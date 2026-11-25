<?php
/**
 * EZ37 redesign — build the ENROLMENT FUNNEL pages (v2 design). LOCAL DEVELOPMENT ONLY.
 *
 * Re-skins the three payment pages with the EZ37 design system and a 3-step progress indicator:
 *   /checkout/           Step 1-2  details + secure payment   ([ez37_checkout])
 *   /payment-complete/   Step 3    confirmation               ([ez37_payment_status])
 *   /payment-cancelled/  Step 2    payment not completed      ([ez37_payment_cancelled])
 *
 * The payment engine (plugin) is NOT changed: it still validates the offering, decides the price,
 * creates the Stripe Checkout Session and treats the webhook as the source of truth.
 * Run bin/pages-local.php first so the pages exist.
 *
 *   ddev exec wp --path=/var/www/html/wordpress eval-file /var/www/html/bin/enrol-local.php
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit( "Run this with WP-CLI eval-file.\n" );
}
if ( ! class_exists( '\Elementor\Plugin' ) ) {
	WP_CLI::error( 'Elementor is not active.' );
}
if ( 'local' !== wp_get_environment_type() && false === strpos( home_url(), 'ddev.site' ) ) {
	WP_CLI::error( 'Refusing to run: this does not look like the local DDEV site.' );
}

require_once __DIR__ . '/lib/ezh-lib.php';

$contact = ezh_url( 'contact-us' );
$pccp_url = ezh_url( 'pccp' );
$lead_photo = ( ezh_media_dir( 'case', '_ez37_case' )['case-study-meeting'] ?? '' );

$defs = array(
	'checkout'          => array(
		'badge'     => 'Enrol',
		'title'     => 'Enrol in the <span class="ez37-hl">PCCP</span>',
		'lead'      => 'Complete your enrolment details for the Professional Coach Certification Programme, read and accept the Enrolment Agreement, then pay securely with Stripe.',
		'step'      => 1,
		'shortcode' => '[ez37_checkout]',
	),
	'payment-complete'  => array(
		'badge'     => 'Confirmation',
		'title'     => 'Thank <span class="ez37-hl">You</span>',
		'lead'      => 'We are confirming your payment. Your receipt and programme material will arrive by email.',
		'step'      => 3,
		'shortcode' => '[ez37_payment_status]',
	),
	'enrolment-received' => array(
		'badge'     => 'Enrolment received',
		'title'     => 'Enrolment <span class="ez37-hl">Received</span>',
		'lead'      => 'Your details are with our team. You have not been charged: we will send you an invoice based on the payment option you chose.',
		'step'      => 2,
		'steps'     => array( 1 => 'Your details', 2 => 'Invoice from our team', 3 => 'Payment' ),
		'shortcode' => '[ez37_enrolment_received]',
	),
	'payment-cancelled' => array(
		'badge'     => 'Payment not completed',
		'title'     => 'No Payment Was <span class="ez37-hl">Taken</span>',
		'lead'      => 'You left before paying, so you have not been charged. You can try again whenever you are ready.',
		'step'      => 2,
		'shortcode' => '[ez37_payment_cancelled]',
	),
);

$steps = array( 1 => 'Your details', 2 => 'Secure payment', 3 => 'Confirmation' );

foreach ( $defs as $slug => $d ) {
	$page = get_page_by_path( $slug, OBJECT, 'page' );
	if ( ! $page && 'enrolment-received' === $slug ) {
		$new_id = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Enrolment received', 'post_name' => $slug ) );
		$page   = $new_id && ! is_wp_error( $new_id ) ? get_post( $new_id ) : null;
	}
	if ( ! $page ) {
		WP_CLI::warning( "Page /$slug/ not found. Run bin/pages-local.php first." );
		continue;
	}

	$stepper = '<ol class="ezen-steps" aria-label="Enrolment progress">';
	foreach ( ( $d['steps'] ?? $steps ) as $n => $label ) {
		$state    = $n < $d['step'] ? ' is-done' : ( $n === $d['step'] ? ' is-current' : '' );
		$stepper .= '<li class="ezen-steps__item' . $state . '"><span class="ezen-steps__dot">' . ( $n < $d['step'] ? '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12.5l4.5 4.5L19 7.5"/></svg>' : $n ) . '</span><b>' . esc_html( $label ) . '</b></li>';
	}
	$stepper .= '</ol>';

	$hero = ezh_section(
		'ezen',
		'<div class="ezh-wrap ezen__hero">' . ezh_head( $d['badge'], $d['title'] ) . '<p class="ezen__lead">' . esc_html( $d['lead'] ) . '</p>' . $stepper . '</div>'
	);

	// The checkout page serves every offering (/checkout/?offering=key): swap the hero to match it. PCCP is the default markup.
	if ( 'checkout' === $slug ) {
		$hero_map = array(
			'pccp' => array( 'title' => 'Professional Coach Certification Programme (PCCP)', 'name' => 'PCCP', 'url' => $pccp_url, 'head' => 'Enrol in the <span class="ez37-hl">PCCP</span>', 'lead' => $d['lead'] ),
			'accp' => array( 'title' => 'Advanced Coach Certification Programme (ACCP)', 'name' => 'ACCP', 'url' => ezh_url( 'accp' ), 'head' => 'Enrol in the <span class="ez37-hl">ACCP</span>', 'lead' => 'Complete your enrolment details for the Advanced Coach Certification Programme, read and accept the Enrolment Agreement, then pay securely with Stripe.' ),
			'amcmp' => array( 'title' => 'Advanced Mentor Coaching Mastery Program (AMCMP)', 'name' => 'AMCMP', 'url' => ezh_url( 'advanced-mastery' ), 'head' => 'Enrol in the <span class="ez37-hl">AMCMP</span>', 'lead' => 'Complete your enrolment details for the Advanced Mentor Coaching Mastery Program, then pay securely with Stripe.' ),
			'mcs' => array( 'title' => 'Mentor Coach Specialization (MCS) Training', 'name' => 'MCS Training', 'url' => ezh_url( 'mcs' ), 'head' => 'Enrol in <span class="ez37-hl">MCS Training</span>', 'lead' => 'Complete your enrolment details for the Mentor Coach Specialization (MCS) Training, then pay securely with Stripe.' ),
			'peer-to-peer' => array( 'title' => 'EZ Peer to Peer Coaching', 'name' => 'Peer to Peer', 'url' => ezh_url( 'peer-to-peer-coaching' ), 'head' => 'Register for <span class="ez37-hl">Peer to Peer Coaching</span>', 'lead' => 'Free for EZ Coaching alumni. Complete your details and accept the Peer-to-Peer Agreement. There is nothing to pay.' ),
			'coaching-skills' => array( 'title' => 'Coaching Skills Training for Managers and Leaders', 'name' => 'Coaching Skills', 'url' => ezh_url( 'coaching-skills-training' ), 'head' => 'Enrol in <span class="ez37-hl">Coaching Skills Training</span>', 'lead' => 'Complete your enrolment details for the Coaching Skills Training for Managers and Leaders course, then pay securely with Stripe.' ),
		);
		$hero['elements'][0]['settings']['html'] .= '<script>(function(){var m=' . wp_json_encode( $hero_map ) . ',k=new URLSearchParams(location.search).get("offering"),d=m[k];if(!d||k==="pccp")return;'
			. 'var h=document.querySelector(".ezs-bhero__copy h1");if(h)h.textContent=d.title;'
			. 'var a=document.querySelectorAll(".ezs-bhero__crumbs a");if(a[1]){a[1].textContent=d.name;a[1].href=d.url;}'
			. 'var t=document.querySelector(".ezen .ezh-h2");if(t)t.innerHTML=d.head;'
			. 'var l=document.querySelector(".ezen__lead");if(l)l.textContent=d.lead;'
			. 'function late(){var w=document.querySelector(".ezen__aside h3");if(w)w.textContent="How "+d.name+" enrolment works";'
			. 'if(k==="peer-to-peer"){var lp=document.querySelectorAll(".ezen__list li");if(lp[2])lp[2].style.display="none";if(lp[1]){var sp1=lp[1].querySelector("b");if(sp1)sp1.textContent="Accept the Peer-to-Peer Agreement";}if(lp[3]){var b3=lp[3].querySelector("b"),s3=lp[3].querySelector("span");if(b3)b3.textContent="Get your list of participants";if(s3)s3.textContent="We email you the link to the participant list so you can choose your peer coach.";}if(lp[0]){var s0=lp[0].querySelector("span");if(s0)s0.textContent="Your contact details and your coaching hours.";}var stp=document.querySelectorAll(".ezen-steps__item");if(stp[1])stp[1].style.display="none";var n2=0;stp.forEach(function(e){if(e.style.display==="none")return;n2++;var dt=e.querySelector(".ezen-steps__dot");if(dt&&!dt.querySelector("svg"))dt.textContent=n2;});var ol2=document.querySelector(".ezen-steps");if(ol2)ol2.classList.add("ezen-steps--two");}if(k==="coaching-skills"||k==="amcmp"||k==="mcs"){var li=document.querySelectorAll(".ezen__list li");if(li[1])li[1].style.display="none";if(li[0]){var sp=li[0].querySelector("span");if(sp)sp.textContent="Your contact details and why you are taking the course.";}'
			. 'var st=document.querySelectorAll(".ezen-steps__item");if(st[1])st[1].style.display="none";var ol=document.querySelector(".ezen-steps");if(ol)ol.classList.add("ezen-steps--two");var n=0;st.forEach(function(e){if(e.style.display==="none")return;n++;var dot=e.querySelector(".ezen-steps__dot");if(dot&&!dot.querySelector("svg"))dot.textContent=n;});}}'
			. 'late();document.addEventListener("DOMContentLoaded",late);window.addEventListener("load",late);'
			. '})();</script>';
	}

	$aside = '<aside class="ezen__aside"><h3>How PCCP enrolment works</h3><ol class="ezen__list">'
		. '<li><b>Complete the enrolment form</b><span>Your professional details and coaching background for the programme cohort.</span></li>'
		. '<li><b>Review and accept the Enrolment Agreement</b><span>Open the agreement, read it in full and accept it. Payment cannot start until it is accepted.</span></li>'
		. '<li><b>Pay securely on Stripe</b><span>Your card details go to Stripe, never to us.</span></li>'
		. '<li><b>Get your confirmation</b><span>An email with your receipt and your programme material.</span></li>'
		. '</ol><p class="ezen__help">Need help or an invoice for your organisation? <a href="' . esc_url( $contact ) . '">Contact us</a>.</p></aside>';

	$grid = array(
		'id'       => substr( md5( 'ezen-grid-' . $slug ), 0, 7 ),
		'elType'   => 'container',
		'isInner'  => false,
		'settings' => array(
			'content_width'  => 'full',
			'flex_direction' => 'column',
			'css_classes'    => 'checkout' === $slug ? 'ezen__grid' : 'ezen__grid ezen__grid--single',
		),
		'elements' => array(
			array(
				'id'         => substr( md5( 'ezen-sc-' . $slug ), 0, 7 ),
				'elType'     => 'widget',
				'widgetType' => 'shortcode',
				'settings'   => array( 'shortcode' => $d['shortcode'], '_css_classes' => 'ezen__card' ),
				'elements'   => array(),
			),
		),
	);

	if ( 'checkout' === $slug ) {
		array_unshift(
			$grid['elements'],
			array(
				'id'         => substr( md5( 'ezen-aside-' . $slug ), 0, 7 ),
				'elType'     => 'widget',
				'widgetType' => 'html',
				'settings'   => array( 'html' => $aside ),
				'elements'   => array(),
			)
		);
	}

	ezh_save_page( $page->ID, array( ezh_banner_hero( 'Professional Coach Certification Programme (PCCP)', '', array(), $lead_photo, 'Coaches in a certification session', array( array( 'PCCP', $pccp_url ), $d['badge'] ), true ), $hero, $grid ), array( 'ez37-redesign.css', 'ez37-home.css', 'ez37-components.css', 'ez37-enrol.css', 'ez37-bhero.css' ) );
	WP_CLI::success( "/$slug/ rebuilt (id {$page->ID})" );
}
