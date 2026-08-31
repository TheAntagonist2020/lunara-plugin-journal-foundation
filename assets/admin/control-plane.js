( function () {
	'use strict';

	var root = document.querySelector( '.lunara-control-plane' );
	if ( ! root ) {
		return;
	}

	var rows = root.querySelector( '[data-lunara-source-rows]' );
	var template = root.querySelector( '[data-lunara-source-template]' );
	var removals = root.querySelector( '[data-lunara-source-removals]' );
	var addButton = root.querySelector( '[data-lunara-source-add]' );
	var sequence = 0;

	function replacementKey() {
		sequence += 1;
		return 'new-' + Date.now().toString( 36 ) + '-' + sequence.toString( 36 );
	}

	function replaceToken( element, attribute, key ) {
		var value = element.getAttribute( attribute );
		if ( value && value.indexOf( '__ROW_KEY__' ) !== -1 ) {
			element.setAttribute( attribute, value.replace( /__ROW_KEY__/g, key ) );
		}
	}

	function appendRemoval( sourceId ) {
		var alreadyRecorded = removals && Array.prototype.some.call( removals.querySelectorAll( 'input[name="removed_source_ids[]"]' ), function ( input ) {
			return input.value === sourceId;
		} );
		if ( ! sourceId || ! removals || alreadyRecorded ) {
			return;
		}
		var input = document.createElement( 'input' );
		input.type = 'hidden';
		input.name = 'removed_source_ids[]';
		input.value = sourceId;
		removals.appendChild( input );
	}

	function removeRow( button ) {
		var row = button.closest( '[data-lunara-source-row]' );
		if ( ! row ) {
			return;
		}
		var nameField = row.querySelector( 'input[name$="[label]"]' );
		var sourceName = nameField && nameField.value.trim() ? nameField.value.trim() : 'this source';
		if ( ! window.confirm( 'Remove ' + sourceName + '? The removal takes effect only after you save and activate.' ) ) {
			return;
		}
		appendRemoval( row.getAttribute( 'data-existing-id' ) || '' );
		row.remove();
	}

	if ( rows ) {
		rows.addEventListener( 'click', function ( event ) {
			var button = event.target.closest( '[data-lunara-source-remove]' );
			if ( button ) {
				event.preventDefault();
				removeRow( button );
			}
		} );
	}

	if ( addButton && rows && template ) {
		addButton.addEventListener( 'click', function () {
			var key = replacementKey();
			var fragment = template.content.cloneNode( true );
			fragment.querySelectorAll( '[name], [id], [for]' ).forEach( function ( element ) {
				replaceToken( element, 'name', key );
				replaceToken( element, 'id', key );
				replaceToken( element, 'for', key );
			} );
			var row = fragment.querySelector( '[data-lunara-source-row]' );
			if ( row ) {
				var legend = row.querySelector( 'legend' );
				if ( legend ) {
					legend.textContent = 'New source';
				}
				rows.appendChild( fragment );
				var label = rows.lastElementChild.querySelector( 'input[name$="[label]"]' );
				if ( label ) {
					label.focus();
				}
			}
		} );
	}

	root.querySelectorAll( 'form[data-lunara-confirm]' ).forEach( function ( form ) {
		form.addEventListener( 'submit', function ( event ) {
			if ( ! window.confirm( form.getAttribute( 'data-lunara-confirm' ) ) ) {
				event.preventDefault();
				return;
			}
			var confirmation = form.querySelector( 'input[name="confirm_rollback"]' );
			if ( confirmation ) {
				confirmation.value = '1';
			}
		} );
	} );
}() );
