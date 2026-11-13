/**
 * EZ37 Payments — success page polling.
 *
 * While the payment is "pending", ask the server every few seconds whether
 * Stripe's webhook has arrived. When the state changes, reload so the server
 * renders the final message. After the last attempt, show the "still
 * confirming, no need to pay again" note. Never decides anything itself.
 */
( function () {
	'use strict';

	var cfg = window.ez37PaymentStatus;
	var box = document.querySelector( '[data-ez37-status="pending"]' );

	if ( ! cfg || ! box || ! window.fetch ) {
		return;
	}

	var attempts = 0;
	var url = cfg.endpoint + ( cfg.endpoint.indexOf( '?' ) === -1 ? '?' : '&' ) +
		'session_id=' + encodeURIComponent( cfg.sessionId );

	function showSlowNotice() {
		var waiting = box.querySelector( '.ez37-status__waiting' );
		var slow = box.querySelector( '.ez37-status__slow' );
		if ( waiting ) {
			waiting.hidden = true;
		}
		if ( slow ) {
			slow.hidden = false;
		}
	}

	function check() {
		attempts++;

		window.fetch( url, { credentials: 'same-origin', cache: 'no-store' } )
			.then( function ( response ) {
				return response.ok ? response.json() : { state: 'pending' };
			} )
			.then( function ( data ) {
				if ( data && data.state && data.state !== 'pending' && data.state !== 'rate_limited' ) {
					window.location.reload();
					return;
				}
				next();
			} )
			.catch( next );
	}

	function next() {
		if ( attempts >= cfg.maxAttempts ) {
			showSlowNotice();
			return;
		}
		window.setTimeout( check, cfg.intervalMs );
	}

	window.setTimeout( check, cfg.intervalMs );
}() );
