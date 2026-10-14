/**
 * Eitaa Connect — admin screens (vanilla JS; jQuery only for the optional WP color picker).
 *
 * @package MW24_Eitaa
 */
( function () {
	'use strict';

	var A = window.MW24EitaaAdmin || {};
	var t = A.i18n || {};

	function $( selector, context ) {
		return ( context || document ).querySelector( selector );
	}

	function $$( selector, context ) {
		return Array.prototype.slice.call( ( context || document ).querySelectorAll( selector ) );
	}

	function esc( value ) {
		var div = document.createElement( 'div' );
		div.textContent = value === null || value === undefined ? '' : String( value );
		return div.innerHTML;
	}

	function sprintf( text ) {
		var args = Array.prototype.slice.call( arguments, 1 );
		return String( text || '' ).replace( /%(\d+)\$s/g, function ( match, index ) {
			var value = args[ parseInt( index, 10 ) - 1 ];
			return value === undefined ? '' : value;
		} ).replace( /%s/g, function () {
			return args.length ? args.shift() : '';
		} );
	}

	/**
	 * Appends query args to a REST path (works with pretty and plain permalinks).
	 */
	function withQuery( path, params ) {
		var separator = String( A.restUrl ).indexOf( '?' ) === -1 ? '?' : '&';
		return path + separator + Object.keys( params ).map( function ( key ) {
			return encodeURIComponent( key ) + '=' + encodeURIComponent( params[ key ] );
		} ).join( '&' );
	}

	function api( path, method, data ) {
		var options = {
			method: method || 'GET',
			credentials: 'same-origin',
			headers: { 'X-WP-Nonce': A.nonce, Accept: 'application/json' }
		};
		if ( options.method !== 'GET' ) {
			options.headers[ 'Content-Type' ] = 'application/json';
			options.body = JSON.stringify( data || {} );
		}
		return window.fetch( A.restUrl + path, options ).then( function ( response ) {
			return response.json().catch( function () {
				return {};
			} ).then( function ( json ) {
				if ( ! response.ok ) {
					throw new Error( json && json.message ? json.message : t.error );
				}
				return json;
			} );
		} );
	}

	function result( box, message, ok ) {
		if ( box ) {
			box.innerHTML = '<span class="' + ( ok ? 'is-ok' : 'is-error' ) + '">' + esc( message ) + '</span>';
		}
	}

	/* ------------------------------------------------------------ copy & confirm */

	function copy( text, button ) {
		var done = function () {
			if ( button ) {
				var old = button.textContent;
				button.textContent = t.copied;
				window.setTimeout( function () {
					button.textContent = old;
				}, 1500 );
			}
		};
		if ( navigator.clipboard && window.isSecureContext ) {
			navigator.clipboard.writeText( text ).then( done );
			return;
		}
		var area = document.createElement( 'textarea' );
		area.value = text;
		document.body.appendChild( area );
		area.select();
		document.execCommand( 'copy' );
		document.body.removeChild( area );
		done();
	}

	document.addEventListener( 'click', function ( event ) {
		var confirmEl = event.target.closest( '[data-mw24-confirm]' );
		if ( confirmEl && ! window.confirm( confirmEl.getAttribute( 'data-mw24-confirm' ) || t.confirm ) ) {
			event.preventDefault();
			event.stopImmediatePropagation();
			return;
		}
		var copyEl = event.target.closest( '[data-mw24-copy]' );
		if ( copyEl ) {
			event.preventDefault();
			copy( copyEl.getAttribute( 'data-mw24-copy' ), copyEl );
		}
	}, true );

	document.addEventListener( 'change', function ( event ) {
		var box = event.target;
		if ( box.matches && box.matches( '[data-mw24-confirm-check]' ) && box.checked && ! window.confirm( box.getAttribute( 'data-mw24-confirm-check' ) ) ) {
			box.checked = false;
		}
	} );

	/* ------------------------------------------------------------ conditional rows */

	function updateShowIf() {
		$$( '[data-mw24-show-if]' ).forEach( function ( row ) {
			var parts = ( row.getAttribute( 'data-mw24-show-if' ) || '' ).split( '=' );
			var inputs = $$( '[name="mw24[' + parts[ 0 ] + ']"]' ).filter( function ( input ) {
				return input.type !== 'hidden';
			} );
			if ( ! inputs.length ) {
				return;
			}
			var input = inputs[ 0 ];
			var value = input.type === 'checkbox' ? ( input.checked ? '1' : '0' ) : input.value;
			row.classList.toggle( 'mw24-hidden', value !== parts[ 1 ] );
		} );
	}

	document.addEventListener( 'change', updateShowIf );
	updateShowIf();

	if ( window.jQuery && window.jQuery.fn && window.jQuery.fn.wpColorPicker ) {
		window.jQuery( '.mw24-color' ).wpColorPicker();
	}

	/* ------------------------------------------------------------ repeaters */

	var rowCounter = 0;

	function addRow( repeater, values ) {
		var template = $( 'template[data-mw24-template]', repeater );
		var rows = $( '[data-mw24-rows]', repeater );
		if ( ! template || ! rows ) {
			return null;
		}
		rowCounter++;
		rows.insertAdjacentHTML( 'beforeend', template.innerHTML.replace( /__i__/g, 'n' + Date.now() + rowCounter ) );
		var row = rows.lastElementChild;
		if ( values && row ) {
			Object.keys( values ).forEach( function ( name ) {
				var input = row.querySelector( '[name$="[' + name + ']"]' );
				if ( input ) {
					input.value = values[ name ];
				}
			} );
		}
		return row;
	}

	$$( '[data-mw24-repeater]' ).forEach( function ( repeater ) {
		repeater.addEventListener( 'click', function ( event ) {
			if ( event.target.closest( '[data-mw24-add]' ) ) {
				event.preventDefault();
				addRow( repeater );
			}
			var remove = event.target.closest( '[data-mw24-remove]' );
			if ( remove ) {
				event.preventDefault();
				remove.closest( 'tr' ).remove();
			}
		} );
	} );

	/* ------------------------------------------------------------ meta key discovery */

	function renderKeys( data ) {
		var box = $( '#mw24-meta-keys' );
		var list = $( '#mw24-meta-key-list' );
		var keys = ( data && data.keys ) || [];
		var used = ( data && data.used ) || [];
		if ( list ) {
			list.innerHTML = keys.map( function ( item ) {
				return '<option value="' + esc( item.key ) + '"></option>';
			} ).join( '' );
		}
		if ( ! keys.length ) {
			box.textContent = t.noKeys;
			return;
		}
		var html = '<table class="widefat striped"><thead><tr><th>' + esc( t.key ) + '</th><th>' + esc( t.rows ) + '</th><th>' + esc( t.phoneLike ) + '</th><th>' + esc( t.sample ) + '</th><th></th></tr></thead><tbody>';
		keys.forEach( function ( item ) {
			var inUse = used.indexOf( item.key ) !== -1;
			html += '<tr><td><code dir="ltr">' + esc( item.key ) + '</code></td><td>' + esc( item.total ) + '</td><td>' + esc( item.ratio ) + '%</td><td dir="ltr">' + esc( item.sample || '—' ) + '</td><td>' +
				( inUse ? '✓' : '<button type="button" class="button button-small" data-mw24-use-key="' + esc( item.key ) + '" data-format="' + esc( item.format || '0' ) + '">' + esc( t.use ) + '</button>' ) + '</td></tr>';
		} );
		box.innerHTML = html + '</tbody></table>';
	}

	$$( '[data-mw24-load-keys]' ).forEach( function ( button ) {
		button.addEventListener( 'click', function () {
			var box = $( '#mw24-meta-keys' );
			box.textContent = t.loading;
			api( withQuery( 'meta-keys', { refresh: button.getAttribute( 'data-mw24-load-keys' ) === '1' ? 1 : 0 } ), 'GET' ).then( renderKeys ).catch( function ( error ) {
				result( box, error.message, false );
			} );
		} );
	} );

	document.addEventListener( 'click', function ( event ) {
		var use = event.target.closest( '[data-mw24-use-key]' );
		if ( ! use ) {
			return;
		}
		event.preventDefault();
		var repeater = $( '[data-mw24-repeater]' );
		if ( repeater ) {
			var row = addRow( repeater, { key: use.getAttribute( 'data-mw24-use-key' ), format: use.getAttribute( 'data-format' ) || '0' } );
			if ( row ) {
				row.scrollIntoView( { behavior: 'smooth', block: 'center' } );
			}
			use.outerHTML = '✓';
		}
	} );

	/* ------------------------------------------------------------ custom table map */

	var tableMap = $( '[data-mw24-table-map]' );

	function tableSaved() {
		try {
			return JSON.parse( tableMap.getAttribute( 'data-saved' ) || '{}' ) || {};
		} catch ( e ) {
			return {};
		}
	}

	function fillSelect( select, values, selected, placeholder ) {
		select.innerHTML = '<option value="">' + esc( placeholder ) + '</option>' + values.map( function ( value ) {
			return '<option value="' + esc( value ) + '"' + ( value === selected ? ' selected' : '' ) + '>' + esc( value ) + '</option>';
		} ).join( '' );
	}

	function renderExtras( columns ) {
		var saved = tableSaved();
		var extras = {};
		( saved.extras || [] ).forEach( function ( extra ) {
			extras[ extra.col ] = extra;
		} );
		var phone = $( '[data-mw24-column="phone_col"]', tableMap ).value;
		var user = $( '[data-mw24-column="user_col"]', tableMap ).value;
		var types = t.extraTypes || {};
		var html = '<table class="widefat"><thead><tr><th>' + esc( t.column ) + '</th><th>' + esc( t.type ) + '</th><th>' + esc( t.valueSource ) + '</th></tr></thead><tbody>';
		columns.forEach( function ( column, index ) {
			if ( column.name === phone || column.name === user || /auto_increment/i.test( column.extra ) ) {
				return;
			}
			var current = extras[ column.name ] || {};
			var options = '<option value="">' + esc( t.notSet ) + '</option>';
			Object.keys( types ).forEach( function ( key ) {
				options += '<option value="' + esc( key ) + '"' + ( current.type === key ? ' selected' : '' ) + '>' + esc( types[ key ] ) + '</option>';
			} );
			html += '<tr><td><code dir="ltr">' + esc( column.name ) + '</code> <span class="mw24-muted">' + esc( column.type ) + '</span>' + ( column.required ? ' <span class="mw24-badge mw24-badge--warn">' + esc( t.required ) + '</span>' : '' ) +
				'<input type="hidden" name="mw24[table][extras][' + index + '][col]" value="' + esc( column.name ) + '"></td>' +
				'<td><select name="mw24[table][extras][' + index + '][type]">' + options + '</select></td>' +
				'<td><input type="text" class="code" dir="ltr" name="mw24[table][extras][' + index + '][value]" value="' + esc( current.value || '' ) + '"></td></tr>';
		} );
		$( '[data-mw24-table-extras]', tableMap ).innerHTML = html + '</tbody></table>';
	}

	function loadColumns( table ) {
		var saved = tableSaved();
		if ( ! table ) {
			return;
		}
		api( withQuery( 'columns', { table: table } ), 'GET' ).then( function ( data ) {
			var names = ( data.columns || [] ).map( function ( column ) {
				return column.name;
			} );
			fillSelect( $( '[data-mw24-column="phone_col"]', tableMap ), names, saved.table === table ? saved.phone_col : '', t.chooseColumn );
			fillSelect( $( '[data-mw24-column="user_col"]', tableMap ), names, saved.table === table ? saved.user_col : '', t.chooseColumn );
			tableMap.mw24Columns = data.columns || [];
			renderExtras( tableMap.mw24Columns );
		} ).catch( function ( error ) {
			result( $( '[data-mw24-table-extras]', tableMap ), error.message, false );
		} );
	}

	function loadTables() {
		var select = $( '[data-mw24-table-select]', tableMap );
		var saved = tableSaved();
		api( 'tables', 'GET' ).then( function ( data ) {
			fillSelect( select, data.tables || [], select.value || saved.table || '', t.chooseTable );
			if ( select.value ) {
				loadColumns( select.value );
			}
		} );
	}

	if ( tableMap ) {
		$( '[data-mw24-load-tables]', tableMap ).addEventListener( 'click', loadTables );
		$( '[data-mw24-table-select]', tableMap ).addEventListener( 'change', function ( event ) {
			loadColumns( event.target.value );
		} );
		$$( '[data-mw24-column]', tableMap ).forEach( function ( select ) {
			select.addEventListener( 'change', function () {
				if ( tableMap.mw24Columns ) {
					renderExtras( tableMap.mw24Columns );
				}
			} );
		} );
		if ( tableSaved().table ) {
			loadTables();
		}
	}

	/* ------------------------------------------------------------ dry-run */

	function renderDryRun( data ) {
		var actions = t.actions || {};
		var html = '<p><strong>' + esc( t.normalized ) + ':</strong> <code dir="ltr">' + esc( data.normalized ) + '</code></p>';
		html += '<p><strong>' + esc( t.variants ) + ':</strong> <code dir="ltr">' + esc( ( data.variants || [] ).join( '  ' ) ) + '</code></p>';
		if ( data.user ) {
			html += '<p class="is-ok">' + esc( t.foundUser ) + ': <a href="' + esc( data.user.edit_link ) + '">' + esc( data.user.display_name ) + ' (' + esc( data.user.login ) + ', #' + esc( data.user.id ) + ')</a></p>';
		} else {
			html += '<p>' + esc( t.newUser ) + '</p>';
		}
		if ( data.conflict ) {
			html += '<p class="is-error">' + esc( t.conflict ) + '</p>';
		}
		if ( data.matches && data.matches.length ) {
			html += '<h4>' + esc( t.matches ) + '</h4><table class="widefat striped"><tbody>';
			data.matches.forEach( function ( match ) {
				html += '<tr><td dir="ltr">' + esc( match.label ) + '</td><td>#' + esc( match.user_id ) + '</td></tr>';
			} );
			html += '</tbody></table>';
		}
		html += '<h4>' + esc( t.writes ) + '</h4><table class="widefat striped"><thead><tr><th>' + esc( t.field ) + '</th><th>' + esc( t.current ) + '</th><th>' + esc( t.value ) + '</th><th>' + esc( t.action ) + '</th></tr></thead><tbody>';
		( data.writes || [] ).forEach( function ( row ) {
			html += '<tr><td dir="ltr">' + esc( row.label ) + '</td><td dir="ltr">' + esc( row.current || '—' ) + '</td><td dir="ltr"><code>' + esc( row.value ) + '</code></td><td>' + esc( actions[ row.action ] || row.action ) + '</td></tr>';
		} );
		return html + '</tbody></table>';
	}

	$$( '[data-mw24-dry-run]' ).forEach( function ( box ) {
		var input = $( '[data-mw24-input]', box );
		var output = $( '[data-mw24-result]', box );
		var run = function () {
			output.textContent = t.loading;
			api( 'dry-run', 'POST', { phone: input.value } ).then( function ( data ) {
				output.innerHTML = renderDryRun( data );
			} ).catch( function ( error ) {
				result( output, error.message, false );
			} );
		};
		$( '[data-mw24-run]', box ).addEventListener( 'click', run );
		input.addEventListener( 'keydown', function ( event ) {
			if ( event.key === 'Enter' ) {
				event.preventDefault();
				run();
			}
		} );
	} );

	/* ------------------------------------------------------------ test message */

	$$( '[data-mw24-test-message]' ).forEach( function ( box ) {
		var output = $( '[data-mw24-result]', box );
		$( '[data-mw24-run]', box ).addEventListener( 'click', function () {
			output.textContent = t.loading;
			api( 'test-message', 'POST', { chat_id: $( '[data-mw24-chat]', box ).value, text: $( '[data-mw24-text]', box ).value } ).then( function ( data ) {
				result( output, data.message || t.sent, true );
			} ).catch( function ( error ) {
				result( output, error.message, false );
			} );
		} );
	} );

	/* ------------------------------------------------------------ backfill */

	var backfill = $( '[data-mw24-backfill]' );
	var pollTimer = null;

	function renderBackfill( state ) {
		if ( ! backfill || ! state ) {
			return;
		}
		var total = Math.max( 1, parseInt( state.total, 10 ) || 1 );
		var labels = t.backfill || {};
		$( '[data-mw24-bar]', backfill ).style.width = Math.min( 100, Math.round( 100 * state.processed / total ) ) + '%';
		$( '[data-mw24-state]', backfill ).textContent = ( labels[ state.status ] || state.status ) + ' — ' + sprintf( t.progress, state.processed, state.total, state.updated, state.writes, state.conflicts );
		window.clearTimeout( pollTimer );
		if ( state.status === 'running' ) {
			pollTimer = window.setTimeout( function () {
				api( 'backfill', 'POST', { op: 'status' } ).then( function ( data ) {
					renderBackfill( data.status );
				} );
			}, 4000 );
		}
	}

	if ( backfill ) {
		try {
			renderBackfill( JSON.parse( backfill.getAttribute( 'data-status' ) || 'null' ) );
		} catch ( e ) {}
		$$( '[data-mw24-op]', backfill ).forEach( function ( button ) {
			button.addEventListener( 'click', function () {
				var op = button.getAttribute( 'data-mw24-op' );
				var output = $( '[data-mw24-result]', backfill );
				output.textContent = t.loading;
				api( 'backfill', 'POST', { op: op } ).then( function ( data ) {
					output.textContent = '';
					if ( data.preview ) {
						var p = data.preview;
						var html = '<p>' + esc( sprintf( t.preview, p.scanned, p.with_phone, p.to_update, p.writes, p.conflicts ) ) + '</p>';
						if ( p.sample && p.sample.length ) {
							html += '<table class="widefat striped"><tbody>';
							p.sample.forEach( function ( row ) {
								html += '<tr><td>#' + esc( row.user_id ) + ' ' + esc( row.login ) + '</td><td dir="ltr">' + esc( row.phone ) + '</td><td dir="ltr">' + esc( row.writes.join( ' | ' ) ) + '</td></tr>';
							} );
							html += '</tbody></table>';
						}
						output.innerHTML = html;
					}
					renderBackfill( data.status );
				} ).catch( function ( error ) {
					result( output, error.message, false );
				} );
			} );
		} );
	}

	/* ------------------------------------------------------------ send message to a user */

	var panel = $( '[data-mw24-send-panel]' );
	var panelUser = 0;

	document.addEventListener( 'click', function ( event ) {
		var trigger = event.target.closest( '[data-mw24-send-user]' );
		if ( ! trigger || ! panel ) {
			return;
		}
		event.preventDefault();
		panelUser = parseInt( trigger.getAttribute( 'data-mw24-send-user' ), 10 );
		$( '[data-mw24-send-name]', panel ).textContent = trigger.getAttribute( 'data-mw24-name' ) || '';
		$( '[data-mw24-send-result]', panel ).textContent = '';
		panel.hidden = false;
		panel.scrollIntoView( { behavior: 'smooth' } );
		$( '[data-mw24-send-text]', panel ).focus();
	} );

	if ( panel ) {
		$( '[data-mw24-send-cancel]', panel ).addEventListener( 'click', function () {
			panel.hidden = true;
		} );
		$( '[data-mw24-send-submit]', panel ).addEventListener( 'click', function () {
			var output = $( '[data-mw24-send-result]', panel );
			output.textContent = t.loading;
			api( 'send-user', 'POST', { user_id: panelUser, text: $( '[data-mw24-send-text]', panel ).value } ).then( function ( data ) {
				result( output, data.message || t.sent, true );
				$( '[data-mw24-send-text]', panel ).value = '';
			} ).catch( function ( error ) {
				result( output, error.message, false );
			} );
		} );
	}

	$$( '[data-mw24-profile-send]' ).forEach( function ( box ) {
		$( '[data-mw24-run]', box ).addEventListener( 'click', function () {
			var output = $( '[data-mw24-result]', box );
			output.textContent = t.loading;
			api( 'send-user', 'POST', { user_id: parseInt( box.getAttribute( 'data-user' ), 10 ), text: $( '[data-mw24-text]', box ).value } ).then( function ( data ) {
				result( output, data.message || t.sent, true );
			} ).catch( function ( error ) {
				result( output, error.message, false );
			} );
		} );
	} );

	/* ------------------------------------------------------------ bulk preview */

	var bulk = $( '[data-mw24-bulk]' );
	if ( bulk ) {
		$( '[data-mw24-bulk-preview]', bulk ).addEventListener( 'click', function () {
			var filters = {};
			$$( '[data-mw24-filter]', bulk ).forEach( function ( input ) {
				filters[ input.getAttribute( 'data-mw24-filter' ) ] = input.type === 'checkbox' ? input.checked : input.value;
			} );
			var output = $( '[data-mw24-bulk-count]', bulk );
			output.textContent = t.loading;
			api( 'bulk-preview', 'POST', filters ).then( function ( data ) {
				output.textContent = sprintf( t.recipients, data.count );
			} ).catch( function ( error ) {
				output.textContent = error.message;
			} );
		} );
	}
}() );
