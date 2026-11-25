<?php
/**
 * EZ37 redesign — build the PRIVACY POLICY page (v2). LOCAL DEVELOPMENT ONLY.
 *
 * The wording below is the client's policy text, dated 26 May 2022, kept as supplied. Edit the $sections array to change it.
 *
 *   ddev exec wp --path=/var/www/html/wordpress eval-file /var/www/html/bin/privacy-local.php
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

$page = get_page_by_path( 'privacy-policy-v2', OBJECT, 'page' );
if ( ! $page ) {
	WP_CLI::error( 'privacy-policy-v2 not found. Run bin/header-footer-local.php first.' );
}

$ol = static fn( array $a ): string => '<ol>' . implode( '', array_map( static fn( $t ) => '<li>' . $t . '</li>', $a ) ) . '</ol>';
$ps = static fn( array $a ): string => implode( '', array_map( static fn( $t ) => '<p>' . $t . '</p>', $a ) );

$ask = array( 'Why do we process your data?', 'Whom your data had been/shall be shared with.', 'How long we shall keep your data.', 'Source(s) of your data in the event it was collected from you directly.' );

$sections_txt = array(
	array( '1.0', 'Introduction', $ps( array( 'Protecting the privacy of our clients is of serious importance to us. In the light of this, we are committed to:' ) ) . $ol( array( 'protecting your data in accordance with this policy and in compliance with the provisions of relevant data protection laws and regulations especially the UK Data Protection Act, 2018.', 'ensuring in accordance with the Act, that our clients take control of their data and how it is used.', 'maintaining the confidentiality of the personal data of our clients.' ) ) ),
	array( '2.0', 'Definition of Personal Data', $ps( array( 'It means any information that can identify a natural person who in this context is regarded as the data subject.', 'This definition excludes anonymous data where the identity of the data subject is not reflected.' ) ) ),
	array( '3.0', 'The Main Thrust of the Policy', $ps( array( 'It outlines the type of personal data we collect about you, how we do so and how we process it.' ) ) ),
	array( '4.0', 'Qualifying Age for Sharing Your Personal Data', $ps( array( 'You must be at least 16 years old before you can share your data with us.' ) ) ),
	array( '5.0', 'Examples of Data We Collect', $ps( array( 'We collect data related to your:' ) ) . $ol( array( 'Identity e.g. Name', 'Expression of interest.', 'Contact address (including e-mail) and phone no(s).', 'Enrolment', 'Social media handles.' ) ) ),
	array( '6.0', 'What We Do with Your Personal Data', $ps( array( 'We shall only:' ) ) . $ol( array( 'use your data when the law permits us to do so and for the purposes specified in this policy.', 'process it to meet our contractual obligations with you and to provide you with our services as an accredited coach training and education provider.', 'use it for purposes of sharing information with you about our services and activities.' ) ) . $ps( array( 'In effect, we require your data to enable us to provide you with our services otherwise we shall be unable to do so.', 'In the above regard, we shall rely on your consent as a lawful basis for processing your data which you have the right to withdraw at any time.' ) ) ),
	array( '7.0', 'What We Shall Not Do', $ps( array( 'We shall not:' ) ) . $ol( array( 'collect any unnecessary personal data from you', 'process your data in any way other than as specified in this', 'supply your data to any third party without your consent, except where such a transfer is a necessary part of the activities that we undertake.' ) ) . $ps( array( 'retain your data beyond the period it is required.' ) ) ),
	array( '8.0', 'Data Access', $ps( array( 'You have the right to access any personal information that we collect, keep, and process about you.' ) ) ),
	array( '9.0', 'Enquiries About Your Data', $ps( array( 'You have the right to ask:' ) ) . $ol( $ask ) ),
	array( '10.0', 'Incomplete/Inaccurate Personal Data About You', $ps( array( 'You have the right to ask:' ) ) . $ol( $ask ) ),
	array( '11.0', 'Amendment of the Policy', $ol( array( 'We may alter this policy from time to time in compliance with changes in the law and for purposes of bringing it in line with current conventional practices.', 'Where such changes are undertaken, the amended policy shall be made available on our website. In addition, we shall post the reasons for the changes.' ) ) ),
	array( '12.0', 'Enquiries/Complaints', $ps( array( 'In the event anyone has some enquiries/complaints, these should be addressed to the Director, EZ37 UK Ltd as detailed out below.', '71-75 Shelton Street, Convent Garden, London, WC2H 9JQ', 'Email: <a href="mailto:coaching@ez37solutions.org">coaching@ez37solutions.org</a>' ) ) ),
);

$body = '<div class="ezl-doc__inner"><p class="ezl-doc__date">Dated 26 May 2022</p>';
foreach ( $sections_txt as $s ) {
	$body .= '<section class="ezl-sec"><h2><span>' . esc_html( $s[0] ) . '</span>' . esc_html( $s[1] ) . '</h2>' . $s[2] . '</section>';
}
$body .= '</div>';

$sections   = array();
$sections[] = ezh_banner_hero( 'Privacy Policy', 'How EZ37 Solutions collects, uses and protects your personal data.', array(), '', '', 'Privacy Policy', true );
$sections[] = ezh_section( 'ezl-doc', $body );

ezh_save_page( $page->ID, $sections, array( 'ez37-redesign.css', 'ez37-home.css', 'ez37-programme.css', 'ez37-bhero.css', 'ez37-components.css', 'ez37-legal.css' ) );

WP_CLI::success( 'Privacy Policy built: ' . get_permalink( $page->ID ) );
