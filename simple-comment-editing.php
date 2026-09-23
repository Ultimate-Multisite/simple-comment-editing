<?php
/**
 * Main class for Simple Comment Editing.
 *
 * @package DLXPlugins\CommentEditLite
 */

namespace DLXPlugins\CommentEditLite;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'No direct access.' );
}

use DLXPlugins\CommentEditLite\Admin\Admin_Settings;

/**
 * Main class for Simple Comment Editing.
 */
class Simple_Comment_Editing {

	/**
	 * Class instance.
	 *
	 * @var Simple_Comment_Editing
	 */
	private static $instance = null;

	/**
	 * The loading image when editing a comment.
	 *
	 * @var string The loading image when editing a comment.
	 */
	private static $loading_img = '';

	/**
	 * Whether or not users can delete their comments.
	 *
	 * @var bool Whether or not users can delete their comments.
	 */
	public static $allow_delete = true;

	/**
	 * Error object for WP_Error.
	 *
	 * @var \WP_Error Error object for WP_Error.
	 */
	public static $errors;

	/**
	 * The scheme (http/https) for admin-ajax.php.
	 *
	 * @var string The scheme (http/https) for admin-ajax.php.
	 */
	private static $scheme;

	/**
	 * Mailchimp API variable with <sp> (server prefix) for search/replace.
	 *
	 * @var string Mailchimp API variable.
	 */
	private $mailchimp_api = 'https://<sp>.api.mailchimp.com/3.0/';

	/**
	 * Whether the core/comment-content block is currently rendering.
	 *
	 * @var bool
	 */
	private $rendering_comment_content_block = false;

	/**
	 * Deferred block comment markers and replacement markup, keyed by comment ID.
	 *
	 * @var array
	 */
	private $deferred_block_comments = array();

	/**
	 * Retrieve an instance of the class.
	 *
	 * @return Simple_Comment_Editing
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Main hook initializer. Should be called right after plugins have finished loading.
	 */
	public function plugins_loaded() {
		add_action( 'init', array( $this, 'init' ), 9 );

		// Determine http/https admin-ajax issue.
		self::$scheme = is_ssl() ? 'https' : 'http';

		/**
		* Filter: sce_loading_img
		*
		* Replace the loading image with a custom version.
		*
		* @since 1.0.0
		*
		* @param string  $image_url URL path to the loading image.
		*/
		self::$loading_img = esc_url( apply_filters( 'sce_loading_img', Functions::get_plugin_url( '/images/loading.gif' ) ) );
	}

	/**
	 * Initialize the plugin.
	 *
	 * @see init action.
	 */
	public function init() {
		new Output();

		/**
		 * Filter: sce_allow_delete
		 *
		 * Determine if users can delete their comments.
		 *
		 * @since 1.1.0
		 *
		 * @param bool $allow_delete True allows deletion, false does not.
		 */
		self::$allow_delete = (bool) apply_filters( 'sce_allow_delete', self::$allow_delete );

		// Skip out and do nothing if we're in the admin and not doing AJAX.
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return false;
		}

		// Initialize errors.
		self::$errors = new \WP_Error();
		self::$errors->add( 'nonce_fail', __( 'You do not have permission to edit this comment.', 'simple-comment-editing' ) );
		self::$errors->add( 'edit_fail', __( 'You can no longer edit this comment.', 'simple-comment-editing' ) );
		self::$errors->add( 'timer_fail', __( 'Timer could not be stopped.', 'simple-comment-editing' ) );
		self::$errors->add( 'comment_empty', __( 'Your comment cannot be empty. Delete instead?', 'simple-comment-editing' ) );
		self::$errors->add( 'comment_marked_spam', __( 'This comment was marked as spam.', 'simple-comment-editing' ) );

		// When a comment is posted.
		add_action( 'comment_post', array( $this, 'comment_posted' ), 100, 1 );

		// Loading scripts.
		add_filter( 'sce_load_scripts', array( $this, 'maybe_load_scripts' ), 5, 1 );
		add_action( 'wp_enqueue_scripts', array( $this, 'add_scripts' ) );

		// Init ajax.
		Ajax::run();

		// Init mailchimp.
		Mailchimp::run();

		// Init WooCommerce.
		WooCommerce::run();

		/* Begin Filters */
		if ( ! is_feed() && ! defined( 'DOING_SCE' ) ) {
			add_filter( 'comment_excerpt', array( $this, 'add_edit_interface' ), 1000, 2 );
			add_filter( 'comment_text', array( $this, 'add_edit_interface' ), 1000, 2 );
			add_filter( 'thesis_comment_text', array( $this, 'add_edit_interface' ), 1000, 2 );
			add_filter( 'render_block_context', array( $this, 'maybe_mark_comment_content_block' ), 10, 2 );
			add_filter( 'render_block_core/comment-content', array( $this, 'restore_deferred_edit_interface' ), 10, 3 );
		}

		// Add button themes.
		add_filter( 'sce_button_extra_save', array( $this, 'maybe_add_save_icon' ) );
		add_filter( 'sce_button_extra_cancel', array( $this, 'maybe_add_cancel_icon' ) );
		add_filter( 'sce_button_extra_delete', array( $this, 'maybe_add_delete_icon' ) );
		add_filter( 'sce_wrapper_class', array( $this, 'output_theme_class' ) );
	} //end init

	/**
	 * Adds the SCE interface if a user can edit their comment
	 *
	 * Called via the comment_text or comment_excerpt filter to add the SCE editing interface to a comment.
	 *
	 * @param string      $comment_content The comment content.
	 * @param array|false $passed_comment The comment object.
	 *
	 * @since 1.0
	 */
	public function add_edit_interface( $comment_content, $passed_comment = false ) {
		global $comment; // For Thesis.
		if ( ( ! $comment && ! $passed_comment ) || empty( $comment_content ) ) {
			return $comment_content;
		}
		if ( $passed_comment ) {
			$comment = (object) $passed_comment; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		}

		$comment_id = absint( $comment->comment_ID );
		$post_id    = absint( $comment->comment_post_ID );

		// Check to see if a user can edit their comment.
		if ( ! Functions::can_edit( $comment_id, $post_id ) ) {
			return $comment_content;
		}

		// Variables for later.
		$original_content  = $comment_content;
		$raw_content       = $comment->comment_content; // For later usage in the textarea.
		$defer_after_block = $this->should_defer_for_comment_content_block( $comment );
		$start_marker      = '';
		$end_marker        = '';

		if ( $defer_after_block ) {
			$marker_id    = wp_generate_uuid4();
			$start_marker = 'SCE_COMMENT_START_' . $marker_id;
			$end_marker   = 'SCE_COMMENT_END_' . $marker_id;
		}

		// Yay, user can edit - Add the initial wrapper.
		$comment_wrapper = sprintf( '<div id="sce-comment%d" class="sce-comment">%s</div>', $comment_id, $comment_content );

		$classes = array( 'sce-edit-comment' );
		/**
		 * Filter: sce_wrapper_class
		 *
		 * Filter allow editing of wrapper class
		 *
		 * @since 2.3.0
		 *
		 * @param array $classes Array of classes for the initial wrapper.
		 */
		$classes = apply_filters( 'sce_wrapper_class', $classes );

		// Create Overall wrapper for JS interface.
		$sce_content = sprintf( '<div id="sce-edit-comment%d" class="%s">', $comment_id, esc_attr( implode( ' ', $classes ) ) );

		// Edit Button.
		$sce_content  .= '<div class="sce-edit-button sce-hide">';
		$ajax_edit_url = add_query_arg(
			array(
				'action'      => 'sce_get_time_left',
				'editComment' => 1,
				'cid'         => $comment_id,
				'pid'         => $post_id,
				'nonce'       => wp_create_nonce( 'sce-edit-comment' . $comment_id ),
			),
			admin_url( 'admin-ajax.php', self::$scheme )
		);

		/**
		* Filter: sce_text_edit
		*
		* Filter allow editing of edit text
		*
		* @since 2.0.0
		*
		* @param string $text Translated click-to-edit text.
		*/
		$click_to_edit_text = apply_filters( 'sce_text_edit', __( 'Click to Edit', 'simple-comment-editing' ) );

		/**
		* Filter: sce_text_edit_delete
		*
		* Filter allow editing of the delete text
		*
		* @since 2.6.0
		*
		* @param string $text Translated delete text.
		*/
		$delete_edit_text = apply_filters( 'sce_text_edit_delete', __( 'Delete Comment', 'simple-comment-editing' ) );

		$allow_edit_delete = apply_filters( 'sce_allow_delete_button', false );
		$allow_edit        = apply_filters( 'sce_allow_edit_button', true );

		if ( $allow_edit && ! $allow_edit_delete ) {
			$sce_content .= sprintf( '<a class="sce-edit-button-main" href="%s">%s</a>', esc_url( $ajax_edit_url ), esc_html( $click_to_edit_text ) );
		} elseif ( $allow_edit && $allow_edit_delete ) {
			$sce_content .= sprintf( '<a class="sce-edit-button-main" href="%s">%s</a>', esc_url( $ajax_edit_url ), esc_html( $click_to_edit_text ) );
			$sce_content .= '<span class="sce-seperator">&nbsp;&ndash;&nbsp;</span>';
			$sce_content .= sprintf( '<a class="sce-delete-button-main" href="%s">%s</a>', esc_url( $ajax_edit_url ), esc_html( $delete_edit_text ) );
		} elseif ( ! $allow_edit && $allow_edit_delete ) {
			$sce_content .= sprintf( '<a class="sce-delete-button-main" href="%s">%s</a>', esc_url( $ajax_edit_url ), esc_html( $delete_edit_text ) );
		} else {
			$sce_content .= sprintf( '<a class="sce-edit-button-main" href="%s">%s</a>', esc_url( $ajax_edit_url ), esc_html( $click_to_edit_text ) );
		}

		/**
		 * Filter: sce_show_timer
		 *
		 * Filter allow you to hide the timer
		 *
		 * @since 2.3.0
		 *
		 * @param bool $show_timer Whether to show the timer.
		 */
		$show_timer        = apply_filters( 'sce_show_timer', true );
		$unlimited_editing = apply_filters( 'sce_unlimited_editing', false, $comment );
		if ( $show_timer && false === $unlimited_editing ) {
			$sce_content .= '<span class="sce-seperator">&nbsp;&ndash;&nbsp;</span>';
			$sce_content .= '<span class="sce-timer"></span>';
		}
		$sce_content .= '</div><!-- .sce-edit-button -->';

		// Loading button.
		$sce_content .= '<div class="sce-loading" style="display: none;">';
		$sce_content .= sprintf( '<img src="%1$s" title="%2$s" alt="%2$s" />', esc_url( self::$loading_img ), esc_attr__( 'Loading', 'simple-comment-editing' ) );
		$sce_content .= '</div><!-- sce-loading -->';

		// Textarea.
		$textarea_content = '<div class="sce-textarea" style="display: none;">';

		/**
		* Filter: sce_extra_fields_pre
		*
		* Filter to add additional form fields before the textarea.
		*
		* @since 3.0.0
		*
		* @param string     $content    Empty string.
		* @param int        $post_id    Post ID.
		* @param int        $comment_id Comment ID.
		* @param \WP_Comment $comment    Comment object.
		*/
		$textarea_content .= apply_filters( 'sce_extra_fields_pre', '', $post_id, $comment_id, $comment );
		$textarea_content .= '<div class="sce-comment-textarea">';
		$textarea_content .= '<textarea class="sce-comment-text" %s>%s</textarea>';
		$textarea_content .= '</div><!-- .sce-comment-textarea -->';

		/**
		* Filter: sce_extra_fields
		*
		* Filter to add additional form fields
		*
		* @since 1.5.0
		*
		* @param string $content    Empty string.
		* @param int    $post_id    Post ID.
		* @param int    $comment_id Comment ID.
		*/
		$textarea_content .= apply_filters( 'sce_extra_fields', '', $post_id, $comment_id );

		$textarea_content       .= '%s</div><!-- .sce-textarea -->';
		$textarea_button_content = '<div class="sce-comment-edit-buttons">';

		/**
		* Filter: sce_text_save
		*
		* Filter allow editing of save text
		*
		* @since 2.0.0
		*
		* @param string $text Translated save text.
		*/
		$save_text = apply_filters( 'sce_text_save', __( 'Save', 'simple-comment-editing' ) );

		/**
		* Filter: sce_text_cancel
		*
		* Filter allow editing of cancel text
		*
		* @since 2.0.0
		*
		* @param string $text Translated cancel text.
		*/
		$cancel_text = apply_filters( 'sce_text_cancel', __( 'Cancel', 'simple-comment-editing' ) );

		/**
		* Filter: sce_text_delete
		*
		* Filter allow editing of delete text
		*
		* @since 2.0.0
		*
		* @param string $text Translated delete text.
		*/
		$delete_text = apply_filters( 'sce_text_delete', __( 'Delete', 'simple-comment-editing' ) );

		$textarea_buttons = '<div class="sce-comment-edit-buttons-group">';

		/**
		 * Filter: sce_button_extra_save
		 *
		 * Add an extra item before the save button text. This is useful for adding icons.
		 *
		 * @param string $content Empty string.
		 */
		$textarea_buttons .= sprintf( '<button class="sce-comment-save">%s%s</button>', apply_filters( 'sce_button_extra_save', '' ), esc_html( $save_text ) );

		/**
		 * Filter: sce_button_extra_cancel
		 *
		 * Add an extra item before the cancel button text. This is useful for adding icons.
		 *
		 * @param string $content Empty string.
		 */
		$textarea_buttons .= sprintf( '<button class="sce-comment-cancel">%s%s</button>', apply_filters( 'sce_button_extra_cancel', '' ), esc_html( $cancel_text ) );

		/**
		 * Filter: sce_button_extra_delete
		 *
		 * Add an extra item before the delete button text. This is useful for adding icons.
		 *
		 * @param string $content Empty string.
		 */
		$textarea_buttons .= self::$allow_delete ? sprintf( '<button class="sce-comment-delete">%s%s</button>', apply_filters( 'sce_button_extra_delete', '' ), esc_html( $delete_text ) ) : '';
		$textarea_buttons .= '</div><!-- .sce-comment-edit-buttons-group -->';

		/**
		 * Filter: sce_show_timer
		 *
		 * Filter allow you to hide the timer
		 *
		 * @param bool $show_timer Whether to show the timer.
		 */
		if ( apply_filters( 'sce_show_timer', true ) ) {
			$textarea_buttons .= '<div class="sce-timer"></div>';
		}
		/**
		* Filter: sce_buttons
		*
		* Filter to add button content
		*
		* @since 1.3.0
		*
		* @param string  $textarea_buttons Button HTML
		* @param int     $comment_id       Comment ID
		*/
		$textarea_buttons         = apply_filters( 'sce_buttons', $textarea_buttons, $comment_id );
		$textarea_button_content .= $textarea_buttons . '</div><!-- .sce-comment-edit-buttons -->';
		$textarea_content         = sprintf(
			$textarea_content,
			'style="max-width: 100%; min-height: 150px;"',
			esc_textarea( $raw_content ),
			$textarea_button_content
		);

		// End.
		$sce_content .= $textarea_content . '</div><!-- .sce-edit-comment -->';

		// Status Area.
		$sce_content .= sprintf( '<div id="sce-edit-comment-status%d" class="sce-status" style="display: none;"></div><!-- .sce-status -->', $comment_id );

		/**
		* Filter: sce_content
		*
		* Filter to overral sce output
		*
		* @since 1.3.0
		*
		* @param string  $sce_content SCE content
		* @param int     $comment_id  Comment ID of the comment
		*/
		$sce_content = apply_filters( 'sce_content', $sce_content, $comment_id );

		if ( $defer_after_block ) {
			$this->deferred_block_comments[ $comment_id ] = array(
				'start'       => $start_marker,
				'end'         => $end_marker,
				'replacement' => '<div id="sce-comment' . $comment_id . '" class="sce-comment">',
				'interface'   => '</div>' . $sce_content,
			);

			return $start_marker . $original_content . $end_marker;
		}

		// Return content.
		$comment_content = $comment_wrapper . $sce_content;
		return $comment_content;
	}

	/**
	 * Mark when the core/comment-content block is about to render.
	 *
	 * @since 3.4.1
	 *
	 * @param array $context      The block context.
	 * @param array $parsed_block The block being rendered.
	 * @return array Unchanged block context.
	 */
	public function maybe_mark_comment_content_block( $context, $parsed_block ) {
		$this->rendering_comment_content_block = ( isset( $parsed_block['blockName'] ) && 'core/comment-content' === $parsed_block['blockName'] );
		return $context;
	}

	/**
	 * Whether Core will strip the edit interface from a pending comment block.
	 *
	 * @since 3.4.1
	 *
	 * @param object $comment Comment object.
	 * @return bool True when interface injection must be deferred.
	 */
	private function should_defer_for_comment_content_block( $comment ) {
		if ( ! $this->rendering_comment_content_block ) {
			return false;
		}
		if ( ! isset( $comment->comment_approved ) || '0' !== (string) $comment->comment_approved ) {
			return false;
		}

		$commenter = wp_get_current_commenter();
		return empty( $commenter['comment_author'] );
	}

	/**
	 * Restore trusted SCE markup after Core sanitizes a pending comment block.
	 *
	 * Plain-text markers pass through Core's wp_kses() call, allowing exact string
	 * replacement without parsing or reserializing the rendered block HTML.
	 *
	 * @since 3.4.1
	 *
	 * @param string    $block_content The rendered block HTML.
	 * @param array     $parsed_block  The parsed block.
	 * @param \WP_Block $block         The block instance.
	 * @return string Filtered block HTML.
	 */
	public function restore_deferred_edit_interface( $block_content, $parsed_block, $block ) {
		$this->rendering_comment_content_block = false;

		if ( ! ( $block instanceof \WP_Block ) || ! isset( $block->context['commentId'] ) ) {
			return $block_content;
		}

		$comment_id = absint( $block->context['commentId'] );
		if ( empty( $this->deferred_block_comments[ $comment_id ] ) ) {
			return $block_content;
		}

		$deferred = $this->deferred_block_comments[ $comment_id ];
		unset( $this->deferred_block_comments[ $comment_id ] );

		$has_start = false !== strpos( $block_content, $deferred['start'] );
		$has_end   = false !== strpos( $block_content, $deferred['end'] );
		if ( ! $has_start || ! $has_end ) {
			return str_replace( array( $deferred['start'], $deferred['end'] ), '', $block_content );
		}

		return str_replace(
			array( $deferred['start'], $deferred['end'] ),
			array( $deferred['replacement'], $deferred['interface'] ),
			$block_content
		);
	}

	/**
	 * Add a delete icon.
	 *
	 * Add a delete icon.
	 *
	 * @since 3.0.0
	 * @access public
	 *
	 * @param string $text Button text.
	 *
	 * @return string Button text
	 */
	public function maybe_add_delete_icon( $text ) {
		if ( true === Options::get_options( false, 'show_icons' ) ) {
			return '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zM19 4h-3.5l-1-1h-5l-1 1H5v2h14V4z"/><path d="M0 0h24v24H0z" fill="none"/></svg>';
		}
		return $text;
	}

	/**
	 * Add a cancel icon.
	 *
	 * Add a cancel icon.
	 *
	 * @since 3.0.0
	 * @access public
	 *
	 * @param string $text Button text.
	 *
	 * @return string Button text
	 */
	public function maybe_add_cancel_icon( $text ) {
		if ( true === Options::get_options( false, 'show_icons' ) ) {
			return '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="24" viewBox="0 0 24 20"><path d="M12 2C6.47 2 2 6.47 2 12s4.47 10 10 10 10-4.47 10-10S17.53 2 12 2zm5 13.59L15.59 17 12 13.41 8.41 17 7 15.59 10.59 12 7 8.41 8.41 7 12 10.59 15.59 7 17 8.41 13.41 12 17 15.59z"/><path d="M0 0h24v24H0z" fill="none"/></svg>';
		}
		return $text;
	}

	/**
	 * Add a save icon.
	 *
	 * Add a save icon.
	 *
	 * @since 3.0.0
	 * @access public
	 *
	 * @param string $text Button text.
	 *
	 * @return string Button text
	 */
	public function maybe_add_save_icon( $text ) {
		if ( true === Options::get_options( false, 'show_icons' ) ) {
			return '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20"><path d="M0 0h24v24H0z" fill="none"/><path d="M17 3H5c-1.11 0-2 .9-2 2v14c0 1.1.89 2 2 2h14c1.1 0 2-.9 2-2V7l-4-4zm-5 16c-1.66 0-3-1.34-3-3s1.34-3 3-3 3 1.34 3 3-1.34 3-3 3zm3-10H5V5h10v4z"/></svg>';
		}
		return $text;
	}

	/**
	 * Returns a theme class.
	 *
	 * Returns a theme class.
	 *
	 * @since 1.0.0
	 * @access public
	 *
	 * @param array $classes SCE Wrapper class.
	 * @return array $classes New SCE theme classes
	 */
	public function output_theme_class( $classes = array() ) {
		$theme = Options::get_options( false, 'button_theme' );
		if ( false === $theme ) {
			return $classes;
		}
		$classes[] = $theme;
		return $classes;
	}

	/**
	 * Adds the necessary JavaScript for the plugin (only loads on posts/pages)
	 *
	 * Called via the wp_enqueue_scripts
	 *
	 * @since 1.0
	 */
	public function add_scripts() {
		if ( ! is_single() && ! is_singular() && ! is_page() ) {
			return;
		}

		// Check if there are any cookies present, otherwise don't load the scripts - WPAC_PLUGIN_NAME is for wp-ajaxify-comments (if the plugin is installed, load the JavaScript file).

		/**
		 * Filter: sce_load_scripts
		 *
		 * Boolean to decide whether to load SCE scripts or not
		 *
		 * @since 1.5.0
		 *
		 * @param bool $load_scripts Whether to load scripts.
		 */
		$load_scripts = apply_filters( 'sce_load_scripts', false );
		if ( ! $load_scripts ) {
			return;
		}

		$main_script_uri = Functions::get_plugin_url( '/dist/sce-editing.js' );
		wp_enqueue_script(
			'simple-comment-editing',
			$main_script_uri,
			array( 'wp-i18n', 'wp-hooks' ),
			SCE_VERSION,
			true
		);
		wp_enqueue_style(
			'simple-comment-editing',
			Functions::get_plugin_url( 'dist/sce-frontend.css' ),
			array(),
			SCE_VERSION,
			'all'
		);

		/**
		* Action: sce_scripts_loaded
		*
		* Allows other plugins to load scripts after SCE has loaded
		*
		* @since 2.3.4
		*/
		do_action( 'sce_scripts_loaded' );

		/* For translations in JS */
		wp_set_script_translations( 'simple-comment-editing', 'simple-comment-editing' );

		/**
		 * Filter: sce_allow_delete_confirmation
		 *
		 * Boolean to decide whether to show a delete confirmation
		 *
		 * @since 2.1.7
		 *
		 * @param bool $allow_confirmation Whether to show a confirmation.
		 */
		$allow_delete_confirmation = (bool) apply_filters( 'sce_allow_delete_confirmation', true );

		wp_localize_script(
			'simple-comment-editing',
			'simple_comment_editing',
			array(
				'and'                       => __( 'and', 'simple-comment-editing' ),
				'confirm_delete'            => apply_filters( 'sce_confirm_delete', __( 'Do you want to delete this comment?', 'simple-comment-editing' ) ),
				'comment_deleted'           => apply_filters( 'sce_comment_deleted', __( 'Your comment has been removed.', 'simple-comment-editing' ) ),
				'comment_deleted_error'     => apply_filters( 'sce_comment_deleted_error', __( 'Your comment could not be deleted', 'simple-comment-editing' ) ),
				'empty_comment'             => apply_filters( 'sce_empty_comment', self::$errors->get_error_message( 'comment_empty' ) ),
				'allow_delete'              => self::$allow_delete,
				'allow_delete_confirmation' => $allow_delete_confirmation,
				'ajax_url'                  => admin_url( 'admin-ajax.php', self::$scheme ),
				'nonce'                     => wp_create_nonce( 'sce-general-ajax-nonce' ),
				'timer_appearance'          => sanitize_text_field( Options::get_options( false, 'timer_appearance' ) ),
			)
		);

		/**
		* Action: sce_load_assets
		*
		* Allow other plugins to load scripts/styyles for SCE
		*
		* @since 2.3.0
		*/
		do_action( 'sce_load_assets' );
	}

	/**
	 * When a comment has been posted.
	 *
	 * Called when a comment has been posted - Stores a cookie for later editing
	 *
	 * @since 1.0
	 *
	 * @param int $comment_id The Comment ID.
	 */
	public function comment_posted( $comment_id ) {
		$comment        = get_comment( $comment_id, OBJECT );
		$post_id        = $comment->comment_post_ID;
		$post           = get_post( $post_id, OBJECT );
		$comment_status = $comment->comment_approved;

		// Do some initial checks to weed out those who shouldn't be able to have editable comments.
		if ( 'spam' === $comment_status ) {
			return; // Marked as spam - no editing allowed.
		}

		// Remove expired comments.
		$this->remove_security_keys();

		$user_id = Functions::get_user_id();

		// Don't set a cookie if a comment is posted via Ajax.
		/**
		 * Filter: sce_can_edit_cookie_bypass
		 * Bypass the cookie based user verification.
		 *
		 * @param bool             $bypass     Whether to bypass cookie authentication.
		 * @param \WP_Comment|null $comment    Comment object.
		 * @param int        $comment_id The comment ID.
		 * @param int|string $post_id    The post ID of the comment.
		 * @param int        $user_id    The logged in user ID.
		 *
		 * @return boolean
		 */
		$cookie_bypass = apply_filters( 'sce_can_edit_cookie_bypass', false, $comment, $comment_id, $post_id, $user_id );

		// if we are logged in and are the comment author, bypass cookie check.
		if ( 0 !== $user_id && ( $post->post_author === $user_id || $comment->user_id === $user_id ) ) {
			$cookie_bypass = true;
			update_comment_meta( $comment_id, '_sce', 'post_author' );
		}
		if ( ! defined( 'DOING_AJAX' ) && ! defined( 'EPOCH_API' ) ) {
			if ( false === $cookie_bypass ) {
				$this->generate_cookie_data( $post_id, $comment_id, 'setcookie' );
			}
		}
	} //end comment_posted

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
	private function get_cookie_value( $name ) {
		if ( isset( $_COOKIE[ $name ] ) ) {
			return sanitize_text_field( wp_unslash( $_COOKIE[ $name ] ) );
		} else {
			return false;
		}
	}

	/**
	 * Return a comment object
	 *
	 * Return a comment object
	 *
	 * @access private
	 * @since 1.5.0
	 *
	 * @param int $comment_id Comment ID.
	 * @return \WP_Comment|null Comment object.
	 */
	public static function get_comment( $comment_id ) {
		if ( isset( $GLOBALS['comment'] ) ) {
			unset( $GLOBALS['comment'] );   // caching.
		}
		$comment_to_return  = get_comment( $comment_id );
		$GLOBALS['comment'] = $comment_to_return; // phpcs:ignore.
		return $comment_to_return;
	}

	/**
	 * Generate or remove a comment cookie
	 *
	 * Generate or remove a comment cookie - Stored as post meta
	 *
	 * @access public
	 * @since 1.5.0
	 *
	 * @param int    $post_id Post ID.
	 * @param int    $comment_id Comment ID.
	 * @param string $return_action 'ajax', 'setcookie, 'removecookie'.
	 * @return void
	 */
	public function generate_cookie_data( $post_id = 0, $comment_id = 0, $return_action = 'ajax' ) {
		if ( 'ajax' === $return_action ) {
			check_ajax_referer( 'sce-general-ajax-nonce' );
		}

		if ( 0 === $post_id ) {
			$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		}

		// Get comment ID.
		if ( 0 === $comment_id ) {
			$comment_id = isset( $_POST['comment_id'] ) ? absint( $_POST['comment_id'] ) : 0;
		}

		// Get comment for IP and user-agent data.
		$comment = \get_comment( $comment_id, ARRAY_A );

		// Get hash and random security key - Stored in the style of Ajax Edit Comments.
		$comment_date_gmt  = '';
		$comment_author_ip = sanitize_text_field( $comment['comment_author_IP'] );
		/**
		 * Filter: sce_pre_comment_user_ip
		 *
		 * Whether to use the IP filter (true by default)
		 *
		 * @since 2.7.1
		 *
		 * @param bool $use_ip Whether to use the comment IP filter.
		 */
		if ( apply_filters( 'sce_pre_comment_user_ip', true ) ) {
			// Props: https://github.com/timreeves.
			$comment_author_ip = apply_filters( 'pre_comment_user_ip', $comment_author_ip );
		}
		$comment_date_gmt = current_time( 'Y-m-d', 1 );
		$user_agent       = isset( $comment['comment_agent'] ) ? sanitize_text_field( $comment['comment_agent'] ) : '';
		$hash             = md5( $comment_author_ip . $comment_date_gmt . Functions::get_user_id() . $user_agent );

		$rand            = '_wpAjax' . $hash . md5( wp_generate_password( 30, true, true ) ) . '-' . time();
		$maybe_save_meta = get_comment_meta( $comment_id, '_sce', true );
		$cookie_name     = 'SimpleCommentEditing' . $comment_id . $hash;
		$cookie_value    = $rand;
		$cookie_expire   = time() + ( 60 * Functions::get_comment_time() );

		if ( ! $maybe_save_meta ) {
			// Make sure we don't set post meta again for security reasons and subsequent calls to this method will generate a new key, so no calling it twice unless you want to remove a cookie.
			update_comment_meta( $comment_id, '_sce', $rand );
		} else {
			// Kinda evil, but if you try to call this method twice, removes the cookie.
			setcookie( $cookie_name, $cookie_value, time() - 60, COOKIEPATH, COOKIE_DOMAIN );
			wp_send_json_success();
			die( '' );
		}

		// Now store a cookie.
		if ( 'setcookie' === $return_action ) {
			setcookie( $cookie_name, $cookie_value, $cookie_expire, COOKIEPATH, COOKIE_DOMAIN );
		} elseif ( 'removecookie' === $return_action ) {
			setcookie( $cookie_name, $cookie_value, time() - 60, COOKIEPATH, COOKIE_DOMAIN );
		}

		$return = array(
			'name'       => $cookie_name,
			'value'      => $cookie_value,
			'expires'    => ( time() + ( 60 * Functions::get_comment_time() ) ) * 1000,
			'post_id'    => $post_id,
			'comment_id' => $comment_id,
			'path'       => COOKIEPATH,
		);
		if ( 'ajax' === $return_action ) {
			wp_send_json_success( $return );
			exit;
		} else {
			return;
		}
		// Should never reach this point, but just in case I suppose.
		wp_send_json_error();
		die( '' );
	}

	/**
	 * Whether to load scripts or not. Will load scripts if logged in, has Ajax comments, or has a cookie.
	 *
	 * Called via the sce_load_scripts filter
	 *
	 * @since 1.5.0
	 *
	 * @param bool $yes True or False.
	 *
	 * @return bool True to load scripts, false if not
	 */
	public function maybe_load_scripts( $yes ) {
		if ( defined( 'WPAC_PLUGIN_NAME' ) || is_user_logged_in() ) {
			return true;
		}

		/* Return True if user is logged in */
		if ( is_user_logged_in() ) {
			return true;
		}

		if ( empty( $_COOKIE ) ) {
			return false;
		}
		$has_cookie = false;
		foreach ( $_COOKIE as $cookie_name => $cookie_value ) {
			if ( substr( $cookie_name, 0, 20 ) === 'SimpleCommentEditing' ) {
				$has_cookie = true;
				break;
			}
		}
		return $has_cookie;
	}

	/**
	 * Removes a comment cookie
	 *
	 * Removes a comment cookie based on the passed comment
	 *
	 * @since 1.0
	 *
	 * @param array $comment The results from get_comment( $id, ARRAY_A ).
	 */
	public static function remove_comment_cookie( $comment ) {
		if ( ! is_array( $comment ) ) {
			return;
		}

		self::get_instance()->generate_cookie_data( $comment['comment_post_ID'], $comment['comment_ID'], 'removecookie' );
	}

	/**
	 * Remove security keys
	 *
	 * When a comment is posted, remove security keys
	 *
	 * @access private
	 * @since 2.0.2
	 */
	private function remove_security_keys() {

		$sce_security = get_transient( 'sce_security_keys' );
		if ( ! $sce_security ) {

			// Remove old SCE keys.
			$security_key_count = get_option( 'ajax-edit-comments_security_key_count' );
			if ( $security_key_count ) {
				global $wpdb;
				delete_option( 'ajax-edit-comments_security_key_count' );
				$wpdb->query(
					$wpdb->prepare(
						"DELETE FROM {$wpdb->postmeta} WHERE LEFT( meta_value, %d ) = %s",
						7,
						'_wpAjax'
					)
				);
			}
			// Delete expired meta.
			global $wpdb;
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->commentmeta} WHERE meta_key = %s AND CAST( SUBSTRING( meta_value, LOCATE( %s, meta_value ) + 1 ) AS UNSIGNED ) < %d",
					'_sce',
					'-',
					time() - ( Functions::get_comment_time() * MINUTE_IN_SECONDS )
				)
			);
			set_transient( 'sce_security_keys', true, HOUR_IN_SECONDS );
		}
	}
} //end class Simple_Comment_Editing

add_action( 'plugins_loaded', __NAMESPACE__ . '\sce_instantiate' );
/**
 * Instantiate SCE.
 */
function sce_instantiate() {
	$sce = Simple_Comment_Editing::get_instance();
	$sce->plugins_loaded();
	if ( is_admin() && apply_filters( 'sce_show_admin', true ) ) {
		new Admin_Settings();
		$sce_enqueue = new Enqueue();
		$sce_enqueue->run();
	}
} //end sce_instantiate


register_activation_hook( Functions::get_plugin_file(), __NAMESPACE__ . '\sce_plugin_activate' );
add_action( 'admin_init', __NAMESPACE__ . '\sce_plugin_activate_redirect' );

/**
 * Add an option upon activation to read in later when redirecting.
 */
function sce_plugin_activate() {
	if ( ! Functions::is_multisite() ) {
		add_option( 'comment-edit-lite-activate', sanitize_text_field( Functions::get_plugin_file() ) );
	}
}

/**
 * Redirect to Simple Comment Editing settings page upon activation.
 */
function sce_plugin_activate_redirect() {

	// If on multisite, bail.
	if ( Functions::is_multisite() ) {
		return;
	}

	// Make sure we're in the admin and that the option is available.
	if ( is_admin() && Functions::get_plugin_file() === get_option( 'comment-edit-lite-activate' ) ) {
		delete_option( 'comment-edit-lite-activate' );
		// GEt bulk activation variable if it exists.
		$maybe_multi = filter_input( INPUT_GET, 'activate-multi', FILTER_VALIDATE_BOOLEAN );

		// Return early if it's a bulk activation.
		if ( $maybe_multi ) {
			return;
		}

		$settings_url = admin_url( 'options-general.php?page=simple-comment-editing' );
		if ( class_exists( '\CommentEditPro\Comment_Edit_Pro' ) ) {
			$settings_url = admin_url( 'options-general.php?page=comment-edit-pro' );
		}
		wp_safe_redirect( esc_url( $settings_url ) );
		exit;
	}
}
