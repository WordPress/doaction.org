<?php

if ( ! defined( 'ABSPATH' ) ) exit;

class do_action_Admin_API {

	/**
	 * Constructor function
	 */
	public function __construct () {
		add_action( 'save_post', array( $this, 'save_meta_boxes' ), 10, 1 );
	}

	/**
	 * Generate HTML for displaying fields
	 * @param  array   $field Field data
	 * @param  boolean $echo  Whether to echo the field HTML or return it
	 * @return void
	 */
	public function display_field ( $data = array(), $post = false, $echo = true ) {

		// Get field info
		if ( isset( $data['field'] ) ) {
			$field = $data['field'];
		} else {
			$field = $data;
		}

		// Check for prefix on option name
		$option_name = '';
		if ( isset( $data['prefix'] ) ) {
			$option_name = $data['prefix'];
		}

		// Get saved data
		$data = '';
		if ( $post ) {

			// Get saved field data
			$option_name .= $field['id'];
			$option = get_post_meta( $post->ID, $field['id'], true );

			// Get data to display in field
			if ( isset( $option ) ) {
				$data = $option;
			}

		} else {

			// Get saved option
			$option_name .= $field['id'];
			$option = get_option( $option_name );

			// Get data to display in field
			if ( isset( $option ) ) {
				$data = $option;
			}

		}

		// Show default data if no option saved and default is supplied
		if ( $data === false && isset( $field['default'] ) ) {
			$data = $field['default'];
		} elseif ( $data === false ) {
			$data = '';
		}

		$html = '';

		switch( $field['type'] ) {

			case 'text':
			case 'url':
			case 'email':
				$html .= '<input id="' . esc_attr( $field['id'] ) . '" type="text" name="' . esc_attr( $option_name ) . '" placeholder="' . esc_attr( $field['placeholder'] ) . '" value="' . esc_attr( $data ) . '" />' . "\n";
			break;

			case 'password':
			case 'number':
			case 'hidden':
				$min = '';
				if ( isset( $field['min'] ) ) {
					$min = ' min="' . esc_attr( $field['min'] ) . '"';
				}

				$max = '';
				if ( isset( $field['max'] ) ) {
					$max = ' max="' . esc_attr( $field['max'] ) . '"';
				}
				$html .= '<input id="' . esc_attr( $field['id'] ) . '" type="' . esc_attr( $field['type'] ) . '" name="' . esc_attr( $option_name ) . '" placeholder="' . esc_attr( $field['placeholder'] ) . '" value="' . esc_attr( $data ) . '"' . $min . '' . $max . '/>' . "\n";
			break;

			case 'text_secret':
				$html .= '<input id="' . esc_attr( $field['id'] ) . '" type="text" name="' . esc_attr( $option_name ) . '" placeholder="' . esc_attr( $field['placeholder'] ) . '" value="" />' . "\n";
			break;

			case 'textarea':
				$html .= '<br/><textarea id="' . esc_attr( $field['id'] ) . '" rows="5" cols="50" name="' . esc_attr( $option_name ) . '" placeholder="' . esc_attr( $field['placeholder'] ) . '">' . esc_textarea( $data ) . '</textarea><br/>'. "\n";
			break;

			case 'checkbox':
				$checked = '';
				if ( $data && 'on' == $data ) {
					$checked = 'checked="checked"';
				}
				$html .= '<input id="' . esc_attr( $field['id'] ) . '" type="' . esc_attr( $field['type'] ) . '" name="' . esc_attr( $option_name ) . '" ' . $checked . '/>' . "\n";
			break;

			case 'checkbox_multi':
				foreach ( $field['options'] as $k => $v ) {
					$checked = false;
					if ( in_array( $k, $data ) ) {
						$checked = true;
					}
					$html .= '<label for="' . esc_attr( $field['id'] . '_' . $k ) . '" class="checkbox_multi"><input type="checkbox" ' . checked( $checked, true, false ) . ' name="' . esc_attr( $option_name ) . '[]" value="' . esc_attr( $k ) . '" id="' . esc_attr( $field['id'] . '_' . $k ) . '" /> ' . esc_html( $v ) . '</label> ';
				}
			break;

			case 'radio':
				foreach ( $field['options'] as $k => $v ) {
					$checked = false;
					if ( $k == $data ) {
						$checked = true;
					}
					$html .= '<label for="' . esc_attr( $field['id'] . '_' . $k ) . '"><input type="radio" ' . checked( $checked, true, false ) . ' name="' . esc_attr( $option_name ) . '" value="' . esc_attr( $k ) . '" id="' . esc_attr( $field['id'] . '_' . $k ) . '" /> ' . esc_html( $v ) . '</label> ';
				}
			break;

			case 'select':
				$html .= '<select name="' . esc_attr( $option_name ) . '" id="' . esc_attr( $field['id'] ) . '">';
				foreach ( $field['options'] as $k => $v ) {
					$selected = false;
					if ( $k == $data ) {
						$selected = true;
					}
					$html .= '<option ' . selected( $selected, true, false ) . ' value="' . esc_attr( $k ) . '">' . esc_html( $v ) . '</option>';
				}
				$html .= '</select> ';
			break;

			case 'select_multi':
				$html .= '<select name="' . esc_attr( $option_name ) . '[]" id="' . esc_attr( $field['id'] ) . '" multiple="multiple">';
				foreach ( $field['options'] as $k => $v ) {
					$selected = false;
					if( ! is_array( $data ) ) {
						$data = array( $data );
					}
					if ( in_array( $k, $data ) ) {
						$selected = true;
					}
					$html .= '<option ' . selected( $selected, true, false ) . ' value="' . esc_attr( $k ) . '">' . esc_html( $v ) . '</option>';
				}
				$html .= '</select> ';
			break;

			case 'image':
				$image_thumb = '';
				if ( $data ) {
					$image_thumb = wp_get_attachment_thumb_url( $data );
				}
				$html .= '<img id="' . esc_attr( $option_name ) . '_preview" class="image_preview" src="' . esc_url( $image_thumb ) . '" /><br/>' . "\n";
				$html .= '<input id="' . esc_attr( $option_name ) . '_button" type="button" data-uploader_title="' . esc_attr__( 'Upload an image', 'do-action' ) . '" data-uploader_button_text="' . esc_attr__( 'Use image', 'do-action' ) . '" class="image_upload_button button" value="' . esc_attr__( 'Upload new image', 'do-action' ) . '" />' . "\n";
				$html .= '<input id="' . esc_attr( $option_name ) . '_delete" type="button" class="image_delete_button button" value="' . esc_attr__( 'Remove image', 'do-action' ) . '" />' . "\n";
				$html .= '<input id="' . esc_attr( $option_name ) . '" class="image_data_field" type="hidden" name="' . esc_attr( $option_name ) . '" value="' . esc_attr( $data ) . '"/><br/>' . "\n";
			break;

			case 'color':
				?><div class="color-picker" style="position:relative;">
			        <input type="text" name="<?php esc_attr_e( $option_name ); ?>" class="color" value="<?php esc_attr_e( $data ); ?>" />
			        <div style="position:absolute;background:#FFF;z-index:99;border-radius:100%;" class="colorpicker"></div>
			    </div>
			    <?php
			break;

			case 'datepicker':
				if( ! $data ) {
					$data = date( 'Y-m-d', time() );
				}
				$display_date = date( 'j F Y', strtotime( $data ) );
				$html .= '<input id="' . esc_attr( $field['id'] ) . '_display" type="text" class="datepicker" placeholder="' . esc_attr( $field['placeholder'] ) . '" value="' . esc_attr( $display_date ) . '" />' . "\n";
				$html .= '<input id="' . esc_attr( $field['id'] ) . '_save" type="hidden" name="' . esc_attr( $option_name ) . '" value="' . esc_attr( $data ) . '" />' . "\n";
			break;

			case 'geocomplete':
				$html .= '<input id="' . esc_attr( $field['id'] ) . '" name="' . esc_attr( $option_name ) . '" type="text" class="geocomplete" placeholder="' . esc_attr( $field['placeholder'] ) . '" value="' . esc_attr( $data ) . '" />' . "\n";
			break;

		}

		switch( $field['type'] ) {

			case 'checkbox_multi':
			case 'radio':
			case 'select_multi':
				$html .= '<br/><span class="description">' . wp_kses_post( $field['description'] ) . '</span>';
			break;

			case 'hidden':
			break;

			default:
				if ( ! $post ) {
					$html .= '<label for="' . esc_attr( $field['id'] ) . '">' . "\n";
				}

				if( isset( $field['description'] ) ) {
					$html .= '<span class="description">' . wp_kses_post( $field['description'] ) . '</span>' . "\n";
				}

				if ( ! $post ) {
					$html .= '</label>' . "\n";
				}
			break;
		}

		if ( ! $echo ) {
			return $html;
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Field values and attributes are escaped above; form controls must remain intact.
		echo $html;

	}

	/**
	 * Validate form field
	 * @param  string $data Submitted value
	 * @param  string $type Type of field to validate
	 * @return string       Validated value
	 */
	public function validate_field ( $data = '', $type = 'text' ) {

		switch ( $type ) {
			case 'text':
				$data = sanitize_text_field( $data );
				break;
			case 'textarea':
				$data = sanitize_textarea_field( $data );
				break;
			case 'url':
				$data = esc_url_raw( $data );
				break;
			case 'email':
				$data = is_email( $data );
				break;
		}

		return $data;
	}

	/**
	 * Add meta box to the dashboard
	 * @param string $id            Unique ID for metabox
	 * @param string $title         Display title of metabox
	 * @param array  $post_types    Post types to which this metabox applies
	 * @param string $context       Context in which to display this metabox ('advanced' or 'side')
	 * @param string $priority      Priority of this metabox ('default', 'low' or 'high')
	 * @param array  $callback_args Any axtra arguments that will be passed to the display function for this metabox
	 * @return void
	 */
	public function add_meta_box ( $id = '', $title = '', $post_types = array(), $context = 'advanced', $priority = 'default', $callback_args = null ) {

		// Get post type(s)
		if ( ! is_array( $post_types ) ) {
			$post_types = array( $post_types );
		}

		// Generate each metabox
		foreach ( $post_types as $post_type ) {
			add_meta_box( $id, $title, array( $this, 'meta_box_content' ), $post_type, $context, $priority, $callback_args );
		}
	}

	/**
	 * Display metabox content
	 * @param  object $post Post object
	 * @param  array  $args Arguments unique to this metabox
	 * @return void
	 */
	public function meta_box_content ( $post, $args ) {

		$field_post_type = str_replace( '-', '_', $post->post_type );
		$fields = apply_filters( $field_post_type . '_custom_fields', array(), $post->post_type );

		if ( ! is_array( $fields ) || 0 == count( $fields ) ) return;

		echo '<div class="custom-field-panel">' . "\n";

		wp_nonce_field( 'do_action_save_meta_' . $post->ID, 'do_action_meta_nonce' );

		foreach ( $fields as $field ) {

			if ( ! isset( $field['metabox'] ) ) continue;

			if ( ! is_array( $field['metabox'] ) ) {
				$field['metabox'] = array( $field['metabox'] );
			}

			if ( in_array( $args['id'], $field['metabox'] ) ) {
				$this->display_meta_box_field( $field, $post );
			}

		}

		echo '</div>' . "\n";

	}

	/**
	 * Dispay field in metabox
	 * @param  array  $field Field data
	 * @param  object $post  Post object
	 * @return void
	 */
	public function display_meta_box_field ( $field = array(), $post ) {

		if ( ! is_array( $field ) || 0 == count( $field ) ) return;

		if( 'hidden' == $field['type'] ) {
			$field = $this->display_field( $field, $post, false ) . "\n";
		} else {
			$field = '<p class="form-field"><label for="' . esc_attr( $field['id'] ) . '">' . esc_html( $field['label'] ) . '</label>' . $this->display_field( $field, $post, false ) . '</p>' . "\n";
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- display_field() escapes its values; the surrounding label is escaped above.
		echo $field;
	}

	/**
	 * Save metabox fields
	 * @param  integer $post_id Post ID
	 * @return void
	 */
	public function save_meta_boxes ( $post_id = 0 ) {

		if ( ! $post_id ) return;

		// Don't clobber meta on autosaves/revisions, and require our nonce so a forged
		// request can't set the plugin's meta keys or wipe them by omitting the fields.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
		if ( wp_is_post_revision( $post_id ) ) return;

		if ( ! isset( $_POST['do_action_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['do_action_meta_nonce'] ) ), 'do_action_save_meta_' . $post_id ) ) {
			return;
		}

		// The current user must be allowed to edit this specific post.
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$post_type = get_post_type( $post_id );
		$field_post_type = str_replace( '-', '_', $post_type );
		$fields = apply_filters( $field_post_type . '_custom_fields', array(), $post_type );

		if ( ! is_array( $fields ) || 0 == count( $fields ) ) return;

		foreach ( $fields as $field ) {
			if ( isset( $_REQUEST[ $field['id'] ] ) ) {
				if ( 'event' === $post_type && 'nonprofits' === $field['id'] ) {
					$orgs = map_deep( wp_unslash( $_REQUEST[ $field['id'] ] ), 'sanitize_text_field' );
					if ( ! is_array( $orgs ) ) {
						continue;
					}
					foreach ( $orgs as $org_id ) {
						if ( ! do_action_functions()->is_event_nonprofit_allowed( $post_id, $org_id )
							|| ! current_user_can( 'edit_post', (int) $org_id )
							|| ! isset( $field['options'][ (int) $org_id ] ) ) {
							continue 2;
						}
					}
					$orgs = array_values( array_unique( array_map( 'intval', $orgs ) ) );
					// Preserve explicit approval when an administrator associates another organiser's nonprofit.
					update_post_meta( $post_id, '_do_action_approved_nonprofits', $orgs );
					update_post_meta( $post_id, $field['id'], $orgs );
					continue;
				}
				if ( 'url' === $field['type'] ) {
					$value = is_string( $_REQUEST[ $field['id'] ] ) ? esc_url_raw( wp_unslash( $_REQUEST[ $field['id'] ] ) ) : '';
				} elseif ( 'email' === $field['type'] ) {
					$value = is_string( $_REQUEST[ $field['id'] ] ) ? sanitize_email( wp_unslash( $_REQUEST[ $field['id'] ] ) ) : '';
				} else {
					$value = map_deep( wp_unslash( $_REQUEST[ $field['id'] ] ), 'sanitize_textarea_field' );
				}
				update_post_meta( $post_id, $field['id'], wp_slash( $this->validate_field( $value, $field['type'] ) ) );
			} else {
				if ( 'event' === $post_type && 'nonprofits' === $field['id'] ) {
					delete_post_meta( $post_id, '_do_action_approved_nonprofits' );
				}
				update_post_meta( $post_id, $field['id'], '' );
			}
		}
	}

}
