<?php
/**
 * EZ37 redesign — build the SOLUTIONS (v2) page. LOCAL DEVELOPMENT ONLY.
 *
 * Banner (same as About) + the three feature bands moved from Home:
 * Coaching programmes, Assessments, Learning and certification.
 *
 * Run (after header-footer-local.php):
 *   ddev exec wp --path=/var/www/html/wordpress eval-file /var/www/html/bin/solutions-local.php
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

$page = get_page_by_path( 'solutions-v2', OBJECT, 'page' );
if ( ! $page ) {
	WP_CLI::error( 'Page solutions-v2 not found. Run bin/header-footer-local.php first.' );
}

require_once __DIR__ . '/lib/ezh-lib.php';

$solution  = ezh_url( 'coaching' );
$img_about = ezh_media_dir( 'about', '_ez37_about' );
$img_case  = ezh_media_dir( 'case', '_ez37_case' );
$img_sol = ezh_media_dir( 'solutions', '_ez37_solutions' );
$big       = $img_sol['solutions-hero'] ?? ( $img_about['hero-workshop'] ?? ( $img_case['case-study-meeting'] ?? '' ) );

$sections   = array();
$sections[] = ezh_banner_hero( 'Solutions', '', array(), $big, 'Four smiling EZ37 clients and business professionals', 'Solutions', true );


// Eight service areas, content from the EZ37 brochure. Layout cycles through three looks:
//   0 = text left / image right + callout + button (Coaching programmes look)
//   1 = image left / text right + text link        (Assessments look)
//   2 = text left / image right + callout + button (Learning and certification look)
// Each band: eyebrow, h2, intro, extra line (callout for looks 0/2, plain line for look 1), button/link label, link, image URL, image focal point.
$bands = array(
	array( 'Coaching', 'Join Over 2,887 Others Utilising the EZ37 Solutions Framework', 'Our ICF-credentialed coaches support leaders and teams to turn intent into results. Every engagement is built around your organisation&rsquo;s goals and grounded in the ICF Code of Ethics.', '<strong>Executive Coaching</strong><br><strong>Team Coaching</strong><br>Group Coaching for <strong>clearer goals, stronger accountability and a coaching culture that becomes part of how you work.</strong>', 'Get Started', ezh_url( 'contact-us' ), $img_about['coaching-programmes-hd'] ?? ( $img_about['coaching-programmes'] ?? '' ), '60% 20%' ),
	array( 'Coaching skills for leaders and managers', 'Equip every leader to coach', 'A practical programme that builds coaching into everyday management conversations. We link it to your performance framework and values, so coaching becomes part of how work gets done.', '<br><strong>The programme journey:</strong> the leader as coach, listening and asking thought-provoking questions, ongoing feedback, and embedding through peer practice and follow-up.', 'Explore Coaching Skills', ezh_url( 'coaching-skills-leaders-managers' ), $img_sol['coaching-skills-leaders'] ?? '', '45% 50%' ),
	array( 'Coach certification', 'ICF-accredited programmes, from first credential to mentor coach', 'Virtual, ICF-accredited coach education taught by ICF-credentialed facilitators, with live sessions, observed coaching, mentor coaching and peer POD groups.', '<strong>Level 1 Professional Coach Certification<br>Level 2 Advanced Coach Training<br>Advanced Coach Certification</strong><br>Mentor coaching pathways for experienced coaches.', 'Get Started', ezh_url( 'contact-us' ), $img_sol['coach-certification'] ?? '', '90% 50%' ),
	array( 'Facilitation and training', 'What Do New Managers Need Most? Training &amp; Support', 'Practical, engaging programmes delivered online, in person or blended, and tailored to your organisation&rsquo;s needs.', 'Facilitating learning online and in person, bespoke learning solutions, corporate and institutional training, <strong>capacity building and mentoring for leaders who develop others.</strong>', 'Explore Facilitation', ezh_url( 'facilitation-and-learning' ), $img_sol['facilitation-training'] ?? '', '38% 50%', 'If your organisation wants to strengthen both your leadership pipeline and your bottom line, <strong>investing in the least experienced tier of managers in your organisation, your largest population of leaders &mdash; will produce real results.</strong>' ),
	array( 'Storytelling and culture', 'Compounding Cultural Capital: Leveraging Storytelling for Measurable Impact', 'People forget slides but remember stories. We support leaders and organisations to find, shape and tell stories that build trust, align teams and drive change, and we weave storytelling through our coaching, training and events.', 'Our SCENE framework:<br>Setting, Challenge, Emotion, New reality and Essence.', 'Get Started', ezh_url( 'contact-us' ), $img_sol['storytelling-culture'] ?? '', '10% 50%' ),
	array( 'Design thinking', 'Solve the right problem, with the people closest to it', 'Design thinking puts people at the centre of problem-solving. We guide your teams through a structured, collaborative process from understanding challenges to developing and testing solutions. We also apply design thinking in the design and delivery of our own services.', '', 'Explore Design Thinking', ezh_url( 'design-thinking' ), $img_sol['design-thinking'] ?? '', '55% 50%', '<strong>Our five-step process:<br>Empathise, Define, Ideate, Prototype and Test.</strong>' ),
	array( 'Business and management consulting', 'Turning strategy into people practice', 'We work alongside leadership, HR and talent teams to design the frameworks, structures and processes that enable people to perform effectively. We reinforce these efforts through coaching and development to make positive behaviours sustainable.', 'Performance management, talent and succession, organisation design, HR advisory, <strong>culture and change, and leadership strategy sessions.</strong>', 'Get Started', ezh_url( 'contact-us' ), $img_sol['business-consulting'] ?? '', '45% 50%' ),
	array( 'Assessment services', 'Insight that powers development', 'Our assessments provide organisations with reliable data for talent acquisition, development and retention, and give individuals a clear starting point for growth.', 'Leadership Effectiveness 360 and the DISC Personality Assessment, run as brief, assess and debrief with certified coaches.', 'Explore Assessments', ezh_url( 'assessments' ), $img_sol['assessment-services'] ?? '', '75% 50%' ),
);
// Public Sector Solutions: same look as Assessment services (image left, text right, link), tinted, as the first band after the banner.
$ps_li  = static function ( array $items ): string {
	return '<ul>' . implode( '', array_map( static function ( $t ) { return '<li>' . esc_html( $t ) . '</li>'; }, $items ) ) . '</ul>';
};
$ps_img = '<div class="ezs-band__img is-fixed">' . ( ! empty( $img_sol['public-sector-whiteboard'] ) ? '<img src="' . esc_url( $img_sol['public-sector-whiteboard'] ) . '" alt="A strategy meeting around a whiteboard" loading="lazy" style="object-position:40% 50%">' : '' ) . '</div>';
$ps_txt = '<div class="ezs-band__text"><span class="ezs-eyebrow">Public Sector Solutions</span>'
	. '<h2 class="ezs-h2">Turn policy into delivered outcomes</h2>'
	. '<p>Our Learning and Capacity Development programmes have strengthened hundreds of public sector officials, improving institutional performance, communication, organisational effectiveness, governance practices, and service delivery across government MDAs.</p>'
	. '<p><strong>Why EZ37 Solutions</strong></p>'
	. $ps_li( array(
		'Our public sector work is informed by the same executive coaching and HR advisory expertise we bring to leading private organisations.',
		'Established experience working within complex, accountability-driven environments.',
		'Outcome-focused: every engagement is designed around measurable delivery, not training for training’s sake.',
	) )
	. '<a class="ezs-link" href="' . esc_url( ezh_url( 'contact-us' ) ) . '">Talk to us about public sector</a></div>';
$sections[] = ezh_section( 'ezs-band is-bleed is-flip', '<div class="ezs-band__grid" id="public-sector">' . $ps_img . $ps_txt . '</div>' );

$band_ids = array( 'coaching', 'coaching-skills', 'coach-certification', 'facilitation', 'storytelling', 'design-thinking', 'consulting', 'assessments' );
foreach ( $bands as $i => $b ) {
	$flip = ( 1 === $i % 2 ); // odd bands = image left, text link; even bands = text left, callout + button
	$img  = '<div class="ezs-band__img">' . ( $b[6] ? '<img src="' . esc_url( $b[6] ) . '" alt="" loading="lazy" style="object-position:' . esc_attr( $b[7] ) . '">' : '' ) . '</div>';
	$txt  = '<div class="ezs-band__text"><span class="ezs-eyebrow">' . esc_html( $b[0] ) . '</span><h2 class="ezs-h2">' . wp_kses( $b[1], array() ) . '</h2><p>' . wp_kses( $b[2] . ( $flip ? ' ' . $b[3] : '' ), array( 'strong' => array(), 'br' => array() ) ) . '</p>'
		. ( ! empty( $b[8] ) ? '<p class="ezs-band__extra is-callout">' . wp_kses( $b[8], array( 'strong' => array(), 'br' => array() ) ) . '</p>' : ( $flip ? '' : '<p class="ezs-band__extra is-callout">' . wp_kses( $b[3], array( 'strong' => array(), 'br' => array() ) ) . '</p>' ) )
		. '<a class="' . ( $flip ? 'ezs-link' : 'ez37-btn' ) . '" href="' . esc_url( $b[5] ) . '">' . esc_html( $b[4] ) . ( $flip ? '' : ' ' . ezh_i( 'arrow' ) ) . '</a></div>';
	$grid = '<div class="ezs-band__grid" id="' . esc_attr( $band_ids[ $i ] ) . '">' . ( $flip ? $img . $txt : $txt . $img ) . '</div>';
	$sections[] = ezh_section( 'ezs-band is-bleed' . ( $flip ? ' is-flip' : '' ), $grid );
}


ezh_save_page( $page->ID, $sections, array( 'ez37-redesign.css', 'ez37-home.css', 'ez37-simple.css', 'ez37-bhero.css', 'ez37-solutions.css' ) );

WP_CLI::success( 'Solutions v2 built: ' . get_permalink( $page->ID ) );
