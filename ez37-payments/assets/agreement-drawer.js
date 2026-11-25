/**
 * EZ37 Payments — agreement drawer.
 *
 * Clicking the agreement tick (or the "read the agreement" link) opens a panel
 * fixed to the right of the screen with the full agreement. "I have read and
 * agree" ticks the box and closes it. Closing without agreeing leaves the box
 * unticked. The server still checks the tick and the agreement version on
 * submit, so this script only improves the experience; it decides nothing.
 *
 * Without JavaScript the link jumps to the drawer (CSS :target) and the
 * checkbox can be ticked normally.
 */
( function () {
	'use strict';

	var drawer = document.querySelector( '[data-ez37-drawer]' );
	var check = document.querySelector( '[data-ez37-agreement]' );

	if ( ! drawer || ! check ) {
		return;
	}

	var backdrop = document.querySelector( '[data-ez37-drawer-backdrop]' );
	var returnFocus = null;

	// Fixed positioning must not be trapped by a transformed page container.
	document.body.appendChild( drawer );
	if ( backdrop ) {
		document.body.appendChild( backdrop );
	}

	function open( trigger ) {
		returnFocus = trigger || check;
		drawer.classList.add( 'is-open' );
		if ( backdrop ) {
			backdrop.hidden = false;
		}
		document.documentElement.classList.add( 'ez37-drawer-lock' );
		drawer.focus();
		drawer.querySelector( '.ez37-drawer__body' ).scrollTop = 0;
	}

	function close() {
		drawer.classList.remove( 'is-open' );
		if ( backdrop ) {
			backdrop.hidden = true;
		}
		document.documentElement.classList.remove( 'ez37-drawer-lock' );
		if ( returnFocus ) {
			returnFocus.focus();
		}
	}

	// Ticking the box opens the agreement first; unticking works normally.
	check.addEventListener( 'click', function ( event ) {
		if ( check.checked ) {
			event.preventDefault();
			check.checked = false;
			open( check );
		}
	} );

	document.querySelectorAll( '[data-ez37-agreement-open]' ).forEach( function ( link ) {
		link.addEventListener( 'click', function ( event ) {
			event.preventDefault();
			open( link );
		} );
	} );

	drawer.querySelectorAll( '[data-ez37-drawer-close]' ).forEach( function ( el ) {
		el.addEventListener( 'click', function ( event ) {
			event.preventDefault();
			close();
		} );
	} );

	drawer.querySelector( '[data-ez37-drawer-accept]' ).addEventListener( 'click', function () {
		check.checked = true;
		close();
	} );

	if ( backdrop ) {
		backdrop.addEventListener( 'click', close );
	}

	document.addEventListener( 'keydown', function ( event ) {
		if ( 'Escape' === event.key && drawer.classList.contains( 'is-open' ) ) {
			close();
		}
	} );
}() );
