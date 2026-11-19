/**
 * Dropzone der Bemusterung. Kein Framework, keine Abhängigkeit.
 *
 * @package MXO_Bemusterung
 */
( function () {
	'use strict';

	var config = window.mxoBemUpload;
	if ( ! config ) {
		return;
	}

	var zone = document.getElementById( 'mxo-bem-zone' );
	var input = document.getElementById( 'mxo-bem-input' );
	var browse = document.getElementById( 'mxo-bem-browse' );
	var list = document.getElementById( 'mxo-bem-list' );

	if ( ! zone || ! input || ! list ) {
		return;
	}

	/**
	 * Legt eine Zeile für eine Datei an.
	 *
	 * @param {string} name Dateiname.
	 * @return {Object} Zeile mit Fortschrittsanzeige.
	 */
	function addRow( name ) {
		var li = document.createElement( 'li' );
		li.className = 'mxo-bem-upload__item';

		var label = document.createElement( 'span' );
		label.className = 'mxo-bem-upload__name';
		label.textContent = name;

		var state = document.createElement( 'span' );
		state.className = 'mxo-bem-upload__state';
		state.textContent = '0 %';

		li.appendChild( label );
		li.appendChild( state );
		list.appendChild( li );

		return {
			progress: function ( percent ) {
				state.textContent = percent + ' %';
			},
			done: function () {
				li.classList.add( 'is-done' );
				state.textContent = config.i18n.done;
			},
			fail: function ( message ) {
				li.classList.add( 'is-failed' );
				state.textContent = message || config.i18n.failed;
			}
		};
	}

	/**
	 * Lädt eine einzelne Datei hoch.
	 *
	 * @param {File} file Die Datei.
	 * @return {void}
	 */
	function upload( file ) {
		var row = addRow( file.name );

		if ( file.size > config.maxBytes ) {
			row.fail( config.i18n.toobig );
			return;
		}

		var data = new FormData();
		data.append( 'file', file );
		data.append( 'bem', config.promo );
		data.append( 't', config.token );

		var request = new XMLHttpRequest();
		request.open( 'POST', config.endpoint, true );

		request.upload.addEventListener( 'progress', function ( event ) {
			if ( event.lengthComputable ) {
				row.progress( Math.round( ( event.loaded / event.total ) * 100 ) );
			}
		} );

		request.addEventListener( 'load', function () {
			if ( request.status >= 200 && request.status < 300 ) {
				row.done();
				return;
			}

			var message = '';
			try {
				message = JSON.parse( request.responseText ).message || '';
			} catch ( error ) {
				message = '';
			}
			row.fail( message );
		} );

		request.addEventListener( 'error', function () {
			row.fail();
		} );

		request.send( data );
	}

	/**
	 * Arbeitet eine Dateiliste nacheinander ab.
	 *
	 * @param {FileList} files Die Dateien.
	 * @return {void}
	 */
	function handleFiles( files ) {
		Array.prototype.forEach.call( files, upload );
	}

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
			handleFiles( event.dataTransfer.files );
		}
	} );

	if ( browse ) {
		browse.addEventListener( 'click', function () {
			input.click();
		} );
	}

	zone.addEventListener( 'keydown', function ( event ) {
		if ( 'Enter' === event.key || ' ' === event.key ) {
			event.preventDefault();
			input.click();
		}
	} );

	input.addEventListener( 'change', function () {
		handleFiles( input.files );
		input.value = '';
	} );
}() );
