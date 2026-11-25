<?php
/**
 * EZ37 redesign — header, footer and navigation (LOCAL DEVELOPMENT ONLY).
 *
 * Creates, all private and scoped so the rest of the site is untouched:
 *  - placeholder pages (home-v2, about-v2, solutions-v2 ... ) as PRIVATE pages
 *  - WordPress menus: New Design Main Menu, plus three footer menus (no theme location)
 *  - Theme Builder header (announcement bar, logo, Mega Menu widget, button)
 *  - Theme Builder footer (brand, three menu columns, contact, legal bar)
 *  - Display conditions: only the new pages (never the whole site)
 *
 * Needs bin/redesign-local.php to have run first (it creates the "New - ..." tokens).
 * Safe to re-run: pages, menus and templates are updated in place.
 *
 * Run from the project root:
 *   ddev exec wp --path=/var/www/html/wordpress eval-file /var/www/html/bin/header-footer-local.php
 *
 * Logo: put a clean (really transparent) logo at design/ez37-logo-clean.png, .svg or .webp and re-run.
 * Do NOT run this against the client's production site.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit( "Run this with WP-CLI eval-file.\n" );
}

if ( ! class_exists( '\Elementor\Plugin' ) || ! class_exists( '\ElementorPro\Plugin' ) ) {
	WP_CLI::error( 'Elementor and Elementor Pro (or Pro Elements) must be active.' );
}

if ( 'local' !== wp_get_environment_type() && false === strpos( home_url(), 'ddev.site' ) ) {
	WP_CLI::error( 'Refusing to run: this does not look like the local DDEV site.' );
}

/* ------------------------------------------------------------------- data */

// Top-level pages: slug => title.
$main = array(
	'home'          => 'Home',
	'about'         => 'About',
	'solutions'     => 'Solutions',
	'case-studies'  => 'Case Studies',
	'testimonials'  => 'Testimonials',
	'partnerships'  => 'Partnerships',
	'blog'          => 'Blog',
	'contact-us'    => 'Contact Us',
);

// Solutions mega menu, left panel.
$areas = array(
	'assessments'             => 'Assessments',
	'coaching'                => 'Coaching',
	'hr-advisory'             => 'HR Advisory',
	'public-sector-solutions' => 'Public Sector Solutions',
	'facilitation-and-learning' => 'Facilitation and Learning',
	'coach-certification'     => 'Coach Certification',
	'coaching-skills-leaders-managers' => 'Coaching Skills for Leaders and Managers',
	'story-telling'           => 'Story Telling',
	'design-thinking'         => 'Design Thinking',
);

// Until each service area has its own page, the mega-menu "Service areas" links jump to the matching band on the Solutions page.
// Anchor ids must match the ids set in bin/solutions-local.php.
$area_anchors = array(
	'coaching'                         => 'coaching',
	'coaching-skills-leaders-managers' => 'coaching-skills',
	'coach-certification'              => 'coach-certification',
	'facilitation-and-learning'        => 'facilitation',
	'story-telling'                    => 'storytelling',
	'design-thinking'                  => 'design-thinking',
	'hr-advisory'                      => 'consulting',
	'public-sector-solutions'          => 'public-sector',
	'assessments'                      => 'assessments',
);

$GLOBALS['area_anchors'] = $area_anchors;

// Solutions mega menu, right panel.
$programmes = array(
	'mcs'                          => 'Mentor Coach Specialisation Training (MCS)',
	'advanced-mastery'             => 'Advanced Mentor Coaching Mastery Program (AMCMP)',
	'pccp'                         => 'Professional Coaching Certification Programme (PCCP)',
	'accp'                         => 'Advanced Coach Certification Programme (ACCP)',
	'coaching-skills-training'     => 'Coaching Skills Training for Managers and Leaders',
	'group-team-coaching'          => 'Group/Team Coaching',
	'leadership-executive-coaching' => 'Leadership/Executive Coaching',
	'mentor-coaching'              => 'Mentor Coaching',
);

// Case Studies mega menu (menu-only parent, like Partnerships).
$case_items = array(
	'cases-one-to-one-coaching' => 'One-to-One Coaching',
	'cases-pccp'                => 'Professional Coaching Certification Programme (PCCP)',
	'cases-team-coaching'       => 'Team Coaching',
	'cases-coaching-skills'     => 'Coaching Skills Training for Managers and Leaders',
);

$partners = array(
	'industry-partnerships'    => 'Industry Partnerships',
	'operational-partnerships' => 'Operational Partnerships',
);

// Individual case study pages (built by cases-local.php); they need the header/footer too.
$case_pages = array(
	'case-team-coaching-fmcg'             => 'A global FMCG Organisation',
	'case-pccp-financial-institution'     => 'A leading Financial Institution',
	'case-one-to-one-foundation'          => 'A leading Not for Profit Organisation',
	'case-coaching-skills-multinational'  => 'Leading Multi-National Financial Institution',
	'peer-to-peer-coaching'               => 'EZ Peer to Peer Coaching',
);

// One interview page per testimonial (data in bin/data/testimonials.php).
require __DIR__ . '/data/testimonials.php';
foreach ( $ez37_testi_people as $t_slug => $t ) {
	if ( in_array( $t_slug, $ez37_testi_nopage, true ) ) {
		continue;
	}
	$case_pages[ 'testimonial-' . $t_slug ] = wp_strip_all_tags( html_entity_decode( $t[0] ) );
}

$legal = array(
	'privacy-policy' => 'Privacy Policy',
	'terms'          => 'Terms',
);

/* ------------------------------------------------------------ page helpers */

$page_ids = array(); // slug => id.

function ez37h_page( string $slug, string $title, array &$page_ids ): int {
	$full     = $slug . '-v2';
	$existing = get_page_by_path( $full, OBJECT, 'page' );
	$content  = "<!-- wp:paragraph -->\n<p>Placeholder page for the redesign. Content to be added.</p>\n<!-- /wp:paragraph -->";

	if ( $existing ) {
		$page_ids[ $slug ] = (int) $existing->ID;
		return (int) $existing->ID;
	}

	$id = wp_insert_post(
		array(
			'post_type'    => 'page',
			'post_status'  => 'private',
			'post_title'   => $title,
			'post_name'    => $full,
			'post_content' => $content,
		),
		true
	);
	if ( is_wp_error( $id ) ) {
		WP_CLI::error( $id->get_error_message() );
	}
	update_post_meta( $id, '_wp_page_template', 'elementor_header_footer' );
	$page_ids[ $slug ] = (int) $id;
	return (int) $id;
}

// Menu-only parents: they open a mega menu and have NO page of their own.
$menu_only = array( 'case-studies', 'partnerships' ); // Solutions has its own page and ALSO opens a mega menu on hover
foreach ( $menu_only as $slug ) {
	$old = get_page_by_path( $slug . '-v2', OBJECT, 'page' );
	if ( $old ) {
		wp_delete_post( $old->ID, true );
		WP_CLI::log( "Removed placeholder page {$slug}-v2 (menu-only item)." );
	}
}

foreach ( array_diff_key( array_merge( $main, $areas, $programmes, $partners, $case_items, $case_pages, $legal ), array_flip( $menu_only ) ) as $slug => $title ) {
	ez37h_page( $slug, $title, $page_ids );
}

// Pages made by redesign-local.php (components page) also get the new header/footer.
$components = get_page_by_path( 'components-v2', OBJECT, 'page' );
$condition_ids = array_values( $page_ids );
if ( $components ) {
	$condition_ids[] = (int) $components->ID;
}
// Payment flow pages (made by pages-local.php) also carry the new header/footer.
foreach ( array( 'checkout', 'payment-complete', 'payment-cancelled' ) as $pay_slug ) {
	$pay_page = get_page_by_path( $pay_slug, OBJECT, 'page' );
	if ( $pay_page ) {
		$condition_ids[] = (int) $pay_page->ID;
	}
}

function ez37h_url( string $slug, array $page_ids ): string {
	return isset( $page_ids[ $slug ] ) ? (string) get_permalink( $page_ids[ $slug ] ) : '#';
}

WP_CLI::log( sprintf( 'Private pages ready: %d.', count( $page_ids ) ) );

/* ------------------------------------------------------------------ menus */

function ez37h_menu( string $name, array $items, array $page_ids ): string {
	$existing = wp_get_nav_menu_object( $name );
	if ( $existing ) {
		wp_delete_nav_menu( $existing->term_id );
	}
	$menu_id = wp_create_nav_menu( $name );
	if ( is_wp_error( $menu_id ) ) {
		WP_CLI::error( $menu_id->get_error_message() );
	}

	foreach ( $items as $slug => $title ) {
		if ( ! isset( $page_ids[ $slug ] ) ) {
			continue;
		}
		wp_update_nav_menu_item(
			$menu_id,
			0,
			array(
				'menu-item-title'     => $title,
				'menu-item-object'    => 'page',
				'menu-item-object-id' => $page_ids[ $slug ],
				'menu-item-type'      => 'post_type',
				'menu-item-status'    => 'publish',
			)
		);
	}

	$obj = wp_get_nav_menu_object( $menu_id );
	return $obj ? (string) $obj->slug : '';
}

ez37h_menu( 'New Design Main Menu', $main, $page_ids );
$menu_company    = ez37h_menu( 'New Design Footer Company', array( 'about' => 'About', 'solutions' => 'Solutions', 'testimonials' => 'Testimonials', 'contact-us' => 'Contact Us', 'blog' => 'Blog' ), $page_ids );
$menu_solutions  = ez37h_menu( 'New Design Footer Solutions', $areas, $page_ids );
$menu_programmes = ez37h_menu( 'New Design Footer Case Studies', $case_items, $page_ids );

WP_CLI::log( 'Menus ready (not assigned to any theme location).' );

/* ------------------------------------------------------------------ logo */

function ez37h_logo(): array {
	$dir = dirname( __DIR__ ) . '/design/';
	foreach ( array( 'ez37-logo-clean.svg', 'ez37-logo-clean.png', 'ez37-logo-clean.webp' ) as $file ) {
		if ( ! file_exists( $dir . $file ) ) {
			continue;
		}

		$existing = get_posts(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'meta_key'       => '_ez37_logo_file', // phpcs:ignore WordPress.DB.SlowDBQuery
				'meta_value'     => $file . '|' . md5_file( $dir . $file ), // phpcs:ignore WordPress.DB.SlowDBQuery
				'posts_per_page' => 1,
				'fields'         => 'ids',
			)
		);
		if ( $existing ) {
			return array(
				'id'  => (int) $existing[0],
				'url' => (string) wp_get_attachment_url( $existing[0] ),
			);
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		add_filter(
			'upload_mimes',
			static function ( $m ) {
				$m['svg'] = 'image/svg+xml';
				return $m;
			}
		);

		$tmp = wp_tempnam( $file );
		copy( $dir . $file, $tmp );
		$id = media_handle_sideload(
			array(
				'name'     => $file,
				'tmp_name' => $tmp,
			),
			0,
			'EZ37 Solutions logo'
		);
		if ( is_wp_error( $id ) ) {
			WP_CLI::warning( 'Logo import failed: ' . $id->get_error_message() . ' — using the text wordmark.' );
			return array( 'id' => 0, 'url' => '' );
		}
		update_post_meta( $id, '_ez37_logo_file', $file . '|' . md5_file( $dir . $file ) );
		return array(
			'id'  => (int) $id,
			'url' => (string) wp_get_attachment_url( $id ),
		);
	}

	return array( 'id' => 0, 'url' => '' );
}

$logo = ez37h_logo();
WP_CLI::log( $logo['id'] ? 'Logo imported from design/.' : 'No clean logo file found in design/ — using the text wordmark.' );

/* ----------------------------------------------------- element builders */

function ez37h_gid( string $name ): string {
	return substr( md5( 'ez37-' . $name ), 0, 7 );
}

function ez37h_gc( string $name ): string {
	return 'globals/colors?id=' . ez37h_gid( $name );
}

function ez37h_gt( string $name ): string {
	return 'globals/typography?id=' . ez37h_gid( $name );
}

function ez37h_id( string $seed = '' ): string {
	static $n = 0;
	++$n;
	return substr( md5( 'ez37-hf-' . $seed . $n ), 0, 7 );
}

function ez37h_gap( int $px ): array {
	return array(
		'column'   => (string) $px,
		'row'      => (string) $px,
		'isLinked' => true,
		'unit'     => 'px',
	);
}

function ez37h_pad( int $t, int $r, int $b, int $l ): array {
	return array(
		'unit'     => 'px',
		'top'      => (string) $t,
		'right'    => (string) $r,
		'bottom'   => (string) $b,
		'left'     => (string) $l,
		'isLinked' => false,
	);
}

function ez37h_container( array $children, string $class = '', array $extra = array() ): array {
	return array(
		'id'       => ez37h_id( 'c' ),
		'elType'   => 'container',
		'settings' => array_merge(
			array(
				'content_width'  => 'full',
				'flex_direction' => 'column',
				'css_classes'    => $class,
			),
			$extra
		),
		'elements' => $children,
		'isInner'  => false,
	);
}

function ez37h_widget( string $type, array $settings ): array {
	return array(
		'id'         => ez37h_id( 'w' ),
		'elType'     => 'widget',
		'widgetType' => $type,
		'settings'   => $settings,
		'elements'   => array(),
	);
}

function ez37h_html( string $html ): array {
	return ez37h_widget( 'html', array( 'html' => $html ) );
}

function ez37h_heading( string $text, string $tag, string $font, string $color ): array {
	return ez37h_widget(
		'heading',
		array(
			'title'       => $text,
			'header_size' => $tag,
			'__globals__' => array(
				'typography_typography' => ez37h_gt( $font ),
				'title_color'           => ez37h_gc( $color ),
			),
		)
	);
}

/** Small stroke icons (24x24) used in the mega menu. */
function ez37h_icon( string $name ): string {
	$paths = array(
		'clipboard' => '<rect x="5" y="4" width="14" height="17" rx="2"/><path d="M9 4h6v3H9z"/><path d="m9 14 2 2 4-4"/>',
		'users'     => '<circle cx="9" cy="8" r="3"/><path d="M3 20c0-3.3 2.7-6 6-6s6 2.7 6 6"/><circle cx="17" cy="9" r="2.5"/><path d="M17 14.5c2.5 0 4 2 4 4.5"/>',
		'briefcase' => '<rect x="3" y="7" width="18" height="13" rx="2"/><path d="M9 7V5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2"/><path d="M3 13h18"/>',
		'landmark'  => '<path d="M3 21h18M5 21V10m4 11V10m6 11V10m4 11V10M2 10l10-6 10 6z"/>',
		'book'      => '<path d="M12 6c-2-1.5-5-2-9-2v14c4 0 7 .5 9 2 2-1.5 5-2 9-2V4c-4 0-7 .5-9 2z"/><path d="M12 6v14"/>',
		'award'     => '<circle cx="12" cy="9" r="5"/><path d="m8.5 13.5-1.5 7 5-3 5 3-1.5-7"/>',
		'target'    => '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="1.5"/>',
		'cap'       => '<path d="M2 9l10-5 10 5-10 5z"/><path d="M6 11v5c0 1.5 3 3 6 3s6-1.5 6-3v-5"/>',
		'compass'   => '<circle cx="12" cy="12" r="9"/><path d="m15.5 8.5-2 5-5 2 2-5z"/>',
		'userplus'  => '<circle cx="10" cy="8" r="3.5"/><path d="M3 20c0-3.6 3.1-6 7-6s7 2.4 7 6"/><path d="M19 8v6M16 11h6"/>',
		'handshake' => '<path d="M3 12l4-4 4 2 3-2 5 4"/><path d="M3 12l5 6 3-1 2 2 4-3 3-4"/>',
		'shield'    => '<path d="M12 3l8 3v6c0 4.5-3.2 8-8 9-4.8-1-8-4.5-8-9V6z"/>',
		'user'      => '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 3.6-7 8-7s8 3 8 7"/>',
		'chat'      => '<path d="M21 12a8 8 0 0 1-11.6 7.1L4 20l1-4.6A8 8 0 1 1 21 12z"/>',
	);
	$d = $paths[ $name ] ?? $paths['target'];
	return '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $d . '</svg>';
}

/** Link list used inside the mega menu panels: icon chip + title. */
function ez37h_links( array $items, array $page_ids, array $icons, string $class = 'ez37-mega__list' ): string {
	$out = '<ul class="' . esc_attr( $class ) . '">';
	foreach ( $items as $slug => $title ) {
		// Text only: no icons in the mega menu (the $icons argument is kept so icons can be switched back on).
		$href = ez37h_url( $slug, $page_ids );
		if ( ! empty( $GLOBALS['area_anchors'][ $slug ] ) && false !== strpos( $class, '--grid' ) ) {
			$href = ez37h_url( 'solutions', $page_ids ) . '#' . $GLOBALS['area_anchors'][ $slug ];
		}
		$out .= '<li><a href="' . esc_url( $href ) . '"><span class="ez37-mega__text">' . esc_html( $title ) . '</span></a></li>';
	}
	return $out . '</ul>';
}

/** Column heading: icon + uppercase title. */
function ez37h_col_title( string $icon, string $text ): string {
	return '<p class="ez37-mega__title">' . esc_html( $text ) . '</p>';
}

function ez37h_logo_widget( array $logo, string $dark_class = '' ): array {
	$home = ez37h_url( 'home', $GLOBALS['ez37h_page_ids'] );

	if ( $logo['id'] ) {
		return ez37h_widget(
			'image',
			array(
				'image'        => array(
					'url' => $logo['url'],
					'id'  => $logo['id'],
				),
				'image_size'   => 'full',
				'width'        => array( 'unit' => 'px', 'size' => false !== strpos( $dark_class, 'chip' ) ? 84 : 70, 'sizes' => array() ), // logo is ~1.87:1: 70px wide is ~38px tall, so the top nav does not grow
				'link_to'      => 'custom',
				'link'         => array( 'url' => $home ),
				'_css_classes' => 'ez37-logo ' . $dark_class,
			)
		);
	}

	return ez37h_html(
		'<a class="ez37-wordmark ' . esc_attr( $dark_class ) . '" href="' . esc_url( $home ) . '" aria-label="EZ37 Solutions, home"><span class="ez37-wordmark__main">EZ<span>37</span></span><small>SOLUTIONS</small></a>'
	);
}

/* ----------------------------------------------------------------- header */

$GLOBALS['ez37h_page_ids'] = $page_ids;

$item_ids = array();
foreach ( array( 'home', 'about', 'solutions', 'case-studies', 'testimonials', 'partnerships', 'blog', 'contact-us' ) as $k ) {
	$item_ids[ $k ] = ez37h_id( 'item-' . $k );
}

$menu_items = array();
$menu_children = array();
foreach ( $main as $slug => $title ) {
	$has_panel = in_array( $slug, array( 'solutions', 'case-studies', 'partnerships' ), true );
	$menu_items[] = array(
		'_id'                   => $item_ids[ $slug ],
		'item_title'            => $title,
		'item_link'             => array(
			'url'         => ( $has_panel && 'solutions' !== $slug ) ? '' : ez37h_url( $slug, $page_ids ), // menu-only parents: no link; Solutions links to its page
			'is_external' => '',
			'nofollow'    => '',
		),
		'item_dropdown_content' => $has_panel ? 'yes' : 'no',
	);

	if ( 'solutions' === $slug ) {
		$area_icons = array(
			'assessments'             => 'clipboard',
			'coaching'                => 'users',
			'hr-advisory'             => 'briefcase',
			'public-sector-solutions' => 'landmark',
			'facilitation-and-learning' => 'book',
			'coach-certification'     => 'award',
			'coaching-skills-leaders-managers' => 'users',
			'story-telling'           => 'message',
			'design-thinking'         => 'lightbulb',
		);
		$prog_icons = array(
			'mcs'                           => 'award',
			'advanced-mastery'              => 'target',
			'pccp'                          => 'award',
			'accp'                          => 'shield',
			'coaching-skills-training'      => 'cap',
			'group-team-coaching'           => 'users',
			'leadership-executive-coaching' => 'compass',
			'mentor-coaching'               => 'userplus',
		);
		$panel = ez37h_container(
			array(
				ez37h_container(
					array(
						ez37h_html( ez37h_col_title( 'compass', 'Service areas' ) . ez37h_links( $areas, $page_ids, $area_icons, 'ez37-mega__list ez37-mega__list--grid' ) ),
					),
					'ez37-mega__col ez37-mega__col--indigo'
				),
				ez37h_container(
					array(
						ez37h_html( ez37h_col_title( 'award', 'Coaching Programmes' ) . ez37h_links( $programmes, $page_ids, $prog_icons, 'ez37-mega__list ez37-mega__list--grid' ) ),
					),
					'ez37-mega__col ez37-mega__col--coral ez37-mega__col--wide'
				),
			),
			'ez37-mega',
			array(
				'_title'         => 'Solutions panel',
				'flex_direction' => 'row',
				'flex_gap'       => ez37h_gap( 16 ),
			)
		);
	} elseif ( 'case-studies' === $slug ) {
		$panel = ez37h_container(
			array(
				ez37h_container(
					array(
						ez37h_html( ez37h_col_title( 'book', 'Case Studies' ) . ez37h_links( $case_items, $page_ids, array( 'cases-one-to-one-coaching' => 'user', 'cases-pccp' => 'award', 'cases-team-coaching' => 'users', 'cases-coaching-skills' => 'cap' ) ) ),
					),
					'ez37-mega__col ez37-mega__col--indigo'
				),
			),
			'ez37-mega ez37-mega--small',
			array( '_title' => 'Case Studies panel' )
		);
	} elseif ( 'partnerships' === $slug ) {
		$panel = ez37h_container(
			array(
				ez37h_container(
					array(
						ez37h_html( ez37h_col_title( 'handshake', 'Partnerships' ) . ez37h_links( $partners, $page_ids, array( 'industry-partnerships' => 'briefcase', 'operational-partnerships' => 'handshake' ) ) ),
					),
					'ez37-mega__col ez37-mega__col--teal'
				),
			),
			'ez37-mega ez37-mega--small',
			array( '_title' => 'Partnerships panel' )
		);
	} else {
		// One child container per item, even when the item has no dropdown.
		$panel = ez37h_container( array(), '', array( '_title' => $title ) );
	}
	$menu_children[] = $panel;
}

$mega = array(
	'id'         => ez37h_id( 'mega' ),
	'elType'     => 'widget',
	'widgetType' => 'mega-menu',
	'settings'   => array(
		'menu_name'            => 'Main menu',
		'menu_items'           => $menu_items,
		'content_width'        => 'fit_to_content',
		'breakpoint_selector'  => 'tablet',
		'open_on'              => 'click',
		'_css_classes'         => 'ez37-nav',
		'__globals__'          => array(
			'menu_item_title_typography_typography' => ez37h_gt( 'New - Nav Link' ),
		),
	),
	'elements'   => $menu_children,
);

$topbar = ez37h_container(
	array(
		ez37h_html( '<span>Coaching, training and learning &amp; development for people and organisations.</span> <a href="' . esc_url( 'https://calendly.com/patricia-akinlotan/30-minutes-contact-meeting' ) . '" target="_blank" rel="noopener">Book a discovery call &rarr;</a>' ),
	),
	'ez37-topbar'
);

/* Search: icon button in the bar + a full-screen search overlay (moved to <body> by its script so the bar's blur can't clip it). */
$search_svg  = '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.6-3.6"/></svg>';
$search_html = '<button type="button" class="ez37-search-btn" aria-label="Search the site" aria-haspopup="dialog">' . $search_svg . '</button>'
	. '<div class="ez37-search" role="dialog" aria-modal="true" aria-label="Search" hidden>'
	. '<button type="button" class="ez37-search__close" aria-label="Close search"><svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg></button>'
	. '<form class="ez37-search__form" role="search" method="get" action="' . esc_url( home_url( '/' ) ) . '"><label class="screen-reader-text" for="ez37-search-input">Search</label>'
	. '<input id="ez37-search-input" type="search" name="s" placeholder="Search here..." autocomplete="off" required><button type="submit" aria-label="Search">' . $search_svg . '</button></form></div>'
	. '<script>(function(){var w=document.currentScript.parentNode,b=w.querySelector(".ez37-search-btn"),o=w.querySelector(".ez37-search");if(!b||!o||o._ez){return;}o._ez=1;document.body.appendChild(o);'
	. 'var i=o.querySelector("input"),c=o.querySelector(".ez37-search__close");'
	. 'function open(){o.hidden=false;document.documentElement.classList.add("ez37-search-open");setTimeout(function(){i.focus();},30);}'
	. 'function close(){o.hidden=true;document.documentElement.classList.remove("ez37-search-open");b.focus();}'
	. 'b.addEventListener("click",open);c.addEventListener("click",close);'
	. 'o.addEventListener("mousedown",function(e){if(e.target===o){close();}});'
	. 'document.addEventListener("keydown",function(e){if(e.key==="Escape"&&!o.hidden){close();}});})();</script>';

$pill = ez37h_container(
	array(
		ez37h_logo_widget( $logo ),
		$mega,
		ez37h_widget( 'html', array( 'html' => $search_html, '_css_classes' => 'ez37-header__search' ) ),
		ez37h_widget(
			'button',
			array(
				'text'         => 'Talk to us →',
				'link'         => array( 'url' => ez37h_url( 'contact-us', $page_ids ) ),
				'_css_classes' => 'ez37-btn ez37-header__cta',
				'__globals__'  => array(
					'typography_typography' => ez37h_gt( 'New - Button' ),
				),
			)
		),
	),
	'ez37-header__bar',
	array(
		'flex_direction'       => 'row',
		'flex_justify_content' => 'space-between',
		'flex_align_items'     => 'center',
		'flex_wrap'            => 'nowrap',
		'flex_gap'             => ez37h_gap( 16 ),
	)
);

$header_row = ez37h_container(
	array( $pill, ez37h_html( '<script>' . '(function(){var mq=window.matchMedia(\'(min-width:1025px)\'),t;
function nav(){return document.querySelector(\'.ez37-nav\');}
function trig(n){return [].filter.call(n.querySelectorAll(\'.e-n-menu-heading [aria-expanded]\'),function(e){return !e.parentElement.closest(\'.e-n-menu-heading [aria-expanded]\');});}
function closeAll(n){trig(n).forEach(function(o){if(o.getAttribute(\'aria-expanded\')===\'true\'){o.click();}});}
function init(){var n=nav();if(!n||n._ez37){return;}n._ez37=1;
trig(n).forEach(function(tr){tr.addEventListener(\'mouseenter\',function(){if(!mq.matches){return;}clearTimeout(t);trig(n).forEach(function(o){if(o!==tr&&o.getAttribute(\'aria-expanded\')===\'true\'){o.click();}});if(tr.getAttribute(\'aria-expanded\')!==\'true\'){tr.click();}});});
n.addEventListener(\'mouseenter\',function(){clearTimeout(t);});
n.addEventListener(\'mouseleave\',function(){if(!mq.matches){return;}t=setTimeout(function(){closeAll(n);},250);});}
init();window.addEventListener(\'load\',init);document.addEventListener(\'DOMContentLoaded\',init);})();' . '</script>' ) ),
	'ez37-header',
	array(
		'padding'               => ez37h_pad( 16, 20, 0, 20 ),
		'sticky'                => 'top',
		'sticky_on'             => array( 'desktop', 'tablet', 'mobile' ),
		'sticky_effects_offset' => 1,
	)
);

$header_data = array( $topbar, $header_row );

/* ----------------------------------------------------------------- footer */

require_once __DIR__ . '/lib/ezh-lib.php';
$GLOBALS['ez37_acc_imgs'] = ezh_media_dir( 'accreditation', '_ez37_acc' );

function ez37h_footer_contact_html(): string {
	$emails = array( 'info@ez37solutions.org', 'coaching@ezcoaching.org' );
	$phones = array(
		'+44 7553 856948'  => '+447553856948',
		'+234 813 089 8773' => '+2348130898773',
		'+234 809 788 1001' => '+2348097881001',
	);
	$html = '<div class="ez37-footer__contact"><div><em>Email</em>';
	foreach ( $emails as $e ) {
		$html .= '<a href="mailto:' . esc_attr( $e ) . '">' . esc_html( $e ) . '</a>';
	}
	$html .= '</div><div><em>Phone</em>';
	foreach ( $phones as $label => $tel ) {
		$html .= '<a href="tel:' . esc_attr( $tel ) . '">' . esc_html( $label ) . '</a>';
	}
	return $html . '</div></div>';
}

function ez37h_footer_offices_html(): string {
	$offices = array(
		'Ibadan' => 'Block 3, Plot 2, Road B, Off Oba Abimbola Way, Agodi GRA, Ibadan, Nigeria.',
		'Lagos'  => '17A, Chibo Ofodile Cl, Unit 3B, Eti-Osa, Lekki Phase 1, Lagos State, Nigeria.',
		'UK'     => '71-75 Shelton Street, Covent Garden, London, WC2H 9JQ, United Kingdom.',
	);
	$pin  = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M12 21s-6.5-5.6-6.5-11a6.5 6.5 0 0 1 13 0c0 5.4-6.5 11-6.5 11z"/><circle cx="12" cy="10" r="2.4"/></svg>';
	$html = '<div class="ez37-footer__addresses">';
	foreach ( $offices as $label => $addr ) {
		$map   = 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode( $addr );
		$html .= '<div class="ez37-footer__addr"><b>' . esc_html( $label ) . ' <a class="ez37-footer__pin" href="' . esc_url( $map ) . '" target="_blank" rel="noopener" aria-label="' . esc_attr( $label . ' office on a map' ) . '">' . $pin . '</a></b><span>' . esc_html( $addr ) . '</span>' . '</div>';
	}
	$html .= '</div>';
	return $html;
}

function ez37h_footer_social_html(): string {
	// TODO: replace the # links with the real profile URLs (YouTube is the real channel).
	$social = array(
		'Facebook'  => array( '#', '<path d="M14 8h3V4h-3a5 5 0 0 0-5 5v3H6v4h3v8h4v-8h3l1-4h-4V9a1 1 0 0 1 1-1z"/>' ),
		'YouTube'   => array( 'https://www.youtube.com/@ez37solutionsltd', '<path d="M22 8.2a3 3 0 0 0-2.1-2.1C18 5.6 12 5.6 12 5.6s-6 0-7.9.5A3 3 0 0 0 2 8.2 31 31 0 0 0 1.6 12 31 31 0 0 0 2 15.8a3 3 0 0 0 2.1 2.1c1.9.5 7.9.5 7.9.5s6 0 7.9-.5a3 3 0 0 0 2.1-2.1c.4-1.2.4-3.8.4-3.8s0-2.6-.4-3.8zM10 15V9l5 3z"/>' ),
		'LinkedIn'  => array( '#', '<path d="M4.98 3.5a2.5 2.5 0 1 1 0 5 2.5 2.5 0 0 1 0-5zM3 9.5h4V21H3zM9.5 9.5h3.8v1.6h.1c.5-1 1.8-2 3.8-2 4 0 4.8 2.6 4.8 6V21h-4v-5c0-1.2 0-2.8-1.7-2.8s-2 1.3-2 2.7V21h-4z"/>' ),
		'Instagram' => array( '#', '<rect x="3" y="3" width="18" height="18" rx="5" fill="none" stroke="currentColor" stroke-width="2"/><circle cx="12" cy="12" r="4" fill="none" stroke="currentColor" stroke-width="2"/><circle cx="17.5" cy="6.5" r="1.2"/>' ),
	);
	$html = '<div class="ez37-footer__social" aria-label="Follow us">';
	foreach ( $social as $name => $d ) {
		$html .= '<a href="' . esc_url( $d[0] ) . '" aria-label="' . esc_attr( $name ) . '" target="_blank" rel="noopener"><svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">' . $d[1] . '</svg></a>';
	}
	return $html . '</div>';
}

function ez37h_footer_accreditation_html(): string {
	$acc_imgs = $GLOBALS['ez37_acc_imgs'] ?? array();
	$items = array(
		'icf-level-1' => 'ICF-Accredited Coaching Education, Level 1',
		'icf-level-2' => 'ICF-Accredited Coaching Education, Level 2',
	);
	$html = '<div class="ez37-footer__badges">';
	foreach ( $items as $key => $alt ) {
		if ( ! empty( $acc_imgs[ $key ] ) ) {
			$html .= '<img src="' . esc_url( $acc_imgs[ $key ] ) . '" alt="' . esc_attr( $alt ) . '" loading="lazy" width="120" height="120">';
		}
	}
	return $html . '</div>';
}

function ez37h_footer_accband_html(): string {
	$acc_imgs = $GLOBALS['ez37_acc_imgs'] ?? array();
	$badges   = '';
	foreach ( array(
		'icf-level-1' => 'ICF-Accredited Coaching Education, Level 1',
		'icf-level-2' => 'ICF-Accredited Coaching Education, Level 2',
	) as $key => $alt ) {
		if ( ! empty( $acc_imgs[ $key ] ) ) {
			$badges .= '<div class="ez37-accband__item ez37-accband__logo"><img src="' . esc_url( $acc_imgs[ $key ] ) . '" alt="' . esc_attr( $alt ) . '" loading="lazy" width="120" height="120"></div>';
		}
	}
	$certs = array(
		array( 'ISO', '9001:2015', 'Certified quality management' ),
		array( 'IFC-LPI', 'Partner', 'Certified trainers and facilitators' ),
	);
	$cards = '';
	foreach ( $certs as $c ) {
		$cards .= '<div class="ez37-accband__item ez37-accband__card"><b>' . esc_html( $c[0] ) . '</b><strong>' . esc_html( $c[1] ) . '</strong><span>' . esc_html( $c[2] ) . '</span></div>';
	}
	// TODO: swap the ISO and IFC-LPI text cards for the official logo files once supplied (design/accreditation/).
	return '<div class="ez37-accband__inner"><h3 class="ez37-accband__title">Accredited &amp; Certified</h3><div class="ez37-accband__row">' . $badges . $cards . '</div></div>';
}

/**
 * Floating WhatsApp chat: a green button that opens a small panel; sending opens WhatsApp (wa.me) with the visitor's text.
 * The business number is public (footer). Change it here only. Styles: design/ez37-chat.css.
 */
function ez37h_footer_chat_html(): string {
	$number = '2348130898773'; // +234 813 089 8773, digits only, international format.
	$wa     = '<svg viewBox="0 0 32 32" aria-hidden="true"><path d="M16.02 3C8.84 3 3 8.83 3 16c0 2.3.6 4.55 1.75 6.53L3 29l6.64-1.7A13 13 0 0 0 16 29c7.17 0 13-5.84 13-13S23.2 3 16.02 3zm0 23.7c-1.95 0-3.86-.53-5.53-1.52l-.4-.23-3.94 1.01 1.05-3.84-.26-.4A10.7 10.7 0 1 1 16.02 26.7zm5.9-8c-.32-.16-1.9-.94-2.2-1.05-.3-.1-.5-.16-.72.16-.22.32-.84 1.05-1.03 1.27-.19.22-.38.24-.7.08-.32-.16-1.36-.5-2.59-1.6-.96-.85-1.6-1.9-1.8-2.22-.19-.32-.02-.5.14-.66.15-.14.32-.38.48-.57.16-.19.21-.32.32-.54.1-.21.05-.4-.03-.56-.08-.16-.72-1.74-1-2.38-.26-.62-.52-.54-.72-.55h-.62c-.21 0-.56.08-.85.4-.29.32-1.12 1.1-1.12 2.67s1.15 3.1 1.3 3.32c.16.21 2.26 3.45 5.47 4.84.76.33 1.36.52 1.82.67.77.24 1.46.2 2.01.12.61-.09 1.9-.78 2.17-1.53.27-.75.27-1.4.19-1.53-.08-.13-.3-.21-.62-.37z"/></svg>';
	$send   = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M2.5 20.5 22 12 2.5 3.5l.01 6.6L15 12 2.51 13.9z"/></svg>';

	$js = '(function(){var r=document.getElementById("ez37-wa");if(!r||r._ez){return;}r._ez=1;'
		. 'var fab=r.querySelector(".ez37-wa__fab"),cl=r.querySelector(".ez37-wa__close"),f=r.querySelector("form"),i=r.querySelector("input");'
		. 'function set(o){r.classList.toggle("is-open",o);fab.setAttribute("aria-expanded",o?"true":"false");if(o){setTimeout(function(){i.focus();},120);}else{fab.focus();}}'
		. 'fab.addEventListener("click",function(){set(true);});cl.addEventListener("click",function(){set(false);});'
		. 'document.addEventListener("keydown",function(e){if(e.key==="Escape"&&r.classList.contains("is-open")){set(false);}});'
		. 'f.addEventListener("submit",function(e){e.preventDefault();var t=(i.value||"").trim()||"Hello EZ37 Solutions, I would like to know more.";'
		. 'window.open("https://wa.me/' . $number . '?text="+encodeURIComponent(t),"_blank","noopener");i.value="";set(false);});})();';

	return '<div class="ez37-wa" id="ez37-wa">'
		. '<button type="button" class="ez37-wa__fab" aria-expanded="false" aria-controls="ez37-wa-panel">' . $wa . '<span>Hi, how can I help?</span></button>'
		. '<div class="ez37-wa__panel" id="ez37-wa-panel" role="dialog" aria-label="Chat with EZ37 Solutions on WhatsApp">'
		. '<div class="ez37-wa__head"><span class="ez37-wa__avatar" aria-hidden="true">EZ</span><div class="ez37-wa__who"><b>Chat with us on WhatsApp</b><span>Our team is here to answer your questions. Ask us anything!</span></div>'
		. '<button type="button" class="ez37-wa__close" aria-label="Close chat"><svg viewBox="0 0 14 14" aria-hidden="true"><path d="M2 2l10 10M12 2L2 12"/></svg></button></div>'
		. '<div class="ez37-wa__body"><span class="ez37-wa__bubble">Hi, how can I help?</span></div>'
		. '<form class="ez37-wa__form"><input type="text" name="m" maxlength="500" placeholder="Reply to EZ37solutions.org" aria-label="Your message" autocomplete="off">'
		. '<button type="submit" class="ez37-wa__send" aria-label="Send on WhatsApp">' . $send . '</button></form>'
		. '</div></div><script>' . $js . '</script>';
}

function ez37h_footer_video_html(): string {
	// Playlist embed taken from the existing EZ37 YouTube block (channel uploads playlist).
	return '<div class="ez37-footer__video"><iframe src="https://www.youtube.com/embed/videoseries?list=UUpKD3maN0PLUTnWkvCMyaaQ" title="EZ37 Solutions on YouTube" loading="lazy" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe></div>'
		. '<a class="ez37-footer__more" href="https://www.youtube.com/@ez37solutionsltd/videos" target="_blank" rel="noopener">View all videos &rarr;</a>';
}


function ez37h_nav_menu_widget( string $slug ): array {
	$items = wp_get_nav_menu_items( $slug );
	$html  = '<ul class="ez37-footer__list">';
	foreach ( (array) $items as $item ) {
		$html .= '<li><a href="' . esc_url( $item->url ) . '">' . esc_html( $item->title ) . '</a></li>';
	}
	$html .= '</ul>';
	return ez37h_html( $html );
}

$logo_chip = ez37h_container(
	array( ez37h_logo_widget( $logo, 'ez37-logo--chip' ) ),
	'ez37-footer__chip'
);

$footer_cols = array(
	ez37h_container(
		array(
			$logo_chip,
			ez37h_html( '<p class="ez37-footer__tagline"><strong>Developing Leaders - Delivering Performance - Deepening Cultures.</strong> We help you create meaningful change in yourself, others, organisations, and the world.</p>' ),
			ez37h_html( ez37h_footer_contact_html() ),
		),
		'ez37-footer__col ez37-footer__col--logo ez37-footer__col--brand2'
	),
	ez37h_container(
		array(
			ez37h_heading( 'Explore', 'h4', 'New - H4 Subhead', 'New - White' ),
			ez37h_nav_menu_widget( $menu_company ),
		),
		'ez37-footer__col ez37-footer__col--company'
	),
	ez37h_container(
		array(
			ez37h_heading( 'Links', 'h4', 'New - H4 Subhead', 'New - White' ),
			ez37h_nav_menu_widget( $menu_solutions ),
		),
		'ez37-footer__col ez37-footer__col--service'
	),
	ez37h_container(
		array(
			ez37h_heading( 'Case Studies', 'h4', 'New - H4 Subhead', 'New - White' ),
			ez37h_nav_menu_widget( $menu_programmes ),
		),
		'ez37-footer__col ez37-footer__col--programmes'
	),
	ez37h_container(
		array(
			ez37h_heading( 'Watch us on YouTube', 'h4', 'New - H4 Subhead', 'New - White' ),
			ez37h_html( ez37h_footer_video_html() ),
			ez37h_html( ez37h_footer_social_html() ),
		),
		'ez37-footer__col ez37-footer__col--video'
	),
	ez37h_container(
		array(
			ez37h_heading( 'Visit our offices', 'h4', 'New - H4 Subhead', 'New - White' ),
			ez37h_html( ez37h_footer_offices_html() ),
		),
		'ez37-footer__col ez37-footer__col--offices'
	),
);

$footer_top = ez37h_container(
	$footer_cols,
	'ez37-footer__top',
	array(
		'flex_direction' => 'row',
		'flex_wrap'      => 'wrap',
		'flex_gap'       => ez37h_gap( 48 ),
	)
);

$footer_bottom = ez37h_container(
	array(
		ez37h_html(
			'<div class="ez37-footer__legal"><span>&copy; ' . esc_html( gmdate( 'Y' ) ) . ' EZ37 Solutions. All rights reserved.</span>'
			. '<span><a href="' . esc_url( ez37h_url( 'privacy-policy', $page_ids ) ) . '">Privacy Policy</a> &middot; <a href="' . esc_url( ez37h_url( 'terms', $page_ids ) ) . '">Terms</a></span></div>'
		),
	),
	'ez37-footer__bottom'
);

$footer_root = ez37h_container(
	array( $footer_top, $footer_bottom ),
	'ez37-footer',
	array(
		'padding'  => ez37h_pad( 56, 48, 24, 48 ),
		'flex_gap' => ez37h_gap( 40 ),
	)
);

$footer_accband = ez37h_container(
	array( ez37h_html( ez37h_footer_accband_html() ) ),
	'ez37-accband',
	array( 'padding' => ez37h_pad( 0, 0, 0, 0 ) )
);

$footer_chat = ez37h_container(
	array( ez37h_html( ez37h_footer_chat_html() ) ),
	'ez37-chat-wrap',
	array( 'padding' => ez37h_pad( 0, 0, 0, 0 ) )
);

$footer_data = array( $footer_accband, $footer_root, $footer_chat );

/* -------------------------------------------------- save Theme Builder docs */

function ez37h_template( string $title, string $type, array $data, string $css = '' ): int {
	$existing = get_posts(
		array(
			'post_type'      => 'elementor_library',
			'post_status'    => 'any',
			'title'          => $title,
			'posts_per_page' => 1,
			'fields'         => 'ids',
		)
	);

	$arr = array(
		'post_type'   => 'elementor_library',
		'post_status' => 'publish',
		'post_title'  => $title,
	);
	if ( $existing ) {
		$arr['ID'] = $existing[0];
		$id        = wp_update_post( $arr, true );
	} else {
		$id = wp_insert_post( $arr, true );
	}
	if ( is_wp_error( $id ) ) {
		WP_CLI::error( $id->get_error_message() );
	}

	wp_set_object_terms( $id, $type, 'elementor_library_type' );
	update_post_meta( $id, '_elementor_template_type', $type );
	update_post_meta( $id, '_elementor_edit_mode', 'builder' );
	update_post_meta( $id, '_elementor_version', defined( 'ELEMENTOR_VERSION' ) ? ELEMENTOR_VERSION : '3.0.0' );
	update_post_meta( $id, '_wp_page_template', 'default' );
	update_post_meta( $id, '_elementor_data', wp_slash( wp_json_encode( $data ) ) );
	// The design-system stylesheet travels with the header/footer so every new page gets it.
	update_post_meta( $id, '_elementor_page_settings', array( 'custom_css' => $css ) );

	return (int) $id;
}

$css_file = dirname( __DIR__ ) . '/design/ez37-redesign.css';
$css      = file_exists( $css_file ) ? (string) file_get_contents( $css_file ) : '';
$chat_css = dirname( __DIR__ ) . '/design/ez37-chat.css';
if ( file_exists( $chat_css ) ) {
	$css .= "\n" . file_get_contents( $chat_css );
}
if ( '' === $css ) {
	WP_CLI::warning( 'design/ez37-redesign.css not found; header and footer will be unstyled.' );
}

$header_id = ez37h_template( 'New Design Header', 'header', $header_data, $css );
$footer_id = ez37h_template( 'New Design Footer', 'footer', $footer_data, $css );

// Display conditions: only the new pages.
$conditions = array();
foreach ( array_unique( $condition_ids ) as $pid ) {
	$conditions[] = array(
		'type'     => 'include',
		'name'     => 'singular',
		'sub_name' => 'page',
		'sub_id'   => (string) $pid,
	);
}

$tb      = \ElementorPro\Modules\ThemeBuilder\Module::instance();
$manager = $tb->get_conditions_manager();
$manager->save_conditions( $header_id, $conditions );
$manager->save_conditions( $footer_id, $conditions );

\Elementor\Plugin::$instance->files_manager->clear_cache();

WP_CLI::success( sprintf( 'Header (#%d) and footer (#%d) saved, shown on %d new pages only.', $header_id, $footer_id, count( $conditions ) ) );
WP_CLI::log( 'View:  ' . ez37h_url( 'home', $page_ids ) );
WP_CLI::log( 'Edit header: ' . admin_url( 'post.php?post=' . $header_id . '&action=elementor' ) );
WP_CLI::log( 'Edit footer: ' . admin_url( 'post.php?post=' . $footer_id . '&action=elementor' ) );
