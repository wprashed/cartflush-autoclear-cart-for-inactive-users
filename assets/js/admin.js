( function() {
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

		const index = target.children.length;
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
