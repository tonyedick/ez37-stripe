<?php
/**
 * EZ37 redesign — EXTEND the private "Components v2" page. LOCAL DEVELOPMENT ONLY.
 *
 * Appends the extended component library to the existing Components v2 page
 * (created by bin/redesign-local.php): buttons, forms, tabs, icons, tables and a
 * reusable section pattern. Safe to re-run: previously appended blocks (CSS class
 * "ez37-ext") are replaced, the original components are kept.
 *
 * Order of scripts:  redesign-local.php  ->  components-local.php
 * (redesign-local.php rebuilds the page from scratch, so run this one after it.)
 *
 *   ddev exec wp --path=/var/www/html/wordpress eval-file /var/www/html/bin/components-local.php
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

$page = get_page_by_path( 'components-v2', OBJECT, 'page' );
if ( ! $page ) {
	WP_CLI::error( 'Page components-v2 not found. Run bin/redesign-local.php first.' );
}

require_once __DIR__ . '/lib/ezh-lib.php';

/** Replace {{i:name}} tokens with the inline SVG icon. */
function ezx_tok( string $html ): string {
	return (string) preg_replace_callback(
		'/\{\{i:([a-z0-9-]+)\}\}/',
		static function ( $m ) {
			return ezh_i( $m[1] );
		},
		$html
	);
}

function ezx_section( string $html ): array {
	return ezh_section( 'ez37-ext ezx', ezx_tok( $html ) );
}

function ezx_spec( string $label, string $classes, string $body ): string {
	return '<div class="ezx-spec"><p class="ezx-spec__label"><span>' . esc_html( $label ) . '</span>' . ( $classes ? '<code>' . esc_html( $classes ) . '</code>' : '' ) . '</p>' . $body . '</div>';
}

$contact  = ezh_url( 'contact-us' );
$sections = array();

/* ------------------------------------------------------------- intro + index */
$sections[] = ezx_section(
	'<div class="ezh-wrap" id="ez37-library">'
	. ezh_head( 'Component library', 'Build Pages From <span class="ez37-hl">Reusable Parts</span>', 'Buttons, forms, tabs, icons and tables, all built from the EZ37 colour tokens, fonts and radii. Copy a block, change the words, and the page stays on-brand.' )
	. '<nav class="ezx-index" aria-label="Component groups"><a href="#ez37-buttons">Buttons</a><a href="#ez37-forms">Forms</a><a href="#ez37-tabs">Tabs</a><a href="#ez37-icons">Icons</a><a href="#ez37-tables">Tables</a><a href="#ez37-patterns">Section patterns</a></nav></div>'
);

/* ------------------------------------------------------------------ buttons */
$btn = <<<'HTML'
<div class="ezh-wrap" id="ez37-buttons">
<div class="ezh-sec-head ezh-center"><div class="ezh-badge-row"><span class="ez37-badge">Buttons</span></div><h2 class="ezh-h2">Buttons <span class="ez37-hl">And Links</span></h2><p class="ezh-lead">Six variants, three sizes, icon and loading states. They work on <code class="ezx-code">&lt;a&gt;</code> and <code class="ezx-code">&lt;button&gt;</code>.</p></div>
@@VARIANTS@@
@@DARK@@
@@SIZES@@
@@ICONS@@
@@STATES@@
@@GROUPS@@
</div>
HTML;
$btn = strtr(
	$btn,
	array(
		'@@VARIANTS@@' => ezx_spec(
			'Variants',
			'ez37-btn · -outline · -dark · -soft · -ghost · -btn-link',
			'<div class="ezx-row"><a class="ez37-btn" href="#">Primary</a><a class="ez37-btn-outline" href="#">Outline</a><a class="ez37-btn-dark" href="#">Dark</a><a class="ez37-btn-soft" href="#">Soft</a><a class="ez37-btn-ghost" href="#">Ghost</a><a class="ez37-btn-link" href="#"><span>Text link</span>{{i:arrow}}</a></div><p class="ezx-note">Primary is the single main action on a screen. Outline is the secondary action. Dark, soft and ghost are for quieter actions; the text link is for in-card navigation.</p>'
		),
		'@@DARK@@'     => ezx_spec(
			'On a dark background',
			'ez37-btn-light · ez37-btn',
			'<div class="ezx-dark"><div class="ezx-row"><a class="ez37-btn-light" href="#">Light</a><a class="ez37-btn" href="#">Primary</a></div></div>'
		),
		'@@SIZES@@'    => ezx_spec(
			'Sizes and shape',
			'ez37-btn--sm · (default) · --lg · --pill · --block',
			'<div class="ezx-row"><a class="ez37-btn ez37-btn--sm" href="#">Small</a><a class="ez37-btn" href="#">Default</a><a class="ez37-btn ez37-btn--lg" href="#">Large</a><a class="ez37-btn ez37-btn--pill" href="#">Pill</a><a class="ez37-btn-outline ez37-btn--sm ez37-btn--pill" href="#">Small outline pill</a></div><div style="margin-top:20px;max-width:420px"><a class="ez37-btn ez37-btn--block" href="#">Full-width button</a></div>'
		),
		'@@ICONS@@'    => ezx_spec(
			'With icons',
			'icon inside the button · ez37-btn--icon',
			'<div class="ezx-row"><a class="ez37-btn" href="#">Book a Consultation {{i:arrow}}</a><a class="ez37-btn-outline" href="#">{{i:download}} Download brochure</a><a class="ez37-btn-soft" href="#">{{i:calendar}} Pick a date</a><a class="ez37-btn-dark" href="#">{{i:mail}} Email us</a></div><div class="ezx-row" style="margin-top:20px"><button type="button" class="ez37-btn-soft ez37-btn--icon" aria-label="Call us">{{i:phone}}</button><button type="button" class="ez37-btn-dark ez37-btn--icon" aria-label="Email us">{{i:mail}}</button><button type="button" class="ez37-btn-outline ez37-btn--icon" aria-label="Search">{{i:search}}</button><button type="button" class="ez37-btn ez37-btn--icon" aria-label="Book a call">{{i:calendar}}</button><button type="button" class="ez37-btn-ghost ez37-btn--icon ez37-btn--sm" aria-label="Open menu">{{i:menu}}</button></div><p class="ezx-note">Icon-only buttons must carry an <code class="ezx-code">aria-label</code>.</p>'
		),
		'@@STATES@@'   => ezx_spec(
			'States',
			'.is-loading · [disabled] · hover and focus are built in',
			'<div class="ezx-row"><button type="button" class="ez37-btn is-loading" aria-busy="true">Sending</button><button type="button" class="ez37-btn-outline is-loading" aria-busy="true">Sending</button><button type="button" class="ez37-btn-soft is-loading" aria-busy="true">Sending</button><button type="button" class="ez37-btn" disabled>Disabled</button><button type="button" class="ez37-btn-outline" disabled>Disabled</button><button type="button" class="ez37-btn-soft" disabled>Disabled</button></div><p class="ezx-note">Hover any button to see the lift and colour change; tab to it to see the focus ring.</p>'
		),
		'@@GROUPS@@'   => ezx_spec(
			'Groups',
			'ez37-btn-group · --joined',
			'<div class="ezx-row" style="gap:32px"><div class="ez37-btn-group"><a class="ez37-btn" href="#">Book a Discovery Call</a><a class="ez37-btn-outline" href="#">See Programmes</a></div><div class="ez37-btn-group ez37-btn-group--joined"><button type="button" class="ez37-btn-soft">Monthly</button><button type="button" class="ez37-btn-dark">Quarterly</button><button type="button" class="ez37-btn-soft">Yearly</button></div></div>'
		),
	)
);
$sections[] = ezx_section( $btn );

/* -------------------------------------------------------------------- forms */
$f_fields = <<<'HTML'
<form class="ez37-form ez37-form--2col" action="#" novalidate onsubmit="return false">
<div class="ez37-field"><label class="ez37-label" for="fx-name">Full name<span class="ez37-req" aria-hidden="true">*</span></label><input class="ez37-input" id="fx-name" type="text" placeholder="Your full name" autocomplete="name"><p class="ez37-hint">As you would like it on your certificate.</p></div>
<div class="ez37-field"><label class="ez37-label" for="fx-email">Work email</label><div class="ez37-input-wrap">{{i:mail}}<input class="ez37-input" id="fx-email" type="email" placeholder="name@company.com" autocomplete="email"></div></div>
<div class="ez37-field"><label class="ez37-label" for="fx-phone">Phone</label><div class="ez37-input-wrap">{{i:phone}}<input class="ez37-input" id="fx-phone" type="tel" placeholder="+234 …" autocomplete="tel"></div></div>
<div class="ez37-field"><label class="ez37-label" for="fx-service">Service of interest</label><select class="ez37-select" id="fx-service"><option value="">Choose one</option><option>Leadership and executive coaching</option><option>Group and team coaching</option><option>Assessments</option><option>Learning and certification</option></select></div>
<div class="ez37-field"><label class="ez37-label" for="fx-date">Preferred date</label><input class="ez37-input" id="fx-date" type="date"></div>
<div class="ez37-field"><label class="ez37-label" for="fx-team">Team size</label><input class="ez37-range" id="fx-team" type="range" min="1" max="200" value="25"><div class="ez37-range-row"><span>1</span><span>200+</span></div></div>
<div class="ez37-field is-full"><label class="ez37-label" for="fx-msg">How can we help?</label><textarea class="ez37-textarea" id="fx-msg" placeholder="Tell us a little about your goals"></textarea></div>
<div class="ez37-field is-full"><span class="ez37-label">Attachment</span><label class="ez37-dropzone">{{i:upload}}<b>Drop a file here or browse</b><span>PDF or Word, up to 5 MB</span><input type="file" aria-label="Attachment"></label></div>
</form>
HTML;
$f_choices = <<<'HTML'
<div class="ezx-grid2">
<div class="ez37-field"><span class="ez37-label">Checkboxes</span><div class="ez37-choices"><label class="ez37-check"><input type="checkbox" checked><span class="ez37-box"></span><span>Coaching</span></label><label class="ez37-check"><input type="checkbox"><span class="ez37-box"></span><span>Assessments</span></label><label class="ez37-check"><input type="checkbox" disabled><span class="ez37-box"></span><span>Disabled option</span></label></div></div>
<div class="ez37-field"><span class="ez37-label">Radio buttons</span><div class="ez37-choices"><label class="ez37-radio"><input type="radio" name="fx-r" checked><span class="ez37-box"></span><span>One to one</span></label><label class="ez37-radio"><input type="radio" name="fx-r"><span class="ez37-box"></span><span>Group</span></label><label class="ez37-radio"><input type="radio" name="fx-r" disabled><span class="ez37-box"></span><span>Disabled option</span></label></div></div>
<div class="ez37-field"><span class="ez37-label">Switches</span><div class="ez37-choices"><label class="ez37-switch"><input type="checkbox" checked><span class="ez37-track"></span><span>Send me programme updates</span></label><label class="ez37-switch"><input type="checkbox"><span class="ez37-track"></span><span>Remind me before sessions</span></label></div></div>
<div class="ez37-field"><span class="ez37-label">Choice cards</span><div class="ez37-choices"><label class="ez37-choice"><input type="radio" name="fx-c" checked><span class="ez37-choice__body">{{i:users}}<span><b>Coaching</b><span>One to one or team</span></span></span></label><label class="ez37-choice"><input type="radio" name="fx-c"><span class="ez37-choice__body">{{i:clip}}<span><b>Assessments</b><span>See the gap first</span></span></span></label></div></div>
</div>
HTML;
$f_states = <<<'HTML'
<div class="ez37-form ez37-form--2col">
<div class="ez37-field"><label class="ez37-label" for="fs-1">Default</label><input class="ez37-input" id="fs-1" type="text" value="Adaora Ayoade"></div>
<div class="ez37-field"><label class="ez37-label" for="fs-2">Disabled</label><input class="ez37-input" id="fs-2" type="text" value="Not editable" disabled></div>
<div class="ez37-field is-error"><label class="ez37-label" for="fs-3">Error</label><input class="ez37-input" id="fs-3" type="email" value="name@company" aria-invalid="true" aria-describedby="fs-3e"><p class="ez37-error" id="fs-3e">Enter a full email address, for example name@company.com.</p></div>
<div class="ez37-field is-success"><label class="ez37-label" for="fs-4">Success</label><input class="ez37-input" id="fs-4" type="email" value="name@company.com"><p class="ez37-hint">Looks good.</p></div>
</div>
<p class="ezx-note">Click into any field to see the focus ring. Errors use colour <em>and</em> a text message so they are not colour-only.</p>
HTML;
$f_contact = <<<'HTML'
<div class="ezx-grid2" style="align-items:start">
<form class="ez37-form" action="#" novalidate data-ez37-demo>
<div class="ez37-field"><label class="ez37-label" for="fc-name">Full name<span class="ez37-req" aria-hidden="true">*</span></label><input class="ez37-input" id="fc-name" type="text" required autocomplete="name"><p class="ez37-error">Please tell us your name.</p></div>
<div class="ez37-field"><label class="ez37-label" for="fc-email">Work email<span class="ez37-req" aria-hidden="true">*</span></label><input class="ez37-input" id="fc-email" type="email" required autocomplete="email"><p class="ez37-error">Enter a valid email address.</p></div>
<div class="ez37-field"><label class="ez37-label" for="fc-org">Organisation</label><input class="ez37-input" id="fc-org" type="text" autocomplete="organisation"></div>
<div class="ez37-field"><label class="ez37-label" for="fc-svc">What do you need?<span class="ez37-req" aria-hidden="true">*</span></label><select class="ez37-select" id="fc-svc" required><option value="">Choose one</option><option>Coaching</option><option>Assessments</option><option>Learning and certification</option><option>Not sure yet</option></select><p class="ez37-error">Choose the closest option.</p></div>
<div class="ez37-field"><label class="ez37-label" for="fc-msg">Message<span class="ez37-req" aria-hidden="true">*</span></label><textarea class="ez37-textarea" id="fc-msg" required></textarea><p class="ez37-error">A short message helps us prepare.</p></div>
<div class="ez37-field"><label class="ez37-check"><input type="checkbox" required><span class="ez37-box"></span><span>I agree to be contacted about this enquiry.<span class="ez37-req" aria-hidden="true">*</span></span></label><p class="ez37-error">Please tick to continue.</p></div>
<div class="ez37-actions"><button type="submit" class="ez37-btn">Send message {{i:arrow}}</button><button type="reset" class="ez37-btn-ghost">Clear</button></div>
<div class="ez37-alert ez37-alert--success" role="status" data-ez37-success hidden>{{i:check-circle}}<div><b>Preview only.</b> The form is valid. On a real page this would submit to the enquiry handler.</div></div>
</form>
<div style="display:grid;gap:20px">
<div class="ez37-alert ez37-alert--info">{{i:info}}<div><b>Info.</b> Use for neutral guidance next to a form.</div></div>
<div class="ez37-alert ez37-alert--success">{{i:check-circle}}<div><b>Success.</b> Your message has been sent.</div></div>
<div class="ez37-alert ez37-alert--warn">{{i:alert}}<div><b>Heads up.</b> Replies usually arrive within one working day.</div></div>
<div class="ez37-alert ez37-alert--error">{{i:alert}}<div><b>Something went wrong.</b> Please try again in a moment.</div></div>
<form class="ez37-form" action="#" onsubmit="return false"><span class="ez37-label">Newsletter (inline form)</span><div class="ez37-inline"><div class="ez37-input-wrap">{{i:mail}}<input class="ez37-input" type="email" placeholder="Your email" aria-label="Email address"></div><button type="submit" class="ez37-btn">Subscribe</button></div></form>
<form class="ez37-form" action="#" onsubmit="return false"><span class="ez37-label">Search (inline form)</span><div class="ez37-inline"><div class="ez37-input-wrap">{{i:search}}<input class="ez37-input" type="search" placeholder="Search programmes" aria-label="Search"></div><button type="submit" class="ez37-btn-dark">Search</button></div></form>
</div>
</div>
<p class="ezx-note">Try submitting the contact form empty: required fields show their error message, then clear as you type. This is a preview; nothing is sent.</p>
HTML;
$forms = '<div class="ezh-wrap" id="ez37-forms"><div class="ezh-sec-head ezh-center"><div class="ezh-badge-row"><span class="ez37-badge">Forms</span></div><h2 class="ezh-h2">Forms <span class="ez37-hl">That Guide People</span></h2><p class="ezh-lead">Every control shares one border, radius and focus ring. Labels sit above fields, hints below, errors in words.</p></div>'
	. ezx_spec( 'Fields', 'ez37-input · -select · -textarea · -input-wrap · -dropzone · -range', $f_fields )
	. ezx_spec( 'Choices', 'ez37-check · -radio · -switch · -choice', $f_choices )
	. ezx_spec( 'States', '.is-error · .is-success · [disabled]', $f_states )
	. ezx_spec( 'Complete forms and messages', 'ez37-form · ez37-field · ez37-alert · ez37-inline', $f_contact )
	. '</div>';
$sections[] = ezx_section( $forms );

/* --------------------------------------------------------------------- tabs */
$tabs = <<<'HTML'
<div class="ezh-wrap" id="ez37-tabs">
<div class="ezh-sec-head ezh-center"><div class="ezh-badge-row"><span class="ez37-badge">Tabs</span></div><h2 class="ezh-h2">Interactive <span class="ez37-hl">Tabs</span></h2><p class="ezh-lead">Keyboard friendly: arrow keys move between tabs, Home and End jump to the first and last.</p></div>
<div class="ezx-spec"><p class="ezx-spec__label"><span>Underline tabs</span><code>ez37-tabs ez37-tabs--line</code></p>
<div class="ez37-tabs ez37-tabs--line" data-ez37-tabs>
<div class="ez37-tablist" role="tablist" aria-label="Programme types">
<button class="ez37-tab" role="tab" id="tl-t1" aria-controls="tl-p1" aria-selected="true">Coaching</button>
<button class="ez37-tab" role="tab" id="tl-t2" aria-controls="tl-p2" aria-selected="false">Assessments</button>
<button class="ez37-tab" role="tab" id="tl-t3" aria-controls="tl-p3" aria-selected="false">Learning</button></div>
<div class="ez37-tabpanel ezx-tabcopy" role="tabpanel" id="tl-p1" aria-labelledby="tl-t1" tabindex="0"><h4>Leadership and executive coaching</h4><p>One to one and group coaching that helps leaders decide, delegate and grow. Placeholder copy to be replaced.</p><a class="ez37-btn-link" href="#"><span>Explore coaching</span>{{i:arrow}}</a></div>
<div class="ez37-tabpanel ezx-tabcopy" role="tabpanel" id="tl-p2" aria-labelledby="tl-t2" tabindex="0" hidden><h4>Assessments that show the gap</h4><p>Psychometric and behavioural assessments reveal strengths, risks and development needs. Placeholder copy to be replaced.</p><a class="ez37-btn-link" href="#"><span>Explore assessments</span>{{i:arrow}}</a></div>
<div class="ez37-tabpanel ezx-tabcopy" role="tabpanel" id="tl-p3" aria-labelledby="tl-t3" tabindex="0" hidden><h4>Facilitating learning</h4><p>Facilitated programmes, training and certification for teams. Placeholder copy to be replaced.</p><a class="ez37-btn-link" href="#"><span>Explore learning</span>{{i:arrow}}</a></div>
</div></div>
<div class="ezx-spec"><p class="ezx-spec__label"><span>Pill tabs (switch audience or billing view)</span><code>ez37-tabs ez37-tabs--pill</code></p>
<div class="ez37-tabs ez37-tabs--pill" data-ez37-tabs>
<div class="ez37-tablist" role="tablist" aria-label="Audience">
<button class="ez37-tab" role="tab" id="tp-t1" aria-controls="tp-p1" aria-selected="true">For employers</button>
<button class="ez37-tab" role="tab" id="tp-t2" aria-controls="tp-p2" aria-selected="false">For employees</button></div>
<div class="ez37-tabpanel ezx-tabcopy" role="tabpanel" id="tp-p1" aria-labelledby="tp-t1" tabindex="0"><ul class="ezx-list"><li>{{i:check}}Comprehensive coaching solutions</li><li>{{i:check}}DISC assessments</li><li>{{i:check}}Tailored HR consulting and advisory</li></ul></div>
<div class="ez37-tabpanel ezx-tabcopy" role="tabpanel" id="tp-p2" aria-labelledby="tp-t2" tabindex="0" hidden><ul class="ezx-list"><li>{{i:check}}Mentor coaching</li><li>{{i:check}}One to one coaching</li><li>{{i:check}}Learning and development programmes</li></ul></div>
</div></div>
<div class="ezx-spec"><p class="ezx-spec__label"><span>Vertical tabs (long lists, FAQs by topic)</span><code>ez37-tabs ez37-tabs--vertical</code></p>
<div class="ez37-tabs ez37-tabs--vertical" data-ez37-tabs>
<div class="ez37-tablist" role="tablist" aria-label="Services" aria-orientation="vertical">
<button class="ez37-tab" role="tab" id="tv-t1" aria-controls="tv-p1" aria-selected="true">Mentor coaching</button>
<button class="ez37-tab" role="tab" id="tv-t2" aria-controls="tv-p2" aria-selected="false">Group and team coaching</button>
<button class="ez37-tab" role="tab" id="tv-t3" aria-controls="tv-p3" aria-selected="false">Coaching skills for managers</button>
<button class="ez37-tab" role="tab" id="tv-t4" aria-controls="tv-p4" aria-selected="false">Public sector solutions</button></div>
<div>
<div class="ez37-tabpanel ezx-tabcopy" role="tabpanel" id="tv-p1" aria-labelledby="tv-t1" tabindex="0"><h4>Mentor coaching</h4><p>Placeholder: who it is for, what happens in a session, and what changes afterwards.</p><a class="ez37-btn-soft ez37-btn--sm" href="#">Learn more</a></div>
<div class="ez37-tabpanel ezx-tabcopy" role="tabpanel" id="tv-p2" aria-labelledby="tv-t2" tabindex="0" hidden><h4>Group and team coaching</h4><p>Placeholder: how a team engagement is structured from kickoff to review.</p><a class="ez37-btn-soft ez37-btn--sm" href="#">Learn more</a></div>
<div class="ez37-tabpanel ezx-tabcopy" role="tabpanel" id="tv-p3" aria-labelledby="tv-t3" tabindex="0" hidden><h4>Coaching skills for managers</h4><p>Placeholder: the skills covered and how managers apply them at work.</p><a class="ez37-btn-soft ez37-btn--sm" href="#">Learn more</a></div>
<div class="ez37-tabpanel ezx-tabcopy" role="tabpanel" id="tv-p4" aria-labelledby="tv-t4" tabindex="0" hidden><h4>Public sector solutions</h4><p>Placeholder: programmes designed for public institutions.</p><a class="ez37-btn-soft ez37-btn--sm" href="#">Learn more</a></div>
</div></div></div>
</div>
HTML;
$sections[] = ezx_section( $tabs );

/* -------------------------------------------------------------------- icons */
$tiles = '';
foreach ( array_keys( ezh_icon_paths() ) as $name ) {
	$tiles .= '<button type="button" class="ez37-icontile" data-icon="' . esc_attr( $name ) . '" aria-label="Copy icon name ' . esc_attr( $name ) . '">{{i:' . $name . '}}<code>' . esc_html( $name ) . '</code></button>';
}
$icons = '<div class="ezh-wrap" id="ez37-icons"><div class="ezh-sec-head ezh-center"><div class="ezh-badge-row"><span class="ez37-badge">Icons</span></div><h2 class="ezh-h2">One <span class="ez37-hl">Icon Set</span></h2><p class="ezh-lead">' . count( ezh_icon_paths() ) . ' outline icons on a 24 px grid. They inherit the text colour, so one icon works in every tone.</p></div>'
	. '<div class="ezx-spec" data-ez37-icons><p class="ezx-spec__label"><span>Gallery</span><code>ezh_i(\'name\')</code></p>'
	. '<div class="ez37-field" style="max-width:360px;margin-bottom:20px"><label class="ez37-label" for="ic-filter">Filter icons</label><div class="ez37-input-wrap">{{i:search}}<input class="ez37-input" id="ic-filter" type="search" placeholder="Try: mail, arrow, user" data-ez37-icon-filter></div></div>'
	. '<div class="ez37-icongrid">' . $tiles . '</div><p class="ezx-live" role="status" aria-live="polite" data-ez37-icon-live></p><p class="ezx-note">Click an icon to copy its name. In page scripts use <code class="ezx-code">ezh_i( \'name\' )</code>; add new icons to <code class="ezx-code">bin/lib/ezh-lib.php</code>.</p></div>'
	. ezx_spec(
		'Sizes',
		'ez37-icon--xs · --sm · (22) · --lg · --xl',
		'<div class="ezx-row" style="gap:28px"><span class="ez37-icon ez37-icon--xs">{{i:star}}</span><span class="ez37-icon ez37-icon--sm">{{i:star}}</span><span class="ez37-icon">{{i:star}}</span><span class="ez37-icon ez37-icon--lg">{{i:star}}</span><span class="ez37-icon ez37-icon--xl">{{i:star}}</span></div>'
	)
	. ezx_spec(
		'Tone chips',
		'ezh-chipx · is-coral · is-amber · is-teal',
		'<div class="ezx-row"><span class="ezh-chipx">{{i:users}}</span><span class="ezh-chipx is-coral">{{i:alert}}</span><span class="ezh-chipx is-amber">{{i:trend}}</span><span class="ezh-chipx is-teal">{{i:check-circle}}</span></div><p class="ezx-note">Default is indigo. Use coral, amber and teal sparingly, for meaning (warning, growth, success), not decoration.</p>'
	)
	. ezx_spec(
		'Icon with text',
		'ezx-list',
		'<ul class="ezx-list"><li>{{i:check}}Included in every programme</li><li>{{i:clock}}Sessions of 60 to 90 minutes</li><li>{{i:map-pin}}Ibadan, Lagos or online</li><li>{{i:shield}}Confidential by default</li></ul>'
	)
	. '</div>';
$sections[] = ezx_section( $icons );

/* ------------------------------------------------------------------- tables */
$t_sched = <<<'HTML'
<div class="ez37-table-wrap"><table class="ez37-table ez37-table--striped ez37-table--stack">
<caption>Programme schedule (sample data)</caption>
<thead><tr><th scope="col">Programme</th><th scope="col">Format</th><th scope="col">Duration</th><th scope="col">Next intake</th><th scope="col">Status</th></tr></thead>
<tbody>
<tr><th scope="row">Professional Coach Certification</th><td data-label="Format">Cohort</td><td data-label="Duration">To be confirmed</td><td data-label="Next intake">To be confirmed</td><td data-label="Status"><span class="ez37-status ez37-status--success">Open</span></td></tr>
<tr><th scope="row">Advanced Coach Certification</th><td data-label="Format">Cohort</td><td data-label="Duration">To be confirmed</td><td data-label="Next intake">To be confirmed</td><td data-label="Status"><span class="ez37-status ez37-status--warn">Filling fast</span></td></tr>
<tr><th scope="row">Coaching Skills for Managers</th><td data-label="Format">Workshop</td><td data-label="Duration">To be confirmed</td><td data-label="Next intake">To be confirmed</td><td data-label="Status"><span class="ez37-status ez37-status--info">Waitlist</span></td></tr>
<tr><th scope="row">Mentor Coaching</th><td data-label="Format">One to one</td><td data-label="Duration">To be confirmed</td><td data-label="Next intake">On request</td><td data-label="Status"><span class="ez37-status ez37-status--neutral">Closed</span></td></tr>
</tbody></table></div>
<p class="ezx-note">On phones the rows turn into stacked cards (cells use <code class="ezx-code">data-label</code>). Dates and durations are placeholders.</p>
HTML;
$t_compare = <<<'HTML'
<div class="ez37-table-wrap"><table class="ez37-table">
<caption>What is included (sample comparison)</caption>
<thead><tr><th scope="col">Feature</th><th scope="col" class="is-center">Coaching</th><th scope="col" class="is-center is-featured">Assessments</th><th scope="col" class="is-center">Learning</th></tr></thead>
<tbody>
<tr><th scope="row">Discovery call</th><td class="is-center is-yes">{{i:check}}</td><td class="is-center is-featured is-yes">{{i:check}}</td><td class="is-center is-yes">{{i:check}}</td></tr>
<tr><th scope="row">Written report</th><td class="is-center is-no">{{i:minus}}</td><td class="is-center is-featured is-yes">{{i:check}}</td><td class="is-center is-no">{{i:minus}}</td></tr>
<tr><th scope="row">Facilitated sessions</th><td class="is-center is-yes">{{i:check}}</td><td class="is-center is-featured is-no">{{i:minus}}</td><td class="is-center is-yes">{{i:check}}</td></tr>
<tr><th scope="row">Certification</th><td class="is-center is-no">{{i:minus}}</td><td class="is-center is-featured is-no">{{i:minus}}</td><td class="is-center is-yes">{{i:check}}</td></tr>
</tbody></table></div>
HTML;
$t_data = <<<'HTML'
<div class="ez37-table-wrap"><table class="ez37-table ez37-table--compact">
<thead><tr><th scope="col">Metric</th><th scope="col" class="is-num">Before</th><th scope="col" class="is-num">After</th><th scope="col" class="is-num">Change</th></tr></thead>
<tbody>
<tr><td><b>Team cohesion</b></td><td class="is-num">00%</td><td class="is-num">00%</td><td class="is-num"><span class="ez37-status ez37-status--success">+00%</span></td></tr>
<tr><td><b>Psychological safety</b></td><td class="is-num">00%</td><td class="is-num">00%</td><td class="is-num"><span class="ez37-status ez37-status--success">+00%</span></td></tr>
<tr><td><b>Attrition</b></td><td class="is-num">00%</td><td class="is-num">00%</td><td class="is-num"><span class="ez37-status ez37-status--info">-00%</span></td></tr>
</tbody></table></div>
<p class="ezx-note">Compact table with right-aligned figures. All numbers are placeholders.</p>
HTML;
$tables = '<div class="ezh-wrap" id="ez37-tables"><div class="ezh-sec-head ezh-center"><div class="ezh-badge-row"><span class="ez37-badge">Tables</span></div><h2 class="ezh-h2">Tables <span class="ez37-hl">Made Readable</span></h2><p class="ezh-lead">Rounded, scrollable wrapper, clear headers, row hover, status pills and a stacked layout for phones.</p></div>'
	. ezx_spec( 'Data table with status pills', 'ez37-table--striped · --stack · ez37-status--*', $t_sched )
	. ezx_spec( 'Comparison table', 'is-center · is-featured · is-yes · is-no', $t_compare )
	. ezx_spec( 'Compact numeric table', 'ez37-table--compact · is-num', $t_data )
	. '</div>';
$sections[] = ezx_section( $tables );

/* ---------------------------------------------------------- section patterns */
$sections[] = ezx_section(
	'<div class="ezh-wrap" id="ez37-patterns"><div class="ezh-sec-head ezh-center" style="margin-bottom:0"><div class="ezh-badge-row"><span class="ez37-badge">Section patterns</span></div><h2 class="ezh-h2">Ready-Made <span class="ez37-hl">Page Sections</span></h2><p class="ezh-lead">Whole sections that can be dropped onto any page. They live in <code class="ezx-code">bin/lib/ezh-lib.php</code> and are called from the page scripts. Below: &ldquo;The challenge&rdquo;, kept out of the Home page for now.</p></div></div>'
);
$sections[] = ezh_section_problem( $contact );

/* ------------------------------------------------------------------- script */
$js = file_exists( dirname( __DIR__ ) . '/design/ez37-components.js' ) ? (string) file_get_contents( dirname( __DIR__ ) . '/design/ez37-components.js' ) : '';
if ( '' === $js ) {
	WP_CLI::warning( 'design/ez37-components.js not found; tabs, icon gallery and the form demo will not be interactive.' );
}
$sections[] = ezh_section( 'ez37-ext', '<script>' . $js . '</script>' );

/* --------------------------------------------------------------- write page */
$data = json_decode( (string) get_post_meta( $page->ID, '_elementor_data', true ), true );
if ( ! is_array( $data ) || ! isset( $data[0]['elements'] ) ) {
	WP_CLI::error( 'Components v2 has no Elementor data yet. Run bin/redesign-local.php first.' );
}
$kept = array();
foreach ( $data[0]['elements'] as $el ) {
	if ( false === strpos( (string) ( $el['settings']['css_classes'] ?? '' ), 'ez37-ext' ) ) {
		$kept[] = $el;
	}
}
$data[0]['elements'] = array_merge( $kept, $sections );

$css = '';
foreach ( array( 'ez37-redesign.css', 'ez37-home.css', 'ez37-components.css' ) as $f ) {
	$path = dirname( __DIR__ ) . '/design/' . $f;
	if ( file_exists( $path ) ) {
		$css .= file_get_contents( $path ) . "\n";
	} else {
		WP_CLI::warning( "design/$f not found; the page will be partly unstyled." );
	}
}
$settings               = get_post_meta( $page->ID, '_elementor_page_settings', true );
$settings               = is_array( $settings ) ? $settings : array();
$settings['custom_css'] = $css;

update_post_meta( $page->ID, '_elementor_data', wp_slash( wp_json_encode( $data ) ) );
update_post_meta( $page->ID, '_elementor_page_settings', wp_slash( $settings ) );
\Elementor\Plugin::$instance->files_manager->clear_cache();

WP_CLI::success( sprintf( 'Components v2 extended (%d original blocks kept, %d new): %s', count( $kept ), count( $sections ), get_permalink( $page->ID ) ) );
