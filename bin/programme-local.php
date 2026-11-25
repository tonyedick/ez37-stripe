<?php
/**
 * EZ37 redesign — build a PAID PROGRAMME page (v2). LOCAL DEVELOPMENT ONLY.
 *
 * One reusable template for every programme that is sold through the EZ37 Payments checkout.
 * Add a programme = add one entry to $programmes below (and its offering to
 * ez37-payments/config/offerings.php). The page only LINKS to /checkout/?offering=<key>;
 * the price, the enrolment form and the agreement all come from the payment plugin, so nothing
 * payment-related is built into this page.
 *
 *   ddev exec wp --path=/var/www/html/wordpress eval-file /var/www/html/bin/programme-local.php            (PCCP)
 *   ddev exec wp --path=/var/www/html/wordpress eval-file /var/www/html/bin/programme-local.php -- accp     (another key)
 *
 * Run header-footer-local.php first (it creates the private pccp-v2 page) and pages-local.php (checkout pages).
 *
 * COPY STATUS: programme content is the client's text from the previous EZ Coaching page (modules A-E, overview,
 * benefits, delivery, goals, policies). Faculty and testimonials come from the EZ37 brochure and the existing site.
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

$calendly = ezh_calendly_url();

/* ---- Small HTML helpers ---------------------------------------------------------------------- */
$ul = static function ( array $items ): string {
	return '<ul>' . implode( '', array_map( static fn( $i ) => '<li>' . $i . '</li>', $items ) ) . '</ul>';
};
$ps = static function ( array $paras ): string {
	return implode( '', array_map( static fn( $t ) => '<p>' . $t . '</p>', $paras ) );
};
/* Library check lists (ezx-list) for bullet lists inside tab cards; numbered lists stay numbered. */
$checks = static function ( string $html ): string {
	return (string) preg_replace_callback(
		'#<ul>(.*?)</ul>#s',
		static fn( $m ) => '<ul class="ezx-list">' . preg_replace( '#<li>(.*?)</li>#s', '<li>' . ezh_i( 'check' ) . '<span class="ezx-list__t">$1</span></li>', $m[1] ) . '</ul>',
		$html
	);
};
/* Underline tabs from the component library (ez37-tabs--line; behaviour from design/ez37-components.js). */
$tabs_html = static function ( string $label, array $tabs ) use ( $checks ): string {
	$nav = '';
	$pan = '';
	foreach ( $tabs as $i => $t ) {
		$id   = 'ezu-' . substr( md5( $label . $i ), 0, 6 );
		$nav .= '<button class="ez37-tab" role="tab" id="' . $id . '-t" aria-controls="' . $id . '-p" aria-selected="' . ( 0 === $i ? 'true' : 'false' ) . '">' . esc_html( $t[0] ) . '</button>';
		$pan .= '<div class="ez37-tabpanel ezpg-rich" role="tabpanel" id="' . $id . '-p" aria-labelledby="' . $id . '-t" tabindex="0"' . ( 0 === $i ? '' : ' hidden' ) . '>' . $checks( $t[1] ) . '</div>';
	}
	return '<div class="ez37-tabs ez37-tabs--line ezpg-linetabs" data-ez37-tabs><div class="ez37-tablist" role="tablist" aria-label="' . esc_attr( $label ) . '">' . $nav . '</div>' . $pan . '</div>';
};
/* Pill tabs from the component library (ez37-tabs--pill; behaviour from design/ez37-components.js). */
$ptabs_html = static function ( string $label, array $tabs ) use ( $checks ): string {
	$nav = '';
	$pan = '';
	foreach ( $tabs as $i => $t ) {
		$id   = 'ezv-' . substr( md5( $label . $i ), 0, 6 );
		$nav .= '<button class="ez37-tab" role="tab" id="' . $id . '-t" aria-controls="' . $id . '-p" aria-selected="' . ( 0 === $i ? 'true' : 'false' ) . '">' . esc_html( $t[0] ) . '</button>';
		$pan .= '<div class="ez37-tabpanel ezpg-rich" role="tabpanel" id="' . $id . '-p" aria-labelledby="' . $id . '-t" tabindex="0"' . ( 0 === $i ? '' : ' hidden' ) . '><h3>' . esc_html( $t[0] ) . '</h3>' . $checks( $t[1] ) . '</div>';
	}
	return '<div class="ezpg-card"><div class="ez37-tabs ez37-tabs--pill ezpg-ptabs" data-ez37-tabs><div class="ez37-tablist" role="tablist" aria-label="' . esc_attr( $label ) . '">' . $nav . '</div>' . $pan . '</div></div>';
};
/* Accordion body: bold lead-ins become sub-headings, line breaks become paragraphs, bullets become library tick lists. */
$acc_body = static function ( string $html ) use ( $checks ): string {
	$html = (string) preg_replace( '#<p><strong>([^<]*)</strong>(?:<br>|</p><p>)?#', '<h4>$1</h4><p>', $html );
	$html = str_replace( '<br>', '</p><p>', $html );
	$html = str_replace( '<p></p>', '', $html );
	return $checks( $html );
};
/* Accordion: $items = array( array( title, html ), ... ). */
$acc_html = static function ( string $group, array $items ) use ( $acc_body ): string {
	$h = '';
	foreach ( $items as $i => $it ) {
		$h .= '<details name="' . esc_attr( $group ) . '"' . ( 0 === $i ? ' open' : '' ) . '><summary>' . esc_html( $it[0] ) . '</summary><div class="ezpg-rich ezpg-acc__body">' . $acc_body( $it[1] ) . '</div></details>';
	}
	return '<div class="ezh-acc ezpg-acc">' . $h . '</div>';
};

/* ---- Programme definitions ------------------------------------------------------------------ */
$programmes = array(
	'pccp' => array(
		'page'     => 'pccp',
		'offering' => 'pccp',
		'title'    => 'Professional Coach Certification Programme',
		'short'    => 'PCCP',
		'crumb'    => array( array( 'Solutions', ezh_url( 'solutions' ) ), array( 'Coach Certification', ezh_url( 'coach-certification' ) ), 'PCCP' ),
		'lead'     => 'ICF-accredited Level 1 coach education: 60 contact hours and 10 hours of mentor coaching, delivered live online over 16 weeks.',
		'facts'    => array(
			array( 'clock', '16 weeks', 'Online, live facilitator interface' ),
			array( 'award', '60 contact hours', 'ICF-accredited Level 1' ),
			array( 'users', '10 hours', 'Mentor coaching' ),
			array( 'globe', 'English', 'Language of delivery' ),
		),
		'tabs_about' => array(
			array( 'About the Programme', '<p>EZ37 Solutions Limited offers gold standard performance coach training that develops you to become a world class coach.</p><p><strong>We are</strong></p>' . $ul( array( 'Accredited by the International Coaching Federation (ICF) to provide coach education', 'A signatory to the Principles for Learning of the International Finance Corporation (IFC), a member of the World Bank Group demonstrating our commitment to the highest international standards of quality and impact, which improves business outcomes.' ) ) ),
			array( 'Intended Participants', $ul( array( 'Managers and professionals seeking to develop strengths and strategies for individual and organisational improvement', 'Executives interested in enhancing core leadership skills such as communication, confidence, empathy and self-awareness', 'Organisations committed to creating a coaching culture and developing internal coaches', 'Individuals looking to pursue an ICF credential e.g. Associate Certified Coach (ACC) credential.' ) ) ),
			array( 'How We&rsquo;re Different', $ul( array( 'We offer peer coaching for participants of our programme with respective individuals undertaking coach certification training in other ICF accredited institutions', 'We support you to accumulate the required qualification practice coaching hours for ICF credentialing.', 'Learn and practice core coaching competencies during training sessions and mentor coaching sessions', 'Participants who complete the full Level 1 accredited program will be eligible to apply for credentials from the International Coaching Federation (ICF). They will not be required to participate in the ICF Performance Evaluation Process.' ) ) ),
		),
		'modules'  => array(
			array( 'A', 'Awareness', 'Developing Self-Awareness', 'Module A includes various assessments and reflective practices that help you to become more aware of yourself, establish your vision, goals, and values. It aids in creating clarity and developing the confidence to make important changes in your personal and professional life; thus establishing where you are, and where you are trying to get to.' ),
			array( 'B', 'Being', 'The &lsquo;Being&rsquo; of a Coach', 'Module B focuses on the &lsquo;Being&rsquo; of a Coach. It entails who a coach is and establishes this as the foundation for a successful coaching profession. It reviews the role mindfulness plays in the life of a coach and also expands on the ethics and standards of ICF coaches must abide by.' ),
			array( 'C', 'Competencies', 'Understanding Core Competencies', 'Module C digs deeper into the ICF core competencies of coaching. Each competency is reviewed and put into practice under various scenarios to aid an understanding of the skills and behaviors required of a professional coach.' ),
			array( 'D', 'Doing', 'The &lsquo;Doing&rsquo; of a Coach', 'Module D reviews and reflects on assignments and tools as you bring learnings from previous modules into real-life coaching scenarios. Here you practice one-on-one coaching and apply the fundamentals of coaching in various situations. You will have the opportunity to debrief and receive feedback from your coaching group and facilitators.' ),
			array( 'E', 'Essentials', 'Essentials of Coaching', 'This final module guides you through vital aspects of coaching by leveraging on coaching practicals from Module D and explaining the core competencies learned in Module C. Here you discover the role that energy, emotion, empathy and engagement play in coaching.' ),
		),
		'overview' => array( '16 weeks', 'Online, live facilitator interface', '60 contact hours', '10 hours mentor coaching', 'ICF credentialled coach facilitators', 'Assessment and Group Work', 'Peer to Peer Coaching practice', 'Access to EZ Coaching Alumni', 'Certificate of Completion' ),
		'language' => 'English Language',
		'benefits' => array( 'Develop and improve leadership skills', 'Fast track personal and professional development', 'Gain financial independence', 'Work from any location', 'Strengthen relationships', 'Expand professional networks' ),
		'delivery' => array( array( 'Frequency', 'Weekly with breaks between sessions' ), array( 'Format', 'Virtual, Live, Interactive' ), array( 'Duration', '16 weeks' ) ),
		'weekend'  => 'coaching@ezcoaching.org',
		'info'     => array(
			array( 'Programme goals and vision', '<p><strong>Vision</strong><br>To create a global coaching culture by developing coaches to become change agents within their communities</p><p><strong>Goals</strong></p>' . $ul( array( 'To inspire participants and graduate certified coaches to become enablers for positive change in the personal and professional lives of their clients', 'To bring distinctive perspectives and experience to the learning of participants' ) ) ),
			array( 'Our coaching approaches', '<p>We apply a combination of coaching approaches (Vision Coaching, Cognitive Coaching and Positive Psychology) We tailor our approach depending on the company, project, group or the team.</p><p><strong>Vision coaching</strong><br>We develop a partnership and draw on elements of feedback, reflection and two-way conversations to motivate, inspire, encourage, and empower clients</p><p><strong>Cognitive Coaching</strong><br>We explore grounded and practical understanding of language, body and emotion as well as conversations for behavioural and cultural transformation. We propose that without relevant emotional learning, lasting change does not take place.</p><p><strong>Positive Psychology Coaching</strong><br>We support clients to expand existing strengths as a way to build positive emotions, creating greater satisfaction and ultimately improved levels of performance.</p>' ),
			array( 'Disability, discrimination and DEIJ statement', '<p>In keeping with its long-standing traditions and policies, EZ37 Solutions Ltd does not discriminate on the basis of race, color, religion, sex, sexual orientation, gender identity, national or ethnic origin, age for disability.</p><p><strong>Able to accommodate learning disability:</strong> Our goal is to create a learning environment which meets the needs of each participant. The organisations official responsible for coordinating compliance with our Disability/Discrimination/DEIJ statement is Patricia Akinlotan, ICF PCC Coach. Please contact her at coachpatricia@ezcoaching.org before enrolling to determine if your needs can be met.</p><p>As members of the ICF community, we ascribe to the core values of integrity, excellence, collaboration and respect. The foundation of these values is a shared commitment to diversity, inclusion, belonging and justice.</p><p>We will place diversity, inclusion, belonging and justice at the forefront of every decision we make within our Association. As we continue the journey toward our vision, we will recommit ourselves to valuing the unique talents, insights and experiences that every coach and client brings to the world.</p><p><strong>Non-Discrimination Policy</strong><br>It is the policy of EZ37 Solutions Ltd that:</p>' . $ul( array( 'Recruitment and hiring of all personnel is conducted without discrimination against any individual with regard to race, age, religion, color, creed, national origin, gender, sexual orientation, gender identity, marital status, disability, or veteran status.', 'All staff and personnel will not discriminate against any employee or participant because of race, age, religion, color, creed, national origin, gender, sexual orientation, gender identity, marital status, disability, or veteran status.', 'All individuals are welcome to participate regardless of race, age, religion, color, creed, national origin, gender, sexual orientation, gender identity, marital status, disability, or veteran status.', 'All employees, and participants should be able to enjoy an environment free of discrimination and harassment. This includes, but is not limited to, discrimination or harassment in the areas of race, age, religion, color, creed, national origin, gender, sexual orientation, gender identity, marital status, disability, or veteran status. Our organization does not and will not tolerate conduct by any employee, student, volunteer, contractor, visitor, or vendor which unreasonably interferes with an individual&rsquo;s ability to learn in a welcoming environment. Participants who wish to report discrimination are encouraged to follow the grievance policy below. EZ37 Solutions Ltd will promptly investigate all claims and reports of inappropriate conduct.' ) ) ),
			array( 'Entry requirements for the course', $ul( array( 'Bachelors Degree / HND', 'Minimum 3 years work experience', 'Must commit to programme requirements' ) ) ),
			array( 'Coaching philosophy', '<p>Our coaching philosophy is centered on providing a standard of care that enables the coach and client to create a collaborative space for trust and personal growth.</p><p>Whilst each coaching conversation is unique our philosophy is built upon the need to show up in the same way for every client.</p><p>Coaching conversations are approached with curiosity whilst ensuring that values are respected throughout the coaching process.</p><p>As a result, we adhere to ICF core competencies and abide by the ICF Code of Ethics.</p>' ),
			array( 'Learning goals', '<p>Our training provides specific techniques and tools which can be applied in situations to support meaningful change. Examples of learning goals include:</p><p><strong>Building confidence</strong><br>Developed through inquiry and asking powerful questions that provide the impetus for clients to note their achievements, successes, positive qualities, good deeds, and ways they positively impact others.<br>Undertaken through empowering conversations focused on proactively creating the future. Ultimately coaches support clients to create a visualisation.<br>Adopting an experiential process for building self confidence through devising a coaching plan that results in measurable achievements and self-affirming interactions</p><p><strong>Self-awareness</strong><br>Techniques include exploring a client&rsquo;s values and then considering how the clients&rsquo; choices align with those values. In the course of the coaching conversation a client should become aware of differences between their values and realities, and is thereby empowered to make different choices and plan appropriately.<br>Self awareness is also enabled through assessments that may focus on values, personality characteristics, or specific skills. A coach works with the client to make sense of the feedback from the assessments and create an action plan for working on opportunity areas.</p><p><strong>Transformation</strong><br>This occurs from the inside out. Through reflective inquiry, clients can look at their beliefs as if they were laid bare before them. From this position, they can examine the gaps in their thought process.</p>' ),
		),
		'policies' => array(
			array( 'Complaint / Grievance Policy', '<p>EZ37 Solutions Ltd seeks to ensure equitable treatment of every person and to attempt to solve any grievances in a timely and fair manner. Participants have the right to file a grievance regarding presentation or content, facility concerns, or instructor/faculty behavior. All grievances will be addressed to the best of our ability to prevent further problems. A process for filing grievances can be found below:</p><ol><li>A participant should first attempt to resolve the issue directly with the educator/trainer, staff, or participant with whom they have an issue. If participants are not comfortable approaching the individual, they can proceed to step 2.</li><li>If participants are not comfortable approaching the individual with whom they have a grievance, or are unable to resolve the issue directly, participants should submit a written grievance to the program director within 5 days. The program director will review the issue and talk to the participants within 3 days of receiving the complaint. The program director will work with all parties involved to resolve the issue.</li><li>If a participant does not feel the issue is resolved, a written request for an appeal should be sent to EZ37 Solutions/FAO Adaora Ayoade for review. This appeal should include the original complaint and reason(s) the participant is unsatisfied with the resolution attempt(s). Appeals are reviewed and a written response will be provided to the participant within 5 days.</li></ol>' ),
			array( 'Fees: Investment in Coach Certification', '<p>Coach tuition fees are due prior commencement. The company offers flexible payment options. We accept payment in naira based on the parallel rate equivalent. Fees are exclusive of VAT.</p><p><strong>Option 1: Payment in full</strong><br>Fees can be paid in full in advance.</p><p><strong>Option 2: Flexible payment plans by modules</strong><br>Fees are due in advance.</p>' . $ul( array( 'Module A and B: Developing Self-Awareness and Being of a Coach', 'Module C: Understanding core Competencies', 'Module D: The Doing of a Coach', 'Module E: Essentials of Coaching' ) ) . '<p><strong>Special offer:</strong> please contact us for our special offer rates.</p>' ),
			array( 'Refund Policy', '<p>Coach Tuition fees are due prior to commencement of the programme. The company offers flexible payment options. We accept payment in Naira based on the parallel rate equivalent. Fees are exclusive of VAT. The company reserves the right to review fee levels at any time.</p>' . $ul( array( 'Cancellation two weeks before commencement date of the course: A refund of the amount paid within 30 days less the $260 admin fee', 'Withdrawal before the end of the module: No refund', 'Non attendance: No refund' ) ) ),
			array( 'Transfer of Credit Policy', '<p>EZ37 Solutions does not offer a transfer of credit policy for the Professional Coach Certification Programme.</p>' ),
			array( 'Partial Completion Policy', '<p>At this time, we are not able to offer credit for partial completion of a course. You must complete the entire course to receive credit. Individuals with questions about this policy are encouraged to contact the programme director via coaching@ez37solutions.org</p>' ),
			array( 'Illness Policy', '<p>In order to provide you with the minimum required training hours for certification, it is important that you are present at all training sessions. If you have an emergency or become ill and are not able to attend a training session, please contact the programme director immediately. EZ37 Solutions Ltd illness policy requires that participants are permitted to make up for missed sessions and coursework due to illness during the cohort.</p><p>In the event that a participant is absent due to illness for more than two (2) sessions (6 hours) and does not provide a doctor&rsquo;s certificate they will be required to pay for the missed sessions in order to gain the additional hours of coach training.</p><p>Participants are expected to:</p>' . $ul( array( 'Utilise the Learning Management System (LMS) to complete the session materials, review the session recording, and complete a coaching session outside the live session to make up for the missed session', 'Engage with the Programme Director regarding any additional support that may be required for meeting the course requirements' ) ) . '<p>If you need to miss more than two (2) sessions of the course, you will have the option to work with the coach facilitator to cover the missed material at your own expense or register for another course.</p><p>If you miss more than 2 hours of mentor coaching, you will need to arrange and pay for individual mentor coaching sessions to complete the required 10 hours of mentor coaching to satisfy the International Coaching Federation requirements.</p><p>Petitions to this policy are considered on a case-by-case basis and must be submitted in writing to the programme director at EZ37 Solutions Ltd.</p>' ),
			array( 'Failure to Complete the Requirements Within the Cohort', '<p>Where a participant fails to complete the requirements for the Programme within the cohort, an extension may be offered by the Director of Education for an additional 30 days.</p><p>A request for an extension should be made in writing to the Director of Education within 30 days of the completion date for the cohort.</p><p>Where a participant has not completed the requirements of the programme and has not requested an additional 30 days from the Director of Education, they would be required to re-enrol for the programme and retake the modules.</p>' ),
			array( 'Ethics, Integrity and Transparency', $ul( array( 'As an ICF Accredited provider, our organization adheres to and emphasizes the International Coaching Federation Code of Ethics. The ICF Code of ethics describes the ICF core values, ethical principles, and standards of behavior for all ICF professionals. Meeting these ethical standards of behavior is the first of the ICF core coaching competencies. You can read more about the <a href="https://coachingfederation.org/ethics/code-of-ethics" target="_blank" rel="noopener">ICF Code of Ethics here</a>.', 'Additionally, EZ37 Solutions Ltd commits to acting with integrity and transparency. We hold ourselves and our participants to the highest level of integrity and strive to be as transparent as possible by explicitly stating measures being taken to provide programs in an ethical manner.', 'We do not believe in using manipulative or dishonest sales tactics and strive to provide a safe and ethical sales process. Further, we work to provide fair and equitable pricing for all programs to ensure access and quality of coaching education.' ) ) ),
			array( 'Participation Policy', '<p>Success in our program requires full commitment by all participants. By enrolling in this course, participants agree to being fully present during all sessions and participate to the best of their ability. This includes arriving on time, abiding by the code of conduct, and engaging in course activities.</p><p><strong>Attendance</strong><br>In order to provide you with the minimum required training hours for certification, it is important that you are present at all course trainings. If you have an emergency or become ill and are not able to attend a coach training session, please contact the programme director immediately. You will be expected to complete the session materials, review the session recording, and complete a coaching session outside the live session to make up for the missed session. If you need to miss more than 6 hours of the course, you will have the option to work with the instructor to cover the missed material at your own expense or register for another course.</p><p><strong>Mentor Coaching</strong><br>If you miss more than 2 hours for group mentor coaching session, you will need to arrange and pay for individual mentor coaching sessions to complete the required 10 hours of mentor coaching to satisfy the International Coaching federation requirements. If you fail to complete the individual mentor coaching session within 4 months of the enrolment of the programme, you will need to arrange and pay for individual mentor coaching sessions to complete the required 10 hours of mentor coaching to satisfy the International Coaching federation requirements.</p><p><strong>Course Engagement</strong><br>Our courses are designed to be interactive and engaging for our participants. It is therefore an expectation that you participate in course activities, including dialogue with the course instructor and peers, practice coaching activities, and experiential learning exercises. If you are unable to participate in an activity, please inform your facilitator as soon as possible. Please refer to the code of conduct for additional details.</p><p><strong>Code of Conduct</strong><br>Participants are expected to conduct themselves in a professional manner during all sessions. This includes but is not limited to:</p>' . $ul( array( 'Arriving on time to all sessions', 'Attending all live sessions and mentor coaching sessions', 'Having camera on for virtual live sessions.', 'Being prepared for the session,', 'Involving yourself in discussions and activities', 'Assuming responsibility for your learning,', 'Contributing to the learning of others.', 'Engaging in discussions with integrity and honesty', 'Being respectful of your fellow participants and instructors, including silencing mobile phone, not texting and other disruptive behaviours.', 'Embracing diversity and inclusion while respecting the dignity and humanity of others' ) ) ),
		),
		'faculty'  => array( 'patricia-akinlotan', 'adaora-ayoade', 'peter-a-james', 'zuzanna-mazurek', 'abigail-isokpan', 'jass-malaney', 'gloria-gbemudu' ),
		'quotes'   => array( 'david-barnard-pccp', 'stephanie-emodi', 'julia-esezobor' ),
	),
);

/* ACCP (Level 2) reuses PCCP's shared company text (approaches, DEIJ, philosophy, learning goals, policies); everything specific to ACCP is below. */
$pc   = $programmes['pccp'];
$info = array();
foreach ( $pc['info'] as $it ) {
	if ( 'Entry requirements for the course' === $it[0] ) {
		$it = array( 'Entry requirements for the course', $ul( array( 'Attained at least 60 hours of accredited ICF Coaching Education (ACSTH/ACTP/Level 1)', 'An ACC coach', 'Must commit to programme requirements' ) ) );
	}
	$info[] = $it;
}
$info[] = array( 'Evaluation and graduation', '<p><strong>Coaching practice</strong><br>The programme will provide continuous coaching opportunities to coach your peers and clients. Our ICF-credentialed facilitators will observe your sessions and provide you with verbal and written feedback in line with the ICF PCC markers.</p><p><strong>Performance evaluation</strong><br>During the programme, you will undertake a final performance evaluation assessed at PCC level.</p><p><strong>Mentor coaching</strong><br>You must complete 10 hours over three months. This is included in the programme.</p><p><strong>Reflective practice</strong><br>You will be required to submit your reflections on the LMS in your preferred format.</p>' );
$pol = array();
foreach ( $pc['policies'] as $it ) {
	if ( 0 === strpos( $it[0], 'Fees' ) ) {
		$it = array( $it[0], '<p>Coach tuition fees are due prior commencement. The company offers flexible payment options. We accept payment in naira based on the parallel rate equivalent. Fees are exclusive of VAT.</p><p><strong>Option 1: Payment in full</strong><br>Fees can be paid in full in advance.</p><p><strong>Option 2: Flexible payment plans</strong><br>Fees are due in advance. Please contact us for the payment plans available.</p><p><strong>Special offer:</strong> please contact us for our special offer rates.</p>' );
	}
	$pol[] = array( $it[0], str_replace( 'Professional Coach Certification Programme', 'Advanced Coach Certification Programme', $it[1] ) );
}
$programmes['accp'] = array(
	'page'       => 'accp',
	'offering'   => 'accp',
	'title'      => 'Advanced Coach Certification Programme',
	'short'      => 'ACCP',
	'crumb'      => array( array( 'Solutions', ezh_url( 'solutions' ) ), array( 'Coach Certification', ezh_url( 'coach-certification' ) ), 'ACCP' ),
	'lead'       => 'ICF-accredited Level 2 coach education for coaches ready to demonstrate the ICF core competencies at an advanced level, with 10 hours of mentor coaching included.',
	'badge_key'  => 'icf-level-2',
	'badge_alt'  => 'ICF Level 2 accredited coaching education',
	'badge_cap'  => 'Level 2 accredited coaching education',
	'mods_h2'    => 'The Programme Guides You Through <span class="ez37-hl">Nine Modules</span>',
	'facts'      => array(
		array( 'award', 'ICF Level 2', 'Accredited coach education' ),
		array( 'clock', 'Weekly', 'One 3-hour live session (Saturdays)' ),
		array( 'users', '10 hours', 'Mentor coaching over three months' ),
		array( 'globe', 'English', 'Language of delivery' ),
	),
	'tabs_about' => array(
		array( 'About the Programme', '<p>EZ37 Solutions Limited offers gold standard performance coach training, an ideal path to the International Coaching Federation (ICF) PCC credential.</p><p>The Advanced Coach Certification Programme (ACCP) aims for participants to demonstrate the ICF core competencies at an advanced level, providing excellent value for clients and organisations.</p><p>The ACCP is designed to meet the Level 2 Coaching Education requirements of the ICF.</p><p>We are a signatory to the Principles for Learning of the International Finance Corporation (IFC), a member of the World Bank Group, demonstrating our commitment to the highest international standards of quality and impact, which improves business outcomes.</p>' ),
		array( 'Intended Participants', '<p>The Advanced Coach Certification Programme is intended for:</p>' . $ul( array( 'Coaches who already have a Level 1 or ACSTH qualification', 'ACC coaches looking to progress toward the Professional Coach Certification (PCC). You would undertake the programme to top up your required training hours, which would earn you a total of 130 hours' ) ) ),
		array( 'How We&rsquo;re Different', $ul( array( 'We offer peer coaching opportunities for Alumni of the Advanced Coach Certification Programme', 'We support you to accumulate the required qualification practice coaching hours for ICF credentialing' ) ) ),
	),
	'modules'    => array(
		$pc['modules'][0],
		$pc['modules'][1],
		$pc['modules'][2],
		$pc['modules'][3],
		array( 'E', 'Essentials', 'Essentials of Coaching', 'This module guides you through vital aspects of coaching by leveraging on coaching practicals from Module D and explaining the core competencies learned in Module C. Here you discover the role that energy, emotion, empathy and engagement play in coaching.' ),
		array( 'F', 'Find Depth', 'Deepening Coaching Competency', 'Acquire skills and techniques to deepen coaching competency.' ),
		array( 'G', 'Gain Insight', 'Group and Team Coaching', 'Discover how coaching one to many creates a shared experience that promotes creativity, innovation and goal attainment.' ),
		array( 'H', 'Hone', 'Your Entrepreneurial Skills', 'Identify the skills and tools required to launch a coaching business.' ),
		array( 'I', 'Identify', 'Best Practice for Internal and External Coach Practitioners', 'Uncover the opportunities and impact for internal and external coach practitioners.' ),
	),
	'overview'   => array( '130 hours of live online coach specific training', 'One 3-hour session per week (Saturdays)', 'Highly practical', '10 hours of mentor coaching over three months', 'Live interactive online facilitator led sessions', 'Learning Management System (LMS)', 'Peer to Peer Coach Practice', 'Assessment', 'Performance Evaluation' ),
	'language'   => 'English Language',
	'benefits'   => array( 'Gain confidence to work with a broader range of clients by developing advanced coaching skills', 'Attain the required training hours to apply for a PCC credential', 'Support to accumulate qualification practice hours for a PCC credential', 'Empower individuals and organisations to excel', 'Opportunity to network through EZ Coaching Alumni', 'Set up your coaching business', 'Develop agility' ),
	'delivery'   => array( array( 'Frequency', 'One 3-hour session per week (Saturdays)' ), array( 'Format', 'Virtual, Live, Interactive' ), array( 'Mentor coaching', '10 hours over three months' ) ),
	'weekend'    => 'coaching@ezcoaching.org',
	'info'       => $info,
	'policies'   => $pol,
	'faculty'    => array( 'adaora-ayoade', 'svea-van-der-hoorn', 'abigail-isokpan', 'zuzanna-mazurek' ),
	'roles'      => array( 'adaora-ayoade' => 'PCC, ICF Coach' ),
	'quotes'     => array(),
);

$programmes['coaching-skills'] = array(
	'page'        => 'coaching-skills-training',
	'offering'    => 'coaching-skills',
	'title'       => 'Coaching Skills Training for Managers and Leaders',
	'short'       => '',
	'crumb'       => array( array( 'Solutions', ezh_url( 'solutions' ) ), 'Coaching Skills Training' ),
	'lead'        => 'Learn to coach your team: develop the leadership competencies that improve performance, retention and productivity.',
	'badge_key'   => '',
	'eyebrow'     => 'Lead by Coaching',
	'fee_note'    => 'Training fee',
	'benefits_h2' => 'Course <span class="ez37-hl">Content</span>',
	'contact_h3'  => 'Questions about the course',
	'cta_h2'      => 'Ready To Coach <span class="ez37-hl">Your Team?</span>',
	'facts'       => array(
		array( 'clock', '3 days', 'Duration' ),
		array( 'users', 'Managers and leaders', 'Team leads, executives and C-suite' ),
		array( 'award', 'Practical', 'Case studies, role-play and an action plan' ),
	),
	'tabs_about'  => array(
		array( 'Overview', '<p>For managers and leaders, their daily jobs require them to assist their staff in solving problems, and they bear responsibility for supervising the work outcome of their team or employees. So, how can managers do these effectively, without losing their best employees, as well as maintaining the company&rsquo;s productivity and profit?</p><p>Managers and Leaders can effectively develop their organisation with the use of coaching. When managers learn to coach, they develop powerful leadership competencies to enable their employees and teams to perform more effectively, as well as enhance productivity.</p><p><strong>Coaching Skills Training for Managers and Leaders</strong></p><p>The coaching skills for Managers and Leaders training will empower the leadership in utilising coaching skills to support members of their team which leads to employee retention, strong employer brand as well as increased productivity and profit.</p>' ),
		array( 'Objectives', '<p>At the end of the course, participants will be able to:</p>' . $ul( array( 'Define coaching and understand its benefits for the manager/leader', 'Utilize coaching models', 'Apply coaching skills and tools at work', 'Understand the importance of coaching in improving overall workplace performance' ) ) ),
		array( 'Target Audience', $ul( array( 'Managers', 'Team Leads', 'Executives', 'C-Suite Executives' ) ) ),
		array( 'Methodology', '<p>The course uses a problem-based methodology in order to engage participants actively in the learning process. Before each course, participants are encouraged to raise issues that they are facing at work by completing a participant profile. During the course, the trainer will act as a facilitator, using interactive learning methods to help participants to share their experiences and learn through participation in activities such as group discussions, case studies, role-playing, and games. At the same time, participants are instructed to develop an action plan for applying their new skills and tools to actual challenges at work.</p>' ),
	),
	'modules'     => array(),
	'overview'    => array(),
	'language'    => '',
	'benefits'    => array( 'What is Coaching', 'Difference between coaching and mentoring', 'Coaching Models and Tools', 'Benefits of Coaching to Managers', 'Embedding a Coaching Culture' ),
	'delivery'    => array( array( 'Duration', '3 days' ) ),
	'weekend'     => 'coaching@ez37solutions.org',
	'info'        => array(),
	'policies'    => array(),
	'alumni'      => false,
	'faculty'     => array( 'adaora-ayoade', 'peter-a-james', 'zuzanna-mazurek', 'abigail-isokpan', 'jass-malaney', 'gloria-gbemudu' ),
	'roles'       => array( 'adaora-ayoade' => 'PCC, ICF Coach' ),
	'quotes'      => array(),
	'pullquote'   => array( 'Coaching is much bigger than coaching. Anybody who is going to be an effective leader in the world today needs to do so in a coaching style.', 'Sir John Whitmore' ),
	'fee_sub'     => '<p class="ezpg-sub">You can pay in full online now by card. Questions about the course, or want to enrol a team? Please <a href="' . esc_url( $calendly ) . '" target="_blank" rel="noopener">book a call with us</a>.</p>',
	'how'         => '<ol class="ezpg-how"><li><b>Share your details</b><span>A short enrolment form with your contact details and why you are taking the course.</span></li><li><b>Pay securely on Stripe</b><span>Your card details go to Stripe, never to us. You are charged in US dollars.</span></li><li><b>Get your confirmation</b><span>An email with your receipt and your course material.</span></li></ol>',
	'faq'         => array(
		array( 'How do I enrol?', 'Choose Enrol Now, complete the short enrolment form, then pay securely on Stripe. It takes a couple of minutes.' ),
		array( 'How do I pay?', 'Card payments (including Apple Pay and Google Pay where your device supports them) are taken securely by Stripe. EZ37 Solutions never sees or stores your card details.' ),
		array( 'What happens after I pay?', 'You receive an email confirming your payment, your receipt from Stripe and your course material. If nothing arrives within a few minutes, check your spam folder, then contact us.' ),
		array( 'Can my organisation pay, or enrol a team?', 'Yes. Please use our Contact page to tell us what your organisation needs and we will arrange it with you.' ),
	),
);

$mentor_faculty_roles = array( 'adaora-ayoade' => 'MCC, CEO EZ37 Solutions Ltd', 'cindy-jensen' => 'MCC, ACTC, CEO INPOWER ONE' );
$mentor_faq = static function ( string $what ): array {
	return array(
		array( 'How do I enrol?', 'Choose Enrol Now, complete the short enrolment form, then pay securely on Stripe. It takes a couple of minutes.' ),
		array( 'Who can join?', $what ),
		array( 'How do I pay?', 'Card payments (including Apple Pay and Google Pay where your device supports them) are taken securely by Stripe. EZ37 Solutions never sees or stores your card details.' ),
		array( 'What happens after I pay?', 'You receive an email confirming your payment, your receipt from Stripe and your programme material. If nothing arrives within a few minutes, check your spam folder, then contact us.' ),
	);
};
$mentor_how = '<ol class="ezpg-how"><li><b>Share your details</b><span>A short enrolment form with your contact details and your current ICF credential.</span></li><li><b>Pay securely on Stripe</b><span>Your card details go to Stripe, never to us. You are charged in US dollars.</span></li><li><b>Get your confirmation</b><span>An email with your receipt and your programme material.</span></li></ol>';

$programmes['amcmp'] = array(
	'page'        => 'advanced-mastery',
	'offering'    => 'amcmp',
	'title'       => 'Advanced Mentor Coaching Mastery Program',
	'short'       => 'AMCMP',
	'crumb'       => array( array( 'Solutions', ezh_url( 'solutions' ) ), array( 'Mentor Coaching', ezh_url( 'mentor-coaching' ) ), 'AMCMP' ),
	'lead'        => 'An ICF AAMC-accredited 60-hour programme on the Standard Pathway to the ICF Mentor Coach Specialization (MCS).',
	'badge_key'   => 'icf-aamc',
	'badge_alt'   => 'ICF AAMC accredited coaching education',
	'eyebrow'     => 'ICF AAMC-Accredited',
	'fee_note'    => 'Early bird fee (standard fee $2,499)',
	'benefits_h2' => 'What you will <span class="ez37-hl">work with</span>',
	'contact_h3'  => 'Questions about the programme',
	'cta_h2'      => 'Ready To Deepen Your <span class="ez37-hl">Mentor Coaching?</span>',
	'facts'       => array(
		array( 'clock', '60 hours', '30 live and 30 self-paced' ),
		array( 'calendar', '16 January 2027', 'Start date' ),
		array( 'award', 'ICF AAMC', 'Standard Pathway to the MCS' ),
	),
	'tabs_about'  => array(
		array( 'Overview', '<p>The Advanced Mentor Coaching Mastery Program (AAMC) is for experienced coaches who want to add mentor coaching to their practice, and for mentor coaches who want to deepen their skills and formalise their training in preparation for the ICF Mentor Coach Specialization (MCS).</p><p>Mentor coaching involves more than reviewing a coaching session and sharing feedback. It requires you to listen carefully, recognise the coach&rsquo;s current level of development, and help them understand their practice and see possibilities for growth.</p><p>This program brings those elements together. You will develop your ability to work with ICF standards while learning how to hold mentor-coaching conversations that are clear, thoughtful, and useful to the coach.</p>' ),
		array( 'From observation to conversation', '<p>Throughout the program you will move between learning and practice. Working with coaching recordings, you will identify evidence, compare observations, and consider what the coaching demonstrates, and what it does not.</p><p>You will then practise bringing those observations into a mentor-coaching conversation: sharing specific feedback, making room for reflection, and supporting the coach in deciding what to try in their next session.</p><p>We also give attention to the factors that can influence your judgement, including bias, assumptions, personal preference, power, and culture. The aim is not simply to arrive at a rating, but to develop sound judgement and take responsibility for how you evaluate and support another coach&rsquo;s work.</p>' ),
		array( 'How you will learn', $ul( array( 'Live sessions bring you together with MCC faculty and fellow participants to work with coaching recordings and live sessions, discuss differences in observation, and practise mentor coaching. You receive feedback on your own mentor-coaching work, with space to reflect and refine your approach.', 'Between live sessions, the online learning platform lets you work through material at your own pace, revisit key topics, and prepare for practice.', 'Relevant research in coaching, adult learning, feedback, decision-making, and human development informs the program where it strengthens your judgement or offers a useful approach to working with coaches.' ) ) . '<p>The goal is to help you bring mentor coaching into your practice with a clear understanding of the standards, experience applying them, and greater confidence in the conversations that support a coach&rsquo;s development.</p>' ),
		array( 'Who it is for', '<p><strong>Intended participants:</strong> experienced credentialed coaches who want to add mentor coaching to their practice, and mentor coaches seeking to deepen their skills and formalise their training for the ICF Mentor Coach Specialization (MCS).</p><p><strong>Requirements:</strong> ICF credentialed coaches: ACC (renewed), PCC and MCC credentials.</p><p><strong>Language:</strong> taught in English.</p>' ),
	),
	'modules'     => array(),
	'overview'    => array(),
	'language'    => '',
	'benefits'    => array( 'ICF Mentor Coaching Competencies, Core Competencies, and Code of Ethics', 'ACC BARS, PCC Markers, and MCC BARS, with learning matched to your credential level', 'Evidence-based observation, evaluation, and developmental feedback', 'Five observed mentor-coaching sessions, written feedback, and five hours of mentoring-on-mentoring', 'ICF Session Observation and Competency Review Forms, including the professional responsibility involved in signing them', 'MCS requirements and application preparation, including the Credit for Prior Learning Pathway for eligible participants' ),
	'delivery'    => array( array( 'Duration', '60 hours' ), array( 'Start date', '16 January 2027' ), array( 'Language', 'English' ) ),
	'weekend'     => 'coaching@ez37solutions.org',
	'info'        => array(),
	'policies'    => array(),
	'alumni'      => false,
	'faculty'     => array( 'adaora-ayoade', 'cindy-jensen' ),
	'roles'       => $mentor_faculty_roles,
	'quotes'      => array(),
	'fee_sub'     => '<p class="ezpg-sub">Fees are exclusive of taxes. Early bird $2,300 USD (standard fee $2,499 USD). You can pay in full online now by card. We work with you to create convenient payment plans: please <a href="' . esc_url( $calendly ) . '" target="_blank" rel="noopener">book a call with us</a>.</p>',
	'how'         => $mentor_how,
	'faq'         => $mentor_faq( 'Experienced credentialed coaches: renewed ACC, PCC and MCC, who want to add mentor coaching to their practice or formalise their training for the ICF Mentor Coach Specialization.' ),
);

$programmes['mcs'] = array(
	'page'        => 'mcs',
	'offering'    => 'mcs',
	'title'       => 'Mentor Coach Specialization (MCS) Training',
	'short'       => '',
	'crumb'       => array( array( 'Solutions', ezh_url( 'solutions' ) ), array( 'Mentor Coaching', ezh_url( 'mentor-coaching' ) ), 'MCS Training' ),
	'lead'        => 'An ICF-accredited 10-hour programme for experienced mentor coaches seeking the new ICF Mentor Coach Specialization (MCS) credential.',
	'badge_key'   => '',
	'eyebrow'     => '10-Hour Pathway',
	'fee_note'    => 'End of year early bird fee (standard fee $525)',
	'benefits_h2' => 'Programme <span class="ez37-hl">Features</span>',
	'contact_h3'  => 'Questions about the programme',
	'cta_h2'      => 'Ready To Pursue Your <span class="ez37-hl">MCS Credential?</span>',
	'facts'       => array(
		array( 'clock', '10 hours', '8.5 self-paced and 1.5 live' ),
		array( 'award', 'ICF accredited', 'Credit for Prior Learning Pathway' ),
		array( 'users', 'ACC, PCC and MCC', 'Experienced mentor coaches' ),
	),
	'tabs_about'  => array(
		array( 'Overview', '<p>The ICF-accredited 10-hour programme is designed for experienced mentor coaches seeking the new ICF Mentor Coach Specialization (MCS) credential. It fulfils the ICF&rsquo;s Credit for Prior Learning requirement, making it a direct and efficient route to formal recognition.</p><p>The programme incorporates applicable evaluation training in ACC BARS, PCC Markers, and MCC BARS, and is designed to meet the 10+ hours of mentor coaching education required for those working toward the ICF Mentor Coach Specialization.</p>' ),
		array( 'Focus', '<p>The Mentor Coach Specialization training is a practice-focused, feedback-driven offering that strengthens coaches&rsquo; ability to understand and apply the ICF Core Competencies, the Code of Ethics, and credential-level performance expectations, with particular emphasis on recognising and coaching to standards while understanding the distinctions between ACC, PCC and MCC.</p><p>It also prepares participants for the ICF Mentor Coach Specialization (MCS) credential, where mentor coaching is becoming a regulated, competency-based professional pathway.</p>' ),
		array( 'Who it is for', '<p>ACC, PCC and MCC coaches on the Mentor Coach Specialization Extended pathway who are already experienced as mentor coaches.</p><p><strong>Language:</strong> English.</p>' ),
	),
	'modules'     => array(),
	'overview'    => array(),
	'language'    => '',
	'benefits'    => array( 'Meets the 10-hour accredited-education requirement for your MCS eligibility', 'Provides formal recognition and market differentiation', 'Deepens your coaching practice through feedback and reflective learning', 'Led by ICF Master Certified Coaches (MCCs) with global expertise', 'Focused 10-hour curriculum: 8.5 hours self-paced and 1.5 hours live', 'Practice providing developmental feedback using real coaching recordings', 'Tailored for experienced ACC (renewed), PCC, and MCC coaches', 'Prepares participants for MCS credential responsibilities and standards' ),
	'delivery'    => array( array( 'Duration', '10 hours' ), array( 'Language', 'English' ) ),
	'weekend'     => 'coaching@ez37solutions.org',
	'info'        => array(),
	'policies'    => array(),
	'alumni'      => false,
	'faculty'     => array( 'adaora-ayoade', 'cindy-jensen' ),
	'roles'       => $mentor_faculty_roles,
	'quotes'      => array(),
	'fee_sub'     => '<p class="ezpg-sub">Fees are exclusive of taxes. End of year special pricing is valid until 31 December 2026: early bird $490 USD (standard fee $525 USD). You can pay in full online now by card. Questions? Please <a href="' . esc_url( $calendly ) . '" target="_blank" rel="noopener">book a call with us</a>.</p>',
	'how'         => $mentor_how,
	'faq'         => $mentor_faq( 'ACC, PCC and MCC coaches on the Mentor Coach Specialization Extended pathway who are already experienced as mentor coaches.' ),
);

$key_args = array_values( array_filter( (array) ( $args ?? array() ), static fn( $a ) => is_string( $a ) && '' !== $a && '--' !== $a ) );
$key      = (string) ( $key_args[0] ?? 'pccp' );
if ( ! isset( $programmes[ $key ] ) ) {
	WP_CLI::error( 'Unknown programme "' . $key . '". Known: ' . implode( ', ', array_keys( $programmes ) ) );
}
$p = $programmes[ $key ];

$page = get_page_by_path( $p['page'] . '-v2', OBJECT, 'page' );
if ( ! $page ) {
	WP_CLI::error( 'Page ' . $p['page'] . '-v2 not found. Run bin/header-footer-local.php first.' );
}

/* ---- Price: read from the payment plugin's server-side catalogue (never typed into the page) ---- */
$price     = '';
$buyable   = false;
$cfg_path  = dirname( __DIR__ ) . '/ez37-payments/config/offerings.php';
if ( class_exists( '\EZ37\Payments\Offerings\OfferingCatalogue' ) && is_readable( $cfg_path ) ) {
	$catalogue = \EZ37\Payments\Offerings\OfferingCatalogue::from_file( $cfg_path );
	$offering  = $catalogue->find( $p['offering'] );
	if ( $offering ) {
		$price   = $offering->formatted_price();
		$buyable = null !== $offering->price_id( 'test' ) || null !== $offering->price_id( 'live' );
		if ( ! $buyable ) {
			WP_CLI::warning( 'Offering "' . $p['offering'] . '" has no Stripe Price ID yet. Create the Price in the Stripe sandbox and add it to config/offerings.php, otherwise checkout shows "not available".' );
		}
	} else {
		WP_CLI::warning( 'Offering "' . $p['offering'] . '" is missing or invalid in config/offerings.php (' . implode( ' ', $catalogue->errors() ) . ').' );
	}
} else {
	WP_CLI::warning( 'EZ37 Payments catalogue not available; the price will not be shown on the page.' );
}
if ( ! get_page_by_path( 'checkout', OBJECT, 'page' ) ) {
	WP_CLI::warning( 'No /checkout/ page found. Run bin/pages-local.php.' );
}
$checkout = home_url( '/checkout/?offering=' . rawurlencode( $p['offering'] ) );

$case_imgs  = ezh_media_dir( 'case', '_ez37_case' );
$abt_imgs   = ezh_media_dir( 'about', '_ez37_about' );
$lead_imgs  = ezh_media_dir( 'leadership', '_ez37_lead' );
$coach_imgs = ezh_media_dir( 'coaches', '_ez37_coach' );
$testi      = ezh_media_dir( 'testimonials', '_ez37_testi' );
$alumni_imgs = ezh_media_dir( 'alumni', '_ez37_alumni' );
$hero_photo = $abt_imgs['accp-conversation'] ?? ( $case_imgs['case-study-meeting'] ?? '' );
$acc_imgs   = ezh_media_dir( 'accreditation', '_ez37_acc' );
$badge_img  = $lead_imgs[ $p['badge_key'] ?? 'icf-level-1' ] ?? ( $acc_imgs[ $p['badge_key'] ?? 'icf-level-1' ] ?? '' );

$link_enrol = '<a class="ez37-btn-link" href="' . esc_url( $checkout ) . '"><span>Enrol now</span>' . ezh_i( 'arrow' ) . '</a>';
$btn_enrol = '<a class="ez37-btn" href="' . esc_url( $checkout ) . '">Enrol Now ' . ezh_i( 'arrow' ) . '</a>';

$sections   = array();
$sections[] = ezh_banner_hero( $p['title'] . ( ! empty( $p['short'] ) ? ' (' . $p['short'] . ')' : '' ), $p['lead'], array(), $hero_photo, '', $p['crumb'], true );

/* 1. Facts strip (ICF badge + key facts + fee from the server-side catalogue) */
$facts = '';
foreach ( $p['facts'] as $f ) {
	$facts .= '<li><span class="ezpg-facts__icon">' . ezh_i( $f[0] ) . '</span><span><b>' . esc_html( $f[1] ) . '</b><small>' . esc_html( $f[2] ) . '</small></span></li>';
}
if ( '' !== $price ) {
	$facts .= '<li><span class="ezpg-facts__icon">' . ezh_i( 'check' ) . '</span><span><b>' . esc_html( $price ) . '</b><small>' . esc_html( $p['fee_note'] ?? 'Programme fee, exclusive of VAT' ) . '</small></span></li>';
}
$sections[] = ezh_section( 'ezpg-facts', '<ul class="ezpg-facts__list">' . $facts . '</ul>' );
$badge_html = $badge_img ? '<aside class="ezpg-about__badge"><img src="' . esc_url( $badge_img ) . '" alt="' . esc_attr( $p['badge_alt'] ?? 'ICF Level 1 accredited coaching education' ) . '" width="200" height="200" loading="lazy"><p><b>ICF-accredited coach education</b><span>' . esc_html( $p['badge_cap'] ?? 'Level 1 accredited coaching education' ) . '</span></p></aside>' : '';

/* Benefits and course details: shown in the left column of the Fee and Enrolment section, before the faculty. */
$dl = '';
foreach ( $p['delivery'] as $d ) {
	$dl .= '<div><dt>' . esc_html( $d[0] ) . '</dt><dd>' . esc_html( $d[1] ) . '</dd></div>';
}
if ( ! empty( $p['language'] ) ) {
	$dl .= '<div><dt>Language of delivery</dt><dd>' . esc_html( $p['language'] ) . '</dd></div>';
}
$benefits_html = '<div class="ezpg-fee__block ezpg-rich"><h2>' . ( $p['benefits_h2'] ?? 'Benefits of the <span class="ez37-hl">Programme</span>' ) . '</h2>' . $checks( $ul( $p['benefits'] ) ) . '</div>';
/* Overview of the programme: left column of Fee and Enrolment, right before the faculty. */
$overview_html = empty( $p['overview'] ) ? '' : '<div class="ezpg-fee__block ezpg-fee__block--rule ezpg-rich"><h2>Overview of the <span class="ez37-hl">Programme</span></h2>' . $checks( $ul( $p['overview'] ) ) . '</div>';
/* Course details: a small note under the sticky fee card. */
$delivery_html = '<div class="ezpg-fee__facts"><h3>Course details</h3><dl>' . $dl . '</dl><h3>' . esc_html( $p['contact_h3'] ?? 'Weekend cohort' ) . '</h3><p>Contact: ' . esc_html( $p['weekend'] ) . '</p></div>';

/* 2. About / Intended participants / How we are different: ONE tab component */
$sections[] = ezh_section(
	'ezpg-about',
	'<div class="ezpg-inner"><div class="ezpg-card ezpg-about__grid' . ( $badge_html ? '' : ' ezpg-about__grid--full' ) . '"><div class="ezpg-about__main"><span class="ezpg-eyebrow">' . esc_html( $p['eyebrow'] ?? 'Become a Coach the World Recognises' ) . '</span>' . $tabs_html( 'About the programme', $p['tabs_about'] ) . '<div class="ezpg-cta">' . $link_enrol . '</div></div>' . $badge_html . '</div></div>'
);

/* 3. Modules A to E */
$mods = '';
if ( ! empty( $p['modules'] ) ) {
foreach ( $p['modules'] as $m ) {
	$mods .= '<li class="ezpg-mod"><span class="ezpg-mod__letter">' . esc_html( $m[0] ) . '</span><div><small>' . esc_html( $m[1] ) . '</small><h3>' . $m[2] . '</h3><p>' . esc_html( $m[3] ) . '</p></div></li>';
}
$sections[] = ezh_section(
	'ezpg-journey',
	'<div class="ezpg-inner"><span class="ezpg-eyebrow">Modules</span><h2>' . ( $p['mods_h2'] ?? 'The Programme Guides You Through the <span class="ez37-hl">ABCDE of Coaching</span>' ) . '</h2><ol class="ezpg-mods">' . $mods . '</ol></div>'
);
}

/* 6. Programme information: ONE accordion (goals, approaches, DEIJ, entry requirements, philosophy, learning goals) */
if ( ! empty( $p['info'] ) ) {
$sections[] = ezh_section(
	'ezpg-info',
	'<div class="ezpg-inner"><h2>Programme <span class="ez37-hl">Information</span></h2>' . $acc_html( 'ezpg-info', $p['info'] ) . '</div>'
);
}

/* Faculty (programme leadership; photos and roles from the brochure). Shown in the left column of the Fee and Enrolment section, beside the sticky fee card. */
$people = array(
	'patricia-akinlotan' => array( 'Patricia Akinlotan', 'Executive Director, PCC' ),
	'adaora-ayoade'      => array( 'Adaora Ayoade', 'Chief Executive Officer, MCC' ),
	'peter-a-james'      => array( 'Dr. Peter A. James', 'PCC, ICF Coach' ),
	'zuzanna-mazurek'    => array( 'Zuzanna Mazurek', 'PCC, ICF Coach' ),
	'abigail-isokpan'    => array( 'Abigail Isokpan', 'MCC, ICF Coach' ),
	'jass-malaney'       => array( 'Jass Malaney', 'PCC, ICF Coach' ),
	'gloria-gbemudu'     => array( 'Gloria Gbemudu', 'PCC, ICF Coach' ),
	'svea-van-der-hoorn' => array( 'Dr. Svea van der Hoorn', 'MCC, ICF Coach' ),
	'cindy-jensen'       => array( 'Cindy Jensen', 'MCC, ACTC' ),
);
foreach ( (array) ( $p['roles'] ?? array() ) as $rk => $rv ) {
	if ( isset( $people[ $rk ] ) ) {
		$people[ $rk ][1] = $rv;
	}
}
$fac = '';
foreach ( $p['faculty'] as $slug ) {
	if ( ! isset( $people[ $slug ] ) ) {
		continue;
	}
	$fac .= '<article class="ezpg-person">' . ( ! empty( $coach_imgs[ $slug ] ) ? '<img src="' . esc_url( $coach_imgs[ $slug ] ) . '" alt="' . esc_attr( $people[ $slug ][0] ) . '" loading="lazy">' : '' )
		. '<h3>' . esc_html( $people[ $slug ][0] ) . '</h3><p>' . esc_html( $people[ $slug ][1] ) . '</p></article>';
}
$faculty_html = '<div class="ezpg-fee__faculty ezpg-slider" data-ezpg-slider><div class="ezpg-slider__head"><h2>Meet Your <span class="ez37-hl">Faculty</span></h2>'
	. '<div class="ezpg-slider__nav"><button type="button" class="ezpg-slider__btn ezpg-slider__btn--prev" aria-label="Previous faculty members" data-dir="-1">' . ezh_i( 'arrow' ) . '</button><button type="button" class="ezpg-slider__btn" aria-label="Next faculty members" data-dir="1">' . ezh_i( 'arrow' ) . '</button></div></div>'
	. '<div class="ezpg-slider__track" role="region" aria-label="Faculty members" tabindex="0">' . $fac . '</div>'
	. '<script>(function(){document.querySelectorAll("[data-ezpg-slider]").forEach(function(w){var t=w.querySelector(".ezpg-slider__track"),b=w.querySelectorAll(".ezpg-slider__btn");function upd(){var m=t.scrollWidth-t.clientWidth-2;b[0].disabled=t.scrollLeft<=2;b[1].disabled=t.scrollLeft>=m;}b.forEach(function(x){x.addEventListener("click",function(){t.scrollBy({left:parseInt(x.dataset.dir,10)*t.clientWidth,behavior:"smooth"});});});t.addEventListener("scroll",upd,{passive:true});window.addEventListener("resize",upd);upd();});})();</script></div>';

/* 7. Fee + enrol panel (the CTA feeds the payment plugin's checkout) */
$sections[] = ezh_section(
	'ezpg-fee',
	'<div class="ezpg-fee__grid">
	<div class="ezpg-fee__text">
		<h2>Fee and <span class="ez37-hl">Enrolment</span></h2>
		' . ( $p['fee_sub'] ?? '<p class="ezpg-sub">Fees are exclusive of VAT. You can pay in full online now by card. If you would prefer a flexible payment plan by module, or want to hear about our special offer rates, please <a href="' . esc_url( $calendly ) . '" target="_blank" rel="noopener">book a call with us</a>.</p>' ) . ( $p['how'] ?? '<ol class="ezpg-how">
			<li><b>Share your details</b><span>A short enrolment form with your contact details and what you hope to achieve.</span></li>
			<li><b>Read and accept the Enrolment Agreement</b><span>It opens in a side panel so you never leave the form. Your acceptance is recorded with your order.</span></li>
			<li><b>Pay securely on Stripe</b><span>Your card details go to Stripe, never to us. You are charged in US dollars.</span></li>
		</ol>' ) . $benefits_html . $overview_html . $faculty_html . '
	</div>
	<aside class="ezpg-fee__card" aria-label="Programme fee">
		<span class="ezpg-fee__label">Payment in full</span>'
	. ( '' !== $price ? '<span class="ezpg-fee__price">' . esc_html( $price ) . '</span>' : '' )
	. '<span class="ezpg-fee__name">' . esc_html( $p['title'] ) . '</span>
		<a class="ez37-btn ez37-btn--lg" href="' . esc_url( $checkout ) . '">Enrol Now ' . ezh_i( 'arrow' ) . '</a>
		<span class="ezpg-fee__note">' . ezh_i( 'lock' ) . 'Secure payment by Stripe</span>
		<a class="ezpg-fee__link" href="' . esc_url( $calendly ) . '" target="_blank" rel="noopener">Questions? Book a discovery call</a>' . $delivery_html . '
	</aside></div>'
);

if ( $p['alumni'] ?? true ) {
/* 6a. After you graduate: graduation lead-in + four alumni support cards (client text from the previous EZ Coaching page; titles are ours) */
$alumni_cards = array(
	array( 'linkedin-alumni', 'Alumni LinkedIn Community', 'Alumni have access to member only content on EZ Coaching Alumni Linkedin page to build cross cultural relationships.' ),
	array( 'peer-to-peer-coaching', 'Peer-to-Peer Coaching', 'A coaching peer-to-peer program involves individuals in similar roles supporting and learning from each other through coaching techniques.', 'Participants alternate between coaching and being coached, offering feedback and guidance to aid personal and professional growth. This program creates a collaborative environment for sharing experiences and enhancing coaching skills to achieve goals.' ),
	array( 'credential-accelerator', 'Credential Accelerator Programme', 'Specialized support to obtain an ICF credential such as ACC or PCC. By participating coaches enhance their skills, meet the requirements for credential, and advance their careers in a shorter period of time.' ),
	array( 'icf-coaching-week', 'ICF International Coaching Week', 'As part of our commitment to giving back to industry, EZ37 Solutions Ltd provides coaches with the opportunity to demonstrate coaching practice with leaders in industry.' ),
);
$ac = '';
foreach ( $alumni_cards as $n => $c ) {
	$tip = '';
	if ( ! empty( $c[3] ) ) {
		$tid = 'ezpg-tip-' . $n;
		$tip = '<span class="ezpg-tip"><button type="button" class="ezpg-tip__btn" aria-describedby="' . $tid . '" aria-label="More about ' . esc_attr( $c[1] ) . '"><svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 16v-5"/><circle cx="12" cy="8" r="0.6" fill="currentColor"/></svg></button><span class="ezpg-tip__box" role="tooltip" id="' . $tid . '">' . esc_html( $c[3] ) . '</span></span>';
	}
	$ac .= '<article class="ezpg-acard"><div class="ezpg-acard__img">' . ( ! empty( $alumni_imgs[ $c[0] ] ) ? '<img src="' . esc_url( $alumni_imgs[ $c[0] ] ) . '" alt="' . esc_attr( $c[1] ) . '" loading="lazy">' : '' ) . '</div><div class="ezpg-acard__head"><h3>' . esc_html( $c[1] ) . '</h3>' . $tip . '</div><p>' . esc_html( $c[2] ) . '</p></article>';
}
$sections[] = ezh_section(
	'ezpg-alumni',
	'<div class="ezpg-inner"><span class="ezpg-eyebrow">After you graduate</span><h2>How We <span class="ez37-hl">Support Our Alumni</span></h2>
	<p class="ezpg-alumni__lead">The graduation is a landmark event in the coach training calendar. It is a symbol of progression to the start of the next chapter in the life of a Certified Coach.</p>
	<p class="ezpg-alumni__lead">We provide support to our alumni through a dedicated team led by an ICF credential coach, offering opportunities for alumni to demonstrate their coaching skills to clients and succeed as professional coaches.</p>
	<div class="ezpg-alumni__grid">' . $ac . '</div></div>'
);
}

/* 8. Policies: ONE tab component */
if ( ! empty( $p['policies'] ) ) {
	$sections[] = ezh_section(
		'ezpg-policies',
		'<div class="ezpg-inner"><h2>Programme <span class="ez37-hl">Policies</span></h2>' . $acc_html( 'ezpg-policies', $p['policies'] ) . '</div>'
	);
}

/* 6c. Testimonials from past participants of this programme (permissions still to be confirmed before go-live) */
$quotes_all = array(
	'david-barnard-pccp' => array( 'David Barnard', 'The Coach Certification Programme (PCCP) attracted a varied group of experienced business leaders, enhancing the collaborative learning in both formal group sessions and self-managed practice pods. The course was well structured and facilitated to create a safe and highly engaged team atmosphere.', 'David Barnard', 'PCCP Alumni' ),
	'stephanie-emodi'    => array( 'Stephanie Emodi', 'The program delivery was of the highest professional quality and I have grown from the experience in confidence, increased self-awareness, actions implemented from the practice coaching sessions all in addition to the actual coaching knowledge gained.', 'Stephanie Emodi', 'PCCP Alumni' ),
	'julia-esezobor'     => array( 'Julia Esezobor', 'Very positive, EZ37 is professional in their approach. The learning content, team of facilitators and the program support was exceptional! The learning outcomes were clear and the delivery consistently world-class. The program did not just focus on making us coaches but also on helping us be all-round better human beings.', 'Julia Esezobor', 'PCCP Alumni' ),
);
$qh = '';
foreach ( $p['quotes'] as $slug ) {
	if ( ! isset( $quotes_all[ $slug ] ) ) {
		continue;
	}
	$q   = $quotes_all[ $slug ];
	$img = $testi[ $slug ] ?? '';
	$av  = $img ? '<img src="' . esc_url( $img ) . '" alt="' . esc_attr( $q[0] ) . '" loading="lazy">' : '<i>' . esc_html( mb_substr( $q[0], 0, 1 ) ) . '</i>';
	$qh .= '<div class="ezh-quote"><div class="q">&ldquo;</div><p>' . esc_html( $q[1] ) . '</p><div class="ezh-person">' . $av . '<div><b>' . esc_html( $q[2] ) . '</b><span>' . esc_html( $q[3] ) . '</span></div></div></div>';
}
if ( '' !== $qh ) {
	$sections[] = ezh_section( 'ezpg-quotes', '<div class="ezpg-inner"><h2>What People <span class="ez37-hl">Say About Us</span></h2><div class="ezpg-quotes__grid">' . $qh . '</div></div>' );
}

/* Pull quote (optional) */
if ( ! empty( $p['pullquote'] ) ) {
	$sections[] = ezh_section( 'ezs-quote', '<div class="ezh-wrap"><figure class="ezs-quote__fig"><span class="ezs-quote__mark" aria-hidden="true">&ldquo;</span><blockquote>' . esc_html( $p['pullquote'][0] ) . '</blockquote><figcaption>' . esc_html( $p['pullquote'][1] ) . '</figcaption></figure></div>' );
}

/* 9. Enrolment questions (describe the real flow; no policy wording is invented here) */
$sections[] = ezh_section_faq(
	$p['faq'] ?? array(
		array( 'How do I enrol?', 'Choose Enrol Now, complete the enrolment form, read and accept the Enrolment Agreement, then pay securely on Stripe. It takes a couple of minutes.' ),
		array( 'Where can I read the Enrolment Agreement?', 'On the enrolment page, before you pay. Tick the agreement box or follow the link and it opens in a panel on the right of the screen. You must accept it to continue, and a record of your acceptance is stored with your order.' ),
		array( 'How do I pay?', 'Card payments (including Apple Pay and Google Pay where your device supports them) are taken securely by Stripe. EZ37 Solutions never sees or stores your card details.' ),
		array( 'What happens after I pay?', 'You receive an email confirming your payment, your receipt from Stripe and your programme material. If nothing arrives within a few minutes, check your spam folder, then contact us.' ),
		array( 'Can my organisation pay, or do you issue invoices?', 'Yes. Please use our Contact page to tell us what your organisation needs and we will arrange it with you.' ),
	)
);

/* 10. Closing CTA band (existing component) */
$sections[] = ezh_section(
	'ezp-enrol',
	'<div class="ezh-wrap"><div class="ezp-enrol">
	<div><h2>' . ( $p['cta_h2'] ?? 'Ready To Start Your <span class="ez37-hl">Certification Journey?</span>' ) . '</h2>
	<p>Enrol online in a couple of minutes. You will pay securely on Stripe and receive your confirmation and programme material by email.</p></div>
	<div class="ezp-enrol__cta"><a class="ez37-btn ez37-btn--lg" href="' . esc_url( $checkout ) . '">Enrol Now ' . ezh_i( 'arrow' ) . '</a>
	<span class="ezp-enrol__note">' . ezh_i( 'lock' ) . 'Secure payment by Stripe</span></div>
	</div></div>'
);

$comp_js = file_exists( dirname( __DIR__ ) . '/design/ez37-components.js' ) ? (string) file_get_contents( dirname( __DIR__ ) . '/design/ez37-components.js' ) : '';
if ( '' === $comp_js ) {
	WP_CLI::warning( 'design/ez37-components.js not found; the policy tabs will not switch.' );
}
$last = count( $sections ) - 1;
$sections[ $last ]['elements'][0]['settings']['html'] .= '<script>' . $comp_js . '</script>';

ezh_save_page( $page->ID, $sections, array( 'ez37-redesign.css', 'ez37-home.css', 'ez37-programme.css', 'ez37-bhero.css', 'ez37-leadership.css', 'ez37-components.css', 'ez37-pcert.css', 'ez37-quote.css' ) );

WP_CLI::success( $p['title'] . ' built: ' . get_permalink( $page->ID ) );
