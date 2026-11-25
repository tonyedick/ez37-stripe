<?php
/**
 * EZ37 redesign — build the EZ PEER TO PEER COACHING page (v2). LOCAL DEVELOPMENT ONLY.
 *
 * A free programme for EZ Coaching alumni. The page only links to /checkout/?offering=peer-to-peer,
 * where the payment plugin shows the registration form and the Peer-to-Peer Agreement; no payment
 * is involved (the offering is marked "free" in ez37-payments/config/offerings.php).
 *
 *   ddev exec wp --path=/var/www/html/wordpress eval-file /var/www/html/bin/peer-local.php
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
require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

$slug = 'peer-to-peer-coaching-v2';
$page = get_page_by_path( $slug, OBJECT, 'page' );
if ( ! $page ) {
	$new  = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'private', 'post_title' => 'Peer to Peer Coaching', 'post_name' => $slug ), true );
	$page = is_wp_error( $new ) ? null : get_post( $new );
}
if ( ! $page ) {
	WP_CLI::error( 'Could not create the page.' );
}

/* Upload the welcome video once (the images go through ezh_media_dir). */
$video_url = '';
$vfile     = dirname( __DIR__ ) . '/design/peer/welcome.mp4';
if ( file_exists( $vfile ) ) {
	$ids = get_posts( array( 'post_type' => 'attachment', 'post_status' => 'inherit', 'meta_key' => '_ez37_peer', 'meta_value' => 'welcome.mp4', 'fields' => 'ids', 'posts_per_page' => 1 ) );
	if ( $ids ) {
		$att = (int) $ids[0];
	} else {
		$tmp = wp_tempnam( 'welcome.mp4' );
		copy( $vfile, $tmp );
		$att = media_handle_sideload( array( 'name' => 'ez-peer-to-peer-welcome.mp4', 'tmp_name' => $tmp ), 0, 'EZ Peer to Peer welcome video' );
		if ( is_wp_error( $att ) ) {
			WP_CLI::warning( 'Video upload failed: ' . $att->get_error_message() );
			$att = 0;
		} else {
			update_post_meta( $att, '_ez37_peer', 'welcome.mp4' );
		}
	}
	$video_url = $att ? (string) wp_get_attachment_url( $att ) : '';
}

$img      = ezh_media_dir( 'peer', '_ez37_peer_img' );
$register = home_url( '/checkout/?offering=peer-to-peer' );
$btn      = '<a class="ez37-btn ez37-btn--lg" href="' . esc_url( $register ) . '">Get Started ' . ezh_i( 'arrow' ) . '</a>';

$sections   = array();
$sections[] = ezh_banner_hero(
	'EZ Peer to Peer Coaching',
	'Free for EZ Coaching alumni: coach and be coached by fellow certified coaches to build your ICF coaching hours.',
	array(),
	$img['peer-laptop-hd'] ?? '',
	'',
	array( array( 'Solutions', ezh_url( 'solutions' ) ), 'Peer to Peer Coaching' ),
	true
);

$sections[] = ezh_section(
	'ezpe-intro',
	'<div class="ezpe-intro__grid"><div class="ezpe-intro__text"><h2>Welcome to the EZ Coaching <span class="ez37-hl">Peer to Peer Programme</span></h2><p>As an ICF Accredited Coach Education Provider we appreciate that coaches may require support to accumulate qualification practice coaching hours towards an ICF credential. Whether your goal is ACC, PCC or MCC, EZ Coaching Peer to Peer gives you access to a network of certified and credential coaches.</p><div class="ezpe-cta">' . $btn . '<small>Free of charge. No payment is taken.</small></div></div>'
	. '<div class="ezpe-intro__media">' . ( $video_url ? '<div class="ezpe-video"><video controls preload="metadata" playsinline' . ( ! empty( $img['peer-poster'] ) ? ' poster="' . esc_url( $img['peer-poster'] ) . '"' : '' ) . '><source src="' . esc_url( $video_url ) . '" type="video/mp4">Your browser does not support embedded video.</video></div>' : '' ) . '</div></div>'
);

$sections[] = ezh_section(
	'ezs-quote',
	'<div class="ezh-wrap"><figure class="ezs-quote__fig"><span class="ezs-quote__mark" aria-hidden="true">&ldquo;</span><blockquote>Typically, the more experienced the coach, the higher the reported average fee per one-hour coaching session, the greater the number of clients, and the more weekly hours spent working as a coach.</blockquote><figcaption>2023 ICF Global Coaching Study</figcaption></figure></div>'
);

$sections[] = ezh_section(
	'ezpe-rows',
	'<div class="ezpe-row"><div><h2>How EZ Peer to Peer Coaching <span class="ez37-hl">works</span></h2><ol>'
	. '<li>Complete and submit the enrolment form.</li>'
	. '<li>You will receive an email with the link to the list of participants in the peer to peer programme.</li>'
	. '<li>Select and contact your preferred coaching partner(s) from the list we provide in the email.</li>'
	. '<li>Set up a coaching agreement to include a mutually agreed timeframe and number of sessions. You would coach each other at each meeting for at least one hour, so allow 2 hours per meeting.</li>'
	. '</ol></div><div class="ezpe-row__img">' . ( ! empty( $img['peer-people-hd'] ) ? '<img src="' . esc_url( $img['peer-people-hd'] ) . '" alt="Three smiling coaches" loading="lazy">' : '' ) . '</div></div>'
);

/* Requirements: same split as About > Why Clients Choose EZ37 (full-bleed image left, dark panel right). */
$sections[] = ezh_section(
	'ezpr-req',
	'<div class="ezpr-req__inner"><figure class="ezpr-req__media">' . ( ! empty( $img['peer-laptop-hd'] ) ? '<img src="' . esc_url( $img['peer-laptop-hd'] ) . '" alt="Coaches on a video call" loading="lazy">' : '' ) . '</figure>'
	. '<div class="ezpr-req__text"><h2>The requirements to join EZ Peer to Peer Coaching</h2><ul class="ezpr-list">'
	. '<li>Complete an ICF accredited coach training programme (Level 1, Level 2, ACSTH or ACTP or Portfolio Path).</li>'
	. '<li>Be accountable for your progress.</li>'
	. '<li>Agree to abide by the ICF code of conduct.</li>'
	. '<li>Agree to be supportive and respectful of others.</li>'
	. '<li class="ezpr-rules"><span class="ezpr-tip" tabindex="0">Rules<span class="ezpr-tip__box" role="tooltip"><strong>It is free to join the EZ Coaching Peer to Peer.</strong> We reserve the right to remove you from access to the programme if you violate the rules:<ol>'
	. '<li>Before you can access the programme, you must first sign the Peer-to-Peer Agreement. The Agreement governs all peer-to-peer interactions conducted through our programme.</li>'
	. '<li>Only coaches certified through an ICF accredited programme and ICF credential coaches are eligible to participate.</li>'
	. '<li>You and your peer can record the sessions if you both agree. The recording will not be available on EZ Peer to Peer.</li></ol></span></span></li>'
	. '</ul></div></div>'
);

$sections[] = ezh_section_faq(
	array(
		array( 'What is peer to peer coaching?', 'The exchange of coaching between two people who are coaches is known as peer-to-peer coaching.' ),
		array( 'How is peer to peer coaching considered as paid hours?', 'Peer-to-peer coaching can be claimed as paid hours if it takes place outside of an educational or training programme.' ),
		array( 'How do I verify my coaching experience?', 'For information about verification of coaching hours, see the ICF experience requirements: https://coachingfederation.org/experience-requirements' ),
		array( 'What are the benefits of EZ Coaching Peer to Peer?', '1. Free to enrol. 2. Achieve your goal to become an ICF credentialed coach. 3. Become accountable to your goals.' ),
		array( 'Are the sessions recorded?', 'EZ Coaching Peer to Peer does not record sessions. Peer coaches can record the session provided both agree to this. The coaching conversation is confidential to both peers.' ),
		array( 'How many sessions are there?', 'The number of sessions is decided upon by the peer coaches. We advise that you allow 2 hours per session so that a pair of coaches can take turns to coach each other.' ),
		array( 'What is the cost of Peer to Peer?', 'This is a free opportunity to support coaches. There is no charge.' ),
		array( 'How are peers selected?', 'You are responsible for contacting the coach to arrange the peer to peer sessions.' ),
		array( 'How are the sessions held?', 'You may hold sessions via Zoom or a channel that is convenient for you and your peer coach.' ),
		array( 'What language is spoken for peer coaching?', 'We recommend English.' ),
		array( 'Do you provide feedback on my coaching?', 'Our mentor coaches are available to provide feedback as required, at a fee. You may also enrol for our virtual coach certification programme, which provides you with coach training and mentor coaching.' ),
		array( 'What is the minimum/maximum number of sessions I can attend?', 'You determine how many sessions you wish to attend. Please ensure that you keep all the appointments and show respect to your peer.' ),
		array( 'What resources are available for a coaching session?', 'A sample coaching agreement is available from the ICF: https://coachfederation.org/app/uploads/2017/11/SampleCoachingAgreement.pdf' ),
	)
);

$sections[] = ezh_section(
	'ezp-enrol',
	'<div class="ezh-wrap"><div class="ezp-enrol">
	<div><h2>Ready To <span class="ez37-hl">Coach and Be Coached?</span></h2>
	<p>Register in a couple of minutes. It is free for EZ Coaching alumni and no payment is taken.</p></div>
	<div class="ezp-enrol__cta">' . $btn . '</div>
	</div></div>'
);

ezh_save_page( $page->ID, $sections, array( 'ez37-redesign.css', 'ez37-home.css', 'ez37-programme.css', 'ez37-bhero.css', 'ez37-leadership.css', 'ez37-components.css', 'ez37-pcert.css', 'ez37-quote.css', 'ez37-peer.css' ) );

WP_CLI::success( 'Peer to Peer Coaching built: ' . get_permalink( $page->ID ) );
