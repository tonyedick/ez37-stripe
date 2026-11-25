<?php
/**
 * EZ37 redesign — build the HOME (v2) page. LOCAL DEVELOPMENT ONLY.
 *
 * Fills the private "Home v2" page (created by header-footer-local.php) with
 * every home section, laid out to follow the reference design but using only
 * the EZ37 design-system colours, fonts and buttons.
 *
 * Each section is an Elementor container (so spacing/background stay editable)
 * holding one HTML widget with the section markup. Copy is PLACEHOLDER copy.
 *
 * Run from the project root:
 *   ddev exec wp --path=/var/www/html/wordpress eval-file /var/www/html/bin/home-local.php
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

$page = get_page_by_path( 'home-v2', OBJECT, 'page' );
if ( ! $page ) {
	WP_CLI::error( 'Page home-v2 not found. Run bin/header-footer-local.php first.' );
}

/* ----------------------------------------------------------------- helpers */
require_once __DIR__ . '/lib/ezh-lib.php';
$contact  = ezh_url( 'contact-us' );
$solution = ezh_url( 'solutions' ); // Solutions page (bin/solutions-local.php)
$cases    = ezh_url( 'contact-us' ); // 'Case Studies' is menu-only (no page); placeholder target until case-study pages exist
$blog     = ezh_url( 'blog' );
$about    = ezh_url( 'about' );
$logos    = array( 'Client Logo', 'Partner Name', 'Organisation', 'Company Name', 'Institution', 'Brand Name' );

$logo_urls = ezh_logo_urls();

$sections = array();

/* ------------------------------------------------------------------ 1 hero (full-bleed photo carousel) */
/* Slides are photos from design/*; add or swap files there (new filenames for replacements) and list them here. */
$hi = ezh_media_dir( 'hero', '_ez37_hero' ); // design/hero/banner-1..3.jpg (wide photos that scale to desktop)
// focal point per photo so faces stay visible when the image is cropped (desktop x, mobile x)
/* Navigation style for this page: 'flat' = full-width white bar (second main nav); '' = the rounded floating bar (default site-wide). */
$nav_flat = true;
$nav_switch = true; // DEV ONLY: shows a small "Nav: Flat/Rounded" switch bottom-left so both navs can be compared. Set false (or remove) before go-live.
$copy_default = array( 'From Strategy to Execution <span>at Scale.</span>', 'We equip executives, teams and institutions with the coaching, assessment and learning frameworks to lead confidently, so strategy turns into results people can see.' );
$copy_two     = array( 'Your Partner in <span>Business Excellence</span>', 'Delivering tailored coaching, HR advisory, and learning programmes for organisational success.' );
$copy_three   = array( 'Navigate Your Career <span>with Coaching</span>', 'We specialise in delivering diverse coaching and learning and development programmes that inspire employees to be more engaged and motivated.' );
// photo, focal point desktop, focal point mobile, [headline html, paragraph]
$hero_slides = array(
	array( $hi['banner-1'] ?? '', '68% 30%', '62% 30%', $copy_default ),
	array( $hi['banner-2'] ?? '', '72% 30%', '70% 30%', $copy_two ),
	array( $hi['banner-3-sharp'] ?? '', '70% 30%', '66% 30%', $copy_three ),
);
$hero_slides = array_values( array_filter( $hero_slides, static function ( $x ) { return '' !== $x[0]; } ) );
$hs = '';
$hd = '';
$hc = '';
$btns = '<div class="ezs-hero__btns"><a class="ez37-btn" href="' . esc_url( ezh_calendly_url() ) . '" target="_blank" rel="noopener">Book a Consultation</a><a class="ezs-hero__ghost" href="' . esc_url( $solution ) . '">Explore Solutions</a></div>';
foreach ( $hero_slides as $i => $sl ) {
	$hs .= '<div class="ezs-hero__slide' . ( 0 === $i ? ' is-on' : '' ) . '" style="background-image:url(' . esc_url( $sl[0] ) . ');--pos:' . $sl[1] . ';--posm:' . $sl[2] . '" role="img" aria-label="EZ37 coaching and leadership programmes"></div>';
	$hc .= '<div class="ezs-hero__copy' . ( 0 === $i ? ' is-on' : '' ) . '"><' . ( 0 === $i ? 'h1' : 'h2' ) . ' class="ezs-hero__title">' . $sl[3][0] . '</' . ( 0 === $i ? 'h1' : 'h2' ) . '><p>' . $sl[3][1] . '</p>' . $btns . '</div>';
	$hd .= '<button type="button" class="ezs-hero__dot' . ( 0 === $i ? ' is-on' : '' ) . '" aria-label="Show slide ' . ( $i + 1 ) . '"></button>';
}
$hero_js = '(function(){var r=document.getElementById("ezs-hero");if(!r||r._ez){return;}r._ez=1;var sl=r.querySelectorAll(".ezs-hero__slide"),cp=r.querySelectorAll(".ezs-hero__copy"),dt=r.querySelectorAll(".ezs-hero__dot"),i=0,t,reduce=window.matchMedia&&window.matchMedia("(prefers-reduced-motion: reduce)").matches;'
	. 'function go(n){i=(n+sl.length)%sl.length;sl.forEach(function(s,k){s.classList.toggle("is-on",k===i);});cp.forEach(function(c,k){c.classList.toggle("is-on",k===i);});dt.forEach(function(d,k){d.classList.toggle("is-on",k===i);});}'
	. 'function start(){if(reduce||sl.length<2){return;}clearInterval(t);t=setInterval(function(){go(i+1);},6000);}'
	. 'dt.forEach(function(d,k){d.addEventListener("click",function(){go(k);start();});});r.addEventListener("mouseenter",function(){clearInterval(t);});r.addEventListener("mouseleave",start);start();})();';
$switch_js = '(function(){var m=document.querySelector(".ezs-navflat");if(!m){return;}var par=m.parentNode,K="ezNav",q=new URLSearchParams(location.search).get("nav"),cur=q||(function(){try{return sessionStorage.getItem(K);}catch(e){return null;}})()||"flat";'
	. 'var b=document.createElement("button");b.type="button";b.className="ezs-navswitch";document.body.appendChild(b);'
	. 'function apply(v){cur=v;if(v==="flat"){if(!m.isConnected){par.insertBefore(m,par.firstChild);}}else{m.remove();}b.textContent="Nav: "+(v==="flat"?"Flat":"Rounded")+" (tap to switch)";try{sessionStorage.setItem(K,v);}catch(e){}}'
	. 'b.addEventListener("click",function(){apply(cur==="flat"?"rounded":"flat");});apply(cur);})();';
$sections[] = ezh_section(
	'ezs-hero',
	'<div class="ezs-hero__stage" id="ezs-hero">' . ( $nav_flat ? '<span class="ezs-navflat" hidden></span>' : '' ) . '<div class="ezs-hero__slides">' . $hs . '</div><div class="ezs-hero__shade"></div>
	<div class="ezh-wrap ezs-hero__inner"><div class="ezs-hero__copies">' . $hc . '</div></div>
	<div class="ezs-hero__dots">' . $hd . '</div><script>' . $hero_js . '</script>' . ( $nav_flat && $nav_switch ? '<script>' . $switch_js . '</script>' : '' ) . '</div>'
);

/* ------------------------------------------------------------------ 3 logos */
$sections[] = ezh_section( 'ezh-logos', '<div class="ezh-wrap"><p>Trusted by organisations that invest in their people</p></div>' . ezh_marquee( $logo_urls ) );


/* -------------------------------------------------------------- 6 about (EZ37 Difference) */
$about_imgs = ezh_media_dir( 'about', '_ez37_about' ); // design/about/main.jpg + inset.jpg
$ceo_img    = ( ezh_media_dir( 'coaches', '_ez37_coach' ) )['adaora-ayoade'] ?? '';
function ezh_about_img( string $url, string $alt, string $cls ): string {
	if ( $url ) {
		return '<div class="' . $cls . '"><img src="' . esc_url( $url ) . '" alt="' . esc_attr( $alt ) . '" loading="lazy"></div>';
	}
	return '<div class="' . $cls . ' ezh-about__ph" role="img" aria-label="' . esc_attr( $alt ) . '"></div>';
}
$sections[] = ezh_section(
	'ezh-about',
	'<div class="ezh-wrap"><div class="ezh-about__grid">
	<div class="ezh-about__text"><span class="ezs-eyebrow">Discover the EZ37 Difference</span>
		<h2 class="ezh-h2">Excellence in <span class="ez37-hl">Coaching and Beyond</span></h2>
		<p class="ezh-lead">Since 2009, EZ37 Solutions Limited has been a trusted leader in Human Resource and Management Consulting. We specialise in coaching, learning and development, assessments, HR advisory, and public sector solutions.</p>
		<p class="ezh-lead">Our mission is to empower organisations and individuals to unlock their potential, drive performance, and achieve lasting success.</p>
		<div class="ezh-about__meta">
			<div class="ezh-ceo">' . ( $ceo_img ? '<img src="' . esc_url( $ceo_img ) . '" alt="Adaora Ayoade" loading="lazy">' : '' ) . '<div><b>Adaora Ayoade</b><span>MD, EZ37 Solutions</span></div></div>
			<div class="ezh-years"><b>17</b><span>Years of<br>Experience</span></div>
		</div>
		<a class="ez37-btn" href="' . esc_url( ezh_calendly_url() ) . '" target="_blank" rel="noopener">Book a Consultation ' . ezh_i( 'arrow' ) . '</a></div>
	<div class="ezh-about__visual">'
	. ezh_about_img( $about_imgs['difference-main'] ?? ( $about_imgs['main'] ?? '' ), 'EZ37 coaching session with a team at the flipchart', 'ezh-about__main' )
	. ezh_about_img( $about_imgs['inset'] ?? '', 'EZ37 client conversation', 'ezh-about__inset' )
	. '</div>
	</div></div>'
);

/* ------------------------------------------------- quote band (from the live site) */
$sections[] = ezh_section(
	'ezs-quote',
	'<div class="ezh-wrap"><figure class="ezs-quote__fig"><span class="ezs-quote__mark" aria-hidden="true">&ldquo;</span><blockquote>Visionary companies don&rsquo;t just demand the best talent &ndash; they create it</blockquote><figcaption>Harvard Business School</figcaption></figure></div>'
);

/* ------------------------------------------------- 4 solutions card grid */
/* Plain, standard layout (cards with a photo on top). Placeholder photos: swap files in design/about, design/process, design/case. */
$img_about   = ezh_media_dir( 'about', '_ez37_about' );
$img_proc    = ezh_media_dir( 'process', '_ez37_proc' );
$img_case    = ezh_media_dir( 'case', '_ez37_case' );
$cards = array(
	array( $img_proc['step-1'] ?? '', 'Leadership and Executive Coaching', 'One-to-one coaching that helps leaders decide, delegate and grow.', 'Get Started', $contact ),
	array( $img_about['hero-workshop'] ?? '', 'Group and Team Coaching', 'Build shared language, trust and results across your teams.', 'Explore team coaching', $solution ),
	array( $img_proc['step-2'] ?? '', 'Mentor Coach Specialisation', 'Develop the next generation of mentors inside your organisation.', 'Learn more', $solution ),
	array( $img_proc['step-3'] ?? '', 'Coaching Certification', 'Professional and Advanced Coach Certification programmes.', 'View programmes', $solution ),
	array( $img_case['case-study-meeting'] ?? '', 'Assessments', 'Psychometric and behavioural assessments grounded in evidence.', 'Explore assessments', $solution ),
	array( $img_about['inset'] ?? '', 'Coaching Skills for Managers', 'Practical coaching skills for managers and leaders.', 'Request training', $contact ),
);
$ch = '';
foreach ( $cards as $c ) {
	$ch .= '<article class="ezs-card"><div class="ezs-card__img">' . ( $c[0] ? '<img src="' . esc_url( $c[0] ) . '" alt="" loading="lazy">' : '' ) . '</div><div class="ezs-card__body"><h3>' . esc_html( $c[1] ) . '</h3><p>' . esc_html( $c[2] ) . '</p><a class="ezs-link" href="' . esc_url( $c[4] ) . '">' . esc_html( 'Explore ' . $c[1] ) . '</a></div></article>';
}
$sections[] = ezh_section( 'ezs-grid', '<div class="ezh-wrap"><span class="ezs-eyebrow">Our Solutions</span><h2 class="ezs-h2">How We <span>Support You</span></h2><div class="ezs-cards">' . $ch . '</div></div>' );
$ezs_grid_idx = count( $sections ) - 1;

/* The three feature bands (Coaching programmes, Assessments, Learning and certification) now live on the Solutions page: bin/solutions-local.php */

/* ---------------------------------------------------------------- 7 numbers */
$testi = ezh_media_dir( 'testimonials', '_ez37_testi' );
function ezh_testi( string $img, string $alt, string $quote, string $name, string $role ): string {
	$av = $img ? '<img src="' . esc_url( $img ) . '" alt="' . esc_attr( $alt ) . '" loading="lazy">' : '<i>' . esc_html( mb_substr( $alt, 0, 1 ) ) . '</i>';
	return '<div class="ezh-quote"><div class="q">&ldquo;</div><p>' . $quote . '</p><div class="ezh-person">' . $av . '<div><b>' . $name . '</b><span>' . $role . '</span></div></div></div>';
}
/* Testimonials carousel: 2 per view on desktop, 1 on mobile. Add a testimonial = add one ezh_testi() line; photo = design/testimonials/<slug>.jpg.
   All six entries are real testimonials (permissions still to be confirmed before go-live). */
/* Full-length versions were trimmed to their first paragraph to keep the carousel compact. Removed paragraphs:
   - ¶ This programme, with its meticulously designed curriculum and practical approach to both online and in-person learning, has truly enhanced my skill set and broadened my horizons. I highly recommend it to anyone seeking to develop a robust understanding of modern teaching methods and create more engaging and effective learning experiences.
   - ¶ I am already applying the principles and skills from the training, so I can confidently say it was a good investment.
   - ¶ The Professional Coach Certification Programme attracted a varied group of experienced business leaders, enhancing collaborative learning in both formal group sessions and self-managed practice pods. ¶ The course was well structured and facilitated to create a safe and highly engaged team atmosphere.
   - ¶ I met new people and built a network of coaches and future professional colleagues. The class pods were awesome!
*/
$tcards = array(
	ezh_testi( $testi['nafisah-muhammad-buba'] ?? '', 'Nafisah Muhammad Buba', 'My experience training with EZ37 Solutions Ltd was absolutely AWESOME! With over 20 years in HR leadership, this programme gave me the exact foundation I needed for personal growth and to launch my own coaching practice for young professionals.', 'Nafisah Muhammad Buba, ACC', 'Head Human Capital Management and Administrative Services' ),
	ezh_testi( $testi['tayo-ilori'] ?? '', 'Tayo Ilori', 'EZ37 Solutions Ltd provided a very professional and engaging experience. The programme gave me the exact leadership edge I needed to step into a coach manager role and build my own coaching practice.', 'Tayo Ilori, ACC', 'Director, Supply Chain Operations, Honeywell Flour Mills Plc' ),
	ezh_testi( $testi['sanet-olufunmilola-de-lange'] ?? '', 'Sanet Olufunmilola de Lange', 'Adaora was amazing. Her wealth of knowledge, practical insights, and unwavering support made this experience incredibly valuable and impactful. It is not just about what she taught, but how it sparked our curiosity to learn more and delve deeper.', 'Sanet Olufunmilola de Lange', 'Founder and MD, NexaRoot' ),
	ezh_testi( $testi['nnennaya-kalu-umeh'] ?? '', 'Nnennaya Kalu-Umeh', 'Thank you EZ37 Solutions Limited, Coach Adaora and the team. The &lsquo;Facilitating Learning Online and In-Person&rsquo; course has been on my wishlist for some time, and I am so glad that I finally did it.', 'Nnennaya Kalu-Umeh', 'Technical Advisor to DG/CEO and Programme Manager, NHIA Leadership Development Programme (NLDP)' ),
	ezh_testi( $testi['david-barnard'] ?? '', 'David Barnard', 'EZ37 Solutions Ltd drew together an outstanding group of tutors from Africa, Europe, and North America. Each brought distinctive perspectives and experience to the learning of our group.', 'David Barnard, ACC', 'Founder and Director, Pracsy Advisory Limited' ),
	ezh_testi( $testi['brighton-ochieng'] ?? '', 'Brighton Ochieng', 'It was very refreshing for me, especially to fully understand what coaching is and is not. I was able to learn from the best on how to truly coach. The mentor coaching sessions were a real revelation, helping me fully understand and experience the ICF Core Competencies in practice.', 'Brighton Ochieng', 'Chief Operations/Technical Officer, Promasidor Nigeria' )
);
$tcar_js = '(function(){var r=document.getElementById("ezs-tcar");if(!r||r._ez){return;}r._ez=1;var tr=r.querySelector(".ezs-tcar__track"),pv=r.querySelector(".ezs-tcar__prev"),nx=r.querySelector(".ezs-tcar__next"),dots=r.querySelector(".ezs-tcar__dots"),cards=tr.children,pages=1;'
	. 'function per(){return window.matchMedia("(min-width:768px)").matches?2:1;}'
	. 'function build(){pages=Math.max(1,Math.ceil(cards.length/per()));dots.innerHTML="";for(var i=0;i<pages;i++){(function(i){var b=document.createElement("button");b.type="button";b.className="ezs-tcar__dot";b.setAttribute("aria-label","Show testimonials "+(i+1));b.addEventListener("click",function(){go(i);});dots.appendChild(b);})(i);}r.classList.toggle("is-single",pages<2);upd();}'
	. 'function cur(){var w=tr.clientWidth;return w?Math.round(tr.scrollLeft/w):0;}'
	. 'function go(i){tr.scrollTo({left:i*tr.clientWidth,behavior:"smooth"});}'
	. 'function upd(){var c=Math.min(cur(),pages-1);[].forEach.call(dots.children,function(d,k){d.classList.toggle("is-on",k===c);});pv.disabled=c<=0;nx.disabled=c>=pages-1;}'
	. 'pv.addEventListener("click",function(){go(cur()-1);});nx.addEventListener("click",function(){go(cur()+1);});tr.addEventListener("scroll",function(){window.requestAnimationFrame(upd);},{passive:true});window.addEventListener("resize",build);build();})();';
$arrow_l = '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 6l-6 6 6 6"/></svg>';
$arrow_r = '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 6l6 6-6 6"/></svg>';
$sections[] = ezh_section(
	'ezh-numbers',
	'<div class="ezh-wrap"><div class="ezh-sec-head ezh-center"><span class="ezs-eyebrow">Proven results</span><h2 class="ezh-h2">What Our <span class="ez37-hl">Clients Are Saying</span></h2><p class="ezs-proof__intro">As a top-ranked provider of leadership development, we&rsquo;ve shaped the industry for more than 17 years and are trusted globally to design and deliver solutions that enable individuals and organisations to reach their full potential. Clients around the world have partnered with us for new managers and frontline leaders leadership training, driving remarkable transformations at their organisations.</p></div>
	<div class="ezs-tcar" id="ezs-tcar"><div class="ezs-tcar__track">' . implode( '', $tcards ) . '</div>
	<div class="ezs-tcar__nav"><button type="button" class="ezs-tcar__prev" aria-label="Previous testimonials">' . $arrow_l . '</button><div class="ezs-tcar__dots"></div><button type="button" class="ezs-tcar__next" aria-label="Next testimonials">' . $arrow_r . '</button></div>
	<script>' . $tcar_js . '</script></div></div>'
);

// Testimonials sit straight after the Our Solutions card grid, before the feature bands.
$ezs_testi = array_pop( $sections );
array_splice( $sections, $ezs_grid_idx + 1, 0, array( $ezs_testi ) );

// Case study card sits straight after the Testimonials. Set $show_case to false to hide it again.
$show_case = true;
$ezs_case  = $show_case ? ezh_section_case( $img_case['case-study-meeting'] ?? '', ezh_url( 'case-team-coaching-fmcg' ), $contact ) : null;

/* --------------------------------------------------------------- 4 problem */
/* Hidden for now (kept in the project): set to true to show "The challenge" again.
   The section itself lives in bin/lib/ezh-lib.php as ezh_section_problem() so other pages can use it. */
$show_problem = false;
if ( $show_problem ) {
	$sections[] = ezh_section_problem( $contact );
}

/* ------------------------------------------------------------ 10 framework */
/* Hidden for now (kept in the project): set to true to show "The Six Levels" again.
   The section lives in bin/lib/ezh-lib.php as ezh_section_framework() so other pages can use it. */
$show_framework = false;
if ( $show_framework ) {
	$sections[] = ezh_section_framework( $about );
}

/* --------------------------------------------------------------- 11 resources */
/* Hidden for now (kept in the project): set to true to show "Resources" again.
   The section lives in bin/lib/ezh-lib.php as ezh_section_resources() so other pages can use it. */
/* Programme tabs: two programmes people can enrol in from Home (Enrol Now goes to each programme page). */
$lead_imgs = ezh_media_dir( 'leadership', '_ez37_lead' );
$sections[] = ezh_section_programmes(
	array(
		array(
			'Coaching Skills Training for Managers and Leaders',
			'Lead by coaching',
			'Coaching Skills Training for Managers and Leaders',
			'The coaching skills for Managers and Leaders training will empower the leadership in utilising coaching skills to support members of their team which leads to employee retention, strong employer brand as well as increased productivity and profit.',
			array( 'What is Coaching', 'Difference between coaching and mentoring', 'Coaching Models and Tools', 'Benefits of Coaching to Managers', 'Embedding a Coaching Culture' ),
			ezh_media_dir( 'about', '_ez37_about' )['accp-conversation'] ?? '',
			ezh_url( 'coaching-skills-training' ),
		),
		array(
			'Professional Coaching Certification',
			'Pathway to ACC',
			'Professional Coaching Certification Programme (PCCP)',
			'A 60-hour ICF-accredited programme, including 10 hours of mentor coaching, that gives you a clear pathway to the ICF ACC credential.',
			array( 'Develop and improve leadership skills', 'Fast track personal and professional development', 'Gain financial independence', 'Work from any location', 'Strengthen relationships', 'Expand professional networks' ),
			$lead_imgs['team-flipchart'] ?? '',
			ezh_url( 'pccp' ),
		),
		array(
			'Advanced Coach Certification Programme (ACCP)',
			'ICF Level 2 accredited',
			'Advanced Coach Certification Programme (ACCP)',
			'The Advanced Coach Certification Programme (ACCP) aims for participants to demonstrate the ICF core competencies at an advanced level, thus providing excellent value for clients and organisations. The ACCP is designed to meet the Level 2 Coaching Education requirements of the ICF.',
			array( 'Gain confidence to work with a broader range of clients by developing advanced coaching skills', 'Attain the required training hours to apply for a PCC credential', 'Support to accumulate qualification practice hours for a PCC credential' ),
			ezh_media_dir( 'about', '_ez37_about' )['accp-meeting'] ?? '',
			ezh_url( 'accp' ),
		),
	)
);

/* Alumni band: the free Peer to Peer programme sits right under the paid programmes. */
$peer_imgs  = ezh_media_dir( 'peer', '_ez37_peer_img' );
$sections[] = ezh_section(
	'ezs-band is-bleed',
	'<div class="ezs-band__grid">'
	. '<div class="ezs-band__text"><span class="ezs-eyebrow">For EZ Coaching <em class="ezh-circled"><span>alumni</span>' . $circle_svg . '</em></span><h2 class="ezs-h2">EZ <span class="ez37-hl">Peer to Peer</span> Coaching</h2>'
	. '<p>Build your ICF coaching hours by coaching and being coached by fellow certified coaches. Free to join, with sessions arranged at times that suit you and your peer.</p>'
	. '<p class="ezs-band__extra is-callout"><strong>Free to enrol</strong><br><strong>Work towards your ACC, PCC or MCC credential</strong><br>Stay accountable to your goals.</p>'
	. '<a class="ez37-btn" href="' . esc_url( ezh_url( 'peer-to-peer-coaching' ) ) . '">Explore Peer to Peer ' . ezh_i( 'arrow' ) . '</a></div>'
	. '<div class="ezs-band__img">' . ( ! empty( $peer_imgs['peer-people-hd'] ) ? '<img src="' . esc_url( $peer_imgs['peer-people-hd'] ) . '" alt="Three smiling coaches" loading="lazy" style="object-position:50% 40%">' : '' ) . '</div>'
	. '</div>'
);

$ezs_process = ezh_section_process(); // How We Partner With You; placed straight after the Case study (see splice below)
$show_resources = true;
if ( $show_resources ) {
	$sections[] = ezh_section_resources( $blog, $cases, $contact );
}

/* ------------------------------------------------------------------ 12 team */
$coach_imgs = ezh_media_dir( 'coaches', '_ez37_coach' );

// slug, name, portfolio (credential), profile URL. Credentials are from the "Meet the Faculty" list on the current site.
$coaches = array(
	array( 'adaora-ayoade', 'Adaora Ayoade', 'ICF-MCC', 'https://www.linkedin.com/in/adaora-ayoade-mcipm-phri-miod' ),
	array( 'peter-a-james', 'Dr. Peter A. James', 'ICF-PCC', 'https://www.linkedin.com/in/drpeterajames' ),
	array( 'svea-van-der-hoorn', 'Dr. Svea van der Hoorn', 'ICF-MCC', '' ),
	array( 'patricia-akinlotan', 'Patricia Akinlotan', 'ICF-PCC', 'https://www.linkedin.com/in/patriciaakinlotan' ),
	array( 'zuzanna-mazurek', 'Zuzanna Mazurek', 'PCC, ICF Coach', 'https://www.linkedin.com/in/zuzanna-mazurek' ),
	array( 'david-barnard', 'David Barnard', 'ACC, ICF Coach', 'https://www.linkedin.com/in/david-barnard-atpracsys' ),
	array( 'barbara-lawrence', 'Barbara Lawrence', 'PCC, ICF Coach', 'https://www.linkedin.com/in/bolawrence' ),
	array( 'abigail-isokpan', 'Abigail Isokpan', 'MCC, ICF Coach', 'https://www.linkedin.com/in/abigail-isokpan-29196b7' ),
	array( 'jass-malaney', 'Jass Malaney', 'PCC, ICF Coach', 'https://ezcoaching.org' ),
	array( 'gloria-gbemudu', 'Gloria Gbemudu', 'PCC, ICF Coach', 'https://www.linkedin.com/in/coachgloriagbemudu' ),
	array( 'samson-umurhurhu', 'Samson Umurhurhu', 'PCC, ICF Coach', 'https://www.linkedin.com/in/samson-umurhurhu' ),
	array( 'femi-odelusi', 'Femi Odelusi', 'PCC, ICF Coach', 'https://www.linkedin.com/in/femiodelusi' ),
);

$in_svg = '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M4.98 3.5a2.5 2.5 0 1 1 0 5 2.5 2.5 0 0 1 0-5zM3 9.5h4V21H3zM9.5 9.5h3.8v1.6h.1c.5-1 1.8-2 3.8-2 4 0 4.8 2.6 4.8 6V21h-4v-5c0-1.2 0-2.8-1.7-2.8s-2 1.3-2 2.7V21h-4z"/></svg>';
// Hand-drawn ellipse that draws itself around the surname (same effect as Elementor's animated headline "circle").
$circle_svg = '<svg class="ezh-circle" viewBox="0 0 300 80" preserveAspectRatio="none" aria-hidden="true"><path pathLength="1" d="M268 20C240 4 150 0 70 10C18 17 4 42 40 60C82 78 222 80 272 56C298 42 288 20 226 8"/></svg>';
$panels = '';
$thumbs = '';
foreach ( $coaches as $i => $c ) {
	list( $slug, $name, $cred, $link ) = $c;
	$parts    = explode( ' ', $name );
	$last     = array_pop( $parts );
	$first    = implode( ' ', $parts );
	$img      = $coach_imgs[ $slug ] ?? '';
	$words    = preg_split( '/\s+/', trim( preg_replace( '/^Dr\.\s+/', '', $name ) ) );
	$initials = strtoupper( $words[0][0] . end( $words )[0] );
	$photo    = $img
		? '<img src="' . esc_url( $img ) . '" alt="' . esc_attr( $name ) . '" loading="lazy" decoding="async">'
		: '<span class="ezh-initials" aria-hidden="true">' . esc_html( $initials ) . '</span>';
	$is_li    = false !== strpos( $link, 'linkedin.com' );
	$btn      = $link
		? '<a class="ezs-link ezh-in" href="' . esc_url( $link ) . '" target="_blank" rel="noopener noreferrer" aria-label="' . esc_attr( $name . ( $is_li ? ' on LinkedIn' : ' profile' ) ) . '">' . ( $is_li ? 'View LinkedIn profile' : 'View profile' ) . '</a>'
		: '';
	$panels  .= '<div class="ezh-coach__panel" data-coach="' . esc_attr( $slug ) . '"' . ( 0 === $i ? '' : ' hidden' ) . '>'
		. '<div class="lbl">' . ( $first ? '<span>' . esc_html( $first ) . '</span>' : '' ) . '<em class="ezh-circled"><span>' . esc_html( $last ) . '</span>' . $circle_svg . '</em></div>'
		. '<div class="ezh-portrait">' . $photo . '</div>'
		. '<div class="ezh-coach__portfolio"><span class="ezh-cred">' . esc_html( $cred ) . '</span></div>'
		. $btn . '</div>';
	$thumbs  .= '<button type="button" class="ezh-thumb' . ( 0 === $i ? ' is-active' : '' ) . '" data-coach="' . esc_attr( $slug ) . '" aria-pressed="' . ( 0 === $i ? 'true' : 'false' ) . '" aria-label="Show ' . esc_attr( $name ) . '">'
		. ( $img ? '<img src="' . esc_url( $img ) . '" alt="" loading="lazy" decoding="async">' : '<span class="ezh-initials" aria-hidden="true">' . esc_html( $initials ) . '</span>' )
		. '<span class="ezh-thumb__name">' . esc_html( $name ) . '</span></button>';
}

$coach_js = '(function(){var r=document.getElementById("ezh-coaches");if(!r||r._ez){return;}r._ez=1;'
	. 'var panels=r.querySelectorAll(".ezh-coach__panel"),thumbs=r.querySelectorAll(".ezh-thumb"),card=r.querySelector(".ezh-coach");'
	. 'function show(id){panels.forEach(function(p){p.hidden=p.getAttribute("data-coach")!==id;});'
	. 'thumbs.forEach(function(t){var on=t.getAttribute("data-coach")===id;t.classList.toggle("is-active",on);t.setAttribute("aria-pressed",on?"true":"false");});}'
	. 'thumbs.forEach(function(t){t.addEventListener("click",function(){show(t.getAttribute("data-coach"));'
	. 'if(window.matchMedia("(max-width:767px)").matches&&card){card.scrollIntoView({behavior:"smooth",block:"start"});}});});})();';

$sections[] = ezh_section(
	'ezh-team',
	'<div class="ezh-wrap"><div class="ezh-sec-head ezh-center"><h2 class="ezh-h2">Meet Your <span class="ez37-hl">Coaching Team</span></h2><p class="ezh-lead">Every coach is credentialed, experienced and matched to your industry, stage and goals.</p></div>
	<div class="ezh-team__grid" id="ezh-coaches">
	<div class="ezh-tcard ezh-coach" aria-live="polite">' . $panels . '</div>
	<div class="ezh-tcard"><div class="lbl"><span>Meet The</span><em>Faculty</em></div><div class="ezh-avatars">' . $thumbs . '</div></div></div>
	<script>' . $coach_js . '</script></div>'
);

// Order on Home: Our Solutions, Coaching Team, Testimonials, Case study, the feature bands moved to the Solutions page.
$ezs_team = array_pop( $sections );
array_splice( $sections, $ezs_grid_idx + 1, 0, array( $ezs_team ) );
if ( $ezs_case ) {
	array_splice( $sections, $ezs_grid_idx + 3, 0, array( $ezs_case ) );
}
// How We Partner With You follows the Case study (or the Testimonials if the Case study is hidden).
array_splice( $sections, $ezs_grid_idx + ( $ezs_case ? 4 : 3 ), 0, array( $ezs_process ) );

/* FAQ now lives on the About page (ezh_section_faq() in bin/lib/ezh-lib.php). */

/* The closing 'Ready To Build Leaders Worth Following?' CTA now lives on the Contact page (bin/contact-local.php). */

/* ------------------------------------------------------------------ save */
ezh_save_page( $page->ID, $sections, array( 'ez37-redesign.css', 'ez37-home.css', 'ez37-simple.css' ) );

WP_CLI::success( 'Home v2 built: ' . get_permalink( $page->ID ) );
