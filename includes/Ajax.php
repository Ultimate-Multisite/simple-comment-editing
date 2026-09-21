<?php
/**
 * Ajax actions.
 *
 * @package CommentEditLite
 */

namespace DLXPlugins\CommentEditLite;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'No direct access.' );
}

/**
 * Class Ajax
 */
class Ajax {

	/**
	 * Class runner.
	 */
	public static function run() {
		// Setup ajax actions.
		add_action( 'wp_ajax_sce_get_time_left', array( static::class, 'ajax_get_time_left' ) );
		add_action( 'wp_ajax_nopriv_sce_get_time_left', array( static::class, 'ajax_get_time_left' ) );
		add_action( 'wp_ajax_sce_save_comment', array( static::class, 'ajax_save_comment' ) );
		add_action( 'wp_ajax_nopriv_sce_save_comment', array( static::class, 'ajax_save_comment' ) );
		add_action( 'wp_ajax_sce_delete_comment', array( static::class, 'ajax_delete_comment' ) );
		add_action( 'wp_ajax_nopriv_sce_delete_comment', array( static::class, 'ajax_delete_comment' ) );
		add_action( 'wp_ajax_sce_stop_timer', array( static::class, 'ajax_stop_timer' ) );
		add_action( 'wp_ajax_nopriv_sce_stop_timer', array( static::class, 'ajax_stop_timer' ) );
	}

	/**
	 * Returns a JSON object of minutes/seconds of the time left to edit a comment
	 */
	public static function ajax_get_time_left() {
		check_ajax_referer( 'sce-general-ajax-nonce' );
		global $wpdb;
		$comment_id = absint( filter_input( INPUT_POST, 'comment_id', FILTER_VALIDATE_INT ) );
		$post_id    = absint( filter_input( INPUT_POST, 'post_id', FILTER_VALIDATE_INT ) );
		$comment    = get_comment( $comment_id, OBJECT );
		// Check if user can edit comment.
		if ( ! Functions::can_edit( $comment_id, $post_id ) ) {
			$response = array(
				'minutes'    => 0,
				'seconds'    => 0,
				'comment_id' => 0,
				'can_edit'   => false,
			);
			wp_send_json_success( $response );
		}

		/**
		 * Filter: sce_unlimited_editing
		 *
		 * Allow unlimited comment editing
		 *
		 * @since 2.3.6
		 *
		 * @param bool Whether to allow unlimited comment editing
		 * @param object Comment object
		 */
		$sce_unlimited_editing = apply_filters( 'sce_unlimited_editing', false, $comment );
		if ( $sce_unlimited_editing ) {
			$response = array(
				'minutes'    => 'unlimited',
				'seconds'    => 'unlimited',
				'comment_id' => $comment_id,
				'can_edit'   => true,
			);
			wp_send_json_success( $response );
		}

		$comment_time = Functions::get_comment_time();
		$query        = $wpdb->prepare( "SELECT ( $comment_time * 60 - (UNIX_TIMESTAMP('" . current_time( 'mysql' ) . "') - UNIX_TIMESTAMP(comment_date))) comment_time FROM {$wpdb->comments} where comment_ID = %d", $comment_id ); // phpcs:ignore

		$comment_time_result = $wpdb->get_row( $query, ARRAY_A ); // phpcs:ignore

		/**
		 * Filter: sce_get_comment_time_left
		 *
		 * Get the comment time remaining.
		 *
		 * @since 2.8.0
		 *
		 * @param int    Current comment editing time.
		 * @param string Current time format in date/time format.
		 * @param int    Current Post ID.
		 * @param int    Current Comment ID.
		 */
		$time_left = apply_filters( 'sce_get_comment_time_left', $comment_time_result['comment_time'], $comment_time, $post_id, $comment_id );

		if ( $time_left < 0 ) {
			$response = array(
				'minutes'    => 0,
				'comment_id' => $comment_id,
				'seconds'    => 0,
				'can_edit'   => false,
			);
			wp_send_json_success( $response );
		}
		$minutes  = floor( $time_left / 60 );
		$seconds  = $time_left - ( $minutes * 60 );
		$response = array(
			'minutes'    => $minutes,
			'comment_id' => $comment_id,
			'seconds'    => $seconds,
			'can_edit'   => true,

		);
		wp_send_json_success( $response );
	}

	/**
	 * Removes the timer and stops comment editing
	 *
	 * Removes the timer and stops comment editing
	 *
	 * @since 1.1.0
	 */
	public static function ajax_stop_timer() {
		$comment_id = absint( filter_input( INPUT_POST, 'comment_id', FILTER_VALIDATE_INT ) );
		$post_id    = absint( filter_input( INPUT_POST, 'post_id', FILTER_VALIDATE_INT ) );
		$nonce      = sanitize_text_field( filter_input( INPUT_POST, 'nonce', FILTER_DEFAULT ) );

		$return           = array();
		$return['errors'] = false;

		// Do a nonce check.
		if ( ! wp_verify_nonce( $nonce, 'sce-edit-comment' . $comment_id ) ) {
			$return['errors'] = true;
			$return['remove'] = true;
			$return['error']  = Simple_Comment_Editing::$errors->get_error_message( 'nonce_fail' );
			wp_send_json_error( $return );
		}

		// Check to see if the user can edit the comment.
		if ( ! Functions::can_edit( $comment_id, $post_id ) ) {
			$return['errors'] = true;
			$return['remove'] = true;
			$return['error']  = Simple_Comment_Editing::$errors->get_error_message( 'edit_fail' );
			wp_send_json_error( $return );
		}

		/**
		 * Action: sce_timer_stopped
		 *
		 * Allow third parties to take action a timer has been stopped
		 *
		 * @since 2.3.0
		 *
		 * @param int $post_id The Post ID
		 * @param int $comment_id The Comment ID
		 */
		do_action( 'sce_timer_stopped', $post_id, $comment_id );

		delete_comment_meta( $comment_id, '_sce' );

		$return['error'] = '';
		wp_send_json_success( $return );
	}

	/**
	 * Removes a WordPress comment, but saves it to the trash
	 *
	 * @since 1.1.0
	 */
	public static function ajax_delete_comment() {
		$comment_id = absint( filter_input( INPUT_POST, 'comment_id', FILTER_VALIDATE_INT ) );
		$post_id    = absint( filter_input( INPUT_POST, 'post_id', FILTER_VALIDATE_INT ) );
		$nonce      = sanitize_text_field( filter_input( INPUT_POST, 'nonce', FILTER_DEFAULT ) );

		$return           = array();
		$return['errors'] = false;

		// Do a nonce check.
		if ( ! wp_verify_nonce( $nonce, 'sce-edit-comment' . $comment_id ) ) {
			$return['errors'] = true;
			$return['remove'] = true;
			$return['error']  = Simple_Comment_Editing::$errors->get_error_message( 'nonce_fail' );
			wp_send_json_error( $return );
		}

		// Check to see if the user can edit the comment.
		if ( ! Functions::can_edit( $comment_id, $post_id ) || false === Simple_Comment_Editing::$allow_delete ) {
			$return['errors'] = true;
			$return['remove'] = true;
			$return['error']  = Simple_Comment_Editing::$errors->get_error_message( 'edit_fail' );
			wp_send_json_error( $return );
		}

		/**
		 * Action: sce_comment_is_deleted
		 *
		 * Allow third parties to take action when a comment has been deleted
		 *
		 * @since 2.3.0
		 *
		 * @param int $post_id The Post ID
		 * @param int $comment_id The Comment ID
		 */
		do_action( 'sce_comment_is_deleted', $post_id, $comment_id );

		/**
		 * Filter: sce_force_delete
		 *
		 * Allow third parties to force a comment to be deleted
		 * instead of being saved to the trash
		 *
		 * @since 2.9.6
		 *
		 * @param bool $force_delete Whether to force delete or not
		 * @param int $comment_id The Comment ID
		 * @param int $post_id The Post ID
		 */
		$force_delete = apply_filters( 'sce_force_delete', false, $comment_id, $post_id );

		wp_delete_comment( $comment_id, $force_delete ); // Save to trash for admin retrieval.
		$return['error'] = '';
		wp_send_json_success( $return );
	} //end ajax_delete_comment

	/**
	 *  Saves a comment to the database, returns the updated comment via JSON.
	 *
	 * Returns a JSON object of the saved comment.
	 *
	 * @since 1.0
	 */
	public static function ajax_save_comment() {
		define( 'DOING_SCE', true );
		$new_comment_content = trim( filter_input( INPUT_POST, 'comment_content', FILTER_DEFAULT ) );
		$comment_id          = absint( filter_input( INPUT_POST, 'comment_id', FILTER_VALIDATE_INT ) );
		$post_id             = absint( filter_input( INPUT_POST, 'post_id', FILTER_VALIDATE_INT ) );
		$nonce               = sanitize_text_field( filter_input( INPUT_POST, 'nonce', FILTER_DEFAULT ) );

		$return           = array();
		$return['errors'] = false;
		$return['remove'] = false; // If set to true, removes the editing interface.

		// Do a nonce check.
		if ( ! wp_verify_nonce( $nonce, 'sce-edit-comment' . $comment_id ) ) {
			$return['errors'] = true;
			$return['remove'] = true;
			$return['error']  = Simple_Comment_Editing::$errors->get_error_message( 'nonce_fail' );
			wp_send_json_error( $return );
		}

		// Check to see if the user can edit the comment.
		if ( ! Functions::can_edit( $comment_id, $post_id ) ) {
			$return['errors'] = true;
			$return['remove'] = true;
			$return['error']  = Simple_Comment_Editing::$errors->get_error_message( 'edit_fail' );
			wp_send_json_error( $return );
		}

		// Check that the content isn't empty.
		if ( '' === $new_comment_content || 'undefined' === $new_comment_content ) {
			$return['errors'] = true;
			$return['error']  = Simple_Comment_Editing::$errors->get_error_message( 'comment_empty' );
			wp_send_json_error( $return );
		}

		// Get original comment.
		$comment_to_save = $original_comment = get_comment( $comment_id, ARRAY_A ); // phpcs:ignore.

		// Check the comment.
		if ( 1 === (int) $comment_to_save['comment_approved'] ) {
			// Short circuit comment moderation filter.
			add_filter( 'pre_option_comment_moderation', array( static::class, 'short_circuit_comment_moderation' ) );
			add_filter( 'pre_option_comment_whitelist', array( static::class, 'short_circuit_comment_moderation' ) );
			add_filter( 'option_comment_moderation', '__return_false' ); // Needed to bypass moderation.
			remove_filter( 'comment_text', array( Simple_Comment_Editing::get_instance(), 'add_edit_interface' ), 1000, 2 ); // Prevents adding in any SCE links to the comment.
			if ( check_comment( $comment_to_save['comment_author'], $comment_to_save['comment_author_email'], $comment_to_save['comment_author_url'], $new_comment_content, $comment_to_save['comment_author_IP'], $comment_to_save['comment_agent'], $comment_to_save['comment_type'] ) ) {
				$comment_to_save['comment_approved'] = 1;
			} else {
				$comment_to_save['comment_approved'] = 0;
			}
			add_filter( 'comment_text', array( Simple_Comment_Editing::get_instance(), 'add_edit_interface' ), 1000, 2 );
			remove_filter( 'option_comment_moderation', '__return_false' );
			// Remove Short circuit comment moderation filter.
			remove_filter( 'pre_option_comment_moderation', array( static::class, 'short_circuit_comment_moderation' ) );
			remove_filter( 'pre_option_comment_whitelist', array( static::class, 'short_circuit_comment_moderation' ) );
		}

		// Check comment against blacklist.
		if ( wp_check_comment_disallowed_list( $comment_to_save['comment_author'], $comment_to_save['comment_author_email'], $comment_to_save['comment_author_url'], $new_comment_content, $comment_to_save['comment_author_IP'], $comment_to_save['comment_agent'] ) ) {
			$comment_to_save['comment_approved'] = 'spam';
		}

		// Update comment content with new content.
		$comment_to_save['comment_content'] = addslashes( $new_comment_content );

		/**
		 * Filter: sce_comment_check_errors
		 *
		 * Return a custom error message based on the saved comment
		 *
		 * @since 1.2.4
		 *
		 * @param bool  $custom_error Default custom error. Overwrite with a string
		 * @param array $comment_to_save Associative array of comment attributes
		 */
		$custom_error = apply_filters( 'sce_comment_check_errors', false, $comment_to_save ); // Filter expects a string returned - $comment_to_save is an associative array.
		if ( is_string( $custom_error ) && ! empty( $custom_error ) ) {
			$return['errors'] = true;
			$return['error']  = esc_html( $custom_error );
			wp_send_json_error( $return );
		}

		/**
		 * Filter: sce_save_before
		 *
		 * Allow third parties to modify comment
		 *
		 * @since 1.5.0
		 *
		 * @param array $comment_to_save The Comment array
		 * @param int $post_id The Post ID
		 * @param int $comment_id The Comment ID
		 */
		$comment_to_save = apply_filters( 'sce_save_before', $comment_to_save, $post_id, $comment_id );

		// Save the comment.
		wp_update_comment( $comment_to_save );

		/**
		 * Action: sce_save_after
		 *
		 * Allow third parties to save content after a comment has been updated
		 *
		 * @since 1.5.0
		 *
		 * @param array $comment_to_save The Comment array
		 * @param int $post_id The Post ID
		 * @param int $comment_id The Comment ID
		 * @param array $original_comment The original
		*/
		ob_start();
		do_action( 'sce_save_after', $comment_to_save, $post_id, $comment_id, $original_comment );
		ob_end_clean();

		// If the comment was marked as spam, return an error.
		if ( 'spam' === $comment_to_save['comment_approved'] ) {
			$return['errors'] = true;
			$return['remove'] = true;
			$return['error']  = Simple_Comment_Editing::$errors->get_error_message( 'comment_marked_spam' );
			Simple_Comment_Editing::remove_comment_cookie( $comment_to_save );
			wp_send_json_error( $return );
		}

		/**
		 * Filter: sce_akismet_enabled
		 *
		 * Allow third parties to disable Akismet.
		 *
		 * @param bool true if Akismet is enabled
		 */
		$akismet_enabled = apply_filters( 'sce_akismet_enabled', true );

		// Check the new comment for spam with Akismet.
		if ( function_exists( 'akismet_check_db_comment' ) && $akismet_enabled ) {
			if ( 'failed' !== akismet_verify_key( get_option( 'wordpress_api_key' ) ) ) { // Akismet.
				$response = akismet_check_db_comment( $comment_id );
				if ( 'true' === $response ) { // You have spam.
					wp_set_comment_status( $comment_id, 'spam' );
					$return['errors'] = true;
					$return['remove'] = true;
					$return['error']  = Simple_Comment_Editing::$errors->get_error_message( 'comment_marked_spam' );
					Simple_Comment_Editing::remove_comment_cookie( $comment_to_save );
					wp_send_json_error( $return );
				}
			}
		}

		$comment_to_return = Simple_Comment_Editing::get_comment( $comment_id );

		/**
		 * Filter: sce_return_comment_text
		 *
		 * Allow comment manipulation before the comment is returned
		 *
		 * @since 2.1.0
		 *
		 * @param string  Comment Content
		 * @param object  Comment Object
		 * @param int     Post ID
		 * @param int     Comment ID
		 */
		remove_filter( 'comment_text', array( Simple_Comment_Editing::get_instance(), 'add_edit_interface' ), 1000 );
		$comment_content_to_return = apply_filters( 'sce_return_comment_text', Functions::get_comment_content( $comment_to_return ), $comment_to_return, $post_id, $comment_id );
		add_filter( 'comment_text', array( Simple_Comment_Editing::get_instance(), 'add_edit_interface' ), 1000, 2 );

		// Ajax response.
		$return['comment_text'] = stripslashes( $comment_content_to_return );
		$return['error']        = '';

		/**
		 * Filter: sce_save_comment_return
		 *
		 * Allow third parties to modify the return value of the comment
		 *
		 * @since 3.0.0
		 * @param array $return The return array
		 * @param object $comment_to_return The comment object
		 * @param int $post_id The post ID
		 * @param int $comment_id The comment ID
		 */
		$return = apply_filters( 'sce_save_comment_return', $return, $comment_to_return, $post_id, $comment_id );
		wp_send_json_success( $return );
	}

	/**
	 * Short circuit the comment moderation option check.
	 *
	 * @since 2.3.9
	 *
	 * @param bool|mixed $option_value The option value for moderation.
	 *
	 * @return int Return a string so there is not a boolean value.
	 */
	public static function short_circuit_comment_moderation( $option_value ) {
		return 'approved';
	}
}
