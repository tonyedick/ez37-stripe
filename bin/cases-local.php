<?php
/**
 * EZ37 redesign — build the CASE STUDY pages (v2). LOCAL DEVELOPMENT ONLY.
 *
 * Two levels, both driven by the $cases and $categories arrays below:
 *   - one page per case study ("the full story");
 *   - one page per Case Studies menu item (a category) that lists every case study in that category.
 * To add a case study: add an entry to $cases and put its slug in the right category's list.
 * Content comes from the client's case study PDFs. The percentages on the home page card are NOT in those PDFs.
 *
 *   ddev exec wp --path=/var/www/html/wordpress eval-file /var/www/html/bin/cases-local.php
 *
 * Run header-footer-local.php first (it creates the four category pages used by the menu).
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

$css   = array( 'ez37-redesign.css', 'ez37-home.css', 'ez37-programme.css', 'ez37-bhero.css', 'ez37-leadership.css', 'ez37-components.css', 'ez37-pcert.css', 'ez37-cases.css' );
$imgs  = ezh_media_dir( 'cases', '_ez37_case_img' );
$book  = ezh_calendly_url();

/* ---- Categories = the Case Studies menu items ---- */
$categories = array(
	'cases-team-coaching'       => array( 'Team Coaching', 'How team coaching has helped organisations build synergy, cohesion and high performance.', array( 'case-team-coaching-fmcg' ) ),
	'cases-pccp'                => array( 'Professional Coaching Certification Programme (PCCP)', 'How our ICF-accredited certification programme has equipped organisations with certified coaches.', array( 'case-pccp-financial-institution' ) ),
	'cases-one-to-one-coaching' => array( 'One-to-One Coaching', 'How one-to-one coaching has supported individuals to grow in confidence, communication and decision-making.', array( 'case-one-to-one-foundation' ) ),
	'cases-coaching-skills'     => array( 'Coaching Skills Training for Managers and Leaders', 'How coaching skills training has helped managers and leaders improve performance, communication and retention.', array( 'case-coaching-skills-multinational' ) ),
);

/* ---- Case studies (text from the client PDFs) ---- */
$cases = array(
	'case-team-coaching-fmcg' => array(
		'category' => 'cases-team-coaching',
		'title'    => 'A global FMCG Organisation',
		'tagline'  => 'Building team synergy and high performance in an HR function through a seven-month coaching intervention',
		'img'      => 'case-team-coaching-hd',
		'need'     => array( 'To enhance team synergy and cohesion within their Human Resources department to drive improved performance. To cultivate a mindset shift, deeper engagement, self-reflection, and adaptability among team members.' ),
		'action'   => array( 'We designed a seven-month coaching intervention tailored to the team&rsquo;s specific needs to achieve this. We began with an assessment to pinpoint areas for growth, which guided our coaching sessions focused on team mindset, psychological safety, and high performance. Following each session, participants engaged in reflective activities in smaller groups to deepen their understanding and apply what they had learned.' ),
		'outcome'  => array(
			'items' => array(
				array( 'Enhanced Team Cohesion and Performance', 'The team demonstrated significant improvements in synergy, cohesion, and psychological safety, leading to increased accountability, responsibility, and ownership.' ),
				array( 'Elevated Emotional Intelligence', 'Participants developed heightened emotional intelligence, fostering stronger relationships and improved team dynamics.' ),
				array( 'Empathetic Communication', 'Communication styles became more compassionate, enhancing collaboration and understanding within the team.' ),
				array( 'Effective Leadership', 'Active listening skills were strengthened, leading to more effective leadership practices.' ),
			),
		),
	),
	'case-pccp-financial-institution' => array(
		'category' => 'cases-pccp',
		'title'    => 'A leading Financial Institution',
		'tagline'  => 'Developing a team of certified coaches across the nation',
		'img'      => 'case-pccp-hd',
		'need'     => array( 'The institution sought to elevate the professionalism of its leadership by developing a team of certified coaches across the nation.' ),
		'action'   => array( 'Accredited by the International Coaching Federation to deliver the Level 1 program to certify coaches, we introduced a 60-hour synchronous and asynchronous coach training program with 10 hours of mentor coaching (7 hours of group coaching and 3 hours of one-to-one coaching). The sessions covered Coaching and Coaching Mindset, Self-Awareness, Maintains Ethical Standards, Establish and Maintains Agreement, Cultivates Trust and Safety, Maintains Presence, Listens Actively, Evokes Awareness, Facilitates Client Growth and Doing of a Coach with the participants utilizing the LMS and having further interactions in pods.' ),
		'outcome'  => array(
			'lead'  => 'The outcome of our coaching program was a profound transformation, as participants demonstrated significant enhancements in their coaching skills for leaders and managers, including:',
			'items' => array(
				array( 'Active Listening', 'Participants developed the ability to attentively hear and understand their clients, leading to deeper connections and more effective communication.' ),
				array( 'Evoking Awareness', 'Participants learned to skillfully guide clients to uncover new insights, perspectives, and motivations, fostering personal growth and development.' ),
				array( 'Facilitating Client Growth', 'Participants became adept at empowering clients to take ownership of their goals, make informed decisions, and achieve success.' ),
			),
			'ripple' => 'The improved coaching skills for leaders and managers and mindset of the participants had a positive ripple effect on the organisation as a whole, resulting in:',
			'items2' => array(
				array( 'Enhanced Team Dynamics', 'Improved communication, collaboration, and trust among team members led to a more cohesive and productive work environment.' ),
				array( 'Increased Productivity', 'As participants applied their new coaching skills for leaders and managers, they were able to facilitate greater efficiency, motivation, and goal achievement within their teams.' ),
			),
			'outro' => array( 'Our coaching program not only equipped participants with the skills and knowledge necessary to become effective coaches but also profoundly impacted organisational performance.', 'By developing the coaching skills for leaders and managers of our participants, we empowered them to drive positive change within their teams and the organisation as a whole.' ),
		),
	),
	'case-one-to-one-foundation' => array(
		'category' => 'cases-one-to-one-coaching',
		'title'    => 'A leading Not for Profit Organisation',
		'tagline'  => 'One-to-one coaching for a scholar: self-awareness, communication, and decision-making',
		'img'      => 'case-one-to-one-hd',
		'need'     => array( 'The Foundation sought to develop well-rounded scholars by providing coaching focused on three key areas: self-awareness, communication and performance, and problem-solving and decision-making.' ),
		'action'   => array(
			'The coaching engagement began with a pre-DISC (DISC) assessment, which provided a comprehensive understanding of the scholar&rsquo;s behavioral style. This assessment helped identify areas for improvement and informed the coaching approach.',
			'<strong>Coaching Sessions:</strong> Nine one-on-one coaching sessions were conducted over three months to support the scholar&rsquo;s growth and development. The sessions focused on building self-awareness, improving communication skills, and enhancing problem-solving and decision-making abilities.',
			'<strong>Post-Assessment:</strong> Three months after the initial sessions, a post-DISC assessment was conducted to measure the impact of the coaching. This evaluation revealed significant improvements in the scholar&rsquo;s behavioral style, including: increased self-awareness and confidence; improved communication and interpersonal skills; and enhanced problem-solving and decision-making abilities.',
			'<strong>Follow-up Coaching:</strong> Due to the success of the initial coaching sessions, the scholar opted for additional coaching sessions to integrate their newly acquired insights and skills further. Ten additional coaching sessions were delivered over a year and a half, with pre- and post-DISC assessments to guide the scholar&rsquo;s progress.',
		),
		'outcome'  => array(
			'lead'  => 'Key changes:',
			'list'  => array( 'The scholar transitioned from a reactive, fact-finding role to a proactive, coaching-oriented role.', 'They developed a deeper understanding of their strengths, weaknesses, and motivations, leading to increased self-awareness and confidence.', 'The scholar improved their communication ability, built strong relationships, and drove results.', 'They demonstrated increased adaptability and resilience and were better equipped to navigate complex situations.' ),
			'quote' => array( 'Coach Adaora has been a game-changer in my personal and professional development journey. Her insightful guidance, unwavering support, and empathetic approach have helped me achieve my goals and empowered me with a newfound sense of confidence and clarity. Her ability to listen attentively, provide constructive feedback, and tailor her coaching to my unique needs has been invaluable. I feel incredibly fortunate to have worked with such an exceptional coach, and I wholeheartedly recommend her to anyone seeking to unlock their full potential and make meaningful progress towards their goals. Thank you, Coach Adaora, for your outstanding dedication and expertise!', 'Feedback from the scholar' ),
		),
	),
	'case-coaching-skills-multinational' => array(
		'category' => 'cases-coaching-skills',
		'title'    => 'Leading Multi-National Financial Institution',
		'tagline'  => 'Coaching skills for managers and leaders that lifted performance, communication and retention',
		'img'      => 'case-coaching-skills-hd',
		'need'     => array( 'Provide managers and leaders with techniques to facilitate thought-provoking coaching conversations that inspire team members and enhance performance.' ),
		'action'   => array( 'Our coaching skills training for managers and leaders facilitated by a team of certified International Coach Federation (ICF) coaches, utilised proven coaching methodologies and resources to deliver a dynamic and engaging learning experience. Participants actively engaged in reflective dialogue, creating a collaborative environment to leverage group strengths and provide valuable feedback. This approach empowered participants to enhance their performance as managers and leaders significantly. To measure the impact of the training, we employed pre- and post-assessments to evaluate its effectiveness across Kirkpatrick&rsquo;s application levels.' ),
		'outcome'  => array(
			'stat'  => array( '80%', 'increase in knowledge of essential coaching skills for leaders and managers' ),
			'lead'  => 'Our program delivered significant results, with participants demonstrating an 80% increase in knowledge of essential coaching skills for leaders and managers, including feedback, communication, active listening, and the GROW model. Within three months, these gains translated into tangible improvements:',
			'items' => array(
				array( 'Enhanced understanding of coaching&rsquo;s power', 'Participants better-understood coaching&rsquo;s impact on employee engagement, retention, and overall team performance.' ),
				array( 'Mastering coaching techniques', 'Participants effectively implemented various coaching skills for leaders and managers, leading to improved communication, collaboration, and problem-solving within their teams.' ),
				array( 'Navigating generational differences', 'Participants acquired valuable strategies for effectively managing and engaging with diverse generations in the workplace, fostering a more inclusive and productive environment.' ),
				array( 'Actionable learning', 'Participants developed personalized action plans based on their new coaching knowledge, ensuring the immediate application of their learnings.' ),
				array( 'Empowering team members', 'The program empowered participants to coach their direct reports, leading to increased career progression and professional development for their teams.' ),
				array( 'Reduced fatigue and improved well-being', 'Participants reported decreased work-related fatigue and enhanced work-life balance, leading to greater job satisfaction and overall well-being.' ),
			),
			'outro' => array( 'By equipping our participants with the tools and knowledge they needed to become effective coaches, we witnessed a positive ripple effect throughout the organisation, fostering a more engaged, empowered, and productive workforce.' ),
		),
	),
);

/* ---- helpers ---- */
$page_for = static function ( string $slug, string $title ) {
	$full = $slug . '-v2';
	$page = get_page_by_path( $full, OBJECT, 'page' );
	if ( ! $page ) {
		$id = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'private', 'post_title' => $title, 'post_name' => $full ), true );
		if ( is_wp_error( $id ) ) {
			WP_CLI::error( $id->get_error_message() );
		}
		update_post_meta( $id, '_wp_page_template', 'elementor_header_footer' );
		$page = get_post( $id );
	}
	return $page;
};
$cta = static function ( string $h2, string $p, string $label, string $href ): array {
	return ezh_section(
		'ezp-enrol ezc-cta',
		'<div class="ezh-wrap"><div class="ezp-enrol"><div><h2>' . $h2 . '</h2><p>' . $p . '</p></div><div class="ezp-enrol__cta"><a class="ez37-btn ez37-btn--lg" href="' . esc_url( $href ) . '">' . esc_html( $label ) . ' ' . ezh_i( 'arrow' ) . '</a></div></div></div>'
	);
};
$paras = static fn( array $a ): string => implode( '', array_map( static fn( $t ) => '<p>' . $t . '</p>', $a ) );

/* ---- 1. one page per case study ---- */
foreach ( $cases as $slug => $c ) {
	$cat   = $categories[ $c['category'] ];
	$page  = $page_for( $slug, $c['title'] );
	$o     = $c['outcome'];
	$img   = $imgs[ $c['img'] ] ?? '';
	$cards = static function ( array $items ): string {
		$h = '';
		foreach ( $items as $it ) {
			$h .= '<div class="ezc-card"><h3>' . wp_kses( $it[0], array() ) . '</h3><p>' . esc_html( html_entity_decode( $it[1] ) ) . '</p></div>';
		}
		return '<div class="ezc-cards">' . $h . '</div>';
	};

	$out = '<div class="ezc-wrap"><h2>Transformational <span class="ez37-hl">Outcome</span></h2>';
	if ( ! empty( $o['stat'] ) ) {
		$out .= '<p class="ezc-stat"><b>' . esc_html( $o['stat'][0] ) . '</b><span>' . esc_html( $o['stat'][1] ) . '</span></p>';
	}
	if ( ! empty( $o['lead'] ) ) {
		$out .= '<p class="ezc-outcome__lead">' . wp_kses( $o['lead'], array() ) . '</p>';
	}
	if ( ! empty( $o['list'] ) ) {
		$out .= '<div class="ezc-block"><ul>' . implode( '', array_map( static fn( $t ) => '<li>' . esc_html( $t ) . '</li>', $o['list'] ) ) . '</ul></div>';
	}
	if ( ! empty( $o['items'] ) ) {
		$out .= $cards( $o['items'] );
	}
	if ( ! empty( $o['ripple'] ) ) {
		$out .= '<p class="ezc-outcome__lead">' . esc_html( $o['ripple'] ) . '</p>' . $cards( $o['items2'] );
	}
	foreach ( (array) ( $o['outro'] ?? array() ) as $t ) {
		$out .= '<p class="ezc-outcome__lead">' . esc_html( $t ) . '</p>';
	}
	if ( ! empty( $o['quote'] ) ) {
		$out .= '<blockquote class="ezc-quote"><p>&ldquo;' . esc_html( $o['quote'][0] ) . '&rdquo;</p><cite>' . esc_html( $o['quote'][1] ) . '</cite></blockquote>';
	}
	$out .= '<p class="ezc-more"><a class="ez37-btn-link" href="' . esc_url( ezh_url( $c['category'] ) ) . '"><span>More ' . esc_html( $cat[0] ) . ' case studies</span>' . ezh_i( 'arrow' ) . '</a></p></div>';

	$sections   = array();
	$sections[] = ezh_banner_hero( $c['title'], $c['tagline'], array(), $img, '', array( array( 'Case Studies', ezh_url( 'case-studies' ) ), array( $cat[0], ezh_url( $c['category'] ) ), $c['title'] ), true );
	$sections[] = ezh_section(
		'ezc-story',
		'<div class="ezc-wrap"><div class="ezc-story__grid"><div class="ezc-story__img">' . ( $img ? '<img src="' . esc_url( $img ) . '" alt="" loading="lazy">' : '' ) . '<p class="ezc-client">The client<b>' . esc_html( $c['title'] ) . '</b></p></div>'
		. '<div><div class="ezc-block"><h2>The <span class="ez37-hl">Need</span></h2>' . $paras( $c['need'] ) . '</div>'
		. '<div class="ezc-block"><h2>The <span class="ez37-hl">Intervention</span></h2>' . $paras( $c['action'] ) . '</div></div></div></div>'
	);
	$sections[] = ezh_section( 'ezc-outcome', $out );
	$sections[] = $cta( 'Want Results <span class="ez37-hl">Like These?</span>', 'Tell us what your organisation needs and we will design a coaching programme around it.', 'Book a discovery call', $book );
	ezh_save_page( $page->ID, $sections, $css );
	WP_CLI::log( 'Case study built: ' . get_permalink( $page->ID ) );
}

/* ---- 2. one page per category (lists every case study in it) ---- */
foreach ( $categories as $slug => $cat ) {
	$page  = $page_for( $slug, $cat[0] );
	$tiles = '';
	foreach ( $cat[2] as $cs ) {
		$c      = $cases[ $cs ];
		$tiles .= '<article class="ezc-tile"><div class="ezc-tile__img">' . ( ! empty( $imgs[ $c['img'] ] ) ? '<img src="' . esc_url( $imgs[ $c['img'] ] ) . '" alt="" loading="lazy">' : '' ) . '</div>'
			. '<div class="ezc-tile__body"><small>Case study</small><h3>' . esc_html( $c['title'] ) . '</h3><p>' . esc_html( $c['tagline'] ) . '</p>'
			. '<a class="ez37-btn-link" href="' . esc_url( ezh_url( $cs ) ) . '"><span>Read the full story</span>' . ezh_i( 'arrow' ) . '</a></div></article>';
	}
	$sections   = array();
	$sections[] = ezh_banner_hero( $cat[0], $cat[1], array(), $imgs[ $cases[ $cat[2][0] ]['img'] ] ?? '', '', array( array( 'Case Studies', ezh_url( 'case-studies' ) ), $cat[0] ), true );
	$sections[] = ezh_section( 'ezc-list', '<div class="ezc-wrap"><h2>' . esc_html( $cat[0] ) . ' <span class="ez37-hl">Case Studies</span></h2><p class="ezc-list__lead">' . esc_html( $cat[1] ) . '</p><div class="ezc-grid">' . $tiles . '</div></div>' );
	$sections[] = $cta( 'Ready To Talk <span class="ez37-hl">About Your Goals?</span>', 'Book a discovery call and we will show you what is possible for your team.', 'Book a discovery call', $book );
	ezh_save_page( $page->ID, $sections, $css );
	WP_CLI::log( 'Category built: ' . get_permalink( $page->ID ) );
}

WP_CLI::success( 'Case study pages built.' );
