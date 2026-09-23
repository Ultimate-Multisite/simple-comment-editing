<?php
/**
 * Register the Settings tab and any sub-tabs.
 *
 * @package SCE
 */

namespace DLXPlugins\CommentEditLite\Admin\Tabs;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'No direct access.' );
}

use DLXPlugins\CommentEditLite\Functions;
use DLXPlugins\CommentEditLite\Options;

/**
 * Output the settings tab and content.
 */
class Settings extends Tabs {

	/**
	 * Tab to run actions against.
	 *
	 * @var string Settings tab.
	 */
	private $tab = 'settings';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_filter( 'sce_admin_tabs', array( $this, 'add_tab' ), 1, 1 );
		add_filter( 'sce_admin_sub_tabs', array( $this, 'add_sub_tab' ), 1, 3 );
		add_action( 'sce_output_' . sanitize_key( $this->tab ), array( $this, 'output_settings' ), 1, 2 );
	}

	/**
	 * Add the settings tab and callback actions.
	 *
	 * @param array $tabs Array of tabs.
	 *
	 * @return array of tabs.
	 */
	public function add_tab( $tabs ) {
		$tabs[] = array(
			'get'    => sanitize_key( $this->tab ),
			'action' => 'sce_output_' . sanitize_key( $this->tab ),
			'url'    => esc_url_raw( Functions::get_settings_url( $this->tab ) ),
			'label'  => _x( 'Settings', 'Tab label as settings', 'simple-comment-editing' ),
			'icon'   => 'home-heart',
		);
		return $tabs;
	}

	/**
	 * Add the settings main tab and callback actions.
	 *
	 * @param array  $tabs        Array of tabs.
	 * @param string $current_tab The current tab selected.
	 * @param string $sub_tab     The current sub-tab selected.
	 *
	 * @return array of tabs.
	 */
	public function add_sub_tab( $tabs, $current_tab, $sub_tab ) {
		if ( ( ! empty( $current_tab ) || ! empty( $sub_tab ) ) && $this->tab !== $current_tab ) {
			return $tabs;
		}
		return $tabs;
	}

	/**
	 * Begin settings routing for the various outputs.
	 *
	 * @param string $tab     Current tab.
	 * @param string $sub_tab Current sub tab.
	 */
	public function output_settings( $tab, $sub_tab = '' ) {
		$tab     = sanitize_key( $tab );
		$sub_tab = sanitize_key( $sub_tab );
		if ( $this->tab === $tab ) {
			if ( empty( $sub_tab ) || $this->tab === $sub_tab ) {
				if ( isset( $_POST['submit'] ) && isset( $_POST['options'] ) ) {
					check_admin_referer( 'save_sce_options' );
					$posted_options = map_deep( wp_unslash( $_POST['options'] ), 'sanitize_text_field' );
					if ( is_array( $posted_options ) ) {
						Options::update_options( $posted_options );
					}
					printf( '<div class="updated sce-updated"><p><strong>%s</strong></p></div>', esc_html__( 'Your options have been saved.', 'simple-comment-editing' ) );
				}
				// Get options and defaults.
				$options = Options::get_options();
				?>
				<div class="sce-admin-panel-area">
					<div class="sce-panel-row">
						<form action="" method="POST">
							<?php wp_nonce_field( 'save_sce_options' ); ?>
							<h1><?php esc_html_e( 'Welcome to Simple Comment Editing!', 'simple-comment-editing' ); ?></h1>
							<p><?php esc_html_e( 'Simple Comment Editing allows you to set a time limit for comment editing. After the time limit has passed, the comment will no longer be editable by the user.', 'simple-comment-editing' ); ?></p>

							<h2><?php esc_html_e( 'Timer', 'simple-comment-editing' ); ?></h2>
							<table class="form-table">
								<tbody>
									<tr>
										<th scope="row"><label for="sce-timer"><?php esc_html_e( 'Edit Timer in Minutes', 'simple-comment-editing' ); ?></label></th>
										<td>
											<input id="sce-timer" class="regular-text" type="number" value="<?php echo esc_attr( absint( $options['timer'] ) ); ?>" name="options[timer]" />
											<p class="description"><?php esc_html_e( 'Enter the time that users have to edit their comments.', 'simple-comment-editing' ); ?></p>
										</td>
									</tr>
									<tr>
										<th scope="row"><label for="sce-timer-appearance"><?php esc_html_e( 'Timer Appearance', 'simple-comment-editing' ); ?></label></th>
										<td>
											<select id="sce-timer-appearance" name="options[timer_appearance]">
												<option value="words" <?php selected( 'words', $options['timer_appearance'] ); ?>><?php esc_html_e( 'Words', 'simple-comment-editing' ); ?></option>
												<option value="compact" <?php selected( 'compact', $options['timer_appearance'] ); ?>><?php esc_html_e( 'Compact', 'simple-comment-editing' ); ?></option>
											</select>
											<p class="description"><?php esc_html_e( 'Select how the timer should appear to the user.', 'simple-comment-editing' ); ?></p>
										</td>
									</tr>
									<tr>
										<th scope="row"><?php esc_html_e( 'Show Timer', 'simple-comment-editing' ); ?></th>
										<td>
											<input type="hidden" value="false" name="options[show_timer]" />
											<p>
												<input id="sce-show-timer" type="checkbox" value="true" name="options[show_timer]" <?php checked( true, $options['show_timer'] ); ?> />
												<label for="sce-show-timer"><?php esc_html_e( 'Show the timer', 'simple-comment-editing' ); ?></label>
											</p>
											<p class="description"><?php esc_html_e( 'Enabling this will show the timer to the user when editing.', 'simple-comment-editing' ); ?></p>
										</td>
									</tr>
								</tbody>
							</table>

							<h2><?php esc_html_e( 'Who Can Edit', 'simple-comment-editing' ); ?></h2>
							<table class="form-table">
								<tbody>
									<tr>
										<th scope="row"><?php esc_html_e( 'Require Login', 'simple-comment-editing' ); ?></th>
										<td>
											<input type="hidden" value="false" name="options[require_login_for_editing]" />
											<p>
												<input id="sce-require-login" type="checkbox" value="true" name="options[require_login_for_editing]" <?php checked( true, $options['require_login_for_editing'] ); ?> />
												<label for="sce-require-login"><?php esc_html_e( 'Require login for editing', 'simple-comment-editing' ); ?></label>
											</p>
											<p class="description"><?php esc_html_e( 'Enabling this will require users to be logged in to edit their comments.', 'simple-comment-editing' ); ?></p>
										</td>
									</tr>
									<tr>
										<th scope="row"><?php esc_html_e( 'Unlimited Editing', 'simple-comment-editing' ); ?></th>
										<td>
											<input type="hidden" value="false" name="options[allow_unlimited_editing]" />
											<p>
												<input id="sce-unlimited-editing" type="checkbox" value="true" name="options[allow_unlimited_editing]" <?php checked( true, $options['allow_unlimited_editing'] ); ?> />
												<label for="sce-unlimited-editing"><?php esc_html_e( 'Unlimited timer for logged-in users', 'simple-comment-editing' ); ?></label>
											</p>
											<p class="description"><?php esc_html_e( 'Enabling this will allow unlimited editing for logged-in users who are the comment author.', 'simple-comment-editing' ); ?></p>
										</td>
									</tr>
								</tbody>
							</table>

							<h2><?php esc_html_e( 'Deletion', 'simple-comment-editing' ); ?></h2>
							<table class="form-table">
								<tbody>
									<tr>
										<th scope="row"><?php esc_html_e( 'Allow Deletion', 'simple-comment-editing' ); ?></th>
										<td>
											<input type="hidden" value="false" name="options[allow_delete]" />
											<p>
												<input id="sce-allow-delete" type="checkbox" value="true" name="options[allow_delete]" <?php checked( true, $options['allow_delete'] ); ?> />
												<label for="sce-allow-delete"><?php esc_html_e( 'Allow comment deletion for users', 'simple-comment-editing' ); ?></label>
											</p>
											<p class="description"><?php esc_html_e( 'Enabling this option will allow users to delete their own comments.', 'simple-comment-editing' ); ?></p>
										</td>
									</tr>
									<tr>
										<th scope="row"><?php esc_html_e( 'Deletion Confirmation', 'simple-comment-editing' ); ?></th>
										<td>
											<input type="hidden" value="false" name="options[allow_delete_confirmation]" />
											<p>
												<input id="sce-allow-delete-confirmation" type="checkbox" value="true" name="options[allow_delete_confirmation]" <?php checked( true, $options['allow_delete_confirmation'] ); ?> />
												<label for="sce-allow-delete-confirmation"><?php esc_html_e( 'Enable comment deletion confirmation', 'simple-comment-editing' ); ?></label>
											</p>
											<p class="description"><?php esc_html_e( 'Enabling this will show a confirmation before the user is allowed to delete their own comments.', 'simple-comment-editing' ); ?></p>
										</td>
									</tr>
									<tr>
										<th scope="row"><?php esc_html_e( 'User Deleted Comments', 'simple-comment-editing' ); ?></th>
										<td>
											<fieldset>
												<legend class="screen-reader-text"><?php esc_html_e( 'How should user-deleted comments be treated?', 'simple-comment-editing' ); ?></legend>
												<p>
													<label>
														<input type="radio" name="options[delete_behavior_users]" value="trash" <?php checked( 'trash', $options['delete_behavior_users'] ); ?> />
														<?php esc_html_e( 'Send to Trash', 'simple-comment-editing' ); ?>
													</label>
													<br />
													<label>
														<input type="radio" name="options[delete_behavior_users]" value="delete" <?php checked( 'delete', $options['delete_behavior_users'] ); ?> />
														<?php esc_html_e( 'Delete Permanently', 'simple-comment-editing' ); ?>
													</label>
												</p>
												<p class="description"><?php esc_html_e( 'Choose whether to send user-deleted comments to the trash or delete them permanently.', 'simple-comment-editing' ); ?></p>
											</fieldset>
										</td>
									</tr>
									<tr>
										<th scope="row"><?php esc_html_e( 'Moderator Deleted Comments', 'simple-comment-editing' ); ?></th>
										<td>
											<fieldset>
												<legend class="screen-reader-text"><?php esc_html_e( 'How should moderator-deleted comments be treated?', 'simple-comment-editing' ); ?></legend>
												<p>
													<label>
														<input type="radio" name="options[delete_behavior_moderators]" value="trash" <?php checked( 'trash', $options['delete_behavior_moderators'] ); ?> />
														<?php esc_html_e( 'Send to Trash', 'simple-comment-editing' ); ?>
													</label>
													<br />
													<label>
														<input type="radio" name="options[delete_behavior_moderators]" value="delete" <?php checked( 'delete', $options['delete_behavior_moderators'] ); ?> />
														<?php esc_html_e( 'Delete Permanently', 'simple-comment-editing' ); ?>
													</label>
												</p>
												<p class="description"><?php esc_html_e( 'Choose whether to send moderator-deleted comments to the trash or delete them permanently. This affects comments deleted in the admin as well.', 'simple-comment-editing' ); ?></p>
											</fieldset>
										</td>
									</tr>
									<tr>
										<th scope="row"><?php esc_html_e( 'Spammed Comments', 'simple-comment-editing' ); ?></th>
										<td>
											<fieldset>
												<legend class="screen-reader-text"><?php esc_html_e( 'How should spam comments be treated?', 'simple-comment-editing' ); ?></legend>
												<p>
													<label>
														<input type="radio" name="options[spam_behavior_moderators]" value="spam" <?php checked( 'spam', $options['spam_behavior_moderators'] ); ?> />
														<?php esc_html_e( 'Send to Spam', 'simple-comment-editing' ); ?>
													</label>
													<br />
													<label>
														<input type="radio" name="options[spam_behavior_moderators]" value="delete" <?php checked( 'delete', $options['spam_behavior_moderators'] ); ?> />
														<?php esc_html_e( 'Spam and Delete', 'simple-comment-editing' ); ?>
													</label>
												</p>
												<p class="description"><?php esc_html_e( 'When marking a comment as spam, choose whether to send the comment directly to spam, or to mark as spam then delete permanently. This affects comments marked as spam in the admin as well.', 'simple-comment-editing' ); ?></p>
											</fieldset>
										</td>
									</tr>
								</tbody>
							</table>

							<h2><?php esc_html_e( 'Appearance', 'simple-comment-editing' ); ?></h2>
							<table class="form-table">
								<tbody>
									<tr>
										<th scope="row"><label for="sce-button-theme"><?php esc_html_e( 'Button Theme', 'simple-comment-editing' ); ?></label></th>
										<td>
											<select id="sce-button-theme" name="options[button_theme]">
												<option value="default" <?php selected( 'default', $options['button_theme'] ); ?>><?php esc_html_e( 'None', 'simple-comment-editing' ); ?></option>
												<option value="regular" <?php selected( 'regular', $options['button_theme'] ); ?>><?php esc_html_e( 'Regular', 'simple-comment-editing' ); ?></option>
												<option value="dark" <?php selected( 'dark', $options['button_theme'] ); ?>><?php esc_html_e( 'Dark', 'simple-comment-editing' ); ?></option>
												<option value="light" <?php selected( 'light', $options['button_theme'] ); ?>><?php esc_html_e( 'Light', 'simple-comment-editing' ); ?></option>
											</select>
											<input type="hidden" value="false" name="options[show_icons]" />
											<p>
												<input id="sce-allow-icons" type="checkbox" value="true" name="options[show_icons]" <?php checked( true, $options['show_icons'] ); ?> />
												<label for="sce-allow-icons"><?php esc_html_e( 'Allow icons for the buttons. Recommended if you have selected a button theme.', 'simple-comment-editing' ); ?></label>
											</p>
										</td>
									</tr>
								</tbody>
							</table>

							<h2><?php esc_html_e( 'Labels', 'simple-comment-editing' ); ?></h2>
							<table class="form-table">
								<tbody>
									<tr>
										<th scope="row"><label for="sce-click-to-edit-text"><?php esc_html_e( 'Click to Edit Text', 'simple-comment-editing' ); ?></label></th>
										<td>
											<input id="sce-click-to-edit-text" class="regular-text" type="text" value="<?php echo esc_attr( $options['click_to_edit_text'] ); ?>" name="options[click_to_edit_text]" />
										</td>
									</tr>
									<tr>
										<th scope="row"><label for="sce-save-text"><?php esc_html_e( 'Save Button Text', 'simple-comment-editing' ); ?></label></th>
										<td>
											<input id="sce-save-text" class="regular-text" type="text" value="<?php echo esc_attr( $options['save_text'] ); ?>" name="options[save_text]" />
										</td>
									</tr>
									<tr>
										<th scope="row"><label for="sce-cancel-text"><?php esc_html_e( 'Cancel Button Text', 'simple-comment-editing' ); ?></label></th>
										<td>
											<input id="sce-cancel-text" class="regular-text" type="text" value="<?php echo esc_attr( $options['cancel_text'] ); ?>" name="options[cancel_text]" />
										</td>
									</tr>
									<tr>
										<th scope="row"><label for="sce-delete-text"><?php esc_html_e( 'Delete Button Text', 'simple-comment-editing' ); ?></label></th>
										<td>
											<input id="sce-delete-text" class="regular-text" type="text" value="<?php echo esc_attr( $options['delete_text'] ); ?>" name="options[delete_text]" />
										</td>
									</tr>
								</tbody>
							</table>

							<h2><?php esc_html_e( 'Messages', 'simple-comment-editing' ); ?></h2>
							<table class="form-table">
								<tbody>
									<tr>
										<th scope="row"><label for="sce-confirm-delete"><?php esc_html_e( 'Comment Deletion Confirmation Text', 'simple-comment-editing' ); ?></label></th>
										<td>
											<input id="sce-confirm-delete" class="regular-text" type="text" value="<?php echo esc_attr( $options['confirm_delete'] ); ?>" name="options[confirm_delete]" />
										</td>
									</tr>
									<tr>
										<th scope="row"><label for="sce-comment-deleted"><?php esc_html_e( 'Comment Deleted Text', 'simple-comment-editing' ); ?></label></th>
										<td>
											<input id="sce-comment-deleted" class="regular-text" type="text" value="<?php echo esc_attr( $options['comment_deleted'] ); ?>" name="options[comment_deleted]" />
										</td>
									</tr>
									<tr>
										<th scope="row"><label for="sce-comment-deleted-error"><?php esc_html_e( 'Comment Deleted Error Text', 'simple-comment-editing' ); ?></label></th>
										<td>
											<input id="sce-comment-deleted-error" class="regular-text" type="text" value="<?php echo esc_attr( $options['comment_deleted_error'] ); ?>" name="options[comment_deleted_error]" />
										</td>
									</tr>
									<tr>
										<th scope="row"><label for="sce-comment-empty-error"><?php esc_html_e( 'Comment Empty Error Text', 'simple-comment-editing' ); ?></label></th>
										<td>
											<input id="sce-comment-empty-error" class="regular-text" type="text" value="<?php echo esc_attr( $options['comment_empty_error'] ); ?>" name="options[comment_empty_error]" />
										</td>
									</tr>
								</tbody>
							</table>

							<?php submit_button( __( 'Save Options', 'simple-comment-editing' ), 'sce-button sce-button-info', 'submit', true ); ?>
						</form>
					</div>
				</div>
				<?php
			}
		}
	}
}
