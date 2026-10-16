<?php
/**
 * Ownership and editing-window integration tests.
 *
 * @package CommentEditLite\Tests
 */

namespace DLXPlugins\CommentEditLite\Tests;

use DLXPlugins\CommentEditLite\Functions;

/**
 * Exercise permissions against real users, comments, metadata, and cookies.
 */
class Functions_Test extends \WP_UnitTestCase {
	use Plugin_State;

	/** Set up a clean request. */
	public function set_up() {
		parent::set_up();
		$this->set_up_plugin_state();
	}

	/** Restore request-local state. */
	public function tear_down() {
		$this->tear_down_plugin_state();
		parent::tear_down();
	}

	/** A matching anonymous browser can edit its comment. */
	public function test_anonymous_owner_can_edit() {
		$id = $this->create_comment();
		$this->assertTrue( Functions::can_edit( $id, (int) get_comment( $id )->comment_post_ID ) );
	}

	/** No cookie means no ownership. */
	public function test_another_browser_cannot_edit() {
		$id = $this->create_comment( 0, 0, false );
		$this->assertFalse( Functions::can_edit( $id, (int) get_comment( $id )->comment_post_ID ) );
	}

	/** A forged cookie must not authenticate a visitor. */
	public function test_forged_cookie_cannot_edit() {
		$id                         = $this->create_comment();
		$_COOKIE[ key( $_COOKIE ) ] = 'wrong-token';
		$this->assertFalse( Functions::can_edit( $id, (int) get_comment( $id )->comment_post_ID ) );
	}

	/** A stale loop global must not grant ownership of the requested comment. */
	public function test_stale_owned_global_cannot_authorize_another_comment() {
		$user_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$owned   = $this->create_comment( $user_id, 0, false );
		$other   = self::factory()->comment->create(
			array( 'comment_post_ID' => get_comment( $owned )->comment_post_ID )
		);
		wp_set_current_user( $user_id );
		$GLOBALS['comment'] = get_comment( $owned );
		$this->assertFalse( Functions::can_edit( $other, (int) get_comment( $other )->comment_post_ID ) );
	}

	/** Permission checks must leave the surrounding loop's comment untouched. */
	public function test_permission_check_preserves_comment_global() {
		$id                 = $this->create_comment();
		$post_id            = (int) get_comment( $id )->comment_post_ID;
		$loop_comment       = get_comment( self::factory()->comment->create() );
		$GLOBALS['comment'] = $loop_comment;
		$this->assertTrue( Functions::can_edit( $id, $post_id ) );
		$this->assertSame( $loop_comment, $GLOBALS['comment'] );

		$_COOKIE = array();
		$this->assertFalse( Functions::can_edit( $id, $post_id ) );
		$this->assertSame( $loop_comment, $GLOBALS['comment'] );
		$this->assertFalse( Functions::can_edit( 0, $post_id ) );
		$this->assertSame( $loop_comment, $GLOBALS['comment'] );
	}

	/** Calls outside a comment loop must not create a comment global. */
	public function test_permission_check_does_not_create_comment_global() {
		$id      = $this->create_comment();
		$post_id = (int) get_comment( $id )->comment_post_ID;
		unset( $GLOBALS['comment'] );
		$this->assertTrue( Functions::can_edit( $id, $post_id ) );
		$this->assertArrayNotHasKey( 'comment', $GLOBALS );
		$this->assertFalse( Functions::can_edit( 0, $post_id ) );
		$this->assertArrayNotHasKey( 'comment', $GLOBALS );
	}

	/** Empty cookie and metadata values must never count as matching ownership. */
	public function test_empty_ownership_values_cannot_edit() {
		$id                         = $this->create_comment();
		$_COOKIE[ key( $_COOKIE ) ] = '';
		delete_comment_meta( $id, '_sce' );
		$this->assertFalse( Functions::can_edit( $id, (int) get_comment( $id )->comment_post_ID ) );
	}

	/** Comment status is validated before any permission override. */
	public function test_trashed_comment_cannot_edit() {
		$id = $this->create_comment();
		wp_set_comment_status( $id, 'trash' );
		$this->assertFalse( Functions::can_edit( $id, (int) get_comment( $id )->comment_post_ID ) );
	}

	/** A missing comment is denied without dereferencing a null global. */
	public function test_missing_comment_cannot_edit() {
		$id      = $this->create_comment();
		$post_id = (int) get_comment( $id )->comment_post_ID;
		wp_delete_comment( $id, true );
		$this->assertFalse( Functions::can_edit( $id, $post_id ) );
	}

	/** Post IDs must match the comment. */
	public function test_mismatched_post_cannot_edit() {
		$id = $this->create_comment();
		$this->assertFalse( Functions::can_edit( $id, self::factory()->post->create() ) );
	}

	/** Logged-in authors do not need an anonymous cookie. */
	public function test_logged_in_author_can_edit_within_window() {
		$user_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$id      = $this->create_comment( $user_id, 60, false );
		wp_set_current_user( $user_id );
		$this->assertTrue( Functions::can_edit( $id, (int) get_comment( $id )->comment_post_ID ) );
	}

	/** Another logged-in user is not the author. */
	public function test_other_subscriber_cannot_edit() {
		$id = $this->create_comment( self::factory()->user->create(), 0, false );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$this->assertFalse( Functions::can_edit( $id, (int) get_comment( $id )->comment_post_ID ) );
	}

	/**
	 * The editing window must stay closed after expiry, including hour/day rollover.
	 *
	 * @dataProvider expired_ages
	 * @param int $age Comment age in seconds.
	 */
	public function test_logged_in_author_cannot_edit_expired_comment( $age ) {
		$user_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$id      = $this->create_comment( $user_id, $age, false );
		wp_set_current_user( $user_id );
		$this->assertFalse( Functions::can_edit( $id, (int) get_comment( $id )->comment_post_ID ) );
	}

	/**
	 * Expiry cases use fixed ages without sleeping.
	 *
	 * @return array
	 */
	public static function expired_ages() {
		return array(
			'boundary'      => array( 300 ),
			'past boundary' => array( 360 ),
			'hour rollover' => array( 3601 ),
			'day rollover'  => array( 86401 ),
		);
	}

	/**
	 * Browser cookie expiry must not be the only time limit.
	 *
	 * @dataProvider expired_ages
	 * @param int $age Comment age in seconds.
	 */
	public function test_anonymous_owner_cannot_replay_cookie_after_expiry( $age ) {
		$id = $this->create_comment( 0, $age );
		$this->assertFalse( Functions::can_edit( $id, (int) get_comment( $id )->comment_post_ID ) );
	}

	/** Unlimited editing is still restricted to the author. */
	public function test_unlimited_editing_allows_author_after_expiry() {
		$user_id = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$id      = $this->create_comment( $user_id, DAY_IN_SECONDS, false );
		wp_set_current_user( $user_id );
		add_filter( 'sce_unlimited_editing', '__return_true' );
		$this->assertTrue( Functions::can_edit( $id, (int) get_comment( $id )->comment_post_ID ) );
	}

	/** Unlimited editing must not grant ownership to another browser. */
	public function test_unlimited_editing_does_not_bypass_ownership() {
		$id = $this->create_comment( 0, DAY_IN_SECONDS, false );
		add_filter( 'sce_unlimited_editing', '__return_true' );
		$this->assertFalse( Functions::can_edit( $id, (int) get_comment( $id )->comment_post_ID ) );
	}

	/** Integrations may veto edits before any ownership checks. */
	public function test_permission_filter_can_deny_owner() {
		$id = $this->create_comment();
		add_filter( 'sce_can_edit_pre', '__return_false' );
		$this->assertFalse( Functions::can_edit( $id, (int) get_comment( $id )->comment_post_ID ) );
	}
}
