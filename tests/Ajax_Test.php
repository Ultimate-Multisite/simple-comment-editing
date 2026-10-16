<?php
/**
 * Real WordPress AJAX handlers and database effects.
 *
 * @package CommentEditLite\Tests
 */

namespace DLXPlugins\CommentEditLite\Tests;

use DLXPlugins\CommentEditLite\Simple_Comment_Editing;

/**
 * Verify responses and persisted state, not mocked WordPress functions.
 */
class Ajax_Test extends \WP_Ajax_UnitTestCase {
	use Plugin_State;

	/** Start with a clean browser. */
	public function set_up() {
		parent::set_up();
		$this->set_up_plugin_state();
	}

	/** Restore request-local state. */
	public function tear_down() {
		parent::tear_down();
		$this->tear_down_plugin_state();
	}

	/**
	 * Dispatch through the registered authenticated or anonymous AJAX hook.
	 *
	 * @param string $action AJAX action.
	 * @param int    $id     Comment ID.
	 * @param array  $values Request overrides (null removes a field).
	 * @return array Decoded JSON response.
	 */
	private function request( $action, $id, $values = array() ) {
		$comment         = get_comment( $id );
		$data            = array_merge(
			array(
				'comment_id'      => (string) $id,
				'post_id'         => (string) $comment->comment_post_ID,
				'comment_content' => 'Updated comment.',
				'nonce'           => wp_create_nonce( 'sce-edit-comment' . $id ),
				'_ajax_nonce'     => wp_create_nonce( 'sce-general-ajax-nonce' ),
			),
			$values
		);
		$_POST           = wp_slash(
			array_filter(
				$data,
				static function ( $value ) {
					return null !== $value;
				}
			)
		);
		$_POST['action'] = $action;
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Constructing a request; the real handler verifies its nonce.
		$_REQUEST             = $_POST;
		$this->_last_response = '';
		try {
			if ( is_user_logged_in() ) {
				$this->_handleAjax( $action );
			} else {
				ob_start();
				do_action( 'wp_ajax_nopriv_' . $action );
			}
		} catch ( \WPAjaxDieContinueException $exception ) {
			// WordPress captured the JSON before terminating the request.
			$this->assertNotSame( '', $this->_last_response );
		}
		$response = json_decode( $this->_last_response, true );
		$this->assertIsArray( $response, $this->_last_response );
		$this->assertArrayHasKey( 'success', $response );
		return $response;
	}

	/**
	 * Missing, invalid, and nonscalar nonces cannot mutate a comment.
	 *
	 * @dataProvider rejected_nonces
	 * @param string $action AJAX action.
	 * @param mixed  $nonce  Invalid nonce.
	 */
	public function test_rejected_nonce_does_not_change_comment( $action, $nonce ) {
		$id       = $this->create_comment();
		$response = $this->request( $action, $id, array( 'nonce' => $nonce ) );
		$this->assertFalse( $response['success'] );
		$this->assertTrue( $response['data']['errors'] );
		$this->assertSame( Simple_Comment_Editing::$errors->get_error_message( 'nonce_fail' ), $response['data']['error'] );
		$this->assertSame( 'Original comment.', get_comment( $id )->comment_content );
		$this->assertSame( '1', get_comment( $id )->comment_approved );
		$this->assertSame( 'sce-test-ownership-token', get_comment_meta( $id, '_sce', true ) );
	}

	/**
	 * Security checks apply to all mutating endpoints.
	 *
	 * @return array
	 */
	public static function rejected_nonces() {
		$cases = array();
		foreach ( array( 'sce_save_comment', 'sce_delete_comment', 'sce_stop_timer' ) as $action ) {
			$nonces = array(
				'missing' => null,
				'invalid' => 'invalid-nonce',
				'array'   => array( 'invalid' ),
			);
			foreach ( $nonces as $label => $nonce ) {
				$cases[ $action . ' ' . $label ] = array( $action, $nonce );
			}
		}
		return $cases;
	}

	/**
	 * A valid nonce alone never grants ownership.
	 *
	 * @dataProvider mutating_actions
	 * @param string $action AJAX action.
	 */
	public function test_non_owner_cannot_mutate_comment( $action ) {
		$id       = $this->create_comment( 0, 0, false );
		$response = $this->request( $action, $id );
		$this->assertFalse( $response['success'] );
		$this->assertSame( 'Original comment.', get_comment( $id )->comment_content );
		$this->assertSame( '1', get_comment( $id )->comment_approved );
		$this->assertSame( 'sce-test-ownership-token', get_comment_meta( $id, '_sce', true ) );
	}

	/**
	 * Endpoints that change comment data.
	 *
	 * @return array
	 */
	public static function mutating_actions() {
		return array( array( 'sce_save_comment' ), array( 'sce_delete_comment' ), array( 'sce_stop_timer' ) );
	}

	/**
	 * An expired cookie cannot authorize changes even when its nonce is valid.
	 *
	 * @dataProvider mutating_actions
	 * @param string $action AJAX action.
	 */
	public function test_expired_owner_cannot_mutate_comment( $action ) {
		$id       = $this->create_comment( 0, 360 );
		$response = $this->request( $action, $id );
		$this->assertFalse( $response['success'] );
		$this->assertSame( 'Original comment.', get_comment( $id )->comment_content );
		$this->assertSame( '1', get_comment( $id )->comment_approved );
		$this->assertSame( 'sce-test-ownership-token', get_comment_meta( $id, '_sce', true ) );
	}

	/** Valid anonymous saves update the database and return rendered content. */
	public function test_anonymous_owner_can_save_comment() {
		$id       = $this->create_comment();
		$response = $this->request( 'sce_save_comment', $id );
		$this->assertTrue( $response['success'] );
		$this->assertSame( 'Updated comment.', get_comment( $id )->comment_content );
		$this->assertStringContainsString( 'Updated comment.', $response['data']['comment_text'] );
	}

	/** Authenticated AJAX uses the same ownership checks and real handler. */
	public function test_logged_in_author_can_save_comment() {
		$user_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$id      = $this->create_comment( $user_id, 60, false );
		wp_set_current_user( $user_id );
		$response = $this->request( 'sce_save_comment', $id );
		$this->assertTrue( $response['success'] );
		$this->assertSame( 'Updated comment.', get_comment( $id )->comment_content );
	}

	/** WordPress request slashing must not corrupt quotes or literal backslashes. */
	public function test_save_preserves_quotes_and_backslashes() {
		$id      = $this->create_comment();
		$content = 'It\'s a "quoted" path: C:\\example\\file';
		$this->assertTrue( $this->request( 'sce_save_comment', $id, array( 'comment_content' => $content ) )['success'] );
		$this->assertSame( $content, get_comment( $id )->comment_content );
	}

	/** Unsafe markup is filtered by WordPress for unprivileged commenters. */
	public function test_save_sanitizes_unsafe_html() {
		$id       = $this->create_comment();
		$response = $this->request( 'sce_save_comment', $id, array( 'comment_content' => '<script>alert(1)</script><b>Allowed</b>' ) );
		$this->assertTrue( $response['success'] );
		$this->assertStringNotContainsString( '<script', get_comment( $id )->comment_content );
		$this->assertStringContainsString( '<b>Allowed</b>', get_comment( $id )->comment_content );
	}

	/** Empty content must not overwrite the original. */
	public function test_empty_save_does_not_change_comment() {
		$id       = $this->create_comment();
		$response = $this->request( 'sce_save_comment', $id, array( 'comment_content' => '   ' ) );
		$this->assertFalse( $response['success'] );
		$this->assertSame( Simple_Comment_Editing::$errors->get_error_message( 'comment_empty' ), $response['data']['error'] );
		$this->assertSame( 'Original comment.', get_comment( $id )->comment_content );
	}

	/** Deletion defaults to reversible trash. */
	public function test_delete_trashes_comment() {
		$id = $this->create_comment();
		$this->assertTrue( $this->request( 'sce_delete_comment', $id )['success'] );
		$this->assertSame( 'trash', get_comment( $id )->comment_approved );
	}

	/** Explicit force-delete integrations can permanently remove a comment. */
	public function test_force_delete_removes_comment() {
		$id = $this->create_comment();
		add_filter( 'sce_force_delete', '__return_true' );
		$this->assertTrue( $this->request( 'sce_delete_comment', $id )['success'] );
		$this->assertNull( get_comment( $id ) );
	}

	/** The deletion setting must be enforced server-side. */
	public function test_disabled_delete_does_not_change_comment() {
		$id                                   = $this->create_comment();
		Simple_Comment_Editing::$allow_delete = false;
		$this->assertFalse( $this->request( 'sce_delete_comment', $id )['success'] );
		$this->assertSame( '1', get_comment( $id )->comment_approved );
	}

	/** Stopping the timer revokes the anonymous browser token. */
	public function test_stop_timer_removes_ownership_metadata() {
		$id = $this->create_comment();
		$this->assertTrue( $this->request( 'sce_stop_timer', $id )['success'] );
		$this->assertSame( '', get_comment_meta( $id, '_sce', true ) );
		$this->assertSame( 'Original comment.', get_comment( $id )->comment_content );
		$this->assertFalse( $this->request( 'sce_save_comment', $id )['success'] );
	}

	/** An active timer returns a bounded remaining duration. */
	public function test_get_time_left_for_owner() {
		$id       = $this->create_comment( 0, 60 );
		$response = $this->request( 'sce_get_time_left', $id );
		$this->assertTrue( $response['success'] );
		$this->assertTrue( $response['data']['can_edit'] );
		$seconds = $response['data']['minutes'] * 60 + $response['data']['seconds'];
		$this->assertGreaterThan( 220, $seconds );
		$this->assertLessThanOrEqual( 240, $seconds );
	}

	/** Expired anonymous sessions cannot get a fresh editing window. */
	public function test_get_time_left_for_expired_comment() {
		$id       = $this->create_comment( 0, 360 );
		$response = $this->request( 'sce_get_time_left', $id );
		$this->assertFalse( $response['data']['can_edit'] );
		$this->assertSame( 0, $response['data']['minutes'] );
		$this->assertSame( 0, $response['data']['seconds'] );
	}

	/** The read endpoint must also reject invalid nonces. */
	public function test_get_time_left_rejects_invalid_nonce() {
		$id = $this->create_comment();
		$this->expectException( \WPAjaxDieStopException::class );
		$this->expectExceptionMessage( '-1' );
		$this->request( 'sce_get_time_left', $id, array( '_ajax_nonce' => 'invalid-nonce' ) );
	}

	/** Unlimited editing returns an explicit nonnumeric timer. */
	public function test_get_time_left_for_unlimited_owner() {
		$id = $this->create_comment( 0, DAY_IN_SECONDS );
		add_filter( 'sce_unlimited_editing', '__return_true' );
		$response = $this->request( 'sce_get_time_left', $id );
		$this->assertTrue( $response['data']['can_edit'] );
		$this->assertSame( 'unlimited', $response['data']['minutes'] );
		$this->assertSame( 'unlimited', $response['data']['seconds'] );
	}
}
