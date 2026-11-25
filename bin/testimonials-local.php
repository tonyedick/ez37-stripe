<?php
/**
 * EZ37 redesign — build the TESTIMONIALS page (v2) and one interview page per person. LOCAL DEVELOPMENT ONLY.
 *
 * Data: bin/data/testimonials.php (people grouped by industry, optional Q&A). Run header-footer-local.php first
 * so the interview pages exist and carry the new header/footer.
 *
 *   ddev exec wp --path=/var/www/html/wordpress eval-file /var/www/html/bin/testimonials-local.php
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
require __DIR__ . '/data/testimonials.php';

$list_page = get_page_by_path( 'testimonials-v2', OBJECT, 'page' );
if ( ! $list_page ) {
	WP_CLI::error( 'testimonials-v2 not found. Run bin/header-footer-local.php first.' );
}

$photos = ezh_media_dir( 'testimonials', '_ez37_testi' );
$css    = array( 'ez37-redesign.css', 'ez37-home.css', 'ez37-bhero.css', 'ez37-components.css', 'ez37-testimonials.css' );

$photo_for = static function ( string $slug ) use ( $photos ): string {
	return $photos[ 'tp-' . $slug ] ?? ( $photos[ $slug ] ?? '' );
};
$initials = static function ( string $name ): string {
	$name  = trim( preg_replace( '/^(Dr|Mr|Mrs|Ms|Prof)\.?\s+/i', '', $name ) );
	$parts = preg_split( '/\s+/', $name );
	return mb_strtoupper( mb_substr( $parts[0], 0, 1 ) . ( count( $parts ) > 1 ? mb_substr( end( $parts ), 0, 1 ) : '' ) );
};
$avatar = static function ( string $slug, string $name ) use ( $photo_for, $initials ): string {
	$u = $photo_for( $slug );
	return $u ? '<img src="' . esc_url( $u ) . '" alt="' . esc_attr( $name ) . '" loading="lazy">' : '<i aria-hidden="true">' . esc_html( $initials( $name ) ) . '</i>';
};
$full_name = static fn( array $t ): string => $t[0] . ( $t[1] ? ', ' . $t[1] : '' );

$arrow_l = '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 6l-6 6 6 6"/></svg>';
$arrow_r = '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 6l6 6-6 6"/></svg>';

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

/* ------------------------------------------------------------- list page */

$js = '(function(){var cars=document.querySelectorAll(".ezt-car");[].forEach.call(cars,function(r){if(r._ez){return;}r._ez=1;var tr=r.querySelector(".ezt-track"),w=r.parentNode,pv=w.querySelector(".ezt-prev"),nx=w.querySelector(".ezt-next"),dots=r.querySelector(".ezt-dots"),cards=tr.children,pages=1;'
	. 'function per(){var w=window.innerWidth;return w>=1100?4:(w>=700?2:1);}'
	. 'function step(){return cards.length>1?cards[1].offsetLeft-cards[0].offsetLeft:tr.clientWidth;}'
	. 'function cur(){return Math.min(pages-1,Math.round(Math.round(tr.scrollLeft/step())/per()));}'
	. 'function go(i){i=Math.max(0,Math.min(pages-1,i));tr.scrollTo({left:i*per()*step(),behavior:"smooth"});}'
	. 'function upd(){var c=cur();[].forEach.call(dots.children,function(d,k){d.classList.toggle("is-on",k===c);});pv.disabled=c<=0;nx.disabled=c>=pages-1;}'
	. 'function build(){pages=Math.max(1,Math.ceil(cards.length/per()));dots.innerHTML="";for(var i=0;i<pages;i++){(function(i){var b=document.createElement("button");b.type="button";b.className="ezt-dot";b.setAttribute("aria-label","Show group "+(i+1));b.addEventListener("click",function(){go(i);});dots.appendChild(b);})(i);}r.classList.toggle("is-single",pages<2);upd();}'
	. 'pv.addEventListener("click",function(){go(cur()-1);});nx.addEventListener("click",function(){go(cur()+1);});tr.addEventListener("scroll",function(){window.requestAnimationFrame(upd);},{passive:true});window.addEventListener("resize",build);build();});})();';

$chips = '';
foreach ( $ez37_testi_cats as $ck => $cl ) {
	$chips .= '<a class="ezt-chip" href="#' . esc_attr( $ck ) . '">' . esc_html( $cl ) . '</a>';
}

$sections   = array();
$img_sol = ezh_media_dir( 'solutions', '_ez37_solutions' );
$big     = $img_sol['solutions-hero'] ?? '';
$sections[] = ezh_banner_hero( 'What People Say About Us', '', array(), $big, 'Smiling EZ37 clients and business professionals', 'Testimonials', true );
$sections[] = ezh_section(
	'ezt-intro',
	'<div class="ezt-wrap"><h2 class="ezh-h2">Professional Coach <span class="ez37-hl">Certification Programme (PCCP)</span></h2>'
	. '<nav class="ezt-chips" aria-label="Industries">' . $chips . '</nav></div>'
);

$i = 0;
foreach ( $ez37_testi_cats as $ck => $cl ) {
	$cards = '';
	$n     = 0;
	foreach ( $ez37_testi_people as $slug => $t ) {
		if ( $t[4] !== $ck ) {
			continue;
		}
		++$n;
		$ext    = in_array( $slug, $ez37_testi_nopage, true ) && ! empty( $ez37_testi_links[ $slug ]['linkedin'] );
		$cards .= '<a class="ezt-card" href="' . esc_url( $ext ? $ez37_testi_links[ $slug ]['linkedin'] : ezh_url( 'testimonial-' . $slug ) ) . '"' . ( $ext ? ' target="_blank" rel="noopener"' : '' ) . '><span class="ezt-card__img">' . $avatar( $slug, $t[0] ) . '</span>'
			. '<b class="ezt-card__name">' . esc_html( $full_name( $t ) ) . '</b><span class="ezt-card__role">' . esc_html( $t[2] ) . '</span><span class="ezt-card__org">' . wp_kses( $t[3], array() ) . '</span>'
			. '<span class="ezt-card__go">' . ( $ext ? 'View LinkedIn profile ' : 'Read interview ' ) . ezh_i( 'arrow' ) . '</span></a>';
	}
	if ( ! $n ) {
		continue;
	}
	$sections[] = ezh_section(
		'ezt-cat' . ( 1 === $i % 2 ? ' is-alt' : '' ),
		'<div class="ezt-wrap" id="' . esc_attr( $ck ) . '"><div class="ezt-head"><div><h2 class="ezt-h">' . esc_html( $cl ) . '</h2></div>'
		. '<div class="ezt-ctrl"><button type="button" class="ezt-prev" aria-label="Previous in ' . esc_attr( $cl ) . '">' . $arrow_l . '</button><button type="button" class="ezt-next" aria-label="Next in ' . esc_attr( $cl ) . '">' . $arrow_r . '</button></div></div>'
		. '<div class="ezt-car"><div class="ezt-track">' . $cards . '</div><div class="ezt-dots"></div></div></div>'
	);
	++$i;
}
$sections[] = ezh_section(
	'ezt-cta',
	'<div class="ezt-wrap"><div class="ezt-cta__row"><div><h2>Ready to write <span class="ez37-hl">your own story?</span></h2><p>Start your coaching journey with EZ37 Solutions.</p></div><a class="ez37-btn ez37-btn--lg" href="' . esc_url( ezh_url( 'contact-us' ) ) . '">Get Started ' . ezh_i( 'arrow' ) . '</a></div></div>'
	. '<script>' . $js . '</script>'
);
ezh_save_page( $list_page->ID, $sections, $css );
WP_CLI::log( 'Testimonials list built: ' . get_permalink( $list_page->ID ) );

/* -------------------------------------------------------- interview pages */

$default_qs = array(
	'What led you to the Professional Coach Certification Programme?',
	'What stood out about learning with EZ37 Solutions?',
	'How has coaching changed the way you lead?',
	'What difference have you seen in your team or organisation?',
	'What would you say to someone considering the programme?',
);

$by_cat = array();
foreach ( $ez37_testi_people as $slug => $t ) {
	if ( ! in_array( $slug, $ez37_testi_nopage, true ) ) {
		$by_cat[ $t[4] ][] = $slug;
	}
}

$built = 0;
foreach ( $ez37_testi_people as $slug => $t ) {
	if ( in_array( $slug, $ez37_testi_nopage, true ) ) {
		// No interview for this person: remove any page built earlier (their card links to LinkedIn instead).
		$old_page = get_page_by_path( 'testimonial-' . $slug . '-v2', OBJECT, 'page' );
		if ( $old_page ) {
			wp_delete_post( $old_page->ID, true );
			WP_CLI::log( 'Removed interview page for ' . $slug . '.' );
		}
		continue;
	}
	$page = $page_for( 'testimonial-' . $slug, wp_strip_all_tags( html_entity_decode( $t[0] ) ) );
	$cat  = $ez37_testi_cats[ $t[4] ];
	$qa   = $ez37_testi_qa[ $slug ] ?? array();
	$real = ! empty( $qa );
	if ( ! $real ) {
		foreach ( $default_qs as $q ) {
			$qa[] = array( $q, 'Interview answer to be added.' );
		}
	}
	$items = '';
	foreach ( $qa as $k => $row ) {
		$items .= '<div class="ezt-qa"><span class="ezt-qa__n">' . ( $k + 1 ) . '</span><div><h3>' . esc_html( $row[0] ) . '</h3><p>' . wp_kses( $row[1], array( 'strong' => array(), 'em' => array(), 'br' => array() ) ) . '</p></div></div>';
	}

	$ids  = $by_cat[ $t[4] ];
	$pos  = array_search( $slug, $ids, true );
	$prev = $ids[ ( $pos + count( $ids ) - 1 ) % count( $ids ) ];
	$next = $ids[ ( $pos + 1 ) % count( $ids ) ];
	$nav  = '<a class="ezt-pn" href="' . esc_url( ezh_url( 'testimonial-' . $prev ) ) . '"><small>Previous in ' . esc_html( $cat ) . '</small>' . esc_html( $ez37_testi_people[ $prev ][0] ) . '</a>'
		. '<a class="ezt-pn is-next" href="' . esc_url( ezh_url( 'testimonial-' . $next ) ) . '"><small>Next in ' . esc_html( $cat ) . '</small>' . esc_html( $ez37_testi_people[ $next ][0] ) . '</a>';

	$li = ! empty( $ez37_testi_links[ $slug ]['linkedin'] ) ? '<a class="ezt-li" href="' . esc_url( $ez37_testi_links[ $slug ]['linkedin'] ) . '" target="_blank" rel="noopener">View LinkedIn profile ' . ezh_i( 'arrow' ) . '</a>' : '';
	$yt = ! empty( $ez37_testi_links[ $slug ]['youtube'] ) ? '<div class="ezt-video"><iframe src="https://www.youtube-nocookie.com/embed/' . esc_attr( $ez37_testi_links[ $slug ]['youtube'] ) . '" title="' . esc_attr( wp_strip_all_tags( html_entity_decode( $t[0] ) ) ) . ' interview" loading="lazy" allow="accelerometer; encrypted-media; picture-in-picture" allowfullscreen></iframe></div>' : '';

	$sec   = array();
	$sec[] = ezh_banner_hero( $full_name( $t ), $t[2] . ', ' . wp_strip_all_tags( html_entity_decode( $t[3] ) ), array(), $big, 'Smiling EZ37 clients and business professionals', array( array( 'Testimonials', ezh_url( 'testimonials' ) ), array( $cat, ezh_url( 'testimonials' ) . '#' . $t[4] ), $t[0] ), true );
	$sec[] = ezh_section(
		'ezt-int',
		'<div class="ezt-int__grid"><aside class="ezt-int__who"><span class="ezt-int__img">' . $avatar( $slug, $t[0] ) . '</span><b>' . esc_html( $full_name( $t ) ) . '</b><span>' . esc_html( $t[2] ) . '</span><em>' . wp_kses( $t[3], array() ) . '</em><span class="ezt-tag">' . esc_html( $cat ) . '</span>' . $li . '</aside>'
		. '<div class="ezt-int__body"><h2 class="ezh-h2">A short <span class="ez37-hl">interview</span></h2>' . $yt . ( $real ? '' : '<p class="ezt-draft">Draft layout: the interview questions and answers for this leader are still to be supplied.</p>' ) . $items . '</div></div>'
	);
	$sec[] = ezh_section( 'ezt-pnav', '<div class="ezt-wrap"><div class="ezt-pnav__row">' . $nav . '</div><p class="ezt-back"><a class="ez37-btn-link" href="' . esc_url( ezh_url( 'testimonials' ) ) . '"><span>Back to all testimonials</span>' . ezh_i( 'arrow' ) . '</a></p></div>' );
	ezh_save_page( $page->ID, $sec, $css );
	++$built;
}

WP_CLI::success( sprintf( 'Testimonials: list page and %d interview pages built.', $built ) );
