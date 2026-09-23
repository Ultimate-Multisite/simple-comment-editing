<?php
/**
 * Helper fuctions.
 *
 * @package CommentEditLite
 */

namespace DLXPlugins\CommentEditLite;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'No direct access.' );
}

/**
 * Class functions
 */
class Functions {

	/**
	 * The comment time in minutes.
	 *
	 * @var int The comment time in minutes.
	 */
	private static $comment_time = 0; // in minutes.

	/**
	 * Checks if the plugin is on a multisite install.
	 *
	 * @return bool True if multisite, false if not.
	 */
	public static function is_multisite() {
		if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
			require_once ABSPATH . '/wp-admin/includes/plugin.php';
		}
		if ( is_multisite() && is_plugin_active_for_network( SCE_SLUG ) ) {
			return true;
		}
		return false;
	}

	/**
	 * Gets the comment time for editing
	 *
	 * @since 1.3.0
	 */
	public static function get_comment_time() {
		if ( self::$comment_time > 0 ) {
			return self::$comment_time;
		}

		$time_do_edit = absint( Options::get_options( false, 'timer' ) );
		/**
		* Filter: sce_comment_time
		*
		* How long in minutes to edit a comment
		*
		* @since 1.0.0
		*
		* @param int  $minutes Time in minutes
		*/
		$comment_time       = absint( apply_filters( 'sce_comment_time', $time_do_edit ) );
		self::$comment_time = $comment_time;
		return self::$comment_time;
	}

	/**
	 * Return a string of the comment's text
	 *
	 * Return formatted comment text
	 *
	 * @access private
	 * @since 1.5.0
	 *
	 * @param \WP_Comment $comment Comment Object.
	 * @return string Comment text
	 */
	public static function get_comment_content( $comment ) {
		$comment_content_to_return = $comment->comment_content;

		// Format the comment for returning.
		if ( function_exists( 'mb_convert_encoding' ) ) {
			$comment_content_to_return = mb_convert_encoding( $comment_content_to_return, '' . get_option( 'blog_charset' ) . '', mb_detect_encoding( $comment_content_to_return, 'UTF-8, ISO-8859-1, ISO-8859-15', true ) );
		}
		return apply_filters( 'comment_text', apply_filters( 'get_comment_text', $comment_content_to_return, $comment, array() ), $comment, array() );
	}

	/**
	 * Whether a user can edit a comment. Returns true/false if a user can edit a comment.
	 *
	 * Retrieves a cookie to see if a comment can be edited or not
	 *
	 * @since 1.0
	 *
	 * @param int $comment_id The Comment ID.
	 * @param int $post_id The Comment's Post ID.
	 * @return bool true if can edit, false if not
	 */
	public static function can_edit( $comment_id, $post_id ) {
		global $comment, $post;

		/**
		 * Filter: sce_can_edit_pre
		 *
		 * Determine if a user can edit the comment (can short-circuit.)
		 *
		 * @since 2.9.1
		 *
		 * @param bool       $can_edit If the user can edit the comment.
		 * @param \WP_Comment $comment Comment object user has left (may be unset)
		 * @param WP_Post    $post    Post object (may be unset)
		 */
		$can_edit_pre = apply_filters( 'sce_can_edit_pre', true, $comment, $post );
		if ( ! $can_edit_pre ) {
			return false;
		}

		// Short circuit if user can edit comment.
		if ( current_user_can( 'edit-comment', $comment_id ) ) {
			return apply_filters( 'sce_can_edit', true, $comment, $comment_id, $post_id );
		}

		if ( ! is_object( $comment ) ) {
			$comment = get_comment( $comment_id, OBJECT ); // phpcs:ignore.
		}
		if ( (int) $comment->comment_post_ID !== $post_id ) {
			return false;
		}
		$user_id = absint( self::get_user_id() );

		// if we are logged in and are the comment author, bypass cookie check.
		$comment_meta      = get_comment_meta( $comment_id, '_sce', true );
		$cookie_bypass     = false;
		$is_comment_author = false;
		if ( is_user_logged_in() && absint( $comment->user_id ) === $user_id ) {
			$is_comment_author = true;
		}

		// If unlimited is enabled and user is comment author, user can edit.
		$sce_unlimited_editing = apply_filters( 'sce_unlimited_editing', false, $comment );
		if ( $is_comment_author && $sce_unlimited_editing ) {
			return apply_filters( 'sce_can_edit', true, $comment, $comment_id, $post_id );
		}

		/**
		 * Filter: sce_can_edit_cookie_bypass
		 *
		 * Bypass the cookie based user verification.
		 *
		 * @since 2.2.0
		 *
		 * @param bool       $bypass     Whether to bypass cookie authentication.
		 * @param \WP_Comment $comment    Comment object.
		 * @param int        $comment_id The comment ID.
		 * @param int        $post_id    The post ID of the comment.
		 * @param int        $user_id    The logged in user ID.
		 */
		$cookie_bypass = apply_filters( 'sce_can_edit_cookie_bypass', $cookie_bypass, $comment, $comment_id, $post_id, $user_id );

		// Check to see if time has elapsed for the comment.
		if ( ( $sce_unlimited_editing && $cookie_bypass ) || $is_comment_author ) {
			$comment_timestamp = strtotime( $comment->comment_date );
			$time_elapsed      = current_time( 'timestamp', get_option( 'gmt_offset' ) ) - $comment_timestamp; // phpcs:ignore.
			$minutes_elapsed   = ( ( ( $time_elapsed % 604800 ) % 86400 ) % 3600 ) / 60;
			if ( ( $minutes_elapsed - self::get_comment_time() ) >= 0 ) {
				return false;
			}
		} elseif ( false === $cookie_bypass ) {
			// Set cookies for verification.
			$comment_date_gmt = date( 'Y-m-d', strtotime( $comment->comment_date_gmt ) ); // phpcs:ignore.
			$cookie_hash      = md5( $comment->comment_author_IP . $comment_date_gmt . $comment->user_id . $comment->comment_agent );

			$cookie_value      = self::get_cookie_value( 'SimpleCommentEditing' . $comment_id . $cookie_hash );
			$comment_meta_hash = get_comment_meta( $comment_id, '_sce', true );
			if ( $cookie_value !== $comment_meta_hash ) {
				return false;
			}
		}

		// All is well, the person/place/thing can edit the comment.
		/**
		 * Filter: sce_can_edit
		 *
		 * Determine if a user can edit the comment
		 *
		 * @since 1.3.2
		 *
		 * @param bool       $can_edit   If the user can edit the comment.
		 * @param \WP_Comment $comment    Comment object user has left.
		 * @param int        $comment_id Comment ID of the comment.
		 * @param int        $post_id    Post ID of the comment.
		 */
		return apply_filters( 'sce_can_edit', true, $comment, $comment_id, $post_id );
	} //end can_edit

	/**
	 * Return a cookie's value
	 *
	 * Return a cookie's value
	 *
	 * @access private
	 * @since 1.5.0
	 *
	 * @param string $name Cookie name.
	 * @return string|false Cookie value, or false when absent.
	 */
	public static function get_cookie_value( $name ) {
		if ( isset( $_COOKIE[ $name ] ) ) {
			return sanitize_text_field( wp_unslash( $_COOKIE[ $name ] ) );
		} else {
			return false;
		}
	}

	/**
	 * Get a user ID
	 *
	 * Get a logged in user's ID
	 *
	 * @access private
	 * @since 1.5.0
	 *
	 * @return int user id
	 */
	public static function get_user_id() {
		$user_id = 0;
		if ( is_user_logged_in() ) {
			$current_user = wp_get_current_user();
			$user_id      = $current_user->ID;
		}
		return $user_id;
	}

	/**
	 * Sanitize an attribute based on type.
	 *
	 * @param array  $attributes Array of attributes.
	 * @param string $attribute  The attribute to sanitize.
	 * @param string $type       The type of sanitization you need (values can be int, text, float, bool, url).
	 *
	 * @return mixed Sanitized attribute. wp_error on failure.
	 */
	public static function sanitize_attribute( $attributes, $attribute, $type = 'text' ) {
		if ( isset( $attributes[ $attribute ] ) ) {
			switch ( $type ) {
				case 'text':
					return sanitize_text_field( $attributes[ $attribute ] );
				case 'bool':
					return filter_var( $attributes[ $attribute ], FILTER_VALIDATE_BOOLEAN );
				case 'int':
					return absint( $attributes[ $attribute ] );
				case 'float':
					if ( is_float( $attributes[ $attribute ] ) ) {
						return $attributes[ $attribute ];
					}
					return 0;
				case 'url':
					return esc_url( $attributes[ $attribute ] );
				case 'default':
					return new \WP_Error( 'sce_unknown_type', __( 'Unknown type.', 'simple-comment-editing' ) );
			}
		}
		return new \WP_Error( 'sce_attribute_not_found', __( 'Attribute not found.', 'simple-comment-editing' ) );
	}

	/**
	 * Convert Hex to RGBA
	 *
	 * @param string    $color   The color to convert.
	 * @param int|false $opacity The opacity.
	 *
	 * @return string rgba attribute.
	 */
	public static function hex2rgba( $color, $opacity = false ) {

		$default = 'rgb(0,0,0)';

		// Return default if no color provided.
		if ( empty( $color ) ) {
			return $default;
		}

		// Sanitize $color if "#" is provided.
		if ( '#' === $color[0] ) {
			$color = substr( $color, 1 );
		}

		// Check if color has 6 or 3 characters and get values.
		if ( strlen( $color ) === 6 ) {
				$hex = array( $color[0] . $color[1], $color[2] . $color[3], $color[4] . $color[5] );
		} elseif ( strlen( $color ) === 3 ) {
				$hex = array( $color[0] . $color[0], $color[1] . $color[1], $color[2] . $color[2] );
		} else {
				return $default;
		}

		// Convert hexadec to rgb.
		$rgb = array_map( 'hexdec', $hex );

		// Check if opacity is set(rgba or rgb).
		if ( $opacity ) {
			if ( abs( $opacity ) > 1 ) {
				$opacity = 1.0;
			}
			$output = 'rgba(' . implode( ',', $rgb ) . ',' . $opacity . ')';
		} else {
			$output = 'rgb(' . implode( ',', $rgb ) . ')';
		}

		// Return rgb(a) color string.
		return $output;
	}

	/**
	 * Return the URL to the admin screen
	 *
	 * @param string $tab     Tab path to load.
	 * @param string $sub_tab Subtab path to load.
	 *
	 * @return string URL to admin screen. Output is not escaped.
	 */
	public static function get_settings_url( $tab = '', $sub_tab = '' ) {
		$options_url = admin_url( 'options-general.php?page=simple-comment-editing' );
		if ( ! empty( $tab ) ) {
			$options_url = add_query_arg( array( 'tab' => sanitize_title( $tab ) ), $options_url );
			if ( ! empty( $sub_tab ) ) {
				$options_url = add_query_arg( array( 'subtab' => sanitize_title( $sub_tab ) ), $options_url );
			}
		}
		return $options_url;
	}

	/**
	 * Get the current admin tab.
	 *
	 * @return null|string Current admin tab.
	 */
	public static function get_admin_tab() {
		$tab = filter_input( INPUT_GET, 'tab', FILTER_DEFAULT );
		if ( $tab && is_string( $tab ) ) {
			return sanitize_text_field( sanitize_title( $tab ) );
		}
		return null;
	}

	/**
	 * Get the current admin sub-tab.
	 *
	 * @return null|string Current admin sub-tab.
	 */
	public static function get_admin_sub_tab() {
		$tab = filter_input( INPUT_GET, 'tab', FILTER_DEFAULT );
		if ( $tab && is_string( $tab ) ) {
			$subtab = filter_input( INPUT_GET, 'subtab', FILTER_DEFAULT );
			if ( $subtab && is_string( $subtab ) ) {
				return sanitize_text_field( sanitize_title( $subtab ) );
			}
		}
		return null;
	}

	/**
	 * Return the plugin slug.
	 *
	 * @return string plugin slug.
	 */
	public static function get_plugin_slug() {
		return dirname( plugin_basename( SCE_FILE ) );
	}

	/**
	 * Return the plugin path.
	 *
	 * @return string plugin path.
	 */
	public static function get_plugin_path() {
		return plugin_basename( SCE_FILE );
	}

	/**
	 * Return the basefile for the plugin.
	 *
	 * @return string base file for the plugin.
	 */
	public static function get_plugin_file() {
		return plugin_basename( SCE_FILE );
	}

	/**
	 * Return the version for the plugin.
	 *
	 * @return string Version for the plugin.
	 */
	public static function get_plugin_version() {
		return SCE_VERSION;
	}

	/**
	 * Get the Plugin Logo.
	 */
	public static function get_plugin_logo() {
		/**
		 * Filer the output of the plugin logo.
		 *
		 * Potentially change branding of the plugin.
		 *
		 * @since 3.0.0
		 *
		 * @param string $url URL to the plugin logo.
		 */
		return apply_filters( 'sce_plugin_logo_full', self::get_plugin_url( '/images/logo.png' ) );
	}

	/**
	 * Get the plugin author name.
	 */
	public static function get_plugin_author() {
		/**
		 * Filer the output of the plugin Author.
		 *
		 * Potentially change branding of the plugin.
		 *
		 * @since 3.0.0
		 *
		 * @param string $author Plugin author name.
		 */
		$plugin_author = apply_filters( 'sce_plugin_author', 'Ronald Huereca' );
		return $plugin_author;
	}

	/**
	 * Return the Plugin author URI.
	 */
	public static function get_plugin_author_uri() {
		/**
		 * Filer the output of the plugin Author URI.
		 *
		 * Potentially change branding of the plugin.
		 *
		 * @since 3.0.0
		 *
		 * @param string $author_uri Plugin author URI.
		 */
		$plugin_author = apply_filters( 'sce_plugin_author_uri', 'https://github.com/ronalfy/simple-comment-editing' );
		return $plugin_author;
	}

	/**
	 * Get the Plugin Icon.
	 */
	public static function get_plugin_icon() {
		/**
		 * Filer the output of the plugin icon.
		 *
		 * Potentially change branding of the plugin.
		 *
		 * @since 3.0.0
		 *
		 * @param string $icon_url URL to the plugin icon.
		 */
		return apply_filters( 'sce_plugin_icon', self::get_plugin_url( '/images/logo.png' ) );
	}

	/**
	 * Return the plugin name for the plugin.
	 *
	 * @return string Plugin name.
	 */
	public static function get_plugin_name() {
		/**
		 * Filer the output of the plugin name.
		 *
		 * Potentially change branding of the plugin.
		 *
		 * @since 3.0.0
		 *
		 * @param string $name Plugin name.
		 */
		return apply_filters( 'sce_plugin_name', __( 'Simple Comment Editing', 'simple-comment-editing' ) );
	}

	/**
	 * Return the plugin description for the plugin.
	 *
	 * @return string plugin description.
	 */
	public static function get_plugin_description() {
		/**
		 * Filer the output of the plugin name.
		 *
		 * Potentially change branding of the plugin.
		 *
		 * @since 3.0.0
		 *
		 * @param string $description Plugin description.
		 */
		return apply_filters( 'sce_plugin_description', __( 'Allow your users to edit comments.', 'simple-comment-editing' ) );
	}

	/**
	 * Retrieve the plugin URI.
	 */
	public static function get_plugin_uri() {
		/**
		 * Filer the output of the plugin URI.
		 *
		 * Potentially change branding of the plugin.
		 *
		 * @since 3.0.0
		 *
		 * @param string $uri Plugin URI.
		 */
		return apply_filters( 'sce_plugin_uri', 'https://github.com/ronalfy/simple-comment-editing' );
	}

	/**
	 * Retrieve the plugin Menu Name.
	 */
	public static function get_plugin_menu_name() {
		/**
		 * Filer the output of the plugin menu name.
		 *
		 * Potentially change branding of the plugin.
		 *
		 * @since 3.0.0
		 *
		 * @param string $menu_name Plugin menu name.
		 */
		return apply_filters( 'sce_plugin_menu_name', __( 'Simple Comment Editing', 'simple-comment-editing' ) );
	}

	/**
	 * Retrieve the plugin title.
	 */
	public static function get_plugin_title() {
		/**
		 * Filer the output of the plugin title.
		 *
		 * Potentially change branding of the plugin.
		 *
		 * @since 3.0.0
		 *
		 * @param string $title Plugin menu title.
		 */
		return apply_filters( 'sce_plugin_menu_title', self::get_plugin_name() );
	}

	/**
	 * Returns appropriate html for KSES.
	 *
	 * @param bool $svg Whether to add SVG data to KSES.
	 */
	public static function get_kses_allowed_html( $svg = true ) {
		$allowed_tags = wp_kses_allowed_html();

		$allowed_tags['nav']        = array(
			'class' => array(),
		);
		$allowed_tags['a']['class'] = array();

		if ( ! $svg ) {
			return $allowed_tags;
		}
		$allowed_tags['svg'] = array(
			'xmlns'       => array(),
			'fill'        => array(),
			'viewbox'     => array(),
			'role'        => array(),
			'aria-hidden' => array(),
			'focusable'   => array(),
			'class'       => array(),
		);

		$allowed_tags['path'] = array(
			'd'       => array(),
			'fill'    => array(),
			'opacity' => array(),
		);

		$allowed_tags['g'] = array();

		$allowed_tags['use'] = array(
			'xlink:href' => array(),
		);

		$allowed_tags['symbol'] = array(
			'aria-hidden' => array(),
			'viewBox'     => array(),
			'id'          => array(),
			'xmls'        => array(),
		);

		return $allowed_tags;
	}

	/**
	 * Get the plugin directory for a path.
	 *
	 * @param string $path The path to the file.
	 *
	 * @return string The new path.
	 */
	public static function get_plugin_dir( $path = '' ) {
		$dir = rtrim( plugin_dir_path( SCE_FILE ), '/' );
		if ( ! empty( $path ) && is_string( $path ) ) {
			$dir .= '/' . ltrim( $path, '/' );
		}
		return $dir;
	}

	/**
	 * Return a plugin URL path.
	 *
	 * @param string $path Path to the file.
	 *
	 * @return string URL to to the file.
	 */
	public static function get_plugin_url( $path = '' ) {
		$dir = rtrim( plugin_dir_url( SCE_FILE ), '/' );
		if ( ! empty( $path ) && is_string( $path ) ) {
			$dir .= '/' . ltrim( $path, '/' );
		}
		return $dir;
	}

	/**
	 * Gets the highest priority for a filter.
	 *
	 * @param int $subtract The amount to subtract from the high priority.
	 *
	 * @return int priority.
	 */
	public static function get_highest_priority( $subtract = 0 ) {
		$highest_priority = PHP_INT_MAX;
		$subtract         = absint( $subtract );
		if ( 0 === $subtract ) {
			--$highest_priority;
		} else {
			$highest_priority = absint( $highest_priority - $subtract );
		}
		return $highest_priority;
	}
}
