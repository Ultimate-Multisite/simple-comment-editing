<?php
/**
 * Preview, move and recover comment placements in the WordPress admin.
 *
 * @package CommentEditLite
 */

namespace DLXPlugins\CommentEditLite\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'No direct access.' );
}

/**
 * Moderation-only comment movement. Never changes comment content or metadata.
 */
class Comment_Movement {

	/**
	 * Register native admin entry points; no public or AJAX mutation endpoint.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_page' ) );
		add_filter( 'comment_row_actions', array( $this, 'row_action' ), 10, 2 );
		add_action( 'admin_post_sce_move_comment', array( $this, 'handle_move' ) );
		add_action( 'admin_post_sce_restore_comment', array( $this, 'handle_restore' ) );
	}

	/**
	 * Add a page under Comments, also providing access to interrupted operations.
	 */
	public function register_page() {
		add_submenu_page( 'edit-comments.php', __( 'Move comment', 'simple-comment-editing' ), __( 'Move comment', 'simple-comment-editing' ), 'moderate_comments', 'sce-move-comment', array( $this, 'render_page' ) );
	}

	/**
	 * Add an entry only for comments the current moderator may edit.
	 *
	 * @param array       $actions Existing actions.
	 * @param \WP_Comment $comment Comment row.
	 * @return array
	 */
	public function row_action( $actions, $comment ) {
		if ( ! is_wp_error( $this->authorize_comment( $comment ) ) ) {
			$actions['sce_move'] = '<a href="' . esc_url( $this->page_url( array( 'comment_id' => $comment->comment_ID ) ) ) . '">' . esc_html__( 'Move', 'simple-comment-editing' ) . '</a>';
		}
		return $actions;
	}

	/**
	 * Read only scalar form fields; arrays, negatives and malformed IDs fail closed.
	 *
	 * @param string $name Field name.
	 * @param int    $type INPUT_POST or INPUT_GET.
	 * @return string
	 */
	private function input( $name, $type = INPUT_POST ) {
		$value = filter_input( $type, $name, FILTER_UNSAFE_RAW, FILTER_REQUIRE_SCALAR );
		return is_string( $value ) ? sanitize_text_field( $value ) : '';
	}

	/**
	 * Parse a positive integer without coercing invalid input into a valid ID.
	 *
	 * @param string $value Submitted value.
	 * @return int
	 */
	private function positive_id( $value ) {
		return ctype_digit( $value ) && (string) (int) $value === $value ? (int) $value : 0;
	}

	/**
	 * Build a local admin URL.
	 *
	 * @param array $args Query arguments.
	 * @return string
	 */
	private function page_url( $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => 'sce-move-comment' ), $args ), admin_url( 'edit-comments.php' ) );
	}

	/**
	 * Require moderation plus read/edit access to a real comment-enabled post.
	 *
	 * @param int $post_id Post ID.
	 * @return \WP_Post|\WP_Error
	 */
	private function authorize_post( $post_id ) {
		$post = $post_id > 0 ? get_post( $post_id ) : null;
		if ( ! current_user_can( 'moderate_comments' ) || ! $post || ! current_user_can( 'edit_post', $post_id ) || ! current_user_can( 'read_post', $post_id ) ) {
			return new \WP_Error( 'access', __( 'You cannot move comments to or from this post.', 'simple-comment-editing' ) );
		}
		if ( in_array( $post->post_status, array( 'trash', 'auto-draft', 'inherit' ), true ) || ! post_type_supports( $post->post_type, 'comments' ) || 'product' === $post->post_type ) {
			return new \WP_Error( 'post', __( 'Choose a comment-enabled post that is not trashed, an attachment, or a WooCommerce product. Product reviews are not supported by this tool.', 'simple-comment-editing' ) );
		}
		return $post;
	}

	/**
	 * Authorize every affected comment, not merely the selected root.
	 *
	 * @param \WP_Comment|null $comment Comment to check.
	 * @return true|\WP_Error
	 */
	private function authorize_comment( $comment ) {
		if ( ! $comment || ! current_user_can( 'moderate_comments' ) || ! current_user_can( 'edit_comment', $comment->comment_ID ) ) {
			return new \WP_Error( 'access', __( 'You cannot move this comment.', 'simple-comment-editing' ) );
		}
		$post = $this->authorize_post( (int) $comment->comment_post_ID );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		if ( ! in_array( $comment->comment_type, array( '', 'comment' ), true ) || metadata_exists( 'comment', $comment->comment_ID, 'rating' ) || in_array( $comment->comment_approved, array( 'spam', 'trash', 'post-trashed' ), true ) ) {
			return new \WP_Error( 'type', __( 'Only ordinary comments outside Spam and Trash can be moved. Product reviews, pingbacks and trackbacks are not supported.', 'simple-comment-editing' ) );
		}
		return true;
	}

	/**
	 * Snapshot only placement fields; never persist comment text or author data.
	 *
	 * @param \WP_Comment $comment Comment to snapshot.
	 * @return array
	 */
	private function placement( $comment ) {
		return array(
			'post'   => (int) $comment->comment_post_ID,
			'parent' => (int) $comment->comment_parent,
		);
	}

	/**
	 * Build a bounded, deterministic preview including descendants left behind.
	 *
	 * @param int  $comment_id Selected root.
	 * @param int  $destination Destination post.
	 * @param bool $subtree Include descendants.
	 * @return array|\WP_Error
	 */
	private function plan( $comment_id, $destination, $subtree ) {
		$root = $comment_id > 0 ? get_comment( $comment_id ) : null;
		if ( ! $root ) {
			return new \WP_Error( 'access', __( 'You cannot move this comment.', 'simple-comment-editing' ) );
		}
		$access = $this->authorize_comment( $root );
		if ( is_wp_error( $access ) ) {
			return $access;
		}
		$post = $this->authorize_post( $destination );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		if ( 'open' !== $post->comment_status ) {
			return new \WP_Error( 'closed', __( 'The destination post must accept comments.', 'simple-comment-editing' ) );
		}
		$source = (int) $root->comment_post_ID;
		if ( $source === $destination ) {
			return new \WP_Error( 'same_post', __( 'The destination must be a different post.', 'simple-comment-editing' ) );
		}

		// Validate the original ancestor chain before using it for reparenting.
		$seen   = array( $comment_id => true );
		$parent = (int) $root->comment_parent;
		while ( $parent ) {
			$ancestor = get_comment( $parent );
			if ( isset( $seen[ $parent ] ) || ! $ancestor || (int) $ancestor->comment_post_ID !== $source || count( $seen ) > 500 ) {
				return new \WP_Error( 'thread', __( 'The existing thread is invalid. Repair its parents before moving it.', 'simple-comment-editing' ) );
			}
			$seen[ $parent ] = true;
			$parent          = (int) $ancestor->comment_parent;
		}

		$thread   = array( $comment_id => $this->placement( $root ) );
		$frontier = array( $comment_id );
		while ( $frontier ) {
			// Do not constrain the post: malformed cross-post descendants must fail.
			$children = get_comments(
				array(
					'parent__in'   => $frontier,
					'status'       => 'all',
					'number'       => 501,
					'orderby'      => 'comment_ID',
					'order'        => 'ASC',
					'hierarchical' => false,
				)
			);
			$frontier = array();
			foreach ( $children as $child ) {
				$id     = (int) $child->comment_ID;
				$access = $this->authorize_comment( $child );
				if ( is_wp_error( $access ) ) {
					return $access;
				}
				if ( isset( $thread[ $id ] ) || (int) $child->comment_post_ID !== $source || count( $thread ) >= 500 ) {
					return new \WP_Error( 'thread', __( 'The thread is invalid or exceeds the 500-comment safety limit. No comments were moved.', 'simple-comment-editing' ) );
				}
				$thread[ $id ] = $this->placement( $child );
				$frontier[]    = $id;
			}
		}
		ksort( $thread, SORT_NUMERIC );
		$before = array();
		$after  = $thread;
		foreach ( $thread as $id => $placement ) {
			if ( $id === $comment_id || $subtree ) {
				$before[ $id ]          = $placement;
				$after[ $id ]['post']   = $destination;
				$after[ $id ]['parent'] = $id === $comment_id ? 0 : $placement['parent'];
			} elseif ( $placement['parent'] === $comment_id ) {
				$before[ $id ]          = $placement;
				$after[ $id ]['parent'] = (int) $root->comment_parent;
			}
		}
		$source_post = $this->authorize_post( $source );
		if ( is_wp_error( $source_post ) ) {
			return $source_post;
		}
		return array(
			'root'              => $comment_id,
			'source'            => $source,
			'destination'       => $destination,
			'subtree'           => $subtree,
			'source_state'      => array( $source_post->post_status, (bool) $source_post->post_password ),
			'destination_state' => array( $post->post_status, (bool) $post->post_password ),
			'before'            => $before,
			'thread_before'     => $thread,
			'thread_after'      => $after,
		);
	}

	/**
	 * Bind confirmation to the exact placement snapshot and selected move mode.
	 *
	 * @param array $plan Preview data.
	 * @return string
	 */
	private function digest( $plan ) {
		return hash_hmac( 'sha256', wp_json_encode( $plan ), wp_salt( 'nonce' ) );
	}

	/**
	 * Render selection and a separate, non-mutating preview.
	 */
	public function render_page() {
		if ( ! current_user_can( 'moderate_comments' ) ) {
			wp_die( esc_html__( 'You cannot move comments.', 'simple-comment-editing' ), '', array( 'response' => 403 ) );
		}
		echo '<div class="wrap"><h1>' . esc_html__( 'Move comment', 'simple-comment-editing' ) . '</h1>';
		$operation = $this->input( 'operation', INPUT_GET );
		$pending   = get_option( '_sce_comment_move_pending', '' );
		if ( $operation ) {
			$this->render_operation( $operation );
			echo '</div>';
			return;
		}
		if ( $pending ) {
			echo '<div class="notice notice-error"><p><a href="' . esc_url( $this->page_url( array( 'operation' => $pending ) ) ) . '">' . esc_html__( 'An interrupted move needs recovery before another move can start.', 'simple-comment-editing' ) . '</a></p></div></div>';
			return;
		}
		$id = $this->positive_id( $this->input( 'comment_id', INPUT_GET ) );
		if ( ! $id ) {
			echo '<p>' . esc_html__( 'Use Move in the Comments list, or enter a comment ID.', 'simple-comment-editing' ) . '</p><form method="get"><input type="hidden" name="page" value="sce-move-comment"><p><label for="sce-comment-id">' . esc_html__( 'Comment ID', 'simple-comment-editing' ) . '</label> <input id="sce-comment-id" name="comment_id" type="number" min="1" required></p>';
			submit_button( __( 'Choose comment', 'simple-comment-editing' ) );
			echo '</form></div>';
			return;
		}
		$root = get_comment( $id );
		if ( ! $root ) {
			wp_die( esc_html__( 'You cannot move this comment.', 'simple-comment-editing' ), '', array( 'response' => 403 ) );
		}
		$access = $this->authorize_comment( $root );
		if ( is_wp_error( $access ) ) {
			wp_die( esc_html( $access->get_error_message() ), '', array( 'response' => 403 ) );
		}
		$destination = $this->positive_id( $this->input( 'destination' ) );
		$subtree     = 'subtree' === $this->input( 'mode' );
		$nonce       = $this->input( '_wpnonce' );
		if ( $nonce ) {
			if ( ! wp_verify_nonce( $nonce, 'sce_move_preview_' . $id ) ) {
				wp_die( esc_html__( 'The preview expired. Start again from the Comments list.', 'simple-comment-editing' ), '', array( 'response' => 403 ) );
			}
			$plan = $this->plan( $id, $destination, $subtree );
			if ( is_wp_error( $plan ) ) {
				echo '<div class="notice notice-error"><p>' . esc_html( $plan->get_error_message() ) . '</p></div>';
			} else {
				$this->render_preview( $plan );
				echo '</div>';
				return;
			}
		}
		echo '<p>' . esc_html( sprintf( /* translators: 1: comment ID, 2: source post title. */ __( 'Move comment #%1$d from "%2$s".', 'simple-comment-editing' ), $id, get_the_title( $root->comment_post_ID ) ) ) . '</p>';
		echo '<form method="post" action="' . esc_url( $this->page_url( array( 'comment_id' => $id ) ) ) . '">';
		wp_nonce_field( 'sce_move_preview_' . $id );
		echo '<p><label for="sce-destination">' . esc_html__( 'Destination post ID', 'simple-comment-editing' ) . '</label> <input id="sce-destination" name="destination" type="number" min="1" value="' . esc_attr( $destination ? $destination : '' ) . '" required aria-describedby="sce-destination-help"></p>';
		echo '<p id="sce-destination-help">' . esc_html__( 'Use the numeric ID of a post you can read and edit. Product reviews are not supported.', 'simple-comment-editing' ) . '</p>';
		echo '<fieldset><legend>' . esc_html__( 'Comments to move', 'simple-comment-editing' ) . '</legend><p><label><input type="radio" name="mode" value="single"' . checked( $subtree, false, false ) . '> ' . esc_html__( 'Only this comment; leave its replies on the original post', 'simple-comment-editing' ) . '</label></p><p><label><input type="radio" name="mode" value="subtree"' . checked( $subtree, true, false ) . '> ' . esc_html__( 'This comment and all its replies', 'simple-comment-editing' ) . '</label></p></fieldset>';
		submit_button( __( 'Preview move', 'simple-comment-editing' ) );
		echo '</form></div>';
	}

	/**
	 * Show the exact destination, exposure and threading effect before confirming.
	 *
	 * @param array $plan Validated preview.
	 */
	private function render_preview( $plan ) {
		echo '<h2>' . esc_html__( 'Preview — no comments have been moved', 'simple-comment-editing' ) . '</h2><ul>';
		foreach ( array( $plan['source'], $plan['destination'] ) as $index => $post_id ) {
			$post = $this->authorize_post( $post_id );
			if ( is_wp_error( $post ) ) {
				wp_die( esc_html( $post->get_error_message() ), '', array( 'response' => 403 ) );
			}
			echo '<li><strong>' . esc_html( 0 === $index ? __( 'Source:', 'simple-comment-editing' ) : __( 'Destination:', 'simple-comment-editing' ) ) . '</strong> ' . esc_html( sprintf( /* translators: 1: post title, 2: post ID, 3: visibility/status. */ __( '%1$s (post #%2$d, status: %3$s)', 'simple-comment-editing' ), get_the_title( $post ), $post_id, $post->post_status ) ) . '</li>';
		}
		echo '</ul><p>' . esc_html( sprintf( /* translators: 1: moved comment count, 2: descendant count left behind. */ __( '%1$d comment(s) will move; %2$d descendant(s) will remain on the original post.', 'simple-comment-editing' ), $plan['subtree'] ? count( $plan['thread_before'] ) : 1, $plan['subtree'] ? 0 : count( $plan['thread_before'] ) - 1 ) ) . '</p>';
		echo '<p>' . esc_html__( 'The moved comment becomes a top-level comment. When replies stay behind, direct children are reparented to its original parent (or become top-level); deeper replies keep their parents. When replies move, their internal relationships are preserved.', 'simple-comment-editing' ) . '</p>';
		echo '<p><strong>' . esc_html__( 'Moving changes where these comments are visible. Approval, content and metadata are preserved; approved comments may become public on the destination.', 'simple-comment-editing' ) . '</strong></p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		$this->hidden( 'action', 'sce_move_comment' );
		$this->hidden( 'comment_id', $plan['root'] );
		$this->hidden( 'destination', $plan['destination'] );
		$this->hidden( 'mode', $plan['subtree'] ? 'subtree' : 'single' );
		$this->hidden( 'snapshot', $this->digest( $plan ) );
		wp_nonce_field( 'sce_confirm_move_' . $this->digest( $plan ) );
		submit_button( __( 'Confirm move', 'simple-comment-editing' ) );
		echo '<p><a href="' . esc_url( $this->page_url( array( 'comment_id' => $plan['root'] ) ) ) . '">' . esc_html__( 'Cancel and change selection', 'simple-comment-editing' ) . '</a></p></form>';
	}

	/**
	 * Output an escaped hidden form field.
	 *
	 * @param string     $name Field name.
	 * @param string|int $value Field value.
	 */
	private function hidden( $name, $value ) {
		echo '<input type="hidden" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '">';
	}

	/**
	 * Confirm only the exact preview, and only through authenticated admin POST.
	 */
	public function handle_move() {
		$snapshot = $this->input( 'snapshot' );
		if ( ! current_user_can( 'moderate_comments' ) || ! wp_verify_nonce( $this->input( '_wpnonce' ), 'sce_confirm_move_' . $snapshot ) ) {
			wp_die( esc_html__( 'The move was not authorized. Preview it again.', 'simple-comment-editing' ), '', array( 'response' => 403 ) );
		}
		$plan = $this->plan( $this->positive_id( $this->input( 'comment_id' ) ), $this->positive_id( $this->input( 'destination' ) ), 'subtree' === $this->input( 'mode' ) );
		if ( is_wp_error( $plan ) ) {
			wp_die( esc_html( $plan->get_error_message() ), '', array( 'response' => 400 ) );
		}
		if ( ! hash_equals( $this->digest( $plan ), $snapshot ) ) {
			wp_die( esc_html__( 'The thread changed after preview. Preview it again; nothing was moved.', 'simple-comment-editing' ), '', array( 'response' => 409 ) );
		}
		$this->finish( $this->execute( $plan, wp_generate_uuid4(), false ) );
	}

	/**
	 * Restore using the server-side journal, never client-supplied parent mappings.
	 */
	public function handle_restore() {
		$id = $this->input( 'operation' );
		if ( ! current_user_can( 'moderate_comments' ) || ! wp_verify_nonce( $this->input( '_wpnonce' ), 'sce_restore_' . $id ) ) {
			wp_die( esc_html__( 'Recovery was not authorized.', 'simple-comment-editing' ), '', array( 'response' => 403 ) );
		}
		$record = $this->record( $id );
		if ( ! $record || is_wp_error( $this->authorize_post( $record['plan']['source'] ) ) || is_wp_error( $this->authorize_post( $record['plan']['destination'] ) ) ) {
			wp_die( esc_html__( 'You cannot access this recovery record.', 'simple-comment-editing' ), '', array( 'response' => 403 ) );
		}
		$this->finish( $this->execute( $record['plan'], $id, true ) );
	}

	/**
	 * Read a private, non-autoloaded journal by its random operation ID.
	 *
	 * @param string $id Operation UUID.
	 * @return array|false
	 */
	private function record( $id ) {
		return wp_is_uuid( $id ) ? get_option( '_sce_comment_move_' . $id, false ) : false;
	}

	/**
	 * Show accurate outcome and a nonce-protected recovery action.
	 *
	 * @param string $id Operation UUID.
	 */
	private function render_operation( $id ) {
		$record = $this->record( $id );
		if ( ! $record || is_wp_error( $this->authorize_post( $record['plan']['source'] ) ) || is_wp_error( $this->authorize_post( $record['plan']['destination'] ) ) ) {
			wp_die( esc_html__( 'You cannot access this recovery record.', 'simple-comment-editing' ), '', array( 'response' => 403 ) );
		}
		$messages = array(
			'prepared'          => __( 'The move was interrupted. Restore the original placement before trying another move.', 'simple-comment-editing' ),
			'moved'             => __( 'The comments were moved and both posts’ counts were refreshed.', 'simple-comment-editing' ),
			'restored'          => __( 'The original placement was restored and both posts’ counts were refreshed.', 'simple-comment-editing' ),
			'cancelled'         => __( 'The move could not proceed or the thread changed. No placement changes were committed. Preview the move again.', 'simple-comment-editing' ),
			'restore_refused'   => __( 'Recovery could not proceed. No recovery changes were committed. Check for new replies, changed parents or lost permissions before retrying; the journal is retained.', 'simple-comment-editing' ),
			'recovery_required' => __( 'The move or count refresh could not finish. The recovery record is retained. Restore the original placement before trying another move.', 'simple-comment-editing' ),
		);
		echo '<div class="notice notice-info"><p>' . esc_html( $messages[ $record['status'] ] ) . '</p></div><p>' . esc_html( sprintf( /* translators: %s: recovery record ID. */ __( 'Recovery record: %s. It stores original post and parent IDs, not comment content.', 'simple-comment-editing' ), $id ) ) . '</p>';
		if ( ! in_array( $record['status'], array( 'restored', 'cancelled' ), true ) ) {
			echo '<p>' . esc_html__( 'Recovery refuses to overwrite changed placements or new replies. Content, approval and metadata are never restored or overwritten.', 'simple-comment-editing' ) . '</p><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			$this->hidden( 'action', 'sce_restore_comment' );
			$this->hidden( 'operation', $id );
			wp_nonce_field( 'sce_restore_' . $id );
			submit_button( __( 'Restore original placement', 'simple-comment-editing' ), 'secondary' );
			echo '</form>';
		}
		echo '<p><a href="' . esc_url( admin_url( 'edit-comments.php' ) ) . '">' . esc_html__( 'Back to Comments', 'simple-comment-editing' ) . '</a></p>';
	}

	/**
	 * Verify recovery has not acquired new replies or unrelated placement edits.
	 *
	 * @param array $plan Original plan.
	 * @return bool
	 */
	private function can_restore( $plan ) {
		if ( is_wp_error( $this->authorize_post( $plan['source'] ) ) || is_wp_error( $this->authorize_post( $plan['destination'] ) ) ) {
			return false;
		}
		foreach ( $plan['thread_after'] as $id => $after ) {
			$comment = get_comment( $id );
			if ( ! $comment || is_wp_error( $this->authorize_comment( $comment ) ) ) {
				return false;
			}
			$current = $this->placement( $comment );
			if ( $current !== $after && $current !== $plan['thread_before'][ $id ] ) {
				return false;
			}
			// Validate the proposed graph, including ancestors changed since the move.
			$seen   = array( $id => true );
			$parent = $plan['thread_before'][ $id ]['parent'];
			while ( $parent ) {
				if ( isset( $seen[ $parent ] ) || count( $seen ) > 500 ) {
					return false;
				}
				$seen[ $parent ] = true;
				if ( isset( $plan['thread_before'][ $parent ] ) ) {
					$placement = $plan['thread_before'][ $parent ];
				} else {
					$ancestor = get_comment( $parent );
					if ( ! $ancestor ) {
						return false;
					}
					$placement = $this->placement( $ancestor );
				}
				if ( $placement['post'] !== $plan['source'] ) {
					return false;
				}
				$parent = $placement['parent'];
			}
		}
		$children = get_comments(
			array(
				'parent__in' => array_keys( $plan['thread_after'] ),
				'status'     => 'all',
				'number'     => 501,
				'fields'     => 'ids',
			)
		);
		return ! array_diff( array_map( 'intval', $children ), array_keys( $plan['thread_after'] ) );
	}

	/**
	 * Apply placement-only writes atomically, retaining a durable recovery journal.
	 *
	 * @param array  $plan Original validated plan.
	 * @param string $id Operation UUID.
	 * @param bool   $restore Restore rather than move.
	 * @return string|\WP_Error Operation UUID or an error before any mutation.
	 * @throws \RuntimeException Internally caught to roll back a failed operation.
	 */
	private function execute( $plan, $id, $restore ) {
		global $wpdb;
		// MyISAM cannot provide rollback. Refuse rather than offer false atomicity.
		foreach ( array( $wpdb->comments, $wpdb->posts, $wpdb->options ) as $table ) {
			$engine = $wpdb->get_var( $wpdb->prepare( 'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s', $table ) );
			if ( 'innodb' !== strtolower( (string) $engine ) ) {
				return new \WP_Error( 'engine', __( 'Moving requires InnoDB comment, post and option tables with transaction support. No comments were moved.', 'simple-comment-editing' ) );
			}
		}
		$lock = 'sce_move_' . md5( $wpdb->comments );
		if ( '1' !== (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 0)', $lock ) ) ) {
			return new \WP_Error( 'busy', __( 'Another move is in progress, or database locks are unavailable. No comments were moved.', 'simple-comment-editing' ) );
		}
		$transaction = false;
		$committing  = false;
		$record      = $restore ? $this->record( $id ) : array(
			'plan'    => $plan,
			'actor'   => get_current_user_id(),
			'created' => time(),
			'status'  => 'prepared',
		);
		try {
			if ( ! $record ) {
				return new \WP_Error( 'journal', __( 'The recovery record is unavailable. No comments were changed.', 'simple-comment-editing' ) );
			}
			$pending = get_option( '_sce_comment_move_pending', '' );
			if ( $pending && ( ! $restore || $pending !== $id ) ) {
				return new \WP_Error( 'pending', __( 'An earlier move needs recovery. Open Comments → Move comment.', 'simple-comment-editing' ) );
			}
			if ( ! $restore && ! add_option( '_sce_comment_move_' . $id, $record, '', false ) ) {
				return new \WP_Error( 'journal', __( 'The recovery record could not be saved. No comments were moved.', 'simple-comment-editing' ) );
			}
			update_option( '_sce_comment_move_pending', $id, false );
			// Set isolation for this transaction only, not the shared connection default.
			if ( get_option( '_sce_comment_move_pending' ) !== $id || false === $wpdb->query( 'SET TRANSACTION ISOLATION LEVEL SERIALIZABLE' ) || false === $wpdb->query( 'START TRANSACTION' ) ) {
				throw new \RuntimeException( 'journal_or_transaction' );
			}
			$transaction = true;
			$wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE ID IN (%d, %d) FOR UPDATE", $plan['source'], $plan['destination'] ) );
			if ( $wpdb->last_error ) {
				throw new \RuntimeException( 'post_lock' );
			}
			// Lock both posts' comment ranges, including potential new descendants.
			$locked = $wpdb->get_col( $wpdb->prepare( "SELECT comment_ID FROM {$wpdb->comments} WHERE comment_post_ID IN (%d, %d) FOR UPDATE", $plan['source'], $plan['destination'] ) );
			if ( $wpdb->last_error ) {
				throw new \RuntimeException( 'lock' );
			}
			clean_comment_cache( $locked );
			clean_post_cache( $plan['source'] );
			clean_post_cache( $plan['destination'] );
			if ( $restore ) {
				$valid = $this->can_restore( $plan );
			} else {
				$current = $this->plan( $plan['root'], $plan['destination'], $plan['subtree'] );
				$valid   = ! is_wp_error( $current ) && hash_equals( $this->digest( $plan ), $this->digest( $current ) );
			}
			if ( ! $valid ) {
				throw new \RuntimeException( 'changed' );
			}
			foreach ( $plan['before'] as $comment_id => $before ) {
				$comment = get_comment( $comment_id );
				if ( ! $comment ) {
					throw new \RuntimeException( 'missing_comment' );
				}
				$target = $restore ? $before : $plan['thread_after'][ $comment_id ];
				if ( $this->placement( $comment ) === $target ) {
					continue;
				}
				// Deliberately avoid re-sanitizing content or triggering approval changes.
				$result = $wpdb->update(
					$wpdb->comments,
					array(
						'comment_post_ID' => $target['post'],
						'comment_parent'  => $target['parent'],
					),
					array(
						'comment_ID'      => $comment_id,
						'comment_post_ID' => $comment->comment_post_ID,
						'comment_parent'  => $comment->comment_parent,
					),
					array( '%d', '%d' ),
					array( '%d', '%d', '%d' )
				);
				if ( 1 !== $result ) {
					throw new \RuntimeException( 'write' );
				}
			}
			if ( ! $this->refresh( $plan ) ) {
				throw new \RuntimeException( 'counts' );
			}
			// wpdb may reconnect after a lost connection, discarding its transaction.
			if ( ! $this->owns_lock( $lock ) ) {
				throw new \RuntimeException( 'connection' );
			}
			$committing = true;
			if ( false === $wpdb->query( 'COMMIT' ) ) {
				throw new \RuntimeException( 'commit' );
			}
			$transaction = false;
			// Other requests must never retain objects loaded before the commit.
			clean_comment_cache( array_keys( $plan['thread_before'] ) );
			clean_post_cache( $plan['source'] );
			clean_post_cache( $plan['destination'] );
			$record['status'] = $restore ? 'restored' : 'moved';
			if ( ! update_option( '_sce_comment_move_' . $id, $record, false ) && get_option( '_sce_comment_move_' . $id ) !== $record ) {
				throw new \RuntimeException( 'journal' );
			}
			delete_option( '_sce_comment_move_pending' );
		} catch ( \Throwable $error ) {
			$known_rollback = ! $committing && $this->owns_lock( $lock );
			if ( $transaction ) {
				$known_rollback = false !== $wpdb->query( 'ROLLBACK' ) && $known_rollback;
			}
			$record['status'] = $known_rollback ? ( $restore ? 'restore_refused' : 'cancelled' ) : 'recovery_required';
			update_option( '_sce_comment_move_' . $id, $record, false );
			// A rejected stale preview must not leave a global recovery deadlock.
			if ( $known_rollback && ( ! $restore || ! $pending ) ) {
				delete_option( '_sce_comment_move_pending' );
			}
			clean_comment_cache( array_keys( $plan['thread_before'] ) );
			clean_post_cache( $plan['source'] );
			clean_post_cache( $plan['destination'] );
		} finally {
			$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) );
		}
		return $id;
	}

	/**
	 * Detect a reconnect before claiming a transaction or rollback succeeded.
	 *
	 * @param string $lock Advisory lock name.
	 * @return bool
	 */
	private function owns_lock( $lock ) {
		global $wpdb;
		return '1' === (string) $wpdb->get_var( $wpdb->prepare( 'SELECT IS_USED_LOCK(%s) = CONNECTION_ID()', $lock ) );
	}

	/**
	 * Invalidate placements and recalculate approved counts for both posts.
	 *
	 * @param array $plan Movement plan.
	 * @return bool
	 */
	private function refresh( $plan ) {
		global $wpdb;
		clean_comment_cache( array_keys( $plan['thread_before'] ) );
		$success = true;
		foreach ( array( $plan['source'], $plan['destination'] ) as $post_id ) {
			// Core returns true even when its UPDATE fails. Verify the intended count.
			$expected = null;
			$observe  = static function ( $updated_post_id, $new_count ) use ( $post_id, &$expected ) {
				if ( (int) $updated_post_id === $post_id ) {
					$expected = (int) $new_count;
				}
			};
			add_action( 'wp_update_comment_count', $observe, PHP_INT_MAX, 2 );
			try {
				$updated = wp_update_comment_count_now( $post_id );
			} finally {
				remove_action( 'wp_update_comment_count', $observe, PHP_INT_MAX );
			}
			$stored  = $wpdb->get_var( $wpdb->prepare( "SELECT comment_count FROM {$wpdb->posts} WHERE ID = %d", $post_id ) );
			$success = $success && $updated && null !== $stored && null !== $expected && (int) $stored === $expected && ! $wpdb->last_error;
			clean_post_cache( $post_id );
		}
		return $success;
	}

	/**
	 * Redirect after POST, so refresh cannot repeat a successful mutation.
	 *
	 * @param string|\WP_Error $result Operation ID or error.
	 */
	private function finish( $result ) {
		if ( is_wp_error( $result ) ) {
			wp_die(
				esc_html( $result->get_error_message() ),
				'',
				array(
					'response'  => 409,
					'back_link' => true,
				)
			);
		}
		wp_safe_redirect( $this->page_url( array( 'operation' => $result ) ) );
		exit;
	}
}
