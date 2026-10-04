/**
 * NBY IT Options — application form enhancements (no dependencies).
 * Client-side checks are for convenience only; the server validates everything.
 */
( function () {
	'use strict';

	var config = window.nbyApplication || { maxBytes: 0, types: [], i18n: {} };
	var i18n = config.i18n || {};

	function fieldWrap( input ) {
		return input.closest( '.nby-field' );
	}

	function setError( input, message ) {
		var wrap = fieldWrap( input );
		var error = wrap ? wrap.querySelector( '[data-nby-error]' ) : null;
		if ( wrap ) {
			wrap.classList.toggle( 'has-error', !! message );
		}
		if ( error ) {
			error.textContent = message || '';
		}
		if ( message ) {
			input.setAttribute( 'aria-invalid', 'true' );
		} else {
			input.removeAttribute( 'aria-invalid' );
		}
	}

	function isUrl( value ) {
		if ( ! /^https?:\/\//i.test( value ) ) {
			value = 'https://' + value;
		}
		try {
			var url = new URL( value );
			return url.hostname.indexOf( '.' ) > 0;
		} catch ( e ) {
			return false;
		}
	}

	function validate( input ) {
		var value = input.type === 'file' || input.type === 'checkbox' ? '' : input.value.trim();

		if ( input.type === 'checkbox' ) {
			return input.required && ! input.checked ? i18n.consent : '';
		}

		if ( input.type === 'file' ) {
			if ( ! input.files || ! input.files.length ) {
				return input.required ? i18n.required : '';
			}
			var file = input.files[ 0 ];
			var ext = ( file.name.split( '.' ).pop() || '' ).toLowerCase();
			if ( config.types.indexOf( ext ) === -1 ) {
				return i18n.fileType + ' (' + config.types.join( ', ' ).toUpperCase() + ')';
			}
			if ( config.maxBytes && file.size > config.maxBytes ) {
				return i18n.fileSize;
			}
			return '';
		}

		if ( input.required && ! value ) {
			return i18n.required;
		}
		if ( value && input.type === 'email' && ! /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test( value ) ) {
			return i18n.email;
		}
		if ( value && input.type === 'url' && ! isUrl( value ) ) {
			return i18n.url;
		}
		return '';
	}

	function init( form ) {
		var inputs = form.querySelectorAll( '.nby-field input, .nby-field textarea' );
		var submit = form.querySelector( '[data-nby-submit]' );
		var container = form.closest( '.nby-apply' );
		var summary = container ? container.querySelector( '[data-nby-summary]' ) : null;

		// Server-side errors: move focus to the summary so screen readers announce it.
		if ( summary && ! summary.hidden ) {
			summary.focus();
		}

		Array.prototype.forEach.call( inputs, function ( input ) {
			var event = input.type === 'file' || input.type === 'checkbox' ? 'change' : 'blur';
			input.addEventListener( event, function () {
				setError( input, validate( input ) );
			} );
		} );

		form.addEventListener( 'submit', function ( e ) {
			if ( form.dataset.submitting === '1' ) {
				e.preventDefault();
				return;
			}

			var firstInvalid = null;
			Array.prototype.forEach.call( inputs, function ( input ) {
				var message = validate( input );
				setError( input, message );
				if ( message && ! firstInvalid ) {
					firstInvalid = input;
				}
			} );

			if ( firstInvalid ) {
				e.preventDefault();
				if ( summary ) {
					summary.hidden = false;
					summary.innerHTML = '';
					var p = document.createElement( 'p' );
					p.textContent = i18n.summary;
					summary.appendChild( p );
				}
				firstInvalid.focus();
				return;
			}

			// Prevent accidental double submissions.
			form.dataset.submitting = '1';
			if ( submit ) {
				submit.classList.add( 'is-loading' );
				submit.setAttribute( 'aria-disabled', 'true' );
				submit.textContent = i18n.submitting;
			}
		} );
	}

	function ready() {
		Array.prototype.forEach.call( document.querySelectorAll( '[data-nby-form]' ), init );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', ready );
	} else {
		ready();
	}
} )();
