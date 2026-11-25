<?php
/**
 * EZ37 redesign — build the LEADERSHIP / EXECUTIVE COACHING (v2) page. LOCAL DEVELOPMENT ONLY.
 *
 * Fills the private "Leadership/Executive Coaching v2" page (created by header-footer-local.php).
 * So far: the hero banner (badge, headline, copy, CTA + "See how it works" link, video-style photo)
 * followed by the trusted-by logo strip. Copy is placeholder text to be replaced with real content.
 *
 *   ddev exec wp --path=/var/www/html/wordpress eval-file /var/www/html/bin/leadership-coaching-local.php
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

$page = get_page_by_path( 'leadership-executive-coaching-v2', OBJECT, 'page' );
if ( ! $page ) {
	WP_CLI::error( 'Page leadership-executive-coaching-v2 not found. Run bin/header-footer-local.php first.' );
}

require_once __DIR__ . '/lib/ezh-lib.php';

$contact   = ezh_url( 'contact-us' );
$video_url = ''; // Set to a video URL (YouTube/Vimeo/MP4 page) to show the "Watch video" play button on the photo.

$case_imgs = ezh_media_dir( 'case', '_ez37_case' );
$photo     = $case_imgs['case-study-meeting'] ?? '';

$play = '';
if ( $video_url ) {
	$play = '<a class="ezp-hero__play" href="' . esc_url( $video_url ) . '" target="_blank" rel="noopener" aria-label="Watch video"><span>' . ezh_i( 'play' ) . '</span><b>Watch Video</b></a>';
}

$sections   = array();
$sections[] = ezh_banner_hero( 'Leadership and Executive Coaching', '', array(), $photo, 'Executives in a coaching conversation', 'Leadership and Executive Coaching', true );

/* Enrol band: links to the EZ37 Payments checkout (Stripe Checkout, TEST mode locally).
   The offering key must exist in ez37-payments/config/offerings.php; price comes from the server, never from this page. */
$offering = 'leadership-coaching';
if ( ! get_page_by_path( 'checkout', OBJECT, 'page' ) ) {
	WP_CLI::warning( 'No /checkout/ page found. Run bin/pages-local.php so the Enrol button has somewhere to go.' );
}
$checkout   = home_url( '/checkout/?offering=' . rawurlencode( $offering ) );
/* ---- Course intro bands (full-bleed, same width system as the Home "Coaching Programmes" band) ---- */
$lead_imgs = ezh_media_dir( 'leadership', '_ez37_lead' );  // icf-level-1.png, woman-desk.jpg, team-flipchart.jpg
$proc_imgs = ezh_media_dir( 'process', '_ez37_proc' );
$abt_imgs  = ezh_media_dir( 'about', '_ez37_about' );
$btn_enrol = '<a class="ez37-btn" href="' . esc_url( $checkout ) . '">Enrol Now ' . ezh_i( 'arrow' ) . '</a>';
$badge     = ! empty( $lead_imgs['icf-level-1'] ) ? '<img class="ezl-badge" src="' . esc_url( $lead_imgs['icf-level-1'] ) . '" alt="ICF Level 1 accredited coaching education" loading="lazy" width="150" height="150">' : '';

$sections[] = ezh_section(
	'ezl-band',
	'<div class="ezl-band__grid">
	<div class="ezl-band__text">
		<h2>Relate to the People You Work With in a <span class="ez37-hl">New Way</span></h2>
		<p class="ezl-band__lead">Learn to listen more effectively, ask questions that provoke and promote lasting transformation, and unlock the ROI only human-centered coaching skills can.</p>
		<p>EZ37 Solutions Leadership and Executive Coaching gives you the core coaching skills known throughout the world, and the real-time experience that makes them part of how you lead and create impact.</p>
		' . $btn_enrol . '
	</div>
	<div class="ezl-band__media">' . $badge . ( ! empty( $lead_imgs['woman-desk'] ) ? '<img class="ezl-band__img" src="' . esc_url( $lead_imgs['woman-desk'] ) . '" alt="A coach in conversation with a client" loading="lazy">' : '' ) . '</div>
	</div>'
);

$sections[] = ezh_section(
	'ezl-band is-flip',
	'<div class="ezl-band__grid">
	<div class="ezl-band__media">' . ( ! empty( $lead_imgs['team-flipchart'] ) ? '<img class="ezl-band__img" src="' . esc_url( $lead_imgs['team-flipchart'] ) . '" alt="A team workshop around a flipchart" loading="lazy">' : '' ) . '</div>
	<div class="ezl-band__text">
		<h2>Develop the People and Cultivate the <span class="ez37-hl">Trust That Drive Results</span></h2>
		<p class="ezl-band__lead">EZ37 coaching shifts the focus from solving problems for people to developing the people and relationships that drive performance, innovation, and change.</p>
		<p>This changes how you relate to the people around you.</p>
		<p>And you can put these coaching skills to work right away, whether that is with someone you manage, a colleague, or a client. This isn&rsquo;t another static framework or curriculum to commit to memory.</p>
		<p>You&rsquo;ll leave this course feeling seen and understood as a leader, equipped to bring the best out of yourself and those you&rsquo;re entrusted to lead and influence.</p>
		' . $btn_enrol . '
	</div>
	</div>'
);

/* ---- Who this course is for (placeholder photos: swap for real ones, same file names or edit the keys) ---- */
$for = array(
	array( $proc_imgs['step-1'] ?? '', 'People improving how they work with others', 'Anyone who wants to strengthen their working relationships.' ),
	array( $proc_imgs['step-2'] ?? '', 'Managers and team leads', 'Leaders who want to include coaching as part of how they lead.' ),
	array( $abt_imgs['inset'] ?? '', 'Individual contributors', 'People who collaborate across teams and want to work differently with others.' ),
	array( $proc_imgs['step-3'] ?? '', 'Internal coaches and HR', 'HR and talent development practitioners supporting growth in their organisation.' ),
	array( $abt_imgs['main'] ?? '', 'Consultants and facilitators', 'Change practitioners who want to add coaching skills to their work.' ),
	array( $abt_imgs['difference-main'] ?? ( $photo ), 'Organisations', 'Teams looking for a focused development experience their people can apply right away.' ),
);
$fc = '';
foreach ( $for as $f ) {
	$fc .= '<article class="ezl-for__card"><div class="ezl-for__img">' . ( $f[0] ? '<img src="' . esc_url( $f[0] ) . '" alt="" loading="lazy">' : '' ) . '</div><h3>' . esc_html( $f[1] ) . '</h3><p>' . esc_html( $f[2] ) . '</p></article>';
}
$sections[] = ezh_section(
	'ezl-for',
	'<div class="ezl-for__inner"><h2>Who This Course Is For</h2>
	<p class="ezl-for__sub">EZ37 Leadership and Executive Coaching is for those who want to bring coaching skills into their existing role so they can lead, manage, and develop people differently.</p>
	<div class="ezl-for__grid">' . $fc . '</div></div>'
);

$sections[] = ezh_section(
	'ezp-enrol',
	'<div class="ezh-wrap"><div class="ezp-enrol">
	<div><h2>Ready To Start Your <span class="ez37-hl">Leadership Journey?</span></h2>
	<p>Enrol online in a couple of minutes. You will pay securely on Stripe and receive your confirmation and programme material by email.</p></div>
	<div class="ezp-enrol__cta"><a class="ez37-btn ez37-btn--lg" href="' . esc_url( $checkout ) . '">Enrol Now ' . ezh_i( 'arrow' ) . '</a>
	<span class="ezp-enrol__note">' . ezh_i( 'lock' ) . 'Secure payment by Stripe</span></div>
	</div></div>'
);

ezh_save_page( $page->ID, $sections, array( 'ez37-redesign.css', 'ez37-home.css', 'ez37-programme.css', 'ez37-bhero.css', 'ez37-leadership.css' ) );

WP_CLI::success( 'Leadership/Executive Coaching v2 built: ' . get_permalink( $page->ID ) );
