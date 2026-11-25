<?php
/**
 * EZ37 redesign — shared helpers for the LOCAL page-building scripts.
 *
 * Required by bin/home-local.php, bin/about-local.php and bin/components-local.php.
 * LOCAL DEVELOPMENT ONLY. Provides: icon set, section/chip/heading builders,
 * Media Library importers, the logo marquee, reusable SECTION PATTERNS
 * (ezh_section_problem, ezh_section_process) and ezh_save_page().
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit( "Run this with WP-CLI eval-file.\n" );
}

function ezh_url( string $slug ): string {
	$p = get_page_by_path( $slug . '-v2', OBJECT, 'page' );
	return $p ? get_permalink( $p ) : '#';
}

/** The shared icon set: name => inner SVG markup (24x24 grid, stroke icons). */
function ezh_icon_paths(): array {
	return array(
		'arrow'    => '<path d="M5 12h14M13 6l6 6-6 6"/>',
		'arrow-down' => '<path d="M12 5v14M6 13l6 6 6-6"/>',
		'users'    => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
		'user'     => '<circle cx="12" cy="8" r="4"/><path d="M4 21v-1a6 6 0 0 1 6-6h4a6 6 0 0 1 6 6v1"/>',
		'target'   => '<circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/>',
		'compass'  => '<circle cx="12" cy="12" r="10"/><path d="m16.2 7.8-2.1 6.3-6.3 2.1 2.1-6.3z"/>',
		'alert'    => '<path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/><path d="M12 9v4M12 17h.01"/>',
		'trend'    => '<path d="m3 17 6-6 4 4 8-8"/><path d="M14 7h7v7"/>',
		'layers'   => '<path d="m12 2 10 5-10 5L2 7z"/><path d="m2 17 10 5 10-5M2 12l10 5 10-5"/>',
		'clip'     => '<rect x="8" y="2" width="8" height="4" rx="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2M9 12h6M9 16h4"/>',
		'book'     => '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20V3H6.5A2.5 2.5 0 0 0 4 5.5z"/><path d="M4 19.5V21h16"/>',
		'file'     => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M9 13h6M9 17h6"/>',
		'download' => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"/>',
		'play'     => '<path d="M6 4v16l14-8z" fill="currentColor"/>',
		'check'         => '<path d="M20 6 9 17l-5-5"/>',
		'x'             => '<path d="M18 6 6 18M6 6l12 12"/>',
		'plus'          => '<path d="M12 5v14M5 12h14"/>',
		'minus'         => '<path d="M5 12h14"/>',
		'mail'          => '<rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-10 6L2 7"/>',
		'phone'         => '<path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 1.9.7 2.8a2 2 0 0 1-.5 2.1L8.1 9.9a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.8.7a2 2 0 0 1 1.7 2z"/>',
		'map-pin'       => '<path d="M20 10c0 6-8 12-8 12S4 16 4 10a8 8 0 0 1 16 0z"/><circle cx="12" cy="10" r="3"/>',
		'calendar'      => '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>',
		'clock'         => '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>',
		'search'        => '<circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>',
		'chevron-down'  => '<path d="m6 9 6 6 6-6"/>',
		'chevron-right' => '<path d="m9 18 6-6-6-6"/>',
		'arrow-left'    => '<path d="M19 12H5M11 6l-6 6 6 6"/>',
		'external'      => '<path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6M15 3h6v6M10 14 21 3"/>',
		'upload'        => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M17 8l-5-5-5 5M12 3v12"/>',
		'star'          => '<path d="m12 2 3.1 6.3 6.9 1-5 4.9 1.2 6.8L12 17.8 5.8 21l1.2-6.8-5-4.9 6.9-1z"/>',
		'shield'        => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/>',
		'award'         => '<circle cx="12" cy="8" r="6"/><path d="M15.5 13.5 17 22l-5-3-5 3 1.5-8.5"/>',
		'globe'         => '<circle cx="12" cy="12" r="10"/><path d="M2 12h20M12 2a15 15 0 0 1 4 10 15 15 0 0 1-4 10 15 15 0 0 1-4-10 15 15 0 0 1 4-10z"/>',
		'lock'          => '<rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
		'info'          => '<circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/>',
		'check-circle'  => '<circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/>',
		'heart'         => '<path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1-1.1a5.5 5.5 0 0 0-7.8 7.8l1 1.1L12 21l7.8-7.5 1-1.1a5.5 5.5 0 0 0 0-7.8z"/>',
		'briefcase'     => '<rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/>',
		'message'       => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',
		'lightbulb'     => '<path d="M9 18h6M10 22h4M12 2a7 7 0 0 0-4 12.7c.6.5 1 1.3 1 2.1V18h6v-1.2c0-.8.4-1.6 1-2.1A7 7 0 0 0 12 2z"/>',
		'menu'          => '<path d="M3 6h18M3 12h18M3 18h18"/>',
	);
}

/** One icon as an inline SVG (inherits currentColor). */
function ezh_i( string $name ): string {
	$paths = ezh_icon_paths();
	$d     = $paths[ $name ] ?? '';
	return '<span class="ezh-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $d . '</svg></span>';
}

function ezh_chip( string $icon, string $tone = '' ): string {
	return '<span class="ezh-chipx ' . esc_attr( $tone ) . '">' . ezh_i( $icon ) . '</span>';
}

/** Single source for the "Book a Discovery Call" booking link (Calendly). */
function ezh_calendly_url(): string {
	return 'https://calendly.com/patricia-akinlotan/30-minutes-contact-meeting';
}

function ezh_head( string $badge, string $h2, string $lead = '' ): string {
	return '<div class="ezh-sec-head ezh-center"><div class="ezh-badge-row"><span class="ez37-badge" style="font-family:Poppins,sans-serif;font-weight:500;font-size:12px;letter-spacing:.08em;text-transform:uppercase;color:var(--ez37-indigo);border:1px solid var(--ez37-indigo);border-radius:999px;padding:6px 14px">' . esc_html( $badge ) . '</span></div>'
		. '<h2 class="ezh-h2">' . $h2 . '</h2>'
		. ( $lead ? '<p class="ezh-lead">' . $lead . '</p>' : '' ) . '</div>';
}

/** One section = a zero-padding container holding one HTML widget. */
function ezh_section( string $class, string $html ): array {
	static $n = 0;
	++$n;
	$zero = array( 'unit' => 'px', 'top' => '0', 'right' => '0', 'bottom' => '0', 'left' => '0', 'isLinked' => true );
	return array(
		'id'       => substr( md5( 'ezh-sec-' . $n ), 0, 7 ),
		'elType'   => 'container',
		'isInner'  => false,
		'settings' => array(
			'content_width'  => 'full',
			'flex_direction' => 'column',
			'css_classes'    => 'ezh-sec ' . $class,
			'padding'        => $zero,
			'margin'         => $zero,
			'flex_gap'       => array( 'column' => '0', 'row' => '0', 'isLinked' => true, 'unit' => 'px' ),
		),
		'elements' => array(
			array(
				'id'         => substr( md5( 'ezh-w-' . $n ), 0, 7 ),
				'elType'     => 'widget',
				'widgetType' => 'html',
				'settings'   => array( 'html' => $html ),
				'elements'   => array(),
			),
		),
	);
}

function ezh_logos( array $names ): string {
	$h = '<div class="ezh-logos__row">';
	foreach ( $names as $n ) {
		$h .= '<span class="ezh-logo"><i></i>' . esc_html( $n ) . '</span>';
	}
	return $h . '</div>';
}

/** Import design/<sub>/*.{jpg,png} into the Media Library once (tagged by file name) and return slug => URL. */
function ezh_media_dir( string $sub, string $meta ): array {
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';

	$files = array_merge( glob( dirname( __DIR__, 2 ) . '/design/' . $sub . '/*.jpg' ) ?: array(), glob( dirname( __DIR__, 2 ) . '/design/' . $sub . '/*.png' ) ?: array() );
	$urls  = array();
	if ( ! $files ) {
		WP_CLI::warning( "No images found in design/$sub." );
		return $urls;
	}
	sort( $files );
	foreach ( $files as $file ) {
		$base = basename( $file );
		$ids  = get_posts(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'meta_key'       => $meta,
				'meta_value'     => $base,
				'fields'         => 'ids',
				'posts_per_page' => 1,
			)
		);
		if ( $ids ) {
			$att = (int) $ids[0];
		} else {
			$tmp = wp_tempnam( $base );
			copy( $file, $tmp );
			$att = media_handle_sideload( array( 'name' => $base, 'tmp_name' => $tmp ), 0, ucwords( str_replace( '-', ' ', pathinfo( $base, PATHINFO_FILENAME ) ) ) );
			if ( is_wp_error( $att ) ) {
				WP_CLI::warning( $base . ': ' . $att->get_error_message() );
				continue;
			}
			update_post_meta( $att, $meta, $base );
		}
		$url = wp_get_attachment_url( $att );
		if ( $url ) {
			$urls[ pathinfo( $base, PATHINFO_FILENAME ) ] = $url;
		}
	}
	return $urls;
}

/** Import design/logos/*.png into the Media Library once (tagged by file name) and return name => URL. */
function ezh_logo_urls(): array {
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';

	$dir   = dirname( __DIR__, 2 ) . '/design/logos';
	$files = glob( $dir . '/*.png' );
	$urls  = array();
	if ( ! $files ) {
		WP_CLI::warning( 'No logos found in design/logos; the logo strip will be empty.' );
		return $urls;
	}
	sort( $files );
	foreach ( $files as $file ) {
		$base = basename( $file );
		$ids  = get_posts(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'meta_key'       => '_ez37_logo',
				'meta_value'     => $base,
				'fields'         => 'ids',
				'posts_per_page' => 1,
			)
		);
		if ( $ids ) {
			$att = (int) $ids[0];
		} else {
			$tmp = wp_tempnam( $base );
			copy( $file, $tmp );
			$att = media_handle_sideload(
				array( 'name' => $base, 'tmp_name' => $tmp ),
				0,
				'Client logo: ' . str_replace( '_', ' ', basename( $base, '.png' ) )
			);
			if ( is_wp_error( $att ) ) {
				WP_CLI::warning( $base . ': ' . $att->get_error_message() );
				continue;
			}
			update_post_meta( $att, '_ez37_logo', $base );
		}
		$url = wp_get_attachment_url( $att );
		if ( $url ) {
			$urls[ basename( $base, '.png' ) ] = $url;
		}
	}
	return $urls;
}

function ezh_logo_alt( string $key ): string {
	if ( 0 === strpos( $key, 'Public_Sector' ) ) {
		return 'Public sector client';
	}
	return 1 === strlen( $key ) ? 'Client logo' : str_replace( '_', ' ', $key );
}

function ezh_logo_img( string $key, string $url ): string {
	return '<img class="ezh-logo-img" src="' . esc_url( $url ) . '" alt="' . esc_attr( ezh_logo_alt( $key ) ) . '" loading="lazy" decoding="async">';
}

/** Endless, seamless slideshow: the set is rendered twice and the track slides by exactly one set. */
function ezh_marquee( array $urls ): string {
	if ( ! $urls ) {
		return '';
	}
	$keys = array_keys( $urls );
	mt_srand( 37 );
	shuffle( $keys );
	$set = '';
	foreach ( $keys as $k ) {
		$set .= ezh_logo_img( $k, $urls[ $k ] );
	}
	$dupe = '';
	foreach ( $keys as $k ) {
		$dupe .= '<img class="ezh-logo-img" src="' . esc_url( $urls[ $k ] ) . '" alt="" aria-hidden="true" loading="lazy" decoding="async">';
	}
	return '<div class="ezh-marquee" role="group" aria-label="Organisations we have worked with"><div class="ezh-marquee__track"><div class="ezh-marquee__set">' . $set . '</div><div class="ezh-marquee__set" aria-hidden="true">' . $dupe . '</div></div></div>';
}

function ezh_logo_row( array $urls, array $keys ): string {
	$h = '<div class="ezh-logos__row ezh-logos__row--img">';
	foreach ( $keys as $k ) {
		if ( isset( $urls[ $k ] ) ) {
			$h .= ezh_logo_img( $k, $urls[ $k ] );
		}
	}
	return $h . '</div>';
}

/* ======================== reusable SECTION PATTERNS ======================== */

/**
 * SECTION PATTERN: "The challenge" (3 problem cards + call-out). Hidden on the Home page for now;
 * call this from any page script that needs it.
 */
function ezh_section_problem( string $contact ): array {
	$prob = array(
		array( 'alert', 'is-coral', 'Strategy Stalls Between The Boardroom And The Team', 'Plans are clear at the top but get diluted by the time they reach the people who must deliver them.' ),
		array( 'user', '', 'Capable People Are Promoted Without Support', 'Your best performers become managers overnight, with little help to lead, coach and give feedback.' ),
		array( 'trend', 'is-amber', 'Growth Outpaces The Systems Behind It', 'As the organisation scales, the habits and structures that once worked start to hold it back.' ),
	);
	$ph = '';
	foreach ( $prob as $p ) {
		$ph .= '<div class="ezh-pcard">' . ezh_chip( $p[0], $p[1] ) . '<h3>' . $p[2] . '</h3><p>' . $p[3] . '</p></div>';
	}
	return ezh_section(
		'ezh-problem',
		'<div class="ezh-problem__inner"><div class="ezh-wrap">' . ezh_head( 'The challenge', 'Great Plans Are Easy. <span class="ez37-hl">Delivering Them Is Hard.</span>', 'Placeholder copy: where ambition meets reality, the gap is almost always about people, not product.' )
		. '<div class="ezh-cards3">' . $ph . '</div>
		<div class="ezh-cta-card"><p>If you have tried training that ticked a box but changed nothing, you are in the right place. We build something different.</p><a class="ez37-btn" href="' . esc_url( ezh_calendly_url() ) . '" target="_blank" rel="noopener">Book a Consultation</a></div></div></div>'
	);
}

/**
 * SECTION PATTERN: "How we work" (3-step timeline + 48-hour plan). Moved from Home to the About page.
 */
function ezh_section_process( string $contact = '' ): array {
	// Four-step horizontal row (numbered circles on a line). The photo version (design/process/step-1..3.jpg, zigzag timeline)
	// was removed at the client's request; see git history if it is ever wanted back.
	$steps = array(
		array( 'Discover', 'Understand your context, goals and people.' ),
		array( 'Design', 'Co-create a bespoke solution with your stakeholders.' ),
		array( 'Deliver', 'Accredited coaches and facilitators deliver.' ),
		array( 'Review', 'Track progress against agreed measures and refine.' ),
	);
	$sh = '';
	foreach ( $steps as $i => $st ) {
		$num = sprintf( '%02d', $i + 1 );
		$sh .= '<div class="ezh-step"><div class="ezh-step__dot">' . $num . '</div><div class="ezh-step__text"><small>Step ' . $num . '</small><h3>' . esc_html( $st[0] ) . '</h3><p>' . esc_html( $st[1] ) . '</p></div></div>';
	}
	return ezh_section(
		'ezh-process',
		'<div class="ezh-wrap"><div class="ezh-sec-head ezh-center"><span class="ezs-eyebrow">How We Partner With You</span><h2 class="ezh-h2">From First Call To <span class="ez37-hl">Real Change</span></h2><p class="ezh-lead ezh-process__lead">Built around your outcomes, from first conversation to results.</p></div>'
		. '<div class="ezh-tl">' . $sh . '</div>'
		. ( $contact ? '<div class="ezh-cta-center ezh-process__cta"><a class="ez37-btn" href="' . esc_url( $contact ) . '">Get Started ' . ezh_i( 'arrow' ) . '</a></div>' : '' )
		. '</div>'
	);
}

/** Original 'What to expect' card (kept as a reusable pattern; About now uses its own content). */
function ezh_section_plan(): array {
	return ezh_section(
	'ezh-process',
	'<div class="ezh-wrap">
	<div class="ezh-plan"><div class="ezh-plan__head"><div><small>What to expect</small><h3>From first call to custom plan in 48 hours.</h3></div><div class="ezh-plan__badge"><b>48hrs</b><span>To your custom<br>recommendation</span></div></div>
	<div class="ezh-plan__steps">
	<div class="ezh-plan__step"><em>01</em><b>Book a consultation</b><span>No prep needed. Just show up for 30 minutes.</span></div>
	<div class="ezh-plan__step"><em>02</em><b>We ask, you talk</b><span>We listen first, then share what we are seeing.</span></div>
	<div class="ezh-plan__step"><em>03</em><b>Custom recommendation</b><span>A specific plan for your goals within 48 hours.</span></div>
	<div class="ezh-plan__step"><em>04</em><b>You decide</b><span>No follow-up spam. A clear path if it fits.</span></div></div>
	<div class="ezh-pills"><span>No prep needed</span><span>No follow-up spam</span><span>Custom plan in 48 hours</span></div></div></div>'
	);
}

/** 'The Six Levels' pyramid (hidden on Home; reusable on any page). $about = link for the button. */
function ezh_section_framework( string $about ): array {
	return ezh_section(
		'ezh-fw',
		'<div class="ezh-wrap"><div class="ezh-fw__grid">
		<div><span class="ezh-eyebrow">Our methodology</span>
			<h2 class="ezh-h2"><span class="ez37-hl">The Six Levels:</span> A Diagnostic And Implementation Roadmap</h2>
			<p class="ezh-lead">Placeholder copy. A sequential framework: build the foundation before the next layer. Each level shows where a team is today and exactly what to work on next.</p>
			<a class="ez37-btn" href="' . esc_url( $about ) . '">See The Full Framework ' . ezh_i( 'arrow' ) . '</a></div>
		<div><svg viewBox="0 0 520 440" role="img" aria-label="Six-level framework pyramid"><g stroke="#fff" stroke-width="3" stroke-linejoin="round">
			<polygon points="260,20 310,100 210,100" fill="#1e1b6b"/>
			<polygon points="210,100 310,100 350,165 170,165" fill="#4338ca"/>
			<polygon points="170,165 350,165 390,230 130,230" fill="#d63424"/>
			<polygon points="130,230 390,230 430,295 90,295" fill="#f5a524"/>
			<polygon points="90,295 430,295 470,360 50,360" fill="#0e9f8e"/>
			<polygon points="50,360 470,360 500,420 20,420" fill="#14123f"/></g>
			<g font-family="Poppins,sans-serif" font-size="14" font-weight="600" fill="#fff" text-anchor="middle"><text x="260" y="85">6</text><text x="260" y="140">5</text><text x="260" y="205">4</text><text x="260" y="270">3</text><text x="260" y="335">2</text><text x="260" y="396">1</text></g></svg></div>
		</div></div>'
	);
}

/** 'Resources for organisations' cards (hidden on Home; reusable on any page). */
function ezh_section_resources( string $blog, string $cases, string $contact ): array {
	return ezh_section(
		'ezh-res-band',
		'<div class="ezh-wrap">' . '<div class="ezh-sec-head ezh-center"><span class="ezs-eyebrow">Learn With Us</span><h2 class="ezh-h2">Resources For Organisations That <span class="ez37-hl">Refuse</span> To Stand Still</h2></div>'
		. '<div class="ezh-cards3">
		<div class="ezh-rcard"><small>' . ezh_i( 'file' ) . 'Blog post</small><h3>Placeholder: Why Strategy Fails At Execution</h3><p>A short read on the most common reasons plans stall between leadership and teams.</p><a href="' . esc_url( $blog ) . '">Read more &rarr;</a></div>
		<div class="ezh-rcard"><small>' . ezh_i( 'file' ) . 'Blog post</small><h3>Placeholder: Building A Coaching Culture</h3><p>How organisations move from one-off training to everyday coaching conversations.</p><a href="' . esc_url( $blog ) . '">Read more &rarr;</a></div>
		<div class="ezh-rcard is-solid"><small>' . ezh_i( 'file' ) . 'Blog post</small><h3>Placeholder: Leadership Readiness Checklist</h3><p>A practical read on spotting where your team needs support first.</p><a href="' . esc_url( $blog ) . '">Read more &rarr;</a></div></div>
		<div class="ezh-cta-center"><a class="ez37-btn" href="' . esc_url( $contact ) . '">Get Started ' . ezh_i( 'arrow' ) . '</a></div></div>'
	);
}

/**
 * Case study card (styles: design/ez37-home.css "8. Case study").
 * Photo header with tag, title and summary; left column of Challenge / Solution items with a link;
 * right column with four outcome tiles. Content: global FMCG organisation case study.
 */
function ezh_section_case( string $img_url, string $link, string $contact = '' ): array {
	$items = array(
		array( 'target', 'Challenge', 'To enhance team synergy and cohesion within their Human Resources department to drive improved performance. To cultivate a mindset shift, deeper engagement, self-reflection, and adaptability among team members.' ),
		array( 'trend', 'Solution', 'We designed a seven-month coaching intervention tailored to the team&rsquo;s specific needs to achieve this. We began with an assessment to pinpoint areas for growth, which guided our coaching sessions focused on team mindset, psychological safety, and high performance. Following each session, participants engaged in reflective activities in smaller groups to deepen their understanding and apply what they had learned.' ),
	);
	$li = '';
	foreach ( $items as $it ) {
		$li .= '<div class="ezh-case__item"><div><h5>' . esc_html( $it[1] ) . '</h5><p>' . wp_kses( $it[2], array() ) . '</p></div></div>';
	}
	$res = array(
		array( '42', 'Team cohesion' ),
		array( '38', 'Emotional intelligence' ),
		array( '35', 'Empathetic communication' ),
		array( '40', 'Effective leadership' ),
	);
	$rh = '';
	foreach ( $res as $r ) {
		$rh .= '<div><b><i>+</i>' . esc_html( $r[0] ) . '<em>%</em></b><span>' . esc_html( $r[1] ) . '</span></div>';
	}
	$bg = $img_url ? '<img class="ezh-case__bg" src="' . esc_url( $img_url ) . '" alt="" loading="lazy">' : '';
	return ezh_section(
		'ezh-case',
		'<div class="ezh-wrap"><div class="ezh-sec-head ezh-center"><span class="ezs-eyebrow">Case study</span><h2 class="ezh-h2">Leadership Change <span class="ez37-hl">That Lasts</span></h2></div>'
		. '<div class="ezh-case__card"><div class="ezh-case__img">' . $bg . '<span class="ezh-tag">Case study</span>'
		. '<h3>A global FMCG Organisation</h3><p>Building team synergy and high performance in an HR function through a seven-month coaching intervention</p></div>'
		. '<div class="ezh-case__body"><div class="ezh-case__text">' . $li
		. '<a class="lnk" href="' . esc_url( $link ) . '">Read the Full Story ' . ezh_i( 'arrow' ) . '</a></div>'
		. '<div class="ezh-case__res"><div class="ezh-case__resin"><h5>Transformational outcome</h5><div class="ezh-res">' . $rh . '</div></div></div></div></div>'
		. '</div>' // the Get Started button now lives under How We Partner With You (ezh_section_process)
	);
}

/**
 * "Where do you want to start?" three-path chooser + discovery-call strip (Solutions page, right after the banner).
 * Styles: design/ez37-solutions.css
 */
function ezh_section_start( string $leadership, string $hr, string $contact ): array {
	$cards = array(
		array( 'compass', 'I lead an organisation.', 'Executives and owners: build leadership capacity that keeps pace with growth.', 'Explore leadership coaching', $leadership, '' ),
		array( 'users', 'I lead our people.', 'HR and L&amp;D leaders: programmes and advisory your managers will actually use.', 'See programmes for HR', $hr, 'is-coral' ),
		array( 'target', 'I am not sure yet.', 'Talk to us. We will help you find the right starting point for your goals.', 'Book a consultation', ezh_calendly_url(), '' ),
	);
	$h = '';
	foreach ( $cards as $c ) {
		$h .= '<a class="ezsol-start__card ' . $c[5] . '" href="' . esc_url( $c[4] ) . '"' . ( ezh_calendly_url() === $c[4] ? ' target="_blank" rel="noopener"' : '' ) . '>'
			. '<h3>' . esc_html( $c[1] ) . '</h3><p>' . wp_kses( $c[2], array() ) . '</p>'
			. '<span class="ezsol-start__link"><span>' . esc_html( $c[3] ) . '</span><i>' . ezh_i( 'arrow' ) . '</i></span></a>';
	}
	return ezh_section(
		'ezsol-start',
		'<div class="ezh-wrap"><span class="ezsol-eyebrow">Where do you want to start?</span>'
		. '<div class="ezsol-start__box"><div class="ezsol-start__grid">' . $h . '</div>'
		. '<div class="ezsol-start__foot"><p><b>Know what you need?</b> A short call. No pressure.</p>'
		. '<a class="ez37-btn-outline" href="' . esc_url( ezh_calendly_url() ) . '" target="_blank" rel="noopener">Book a Discovery Call</a></div></div></div>'
	);
}


/**
 * Two-programme tab chooser for Home (Enrol Now goes to each programme page).
 * $progs: array of array( tab label, tab sub-label, title, paragraph, ticks[], image url, enrol url ).
 * Styles: design/ez37-simple.css "Programme tabs".
 */
function ezh_section_programmes( array $progs ): array {
	$tabs   = '';
	$panels = '';
	foreach ( $progs as $k => $p ) {
		$on    = 0 === $k;
		$tabs .= '<button type="button" class="ezpt__tab' . ( $on ? ' is-on' : '' ) . '" role="tab" aria-selected="' . ( $on ? 'true' : 'false' ) . '" data-ezpt="' . (int) $k . '"><b>' . esc_html( $p[0] ) . '</b><i>' . nl2br( wp_kses( $p[1], array() ) ) . '</i></button>';
		$li    = '';
		foreach ( $p[4] as $t ) {
			$li .= '<li>' . esc_html( $t ) . '</li>';
		}
		$panels .= '<div class="ezpt__panel' . ( $on ? ' is-on' : '' ) . '" role="tabpanel" data-ezpt="' . (int) $k . '"' . ( $on ? '' : ' hidden' ) . '>'
			. '<div class="ezpt__img">' . ( $p[5] ? '<img src="' . esc_url( $p[5] ) . '" alt="" loading="lazy">' : '' ) . '</div>'
			. '<div class="ezpt__text"><h3>' . esc_html( $p[2] ) . '</h3><p>' . wp_kses( $p[3], array( 'p' => array() ) ) . '</p>' . ( $li ? '<ul>' . $li . '</ul>' : '' )
			. '<a class="ez37-btn" href="' . esc_url( $p[6] ) . '">Explore Program ' . ezh_i( 'arrow' ) . '</a></div></div>';
	}
	$js = '(function(){var r=document.getElementById("ezpt");if(!r)return;var t=r.querySelectorAll(".ezpt__tab"),p=r.querySelectorAll(".ezpt__panel");'
		. 't.forEach(function(b){b.addEventListener("click",function(){var k=b.getAttribute("data-ezpt");'
		. 't.forEach(function(x){var on=x===b;x.classList.toggle("is-on",on);x.setAttribute("aria-selected",on?"true":"false");});if(b.scrollIntoView){b.parentNode.scrollTo({left:b.offsetLeft-(b.parentNode.clientWidth-b.offsetWidth)/2,behavior:"smooth"});}'
		. 'p.forEach(function(x){var on=x.getAttribute("data-ezpt")===k;x.classList.toggle("is-on",on);x.hidden=!on;});});});})();';
	return ezh_section(
		'ezpt-sec',
		'<div class="ezh-wrap"><div class="ezh-sec-head ezh-center"><span class="ezs-eyebrow">Your Pathway</span><h2 class="ezh-h2">Our <span class="ez37-hl">Coaching Courses</span> &amp; Programs</h2>'
		. '</div>'
		. '<div class="ezpt" id="ezpt"><div class="ezpt__tabs" role="tablist">' . $tabs . '</div><div class="ezpt__panels">' . $panels . '</div><script>' . $js . '</script></div></div>'
	);
}

/** FAQ accordion (production questions and answers by default). */
function ezh_section_faq( array $faqs = array() ): array {
	if ( ! $faqs ) {
		$faqs = array(
			array( 'What are the key roles of human resources?', 'Human resources is responsible for managing talent, fostering employee development, ensuring compliance with labour laws, and implementing organisational policies. HR also handles recruitment, training, performance management, and employee relations to align the workforce with business objectives.' ),
			array( 'How can I get started with EZ37 Solutions?', 'Starting with EZ37 Solutions is simple! Reach out to us via our contact page, schedule a consultation, and we&rsquo;ll tailor a solution that fits your organisational needs. Our team will guide you through every step of the process to ensure a seamless and impactful partnership.' ),
			array( 'What are the benefits of choosing our services?', 'Choosing EZ37 Solutions means access to expert guidance, cutting-edge HR and business strategies, and customised solutions that drive performance. Our services help you optimize workforce management, enhance leadership skills, and create sustainable business growth, leading to improved overall productivity and success.' ),
			array( 'How can I contact your expert staff?', 'To connect with our expert staff, simply visit our contact page and fill out the inquiry form. Alternatively, you can reach us directly via email or phone, and our team will get back to you promptly to address your needs and provide support.' ),
		);
	}
	$fh = '';
	foreach ( $faqs as $i => $f ) {
		// Answers may contain entities (e.g. &rsquo;); questions are plain text.
		$fh .= '<details name="ezh-faq"' . ( 0 === $i ? ' open' : '' ) . '><summary>' . esc_html( $f[0] ) . '</summary><p>' . wp_kses( $f[1], array() ) . '</p></details>';
	}
	return ezh_section( 'ezh-faq', '<div class="ezh-faq__inner"><div class="ezh-wrap">' . '<div class="ezh-sec-head ezh-center"><h2 class="ezh-h2">Frequently Asked <span class="ez37-hl">Questions</span></h2></div>' . '<div class="ezh-acc">' . $fh . '</div></div></div>' );
}

/* ============================== page writer ============================== */

/**
 * Write a list of sections into a private page: root container (.ez37), Elementor meta,
 * and the concatenated design-system CSS as the page's custom CSS.
 */
/**
 * Dev-only flat/rounded top-nav switch. Returns the hidden flat-nav marker plus the switch script.
 * Remove the script part before go-live (keep the marker if the flat nav is chosen).
 */
function ezh_nav_switch( bool $with_button = true ): string {
	$js = '(function(){var m=document.querySelector(".ezs-navflat");if(!m){return;}var par=m.parentNode,K="ezNav",q=new URLSearchParams(location.search).get("nav"),cur=q||(function(){try{return sessionStorage.getItem(K);}catch(e){return null;}})()||"flat";'
		. 'var b=document.createElement("button");b.type="button";b.className="ezs-navswitch";document.body.appendChild(b);'
		. 'function apply(v){cur=v;if(v==="flat"){if(!m.isConnected){par.insertBefore(m,par.firstChild);}}else{m.remove();}b.textContent="Nav: "+(v==="flat"?"Flat":"Rounded")+" (tap to switch)";try{sessionStorage.setItem(K,v);}catch(e){}}'
		. 'b.addEventListener("click",function(){apply(cur==="flat"?"rounded":"flat");});apply(cur);})();';
	return '<span class="ezs-navflat" hidden></span>' . ( $with_button ? '<script>' . $js . '</script>' : '' );
}

/**
 * Page banner: photo background under a dark indigo overlay, large left-aligned page title with a small breadcrumb.
 * $buttons / $img_url / $alt are kept for compatibility but no longer rendered.
 */
function ezh_banner_hero( string $title, string $lead, array $buttons, string $img_url, string $alt = '', $crumb = '', bool $show_title = true ): array {
	$crumb = $crumb ? $crumb : $title;
	$home_v2 = get_page_by_path( 'home-v2', OBJECT, 'page' );
	$trail   = '<a href="' . esc_url( $home_v2 ? get_permalink( $home_v2 ) : home_url( '/' ) ) . '">Home</a>';
	foreach ( (array) $crumb as $i => $c ) {
		$last   = ( count( (array) $crumb ) - 1 === $i );
		$trail .= '<span aria-hidden="true">&rsaquo;</span>' . ( $last || ! is_array( $c ) ? '<span aria-current="page">' . esc_html( is_array( $c ) ? $c[0] : $c ) . '</span>' : '<a href="' . esc_url( $c[1] ) . '">' . esc_html( $c[0] ) . '</a>' );
	}
	return ezh_section(
		'ezs-bhero',
		'<div class="ezs-bhero__stage"' . ( $img_url ? ' style="--bh-img:url(\'' . esc_url( $img_url ) . '\')"' : '' ) . '>' . ezh_nav_switch() . '
		<div class="ezs-bhero__copy">
		<nav class="ezs-bhero__crumbs" aria-label="Breadcrumb">' . $trail . '</nav>
		<h1>' . esc_html( $title ) . '</h1>'
		. ( $lead ? '<p>' . esc_html( $lead ) . '</p>' : '' )
		. '</div></div>'
	);
}

function ezh_save_page( int $id, array $sections, array $css_files ): void {
	$root = array(
		'id'       => 'ezhroot',
		'elType'   => 'container',
		'isInner'  => false,
		'settings' => array(
			'content_width'  => 'full',
			'flex_direction' => 'column',
			'css_classes'    => 'ez37',
			'padding'        => array( 'unit' => 'px', 'top' => '0', 'right' => '0', 'bottom' => '0', 'left' => '0', 'isLinked' => true ),
			'flex_gap'       => array( 'column' => '0', 'row' => '0', 'isLinked' => true, 'unit' => 'px' ),
		),
		'elements' => $sections,
	);

	$css = '';
	foreach ( $css_files as $f ) {
		$path = dirname( __DIR__, 2 ) . '/design/' . $f;
		if ( file_exists( $path ) ) {
			$css .= file_get_contents( $path ) . "\n";
		} else {
			WP_CLI::warning( "design/$f not found; the page will be partly unstyled." );
		}
	}

	$settings = get_post_meta( $id, '_elementor_page_settings', true );
	$settings = is_array( $settings ) ? $settings : array();
	$settings['custom_css'] = $css;

	update_post_meta( $id, '_wp_page_template', 'elementor_header_footer' );
	update_post_meta( $id, '_elementor_edit_mode', 'builder' );
	update_post_meta( $id, '_elementor_template_type', 'wp-page' );
	update_post_meta( $id, '_elementor_version', defined( 'ELEMENTOR_VERSION' ) ? ELEMENTOR_VERSION : '3.0.0' );
	update_post_meta( $id, '_elementor_data', wp_slash( wp_json_encode( array( $root ) ) ) );
	// wp_slash so backslashes in the CSS survive update_post_meta's unslashing.
	update_post_meta( $id, '_elementor_page_settings', wp_slash( $settings ) );

	\Elementor\Plugin::$instance->files_manager->clear_cache();
}

/**
 * SECTION PATTERN: "Our Impact" (stat band, credential cards, recognised-by line, CEO call strip).
 * Figures come from the EZ37 brochure; confirm before go-live.
 */
function ezh_section_impact(): array {
	$stats = array(
		array( 'award', '165', 'Coaches certified' ),
		array( 'users', '500+', 'Leaders coached' ),
		array( 'clock', '1,000+', 'Coaching hours delivered' ),
		array( 'book', '700+', 'Employees trained' ),
		array( 'shield', '45+', 'Clients retained' ),
	);
	$sh = '';
	foreach ( $stats as $t ) {
		$sh .= '<div class="ezim__stat">' . ezh_chip( $t[0] ) . '<b>' . esc_html( $t[1] ) . '</b><span>' . esc_html( $t[2] ) . '</span></div>';
	}
	$creds = array(
		array( '2009', 'Established', "EZ37 Solutions Limited\n(RC 833803)" ),
		array( 'ISO', '9001:2015', 'Certified quality management' ),
		array( 'ICF', 'Accredited', "Coach education provider,\nLevel 1 and 2" ),
		array( 'IFC-LPI', 'Partner', 'Certified trainers and facilitators' ),
	);
	$ch = '';
	foreach ( $creds as $c ) {
		$ch .= '<div class="ezim__cred"><b>' . esc_html( $c[0] ) . '</b><strong>' . esc_html( $c[1] ) . '</strong><span>' . nl2br( esc_html( $c[2] ) ) . '</span></div>';
	}
	return ezh_section(
		'ezim',
		'<div class="ezim__inner">
	<h2 class="ezim__h">Our Impact</h2>
	<div class="ezim__stats">' . $sh . '</div>
	<div class="ezim__creds">' . $ch . '</div>
	<span class="ezs-eyebrow ezim__also">Also recognised by</span>
	<p class="ezim__rec">Chartered Institute of Bankers of Nigeria (CIBN) <i>&middot;</i> Signatory to the GLC Principles of Learning</p>
	<div class="ezim__cta">' . ezh_i( 'mail' ) . '<p><b>Find out how we can partner with you.</b> Schedule a call with our CEO, Adaora Ayoade: <a href="mailto:adaora@ez37solutions.org">adaora@ez37solutions.org</a></p></div>
	</div>'
	);
}
