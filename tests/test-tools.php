<?php
/**
 * Integration tests for tools requests.
 *
 * @package do_action
 */

declare( strict_types = 1 );

/**
 * Exercise the tools AJAX entry points with real WordPress nonce checks.
 */
class Tests_Do_Action_Tools extends WP_Ajax_UnitTestCase {
	/**
	 * Create a user allowed to open the tools page.
	 *
	 * @return void
	 */
	public function set_up(): void {
		parent::set_up();
		$user = self::factory()->user->create_and_get();
		$user->add_cap( 'use_do_action_tools' );
		wp_set_current_user( $user->ID );
	}

	/**
	 * List the AJAX actions and their expected response fields.
	 *
	 * @return array Action names and response keys.
	 */
	public static function ajax_actions(): array {
		return array(
			array( 'fetch_event_orgs', 'org_select' ),
			array( 'format_email_preview', 'email_subject' ),
		);
	}

	/**
	 * Requests without a nonce must stop before returning tools data.
	 *
	 * @dataProvider ajax_actions
	 * @param string $action       AJAX action name.
	 * @param string $response_key Expected response field for an authorized call.
	 * @return void
	 */
	public function test_missing_nonce_is_rejected( string $action, string $response_key ): void {
		$this->expectException( WPAjaxDieStopException::class );
		$this->expectExceptionMessage( '-1' );
		$this->_handleAjax( $action );
	}

	/**
	 * Valid requests retain the empty-event response used by the selector UI.
	 *
	 * @dataProvider ajax_actions
	 * @param string $action       AJAX action name.
	 * @param string $response_key Expected response field for an authorized call.
	 * @return void
	 */
	public function test_valid_nonce_preserves_response( string $action, string $response_key ): void {
		$_POST['nonce'] = wp_create_nonce( 'do_action_tools_ajax' );
		try {
			$this->_handleAjax( $action );
		} catch ( WPAjaxDieContinueException $exception ) {
			// WordPress terminates successful JSON responses through wp_die().
			$this->assertSame( '', $exception->getMessage() );
		}
		$response = json_decode( $this->_last_response, true );
		$this->assertIsArray( $response );
		$this->assertArrayHasKey( $response_key, $response );
	}
}
