<?php
/**
 * EZ37 redesign — build the ABOUT (v2) page. LOCAL DEVELOPMENT ONLY.
 *
 * Fills the private "About v2" page (created by header-footer-local.php):
 *   1. About hero bento (photo + title card + stat cards + two photos)
 *   2. "How we work" section pattern (moved here from the Home page)
 *
 * Run from the project root (after header-footer-local.php and home-local.php):
 *   ddev exec wp --path=/var/www/html/wordpress eval-file /var/www/html/bin/about-local.php
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

$page = get_page_by_path( 'about-v2', OBJECT, 'page' );
if ( ! $page ) {
	WP_CLI::error( 'Page about-v2 not found. Run bin/header-footer-local.php first.' );
}

require_once __DIR__ . '/lib/ezh-lib.php';

// Placeholder photos (swap for real About-page photography; keep the same file names or edit the keys below).
$about_imgs = ezh_media_dir( 'about', '_ez37_about' );  // main.jpg, inset.jpg
$case_imgs  = ezh_media_dir( 'case', '_ez37_case' );    // case-study-meeting.jpg
$big   = $about_imgs['who-we-are'] ?? $about_imgs['hero-workshop'] ?? ( $case_imgs['case-study-meeting'] ?? '' );
$pic_a = $about_imgs['main'] ?? '';
$pic_b = $about_imgs['inset'] ?? '';

function ezab_img( string $url, string $alt ): string {
	return $url ? '<img src="' . esc_url( $url ) . '" alt="' . esc_attr( $alt ) . '" loading="lazy">' : '';
}

$stats = array(
	array( 'calendar', '17 years &amp; counting!', 'Founded in 2009' ),
	array( 'users', '2,887+ Clients', 'Individuals and organisations served' ),
	array( 'map-pin', 'Ibadan &middot; Lagos &middot; London', 'Offices in Nigeria and the UK' ),
	array( 'briefcase', '8,080+ Projects', 'Delivered across sectors' ),
);
$sh = '';
foreach ( $stats as $s ) {
	$sh .= '<div class="ezab__stat"><div><b>' . $s[1] . '</b><span>' . $s[2] . '</span></div></div>';
}

$sections   = array();
$sections[] = ezh_banner_hero( 'About Us', '', array(), $big, 'EZ37 facilitator leading a leadership workshop', 'About', true );
$sections[] = ezh_section(
	'ezab-who',
	'<div class="ezab-who__grid">
	<div class="ezab-who__text">
		<h2>Who We Are</h2>
		<div class="ezab-who__body"><p>EZ37 Solutions is an HR and management consulting firm and an ICF-accredited coach education provider. For 17 years we have partnered with public and private sector organisations to develop leaders and turn strategy into lasting behavioural change. We combine international coaching standards with deep in-market experience, and we design every engagement around the outcomes our clients need.</p>
		<p>As a dynamic firm, we specialise in <strong>Coaching, Learning and Development, Assessments, HR Advisory, Public Sector Solutions, and Business and Management Consulting</strong> services. Our tailored solutions are designed to drive enhanced business performance, employee satisfaction, and sustainable growth.</p>
		<p>With over a decade of industry expertise, we have established a proven track record of delivering results that matter. Our team combines strategic insight with innovative thinking to address complex challenges and create lasting value for our clients.</p>
		<p>Whether you are aiming to develop your workforce, strengthen leadership skills, or boost organisational performance, EZ37 Solutions is your trusted partner for success.</p></div>
	</div>
	<figure class="ezab-who__media">' . ezab_img( $big, 'EZ37 facilitator leading a leadership workshop' ) . '</figure>
	</div>'
);

/* Corporate philosophy: Vision + Mission cards, then core-value tiles */
/* Core values: front = icon + name, back (on hover / focus / tap) = the full write-up. */
$phil_vals = array(
	array( 'users', 'People', 'We embrace the diversity of every individual &ndash; our employees, our consultants, and our clients. We value and benefit from the diversity and entrepreneurial spirit of each individual.' ),
	array( 'shield', 'Integrity', 'Integrity, honesty, and accountability are at the core of our business. We adhere absolutely to the independence and objectivity requirements under which we do business.' ),
	array( 'globe', 'Partnership', 'The success of partnership is achieved through creating a &lsquo;win-win&rsquo; outcome for all involved. We believe the best solutions result from working alongside our clients. We strive for client participation at every level of engagement.' ),
	array( 'award', 'Excellence', 'We deliver what we promise and add value that goes beyond what is expected.' ),
	array( 'target', 'Team Work', 'We recognise that every member of the team is an embodiment of value. Therefore, we believe the best solutions come from recognising our individual strength and working together to achieve a common goal.' ),
);
$pv = '';
foreach ( $phil_vals as $v ) {
	$pv .= '<div class="ezab-phil__val" tabindex="0"><div class="ezab-phil__in">'
		. '<div class="ezab-phil__face is-front">' . ezh_i( $v[0] ) . '<b>' . esc_html( $v[1] ) . '</b></div>'
		. '<div class="ezab-phil__face is-back"><h4>' . esc_html( $v[1] ) . '</h4><p>' . wp_kses( $v[2], array() ) . '</p></div>'
		. '</div></div>';
}
$sections[] = ezh_section(
	'ezab-phil',
	'<div class="ezab-phil__inner">
	<h2>Our Corporate Philosophy</h2>
	<div class="ezab-phil__cards">
		<article><h3>Vision</h3><p>To be a world-class leader focused on delivering distinctive and innovative solutions in Human Resources and Management Consultancy, enhancing business performance and customer satisfaction.</p></article>
		<article><h3>Mission</h3><p>To provide transformational business strategies, including HR solutions, that positively impact people and organisations.</p></article>
	</div>
	<div class="ezab-phil__bar"><h3>Core Values</h3><span></span></div>
	<div class="ezab-phil__vals">' . $pv . '</div>
	</div>'
);

$why_items = array(
	array( 'International standards', 'ICF-accredited coach education, ISO 9001:2015 certified quality management and IFC-LPI partnership.' ),
	array( 'In-market experience', '17 years of supporting public and private sector organisations globally, from UK and Nigerian offices.' ),
	array( 'Systemic, joined-up design', 'Coaching, assessment, training and consulting combined into one coherent journey, not stand-alone events.' ),
	array( 'Ethical, credentialed practice', 'MCC and PCC-led delivery, grounded in the ICF Code of Ethics.' ),
);
$wh = '';
foreach ( $why_items as $k => $w ) {
	$wh .= '<details class="ezab-why__item" name="ezab-why"' . ( 0 === $k ? ' open' : '' ) . '><summary><span>' . esc_html( $w[0] ) . '</span><i aria-hidden="true"></i></summary><p>' . esc_html( $w[1] ) . '</p></details>';
}
// "How we work" timeline removed from About; recall it any time with: $sections[] = ezh_section_process();  (lib/ezh-lib.php)
$sections[] = ezh_section_impact();

$sections[] = ezh_section(
	'ezab-why',
	'<div class="ezab-why__inner">
	<figure class="ezab-why__media">' . ezab_img( $about_imgs['why-us'] ?? '', 'A client listening attentively during a coaching session' ) . '</figure>
	<div class="ezab-why__text"><h2>Why Clients Choose EZ37</h2>' . $wh . '</div>
	</div>'
);

$sections[] = ezh_section(
	'ezab-ready',
	'<div class="ezab-ready__in"><h2 class="ezab-ready__h">Ready to Start Your Journey With Us?</h2><a class="ez37-btn ezab-ready__btn" href="' . esc_url( ezh_url( 'contact-us' ) ) . '">Talk to us &rarr;</a></div>'
);
$sections[] = ezh_section_faq(); // last section of the page

ezh_save_page( $page->ID, $sections, array( 'ez37-redesign.css', 'ez37-home.css', 'ez37-about.css', 'ez37-bhero.css' ) );

WP_CLI::success( 'About v2 built: ' . get_permalink( $page->ID ) );
