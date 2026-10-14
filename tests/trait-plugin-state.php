<?php
/**
 * Restore request-local plugin caches and browser state between tests.
 *
 * @package CommentEditLite\Tests
 */

namespace DLXPlugins\CommentEditLite\Tests;

use DLXPlugins\CommentEditLite\Functions;
use DLXPlugins\CommentEditLite\Options;
use DLXPlugins\CommentEditLite\Simple_Comment_Editing;

/**
 * State isolation shared by WordPress and AJAX test cases.
 */
trait Plugin_State {
	/**
	 * Original static property values.
	 *
	 * @var array
	 */
	private $plugin_properties = array();

	/**
	 * Original browser state.
	 *
	 * @var array
	 */
	private $browser_state = array();

	/**
	 * Start each test with cold caches and an anonymous browser.
	 */
	protected function set_up_plugin_state() {
		// phpcs:ignore WordPress.Security.NonceVerification -- Snapshotting a test request, not processing form data.
		$this->browser_state = array( $_COOKIE, $_POST, $_GET, $_REQUEST );
		foreach ( array( array( Functions::class, 'comment_time', 0 ), array( Options::class, 'options', array() ) ) as $definition ) {
			$property = new \ReflectionProperty( $definition[0], $definition[1] );
			if ( PHP_VERSION_ID < 80100 ) {
				$property->setAccessible( true );
			}
			$this->plugin_properties[] = array( $property, $property->getValue() );
			$property->setValue( null, $definition[2] );
		}
		$this->plugin_properties[]            = array( new \ReflectionProperty( Simple_Comment_Editing::class, 'allow_delete' ), Simple_Comment_Editing::$allow_delete );
		Simple_Comment_Editing::$allow_delete = true;
		$_COOKIE                              = array();
		$_POST                                = array();
		$_GET                                 = array();
		$_REQUEST                             = array();
		unset( $GLOBALS['comment'], $GLOBALS['post'] );
		wp_set_current_user( 0 );
		update_option( 'timezone_string', 'UTC' );
		update_option( 'gmt_offset', 0 );
	}

	/**
	 * Restore caches and request state before WordPress rolls back the database.
	 */
	protected function tear_down_plugin_state() {
		foreach ( $this->plugin_properties as $snapshot ) {
			$snapshot[0]->setValue( null, $snapshot[1] );
		}
		$this->plugin_properties                   = array();
		list( $_COOKIE, $_POST, $_GET, $_REQUEST ) = $this->browser_state;
		unset( $GLOBALS['comment'], $GLOBALS['post'] );
		wp_set_current_user( 0 );
	}

	/**
	 * Create a real comment and, optionally, its anonymous ownership cookie.
	 *
	 * @param int  $user_id Comment author ID (zero for anonymous).
	 * @param int  $age     Age in seconds.
	 * @param bool $cookie Whether this browser owns the comment.
	 * @return int Comment ID.
	 */
	protected function create_comment( $user_id = 0, $age = 0, $cookie = true ) {
		$post_id    = self::factory()->post->create();
		$comment_id = self::factory()->comment->create(
			array(
				'comment_post_ID'   => $post_id,
				'user_id'           => $user_id,
				'comment_content'   => 'Original comment.',
				'comment_approved'  => '1',
				'comment_author_IP' => '192.0.2.1',
				'comment_agent'     => 'SCE test browser',
				'comment_date'      => gmdate( 'Y-m-d H:i:s', time() - $age ),
				'comment_date_gmt'  => gmdate( 'Y-m-d H:i:s', time() - $age ),
			)
		);
		update_comment_meta( $comment_id, '_sce', 'sce-test-ownership-token' );
		if ( $cookie ) {
			$comment = get_comment( $comment_id );
			$hash    = md5( $comment->comment_author_IP . gmdate( 'Y-m-d', strtotime( $comment->comment_date_gmt ) ) . $comment->user_id . $comment->comment_agent );
			$_COOKIE[ 'SimpleCommentEditing' . $comment_id . $hash ] = 'sce-test-ownership-token';
		}
		return $comment_id;
	}
}
