/* global jQuery, ClientReviewsAdmin */
( function ( $ ) {
	'use strict';

	function actionForField( field ) {
		return 'is_featured' === field ? 'client_reviews_toggle_featured' : 'client_reviews_toggle_visible';
	}

	$( document ).on( 'click', '.client-reviews-toggle', function ( event ) {
		event.preventDefault();

		var $button = $( this );
		var field   = $button.data( 'field' );

		$button.prop( 'disabled', true );

		$.post( ClientReviewsAdmin.ajaxUrl, {
			action: actionForField( field ),
			id: $button.data( 'id' ),
			nonce: $button.data( 'nonce' ),
		} ).done( function ( response ) {
			if ( response && response.success ) {
				var isOn = 1 === response.data.value;
				$button.text( isOn ? $button.data( 'on-label' ) || 'On' : $button.data( 'off-label' ) || 'Off' );
			}
		} ).always( function () {
			$button.prop( 'disabled', false );
		} );
	} );

	$( document ).on( 'click', '.client-reviews-delete', function ( event ) {
		event.preventDefault();

		var $link = $( this );

		// eslint-disable-next-line no-alert
		if ( ! window.confirm( ClientReviewsAdmin.i18n.confirmDelete ) ) {
			return;
		}

		$.post( ClientReviewsAdmin.ajaxUrl, {
			action: 'client_reviews_delete',
			id: $link.data( 'id' ),
			nonce: $link.data( 'nonce' ),
		} ).done( function ( response ) {
			if ( response && response.success ) {
				$link.closest( 'tr' ).fadeOut( 200, function () {
					$( this ).remove();
				} );
			}
		} );
	} );
} )( jQuery );
