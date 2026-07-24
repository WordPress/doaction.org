<?php

if ( ! defined( 'ABSPATH' ) ) exit;

class do_action_tools {
	/**
	 * The single instance of do_action.
	 * @var 	object
	 * @access  private
	 * @since 	1.0.0
	 */
	private static $_instance = null;

	public function __construct ( $parent = null ) {

		$this->parent = $parent;

		add_action( 'admin_menu', array( $this, 'add_tools_page' ) );

		// Tools actions
		add_action( 'admin_init', array( $this, 'send_email' ) );
		add_action( 'admin_init', array( $this, 'export_csv' ) );

		add_action( 'wp_ajax_format_email_preview', array( $this, 'format_email_preview' ) );

		add_action( 'wp_ajax_fetch_event_orgs', array( $this, 'fetch_event_orgs' ) );
	}

	public function add_tools_page() {
	    add_menu_page( __( 'do_action Tools', 'do-action' ), __( 'do_action Tools', 'do-action' ), 'use_do_action_tools', 'do-action-tools', array( $this, 'tools_page' ), 'dashicons-admin-tools', 9 );
	}

	public function tools_page() {

	    // Build page HTML
		$html = '<div class="wrap" id="' . $this->parent->_token . '_tools">' . "\n";
			$html .= '<h2>' . __( 'do_action Tools', 'do-action' ) . '</h2>' . "\n";

			$tab = 'email';
			if ( isset( $_GET['tab'] ) && $_GET['tab'] ) {
				$tab = $_GET['tab'];
			}

			$tabs = array(
				'email' => __( 'Email', 'do-action' ),
				'export' => __( 'Export', 'do-action' ),
			);

			$html .= '<h2 class="nav-tab-wrapper">' . "\n";

			$c = 0;
			foreach ( $tabs as $id => $label ) {

				// Set tab class
				$class = 'nav-tab';
				if ( ! isset( $_GET['tab'] ) ) {
					if ( 0 == $c ) {
						$class .= ' nav-tab-active';
					}
				} else {
					if ( isset( $_GET['tab'] ) && $id == $_GET['tab'] ) {
						$class .= ' nav-tab-active';
					}
				}

				// Set tab link
				$tab_link = add_query_arg( array( 'tab' => $id ) );
				if ( isset( $_GET['settings-updated'] ) ) {
					$tab_link = remove_query_arg( 'settings-updated', $tab_link );
				}

				if ( isset( $_GET['mail_sent'] ) ) {
					$tab_link = remove_query_arg( 'mail_sent', $tab_link );
				}

				// Output tab
				$html .= '<a href="' . esc_url( $tab_link ) . '" class="' . esc_attr( $class ) . '">' . esc_html( $label ) . '</a>' . "\n";

				++$c;
			}

			$html .= '</h2>' . "\n";

			$message_class = $message_note = '';

			if( isset( $_GET['mail_sent'] ) ) {
				switch( esc_html( $_GET['mail_sent'] ) ) {
					case 'success':
						$message_class = 'updated';
						$message_note = __( 'Email sent successfully.', 'do-action' );
					break;
					case 'error':
						$message_class = 'error';
						$message_note = __( 'There was an error sending your email - please try again.', 'do-action' );
					break;
				}
			} elseif( isset( $_GET['export'] ) && 'failed' == $_GET['export'] ) {
				$message_class = 'error';
				$message_note = __( 'There was an error exporting the data - please try again.', 'do-action' );
			}

			if( $message_class && $message_note ) {
				$html .= '<div class="' . esc_attr( $message_class ) . ' notice is-dismissible">' . "\n";
					$html .= '<p>' . $message_note . '</p>' . "\n";
				$html .= '</div>' . "\n";
			}

			$html .= '<form method="post" id="poststuff" action="" enctype="multipart/form-data" name="do-action-tools">' . "\n";

			if( 'email' == $tab ) {

				$event_args = array(
					'post_type' => 'event',
					'post_status' => 'publish',
					'posts_per_page' => -1,
					'fields' => 'ids',
					'orderby' => 'title',
					'order' => 'ASC',
				);

				if( current_user_can( 'organiser' ) ) {
					$event_args['author'] = get_current_user_id();
				}

				$events = get_posts( $event_args );

				// Select recipient event
				$html .= '<p>' . "\n";
					$html .= '<label for="recipient_event">' . __( 'Event:', 'do-action' ) . '</label>' . "\n";
					$html .= '<select id="recipient_event" name="recipient_event">' . "\n";
						$html .= '<option value="0">' . __( '-- Select event --', 'do-action' ) . '</option>' . "\n";
						foreach( $events as $event_id ) {
							$html .= '<option value="' . intval( $event_id ) . '">' . esc_html( get_the_title( $event_id ) ) . '</option>' . "\n";
						}
					$html .= '</select>' . "\n";
				$html .= '</p>' . "\n";

				// Select recipient organisation
				$html .= '<p id="recipient-org-wrapper">' . "\n";
					$html .= '<label for="recipient_org">' . __( 'Organisation(s):', 'do-action' ) . '</label>' . "\n";
					$html .= '<p>' . "\n";
						$html .= '<em>' . __( 'If you don\'t select any organisations, then the email will be sent to the applicable recipients from all of them.', 'do-action' ) . '</em>' . "\n";
					$html .= '</p>' . "\n";
					$html .= '<span id="recipient-org-select-wrapper">' . "\n";
						$html .= '<select id="recipient_orgs" name="recipient_orgs[]" multiple="multiple" disabled="disabled">' ."\n";
						$html .= '</select>' ."\n";
					$html .= '</span>' . "\n";
				$html .= '</p>' . "\n";

				// Select recipient roles
				$html .= '<p>' . "\n";
					$html .= '<label for="recipient_roles">' . __( 'Recipient roles:', 'do-action' ) . '</label>' . "\n";
					$html .= '<ul>' . "\n";
						$html .= '<li><label for="recipient-organiser"><input type="checkbox" name="recipient_roles[]" id="recipient-organiser" value="organiser" checked="checked" />' . __( 'Event organiser', 'do-action' ) . '</label></li>' . "\n";
						$html .= '<li><label for="recipient-npo"><input type="checkbox" name="recipient_roles[]" id="recipient-npo" value="npo" checked="checked" />' . __( 'Non-profit organisation', 'do-action' ) . '</label></li>' . "\n";

						$all_roles = get_terms( array( 'taxonomy' => 'role', 'hide_empty' => false ) );
						$dev_done = false;
						foreach( $all_roles as $role ) {
							$role_name = $role->name;
							$role_slug = $role->slug;
							if( in_array( $role_name, array( 'Developer 1', 'Developer 2', 'Developer 3', 'Developer 4', 'Developer 5', 'Developer 6',  ) ) ) {
								if( $dev_done ) {
									continue;
								}
								$role_name = __( 'Developer', 'do-action' );
								$role_slug = 'developer';
								$dev_done = true;
							}
							$html .= '<li><label for="recipient-' . esc_attr( $role_slug ) . '"><input type="checkbox" name="recipient_roles[]" id="recipient-' . esc_attr( $role_slug ) . '" value="' . esc_attr( $role_slug ) . '" checked="checked" />' . esc_html( $role_name ) . '</label></li>' . "\n";
						}

					$html .= '</ul>' . "\n";
				$html .= '</p>' . "\n";

				$html .= '<hr/>' . "\n";

				$html .= '<p>' . "\n";
					$html .= '<em>' . sprintf( __( 'The following placeholders are available for the email subject and body: %1$s, %2$s, %3$s and %4$s', 'do-action' ), '<code>{{NAME}}</code>', '<code>{{EMAILADDRESS}}</code>', '<code>{{NONPROFIT}}</code>', '<code>{{ROLE}}</code>' ) . '</em>' . "\n";
				$html .= '</p>' . "\n";

				// Set email subject
				$html .= '<p>' . "\n";
					$html .= '<label for="email_subject">' . __( 'Email subject:', 'do-action' ) . '</label>' . "\n";
					$html .= '<input type="text" class="large-text" id="email_subject" name="email_subject" />' . "\n";
				$html .= '</p>' . "\n";

				// Set email body
				$html .= '<p>' . "\n";
					$html .= '<label for="email_body">' . __( 'Email body:', 'do-action' ) . '</label>' . "\n";
					ob_start();
					wp_editor( '', 'email_body' );
					$html .= ob_get_clean();
					$html .= wp_nonce_field( 'do_action_send_email', '_wpnonce', true, false ) . "\n";
					$html .= '<input type="hidden" name="send_do_action_email" value="true" />' . "\n";
				$html .= '</p>' . "\n";

				// Send email
				$html .= '<p class="submit">' . "\n";
					$html .= '<input type="hidden" name="tab" value="' . esc_attr( $tab ) . '" />' . "\n";
					$html .= '<input type="button" id="do-action-preview-email" class="button-secondary" value="' . __( 'Preview', 'do-action' ) . '" />' . "\n";
					$html .= '<input name="Submit" type="submit" class="button-primary" value="' . __( 'Send', 'do-action' ) . '" />' . "\n";
				$html .= '</p>' . "\n";

				$html .= '<div id="do-action-email-preview-wrapper">' . "\n";
					$html .= '<p>' . "\n";
						$html .= '<em>' . __( 'Note that email previews use demo data taken from the entire database to replace the placeholders, so they may not reflect data from the selected event/roles.', 'do-action' ) . '</em>' . "\n";
					$html .= '</p>' . "\n";
					$html .= '<div id="do-action-email-preview" class="postbox-container">' . "\n";
						$html .= '<div class="postbox">' . "\n";
							$html .= '<h2 class="hndle"><span>' . __( 'Email preview', 'do-action' ) . '</span></h2>' . "\n";
							$html .= '<div class="inside">' . "\n";
								$html .= '<div class="spinner-wrapper"><span class="spinner is-active"></span></div>' . "\n";
							$html .= '</div>' . "\n";
						$html .= '</div>' . "\n";
					$html .= '</div>' . "\n";
				$html .= '</div>' . "\n";

			} elseif( 'export' == $tab ) {

				$event_args = array(
					'post_type' => 'event',
					'post_status' => 'publish',
					'posts_per_page' => -1,
					'fields' => 'ids',
					'orderby' => 'title',
					'order' => 'ASC',
				);

				if( current_user_can( 'organiser' ) ) {
					$event_args['author'] = get_current_user_id();
				}

				$events = get_posts( $event_args );

				// Select export event
				$html .= '<p>' . "\n";
					$html .= '<label for="recipient_event">' . __( 'Event:', 'do-action' ) . '</label>' . "\n";
					$html .= '<select id="recipient_event" name="recipient_event">' . "\n";
						$html .= '<option value="0">' . __( '-- Select event --', 'do-action' ) . '</option>' . "\n";
						foreach( $events as $event_id ) {
							$html .= '<option value="' . intval( $event_id ) . '">' . esc_html( get_the_title( $event_id ) ) . '</option>' . "\n";
						}
					$html .= '</select>' . "\n";
				$html .= '</p>' . "\n";

				// Select export organisation(s)
				$html .= '<p id="recipient-org-wrapper">' . "\n";
					$html .= '<label for="recipient_org">' . __( 'Organisation(s):', 'do-action' ) . '</label>' . "\n";
					$html .= '<p>' . "\n";
						$html .= '<em>' . __( 'If you don\'t select any organisations, then export data will include people from all of them.', 'do-action' ) . '</em>' . "\n";
					$html .= '</p>' . "\n";
					$html .= '<span id="recipient-org-select-wrapper">' . "\n";
						$html .= '<select id="recipient_orgs" name="recipient_orgs[]" multiple="multiple" disabled="disabled">' ."\n";
						$html .= '</select>' ."\n";
					$html .= '</span>' . "\n";
				$html .= '</p>' . "\n";

				// Select export role(s)
				$html .= '<p>' . "\n";
					$html .= '<label for="recipient_roles">' . __( 'Recipient roles:', 'do-action' ) . '</label>' . "\n";
					$html .= '<ul>' . "\n";
						$html .= '<li><label for="recipient-organiser"><input type="checkbox" name="recipient_roles[]" id="recipient-organiser" value="organiser" checked="checked" />' . __( 'Event organiser', 'do-action' ) . '</label></li>' . "\n";
						$html .= '<li><label for="recipient-npo"><input type="checkbox" name="recipient_roles[]" id="recipient-npo" value="npo" checked="checked" />' . __( 'Non-profit organisation', 'do-action' ) . '</label></li>' . "\n";

						$all_roles = get_terms( array( 'taxonomy' => 'role', 'hide_empty' => false ) );
						$dev_done = false;
						foreach( $all_roles as $role ) {
							$role_name = $role->name;
							$role_slug = $role->slug;
							if( in_array( $role_name, array( 'Developer 1', 'Developer 2', 'Developer 3', 'Developer 4', 'Developer 5', 'Developer 6',  ) ) ) {
								if( $dev_done ) {
									continue;
								}
								$role_name = __( 'Developer', 'do-action' );
								$role_slug = 'developer';
								$dev_done = true;
							}
							$html .= '<li><label for="recipient-' . esc_attr( $role_slug ) . '"><input type="checkbox" name="recipient_roles[]" id="recipient-' . esc_attr( $role_slug ) . '" value="' . esc_attr( $role_slug ) . '" checked="checked" />' . esc_html( $role_name ) . '</label></li>' . "\n";
						}

					$html .= '</ul>' . "\n";
				$html .= '</p>' . "\n";

				// Generate export data
				$html .= '<p class="submit">' . "\n";
					$html .= '<input type="hidden" name="tab" value="' . esc_attr( $tab ) . '" />' . "\n";
					$html .= wp_nonce_field( 'do_action_export_csv', '_wpnonce', true, false ) . "\n";
					$html .= '<input type="hidden" name="export_do_action_data" value="export" />' . "\n";
					$html .= '<input name="Submit" type="submit" class="button-primary" value="' . __( 'Download CSV', 'do-action' ) . '" />' . "\n";
				$html .= '</p>' . "\n";

			}

			$html .= '</form>' . "\n";

		$html .= '</div>' . "\n";

		echo $html;
	}

	public function format_email_preview() {

		// Only users who can use the tools may generate an email preview (which reads
		// recipient PII out of the database).
		if( ! current_user_can( 'use_do_action_tools' ) ) {
			wp_send_json_error( '', 403 );
		}

		$event_id = intval( $_POST['event_id'] );

		// The tools capability alone does not scope an organiser to their own events, so
		// confirm the current user may edit this specific event before reading its PII.
		if( $event_id && ! current_user_can( 'edit_post', $event_id ) ) {
			wp_send_json_error( '', 403 );
		}

		if( ! $event_id ) {
			$response = array(
				'email_subject' => __( 'Please select an event.', 'do-action' ),
				'email_body' => '&nbsp;',
			);
			wp_send_json( $response );
		}

		$roles = get_terms( array( 'hide_empty' => true, 'fields' => 'id=>slug' ) );

		$recipients = $this->get_people_data( $event_id, $roles );

		if( empty( $recipients ) ) {
			wp_send_json( array(
				'email_subject' => __( 'No recipient data available for this event.', 'do-action' ),
				'email_body' => '&nbsp;',
			) );
		}

		$recipient = $recipients[ array_rand( $recipients, 1 ) ];

		$subject = $this->format_email( $_POST['email_subject'], $recipient, 'subject' );
		$message = $this->format_email( $_POST['email_body'], $recipient, 'body' );

		$response = array(
			'email_subject' => $subject,
			'email_body' => $message,
		);

		wp_send_json( $response );
	}

	public function fetch_event_orgs () {

		// Only users who can use the tools may list an event's organisations.
		if( ! current_user_can( 'use_do_action_tools' ) ) {
			wp_send_json_error( '', 403 );
		}

		$event_id = intval( $_POST['event_id'] );

		// Confirm the current user may edit this specific event before listing its orgs.
		if( $event_id && ! current_user_can( 'edit_post', $event_id ) ) {
			wp_send_json_error( '', 403 );
		}

		$select = '<select id="recipient_orgs" name="recipient_orgs[]" multiple="multiple" disabled="disabled">' . "\n" . '</select>' . "\n";

		if( ! $event_id ) {
			$response = array(
				'org_select' => $select,
			);
			wp_send_json( $response );
		}

		$orgs = get_post_meta( $event_id, 'nonprofits', true );

		if( ! $orgs ) {
			$response = array(
				'org_select' => $select,
			);
			wp_send_json( $response );
		}

		$select = '<select id="recipient_orgs" name="recipient_orgs[]" multiple="multiple">' . "\n";

		foreach( $orgs as $id ) {
			$select .= '<option value="' . esc_attr( $id ) . '">' . esc_html( get_the_title( $id ) ) . '</option>' . "\n";
		}

		$select .= '</select>' . "\n";

		$response = array(
			'org_select' => $select,
		);
		wp_send_json( $response );
	}

	public function send_email () {

		if( ! isset( $_POST['send_do_action_email'] ) ) {
			return false;
		}

		// This handler runs on admin_init, which fires for unauthenticated requests to
		// admin-post.php. Require the tools capability and a valid nonce before doing anything.
		if( ! current_user_can( 'use_do_action_tools' ) ) {
			return false;
		}

		check_admin_referer( 'do_action_send_email' );

		$event_id = intval( $_POST['recipient_event'] );

		// The tools capability alone does not scope an organiser to their own events, so
		// confirm the current user may edit this specific event before mailing its people.
		if( $event_id && ! current_user_can( 'edit_post', $event_id ) ) {
			return false;
		}

		$sent_mails = $failed_mails = array();
		if( $event_id ) {

			$organiser_email = get_post_meta( $event_id, 'organiser_email', true );

			if( $organiser_email ) {

				// Get selected organisations
				$orgs = false;
				if( ! empty( $_POST['recipient_orgs'] ) ) {
					$orgs = array_map( 'intval', (array) $_POST['recipient_orgs'] );
				}

				// Sanitise selected roles
				$roles = isset( $_POST['recipient_roles'] ) ? array_map( 'esc_html', (array) $_POST['recipient_roles'] ) : array();

				$recipients = $this->get_people_data( $event_id, $roles, $orgs );

				if( 0 < count( $recipients ) ) {

					$from = sprintf( __( 'do_action %s', 'do-action' ), get_the_title( $event_id ) ) . ' <' . $organiser_email . '>';

					$headers = array();
					$headers[] = 'From: ' . $from;
					$headers[] = 'Content-Type: text/html; charset=UTF-8';

					foreach( $recipients as $recipient ) {

						if( ! $recipient['name'] || ! $recipient['email'] ) {
							continue;
						}

						$to = $recipient['name'] . ' <' . $recipient['email'] . '>';
						$subject = $this->format_email( $_POST['email_subject'], $recipient, 'subject' );
						$message = $this->format_email( $_POST['email_body'], $recipient, 'body' );

						$sent = wp_mail( $to, $subject, $message, $headers );
						if( $sent ) {
							$sent_mails[] = $recipient;
						} else {
							$failed_mails[] = $recipient;
						}
					}

				}
			}
		}

		if( 0 < count( $sent_mails ) ) {
			$result = 'success';
		} else {
			$result = 'error';
		}

		$redirect_url = add_query_arg( 'mail_sent', $result );
		wp_safe_redirect( $redirect_url );
		exit;
	}

	public function export_csv () {

		if( ! isset( $_POST['export_do_action_data'] ) ) {
			return false;
		}

		// This handler runs on admin_init, which fires for unauthenticated requests to
		// admin-post.php. Require the tools capability and a valid nonce before doing anything.
		if( ! current_user_can( 'use_do_action_tools' ) ) {
			return false;
		}

		check_admin_referer( 'do_action_export_csv' );

		$event_id = intval( $_POST['recipient_event'] );

		// The tools capability alone does not scope an organiser to their own events, so
		// confirm the current user may edit this specific event before exporting its PII.
		if( $event_id && ! current_user_can( 'edit_post', $event_id ) ) {
			return false;
		}

		if( $event_id ) {

			// Get and sanitise selected organisations
			$orgs = false;
			if( ! empty( $_POST['recipient_orgs'] ) ) {
				$orgs = array_map( 'intval', (array) $_POST['recipient_orgs'] );
			}

			// Sanitise selected roles
			$roles = isset( $_POST['recipient_roles'] ) ? array_map( 'esc_html', (array) $_POST['recipient_roles'] ) : array();

			$data = $this->get_people_data( $event_id, $roles, $orgs );

			if( 0 < count( $data ) ) {

				// Stream the CSV straight to the browser. Writing it into the public uploads
				// directory (as this previously did) left participant PII at a guessable,
				// unauthenticated URL that is web-served regardless of the attachment's
				// post_status, and the file was never cleaned up.
				$filename = 'do_action-export-' . gmdate( 'Y-m-d' ) . '.csv';

				nocache_headers();
				header( 'Content-Type: text/csv; charset=utf-8' );
				header( 'Content-Disposition: attachment; filename="' . $filename . '"' );

				$handler = fopen( 'php://output', 'w' );

				// Generate CSV headers
				fputcsv( $handler, array( __( 'Name', 'do-action' ), __( 'Email', 'do-action' ), __( 'Phone', 'do-action' ), __( 'Role', 'do-action' ), __( 'Organisation', 'do-action' ) ) );

				// Insert export data
				foreach ( $data as $person ) {
					fputcsv( $handler, array_map( array( $this, 'escape_csv_cell' ), $person ) );
				}

				fclose( $handler );
				exit;

			}
		}

	}

	/**
	 * Neutralise spreadsheet formula injection in an exported CSV cell.
	 *
	 * A value starting with =, +, -, @, tab or CR is treated as a formula when the CSV is
	 * opened in Excel/Sheets. Participant/org values come from less-trusted input, so prefix
	 * any such cell with a single quote to force it to be read as text.
	 *
	 * @param  mixed  $value Cell value.
	 * @return string
	 */
	private function escape_csv_cell( $value ) {
		$value = (string) $value;
		if ( '' !== $value && in_array( $value[0], array( '=', '+', '-', '@', "\t", "\r" ), true ) ) {
			$value = "'" . $value;
		}
		return $value;
	}

	private function get_people_data ( $event_id = 0, $roles = array(), $orgs = false ) {

		$recipients = array();

		if( ! $event_id || 0 == count( $roles ) ) {
			return $recipients;
		}

		foreach( $roles as $role ) {
			if( 'organiser' == $role ) {
				$recipient_set = $this->get_organiser_email_recipient( $event_id );
			} elseif( 'npo' == $role ) {
				$recipient_set = $this->get_nonprofit_email_recipient( $event_id, $orgs );
			} elseif( 'developer' == $role ) {
				$dev1recipients = $this->get_participant_email_recipients( $event_id, 'developer-1', $orgs );
				$dev2recipients = $this->get_participant_email_recipients( $event_id, 'developer-2', $orgs );
				$dev3recipients = $this->get_participant_email_recipients( $event_id, 'developer-3', $orgs );
				$recipient_set = array_merge( $dev1recipients, $dev2recipients, $dev3recipients );
			} else {
				$recipient_set = $this->get_participant_email_recipients( $event_id, $role, $orgs );
			}
			$recipients = array_merge( $recipients, $recipient_set );
		}

		return $recipients;
	}

	private function get_organiser_email_recipient ( $event_id = 0 ) {

		$recipients = array();

		if( ! $event_id ) {
			return $recipients;
		}

		$organiser_email = get_post_meta( $event_id, 'organiser_email', true );

		if( ! $organiser_email ) {
			return $recipients;
		}

		$event_title = sprintf( __( 'do_action %1$s', 'do-action' ), get_the_title( $event_id ) );
		$organiser_name = sprintf( __( '%1$s Organiser', 'do-action' ), $event_title );

		$recipients[] = array(
			'name' => $organiser_name,
			'email' => $organiser_email,
			'phone' => '',
			'role' => __( 'Event Organiser', 'do-action' ),
			'org' => $event_title,
		);

		return $recipients;

	}

	private function get_nonprofit_email_recipient ( $event_id = 0, $orgs = false ) {

		$recipients = array();

		if( ! $event_id ) {
			return $recipients;
		}

		if( ! $orgs ) {
			$orgs = get_post_meta( $event_id, 'nonprofits', true );
		}

		if( $orgs && 0 < count( $orgs ) ) {
			foreach( $orgs as $id ) {

				$contact_name = get_post_meta( $id, 'contact_name', true );
				$contact_email = get_post_meta( $id, 'contact_email', true );
				$contact_number = get_post_meta( $id, 'contact_number', true );

				if( $contact_name && $contact_email ) {
					$recipients[] = array(
						'name' => $contact_name,
						'email' => $contact_email,
						'phone' => $contact_number,
						'role' => __( 'Non-Profit Representative', 'do-action' ),
						'org' => get_the_title( $id ),
					);
				}
			}
		}

		return $recipients;

	}

	private function get_participant_email_recipients ( $event_id = 0, $role = '', $orgs = false ) {

		$recipients = array();

		if( ! $event_id || ! $role ) {
			return $recipients;
		}

		if( ! $orgs ) {
			$orgs = get_post_meta( $event_id, 'nonprofits', true );
		}

		if( $orgs && 0 < count( $orgs ) ) {

			$role_obj = get_term_by( 'slug', $role, 'role' );

			$role_name = $role_obj->name;
			if( in_array( $role_name, array( 'Developer 1', 'Developer 2', 'Developer 3' ) ) ) {
				$role_name = __( 'Developer', 'do-action' );
			}
			foreach( $orgs as $id ) {
				$participant_name = get_post_meta( $id, $role . '_name', true );
				$participant_email = get_post_meta( $id, $role . '_email_address', true );
				$participant_phone = get_post_meta( $id, $role . '_phone_number', true );

				if( $participant_name && $participant_email ) {
					$recipients[] = array(
						'name' => $participant_name,
						'email' => $participant_email,
						'phone' => $participant_phone,
						'role' => $role_name,
						'org' => get_the_title( $id ),
					);
				}

			}
		}

		return $recipients;

	}

	private function format_email ( $message = '', $recipient = array(), $context = 'body' ) {

		$name = '';
		if( isset( $recipient['name'] ) ) {
			$name = esc_html( $recipient['name'] );
		}

		$org = '';
		if( isset( $recipient['org'] ) ) {
			$org = esc_html( $recipient['org'] );
		}

		$role = '';
		if( isset( $recipient['role'] ) ) {
			$role = esc_html( $recipient['role'] );
		}

		$email = '';
		if( isset( $recipient['email'] ) ) {
			$email = esc_html( $recipient['email'] );
		}

		$message = str_replace( array( '{{NAME}}', '{{NONPROFIT}}', '{{ROLE}}', '{{EMAILADDRESS}}' ), array( $name, $org, $role, $email ), $message );

		if( 'subject' == $context ) {
			$message = esc_html( stripslashes_deep( $message ) );
		} else {
			$message = stripslashes_deep( wp_kses_stripslashes( wpautop( $message ) ) );
		}

		return $message;
	}

	/**
	 * Main do_action_tools Instance
	 *
	 * Ensures only one instance of do_action_tools is loaded or can be loaded.
	 *
	 * @since 1.0.0
	 * @static
	 * @see do_action_functions()
	 * @return Main do_action_tools instance
	 */
	public static function instance ( $file = '', $version = '1.0.0' ) {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self( $file, $version );
		}
		return self::$_instance;
	} // End instance ()

	/**
	 * Cloning is forbidden.
	 *
	 * @since 1.0.0
	 */
	public function __clone () {
		_doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?' ), $this->_version );
	} // End __clone ()

	/**
	 * Unserializing instances of this class is forbidden.
	 *
	 * @since 1.0.0
	 */
	public function __wakeup () {
		_doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?' ), $this->_version );
	} // End __wakeup ()
}