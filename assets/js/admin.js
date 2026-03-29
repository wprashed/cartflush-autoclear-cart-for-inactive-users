( function() {
	function getNextIndex( target ) {
		let maxIndex = -1;

		Array.from( target.querySelectorAll( 'select, input' ) ).forEach( function( field ) {
			const name = field.getAttribute( 'name' ) || '';
			const match = name.match( /\[(\d+)\]\[[^\]]+\]$/ );

			if ( match ) {
				maxIndex = Math.max( maxIndex, parseInt( match[1], 10 ) );
			}
		} );

		return maxIndex + 1;
	}

	function addRow(event) {
		const button = event.currentTarget;
		const type = button.getAttribute( 'data-cartflush-add-row' );

		if ( ! type ) {
			return;
		}

		const template = document.getElementById( 'tmpl-cartflush-' + type );
		const target = document.querySelector( '[data-cartflush-rows="' + type + '"]' );

		if ( ! template || ! target ) {
			return;
		}

		const index = getNextIndex( target );
		const html = template.innerHTML.replace( /{{index}}/g, index );

		target.insertAdjacentHTML( 'beforeend', html );
	}

	document.addEventListener( 'click', function( event ) {
		const button = event.target.closest( '[data-cartflush-add-row]' );
		const removeButton = event.target.closest( '.cartflush-remove-row' );

		if ( button ) {
			addRow( { currentTarget: button } );
			return;
		}

		if ( removeButton ) {
			const row = removeButton.closest( 'tr' );

			if ( row ) {
				row.remove();
			}
		}
	} );
}() );
