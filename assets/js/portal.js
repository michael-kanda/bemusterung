/**
 * Frontend-Portal der Bemusterung.
 *
 * Bewusst ohne Framework: eine Liste, ein Formular, eine Dropzone.
 *
 * @package MXO_Bemusterung
 */
( function () {
	'use strict';

	var cfg = window.mxoBem;
	var app = document.getElementById( 'mxo-bem-app' );

	if ( ! cfg || ! app ) {
		return;
	}

	var state = { promos: [], current: null, busy: false };

	/**
	 * Hängt eine URL mit dem Zugangstoken zusammen.
	 *
	 * @param {string} path Pfad unterhalb des Namensraums.
	 * @return {string} Vollständige URL.
	 */
	function url( path ) {
		var joiner = cfg.root.indexOf( '?' ) === -1 ? '?' : '&';
		return cfg.root + path + joiner + 'k=' + encodeURIComponent( cfg.token );
	}

	/**
	 * Ruft die REST-API auf.
	 *
	 * @param {string} path   Pfad.
	 * @param {string} method HTTP-Methode.
	 * @param {Object} body   Nutzdaten.
	 * @return {Promise} Antwort als Objekt.
	 */
	function api( path, method, body ) {
		return fetch( url( path ), {
			method: method || 'GET',
			headers: body ? { 'Content-Type': 'application/json' } : {},
			body: body ? JSON.stringify( body ) : undefined
		} ).then( function ( response ) {
			return response.json().then( function ( data ) {
				if ( ! response.ok ) {
					throw new Error( data.message || 'Fehler' );
				}
				return data;
			} );
		} );
	}

	/**
	 * Erzeugt ein Element.
	 *
	 * @param {string} tag     Tagname.
	 * @param {Object} props   Eigenschaften.
	 * @param {Array}  kids    Kindelemente.
	 * @return {Element} Das Element.
	 */
	function el( tag, props, kids ) {
		var node = document.createElement( tag );

		Object.keys( props || {} ).forEach( function ( key ) {
			if ( key === 'text' ) {
				node.textContent = props[ key ];
			} else if ( key === 'html' ) {
				node.innerHTML = props[ key ];
			} else if ( key.indexOf( 'on' ) === 0 ) {
				node.addEventListener( key.slice( 2 ).toLowerCase(), props[ key ] );
			} else {
				node.setAttribute( key, props[ key ] );
			}
		} );

		( kids || [] ).forEach( function ( kid ) {
			if ( kid ) {
				node.appendChild( kid );
			}
		} );

		return node;
	}

	/**
	 * Zeigt eine kurze Rückmeldung.
	 *
	 * @param {string}  message Text.
	 * @param {boolean} isError Ob es ein Fehler ist.
	 * @return {void}
	 */
	function toast( message, isError ) {
		var box = document.getElementById( 'mxo-bem-toast' );

		if ( ! box ) {
			box = el( 'div', { id: 'mxo-bem-toast', class: 'mxo-bem__toast', role: 'status' } );
			document.body.appendChild( box );
		}

		box.textContent = message;
		box.className = 'mxo-bem__toast is-visible' + ( isError ? ' is-error' : '' );

		window.setTimeout( function () {
			box.className = 'mxo-bem__toast';
		}, 4000 );
	}

	/**
	 * Lädt die Liste und zeichnet sie.
	 *
	 * @return {void}
	 */
	function loadList() {
		api( '/promos' ).then( function ( data ) {
			state.promos = data.promos || [];
			state.current = null;
			renderList();
		} ).catch( function ( error ) {
			app.innerHTML = '';
			app.appendChild( el( 'p', { class: 'mxo-bem__error', text: error.message } ) );
		} );
	}

	/**
	 * Zeichnet die Übersicht.
	 *
	 * @return {void}
	 */
	function renderList() {
		app.innerHTML = '';

		app.appendChild( el( 'div', { class: 'mxo-bem__head' }, [
			el( 'h2', { text: 'Deine Bemusterungen' } ),
			el( 'button', {
				class: 'mxo-bem__btn mxo-bem__btn--primary',
				type: 'button',
				text: 'Neue Bemusterung',
				onClick: function () {
					openEditor( null );
				}
			} )
		] ) );

		if ( ! state.promos.length ) {
			app.appendChild( el( 'p', {
				class: 'mxo-bem__empty',
				text: 'Noch nichts angelegt. Leg mit „Neue Bemusterung" los.'
			} ) );
			return;
		}

		var list = el( 'ul', { class: 'mxo-bem__list' } );

		state.promos.forEach( function ( promo ) {
			var downloads = promo.tracks.reduce( function ( sum, track ) {
				return sum + track.downloads;
			}, 0 );

			var meta = promo.tracks.length + ( promo.tracks.length === 1 ? ' Titel' : ' Titel' )
				+ ' · ' + downloads + ' Downloads';

			var actions = [
				el( 'button', {
					class: 'mxo-bem__btn',
					type: 'button',
					text: 'Bearbeiten',
					onClick: function () {
						openEditor( promo );
					}
				} )
			];

			if ( promo.link ) {
				actions.push( el( 'button', {
					class: 'mxo-bem__btn',
					type: 'button',
					text: 'Link kopieren',
					onClick: function () {
						copy( promo.link );
					}
				} ) );
			}

			list.appendChild( el( 'li', { class: 'mxo-bem__row' }, [
				promo.cover
					? el( 'img', { class: 'mxo-bem__thumb', src: promo.cover, alt: '' } )
					: el( 'span', { class: 'mxo-bem__thumb mxo-bem__thumb--empty' } ),
				el( 'div', { class: 'mxo-bem__rowbody' }, [
					el( 'strong', { text: promo.title } ),
					el( 'span', {
						class: 'mxo-bem__badge' + ( promo.status === 'publish' ? ' is-live' : '' ),
						text: promo.status === 'publish' ? 'veröffentlicht' : 'Entwurf'
					} ),
					el( 'span', { class: 'mxo-bem__meta', text: meta } )
				] ),
				el( 'div', { class: 'mxo-bem__rowactions' }, actions )
			] ) );
		} );

		app.appendChild( list );
	}

	/**
	 * Kopiert Text in die Zwischenablage.
	 *
	 * @param {string} text Der Text.
	 * @return {void}
	 */
	function copy( text ) {
		if ( navigator.clipboard ) {
			navigator.clipboard.writeText( text ).then( function () {
				toast( 'Link kopiert' );
			} );
			return;
		}

		window.prompt( 'Link kopieren:', text );
	}

	/**
	 * Öffnet den Editor für eine Bemusterung.
	 *
	 * @param {Object|null} promo Bestehende Bemusterung oder null für neu.
	 * @return {void}
	 */
	function openEditor( promo ) {
		state.current = promo;
		app.innerHTML = '';

		var fields = {};

		/**
		 * Baut ein beschriftetes Eingabefeld.
		 *
		 * @param {string} key   Feldname.
		 * @param {string} label Beschriftung.
		 * @param {string} type  Eingabetyp.
		 * @return {Element} Das Feld.
		 */
		function field( key, label, type ) {
			var input = el( type === 'textarea' ? 'textarea' : 'input', {
				class: 'mxo-bem__input',
				type: type || 'text',
				rows: type === 'textarea' ? '6' : null
			} );

			input.value = promo ? ( promo[ key ] || '' ) : '';
			fields[ key ] = input;

			return el( 'label', { class: 'mxo-bem__field' }, [
				el( 'span', { text: label } ),
				input
			] );
		}

		app.appendChild( el( 'button', {
			class: 'mxo-bem__back',
			type: 'button',
			text: '← Zurück zur Übersicht',
			onClick: loadList
		} ) );

		app.appendChild( el( 'h2', { text: promo ? promo.title : 'Neue Bemusterung' } ) );

		var form = el( 'div', { class: 'mxo-bem__form' }, [
			field( 'title', 'Titel der Bemusterung', 'text' ),
			field( 'artist', 'Artist', 'text' ),
			field( 'label', 'Label', 'text' ),
			field( 'releaseDate', 'Releasedatum', 'date' ),
			field( 'contact', 'Kontakt für Rückfragen', 'text' ),
			field( 'text', 'Pressetext', 'textarea' )
		] );

		app.appendChild( form );

		var saveButton = el( 'button', {
			class: 'mxo-bem__btn mxo-bem__btn--primary',
			type: 'button',
			text: 'Speichern',
			onClick: function () {
				save( fields );
			}
		} );

		app.appendChild( el( 'div', { class: 'mxo-bem__actions' }, [ saveButton ] ) );

		if ( promo ) {
			app.appendChild( buildDropzone( promo ) );
			app.appendChild( buildTrackList( promo ) );
			app.appendChild( buildPublish( promo ) );
		} else {
			app.appendChild( el( 'p', {
				class: 'mxo-bem__hint',
				text: 'Speichere zuerst, danach kannst du Dateien hinzufügen.'
			} ) );
		}
	}

	/**
	 * Speichert die Felder.
	 *
	 * @param {Object} fields Die Eingabefelder.
	 * @return {void}
	 */
	function save( fields ) {
		if ( state.busy ) {
			return;
		}

		var payload = {};
		Object.keys( fields ).forEach( function ( key ) {
			payload[ key ] = fields[ key ].value;
		} );

		if ( state.current ) {
			payload.id = state.current.id;
		}

		state.busy = true;

		api( '/promos', 'POST', payload ).then( function ( promo ) {
			state.busy = false;
			toast( 'Gespeichert' );
			openEditor( promo );
		} ).catch( function ( error ) {
			state.busy = false;
			toast( error.message, true );
		} );
	}

	/**
	 * Baut die Dropzone.
	 *
	 * @param {Object} promo Die Bemusterung.
	 * @return {Element} Der Bereich.
	 */
	function buildDropzone( promo ) {
		var input = el( 'input', {
			type: 'file',
			multiple: 'multiple',
			class: 'mxo-bem__file',
			accept: '.mp3,.wav,.aif,.aiff,.flac,.jpg,.jpeg,.png,.pdf'
		} );

		var progress = el( 'ul', { class: 'mxo-bem__progress' } );

		var zone = el( 'div', {
			class: 'mxo-bem__zone',
			tabindex: '0',
			role: 'button',
			onClick: function () {
				input.click();
			},
			onKeydown: function ( event ) {
				if ( event.key === 'Enter' || event.key === ' ' ) {
					event.preventDefault();
					input.click();
				}
			}
		}, [
			el( 'span', { class: 'mxo-bem__zonetitle', text: 'Dateien hierher ziehen' } ),
			el( 'span', {
				class: 'mxo-bem__zonehint',
				text: 'Cover als JPG, Vorhörversion als MP3, Master als WAV. Gleiche Dateinamen gehören zum selben Titel.'
			} )
		] );

		[ 'dragenter', 'dragover', 'dragleave', 'drop' ].forEach( function ( name ) {
			zone.addEventListener( name, function ( event ) {
				event.preventDefault();
				event.stopPropagation();
			} );
		} );

		[ 'dragenter', 'dragover' ].forEach( function ( name ) {
			zone.addEventListener( name, function () {
				zone.classList.add( 'is-over' );
			} );
		} );

		[ 'dragleave', 'drop' ].forEach( function ( name ) {
			zone.addEventListener( name, function () {
				zone.classList.remove( 'is-over' );
			} );
		} );

		zone.addEventListener( 'drop', function ( event ) {
			if ( event.dataTransfer && event.dataTransfer.files ) {
				queue( promo, event.dataTransfer.files, progress );
			}
		} );

		input.addEventListener( 'change', function () {
			queue( promo, input.files, progress );
			input.value = '';
		} );

		return el( 'div', { class: 'mxo-bem__uploads' }, [ zone, input, progress ] );
	}

	/**
	 * Lädt die Dateien nacheinander hoch.
	 *
	 * Nacheinander und nicht parallel, weil mehrere gleichzeitige WAV-Uploads
	 * viele Server überfordern.
	 *
	 * @param {Object}   promo    Die Bemusterung.
	 * @param {FileList} files    Die Dateien.
	 * @param {Element}  progress Die Fortschrittsliste.
	 * @return {void}
	 */
	function queue( promo, files, progress ) {
		var pending = Array.prototype.slice.call( files );

		/**
		 * Arbeitet den nächsten Eintrag ab.
		 *
		 * @return {void}
		 */
		function next() {
			if ( ! pending.length ) {
				api( '/promos/' + promo.id ).then( openEditor );
				return;
			}

			upload( promo, pending.shift(), progress, next );
		}

		next();
	}

	/**
	 * Lädt eine einzelne Datei hoch.
	 *
	 * @param {Object}   promo    Die Bemusterung.
	 * @param {File}     file     Die Datei.
	 * @param {Element}  progress Die Fortschrittsliste.
	 * @param {Function} done     Rückruf nach Abschluss.
	 * @return {void}
	 */
	function upload( promo, file, progress, done ) {
		var state_el = el( 'span', { class: 'mxo-bem__pstate', text: '0 %' } );
		var row = el( 'li', { class: 'mxo-bem__prow' }, [
			el( 'span', { class: 'mxo-bem__pname', text: file.name } ),
			state_el
		] );

		progress.appendChild( row );

		if ( file.size > cfg.maxBytes ) {
			row.classList.add( 'is-failed' );
			state_el.textContent = 'zu groß';
			done();
			return;
		}

		var data = new FormData();
		data.append( 'file', file );

		var request = new XMLHttpRequest();
		request.open( 'POST', url( '/promos/' + promo.id + '/files' ), true );

		request.upload.addEventListener( 'progress', function ( event ) {
			if ( event.lengthComputable ) {
				state_el.textContent = Math.round( ( event.loaded / event.total ) * 100 ) + ' %';
			}
		} );

		request.addEventListener( 'load', function () {
			if ( request.status >= 200 && request.status < 300 ) {
				row.classList.add( 'is-done' );
				state_el.textContent = 'fertig';
			} else {
				var message = '';
				try {
					message = JSON.parse( request.responseText ).message || 'fehlgeschlagen';
				} catch ( error ) {
					message = 'fehlgeschlagen';
				}
				row.classList.add( 'is-failed' );
				state_el.textContent = message;
			}
			done();
		} );

		request.addEventListener( 'error', function () {
			row.classList.add( 'is-failed' );
			state_el.textContent = 'fehlgeschlagen';
			done();
		} );

		request.send( data );
	}

	/**
	 * Baut eine kopierbare Linkzeile.
	 *
	 * Redaktionen wollen die Adresse direkt im Mail, nicht den Umweg über die
	 * Promoseite. Deshalb steht sie hier zum Herauskopieren bereit.
	 *
	 * @param {string} label Beschriftung.
	 * @param {string} value Die Adresse.
	 * @return {Element} Die Zeile.
	 */
	function linkRow( label, value ) {
		var input = el( 'input', { type: 'text', class: 'mxo-bem__input mxo-bem__linkfield', readonly: 'readonly' } );
		input.value = value;

		input.addEventListener( 'focus', function () {
			input.select();
		} );

		return el( 'div', { class: 'mxo-bem__linkrow' }, [
			el( 'span', { class: 'mxo-bem__linklabel', text: label } ),
			input,
			el( 'button', {
				class: 'mxo-bem__btn mxo-bem__btn--small',
				type: 'button',
				text: 'Kopieren',
				onClick: function () {
					copy( value );
				}
			} )
		] );
	}

	/**
	 * Baut die Trackliste mit Vorhörfunktion.
	 *
	 * @param {Object} promo Die Bemusterung.
	 * @return {Element} Der Bereich.
	 */
	function buildTrackList( promo ) {
		var wrap = el( 'div', { class: 'mxo-bem__tracks' } );

		if ( ! promo.tracks.length ) {
			return wrap;
		}

		wrap.appendChild( el( 'h3', { text: 'Titel' } ) );

		promo.tracks.forEach( function ( track ) {
			var bits = [ el( 'strong', { text: track.title } ) ];

			bits.push( el( 'span', {
				class: 'mxo-bem__meta',
				text: ( track.hasMaster ? 'WAV vorhanden' : 'WAV fehlt' )
					+ ' · ' + track.downloads + ' Downloads'
			} ) );

			if ( track.preview ) {
				var audio = el( 'audio', { controls: 'controls', class: 'mxo-bem__audio' } );
				audio.src = track.preview;
				bits.push( audio );
			}

			if ( track.download ) {
				bits.push( linkRow( 'Downloadlink für Redaktionen', track.download ) );
			}

			if ( track.preview ) {
				bits.push( linkRow( 'Vorhörlink', track.preview ) );
			}

			wrap.appendChild( el( 'div', { class: 'mxo-bem__track' }, [
				el( 'div', { class: 'mxo-bem__trackbody' }, bits ),
				el( 'button', {
					class: 'mxo-bem__btn mxo-bem__btn--danger',
					type: 'button',
					text: 'Entfernen',
					onClick: function () {
						if ( ! window.confirm( 'Titel „' + track.title + '" wirklich entfernen?' ) ) {
							return;
						}
						api( '/promos/' + promo.id + '/tracks/' + track.id, 'DELETE' )
							.then( openEditor )
							.catch( function ( error ) {
								toast( error.message, true );
							} );
					}
				} )
			] ) );
		} );

		return wrap;
	}

	/**
	 * Baut den Veröffentlichungsbereich.
	 *
	 * @param {Object} promo Die Bemusterung.
	 * @return {Element} Der Bereich.
	 */
	function buildPublish( promo ) {
		var live = promo.status === 'publish';
		var kids = [];

		kids.push( el( 'button', {
			class: 'mxo-bem__btn ' + ( live ? '' : 'mxo-bem__btn--primary' ),
			type: 'button',
			text: live ? 'Zurückziehen' : 'Veröffentlichen',
			onClick: function () {
				api( '/promos/' + promo.id + '/status', 'POST', {
					status: live ? 'draft' : 'publish'
				} ).then( function ( updated ) {
					toast( live ? 'Zurückgezogen' : 'Veröffentlicht' );
					openEditor( updated );
				} ).catch( function ( error ) {
					toast( error.message, true );
				} );
			}
		} ) );

		if ( promo.link ) {
			var linkField = el( 'input', { type: 'text', class: 'mxo-bem__input mxo-bem__link', readonly: 'readonly' } );
			linkField.value = promo.link;

			kids.push( linkField );
			kids.push( el( 'button', {
				class: 'mxo-bem__btn',
				type: 'button',
				text: 'Link kopieren',
				onClick: function () {
					copy( promo.link );
				}
			} ) );
		}

		return el( 'div', { class: 'mxo-bem__publish' }, [
			el( 'h3', { text: 'Bemusterungslink' } ),
			el( 'p', {
				class: 'mxo-bem__hint',
				text: live
					? 'Diesen Link kannst du an die Redaktionen schicken. Er ist nicht über Google auffindbar.'
					: 'Nach dem Veröffentlichen erscheint hier der Link für die Redaktionen.'
			} ),
			el( 'div', { class: 'mxo-bem__actions' }, kids )
		] );
	}

	loadList();
}() );
