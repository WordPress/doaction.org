jQuery( document ).ready( function ($) {
	$( '.non-profit-selector' ).change( function () {
		var val = $( this ).val();
		$( '.non-profit-selector' ).closest( 'li' ).removeClass( 'selected' );
		if( $( this ).length > 0 ) {
			$( this ).closest( 'li' ).addClass( 'selected' );
			$( '.role-description' ).hide();
			$( '#participant-details-wrapper' ).hide();
			$( '.role-selector-list' ).hide();
			$( '#role-list-' + val ).show();
			$( '#non-profit-role-wrapper' ).slideDown( 'fast' );
			scroll_to_element( '#non-profit-role-wrapper' );
		}
	});

	$( '.role-selector' ).change( function () {
		var val = $( this ).val();
		$( '.role-selector' ).closest( 'label' ).removeClass( 'selected' );
		if( $( this ).length > 0 ) {
			$( this ).closest( 'label' ).addClass( 'selected' );
			$( '.role-description' ).hide();
			$( '#role-description-' + val ).show();
			$( '#participant-details-wrapper' ).slideDown( 'fast' );
			scroll_to_element( '#participant-details-wrapper' );
		}
	});

	$( '#participant-name' ).keyup( function() {
		var name = $(this).val();
		var email = $( '#participant-email' ).val();
		if( name && email ) {
			$( '#form-submit-row' ).slideDown( 'fast' );
			$( '#participant-form-submit' ).prop( 'disabled', false );
		} else {
			$( '#form-submit-row' ).slideUp( 'fast' );
			$( '#participant-form-submit' ).prop( 'disabled', true );
		}
	});

	$( '#participant-email' ).keyup( function() {
		var email = $(this).val();
		var name = $( '#participant-name' ).val();
		if( name && email ) {
			$( '#form-submit-row' ).slideDown( 'fast' );
			$( '#participant-form-submit' ).prop( 'disabled', false );
		} else {
			$( '#form-submit-row' ).slideUp( 'fast' );
			$( '#participant-form-submit' ).prop( 'disabled', true );
		}
	});

	function validate_applicaton_form() {
		var org_name = $( '#org_name' ).val();
		var org_url = $( '#org_url' ).val();
		var contact_name = $( '#contact_name' ).val();
		var contact_email = $( '#contact_email' ).val();

		if( org_name && org_url && contact_name && contact_email ) {
			$( '#application-form-submit' ).prop( 'disabled', false );
		} else {
			$( '#application-form-submit' ).prop( 'disabled', true );
		}
	}

	function scroll_to_element( target ) {
	    var topoffset = 30;
	    var speed = 800;
	    var destination = jQuery( target ).offset().top - topoffset;
	    jQuery( 'html:not(:animated),body:not(:animated)' ).animate( { scrollTop: destination}, speed, function() {

	    });
	    return false;
	}

	$( '#org_name' ).keyup( function() {
		validate_applicaton_form();
	});
	$( '#org_url' ).keyup( function() {
		validate_applicaton_form();
	});
	$( '#contact_name' ).keyup( function() {
		validate_applicaton_form();
	});
	$( '#contact_email' ).keyup( function() {
		validate_applicaton_form();
	});

})