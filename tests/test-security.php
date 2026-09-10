<?php
/**
 * Regression tests for nonprofit authorization and stored metadata rendering.
 *
 * @package do_action
 */

declare( strict_types = 1 );

/**
 * Exercise the plugin against real WordPress users, posts and metadata.
 */
class Tests_Do_Action_Security extends WP_UnitTestCase {
	/**
	 * Scoped organiser.
	 *
	 * @var WP_User
	 */
	private WP_User $organiser;

	/**
	 * Administrator with the plugin's custom post capabilities.
	 *
	 * @var WP_User
	 */
	private WP_User $administrator;

	/**
	 * Owned event and nonprofit IDs.
	 *
	 * @var int[]
	 */
	private array $ids = array();

	/**
	 * Original request superglobals.
	 *
	 * @var array
	 */
	private array $request = array();

	/**
	 * Build two organisers' isolated event data.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();
		// phpcs:ignore WordPress.Security.NonceVerification -- Preserve the test process request state.
		$this->request       = array( $_POST, $_REQUEST );
		$this->organiser     = self::factory()->user->create_and_get();
		$other               = self::factory()->user->create_and_get();
		$this->administrator = self::factory()->user->create_and_get( array( 'role' => 'administrator' ) );

		foreach ( array( $this->organiser, $other ) as $user ) {
			foreach ( array( 'read', 'organiser', 'use_do_action_tools', 'edit_events', 'edit_published_events', 'edit_non-profits', 'edit_published_non-profits' ) as $cap ) {
				$user->add_cap( $cap );
			}
		}
		foreach ( array( 'event', 'non-profit' ) as $type ) {
			foreach ( get_post_type_object( $type )->cap as $cap ) {
				$this->administrator->add_cap( $cap );
			}
		}
		$this->administrator->add_cap( 'use_do_action_tools' );
		wp_set_current_user( $this->administrator->ID );

		$this->ids['event']      = self::factory()->post->create(
			array(
				'post_type'   => 'event',
				'post_author' => $this->organiser->ID,
			)
		);
		$this->ids['own']        = self::factory()->post->create(
			array(
				'post_type'   => 'non-profit',
				'post_author' => $this->organiser->ID,
			)
		);
		$this->ids['other']      = self::factory()->post->create(
			array(
				'post_type'   => 'non-profit',
				'post_author' => $other->ID,
			)
		);
		$this->ids['unselected'] = self::factory()->post->create(
			array(
				'post_type'   => 'non-profit',
				'post_author' => $this->organiser->ID,
			)
		);
		$role                    = self::factory()->term->create(
			array(
				'taxonomy' => 'role',
				'slug'     => 'designer',
				'name'     => 'Designer',
			)
		);

		foreach ( array( 'own', 'other', 'unselected' ) as $label ) {
			$id = $this->ids[ $label ];
			wp_set_object_terms( $id, array( $role ), 'role' );
			update_post_meta( $id, 'contact_name', $label . ' contact' );
			update_post_meta( $id, 'contact_email', $label . '@example.org' );
			update_post_meta( $id, 'contact_number', '12345' );
			update_post_meta( $id, 'designer_name', $label . ' participant' );
			update_post_meta( $id, 'designer_email_address', $label . '-participant@example.org' );
			update_post_meta( $id, 'designer_phone_number', '67890' );
		}
		update_post_meta( $this->ids['event'], 'nonprofits', array( $this->ids['own'] ) );
		update_post_meta( $this->ids['event'], 'event_status', 'accepting_signups' );
		wp_set_current_user( $this->organiser->ID );
	}

	/**
	 * Restore request globals and the current user.
	 *
	 * @return void
	 */
	public function tear_down(): void {
		list( $_POST, $_REQUEST ) = $this->request;
		wp_set_current_user( 0 );
		parent::tear_down();
	}

	/**
	 * Call the shared recipient boundary used by mail, CSV and preview.
	 *
	 * @param array|false $orgs Explicit organisation IDs or the default selection.
	 * @return array Recipient records.
	 */
	private function recipients( array|false $orgs = false ): array {
		$method = new ReflectionMethod( do_action_tools::class, 'get_people_data' );
		return $method->invoke( do_action_functions()->tools, $this->ids['event'], array( 'npo', 'designer' ), $orgs );
	}

	/**
	 * Explicit foreign or unselected IDs must not reach mail or CSV.
	 *
	 * @return void
	 */
	public function test_rejects_foreign_and_unselected_recipients(): void {
		$this->assertSame( array(), $this->recipients( array( $this->ids['other'] ) ) );
		$this->assertSame( array(), $this->recipients( array( $this->ids['unselected'] ) ) );
		$this->assertSame( array(), $this->recipients( array( $this->ids['own'], $this->ids['other'] ) ) );
	}

	/**
	 * Empty and malformed explicit selections must not expand to all recipients.
	 *
	 * @return void
	 */
	public function test_explicit_empty_selection_stays_empty(): void {
		$this->assertSame( array(), $this->recipients( array() ) );
		$this->assertSame( array(), $this->recipients( array( 0 ) ) );
		$this->assertSame( array(), $this->recipients( array( 'not-an-id' ) ) );
	}

	/**
	 * Zero must not resolve to the global post through WordPress get_post().
	 *
	 * @return void
	 */
	public function test_zero_relationship_does_not_use_global_post(): void {
		$GLOBALS['post'] = get_post( $this->ids['own'] );
		$this->assertFalse( do_action_functions()->is_event_nonprofit_allowed( $this->ids['event'], 0 ) );
	}

	/**
	 * Valid organiser and administrator selections retain their recipients.
	 *
	 * @return void
	 */
	public function test_authorized_explicit_and_default_recipients(): void {
		$emails = array( 'own@example.org', 'own-participant@example.org' );
		$this->assertSame( $emails, array_column( $this->recipients(), 'email' ) );
		$this->assertSame( $emails, array_column( $this->recipients( array( (string) $this->ids['own'] ) ), 'email' ) );
		wp_set_current_user( $this->administrator->ID );
		$this->assertSame( $emails, array_column( $this->recipients(), 'email' ) );
	}

	/**
	 * Store legacy poisoned metadata without using the guarded production writers.
	 *
	 * @return void
	 */
	private function seed_foreign_relationship(): void {
		global $wpdb;
		delete_post_meta( $this->ids['event'], 'nonprofits' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.SlowDBQuery -- Model metadata persisted before the security fix.
		$wpdb->insert(
			$wpdb->postmeta,
			array(
				'post_id'    => $this->ids['event'],
				// phpcs:ignore WordPress.DB.SlowDBQuery -- Insert a fixture, not a metadata query.
				'meta_key'   => 'nonprofits',
				// phpcs:ignore WordPress.DB.SlowDBQuery -- Insert a fixture, not a metadata query.
				'meta_value' => maybe_serialize( array( $this->ids['own'], $this->ids['other'] ) ),
			)
		);
		wp_cache_delete( $this->ids['event'], 'post_meta' );
	}

	/**
	 * Persisted foreign references must not leak through default tools selection.
	 *
	 * @return void
	 */
	public function test_default_recipients_ignore_poisoned_relationship(): void {
		$this->seed_foreign_relationship();
		$this->assertSame( array( 'own@example.org', 'own-participant@example.org' ), array_column( $this->recipients(), 'email' ) );
	}

	/**
	 * Foreign private details must not render in an owned event's metabox.
	 *
	 * @return void
	 */
	public function test_event_metabox_checks_nonprofit_permission(): void {
		$this->seed_foreign_relationship();
		ob_start();
		do_action_functions()->event_nonprofit_metabox_content( get_post( $this->ids['event'] ), array( 'args' => array( 'org_id' => $this->ids['other'] ) ) );
		$this->assertSame( '', ob_get_clean() );
	}

	/**
	 * Public forms must not expose legacy foreign associations to new signups.
	 *
	 * @return void
	 */
	public function test_signup_form_ignores_poisoned_relationship(): void {
		$this->seed_foreign_relationship();
		wp_set_current_user( 0 );
		ob_start();
		do_action_functions()->event_sign_up_form( get_post( $this->ids['event'] ) );
		$html = ob_get_clean();
		$this->assertStringContainsString( 'nonprofit-' . $this->ids['own'], $html );
		$this->assertStringNotContainsString( 'nonprofit-' . $this->ids['other'], $html );
	}

	/**
	 * The normal metabox writer must leave associations unchanged on invalid input.
	 *
	 * @return void
	 */
	public function test_metabox_save_rejects_foreign_association(): void {
		$_POST['do_action_meta_nonce'] = wp_create_nonce( 'do_action_save_meta_' . $this->ids['event'] );
		$_REQUEST['nonprofits']        = array( $this->ids['other'] );
		$admin                         = new do_action_Admin_API();
		$admin->save_meta_boxes( $this->ids['event'] );
		remove_action( 'save_post', array( $admin, 'save_meta_boxes' ) );
		$this->assertSame( array( $this->ids['own'] ), get_post_meta( $this->ids['event'], 'nonprofits', true ) );
	}

	/**
	 * Native custom fields must not bypass the dedicated association selector.
	 *
	 * @return void
	 */
	public function test_native_relationship_writes_are_denied(): void {
		$this->assertFalse( current_user_can( 'edit_post_meta', $this->ids['event'], 'nonprofits' ) );
		$this->assertFalse( current_user_can( 'edit_post_meta', $this->ids['event'], '_do_action_approved_nonprofits' ) );
		$_POST = array(
			'metakeyinput' => 'nonprofits',
			'metavalue'    => array( $this->ids['other'] ),
		);
		$this->assertFalse( add_meta( $this->ids['event'] ) );
	}

	/**
	 * Administrators may approve cross-owner associations for public signup.
	 *
	 * @return void
	 */
	public function test_administrator_can_approve_cross_owner_association(): void {
		wp_set_current_user( $this->administrator->ID );
		$_POST['do_action_meta_nonce'] = wp_create_nonce( 'do_action_save_meta_' . $this->ids['event'] );
		$_REQUEST['nonprofits']        = array( $this->ids['other'] );
		$admin                         = new do_action_Admin_API();
		$admin->save_meta_boxes( $this->ids['event'] );
		remove_action( 'save_post', array( $admin, 'save_meta_boxes' ) );
		$this->assertSame( array( $this->ids['other'] ), get_post_meta( $this->ids['event'], 'nonprofits', true ) );
		$this->assertSame( array( 'other@example.org', 'other-participant@example.org' ), array_column( $this->recipients(), 'email' ) );
		wp_set_current_user( 0 );
		$this->assertSame( array( $this->ids['other'] ), do_action_functions()->get_event_nonprofits( $this->ids['event'] ) );
		wp_set_current_user( $this->organiser->ID );
		$this->assertSame( array(), $this->recipients() );
	}

	/**
	 * Independently stored venue markup must be inert in the public header.
	 *
	 * @return void
	 */
	public function test_native_venue_metadata_is_escaped(): void {
		$this->assertFalse( current_user_can( 'unfiltered_html' ) );
		$_POST = array(
			'metakeyinput' => 'venue_name',
			'metavalue'    => '<img src=x onerror=alert(1)>',
		);
		$this->assertIsInt( add_meta( $this->ids['event'] ) );
		$this->go_to( get_permalink( $this->ids['event'] ) );
		$GLOBALS['post'] = get_post( $this->ids['event'] );
		ob_start();
		do_action_functions()->storefront_post_header();
		$html = ob_get_clean();
		$this->assertStringNotContainsString( '<img src=x', $html );
		$this->assertStringContainsString( '&lt;img src=x', $html );
	}

	/**
	 * Contact and participant fields remain inert for anonymous password holders.
	 *
	 * @return void
	 */
	public function test_contact_metadata_is_escaped_without_requiring_login(): void {
		foreach ( array( 'contact_name', 'contact_email', 'contact_number', 'designer_name', 'designer_email_address', 'designer_phone_number' ) as $key ) {
			update_post_meta( $this->ids['own'], $key, '<img src=x onerror=alert(1)>' );
		}
		wp_set_current_user( 0 );
		ob_start();
		do_action_functions()->nonprofit_team( get_post( $this->ids['own'] ) );
		$html = ob_get_clean();
		$this->assertStringNotContainsString( '<img src=x', $html );
		$this->assertSame( 6, substr_count( $html, '&lt;img src=x' ) );
	}

	/**
	 * Preview bodies retain rich text and literal backslashes without active HTML.
	 *
	 * @return void
	 */
	public function test_email_preview_filters_html_after_substitution(): void {
		$method = new ReflectionMethod( do_action_tools::class, 'format_email' );
		$html   = $method->invoke(
			do_action_functions()->tools,
			'<strong>Hello {{NAME}}</strong><script>alert(1)</script><a href="javascript:alert(1)">Link</a><img src=x onerror=alert(1)> C:\\team',
			array( 'name' => '<svg onload=alert(1)>' ),
			'body'
		);
		$this->assertStringNotContainsString( '<script', $html );
		$this->assertStringNotContainsString( 'javascript:', $html );
		$this->assertStringNotContainsString( 'onerror=', $html );
		$this->assertStringNotContainsString( '<svg', $html );
		$this->assertStringContainsString( '<strong>Hello &lt;svg', $html );
		$this->assertStringContainsString( 'C:\\team', $html );
	}

	/**
	 * Public descriptions preserve paragraphs and emphasis while filtering scripts.
	 *
	 * @return void
	 */
	public function test_nonprofit_description_filters_active_html(): void {
		$org               = get_post( $this->ids['own'] );
		$org->post_excerpt = '<strong>Our mission</strong><img src=x onerror=alert(1)><script>alert(1)</script>';
		ob_start();
		do_action_functions()->nonprofit_team( $org );
		$html = ob_get_clean();
		$this->assertStringContainsString( '<strong>Our mission</strong>', $html );
		$this->assertStringNotContainsString( 'onerror=', $html );
		$this->assertStringNotContainsString( '<script', $html );
	}

	/**
	 * Saving text fields preserves literal backslashes and quotation marks.
	 *
	 * @return void
	 */
	public function test_metadata_round_trip_preserves_text(): void {
		$_POST['do_action_meta_nonce'] = wp_create_nonce( 'do_action_save_meta_' . $this->ids['event'] );
		$_REQUEST['venue_name']        = wp_slash( 'The "Hall" C:\\venue <script>alert(1)</script>' );
		$_REQUEST['nonprofits']        = array( $this->ids['own'] );
		$admin                         = new do_action_Admin_API();
		$admin->save_meta_boxes( $this->ids['event'] );
		remove_action( 'save_post', array( $admin, 'save_meta_boxes' ) );
		$this->assertSame( 'The "Hall" C:\\venue', get_post_meta( $this->ids['event'], 'venue_name', true ) );
	}

	/**
	 * URL fields retain encoded path characters through the metabox save handler.
	 *
	 * @return void
	 */
	public function test_metadata_preserves_encoded_urls(): void {
		$url                           = 'https://example.org/a%20b/path%2Fpart?q=a%26b';
		$_POST['do_action_meta_nonce'] = wp_create_nonce( 'do_action_save_meta_' . $this->ids['own'] );
		$_REQUEST['url']               = wp_slash( $url );
		$admin                         = new do_action_Admin_API();
		$admin->save_meta_boxes( $this->ids['own'] );
		remove_action( 'save_post', array( $admin, 'save_meta_boxes' ) );
		$this->assertSame( $url, get_post_meta( $this->ids['own'], 'url', true ) );
	}

	/**
	 * Admin field rendering keeps controls and escapes malicious attribute values.
	 *
	 * @return void
	 */
	public function test_admin_field_retains_controls_and_escapes_values(): void {
		update_post_meta( $this->ids['event'], 'venue_name', '"><script>alert(1)</script>' );
		$admin = new do_action_Admin_API();
		ob_start();
		$admin->display_meta_box_field(
			array(
				'id'          => 'venue_name',
				'type'        => 'text',
				'label'       => 'Venue',
				'placeholder' => '',
			),
			get_post( $this->ids['event'] )
		);
		$html = ob_get_clean();
		remove_action( 'save_post', array( $admin, 'save_meta_boxes' ) );
		$this->assertStringContainsString( '<input', $html );
		$this->assertStringContainsString( 'name="venue_name"', $html );
		$this->assertStringNotContainsString( '<script', $html );
	}

	/**
	 * Both admin script variants use the corresponding file for cache invalidation.
	 *
	 * @return void
	 */
	public function test_admin_script_versions_use_existing_assets(): void {
		$plugin = do_action_functions();
		$suffix = $plugin->script_suffix;
		try {
			foreach ( array( '', '.min' ) as $variant ) {
				$plugin->script_suffix = $variant;
				wp_deregister_script( 'do_action-admin' );
				$plugin->admin_enqueue_scripts();
				$script = wp_scripts()->registered['do_action-admin'];
				$file   = dirname( __DIR__ ) . '/wp-content/plugins/do-action/assets/js/admin' . $variant . '.js';
				$this->assertStringEndsWith( '.' . filemtime( $file ), $script->ver );
				$this->assertStringEndsWith( 'admin' . $variant . '.js', $script->src );
			}
		} finally {
			$plugin->script_suffix = $suffix;
			wp_deregister_script( 'do_action-admin' );
		}
	}
}
