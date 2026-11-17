<?php
/**
 * EZ37 redesign — LOCAL DEVELOPMENT ONLY.
 *
 * Adds the "New - ..." Global Colors and Global Fonts to the active Elementor
 * kit and creates a PRIVATE "Components v2" page that shows every component.
 *
 * Safe to re-run: existing "New - ..." tokens are replaced, never duplicated;
 * the default Primary/Secondary/Text/Accent tokens are never touched.
 *
 * Run from the project root:
 *   ddev exec wp --path=/var/www/html/wordpress eval-file /var/www/html/bin/redesign-local.php
 *
 * Do NOT run this against the client's production site.
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

/* ------------------------------------------------------------------ tokens */

$colors = array(
	'New - Ink'          => '#14123F',
	'New - Body Text'    => '#4A4968',
	'New - Brand Indigo' => '#4338CA',
	'New - Indigo Deep'  => '#1E1B6B',
	'New - Indigo Tint'  => '#EEF0FF',
	'New - Indigo Mist'  => '#F7F7FF',
	'New - Accent Coral' => '#FF5A47',
	'New - Coral Deep'   => '#D63424',
	'New - Coral Hover'  => '#B42318',
	'New - Coral Tint'   => '#FFEDEA',
	'New - Teal'         => '#0E9F8E',
	'New - Teal Tint'    => '#E3F7F4',
	'New - Amber'        => '#F5A524',
	'New - Amber Tint'   => '#FFF4DC',
	'New - White'        => '#FFFFFF',
	'New - Line'         => '#E3E2F3',
);

// name => [family, weight, [desktop, tablet, mobile], line-height em, letter-spacing em, uppercase].
$fonts = array(
	'New - H1 Hero'        => array( 'Poppins', '700', array( 56, 44, 34 ), 1.12, -0.02, false ),
	'New - H2 Section'     => array( 'Poppins', '600', array( 40, 32, 28 ), 1.2, -0.015, false ),
	'New - H3 Card Title'  => array( 'Poppins', '600', array( 22, 20, 20 ), 1.3, 0, false ),
	'New - H4 Subhead'     => array( 'Poppins', '600', array( 18, 18, 17 ), 1.4, 0, false ),
	'New - Eyebrow Badge'  => array( 'Poppins', '500', array( 12, 12, 12 ), 1.3, 0.08, true ),
	'New - Body Large'     => array( 'Inter', '400', array( 18, 17, 16 ), 1.65, 0, false ),
	'New - Body'           => array( 'Inter', '400', array( 16, 16, 16 ), 1.6, 0, false ),
	'New - Small'          => array( 'Inter', '400', array( 14, 14, 14 ), 1.55, 0, false ),
	'New - Button'         => array( 'Poppins', '600', array( 16, 16, 15 ), 1, 0, false ),
	'New - Stat Number'    => array( 'Poppins', '700', array( 48, 40, 36 ), 1.1, -0.02, false ),
	'New - Nav Link'       => array( 'Poppins', '500', array( 15, 15, 16 ), 1, 0, false ),
);

function ez37_gid( string $name ): string {
	return substr( md5( 'ez37-' . $name ), 0, 7 );
}

function ez37_px( $size ): array {
	return array(
		'unit'  => 'px',
		'size'  => $size,
		'sizes' => array(),
	);
}

/* --------------------------------------------------------- write to the kit */

$kit = \Elementor\Plugin::$instance->kits_manager->get_active_kit();
if ( ! $kit || ! $kit->get_id() ) {
	WP_CLI::error( 'No active Elementor kit found. Open any page in Elementor once, then re-run.' );
}

$kit_id   = $kit->get_id();
$settings = get_post_meta( $kit_id, '_elementor_page_settings', true );
$settings = is_array( $settings ) ? $settings : array();

$keep_colors = array();
foreach ( (array) ( $settings['custom_colors'] ?? array() ) as $item ) {
	if ( 0 !== strpos( (string) ( $item['title'] ?? '' ), 'New - ' ) ) {
		$keep_colors[] = $item;
	}
}
foreach ( $colors as $title => $hex ) {
	$keep_colors[] = array(
		'_id'   => ez37_gid( $title ),
		'title' => $title,
		'color' => $hex,
	);
}
$settings['custom_colors'] = $keep_colors;

$keep_type = array();
foreach ( (array) ( $settings['custom_typography'] ?? array() ) as $item ) {
	if ( 0 !== strpos( (string) ( $item['title'] ?? '' ), 'New - ' ) ) {
		$keep_type[] = $item;
	}
}
foreach ( $fonts as $title => $f ) {
	list( $family, $weight, $sizes, $line, $spacing, $upper ) = $f;

	$entry = array(
		'_id'                           => ez37_gid( $title ),
		'title'                         => $title,
		'typography_typography'         => 'custom',
		'typography_font_family'        => $family,
		'typography_font_weight'        => $weight,
		'typography_font_size'          => ez37_px( $sizes[0] ),
		'typography_font_size_tablet'   => ez37_px( $sizes[1] ),
		'typography_font_size_mobile'   => ez37_px( $sizes[2] ),
		'typography_line_height'        => array(
			'unit'  => 'em',
			'size'  => $line,
			'sizes' => array(),
		),
		// Elementor stores letter spacing in px; convert from em at desktop size.
		'typography_letter_spacing'     => ez37_px( round( $spacing * $sizes[0], 2 ) ),
	);
	if ( $upper ) {
		$entry['typography_text_transform'] = 'uppercase';
	}
	$keep_type[] = $entry;
}
$settings['custom_typography'] = $keep_type;

update_post_meta( $kit_id, '_elementor_page_settings', $settings );
WP_CLI::success( sprintf( 'Kit %d: %d colours and %d fonts written.', $kit_id, count( $colors ), count( $fonts ) ) );

/* ------------------------------------------------------- element builders */

function ez37_gc( string $name ): string {
	return 'globals/colors?id=' . ez37_gid( $name );
}

function ez37_gt( string $name ): string {
	return 'globals/typography?id=' . ez37_gid( $name );
}

function ez37_el_id(): string {
	static $n = 0;
	++$n;
	return substr( md5( 'ez37-el-' . $n ), 0, 7 );
}

function ez37_container( array $children, string $class = '', array $extra = array() ): array {
	$settings = array_merge(
		array(
			'content_width'  => 'full',
			'flex_direction' => 'column',
			'css_classes'    => $class,
		),
		$extra
	);

	return array(
		'id'       => ez37_el_id(),
		'elType'   => 'container',
		'settings' => $settings,
		'elements' => $children,
		'isInner'  => false,
	);
}

function ez37_row( array $children, string $class = '', int $gap = 24 ): array {
	return ez37_container(
		$children,
		$class,
		array(
			'flex_direction' => 'row',
			'flex_wrap'      => 'wrap',
			'flex_gap'       => array(
				'column'   => (string) $gap,
				'row'      => (string) $gap,
				'isLinked' => true,
				'unit'     => 'px',
			),
		)
	);
}

function ez37_widget( string $type, array $settings ): array {
	return array(
		'id'         => ez37_el_id(),
		'elType'     => 'widget',
		'widgetType' => $type,
		'settings'   => $settings,
		'elements'   => array(),
	);
}

function ez37_heading( string $html, string $tag, string $font, string $color = 'New - Ink', string $class = '' ): array {
	return ez37_widget(
		'heading',
		array(
			'title'        => $html,
			'header_size'  => $tag,
			'_css_classes' => $class,
			'__globals__'  => array(
				'typography_typography' => ez37_gt( $font ),
				'title_color'           => ez37_gc( $color ),
			),
		)
	);
}

function ez37_text( string $html, string $font = 'New - Body', string $color = 'New - Body Text' ): array {
	return ez37_widget(
		'text-editor',
		array(
			'editor'      => '<p>' . $html . '</p>',
			'__globals__' => array(
				'typography_typography' => ez37_gt( $font ),
				'text_color'            => ez37_gc( $color ),
			),
		)
	);
}

function ez37_button( string $label, string $class ): array {
	return ez37_widget(
		'button',
		array(
			'text'         => $label,
			'link'         => array( 'url' => '#' ),
			'_css_classes' => $class,
			'__globals__'  => array(
				'typography_typography' => ez37_gt( 'New - Button' ),
			),
		)
	);
}

function ez37_card( string $title, string $body, string $class = 'ez37-card' ): array {
	return ez37_container(
		array(
			ez37_heading( $title, 'h3', 'New - H3 Card Title' ),
			ez37_text( $body ),
		),
		$class,
		array( 'flex_gap' => array( 'column' => '16', 'row' => '16', 'isLinked' => true, 'unit' => 'px' ) )
	);
}

function ez37_stat( string $number, string $label, string $color, string $class ): array {
	return ez37_container(
		array(
			ez37_heading( $number, 'div', 'New - Stat Number', $color ),
			ez37_text( $label, 'New - Small' ),
		),
		'ez37-card ez37-stat ' . $class
	);
}

function ez37_swatches( array $colors ): array {
	$cards = array();
	foreach ( $colors as $title => $hex ) {
		$cards[] = ez37_container(
			array(
				ez37_widget(
					'html',
					array(
						'html' => sprintf(
							'<div style="width:120px"><div style="height:56px;border-radius:12px;background:%1$s;border:1px solid #E3E2F3"></div><div style="font:600 12px Poppins,sans-serif;margin-top:8px;color:#14123F">%2$s</div><div style="font:400 12px Inter,sans-serif;color:#4A4968">%1$s</div></div>',
							esc_attr( $hex ),
							esc_html( $title )
						),
					)
				),
			)
		);
	}
	return ez37_row( $cards, '', 16 );
}

/* ----------------------------------------------------------------- the page */

$page = array(
	// Hero.
	ez37_container(
		array(
			ez37_heading( 'Components v2 (private test page)', 'div', 'New - Eyebrow Badge', 'New - Brand Indigo', 'ez37-badge' ),
			ez37_heading( 'Build Better Coaches. <span class="ez37-hl">Unlock Real Growth.</span>', 'h1', 'New - H1 Hero' ),
			ez37_text( 'This page shows every component of the EZ37 design system so you can check hover, focus, tablet and mobile once, then copy blocks into real pages.', 'New - Body Large' ),
			ez37_row(
				array(
					ez37_button( 'Book a Discovery Call', 'ez37-btn' ),
					ez37_button( 'See Programmes', 'ez37-btn-outline' ),
				),
				'',
				16
			),
		),
		'',
		array(
			'padding'        => array( 'unit' => 'px', 'top' => '96', 'right' => '24', 'bottom' => '64', 'left' => '24', 'isLinked' => false ),
			'flex_gap'       => array( 'column' => '24', 'row' => '24', 'isLinked' => true, 'unit' => 'px' ),
			'boxed_width'    => array( 'unit' => 'px', 'size' => 1200, 'sizes' => array() ),
			'content_width'  => 'boxed',
		)
	),

	// Path cards.
	ez37_container(
		array(
			ez37_row(
				array(
					ez37_card( 'I lead the company.', 'Founders and executives: grow your leadership as fast as the company grows.', 'ez37-card ez37-path' ),
					ez37_card( 'I lead our people.', 'HR and people leaders: programmes your managers will actually use.', 'ez37-card ez37-path' ),
					ez37_card( 'I am not sure yet.', 'Take the short diagnostic and see which programme to start with.', 'ez37-card ez37-path' ),
				)
			),
		),
		'',
		array(
			'padding'       => array( 'unit' => 'px', 'top' => '0', 'right' => '24', 'bottom' => '64', 'left' => '24', 'isLinked' => false ),
			'content_width' => 'boxed',
			'boxed_width'   => array( 'unit' => 'px', 'size' => 1200, 'sizes' => array() ),
		)
	),

	// Tinted band with stats.
	ez37_container(
		array(
			ez37_heading( 'Proven results', 'div', 'New - Eyebrow Badge', 'New - Brand Indigo', 'ez37-badge' ),
			ez37_heading( 'The Numbers <span class="ez37-hl">Speak</span> For Themselves', 'h2', 'New - H2 Section' ),
			ez37_row(
				array(
					ez37_stat( '000', 'Placeholder statistic, replace with a real figure', 'New - Brand Indigo', '' ),
					ez37_stat( '0.0', 'Placeholder statistic, replace with a real figure', 'New - Coral Deep', 'ez37-stat--coral' ),
					ez37_stat( '00', 'Placeholder statistic, replace with a real figure', 'New - Teal', 'ez37-stat--teal' ),
				)
			),
		),
		'ez37-band',
		array(
			'flex_gap'       => array( 'column' => '24', 'row' => '24', 'isLinked' => true, 'unit' => 'px' ),
			'align_items'    => 'center',
		)
	),

	// Palette reference.
	ez37_container(
		array(
			ez37_heading( 'Colour tokens', 'h2', 'New - H2 Section' ),
			ez37_swatches( $colors ),
		),
		'',
		array(
			'padding'       => array( 'unit' => 'px', 'top' => '64', 'right' => '24', 'bottom' => '96', 'left' => '24', 'isLinked' => false ),
			'flex_gap'      => array( 'column' => '24', 'row' => '24', 'isLinked' => true, 'unit' => 'px' ),
			'content_width' => 'boxed',
			'boxed_width'   => array( 'unit' => 'px', 'size' => 1200, 'sizes' => array() ),
		)
	),
);

// Everything sits inside one root container carrying the scope class "ez37".
$root = ez37_container( $page, 'ez37' );

$css_path = dirname( __DIR__ ) . '/design/ez37-redesign.css';
$css      = file_exists( $css_path ) ? (string) file_get_contents( $css_path ) : '';
if ( '' === $css ) {
	WP_CLI::warning( 'design/ez37-redesign.css not found; the page will be unstyled.' );
}

$existing = get_page_by_path( 'components-v2', OBJECT, 'page' );
$post_arr = array(
	'post_title'  => 'Components v2',
	'post_name'   => 'components-v2',
	'post_type'   => 'page',
	'post_status' => 'private',
);
if ( $existing ) {
	$post_arr['ID'] = $existing->ID;
	$page_id        = wp_update_post( $post_arr, true );
} else {
	$page_id = wp_insert_post( $post_arr, true );
}
if ( is_wp_error( $page_id ) ) {
	WP_CLI::error( $page_id->get_error_message() );
}

update_post_meta( $page_id, '_wp_page_template', 'elementor_header_footer' );
update_post_meta( $page_id, '_elementor_edit_mode', 'builder' );
update_post_meta( $page_id, '_elementor_template_type', 'wp-page' );
update_post_meta( $page_id, '_elementor_version', defined( 'ELEMENTOR_VERSION' ) ? ELEMENTOR_VERSION : '3.0.0' );
update_post_meta( $page_id, '_elementor_data', wp_slash( wp_json_encode( array( $root ) ) ) );
update_post_meta( $page_id, '_elementor_page_settings', array( 'custom_css' => $css ) );

\Elementor\Plugin::$instance->files_manager->clear_cache();

WP_CLI::success( 'Private page ready: ' . get_permalink( $page_id ) );
WP_CLI::log( 'Edit in Elementor: ' . admin_url( 'post.php?post=' . $page_id . '&action=elementor' ) );
