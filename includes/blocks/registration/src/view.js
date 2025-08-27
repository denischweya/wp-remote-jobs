/**
 * Use this file for JavaScript code that you want to run in the front-end
 * on posts/pages that contain this block.
 *
 * When this file is defined as the value of the `viewScript` property
 * in `block.json` it will be enqueued on the front end of the site.
 *
 * Example:
 *
 * ```js
 * {
 *   "viewScript": "file:./view.js"
 * }
 * ```
 *
 * If you're not making any changes to this file because your project doesn't need any
 * JavaScript running in the front-end, then you should delete this file and remove
 * the `viewScript` property from `block.json`.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-metadata/#view-script
 */

jQuery( document ).ready( function ( $ ) {
	var frame;

	$( '.upload-logo-button' ).on( 'click', function ( e ) {
		e.preventDefault();

		if ( frame ) {
			frame.open();
			return;
		}

		frame = wp.media( {
			title: 'Select or Upload Company Logo',
			button: {
				text: 'Use this image',
			},
			multiple: false,
		} );

		frame.on( 'select', function () {
			var attachment = frame.state().get( 'selection' ).first().toJSON();
			
			// Store attachment ID instead of URL for better WordPress integration
			$( '#logo' ).val( attachment.id );
			
			// Create proper image element with WordPress standards
			var logoHtml = '<img src="' + attachment.url + '" alt="' + 
				(attachment.alt || 'Company Logo Preview') + 
				'" class="company-logo-preview" style="max-width: 150px; height: auto;" />';
			
			$( '.logo-preview' ).html( logoHtml );
			$( '.remove-logo-button' ).show();
		} );

		frame.open();
	} );

	$( '.remove-logo-button' ).on( 'click', function ( e ) {
		e.preventDefault();
		$( '#logo' ).val( '' );
		$( '.logo-preview' ).empty();
		$( this ).hide();
	} );
} );
