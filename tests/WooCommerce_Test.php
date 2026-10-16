<?php
/**
 * Review rating response regression tests using real comment metadata.
 *
 * @package CommentEditLite\Tests
 */

namespace DLXPlugins\CommentEditLite\Tests;

use DLXPlugins\CommentEditLite\WooCommerce;

/**
 * Response values must reflect persistence, not submitted rating input.
 */
class WooCommerce_Test extends \WP_UnitTestCase {
	use Plugin_State;

	/** Preserve request globals for each test. */
	public function set_up() {
		parent::set_up();
		$this->set_up_plugin_state();
	}

	/** Restore request globals before the database rollback. */
	public function tear_down() {
		$this->tear_down_plugin_state();
		parent::tear_down();
	}

	/** A rejected or absent rating request cannot become a successful UI rating. */
	public function test_response_uses_saved_rating_instead_of_request() {
		$comment_id = self::factory()->comment->create( array( 'comment_type' => 'review' ) );
		$comment    = get_comment( $comment_id );
		update_comment_meta( $comment_id, 'rating', 3 );
		$_POST['rating']              = '5';
		$_POST['wooEditCommentNonce'] = 'invalid';

		$response = WooCommerce::return_woocommerce_rating_fields( array( 'errors' => false ), $comment, (int) $comment->comment_post_ID, $comment_id );
		$this->assertSame(
			array(
				'errors' => false,
				'rating' => 3,
			),
			$response
		);
		unset( $_POST['rating'], $_POST['wooEditCommentNonce'] );
		$this->assertSame( $response, WooCommerce::return_woocommerce_rating_fields( array( 'errors' => false ), $comment, (int) $comment->comment_post_ID, $comment_id ) );
	}

	/** Missing and out-of-range stored ratings must not produce selected stars. */
	public function test_response_omits_unrated_and_invalid_ratings() {
		$comment_id = self::factory()->comment->create( array( 'comment_type' => 'review' ) );
		$comment    = get_comment( $comment_id );
		foreach ( array( '', 0, -1, 6 ) as $rating ) {
			update_comment_meta( $comment_id, 'rating', $rating );
			$this->assertSame( array(), WooCommerce::return_woocommerce_rating_fields( array(), $comment, (int) $comment->comment_post_ID, $comment_id ) );
		}
	}

	/** Ordinary comment responses remain untouched even if custom metadata exists. */
	public function test_ordinary_comment_response_is_unchanged() {
		$comment_id = self::factory()->comment->create();
		$comment    = get_comment( $comment_id );
		update_comment_meta( $comment_id, 'rating', 5 );
		$this->assertSame( array(), WooCommerce::return_woocommerce_rating_fields( array(), $comment, (int) $comment->comment_post_ID, $comment_id ) );
	}
}
