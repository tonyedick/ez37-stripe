<?php
/**
 * EZ37 redesign — build the CONTACT (v2) page. LOCAL DEVELOPMENT ONLY.
 *
 * Fills the private "Contact Us v2" page (created by header-footer-local.php):
 *   1. Hero: contact card (email + phone) beside the Calendly booking widget
 *   2. "Send us a message" form (design only; submission is a preview until the handler is built)
 *   3. Offices (Ibadan, Lagos, London)
 *
 * Run after header-footer-local.php:
 *   ddev exec wp --path=/var/www/html/wordpress eval-file /var/www/html/bin/contact-local.php
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

$page = get_page_by_path( 'contact-us-v2', OBJECT, 'page' );
if ( ! $page ) {
	WP_CLI::error( 'Page contact-us-v2 not found. Run bin/header-footer-local.php first.' );
}

require_once __DIR__ . '/lib/ezh-lib.php';

/* ------------------------------------------------------------------- data */
$emails = array( 'info@ez37solutions.org', 'coaching@ezcoaching.org' );
$phones = array( '+44 7553 856948', '+234 813 089 8773', '+234 809 788 1001' );
$offices = array(
	array( 'Ibadan', 'Block 3, Plot 2, Road B, Off Oba Abimbola Way, Agodi GRA, Ibadan, Oyo State.' ),
	array( 'Lagos', '17A, Chibo Ofodile Cl, Unit 3B, Eti-Osa, Lekki Phase 1, Lagos State.' ),
	array( 'London', '71-75 Shelton Street, Covent Garden, London WC2H 9JQ.' ),
);
$services = array( 'Assessments', 'Coaching', 'HR Advisory', 'Public Sector Solutions', 'Facilitation and Learning', 'Coaching programmes and certification', 'Not sure yet' );
$calendly = ezh_calendly_url(); // single source: lib/ezh-lib.php

// Placeholder photography (replace with real photos; keep file names or edit the keys).
$imgs   = ezh_media_dir( 'about', '_ez37_about' );
$bg     = $imgs['main'] ?? '';
$formph = $imgs['hero-workshop'] ?? ( $imgs['inset'] ?? '' );

/* ----------------------------------------------------------- hero builders */
$em = '';
foreach ( $emails as $e ) {
	$em .= '<a href="mailto:' . esc_attr( $e ) . '">' . esc_html( $e ) . '</a>';
}
$ph = '';
foreach ( $phones as $p ) {
	$ph .= '<a href="tel:' . esc_attr( preg_replace( '/[^0-9+]/', '', $p ) ) . '">' . esc_html( $p ) . '</a>';
}

$sections   = array();
$sections[] = ezh_banner_hero( 'Contact Us', '', array(), $bg, 'EZ37 team in conversation', 'Contact Us', true );
$opts = '<option value="">- Select</option>';
foreach ( $services as $s ) {
	$opts .= '<option>' . esc_html( $s ) . '</option>';
}

$social = array(
	'Facebook'  => array( '#', '<path d="M14 8h3V4h-3a5 5 0 0 0-5 5v3H6v4h3v8h4v-8h3l1-4h-4V9a1 1 0 0 1 1-1z"/>' ),
	'YouTube'   => array( 'https://www.youtube.com/@ez37solutionsltd', '<path d="M22 8.2a3 3 0 0 0-2.1-2.1C18 5.6 12 5.6 12 5.6s-6 0-7.9.5A3 3 0 0 0 2 8.2 31 31 0 0 0 1.6 12 31 31 0 0 0 2 15.8a3 3 0 0 0 2.1 2.1c1.9.5 7.9.5 7.9.5s6 0 7.9-.5a3 3 0 0 0 2.1-2.1c.4-1.2.4-3.8.4-3.8s0-2.6-.4-3.8zM10 15V9l5 3z"/>' ),
	'LinkedIn'  => array( '#', '<path d="M4.98 3.5a2.5 2.5 0 1 1 0 5 2.5 2.5 0 0 1 0-5zM3 9.5h4V21H3zM9.5 9.5h3.8v1.6h.1c.5-1 1.8-2 3.8-2 4 0 4.8 2.6 4.8 6V21h-4v-5c0-1.2 0-2.8-1.7-2.8s-2 1.3-2 2.7V21h-4z"/>' ),
	'Instagram' => array( '#', '<rect x="3" y="3" width="18" height="18" rx="5" fill="none" stroke="currentColor" stroke-width="2"/><circle cx="12" cy="12" r="4" fill="none" stroke="currentColor" stroke-width="2"/><circle cx="17.5" cy="6.5" r="1.2"/>' ),
);
$soc = '';
foreach ( $social as $name => $d ) {
	$soc .= '<a href="' . esc_url( $d[0] ) . '" aria-label="' . esc_attr( $name ) . '" target="_blank" rel="noopener"><svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">' . $d[1] . '</svg></a>';
}
$oh = '';
foreach ( $offices as $o ) {
	$oh .= '<div class="ezc2__item">' . ezh_i( 'map-pin' ) . '<div><span class="ezc2__label">' . esc_html( $o[0] ) . '</span>' . esc_html( $o[1] ) . '</div></div>';
}

$sections[] = ezh_section(
	'ezc2',
	'<div class="ezh-wrap" id="ezct">
	<div class="ezc2__head"><h2>Contact EZ37 Solutions</h2>
	<p>Work with a leadership and coaching partner to elevate your team&rsquo;s performance. Share your business or personal challenges in our booking form so we can serve you best.</p></div>
	<div class="ezc2__card">
		<div class="ezc2__info"' . ( $formph ? ' style="--ezc2-img:url(\'' . esc_url( $formph ) . '\')"' : '' ) . '>
			<h3>Get in touch</h3>
			' . $oh . '
			<div class="ezc2__item">' . ezh_i( 'mail' ) . '<div><span class="ezc2__label">Email</span>' . $em . '</div></div>
			<div class="ezc2__item">' . ezh_i( 'phone' ) . '<div><span class="ezc2__label">Phone</span>' . $ph . '</div></div>
			<hr>
			<p>You can also schedule a call with our team. Choose a date and time that works best for you.</p>
			<a class="ezc2__call" href="' . esc_url( $calendly ) . '" target="_blank" rel="noopener">Schedule a call</a>
			<div class="ezc2__social">' . $soc . '</div>
		</div>
		<div class="ezc2__formcol">
			<h3>Send us a message</h3>
			<form class="ez37-form ezct__form" action="#" novalidate data-ez37-demo>
				<div class="ezct__row">
					<div class="ez37-field"><label class="ez37-label" for="ct-name">Name</label><input class="ez37-input" id="ct-name" type="text" placeholder="Your name" autocomplete="name" required><p class="ez37-error">Please tell us your name.</p></div>
					<div class="ez37-field"><label class="ez37-label" for="ct-email">Email</label><input class="ez37-input" id="ct-email" type="email" placeholder="you@company.com" autocomplete="email" required><p class="ez37-error">Enter a valid email address.</p></div>
				</div>
				<div class="ez37-field"><label class="ez37-label" for="ct-service">What service are you interested in?</label><select class="ez37-select" id="ct-service" required>' . $opts . '</select><p class="ez37-error">Choose the closest option.</p></div>
				<div class="ez37-field"><label class="ez37-label" for="ct-msg">Message</label><textarea class="ez37-textarea" id="ct-msg" placeholder="Type your message..." required></textarea><p class="ez37-error">A short message helps us prepare.</p></div>
				<button type="submit" class="ez37-btn ez37-btn--block ez37-btn--lg">Send message</button>
				<div class="ez37-alert ez37-alert--success" role="status" data-ez37-success hidden>' . ezh_i( 'check-circle' ) . '<div><b>Preview only.</b> The form is valid. Sending will be connected before launch.</div></div>
			</form>
		</div>
	</div></div>'
);

/* ------------------------------------------------- closing CTA (moved from Home) */
$logo_urls = ezh_logo_urls();
$sections[] = ezh_section(
	'ezh-close',
	'<div class="ezh-close__banner"><div class="ezh-close__card"><h2 class="ezh-h2">Ready To Build <span class="ez37-hl">Leaders Worth Following?</span></h2>
	<p class="ezh-lead">Whether you know exactly what you need or are still working it out, we will meet you where you are.</p>
	<div class="ezh-btnrow"><a class="ez37-btn" href="' . esc_url( $calendly ) . '" target="_blank" rel="noopener">Book a Consultation</a><a class="ez37-btn-outline" href="' . esc_url( ezh_url( 'solutions' ) ) . '">Explore Solutions</a></div>
	<p class="ezh-note">30-minute call. No pitch, just diagnosis.</p><hr>
	<p class="ezh-note" style="margin:0 0 16px">Join the organisations building their next generation of leaders</p>' . ezh_logo_row( $logo_urls, array( 'AXA', 'FirstBank', 'MTN', 'TotalEnergies', 'GTCO' ) ) . '</div></div>'
);

/* ------------------------------------------------- form behaviour (preview) */
$js_file = __DIR__ . '/../design/ez37-components.js';
$js      = file_exists( $js_file ) ? (string) file_get_contents( $js_file ) : '';
if ( '' === $js ) {
	WP_CLI::warning( 'design/ez37-components.js not found; the form will not validate.' );
}
$sections[] = ezh_section( 'ezct-js', '<script>' . $js . '</script>' );

ezh_save_page( $page->ID, $sections, array( 'ez37-redesign.css', 'ez37-home.css', 'ez37-components.css', 'ez37-contact.css', 'ez37-bhero.css' ) );

WP_CLI::success( 'Contact v2 built: ' . get_permalink( $page->ID ) );
