/* Behavioural tests of assets/js/eitaa-app.js with a mocked Eitaa SDK (jsdom). Run: cd tests/js && npm install && npm test */
const { JSDOM } = require( 'jsdom' );
const path = require( 'path' );
const fs = require( 'fs' );
const SRC = fs.readFileSync( path.join( __dirname, '../../assets/js/eitaa-app.js' ), 'utf8' );
let pass = 0, fail = 0;
function check( label, cond, extra ) {
	if ( cond ) { pass++; console.log( '  ok   - ' + label ); } else { fail++; console.log( '  FAIL - ' + label, extra !== undefined ? JSON.stringify( extra ) : '' ); }
}

function makeSdk( opts ) {
	const calls = [];
	const events = {};
	const btn = ( name ) => ( {
		isVisible: false, params: null, handlers: [],
		show() { this.isVisible = true; calls.push( name + '.show' ); },
		hide() { this.isVisible = false; calls.push( name + '.hide' ); },
		setParams( p ) { this.params = Object.assign( {}, this.params, p ); if ( p.is_visible ) this.isVisible = true; calls.push( name + '.setParams' ); },
		setText( t ) { this.text = t; },
		onClick( cb ) { this.handlers.push( cb ); },
		click() { this.handlers.forEach( ( h ) => h() ); },
	} );
	const sdk = {
		initData: opts.initData || '',
		initDataUnsafe: opts.unsafe || { user: { id: 1, allows_write_to_pm: true } },
		colorScheme: opts.scheme || 'light',
		isFullscreen: false,
		themeParams: {},
		ready() { calls.push( 'ready' ); },
		expand() { calls.push( 'expand' ); },
		requestFullScreen() { calls.push( 'requestFullScreen' ); this.isFullscreen = true; ( events.fullscreenChanged || [] ).forEach( ( h ) => h() ); },
		disableVerticalSwipes() { calls.push( 'disableVerticalSwipes' ); },
		setHeaderColor( c ) { calls.push( 'setHeaderColor:' + c ); },
		setBackgroundColor( c ) { calls.push( 'setBackgroundColor:' + c ); },
		setBottomBarColor( c ) { calls.push( 'setBottomBarColor:' + c ); },
		enableClosingConfirmation() { calls.push( 'enableClosingConfirmation' ); },
		disableClosingConfirmation() { calls.push( 'disableClosingConfirmation' ); },
		showAlert( m ) { calls.push( 'alert:' + m ); },
		onEvent( e, h ) { ( events[ e ] = events[ e ] || [] ).push( h ); },
		offEvent( e, h ) { events[ e ] = ( events[ e ] || [] ).filter( ( x ) => x !== h ); },
		requestContact( cb ) { calls.push( 'requestContact' ); sdk._contactCb = cb; },
		requestWriteAccess( cb ) { calls.push( 'requestWriteAccess' ); sdk._writeCb = cb; },
		HapticFeedback: { impactOccurred( s ) { calls.push( 'haptic:' + s ); }, notificationOccurred( s ) { calls.push( 'haptic:' + s ); } },
		MainButton: btn( 'Main' ), SecondaryButton: btn( 'Secondary' ), BackButton: btn( 'Back' ), SettingsButton: btn( 'Settings' ),
	};
	return { sdk, calls, events };
}

function setup( opts ) {
	const html = '<!doctype html><html><head></head><body>' + ( opts.body || '' ) + '</body></html>';
	const dom = new JSDOM( html, { url: opts.url || 'https://shop.test/?mw24_eitaa=1', runScripts: 'outside-only', pretendToBeVisual: true } );
	const w = dom.window;
	const { sdk, calls, events } = makeSdk( opts );
	if ( ! opts.noSdk ) { w.Eitaa = { WebApp: sdk }; }
	const requests = [];
	const responder = opts.responder || ( () => ( { status: 200, body: {} } ) );
	w.XMLHttpRequest = function () {
		const xhr = { headers: {}, readyState: 0 };
		xhr.open = ( m, u ) => { xhr.method = m; xhr.url = u; };
		xhr.setRequestHeader = ( k, v ) => { xhr.headers[ k ] = v; };
		xhr.send = ( body ) => {
			requests.push( { method: xhr.method, url: xhr.url, headers: xhr.headers, body: body ? JSON.parse( body ) : null } );
			const r = responder( xhr.method, xhr.url, body ? JSON.parse( body ) : null, xhr.headers );
			setTimeout( () => { xhr.status = r.status; xhr.responseText = JSON.stringify( r.body ); xhr.readyState = 4; xhr.onreadystatechange && xhr.onreadystatechange(); }, 1 );
		};
		return xhr;
	};
	let reloaded = 0, navigated = [];
	w.MW24Eitaa = Object.assign( {
		version: '1.0.0', restUrl: 'https://shop.test/wp-json/mw24-eitaa/v1/', nonce: '', debug: false,
		cookie: { name: 'mw24_eitaa_app', path: '/' },
		user: { loggedIn: false, linked: false },
		page: { isEntry: true, isLogin: false, isCart: false, isCheckout: false, home: 'https://shop.test/', redirectTo: '' },
		startRoute: '',
		app: { expand: true, fullscreen: true, backButton: true, haptic: true, disableVerticalSwipes: true, safeArea: true, closingConfirmation: 'cart',
			colors: { header: '#112233', background: '', bottomBar: '' },
			mainButton: { enabled: true, woo: true, text: '', url: '', color: '', textColor: '' },
			secondaryButton: { enabled: false, text: '', url: '', position: 'left' },
			settingsButton: { enabled: true, url: 'https://shop.test/my-account/' } },
		woo: { active: true, cartCount: 0, cartUrl: 'https://shop.test/cart/', checkoutUrl: 'https://shop.test/checkout/', storeCartUrl: 'https://shop.test/wp-json/wc/store/v1/cart' },
		login: { enabled: true, prompt: 'immediate', direct: false, requestWriteAccess: true, redirect: '' },
		i18n: { cart: 'Cart (%d)', checkout: 'Continue to checkout', loggingIn: 'Logging in', loginFailed: 'failed', networkError: 'net', notSupported: 'ns', linked: 'linked' },
	}, opts.cfg || {} );
	// Stub navigation (jsdom does not implement it).
	w.__nav = navigated;
	const patched = SRC.replace( /window\.location\.reload\(\)/g, 'window.__reload()' ).replace( /window\.location\.replace\( url \)/g, 'window.__nav.push( url )' ).replace( /window\.location\.href = url/g, 'window.__nav.push( url )' );
	w.__reload = () => { reloaded++; };
	w.eval( patched );
	return { w, sdk, calls, events, requests, get reloaded() { return reloaded; }, navigated };
}
const tick = ( ms = 20 ) => new Promise( ( r ) => setTimeout( r, ms ) );
const INIT = 'auth_date=1&user=%7B%22id%22%3A1%7D&hash=' + 'a'.repeat( 64 );

( async () => {
	console.log( '== outside Eitaa' );
	{
		const t = setup( { noSdk: true } );
		await tick();
		check( 'no requests when SDK missing', t.requests.length === 0 );
		check( 'app class removed', ! t.w.document.documentElement.classList.contains( 'mw24-eitaa-app' ) );
	}
	{
		const t = setup( { initData: '' } );
		await tick();
		check( 'no initData -> leaves app mode', t.requests.length === 0 && ! t.calls.includes( 'ready' ) );
	}

	console.log( '== boot & window setup' );
	const bar = '<div id="mw24-eitaa-phone-bar" hidden><button data-mw24-eitaa-share>Share</button><button data-mw24-eitaa-later>Later</button><p data-mw24-eitaa-status hidden></p></div>';
	{
		const t = setup( { initData: INIT, body: bar, responder: () => ( { status: 200, body: { status: 'need_phone' } } ) } );
		await tick();
		check( 'ready() called', t.calls.includes( 'ready' ) );
		check( 'expand() + requestFullScreen fallback + swipes', t.calls.includes( 'expand' ) && t.calls.includes( 'requestFullScreen' ) && t.calls.includes( 'disableVerticalSwipes' ) );
		check( 'header color applied, empty colors skipped', t.calls.includes( 'setHeaderColor:#112233' ) && ! t.calls.some( ( c ) => c.startsWith( 'setBackgroundColor' ) ) );
		check( 'body classes', t.w.document.body.classList.contains( 'mw24-eitaa-app' ) && t.w.document.body.classList.contains( 'mw24-eitaa-light' ) && t.w.document.body.classList.contains( 'mw24-eitaa-fullscreen' ) );
		check( 'back button hidden on entry page', t.calls.includes( 'Back.hide' ) );
		check( 'main button hidden with empty cart', t.calls.includes( 'Main.hide' ) );
		check( 'settings button shown', t.sdk.SettingsButton.isVisible );
		check( 'closing confirmation off for empty cart', t.calls.includes( 'disableClosingConfirmation' ) );
		check( 'POST /session with initData header (not in URL)', t.requests[ 0 ] && /\/session$/.test( t.requests[ 0 ].url ) && t.requests[ 0 ].headers[ 'X-Eitaa-Init-Data' ] === INIT && t.requests[ 0 ].url.indexOf( 'hash' ) === -1 );
		check( 'no nonce header for guests', ! ( 'X-WP-Nonce' in t.requests[ 0 ].headers ) );
		check( 'need_phone -> bar shown (prompt=immediate)', t.w.document.getElementById( 'mw24-eitaa-phone-bar' ).hidden === false );
		check( 'initData cached for later pages', t.w.sessionStorage.getItem( 'mw24_eitaa_init' ) === INIT );

		console.log( '== phone sharing' );
		t.w.document.querySelector( '[data-mw24-eitaa-share]' ).click();
		check( 'requestContact called', t.calls.includes( 'requestContact' ) );
		// Eitaa answers with the object shape { response: "contact=..." }.
		t.sdk._contactCb( true, { response: 'contact=%7B%7D&auth_date=1&hash=' + 'b'.repeat( 64 ) } );
		await tick();
		const contactReq = t.requests.find( ( r ) => /\/contact$/.test( r.url ) );
		check( 'POST /contact with signed response', contactReq && contactReq.body.contact.indexOf( 'contact=' ) === 0 );
	}
	{
		let n = 0;
		const t = setup( { initData: INIT, body: bar, responder: ( m, u ) => {
			n++;
			if ( /session/.test( u ) ) return { status: 200, body: { status: 'need_phone' } };
			return { status: 200, body: { status: 'ok', reload: true, redirect: '' } };
		} } );
		await tick();
		t.w.document.querySelector( '[data-mw24-eitaa-share]' ).click();
		t.sdk._contactCb( true, 'contact=%7B%7D&auth_date=1&hash=' + 'c'.repeat( 64 ) ); // String shape.
		await tick();
		check( 'string callback shape supported + page reloaded after login', t.reloaded === 1 );
		check( 'success haptic', t.calls.includes( 'haptic:success' ) );
	}
	{
		const t = setup( { initData: INIT, body: bar, responder: ( m, u ) => /session/.test( u ) ? { status: 200, body: { status: 'need_phone' } } : { status: 401, body: { code: 'mw24_eitaa_auth_failed', message: 'Eitaa login failed' } } } );
		await tick();
		t.w.document.querySelector( '[data-mw24-eitaa-share]' ).click();
		t.sdk._contactCb( true, { response: 'contact=x&hash=' + 'd'.repeat( 64 ) } );
		await tick();
		check( 'server error -> native alert with message', t.calls.includes( 'alert:Eitaa login failed' ) );
		check( 'error haptic, no reload', t.calls.includes( 'haptic:error' ) && t.reloaded === 0 );
		check( 'bar buttons re-enabled', ! t.w.document.querySelector( '[data-mw24-eitaa-share]' ).disabled );
	}
	{
		const t = setup( { initData: INIT, body: bar, responder: () => ( { status: 200, body: { status: 'need_phone' } } ) } );
		await tick();
		t.w.document.querySelector( '[data-mw24-eitaa-share]' ).click();
		t.sdk._contactCb( false, null );
		await tick();
		check( 'cancelled sharing -> no /contact request', ! t.requests.some( ( r ) => /contact/.test( r.url ) ) );
		t.w.document.querySelector( '[data-mw24-eitaa-later]' ).click();
		check( '"Later" hides bar and is remembered', t.w.document.getElementById( 'mw24-eitaa-phone-bar' ).hidden && t.w.sessionStorage.getItem( 'mw24_eitaa_later' ) === '1' );
	}

	console.log( '== prompt modes' );
	{
		const t = setup( { initData: INIT, body: bar, cfg: { login: { enabled: true, prompt: 'cart_checkout', direct: false } }, responder: () => ( { status: 200, body: { status: 'need_phone' } } ) } );
		await tick();
		check( 'cart_checkout mode: no bar on a product page', t.w.document.getElementById( 'mw24-eitaa-phone-bar' ).hidden );
	}
	{
		const t = setup( { initData: INIT, body: bar, cfg: { login: { enabled: true, prompt: 'immediate', direct: true } }, responder: () => ( { status: 200, body: { status: 'need_phone' } } ) } );
		await tick();
		check( 'direct mode opens native dialog without bar', t.calls.includes( 'requestContact' ) && t.w.document.getElementById( 'mw24-eitaa-phone-bar' ).hidden );
	}
	{
		const t = setup( { initData: INIT, body: bar + '<button data-mw24-eitaa-login data-redirect="https://shop.test/after/">Login</button>', cfg: { login: { enabled: true, prompt: 'button', direct: false } }, responder: ( m, u ) => /session/.test( u ) ? { status: 200, body: { status: 'need_phone' } } : { status: 200, body: { status: 'ok', reload: true } } } );
		await tick();
		check( 'button mode: nothing shown automatically', t.w.document.getElementById( 'mw24-eitaa-phone-bar' ).hidden && ! t.calls.includes( 'requestContact' ) );
		t.w.document.querySelector( '[data-mw24-eitaa-login]' ).click();
		await tick();
		check( 'login button -> session -> requestContact', t.calls.includes( 'requestContact' ) );
		t.sdk._contactCb( true, { response: 'contact=z&hash=' + 'e'.repeat( 64 ) } );
		await tick();
		check( 'redirect attribute used after login', t.navigated.includes( 'https://shop.test/after/' ) );
	}

	console.log( '== returning user & logged-in user' );
	{
		const t = setup( { initData: INIT, responder: () => ( { status: 200, body: { status: 'ok', reload: true, redirect: '' } } ) } );
		await tick();
		check( 'linked account -> reload', t.reloaded === 1 );
	}
	{
		const t = setup( { initData: INIT, unsafe: { user: { id: 1, allows_write_to_pm: false } }, cfg: { nonce: 'abc123', user: { loggedIn: true, linked: false } }, responder: () => ( { status: 200, body: { status: 'ok', linked: true, reload: false } } ) } );
		await tick();
		check( 'logged-in: nonce header sent', t.requests[ 0 ].headers[ 'X-WP-Nonce' ] === 'abc123' );
		check( 'write access requested when not allowed', t.calls.includes( 'requestWriteAccess' ) );
		t.sdk._writeCb( true );
		await tick();
		check( 'granted write access reported to /session', t.requests.some( ( r ) => r.body && r.body.write_access === true ) );
	}

	console.log( '== WooCommerce main button' );
	{
		const t = setup( { initData: INIT, cfg: { woo: { active: true, cartCount: 2, cartUrl: 'https://shop.test/cart/', checkoutUrl: 'https://shop.test/checkout/', storeCartUrl: '' } }, responder: () => ( { status: 200, body: { status: 'need_phone' } } ) } );
		await tick();
		check( 'Cart (2) shown', t.sdk.MainButton.isVisible && t.sdk.MainButton.params.text === 'Cart (2)' );
		check( 'closing confirmation on with items', t.calls.includes( 'enableClosingConfirmation' ) );
		t.sdk.MainButton.click();
		check( 'main button opens cart', t.navigated.includes( 'https://shop.test/cart/' ) );
	}
	{
		const t = setup( { initData: INIT, cfg: { page: { isEntry: false, isCart: true, isCheckout: false, home: 'https://shop.test/' }, woo: { active: true, cartCount: 1, cartUrl: 'c', checkoutUrl: 'https://shop.test/checkout/', storeCartUrl: '' } }, responder: () => ( { status: 200, body: { status: 'need_phone' } } ) } );
		await tick();
		check( 'cart page -> "Continue to checkout"', t.sdk.MainButton.params.text === 'Continue to checkout' );
	}
	{
		const t = setup( { initData: INIT, cfg: { page: { isEntry: false, isCart: false, isCheckout: true, home: 'h' }, woo: { active: true, cartCount: 1, cartUrl: 'c', checkoutUrl: 'k', storeCartUrl: '' } }, responder: () => ( { status: 200, body: { status: 'need_phone' } } ) } );
		await tick();
		check( 'checkout page -> main button hidden', t.calls.includes( 'Main.hide' ) && ! t.sdk.MainButton.isVisible );
	}
	{
		const t = setup( { initData: INIT, body: '<span class="mw24-eitaa-cart-count" data-count="0" hidden></span>', responder: ( m, u ) => /wc\/store/.test( u ) ? { status: 200, body: { items_count: 4 } } : { status: 200, body: { status: 'need_phone' } } } );
		await tick();
		t.w.document.body.dispatchEvent( new t.w.CustomEvent( 'wc-blocks_added_to_cart' ) );
		await tick();
		check( 'blocks add-to-cart -> Store API -> Cart (4)', t.sdk.MainButton.params && t.sdk.MainButton.params.text === 'Cart (4)' );
	}

	console.log( '== start parameter routing (once per session)' );
	{
		const t = setup( { initData: INIT, url: 'https://shop.test/?mw24_eitaa=1&tgWebAppStartParam=p12', cfg: { startRoute: 'https://shop.test/product/x/' }, responder: () => ( { status: 200, body: { status: 'need_phone' } } ) } );
		await tick();
		check( 'server-resolved start route navigated', t.navigated.includes( 'https://shop.test/product/x/' ) );
		check( 'no session call before routing page', ! t.requests.some( ( r ) => /session/.test( r.url ) ) );
	}
	{
		const t = setup( { initData: INIT, unsafe: { start_param: 'u_offers', user: { id: 1, allows_write_to_pm: true } }, responder: ( m, u ) => /route/.test( u ) ? { status: 200, body: { url: 'https://shop.test/offers/' } } : { status: 200, body: { status: 'need_phone' } } } );
		await tick();
		check( 'unsafe start_param resolved through GET /route', t.requests.some( ( r ) => r.method === 'GET' && /route[?&]start=u_offers/.test( r.url ) ) && t.navigated.includes( 'https://shop.test/offers/' ) );
		check( 'routing flag stored', t.w.sessionStorage.getItem( 'mw24_eitaa_routed' ) === '1' );
	}

	{
		const t = setup( { initData: INIT, unsafe: { start_param: 'cart', user: { id: 1, allows_write_to_pm: true } }, cfg: { restUrl: 'https://shop.test/?rest_route=/mw24-eitaa/v1/' }, responder: ( m, u ) => /route/.test( u ) ? { status: 200, body: { url: 'https://shop.test/cart/' } } : { status: 200, body: { status: 'need_phone' } } } );
		await tick();
		check( 'plain permalinks: query appended with &', t.requests.some( ( r ) => r.url === 'https://shop.test/?rest_route=/mw24-eitaa/v1/route&start=cart' ) );
	}

	console.log( '\nRESULT: ' + pass + ' passed, ' + fail + ' failed' );
	process.exit( fail ? 1 : 0 );
} )();
