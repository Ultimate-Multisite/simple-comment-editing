<?php
/**
 * WooCommerce actions/filters.
 *
 * @package CommentEditLite
 */

namespace DLXPlugins\CommentEditLite;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'No direct access.' );
}

/**
 * Class WooCommerce
 */
class WooCommerce {
	/**
	 * Class runner.
	 */
	public static function run() {
		// Add in the ratings field.
		add_filter( 'sce_extra_fields_pre', array( static::class, 'add_woocommerce_fields' ), 10, 4 );

		// Save the ratings field.
		add_action( 'sce_save_after', array( static::class, 'save_woocommerce_rating_fields' ), 10, 4 );

		// Return the ratings field.
		add_filter( 'sce_save_comment_return', array( static::class, 'return_woocommerce_rating_fields' ), 10, 4 );
	}

	/**
	 * Add the WooCommerce rating field.
	 *
	 * @param array      $return Return data.
	 * @param WP_Comment $comment Comment.
	 * @param int        $post_id Post ID.
	 * @param int        $comment_id Comment ID.
	 *
	 * @return array
	 */
	public static function return_woocommerce_rating_fields( $return, $comment, $post_id, $comment_id ) {
		if ( 'review' !== $comment->comment_type ) {
			return $return;
		}

		// Get the rating.
		$rating = filter_input( INPUT_POST, 'rating', FILTER_SANITIZE_NUMBER_INT );

		// Check for a valid rating.
		if ( ! $rating ) {
			return $return;
		}

		$return['rating'] = absint( $rating );
		return $return;
	}

	/**
	 * Save the WooCommerce rating field.
	 *
	 * @param WP_Comment $comment_to_save Comment to save.
	 * @param int        $post_id Post ID.
	 * @param int        $comment_id Comment ID.
	 * @param WP_Comment $original_comment Original comment.
	 */
	public static function save_woocommerce_rating_fields( $comment_to_save, $post_id, $comment_id, $original_comment ) {
		if ( 'review' !== $comment_to_save['comment_type'] ) {
			return;
		}

		// Get the rating.
		$rating = filter_input( INPUT_POST, 'rating', FILTER_SANITIZE_NUMBER_INT );
		$nonce  = sanitize_text_field( filter_input( INPUT_POST, 'wooEditCommentNonce', FILTER_DEFAULT ) );

		// Check for a valid rating.
		if ( ! $rating ) {
			return;
		}

		// Check the nonce.
		if ( ! wp_verify_nonce( $nonce, 'woocommerce-edit-comment_' . $comment_id ) ) {
			return;
		}

		// Check permissions.
		if ( ! Functions::can_edit( $comment_id, $post_id ) ) {
			return;
		}

		// User can edit comment, change rating.
		update_comment_meta( $comment_id, 'rating', absint( $rating ) );
	}

	/**
	 * Add WooCommerce fields to the comment form.
	 *
	 * @param string     $markup HTML markup to return.
	 * @param int        $post_id Post ID.
	 * @param int        $comment_id Comment ID.
	 * @param WP_Comment $comment WP_Comment object.
	 *
	 * @return string HTML markup.
	 */
	public static function add_woocommerce_fields( $markup, $post_id, $comment_id, $comment ) {
		if ( 'review' !== $comment->comment_type ) {
			return $markup;
		}

		// Get current rating.
		$rating = get_comment_meta( $comment_id, 'rating', true );
		if ( $rating && wc_review_ratings_enabled() ) {
				$markup  = '<div class="comment-form-rating" data-selected-rating="' . esc_attr( $rating ) . '"><label for="sce-rating-' . esc_attr( $comment_id ) . '">' . esc_html__( 'Your rating', 'simple-comment-editing' ) . ( wc_review_ratings_required() ? '&nbsp;<span class="required">*</span>' : '' ) . '</label><select name="rating" id="sce-rating-' . esc_attr( $comment_id ) . '" required>
					<option value="" ' . selected( 'none', $rating, false ) . '>' . esc_html__( 'Rate&hellip;', 'simple-comment-editing' ) . '</option>
					<option value="5" ' . selected( '5', $rating, false ) . '>>' . esc_html__( 'Perfect', 'simple-comment-editing' ) . '</option>
					<option value="4" ' . selected( '4', $rating, false ) . '>' . esc_html__( 'Good', 'simple-comment-editing' ) . '</option>
					<option value="3" ' . selected( '3', $rating, false ) . '>' . esc_html__( 'Average', 'simple-comment-editing' ) . '</option>
					<option value="2" ' . selected( '2', $rating, false ) . '>' . esc_html__( 'Not that bad', 'simple-comment-editing' ) . '</option>
					<option value="1" ' . selected( '1', $rating, false ) . '>>' . esc_html__( 'Very poor', 'simple-comment-editing' ) . '</option>
				</select></div>';
				$markup .= wp_nonce_field( 'woocommerce-edit-comment_' . $comment_id, 'woo_edit_comment_nonce_' . $comment_id, false, false );
		}
		return $markup;
	}
}
