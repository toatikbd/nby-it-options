/**
 * NBY IT Options — admin: keep the status select color in sync with its value.
 */
( function () {
	'use strict';
	var select = document.getElementById( 'nby-application-status' );
	if ( ! select ) {
		return;
	}
	select.addEventListener( 'change', function () {
		select.className = select.className.replace( /\bnby-status--[a-z-]+/g, '' ).trim() + ' nby-status--' + select.value;
	} );
} )();
