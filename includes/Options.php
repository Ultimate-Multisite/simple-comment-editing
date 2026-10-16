<?php
/**
 * Plugin Options.
 *
 * @package CommentEditLite
 */

namespace DLXPlugins\CommentEditLite;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'No direct access.' );
}

/**
 * Class Options
 */
class Options {

	/**
	 * A list of options cached for saving.
	 *
	 * @var array $options
	 */
	private static $options = array();

	/**
	 * Get the options for Simple Comment Editing.
	 *
	 * @since 3.0.0
	 *
	 * @param bool   $force true to retrieve options directly, false to use cached version.
	 * @param string $key The option key to retrieve.
	 *
	 * @return string|array|bool Return a string if key is set, array of options (default), or false if key is set and option is not found.
	 */
	public static function get_options( $force = false, $key = '' ) {

		$options = self::$options;
		if ( ! is_array( $options ) || empty( $options ) || true === $force ) {
			$options       = get_site_option( 'sce_options', array() );
			self::$options = $options;
		}
		if ( false === $options || empty( $options ) || ! is_array( $options ) ) {
			$options = self::get_defaults();
		} else {
			$options = wp_parse_args( $options, self::get_defaults() );
		}
		self::$options = $options;

		// Return a key if set.
		if ( ! empty( $key ) ) {
			if ( isset( $options[ $key ] ) ) {
				return $options[ $key ];
			} else {
				return false;
			}
		}

		return self::$options;
	}

	/**
	 * Save options for the plugin.
	 *
	 * @param array $options array of options.
	 */
	public static function update_options( $options = array() ) {
		$current_options = self::get_options( true );

		foreach ( $options as $key => &$option ) {
			switch ( $key ) {
				case 'enable_mailchimp':
				case 'mailchimp_api_key_valid':
				case 'mailchimp_checkbox_enabled':
				case 'show_icons':
				case 'show_timer':
				case 'allow_delete':
				case 'allow_delete_confirmation':
				case 'allow_unlimited_editing':
				case 'require_login_for_editing':
				case 'enable_quicktags':
				case 'load_scripts_posts_only':
				case 'load_scripts_with_comments':
					$option = (bool) filter_var( $options[ $key ], FILTER_VALIDATE_BOOLEAN );
					break;
				case 'timer':
					$timer = absint( $options[ $key ] );
					if ( 0 === $timer ) {
						$timer = 5;
					}
					$option = $timer;
					break;
				case 'delete_behavior_users':
				case 'delete_behavior_moderators':
					$option = 'delete' === $options[ $key ] ? 'delete' : 'trash';
					break;
				case 'spam_behavior_moderators':
					$option = 'delete' === $options[ $key ] ? 'delete' : 'spam';
					break;
				default:
					if ( is_array( $option ) ) {
						$option = map_deep( $option, 'sanitize_text_field' );
					} else {
						$option = sanitize_text_field( $options[ sanitize_key( $key ) ] );
					}
					break;
			}
		}

		$options = wp_parse_args( $options, $current_options );

		if ( Functions::is_multisite() ) {
			update_site_option( 'sce_options', $options );
		} else {
			update_option( 'sce_options', $options );
		}

		self::$options = $options;
	}

	/**
	 * Get the default options for Simple Comment Editing.
	 *
	 * @since 3.0.0
	 */
	private static function get_defaults() {
		$defaults = array(
			'timer'                           => 5,
			'timer_appearance'                => 'words',
			'button_theme'                    => 'default',
			'show_icons'                      => false,
			'show_timer'                      => true,
			'allow_delete'                    => true,
			'allow_delete_confirmation'       => true,
			'allow_unlimited_editing'         => false,
			'require_login_for_editing'       => false,
			'enable_quicktags'                => false,
			'load_scripts_posts_only'         => false,
			'load_scripts_with_comments'      => false,
			'delete_behavior_users'           => 'trash',
			'delete_behavior_moderators'      => 'trash',
			'spam_behavior_moderators'        => 'spam',
			'click_to_edit_text'              => __( 'Click to Edit', 'simple-comment-editing' ),
			'save_text'                       => __( 'Save', 'simple-comment-editing' ),
			'cancel_text'                     => __( 'Cancel', 'simple-comment-editing' ),
			'delete_text'                     => __( 'Delete', 'simple-comment-editing' ),
			'confirm_delete'                  => __( 'Do you want to delete this comment?', 'simple-comment-editing' ),
			'comment_deleted'                 => __( 'Your comment has been removed.', 'simple-comment-editing' ),
			'comment_deleted_error'           => __( 'Your comment could not be deleted', 'simple-comment-editing' ),
			'comment_empty_error'             => __( 'Your comment appears to be empty.', 'simple-comment-editing' ),
			'enable_mailchimp'                => false,
			'mailchimp_api_key'               => '',
			'mailchimp_api_key_valid'         => false,
			'mailchimp_api_key_server_prefix' => '',
			'mailchimp_lists'                 => array(),
			'mailchimp_selected_list'         => '',
			'mailchimp_signup_label'          => __( 'Sign Up for Updates', 'simple-comment-editing' ),
			'mailchimp_checkbox_enabled'      => false,
		);

		/**
		 * Allow other plugins to add to the defaults.
		 *
		 * @since 3.0.0
		 *
		 * @param array $defaults An array of option defaults.
		 */
		$defaults = apply_filters( 'sce_options_defaults', $defaults );
		return $defaults;
	}
}
