<?php

if ( ! defined( 'ABSPATH' ) ) exit;

class do_action {

	/**
	 * The single instance of do_action.
	 * @var 	object
	 * @access  private
	 * @since 	1.0.0
	 */
	private static $_instance = null;

	/**
	 * The version number.
	 * @var     string
	 * @access  public
	 * @since   1.0.0
	 */
	public $_version;

	/**
	 * The token.
	 * @var     string
	 * @access  public
	 * @since   1.0.0
	 */
	public $_token;

	/**
	 * The main plugin file.
	 * @var     string
	 * @access  public
	 * @since   1.0.0
	 */
	public $file;

	/**
	 * The main plugin directory.
	 * @var     string
	 * @access  public
	 * @since   1.0.0
	 */
	public $dir;

	/**
	 * The plugin assets directory.
	 * @var     string
	 * @access  public
	 * @since   1.0.0
	 */
	public $assets_dir;

	/**
	 * The plugin assets URL.
	 * @var     string
	 * @access  public
	 * @since   1.0.0
	 */
	public $assets_url;

	/**
	 * Suffix for Javascripts.
	 * @var     string
	 * @access  public
	 * @since   1.0.0
	 */
	public $script_suffix;

	/**
	 * Constructor function.
	 * @access  public
	 * @since   1.0.0
	 * @return  void
	 */
	public function __construct ( $file = '', $version = '1.0.0' ) {
		$this->_version = $version;
		$this->_token = 'do_action';

		// Load plugin environment variables
		$this->file = $file;
		$this->dir = dirname( $this->file );
		$this->assets_dir = trailingslashit( $this->dir ) . 'assets';
		$this->assets_url = esc_url( trailingslashit( plugins_url( '/assets/', $this->file ) ) );

		$this->akismet_api_key = '98e6b103f2e3';

		$this->post_types = array(
			'event' => array( 'plural' => __( 'Events', 'do-action' ), 'single' => __( 'Event', 'do-action' ), 'options' => array( 'menu_icon' => 'dashicons-calendar-alt' ) ),
			'non-profit' => array( 'plural' => __( 'Non-profits', 'do-action' ), 'single' => __( 'Non-profit', 'do-action' ), 'options' => array( 'menu_icon' => 'dashicons-store' ) ),
			'sponsor' => array( 'plural' => __( 'Sponsors', 'do-action' ), 'single' => __( 'Sponsor', 'do-action' ), 'options' => array( 'menu_icon' => 'dashicons-heart' ) ),
		);

		$this->taxonomies = array(
			'role' => array( 'plural' => __( 'Roles', 'do-action' ), 'single' => __( 'Role', 'do-action' ), 'post_types' => array( 'non-profit' ), 'args' => array() ),
		);

		$this->script_suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		register_activation_hook( $this->file, array( $this, 'install' ) );

		// Set up post types & taxonomies
		add_action( 'init', array( $this, 'register_post_types' ), 1 );
		add_action( 'init', array( $this, 'register_taxonomies' ), 1 );
		add_filter( 'dashboard_glance_items', array( $this, 'glance_items' ), 10, 1 );
		add_action( 'save_post', array( $this, 'set_nonprofits_private' ), 10, 2 );
		add_action( 'restrict_manage_posts', array( $this, 'filter_non_profits_list_table' ), 10, 2 );

		// Register custom fields & meta boxes
		add_action( 'init', array( $this, 'custom_fields' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ), 10, 2 );

		// Process the sign up form
		add_action( 'wp', array( $this, 'check_signup_form' ) );

		// Process the application form
		add_action( 'wp', array( $this, 'check_application_form' ) );

		// Cusomtise dashboard display
		add_action( 'admin_init', array( $this, 'redirect_dashboard' ) );
		add_action( 'admin_menu', array( $this, 'modify_admin_menu' ), 999 );
		add_filter( 'request', array( $this, 'modify_admin_lists' ) );
		add_filter( 'wp_count_posts', array( $this, 'modify_post_counts' ), 10, 3 );
		add_filter( 'removable_query_args', array( $this, 'removable_query_args' ), 10, 1 );

		// Register custom sidebars
		add_action( 'widgets_init', array( $this, 'register_sidebars' ) );

		// Add shortcodes
		add_shortcode( 'upcoming_events', array( $this, 'upcoming_events' ) );
		add_shortcode( 'event_map', array( $this, 'event_map' ) );
		add_shortcode( 'event_sponsors', array( $this, 'event_sponsors' ) );
		add_shortcode( 'past_events', array( $this, 'past_events' ) );

		// Load frontend JS & CSS
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ), 10 );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ), 10 );

		// Load admin JS & CSS
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ), 10, 1 );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_styles' ), 10, 1 );

		// Load API for generic admin functions
		if ( is_admin() ) {
			$this->admin = new do_action_Admin_API();
		}

		// Handle localisation
		add_action( 'plugins_loaded', array( $this, 'load_localisation' ) );
	} // End __construct ()

	public function upcoming_events () {
		$output = '';

		ob_start();
		?>
		<h2><?php _e( 'Upcoming Events', 'do-action' ); ?></h2>

		<ul class="upcoming-events">
		<?php

		$today = date( 'Y-m-d' );

		$args = array(
			'post_type' => 'event',
			'meta_query' => array(
				array(
					'key' => 'date',
					'value' => $today,
					'compare' => '>=',
					'type' => 'date',
				),
			),
			'meta_key' => 'date',
			'orderby' => 'meta_value',
			'order' => 'ASC',
			'lang' => 'en',
			'posts_per_page' => -1,
		);

		$events = get_posts( $args );

		foreach( $events as $event ) { ?>
			<li>
				<span class="event-image">
					<?php if ( has_post_thumbnail( $event->ID ) ) {
						$image_array = wp_get_attachment_image_src( get_post_thumbnail_id( $event->ID ), 'medium' );
						$image_url = $image_array[0];
					?>
					<a href="<?php echo esc_url( get_permalink( $event->ID ) ); ?>">
						<img src="<?php echo $image_url; ?>" />
					</a>
					<?php } ?>
				</span>
				<span class="event-details">
					<a href="<?php echo esc_url( get_permalink( $event->ID ) ); ?>">
						<span class="event-title"><?php echo $event->post_title; ?></span>
						<span class="event-date"><?php echo date( 'j F Y', strtotime( get_post_meta( $event->ID, 'date', true ) ) ); ?></span>
					</a>
					<?php
					$status = get_post_meta( $event->ID, 'event_status', true );

					switch( $status ) {
						case 'accepting_applications':
							$action_button = __( 'Apply!', 'do-action' );
						break;

						case 'accepting_signups':
							$action_button = __( 'Sign up!', 'do-action' );
						break;

						default:
							$action_button = __( 'Read more', 'do-action' );
						break;
					}
					?>
					<a class="event-button button" href="<?php echo esc_url( get_permalink( $event->ID ) ); ?>"><?php esc_html_e( $action_button ); ?></a>
				</span>
			</li>
		<?php } ?>
		</ul>
		<?php

		$output = ob_get_clean();

		return $output;
	}

	public function event_map () {
		global $post;

		if( ! $post ) {
			return;
		}

		$lat = get_post_meta( $post->ID, 'lat', true );
		$lng = get_post_meta( $post->ID, 'lng', true );

		if( ! $lat || ! $lng ) {
			return;
		}

		$venue_name = get_post_meta( $post->ID, 'venue_name', true );
		$venue_address = get_post_meta( $post->ID, 'venue_location', true );
		$venue_address = str_replace( ',', "<br/>", $venue_address );

		$infowindow = '<b>' . $venue_name . '</b><br/>' . $venue_address;

		ob_start();
		?>
		<div id="event-map"></div>
	    <script>
	      function initMap() {

	      	var latlng = {lat: <?php echo $lat; ?>, lng: <?php echo $lng; ?>};

	        var map = new google.maps.Map(document.getElementById('event-map'), {
	          center: latlng,
	          zoom: 14
	        });

	        var marker_image = 'https://doaction.org/wp-content/uploads/2016/05/do_action-map-pin.png';

	        var infowindow = new google.maps.InfoWindow({
				content: '<?php echo $infowindow; ?>'
			});

	        var marker = new google.maps.Marker({
				position: latlng,
				map: map,
				title: '<?php echo $post->post_title; ?>',
				icon: marker_image
			});

			marker.addListener('click', function() {
				infowindow.open(map, marker);
			});
	      }
	    </script>
		<?php

		return ob_get_clean();
	}

	public function event_sponsors () {
		global $post;

		if( ! $post ) {
			return;
		}

		$sponsor_ids = get_post_meta( $post->ID, 'sponsors', true );

		if( $sponsor_ids ) {
			shuffle( $sponsor_ids );
		}

		ob_start();

		?>
		<h3 class="widget-title"><?php _e( 'Sponsors', 'do-action' ); ?></h3>
		<ul id="event_sponsors">
			<li>
				<a href="https://doaction.org/sponsor/wordpress-foundation/" title="WordPress Foundation">
					<img src="https://doaction.org/wp-content/uploads/2018/07/wp-foundation-hoz-logo.png" alt="WordPress Foundation" class="sponsor-image" />
				</a>
			</li>
			<?php
			if( $sponsor_ids ) {
				foreach( $sponsor_ids as $id ) {
					$title = get_the_title( $id );
					$logo = wp_get_attachment_url( get_post_thumbnail_id( $id ) );
					$url = get_permalink( $id );
					?>
					<li>
						<a href="<?php echo $url; ?>" title="<?php echo $title; ?>">
							<img src="<?php echo $logo; ?>" alt="<?php echo $title; ?>" class="sponsor-image" />
						</a>
					</li>
					<?php
				}
			}
			?>
		</ul>
		<?php

		return ob_get_clean();
	}

	public function event_form() {
		global $post;

		if( ! $post ) {
			return;
		}

		$status = get_post_meta( $post->ID, 'event_status', true );

		switch( $status ) {

			case 'announced':
			break;

			case 'accepting_applications':
				$this->event_application_form( $post );
			break;

			case 'selecting_nonprofits':
				?>
				<h2><?php _e( 'Non-profit applications are now closed', 'do-action' ); ?></h2>
				<p><?php _e( 'We are now deciding on the final list of organisations for this hackathon - once we have finalised the list, we will be in touch with all of the applications to let them know. We will then open up participant sign-ups for the event.', 'do-action' ); ?></p>
				<?php
			break;

			case 'accepting_signups':
				$this->event_sign_up_form( $post );
			break;

			case 'completed':
				?>
				<h2><?php _e( 'This hackathon has ended', 'do-action' ); ?></h2>
				<p><?php _e( 'Thank you to everyone who was involved!', 'do-action' ); ?></p>
				<?php
			break;
		}
	}

	public function event_application_form ( $event = null ) {

		if( ! $event ) {
			return;
		}

		?>
		<div id="event-application-form">
			<form name="event-application-form" action="" method="post">

				<h2><?php _e( 'Apply for your organisation to be a part of this hackathon', 'do-action' ); ?></h2>
				<p><?php _e( 'Fill in the form below in order for your non-profit organisation to be a part of this event and to potentially be one of the organiseations that receives a brand new website.', 'do-action' ); ?></p>

				<p>
					<label for="org_name"><?php _e( 'What is the name of your organisation?', 'do-action' ); ?></label>
					<input id="org_name" type="text" name="org_name" value="" />
				</p>

				<p>
					<label for="org_url"><?php _e( 'What is your organisation\'s website address?', 'do-action' ); ?></label>
					<input id="org_url" type="text" name="org_url" value="" /><br/>
					<span class="form-description"><?php _e( 'This is the address for your current website, Facebook page, or any other page that tells us a bit about your organisation.', 'do-action' ); ?></span>
				</p>

				<p>
					<label for="org_description"><?php _e( 'What is your organisation all about?', 'do-action' ); ?></label>
					<textarea id="org_description" name="org_description"></textarea><br/>
					<span class="form-description"><?php _e( 'Tell us what your organisation does, how you work, what your mission statement is, or anything else that you feel is relevant.', 'do-action' ); ?></span>
				</p>

				<p>
					<label for="org_achieve"><?php _e( 'What do you hope to achieve with a new website for your organisation?', 'do-action' ); ?></label>
					<textarea id="org_achieve" name="org_achieve"></textarea><br/>
					<span class="form-description"><?php _e( 'Be as descriptive as you like - this is to give us an idea of what you are looking for and is not a final analysis of your needs.', 'do-action' ); ?></span>
				</p>

				<p>
					<label for="contact_name"><?php _e( 'What is your name?', 'do-action' ); ?></label>
					<input id="contact_name" type="text" name="contact_name" value="" /><br/>
					<span class="form-description"><?php _e( 'This individual will be the primary contact between us and your organisation.', 'do-action' ); ?></span>
				</p>

				<p>
					<label for="contact_email"><?php _e( 'What is your email address?', 'do-action' ); ?></label>
					<input id="contact_email" type="email" name="contact_email" value="" /><br/>
					<span class="form-description"><?php _e( 'This will be the primary contact email address between us and your organisation.', 'do-action' ); ?></span>
				</p>

				<input type="hidden" name="doaction_application_sent" value="true" />
				<input type="submit" disabled value="<?php esc_attr_e( 'Apply!', 'do-action' ); ?>" id="application-form-submit" />
			</form>
		</div>
		<?php

	}

	public function get_pll_current_language() {
		if ( function_exists( 'pll_current_language' ) ) {
			if ( pll_current_language() == 'en' ) {
				return null;
			} else {
				return '/' . pll_current_language();
			}
		} else {
		return null;
		}
	}	

	public function event_sign_up_form ( $event = null ) {

		if( ! $event ) {
			return;
		}

		$orgs = get_post_meta( $event->ID, 'nonprofits', true );

		if( $orgs && 0 < count( $orgs ) ) {

			?>
			<div id="event-sign-up-form">
				<form name="event-sign-up-form" action="" method="post">
					<h2><?php _e( 'Sign up as a participant for this hackathon', 'do-action' ); ?></h2>
					<p><?php printf( __( 'To be a part of this event, simply fill in the form below and your participation will be final. Read our %1$sparticipant\'s guide%2$s for more details on what is expected of you when you sign up.', 'do-action' ), '<a href="' . get_site_url() . get_pll_current_language_path() . '/participants-guide/">', '</a>' ); ?></p>

					<h3><?php _e( 'Select an organisation', 'do-action' ); ?></h3>
					<p class="form-description"><?php _e( 'Click on a non-profit organisation to select it and, once you have done so, you will be able to select your role on the build team for that organisation. If an organisation is faded out, then it has no roles available.', 'do-action' ); ?></p>

					<ul class="non-profit-options">
						<?php
						shuffle( $orgs );
						foreach ( $orgs as $id ) {
							$org = get_post( $id );
							$url = get_post_meta( $id, 'url', true );

							$label_class = '';
							$positions = __( '(All positions filled)', 'do-action' );
							$roles = get_the_terms( $id, 'role' );
							$available =  intval( count( $roles ) );
							foreach( $roles as $role ) {
								$participant = get_post_meta( $id, $role->slug . '_name' , true );
								if( $participant ) {
									--$available;
								}
							}
							if( $available ) {
								$label_class = 'available';
								$positions = sprintf( _n( '(%s position available)', '(%s positions available)', $available, 'do-action' ), $available );
							}
							?>
							<li>
								<label for="nonprofit-<?php esc_attr_e( $id ); ?>" class="<?php esc_attr_e( $label_class ); ?>">
									<?php // if( $available ) { ?>
										<input type="radio" class="non-profit-selector" value="<?php esc_attr_e( $id ); ?>" name="nonprofit" id="nonprofit-<?php esc_attr_e( $id ); ?>" />
									<?php // } ?>
									<span class="nonprofit-title"><?php esc_html_e( $org->post_title ); ?> <em><?php echo $positions; ?></em></span>
									<?php
									if( $url ) {
										echo '<span class="nonprofit-url">';
										printf( __( '%1$sWebsite%2$s', 'do-action' ), '<a href="' . esc_url( $url ) . '" target="_blank">', '</a>' );
										echo '</span><br/>';
									}
									?>
									<span class="nonprofit-excerpt"><?php echo wpautop( $org->post_excerpt ); ?></span>
									<p style="text-align:center;"><a class="button"><?php _e( 'Select', 'do-action' ); ?></a></p>
								</label>
							</li>
							<?php
						}
						?>
					</ul>

					<div id="non-profit-role-wrapper">
						<h3><?php _e( 'Select your role', 'do-action' ); ?></h3>
						<p class="form-description"><?php _e( 'Each build team has a selection of roles available - select the one that suits you the best. If a role is greyed out, then it has already been filled.', 'do-action' ); ?></p>

						<?php
						foreach ( $orgs as $id ) {
							$roles = get_the_terms( $id, 'role' );
							if( $roles && 0 < count( $roles ) ) {
								shuffle( $roles );
								?>
								<ul class="role-selector-list" id="role-list-<?php esc_attr_e( $id ); ?>">
									<?php
									foreach( $roles as $role ) {
										$participant = get_post_meta( $id, $role->slug . '_name' , true );
										$disabled = $role_tail = '';
										if( $participant ) {
											$disabled = 'disabled';
											$role_tail = ' - ' . $participant;
										}
										$role_name = $role->name;
										if( in_array( $role_name, array( 'Developer 1', 'Developer 2', 'Developer 3', 'Developer 4', 'Developer 5' ) ) ) {
											$role_name = __( 'Developer', 'do-action' );
										}
										?>
										<li class="<?php esc_attr_e( $disabled ); ?>">
											<label for="role-<?php esc_attr_e( $id ); ?>-<?php esc_attr_e( $role->term_id ); ?>">
												<input type="radio" class="role-selector <?php esc_attr_e( $role->slug ); ?>" value="<?php esc_attr_e( $role->term_id ); ?>" name="role" id="role-<?php esc_attr_e( $id ); ?>-<?php esc_attr_e( $role->term_id ); ?>" <?php esc_attr_e( $disabled ); ?> />
												<?php echo $role_name . $role_tail; ?>
											</label>
										</li>
										<?php
									}
									?>
								</ul>
								<?php
							}
						}

						$all_roles = get_terms( array( 'taxonomy' => 'role', 'hide_empty' => false ) );
						foreach( $all_roles as $role ) {
							$role_name = $role->name;
							if( in_array( $role_name, array( 'Developer 1', 'Developer 2', 'Developer 3', 'Developer 4', 'Developer 5' ) ) ) {
								$role_name = __( 'Developer', 'do-action' );
							}
							?>
							<p class="role-description" id="role-description-<?php esc_attr_e( $role->term_id ); ?>">
								<strong><?php echo $role_name; ?>:</strong> <?php echo $role->description; ?>
							</p>
							<?php
						}
						?>

					</div>

					<div id="participant-details-wrapper">
						<h3><?php _e( 'Fill in your details', 'do-action' ); ?></h3>
						<p class="form-description"><?php _e( 'All we need from you now are your details so we can provide you with further information about the event.', 'do-action' ); ?></p>

						<p>
							<label for="participant-name"><?php _e( 'Name:', 'do-action' ); ?></label><br/><input type="text" id="participant-name" name="participant_name" />
						</p>
						<p>
							<label for="participant-email"><?php _e( 'Email address:', 'do-action' ); ?></label><br/><input type="email" id="participant-email" name="participant_email" />
						</p>
						<p>
							<label for="participant-number"><?php _e( 'Phone number:', 'do-action' ); ?></label><br/><input type="text" id="participant-number" name="participant_number" />
						</p>
					</div>

					<div id="form-submit-row">
						<p class="form-description"><?php printf( __( 'By submitting this form you are confirming that you will attend the event on the listed date and that you have read through the %1$sparticipant\'s guide%2$s.', 'do-action' ), '<a href="' . get_site_url() . get_pll_current_language() . '/participants-guide/">', '</a>' ); ?></p>
						<input type="hidden" name="doaction_signed_up" value="true" />
						<input type="submit" disabled value="<?php esc_attr_e( 'Sign up!', 'do-action' ); ?>" id="participant-form-submit" />
					</div>

				</form>
			</div>
			<?php
		}
	}

	public function check_signup_form() {
		global $post;

		if( isset( $_POST['doaction_signed_up'] ) && 'true' == $_POST['doaction_signed_up'] ) {

			$signed_up = $this->process_signup_form_submission( $_POST, $post );

			if( $signed_up ) {
				$signup_result = 'success';
			} else {
				$signup_result = 'error';
			}

			$redirect_url = add_query_arg( 'signup', $signup_result, get_permalink( $post->ID ) );
			wp_safe_redirect( $redirect_url );
			exit;
		}
	}

	private function process_signup_form_submission ( $post = array(), $event = false ) {

		$org = get_post( intval( $post['nonprofit'] ) );
		$role = get_term( intval( $post['role'] ), 'role' );

		if( ! $org || ! $event || ! $role || is_wp_error( $role ) ) {
			return false;
		}

		// Make sure we don't overwrite an existing participant
		$current_participant = get_post_meta( $org->ID, $role->slug . '_name', true );
		if( $current_participant ) {
			return false;
		}

		$participant_name = esc_html( $post['participant_name'] );
		$participant_email = esc_html( $post['participant_email'] );
		$participant_number = esc_html( $post['participant_number'] );

		// Check for spam submissions
		require_once( 'lib/akismet.fuspam.php' );

		if( function_exists( 'fuspam' ) ) {

			// Get most accurate IP address for user
			$user_ip = getenv('HTTP_CLIENT_IP')?:
			getenv('HTTP_X_FORWARDED_FOR')?:
			getenv('HTTP_X_FORWARDED')?:
			getenv('HTTP_FORWARDED_FOR')?:
			getenv('HTTP_FORWARDED')?:
			getenv('REMOTE_ADDR');

			$data['blog'] = 'https://doaction.org/';
			$data['user_ip'] = $user_ip;
			$data['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
			$data['referrer'] = $_SERVER['HTTP_REFERER'];
			$data['permalink'] = get_post_permalink( $event->ID );
			$data['comment_type'] = 'registration';
			$data['comment_author'] = $participant_name;
			$data['comment_author_email'] = $participant_email;
			$data['comment_author_url'] = '';
			$data['comment_content'] = '';

			$is_spam = fuspam( $data, 'check-spam', $this->akismet_api_key );

			if( 'true' == $is_spam ) {
				return false;
			}
		}

		update_post_meta( $org->ID, $role->slug . '_name', $participant_name );
		update_post_meta( $org->ID, $role->slug . '_email_address', $participant_email );
		update_post_meta( $org->ID, $role->slug . '_phone_number', $participant_number );

		// If this is a Project Manager signing up, set a new random password
		if( 'project-manager' == $role->slug && ( ! $org->post_password || 'do_action' == $org->post_password ) ) {

			$new_password = $this->random_password( 10 );

			remove_action( 'save_post', array( $this, 'set_nonprofits_private' ), 10, 2 );

			wp_update_post( array( 'ID' => $org->ID, 'post_password' => $new_password ) );

			add_action( 'save_post', array( $this, 'set_nonprofits_private' ), 10, 2 );

			// Get updated post object
			$org = get_post( $org->ID );
		}

		$email_sent = $this->send_signup_email( $participant_email, $participant_name, $org, $role, $event );

		return true;
	}

	public function random_password( $length = 10 ) {
	    $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%?";
	    $password = substr( str_shuffle( $chars ), 0, $length );
	    return $password;
	}

	private function send_signup_email ( $email = '', $name = '', $org = fales, $role = false, $event = false ) {

		if( ! $email || ! $name || ! $org || ! $role || ! $event ) {
			return false;
		}

		$subject = __( 'Thank you for signing up!', 'do-action' );

		$from = sprintf( __( 'do_action %s', 'do-action' ), $event->post_title ) . ' <' . get_post_meta( $event->ID, 'organiser_email', true ) . '>';

		$headers[] = 'From: ' . $from;
		$headers[] = 'Content-Type: text/html; charset=UTF-8';

		ob_start();

		?>
		<p><?php printf( __( 'Hi %s,', 'do-action' ), $name ); ?></p>

		<p><?php printf( __( 'Thank you for signing up as %1$s for %2$s at the %3$s do_action hackathon. You have been added to the build team and we will be in touch with more details in the coming weeks.', 'do-action' ), $role->name, $org->post_title, $event->post_title ); ?></p>

		<?php if( 'project-manager' == $role->slug ) { ?>
			<p><?php printf( __( 'As the Project Manager you will need to be in touch with your chosen non-profit organisation before the event. To that end, you will find the non-profit contact details as well as your team\'s contact details %1$sright here%2$s. The password to view the info on that page is "%3$s" (without the quotation marks) and the team list will be filled up there as participants sign up.', 'do-action' ), '<a href="' . esc_url( get_permalink( $org->ID ) ) . '">', '</a>', $org->post_password ); ?></p>
		<?php } else { ?>
			<p><?php _e( 'Your team\'s Project Manager will also be in touch with you closer to the time regarding the non-profit you have chosen as well as any pre-planning you can do before the event.', 'do-action' ); ?></p>
		<?php } ?>

		<p><?php printf( __( 'Please make sure that you read our %1$sparticipant\'s guide%2$s for more details on what is expected of you now that you have signed up. You will also find vital information there explaining how the day will work and what you need to bring with you.', 'do-action' ), '<a href="' . get_site_url() . get_pll_current_language() . '/participants-guide/">', '</a>' ); ?></p>

		<p><?php printf( __( 'It would be really helpful if you sent %1$sthe sign up link%2$s to anyone that you know who might also be interested in participating in the day.', 'do-action' ), '<a href="' . esc_url( get_permalink( $event->ID ) ) . '">', '</a>' ); ?></p>

		<p><?php printf( __( 'Cheers,%sThe do_action team', 'do-action' ), '<br/>' ); ?></p>
		<?php

		$message = ob_get_clean();

		return wp_mail( $email, $subject, $message, $headers );
	}

	public function check_application_form() {
		global $post;

		if( isset( $_POST['doaction_application_sent'] ) && 'true' == $_POST['doaction_application_sent'] ) {

			$signed_up = $this->process_application_form_submission( $_POST, $post );

			if( $signed_up ) {
				$signup_result = 'success';
			} else {
				$signup_result = 'error';
			}

			$redirect_url = add_query_arg( 'application', $signup_result, get_permalink( $post->ID ) );
			wp_safe_redirect( $redirect_url );

		}
	}

	private function process_application_form_submission ( $post = array(), $event = false ) {

		if( ! $event  ) {
			return false;
		}

		// Get event organiser ID
		$organiser_id = get_post_field( 'post_author', $event->ID );

		// Get event data
		$title = esc_html( $post['org_name'] );
		$url = esc_sql( $post['org_url'] );
		$org_description = esc_html( $post['org_description'] );
		$org_achieve = esc_html( $post['org_achieve'] );
		$contact_name = esc_html( $post['contact_name'] );
		$contact_email = esc_html( $post['contact_email'] );

		// Set up post insert arguments
		$post_args = array(
			'post_title' => $title,
			'post_author' => $organiser_id,
			'post_type' => 'non-profit',
			'post_status' => 'publish',
			'post_excerpt' => $org_description,
			'post_password' => 'do_action',
		);

		// Check for spam submissions
		require_once( 'lib/akismet.fuspam.php' );

		if( function_exists( 'fuspam' ) ) {

			// Get most accurate IP address for user
			$user_ip = getenv('HTTP_CLIENT_IP')?:
			getenv('HTTP_X_FORWARDED_FOR')?:
			getenv('HTTP_X_FORWARDED')?:
			getenv('HTTP_FORWARDED_FOR')?:
			getenv('HTTP_FORWARDED')?:
			getenv('REMOTE_ADDR');

			$data['blog'] = 'https://doaction.org/';
			$data['user_ip'] = $user_ip;
			$data['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
			$data['referrer'] = $_SERVER['HTTP_REFERER'];
			$data['permalink'] = get_post_permalink( $event->ID );
			$data['comment_type'] = 'application';
			$data['comment_author'] = $contact_name;
			$data['comment_author_email'] = $contact_email;
			$data['comment_author_url'] = $url;
			$data['comment_content'] = $org_achieve;

			$is_spam = fuspam( $data, 'check-spam', $this->akismet_api_key );

			if( 'true' == $is_spam ) {
				return false;
			}
		}

		// Insert new post
		$org_id = wp_insert_post( $post_args );

		if( ! $org_id || is_wp_error( $org_id ) ) {
			return false;
		}

		// Add organisation meta to post
		update_post_meta( $org_id, 'url', $url );
		update_post_meta( $org_id, 'contact_name', $contact_name );
		update_post_meta( $org_id, 'contact_email', $contact_email );
		update_post_meta( $org_id, 'org_achieve', $org_achieve );
		update_post_meta( $org_id, 'event', $event->ID );

		// Get organisation post object
		$org = get_post( $org_id );

		// Set all roles to be active by default
		$all_roles = get_terms( array( 'taxonomy' => 'role', 'hide_empty' => false, 'fields' => 'ids' ) );

		// Force IDs to be interpreted as integers
		$roles = array();
		foreach( $all_roles as $role_id ) {
			$role_id = intval( $role_id );
			if( ! in_array( $role_id, array( 52, 54 ) ) ) {
				$roles[] = $role_id;
			}
		}

		wp_set_object_terms( $org_id, $roles, 'role' );

		// Send email to applicant
		$email_sent = $this->send_application_email( $contact_email, $contact_name, $org, $event );

		return true;
	}

	private function send_application_email ( $email = '', $name = '', $org = fales, $event = false ) {

		if( ! $email || ! $name || ! $org || ! $event ) {
			return false;
		}

		$subject = __( 'Thank you for your application!', 'do-action' );

		$from = sprintf( __( 'do_action %s', 'do-action' ), $event->post_title ) . ' <' . get_post_meta( $event->ID, 'organiser_email', true ) . '>';

		$headers[] = 'From: ' . $from;
		$headers[] = 'Content-Type: text/html; charset=UTF-8';

		ob_start();

		?>
		<p><?php printf( __( 'Hi %s,', 'do-action' ), $name ); ?></p>

		<p><?php printf( __( 'Thank you for applying for %1$s to be a part of the %2$s do_action hackathon. Your application has been received and we will be in touch once we have decided on the final list of non-profit organisations.', 'do-action' ), $org->post_title, $event->post_title ); ?></p>

		<p><?php printf( __( 'If you would like to know more about do_action and what it\'s all about, then you can find out more %1$shere%2$s.', 'do-action' ), '<a href="' . get_site_url() . get_pll_current_language() . '/about/">', '</a>' ); ?></p>

		<p><?php printf( __( 'If you know of any other non-profit organisation that could benefit from this event, then it would be great if you sent %1$sthe application link%2$s to them. The more the merrier!', 'do-action' ), '<a href="' . esc_url( get_permalink( $event->ID ) ) . '">', '</a>' ); ?></p>

		<p><?php printf( __( 'Cheers,%sThe do_action team', 'do-action' ), '<br/>' ); ?></p>
		<?php

		$message = ob_get_clean();

		return wp_mail( $email, $subject, $message, $headers );
	}

	public function set_nonprofits_private( $post_id, $post ) {

	    if( $post->post_type && 'non-profit' == $post->post_type ) {

	    	if( in_array( $post->post_status, array( 'auto-draft', 'trash' ) ) ) {
	    		return;
	    	}

            remove_action( 'save_post', array( $this, 'set_nonprofits_private' ), 10, 2 );

            $args = array(
            	'ID' => $post_id,
            	'post_status' => 'publish',
        	);

            // Only set the post password if one does not already exist
        	if( ! $post->post_password ) {
        		$args['post_password'] = 'do_action';
        	}

            wp_update_post( $args );

            add_action( 'save_post', array( $this, 'set_nonprofits_private' ), 10, 2 );

        }

        return;
	}

	public function nonprofit_team ( $org ) {

		if( is_int( $org ) ) {
			$org = get_post( $org );
		}

		$about = $org->post_excerpt;
		if( $about ) {
			?>
			<h3><?php _e( 'A bit about the organisation:', 'do-action' ); ?></h3>
			<?php echo wpautop( $about );
		}

		$org_achieve = get_post_meta( $org->ID, 'org_achieve', true );
		if( $org_achieve ) {
			?>
			<h3><?php _e( 'What the organisation hopes to achieve with a new website:', 'do-action' ); ?></h3>
			<?php echo wpautop( $org_achieve );
		}

		$website = get_post_meta( $org->ID, 'url', true );
		$contact_name = get_post_meta( $org->ID, 'contact_name', true );
		$contact_email = get_post_meta( $org->ID, 'contact_email', true );
		$contact_number = get_post_meta( $org->ID, 'contact_number', true );
		?>
		<h3><?php _e( 'Contact Details', 'do-action' ); ?></h3>
		<ul>
			<?php if( $website ) { ?>
				<li><?php printf( __( '%1$sWebsite%2$s', 'do-action' ), '<a href="' . esc_url( $website ) . '">', '</a>' ); ?></li>
			<?php } ?>
			<li><?php printf( __( 'Contact name: %1$s', 'do-action' ), '<b>' . $contact_name . '</b>' ); ?></li>
			<li><?php printf( __( 'Email address: %1$s', 'do-action' ), '<b>' . $contact_email . '</b>' ); ?></li>
			<li><?php printf( __( 'Phone number: %1$s', 'do-action' ), '<b>' . $contact_number . '</b>' ); ?></li>
		</ul>

		<h3><?php _e( 'Build Team', 'do-action' ); ?></h3>
		<?php

		$roles = get_the_terms( $org->ID, 'role' );

		if( $roles && 0 < count( $roles ) ) {
			shuffle( $roles );
			?>
			<ul class="build-team-list">
				<?php
				foreach( $roles as $role ) {
					$participant_name = get_post_meta( $org->ID, $role->slug . '_name', true );
					$role_tail = '';
					if( $participant_name ) {
						$participant_email = get_post_meta( $org->ID, $role->slug . '_email_address', true );
						$participant_number = get_post_meta( $org->ID, $role->slug . '_phone_number', true );
						if( $participant_number ) {
							$participant_number = ' | ' . $participant_number;
						}
						$role_tail = ': <b>' . $participant_name . ' &lt;' . $participant_email . '&gt;' . $participant_number . '</b>';
					}

					$role_name = $role->name;
					if( in_array( $role_name, array( 'Developer 1', 'Developer 2', 'Developer 3', 'Developer 4', 'Developer 5' ) ) ) {
						$role_name = __( 'Developer', 'do-action' );
					}
					?>
					<li><?php echo $role_name . $role_tail; ?></li>
					<?php
				}
				?>
			</ul>
			<?php
		}
	}

	public function past_events( $params = array() ) {

		ob_start();

		?>
		<ul class="upcoming-events">
		<?php

		$today = date( 'Y-m-d' );

		$args = array(
			'post_type' => 'event',
			'meta_query' => array(
				array(
					'key' => 'date',
					'value' => $today,
					'compare' => '<',
					'type' => 'date',
				),
				array(
					'key' => 'event_status',
					'value' => 'completed',
					'compare' => '=',
				),
			),
			'meta_key' => 'date',
			'orderby' => 'meta_value',
			'order' => 'DESC',
			'lang' => 'en',
			'posts_per_page' => -1,
		);

		$events = get_posts( $args );

		foreach( $events as $event ) {
			?>
			<li>
				<span class="event-image">
					<?php if ( has_post_thumbnail( $event->ID ) ) {
						$image_array = wp_get_attachment_image_src( get_post_thumbnail_id( $event->ID ), 'medium' );
						$image_url = $image_array[0];
					?>
					<a href="<?php echo esc_url( get_permalink( $event->ID ) ); ?>">
						<img src="<?php echo $image_url; ?>" />
					</a>
					<?php } ?>
				</span>
				<span class="event-details">
					<a href="<?php echo esc_url( get_permalink( $event->ID ) ); ?>">
						<span class="event-title"><?php echo $event->post_title; ?></span>
						<span class="event-date"><?php echo date( 'j F Y', strtotime( get_post_meta( $event->ID, 'date', true ) ) ); ?></span>
					</a>
					<a class="event-button button" href="<?php echo esc_url( get_permalink( $event->ID ) ); ?>"><?php _e( 'Read more', 'do-action' ); ?></a>
				</span>
			</li>
			<?php
		}

		?>
		</ul>
		<?php

		return ob_get_clean();
	}

	public function modify_admin_lists( $request ) {
		global $pagenow, $typenow;

		if( ! is_admin() ) {
			return $request;
		}

		if( 'edit.php' != $pagenow ) {
			return $request;
		}

		if( ! in_array( $typenow, array( 'event', 'non-profit' ) ) ) {
			return $request;
		}

		if( isset( $_GET['event-select'] ) && $_GET['event-select'] ) {
			$request['meta_query'][] = array(
				'key' => 'event',
				'value' => intval( $_GET['event-select'] ),
				'compare' => '=',
			);
		}

		if( ! current_user_can( 'organiser' ) ) {
			return $request;
		}

		$request['author'] = get_current_user_id();

		return $request;
	}

	public function modify_post_counts ( $counts, $type, $perm ) {
		global $pagenow, $typenow;

		if( ! current_user_can( 'organiser' ) ) {
			return $counts;
		}

		if( 'edit.php' != $pagenow ) {
			return $counts;
		}

		if( ! in_array( $typenow, array( 'event', 'non-profit' ) ) ) {
			return $counts;
		}

		$args = array(
			'post_type' => $type,
			'author' => get_current_user_id(),
			'posts_per_page' => -1
		);

		// Get all available statuses
		$stati = get_post_stati();

		// Update count object
		foreach( $stati as $status ) {
			$args['post_status'] = $status;
			$posts = get_posts( $args );
			$counts->$status = count( $posts );
		}

		return $counts;
	}

	public function storefront_post_header() {
		?>
		<header class="entry-header">
		<?php
		if ( is_single() ) {
			if ( 'post' == get_post_type() ) {
				storefront_posted_on();
			}
			the_title( '<h1 class="entry-title" itemprop="name headline">', '</h1>' );
		} else {
			if ( 'post' == get_post_type() ) {
				storefront_posted_on();
			}

			the_title( sprintf( '<h1 class="entry-title" itemprop="name headline"><a href="%s" rel="bookmark">', esc_url( get_permalink() ) ), '</a></h1>' );
		}

		if ( 'event' == get_post_type() ) {
			$date = get_post_meta( get_the_ID(), 'date', true );
			$venue = get_post_meta( get_the_ID(), 'venue_name', true );
			echo '<span class="event-date">' . date( 'j F Y', strtotime( $date ) ) . ' ' . __( 'at', 'do-action' ) . ' ' . $venue . '</span>';
		} elseif( 'sponsor' == get_post_type() ) {
			$url = get_post_meta( get_the_ID(), 'url', true );
			if( $url ) {
				echo '<span class="sponsor-meta"><a href="' . esc_url( $url ) . '" title="' . get_the_title() . '" target="_blank">' . __( 'Visit website', 'do-action' ) . '</a></span>';
			}
		}

		?>
		</header><!-- .entry-header -->
		<?php
	}

	public function custom_fields () {
		foreach( $this->post_types as $type => $details ) {
			$type = str_replace( '-', '_', $type );
			add_filter( $type . '_custom_fields', array( $this, $type . '_custom_fields' ), 10, 2 );
		}
	}

	public function event_custom_fields ( $fields, $post_type ) {

		$org_args = array(
			'post_type' => 'non-profit',
			'post_status' => 'publish',
			'orderby' => 'title',
			'order' => 'ASC',
			'fields' => 'ids',
			'posts_per_page' => -1,
		);

		if( current_user_can( 'organiser' ) ) {
			$org_args['author'] = get_current_user_id();
		}

		$nonprofits = get_posts( $org_args );
		$orgs = array();
		foreach( $nonprofits as $id ) {
			$orgs[ $id ] = get_the_title( $id );
		}

		$sponsor_args = array(
			'post_type' => 'sponsor',
			'post_status' => 'publish',
			'orderby' => 'title',
			'order' => 'ASC',
			'fields' => 'ids',
			'posts_per_page' => -1,
		);

		$sponsor_ids = get_posts( $sponsor_args );
		$sponsors = array();
		foreach( $sponsor_ids as $id ) {
			$sponsors[ $id ] = get_the_title( $id );
		}

		$event_status_options = array(
			'announced' => __( 'Announced', 'do-action' ),
			'accepting_applications' => __( 'Accepting non-profit applications', 'do-action' ),
			'selecting_nonprofits' => __( 'Selecting non-profits', 'do-action' ),
			'accepting_signups' => __( 'Accepting participant sign-ups', 'do-action' ),
			'completed' => __( 'Completed', 'do-action' ),
		);

		$fields = array(
			array(
				'id' => 'event_status',
				'label' => __( 'Status of event:', 'do-action' ),
				'type' => 'select',
				'default' => 'announced',
				'options' => $event_status_options,
				'metabox' => 'event_details',
			),
			array(
				'id' => 'organiser_email',
				'label' => __( 'Organiser email address:', 'do-action' ),
				'type' => 'email',
				'default' => '',
				'placeholder' => '',
				'metabox' => 'event_details',
			),
			array(
				'id' => 'date',
				'label' => __( 'Event date:', 'do-action' ),
				'type' => 'datepicker',
				'default' => '',
				'placeholder' => __( '25 March 2016', 'do-action' ),
				'metabox' => 'event_details',
			),
			array(
				'id' => 'nonprofits',
				'label' => __( 'Non-profits:', 'do-action' ),
				'type' => 'select_multi',
				'default' => '',
				'options' => $orgs,
				'metabox' => 'event_details',
			),
			array(
				'id' => 'sponsors',
				'label' => __( 'Sponsors:', 'do-action' ),
				'type' => 'select_multi',
				'default' => '',
				'options' => $sponsors,
				'metabox' => 'event_details',
			),
			array(
				'id' => 'venue_name',
				'label' => __( 'Venue name:', 'do-action' ),
				'type' => 'text',
				'default' => '',
				'placeholder' => '',
				'description' => __( 'Used for display purposes only.', 'do-action' ),
				'metabox' => 'event_details',
			),
			array(
				'id' => 'venue_location',
				'label' => __( 'Venue location:', 'do-action' ),
				'type' => 'geocomplete',
				'default' => '',
				'placeholder' => '',
				'metabox' => 'event_details',
			),
			array(
				'id' => 'lat',
				'label' => '',
				'type' => 'hidden',
				'default' => '',
				'placeholder' => '',
				'metabox' => 'event_details',
			),
			array(
				'id' => 'lng',
				'label' => '',
				'type' => 'hidden',
				'default' => '',
				'placeholder' => '',
				'metabox' => 'event_details',
			),
		);

		return $fields;
	}

	public function non_profit_custom_fields ( $fields, $post_type ) {
		global $post;

		$fields = array(
			array(
				'id' => 'url',
				'label' => __( 'URL:', 'do-action' ),
				'type' => 'url',
				'default' => '',
				'placeholder' => 'http://',
				'metabox' => 'non_profit_details',
			),
			array(
				'id' => 'contact_name',
				'label' => __( 'Contact name:', 'do-action' ),
				'type' => 'text',
				'default' => '',
				'placeholder' => '',
				'metabox' => 'non_profit_details',
			),
			array(
				'id' => 'contact_email',
				'label' => __( 'Contact email address:', 'do-action' ),
				'type' => 'email',
				'default' => '',
				'placeholder' => '',
				'metabox' => 'non_profit_details',
			),
			array(
				'id' => 'contact_number',
				'label' => __( 'Contact phone number:', 'do-action' ),
				'type' => 'text',
				'default' => '',
				'placeholder' => '',
				'metabox' => 'non_profit_details',
			),
			array(
				'id' => 'org_achieve',
				'label' => __( 'What the organisation hopes to achieve with a new website:', 'do-action' ),
				'type' => 'textarea',
				'default' => '',
				'placeholder' => '',
				'metabox' => 'non_profit_details',
			),
		);

		if( $post && isset( $post->ID ) ) {
			$roles = get_the_terms( $post, 'role' );

			if( $roles && 0 < count( $roles ) ) {
				foreach( $roles as $role ) {
					$fields[] = array(
						'id' => $role->slug . '_name',
						'label' => __( 'Name:', 'do-action' ),
						'type' => 'text',
						'default' => '',
						'placeholder' => '',
						'metabox' => 'non_profit_role_' . $role->slug,
					);
					$fields[] = array(
						'id' => $role->slug . '_email_address',
						'label' => __( 'Email address:', 'do-action' ),
						'type' => 'email',
						'default' => '',
						'placeholder' => '',
						'metabox' => 'non_profit_role_' . $role->slug,
					);
					$fields[] = array(
						'id' => $role->slug . '_phone_number',
						'label' => __( 'Phone number:', 'do-action' ),
						'type' => 'text',
						'default' => '',
						'placeholder' => '',
						'metabox' => 'non_profit_role_' . $role->slug,
					);
				}
			}
		}

		return $fields;
	}

	public function sponsor_custom_fields ( $fields, $post_type ) {

		$fields = array(
			array(
				'id' => 'url',
				'label' => __( 'URL:', 'do-action' ),
				'type' => 'url',
				'default' => '',
				'placeholder' => 'http://',
				'metabox' => 'sponsor_details',
			),
			array(
				'id' => 'contact_email',
				'label' => __( 'Contact email address:', 'do-action' ),
				'type' => 'email',
				'default' => '',
				'placeholder' => '',
				'metabox' => 'sponsor_details',
			),
		);

		return $fields;
	}

	public function add_meta_boxes ( $post_type, $post ) {
		foreach( $this->post_types as $type => $details ) {

			if( $type != $post_type ) {
				continue;
			}

			$field_type = str_replace( '-', '_', $type );
			$this->admin->add_meta_box( $field_type . '_details', sprintf( __( '%s Details' , 'do-action' ), $details['single'] ), array( $type ), 'normal', 'high' );

			if( 'non-profit' == $post_type ) {
				if( $post && isset( $post->ID ) ) {
					$roles = get_the_terms( $post, 'role' );

					if( $roles && 0 < count( $roles ) ) {

						foreach( $roles as $role ) {
							$this->admin->add_meta_box( $field_type . '_role_' . $role->slug, sprintf( __( 'Role: %s' , 'do-action' ), $role->name ), array( 'non-profit' ), 'advanced', 'high' );
						}
					}
				}
			} elseif( 'event' == $post_type ) {
				$orgs = get_post_meta( $post->ID, 'nonprofits', true );

				if( $orgs && 0 < count( $orgs ) ) {
					foreach( $orgs as $id ) {
						$box_title = get_the_title( $id );

						$nonprofit_author = get_post_field( 'post_author', $id );

						if( current_user_can( 'administrator' ) || current_user_can( 'editor' ) || $nonprofit_author == $post->post_author ) {
							$edit_url = admin_url( 'post.php?post=' . $id . '&action=edit' );
							$box_title .= '&nbsp;<a href="' . $edit_url . '"><span class="dashicons dashicons-edit edit-non-profit-from-event"></span></a>';
						}

						add_meta_box( 'event-nonprofit-details-' . $id, $box_title, array( $this, 'event_nonprofit_metabox_content' ), 'event', 'advanced', 'default', array( 'org_id' => $id ) );
					}
				}
			}
		}
	}

	public function event_nonprofit_metabox_content ( $post, $args ) {

		$org_id = intval( $args['args']['org_id'] );

		if( ! $org_id ) {
			return;
		}

		$this->nonprofit_team( $org_id );
	}

	public function filter_non_profits_list_table ( $post_type, $which ) {

		if ( 'non-profit' == $post_type ) {

			$event_args = array(
				'post_type' => 'event',
				'post_status' => 'publish',
				'orderby' => 'title',
				'order' => 'ASC',
				'fields' => 'ids',
				'posts_per_page' => -1,
			);

			if( current_user_can( 'organiser' ) ) {
				$event_args['author'] = get_current_user_id();
			}

			$events = get_posts( $event_args );

			$selected_event = 0;
			if( isset( $_GET['event-select'] ) && $_GET['event-select'] ) {
				$selected_event = intval( $_GET['event-select'] );
			}

			$html = '<select name="event-select" id="event-select">';
				$html .= '<option value="0" ' . selected( 0, $selected_event, false ) . '>' . __( 'All events', 'do-action' ) . '</option>';
				foreach( $events as $event_id ) {
					$event_title = get_the_title( $event_id );
					$html .= '<option value="' . esc_attr( $event_id ) . '" ' . selected( $event_id, $selected_event, false ) . '>' . esc_html( $event_title ) . '</option>';
				}
			$html .= '</select>';

			echo $html;

		}
	}

	public function redirect_dashboard () {
		global $pagenow;

		if( current_user_can( 'organiser' ) && 'index.php' == $pagenow ) {
			wp_safe_redirect( admin_url( 'edit.php?post_type=event' ) );
			exit;
		}

	}

	public function modify_admin_menu () {
		if( current_user_can( 'organiser' ) ) {
			remove_menu_page( 'index.php' );
			remove_menu_page( 'jetpack' );
			remove_menu_page( 'edit.php' );
			remove_menu_page( 'upload.php' );
			remove_menu_page( 'edit-comments.php' );
			remove_menu_page( 'tools.php' );
		}
	}

	public function register_post_types () {
		foreach( $this->post_types as $type => $details ) {
			$this->register_post_type( $type, $details['plural'], $details['single'], '', $details['options'] );
		}
	}

	public function register_taxonomies () {
		foreach( $this->taxonomies as $tax => $details ) {
			$this->register_taxonomy( $tax, $details['plural'], $details['single'], $details['post_types'], $details['args'] );
		}
	}

	public function removable_query_args( $args = array() ) {
		$args['mail_sent'] = true;
		return $args;
	}

	public function glance_items( $items = array() ) {

		foreach( $this->post_types as $type => $details ) {

			if( ! post_type_exists( $type ) ) {
				continue;
			}

        	$num_posts = wp_count_posts( $type );

        	if( $num_posts ) {

	            $published = intval( $num_posts->publish );
	            $post_type = get_post_type_object( $type );

	            $text = _n( '%s ' . $post_type->labels->singular_name, '%s ' . $post_type->labels->name, $published, 'your_textdomain' );
	            $text = sprintf( $text, number_format_i18n( $published ) );

				if ( $post_type && current_user_can( $post_type->cap->edit_posts ) ) {
					$items[] = sprintf( '<a class="%1$s-count" href="edit.php?post_type=%1$s">%2$s</a>', $type, $text ) . "\n";
				} else {
					$items[] = sprintf( '<span class="%1$s-count">%2$s</span>', $type, $text ) . "\n";
				}
			}

		}

		return $items;
	}

	public function register_sidebars () {
		register_sidebar( array(
			'name'          => __( 'Events', 'do-action' ),
			'id'            => 'sidebar-event',
			'description'   => '',
			'before_widget' => '<aside id="%1$s" class="widget %2$s">',
			'after_widget'  => '</aside>',
			'before_title'  => '<h3 class="widget-title">',
			'after_title'   => '</h3>',
		) );
	}

	/**
	 * Wrapper function to register a new post type
	 * @param  string $post_type   Post type name
	 * @param  string $plural      Post type item plural name
	 * @param  string $single      Post type item single name
	 * @param  string $description Description of post type
	 * @return object              Post type class object
	 */
	public function register_post_type ( $post_type = '', $plural = '', $single = '', $description = '', $options = array() ) {

		if ( ! $post_type || ! $plural || ! $single ) return;

		$post_type = new do_action_Post_Type( $post_type, $plural, $single, $description, $options );

		return $post_type;
	}

	/**
	 * Wrapper function to register a new taxonomy
	 * @param  string $taxonomy   Taxonomy name
	 * @param  string $plural     Taxonomy single name
	 * @param  string $single     Taxonomy plural name
	 * @param  array  $post_types Post types to which this taxonomy applies
	 * @return object             Taxonomy class object
	 */
	public function register_taxonomy ( $taxonomy = '', $plural = '', $single = '', $post_types = array(), $taxonomy_args = array() ) {

		if ( ! $taxonomy || ! $plural || ! $single ) return;

		$taxonomy = new do_action_Taxonomy( $taxonomy, $plural, $single, $post_types, $taxonomy_args );

		return $taxonomy;
	}

	/**
	 * Load frontend CSS.
	 * @access  public
	 * @since   1.0.0
	 * @return void
	 */
	public function enqueue_styles () {
		wp_register_style( $this->_token . '-frontend', esc_url( $this->assets_url ) . 'css/frontend.css', array(), $this->_version );
		wp_enqueue_style( $this->_token . '-frontend' );
	} // End enqueue_styles ()

	/**
	 * Load frontend Javascript.
	 * @access  public
	 * @since   1.0.0
	 * @return  void
	 */
	public function enqueue_scripts () {

		wp_register_script( $this->_token . '-google-maps', '//maps.googleapis.com/maps/api/js?key=AIzaSyCxLB91fJO-JOkTEsWtr0y3_Gypcxjn6nM&callback=initMap', array(), '4.0.2', true );

		wp_register_script( $this->_token . '-frontend', esc_url( $this->assets_url ) . 'js/frontend' . $this->script_suffix . '.js', array( 'jquery', $this->_token . '-google-maps' ), $this->_version, true );
		wp_enqueue_script( $this->_token . '-frontend' );
	} // End enqueue_scripts ()

	/**
	 * Load admin CSS.
	 * @access  public
	 * @since   1.0.0
	 * @return  void
	 */
	public function admin_enqueue_styles ( $hook = '' ) {

		wp_register_style( $this->_token . '-jqeury-ui', '//code.jquery.com/ui/1.11.4/themes/smoothness/jquery-ui.css', array(), '1.11.4' );

		wp_register_style( $this->_token . '-admin', esc_url( $this->assets_url ) . 'css/admin.css', array( $this->_token . '-jqeury-ui' ), $this->_version );
		wp_enqueue_style( $this->_token . '-admin' );

		wp_enqueue_style( $this->_token . '-jquery-ui-datepicker', esc_url( $this->assets_url ) . 'css/datepicker.css', false, false, false );

	} // End admin_enqueue_styles ()

	/**
	 * Load admin Javascript.
	 * @access  public
	 * @since   1.0.0
	 * @return  void
	 */
	public function admin_enqueue_scripts ( $hook = '' ) {

		wp_register_script( $this->_token . '-google-places', '//maps.googleapis.com/maps/api/js?key=AIzaSyCxLB91fJO-JOkTEsWtr0y3_Gypcxjn6nM&libraries=places', array(), '4.0.2' );
		wp_register_script( $this->_token . '-geocomplete', esc_url( $this->assets_url ) . 'js/jquery.geocomplete' . $this->script_suffix . '.js', array( 'jquery', $this->_token . '-google-places' ), '1.7.0' );

		wp_register_script( $this->_token . '-admin', esc_url( $this->assets_url ) . 'js/admin' . $this->script_suffix . '.js', array( 'jquery', 'jquery-ui-datepicker', $this->_token . '-geocomplete' ), $this->_version );
		wp_enqueue_script( $this->_token . '-admin' );

	} // End admin_enqueue_scripts ()

	/**
	 * Load plugin localisation
	 * @access  public
	 * @since   1.0.0
	 * @return  void
	 */
	public function load_localisation () {
		load_plugin_textdomain( 'do-action', false, dirname( plugin_basename( $this->file ) ) . '/languages/' );
	} // End load_localisation ()

	/**
	 * Main do_action Instance
	 *
	 * Ensures only one instance of do_action is loaded or can be loaded.
	 *
	 * @since 1.0.0
	 * @static
	 * @see do_action_functions()
	 * @return Main do_action instance
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

	/**
	 * Installation. Runs on activation.
	 * @access  public
	 * @since   1.0.0
	 * @return  void
	 */
	public function install () {
		$this->_log_version_number();
	} // End install ()

	/**
	 * Log the plugin version number.
	 * @access  public
	 * @since   1.0.0
	 * @return  void
	 */
	private function _log_version_number () {
		update_option( $this->_token . '_version', $this->_version );
	} // End _log_version_number ()

}