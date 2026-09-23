<?php
/**
 * Mailchimp actions and functions.
 *
 * @package CommentEditLite
 */

namespace DLXPlugins\CommentEditLite;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'No direct access.' );
}

/**
 * Class Mailchimp
 */
class Mailchimp {

	/**
	 * Mailchimp API variable with <sp> (server prefix) for search/replace.
	 *
	 * @var string Mailchimp API variable.
	 */
	private static $mailchimp_api = 'https://<sp>.api.mailchimp.com/3.0/';

	/**
	 * Class runner.
	 */
	public static function run() {
		// Add Mailchimp Checkbox.
		add_filter( 'comment_form_defaults', array( static::class, 'add_mailchimp_checkbox' ), 100 );
		// When a new comment has been added.
		add_action( 'comment_post', array( static::class, 'comment_posted_mailchimp' ), 100, 2 );
	}

	/**
	 * Add an subscribe option below the comment textarea and above the submit button.
	 *
	 * @param array $comment_fields Array of defaults for the form field.
	 */
	public static function add_mailchimp_checkbox( $comment_fields ) {
		$options           = Options::get_options();
		$mailchimp_enabled = (bool) $options['enable_mailchimp'];

		// Chceck to see if Mailchimp is enabled.
		if ( ! $mailchimp_enabled || current_user_can( 'moderate_comments' ) ) {
			return $comment_fields;
		}

		// Now get the checkbox details.
		$checked_by_default              = (bool) $options['mailchimp_checkbox_enabled'];
		$mailchimp_html                  = array(
			'sce-mailchimp' => sprintf(
				'<section class="comment-form-sce-mailchimp"><label><input type="checkbox" name="sce-mailchimp-signup" %s /> %s</label></section>',
				checked( $checked_by_default, true, false ),
				esc_html( $options['mailchimp_signup_label'] )
			),
		);
		$comment_fields['submit_button'] = $mailchimp_html['sce-mailchimp'] . $comment_fields['submit_button'];
		return $comment_fields;
	}

	/**
	 * Send Mailchimp when a comment has been posted.
	 *
	 * @param int  $comment_id Comment ID that has been submitted.
	 * @param bool $maybe_comment_approved Whether the comment is approved or not (1, 0, spam).
	 */
	public static function comment_posted_mailchimp( $comment_id, $maybe_comment_approved ) {
		$signup_enabled = (bool) filter_input( INPUT_POST, 'sce-mailchimp-signup', FILTER_VALIDATE_BOOLEAN );

		if ( $signup_enabled && 'spam' !== $maybe_comment_approved ) {
			// Get the comment.
			$comment         = get_comment( $comment_id );
			$commenter_email = $comment->comment_author_email;

			$subscriber_added = self::add_subscriber( $comment_id, $commenter_email, $comment );
		}
	}

	/**
	 * Add a subscriber to mailchimp.
	 *
	 * @param int         $comment_id The comment ID.
	 * @param string      $email      The email address.
	 * @param \WP_Comment $comment    The comment object.
	 *
	 * @return bool True if the subscriber was added, false otherwise.
	 */
	private static function add_subscriber( $comment_id, $email, $comment ) {
		if ( ! is_email( $email ) ) {
			return false;
		}

		$options = Options::get_options();
		$list    = $options['mailchimp_selected_list'] ?? '';
		if ( empty( $list ) ) {
			return false;
		}

		// Format API url for a server prefix..
		$mailchimp_api_url = str_replace(
			'<sp>',
			sanitize_key( $options['mailchimp_api_key_server_prefix'] ),
			self::$mailchimp_api
		);

		$commenter_name    = $comment->comment_author;
		$mailchimp_api_key = sanitize_text_field( $options['mailchimp_api_key'] );

		$endpoint = $mailchimp_api_url . 'lists/' . $list . '/members/';

		// Start building up HTTP args.
		$http_args            = array();
		$http_args['headers'] = array(
			'Authorization' => 'Bearer ' . $mailchimp_api_key,
			'Accept'        => 'application/json;ver=1.0',
		);
		$http_args['body']    = wp_json_encode(
			array(
				'email_address' => $email,
				'status'        => 'pending',
				'merge_fields'  => array(
					'FNAME' => $commenter_name,
				),
			)
		);
		$response             = wp_safe_remote_post( esc_url_raw( $endpoint ), $http_args );
		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			// Response code can be 400 if the member already exists.
			return false;
		}

		// Now format response from JSON.
		$response_array = json_decode( wp_remote_retrieve_body( $response ), true );
		// Save subscription ID to comment meta.
		add_comment_meta( $comment_id, 'sce_mailchimp_id', $response_array['id'] );
		return true;
	}
}
