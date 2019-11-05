jQuery( document ).ready( function ($) {

	// Activate the datepicker for event dates
	$('.datepicker').datepicker({
		dateFormat: 'd MM yy',
		altField: '#date_save',
		altFormat: 'yy-mm-dd',
	});

	// Activate the Geocomplete API for event locations
	$('.geocomplete').geocomplete({
		details: 'form#post',
	});

	$('#do_action_tools #recipient_event').change( function() {
		var event_id = $(this).val();

		var data = {
			'action': 'fetch_event_orgs',
			'event_id': event_id,
		};

		$.post( ajaxurl, data, function( data ) {
			if( data.org_select ) {
				$( '#recipient-org-select-wrapper' ).html( data.org_select );
				$( '#recipient_orgs' ).chosen();
			} else {
				$( '#recipient-org-select-wrapper' ).html( '' );
			}
		});

	});

	// Preview email via AJAX before sending
	$('#do-action-preview-email').click( function () {

		$( '#do-action-email-preview-wrapper' ).slideDown( 'fast' );

		var event_id = $('#recipient_event').val();
		var subject = $('#email_subject').val();
		var body = $('iframe#email_body_ifr').contents().find('html').find('body').html();

		var data = {
			'action': 'format_email_preview',
			'event_id': event_id,
			'email_subject': subject,
			'email_body': body
		};

		$.post( ajaxurl, data, function( data ) {
			$( '#do-action-email-preview h2 span' ).html( data.email_subject );
			$( '#do-action-email-preview .inside' ).html( data.email_body );
		});
	});


});