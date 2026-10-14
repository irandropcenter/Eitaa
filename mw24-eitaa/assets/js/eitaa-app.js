/**
 * Eitaa Connect — mini-app runtime.
 *
 * Vanilla ES5, no jQuery required. Loaded only in mini-app mode, after the official
 * SDK (https://developer.eitaa.com/eitaa-web-app.js) which is the first script in <head>.
 *
 * @package MW24_Eitaa
 */
( function ( window, document ) {
	'use strict';

	var cfg = window.MW24Eitaa || {};
	var app = cfg.app || {};
	var i18n = cfg.i18n || {};
	var page = cfg.page || {};
	var woo = cfg.woo || {};
	var login = cfg.login || {};
	var WA = window.Eitaa && window.Eitaa.WebApp ? window.Eitaa.WebApp : null;
	var PREFIX = 'mw24_eitaa_';
	var mainUrl = '';
	var secondaryUrl = '';
	var pendingRedirect = '';
	var phoneBusy = false;

	/* ------------------------------------------------------------------ helpers */

	function log() {
		if ( cfg.debug && window.console && window.console.log ) {
			var args = Array.prototype.slice.call( arguments );
			args.unshift( '[mw24-eitaa]' );
			window.console.log.apply( window.console, args );
		}
	}

	function safe( fn ) {
		try {
			return fn();
		} catch ( e ) {
			log( 'error', e );
			return undefined;
		}
	}

	var store = {
		get: function ( key ) {
			return safe( function () {
				return window.sessionStorage.getItem( PREFIX + key );
			} ) || null;
		},
		set: function ( key, value ) {
			safe( function () {
				window.sessionStorage.setItem( PREFIX + key, String( value ) );
			} );
		},
		remove: function ( key ) {
			safe( function () {
				window.sessionStorage.removeItem( PREFIX + key );
			} );
		}
	};

	function has( method ) {
		return !! WA && typeof WA[ method ] === 'function';
	}

	function call( method ) {
		var args = Array.prototype.slice.call( arguments, 1 );
		if ( has( method ) ) {
			return safe( function () {
				return WA[ method ].apply( WA, args );
			} );
		}
		return undefined;
	}

	function format( text, value ) {
		return String( text || '' ).replace( '%d', String( value ) ).replace( '%s', String( value ) );
	}

	function toggleClass( el, name, on ) {
		if ( ! el ) {
			return;
		}
		if ( on ) {
			el.classList.add( name );
		} else {
			el.classList.remove( name );
		}
	}

	function closest( el, selector ) {
		while ( el && el.nodeType === 1 ) {
			var matches = el.matches || el.msMatchesSelector || el.webkitMatchesSelector;
			if ( matches && matches.call( el, selector ) ) {
				return el;
			}
			el = el.parentNode;
		}
		return null;
	}

	function navigate( url, replace ) {
		if ( ! url ) {
			return;
		}
		if ( replace ) {
			window.location.replace( url );
		} else {
			window.location.href = url;
		}
	}

	function alertMsg( message ) {
		if ( ! message ) {
			return;
		}
		if ( has( 'showAlert' ) ) {
			call( 'showAlert', String( message ) );
		} else {
			window.alert( message );
		}
	}

	function haptic( kind ) {
		if ( ! app.haptic || ! WA || ! WA.HapticFeedback ) {
			return;
		}
		safe( function () {
			if ( kind === 'success' || kind === 'error' || kind === 'warning' ) {
				WA.HapticFeedback.notificationOccurred( kind );
			} else {
				WA.HapticFeedback.impactOccurred( kind || 'light' );
			}
		} );
	}

	function queryParam( name ) {
		var match = new RegExp( '[?&]' + name + '=([^&#]*)' ).exec( window.location.search );
		return match ? decodeURIComponent( match[ 1 ].replace( /\+/g, ' ' ) ) : '';
	}

	/* ------------------------------------------------------------------ Eitaa data */

	/**
	 * Signed initData. Cached in sessionStorage so later pages of the same WebView
	 * session can still authenticate even if the SDK does not persist it.
	 */
	function initData() {
		var data = WA && WA.initData ? String( WA.initData ) : '';
		if ( data ) {
			store.set( 'init', data );
			return data;
		}
		return store.get( 'init' ) || '';
	}

	function unsafeData() {
		return ( WA && WA.initDataUnsafe ) || {};
	}

	function sessionKey() {
		var match = /(?:^|&)hash=([a-fA-F0-9]+)/.exec( initData() );
		return match ? match[ 1 ].slice( 0, 16 ) : 'none';
	}

	/* ------------------------------------------------------------------ REST */

	function request( method, path, data, done ) {
		var xhr = new window.XMLHttpRequest();
		xhr.open( method, cfg.restUrl + path, true );
		xhr.setRequestHeader( 'Accept', 'application/json' );
		xhr.setRequestHeader( 'X-Eitaa-Init-Data', initData() );
		if ( cfg.nonce ) {
			xhr.setRequestHeader( 'X-WP-Nonce', cfg.nonce );
		}
		if ( method !== 'GET' ) {
			xhr.setRequestHeader( 'Content-Type', 'application/json' );
		}
		xhr.withCredentials = true;
		xhr.timeout = 20000;
		xhr.onreadystatechange = function () {
			if ( xhr.readyState !== 4 ) {
				return;
			}
			var json = null;
			safe( function () {
				json = JSON.parse( xhr.responseText );
			} );
			if ( xhr.status >= 200 && xhr.status < 300 && json ) {
				done( null, json );
				return;
			}
			var error = new Error( json && json.message ? json.message : ( xhr.status ? i18n.loginFailed : i18n.networkError ) );
			error.code = json && json.code ? json.code : '';
			error.status = xhr.status;
			done( error, json );
		};
		xhr.ontimeout = function () {
			done( new Error( i18n.networkError ), null );
		};
		xhr.send( method === 'GET' ? null : JSON.stringify( data || {} ) );
	}

	/* ------------------------------------------------------------------ appearance */

	function applyTheme() {
		var dark = WA && WA.colorScheme === 'dark';
		[ document.documentElement, document.body ].forEach( function ( el ) {
			toggleClass( el, 'mw24-eitaa-app', true );
			toggleClass( el, 'mw24-eitaa-dark', dark );
			toggleClass( el, 'mw24-eitaa-light', ! dark );
		} );
		var colors = app.colors || {};
		if ( colors.header ) {
			call( 'setHeaderColor', colors.header );
		}
		if ( colors.background ) {
			call( 'setBackgroundColor', colors.background );
		}
		if ( colors.bottomBar ) {
			call( 'setBottomBarColor', colors.bottomBar );
		}
	}

	function applyFullscreenClass() {
		toggleClass( document.body, 'mw24-eitaa-fullscreen', !! ( WA && WA.isFullscreen ) );
	}

	function setupWindow() {
		call( 'ready' );
		if ( app.expand ) {
			call( 'expand' );
		}
		if ( app.fullscreen ) {
			if ( has( 'requestFullscreen' ) ) {
				call( 'requestFullscreen' );
			} else {
				call( 'requestFullScreen' );
			}
		}
		if ( app.disableVerticalSwipes ) {
			call( 'disableVerticalSwipes' );
		}
		applyTheme();
		applyFullscreenClass();
		call( 'onEvent', 'themeChanged', applyTheme );
		call( 'onEvent', 'fullscreenChanged', applyFullscreenClass );
	}

	/* ------------------------------------------------------------------ buttons */

	function setupBackButton() {
		var back = WA && WA.BackButton;
		if ( ! back ) {
			return;
		}
		var show = app.backButton && ! page.isEntry && window.history.length > 1;
		if ( ! show ) {
			safe( function () {
				back.hide();
			} );
			return;
		}
		safe( function () {
			back.onClick( function () {
				haptic( 'light' );
				if ( window.history.length > 1 ) {
					window.history.back();
				} else {
					navigate( page.home );
				}
			} );
			back.show();
		} );
	}

	function mainButtonState() {
		var mb = app.mainButton || {};
		if ( ! mb.enabled ) {
			return null;
		}
		if ( mb.woo && woo.active ) {
			if ( page.isCheckout ) {
				return null;
			}
			if ( page.isCart ) {
				return woo.cartCount > 0 ? { text: i18n.checkout, url: woo.checkoutUrl } : null;
			}
			if ( woo.cartCount > 0 ) {
				return { text: format( i18n.cart, woo.cartCount ), url: woo.cartUrl };
			}
		}
		if ( mb.text && mb.url ) {
			return { text: mb.text, url: mb.url };
		}
		return null;
	}

	function renderMainButton() {
		var button = WA && WA.MainButton;
		if ( ! button ) {
			return;
		}
		var state = mainButtonState();
		var mb = app.mainButton || {};
		if ( ! state ) {
			mainUrl = '';
			safe( function () {
				button.hide();
			} );
			return;
		}
		mainUrl = state.url;
		safe( function () {
			if ( typeof button.setParams === 'function' ) {
				var params = { text: state.text, is_visible: true, is_active: true };
				if ( mb.color ) {
					params.color = mb.color;
				}
				if ( mb.textColor ) {
					params.text_color = mb.textColor;
				}
				button.setParams( params );
			} else {
				button.setText( state.text );
			}
			button.show();
		} );
	}

	function setupButtons() {
		var main = WA && WA.MainButton;
		if ( main ) {
			safe( function () {
				main.onClick( function () {
					haptic( 'light' );
					navigate( mainUrl );
				} );
			} );
		}
		renderMainButton();

		var second = WA && WA.SecondaryButton;
		var sb = app.secondaryButton || {};
		if ( second ) {
			safe( function () {
				if ( sb.enabled && sb.text && sb.url ) {
					secondaryUrl = sb.url;
					second.onClick( function () {
						haptic( 'light' );
						navigate( secondaryUrl );
					} );
					if ( typeof second.setParams === 'function' ) {
						second.setParams( { text: sb.text, is_visible: true, position: sb.position || 'left' } );
					} else {
						second.setText( sb.text );
					}
					second.show();
				} else {
					second.hide();
				}
			} );
		}

		var settings = WA && WA.SettingsButton;
		var st = app.settingsButton || {};
		if ( settings ) {
			safe( function () {
				if ( st.enabled && st.url ) {
					settings.onClick( function () {
						haptic( 'light' );
						navigate( st.url );
					} );
					settings.show();
				} else {
					settings.hide();
				}
			} );
		}
		setupBackButton();
	}

	function updateClosingConfirmation() {
		var mode = app.closingConfirmation;
		var on = mode === 'always' || ( mode === 'cart' && woo.active && woo.cartCount > 0 );
		call( on ? 'enableClosingConfirmation' : 'disableClosingConfirmation' );
	}

	/* ------------------------------------------------------------------ WooCommerce cart */

	function setCartCount( count ) {
		count = parseInt( count, 10 );
		if ( isNaN( count ) || count === woo.cartCount ) {
			return;
		}
		woo.cartCount = count;
		renderMainButton();
		updateClosingConfirmation();
		log( 'cart count', count );
	}

	function readCartFromDom() {
		var el = document.querySelector( '.mw24-eitaa-cart-count' );
		if ( el ) {
			setCartCount( el.getAttribute( 'data-count' ) );
		}
	}

	function readCartFromFragments( fragments ) {
		var html = fragments && fragments[ 'span.mw24-eitaa-cart-count' ];
		var match = html ? /data-count="(\d+)"/.exec( html ) : null;
		if ( match ) {
			setCartCount( match[ 1 ] );
			return true;
		}
		return false;
	}

	function readCartFromStoreApi() {
		if ( ! woo.storeCartUrl ) {
			return;
		}
		var xhr = new window.XMLHttpRequest();
		xhr.open( 'GET', woo.storeCartUrl, true );
		xhr.withCredentials = true;
		xhr.onreadystatechange = function () {
			if ( xhr.readyState === 4 && xhr.status === 200 ) {
				safe( function () {
					var data = JSON.parse( xhr.responseText );
					if ( data && typeof data.items_count !== 'undefined' ) {
						setCartCount( data.items_count );
					}
				} );
			}
		};
		xhr.send( null );
	}

	function setupCart() {
		updateClosingConfirmation();
		if ( ! woo.active ) {
			return;
		}
		if ( window.jQuery ) {
			window.jQuery( document.body ).on( 'added_to_cart removed_from_cart', function ( event, fragments ) {
				if ( ! readCartFromFragments( fragments ) ) {
					readCartFromStoreApi();
				}
			} );
			window.jQuery( document.body ).on( 'wc_fragments_refreshed wc_fragments_loaded updated_cart_totals', function () {
				window.setTimeout( readCartFromDom, 50 );
			} );
		}
		document.body.addEventListener( 'wc-blocks_added_to_cart', readCartFromStoreApi );
		document.body.addEventListener( 'wc-blocks_removed_from_cart', readCartFromStoreApi );
	}

	/* ------------------------------------------------------------------ phone bar */

	function bar() {
		return document.getElementById( 'mw24-eitaa-phone-bar' );
	}

	function setBarBusy( busy, text ) {
		var el = bar();
		if ( ! el ) {
			return;
		}
		var status = el.querySelector( '[data-mw24-eitaa-status]' );
		var buttons = el.querySelectorAll( 'button' );
		for ( var i = 0; i < buttons.length; i++ ) {
			buttons[ i ].disabled = !! busy;
		}
		if ( status ) {
			status.textContent = busy ? ( text || i18n.loggingIn ) : '';
			status.hidden = ! busy;
		}
	}

	function showBar() {
		var el = bar();
		if ( ! el ) {
			return;
		}
		el.hidden = false;
		toggleClass( document.body, 'mw24-eitaa-bar-open', true );
	}

	function hideBar() {
		var el = bar();
		if ( el ) {
			el.hidden = true;
		}
		toggleClass( document.body, 'mw24-eitaa-bar-open', false );
	}

	function shouldPrompt() {
		if ( login.prompt === 'button' || store.get( 'later' ) === '1' ) {
			return false;
		}
		if ( login.prompt === 'cart_checkout' ) {
			return !! ( page.isCart || page.isCheckout );
		}
		return true;
	}

	function maybePrompt() {
		if ( ! shouldPrompt() ) {
			return;
		}
		if ( login.direct || ! bar() ) {
			if ( store.get( 'asked_' + sessionKey() ) !== '1' ) {
				store.set( 'asked_' + sessionKey(), '1' );
				requestPhone();
			}
			return;
		}
		showBar();
	}

	/* ------------------------------------------------------------------ login flow */

	function afterLogin( json ) {
		haptic( 'success' );
		hideBar();
		store.set( 'session_' + sessionKey(), 'done' );
		var target = pendingRedirect || ( json && json.redirect ) || page.redirectTo || '';
		document.dispatchEvent( new window.CustomEvent( 'mw24-eitaa:login', { detail: json } ) );
		if ( target ) {
			navigate( target, true );
		} else {
			window.location.reload();
		}
	}

	function sendContact( response ) {
		setBarBusy( true );
		request( 'POST', 'contact', { contact: response }, function ( error, json ) {
			phoneBusy = false;
			if ( error ) {
				setBarBusy( false );
				haptic( 'error' );
				alertMsg( error.message );
				return;
			}
			if ( json && json.status === 'ok' ) {
				afterLogin( json );
			} else {
				setBarBusy( false );
			}
		} );
	}

	/**
	 * Opens Eitaa's native phone sharing dialog and posts the signed response.
	 * Handles both callback shapes: (isOk, "contact=...") and (isOk, { response: "contact=..." }),
	 * and the contactRequested event as a fallback.
	 */
	function requestPhone() {
		if ( ! has( 'requestContact' ) ) {
			alertMsg( i18n.notSupported );
			return;
		}
		if ( phoneBusy ) {
			return;
		}
		phoneBusy = true;
		var handled = false;

		function handle( response ) {
			if ( handled ) {
				return;
			}
			handled = true;
			call( 'offEvent', 'contactRequested', onEvent );
			if ( ! response ) {
				phoneBusy = false;
				setBarBusy( false );
				log( 'contact not shared' );
				return;
			}
			sendContact( response );
		}

		function onEvent( event ) {
			if ( event && event.status === 'cancelled' ) {
				handle( null );
			} else if ( event && typeof event.response === 'string' && event.response ) {
				handle( event.response );
			}
		}

		call( 'onEvent', 'contactRequested', onEvent );
		call( 'requestContact', function ( isOk, data ) {
			var response = '';
			if ( typeof data === 'string' ) {
				response = data;
			} else if ( data && typeof data.response === 'string' ) {
				response = data.response;
			}
			if ( isOk && response ) {
				handle( response );
			} else if ( isOk ) {
				window.setTimeout( function () {
					handle( null );
				}, 2500 );
			} else {
				handle( null );
			}
		} );
	}

	function maybeRequestWriteAccess() {
		var user = unsafeData().user || {};
		if ( ! login.requestWriteAccess || user.allows_write_to_pm || ! has( 'requestWriteAccess' ) ) {
			return;
		}
		if ( store.get( 'write_asked' ) === '1' ) {
			return;
		}
		store.set( 'write_asked', '1' );
		call( 'requestWriteAccess', function ( granted ) {
			if ( granted ) {
				request( 'POST', 'session', { write_access: true }, function () {} );
			}
		} );
	}

	function startSession( force ) {
		var key = 'session_' + sessionKey();
		var state = store.get( key );
		if ( ! force && state === 'done' ) {
			if ( cfg.user && cfg.user.loggedIn ) {
				maybeRequestWriteAccess();
			}
			return;
		}
		if ( ! force && state === 'need_phone' ) {
			maybePrompt();
			return;
		}
		request( 'POST', 'session', {}, function ( error, json ) {
			if ( error ) {
				log( 'session error', error.code, error.message );
				if ( force ) {
					haptic( 'error' );
					alertMsg( error.message );
				}
				return;
			}
			log( 'session', json );
			if ( json.status === 'ok' ) {
				if ( json.reload ) {
					afterLogin( json );
					return;
				}
				store.set( key, 'done' );
				if ( json.message && force ) {
					alertMsg( json.message );
				} else if ( json.new_link && force ) {
					alertMsg( i18n.linked );
				}
				maybeRequestWriteAccess();
			} else if ( json.status === 'need_phone' ) {
				store.set( key, 'need_phone' );
				if ( force ) {
					requestPhone();
				} else {
					maybePrompt();
				}
			}
		} );
	}

	function bindUi() {
		document.addEventListener( 'click', function ( event ) {
			var target = event.target;
			var loginButton = closest( target, '[data-mw24-eitaa-login]' );
			if ( loginButton ) {
				event.preventDefault();
				haptic( 'light' );
				pendingRedirect = loginButton.getAttribute( 'data-redirect' ) || '';
				startSession( true );
				return;
			}
			if ( closest( target, '[data-mw24-eitaa-share]' ) ) {
				event.preventDefault();
				haptic( 'light' );
				requestPhone();
				return;
			}
			if ( closest( target, '[data-mw24-eitaa-later]' ) ) {
				event.preventDefault();
				store.set( 'later', '1' );
				hideBar();
			}
		} );
	}

	/* ------------------------------------------------------------------ routing */

	function routeStartParam( done ) {
		if ( store.get( 'routed' ) === '1' ) {
			done( false );
			return;
		}
		store.set( 'routed', '1' );
		var param = unsafeData().start_param || queryParam( 'tgWebAppStartParam' );
		if ( ! param ) {
			done( false );
			return;
		}
		if ( cfg.startRoute ) {
			if ( cfg.startRoute === window.location.href ) {
				done( false );
				return;
			}
			navigate( cfg.startRoute, true );
			done( true );
			return;
		}
		var separator = String( cfg.restUrl ).indexOf( '?' ) === -1 ? '?' : '&';
		request( 'GET', 'route' + separator + 'start=' + encodeURIComponent( param ), null, function ( error, json ) {
			if ( ! error && json && json.url && json.url !== window.location.href ) {
				navigate( json.url, true );
				done( true );
			} else {
				done( false );
			}
		} );
	}

	/* ------------------------------------------------------------------ boot */

	function leaveAppMode() {
		toggleClass( document.documentElement, 'mw24-eitaa-app', false );
		toggleClass( document.body, 'mw24-eitaa-app', false );
		var cookie = cfg.cookie || {};
		if ( cookie.name ) {
			document.cookie = cookie.name + '=; Max-Age=0; path=' + ( cookie.path || '/' );
		}
		log( 'Not inside Eitaa: mini-app mode switched off.' );
	}

	function boot() {
		if ( ! WA || ! initData() ) {
			leaveAppMode();
			return;
		}
		setupWindow();
		setupButtons();
		setupCart();
		bindUi();
		window.MW24EitaaApp = {
			requestPhone: requestPhone,
			login: function () {
				startSession( true );
			},
			isInEitaa: function () {
				return true;
			}
		};
		document.dispatchEvent( new window.CustomEvent( 'mw24-eitaa:ready', { detail: { version: cfg.version } } ) );
		routeStartParam( function ( navigated ) {
			if ( navigated || ! login.enabled ) {
				return;
			}
			startSession( false );
		} );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}
}( window, document ) );
