/* eslint-disable no-undef */
/**
 * Covers comment editing on the frontend.
 */
import sendCommand from '../SendCommand';

// get i18n variables.
const { __, _n } = wp.i18n;

// get hooks.
const { addFilter, applyFilters, doAction } = wp.hooks;

// Get the timer placeholder.
const timers = [];

// Get the textareas placeholder.
const textareas = [];

// Track active editors so repeated opens cannot create duplicate instances.
const editorInstances = [];

// Onload, get dom entries.
window.addEventListener( 'load', () => {
	const comment_edit_buttons = document.querySelectorAll( '.sce-edit-button' );
	if ( ! comment_edit_buttons ) {
		return;
	}

	// For Ajaxify Comment integration.
	document.addEventListener( 'wpacAfterUpdateComments', function( e ) {
		const { detail } = e;
		const { commentUrl } = detail;
		window.SCE_comments_updated( commentUrl );
	} );

	addFilter( 'sce.comment.save.data', 'comment-edit-lite', ( ajaxSaveParams ) => {
		// Get current comment.
		const comment = document.querySelector( `#sce-edit-comment${ ajaxSaveParams.comment_id }` );

		if ( null === comment ) {
			return ajaxSaveParams;
		}

		// Read the form value, including when jQuery's star enhancement is unavailable.
		const rating = comment.querySelector( '.comment-form-rating select[name="rating"]' );
		const nonceElement = comment.querySelector( '#woo_edit_comment_nonce_' + ajaxSaveParams.comment_id );
		if ( null !== rating && /^[1-5]$/.test( rating.value ) && null !== nonceElement ) {
			ajaxSaveParams.wooEditCommentNonce = nonceElement.value;
			ajaxSaveParams.rating = rating.value;
		}
		return ajaxSaveParams;
	} );

	/**
	 * Gets the time left for the comment.
	 *
	 * @param {number} commentId The Comment ID.
	 * @param {number} postId    The Post ID.
	 * @param {string} nonce     The ajax nonce.
	 * @param {string} ajaxUrl   The ajax url.
	 */
	const getTimeLeft = async( commentId, postId, nonce, ajaxUrl ) => {
		const response = await sendCommand( 'sce_get_time_left', { comment_id: commentId, post_id: postId, _ajax_nonce: nonce }, ajaxUrl );
		return response;
	};

	/**
	 * Save a comment via Ajax.
	 *
	 * @param {string} action     The Ajax action.
	 * @param {Object} ajaxParams The Ajax params including the nonce.
	 * @param {string} ajaxUrl    The Ajax URL.
	 *
	 * @return {Promise} The Ajax promise.
	 */
	const saveComment = async( action, ajaxParams, ajaxUrl ) => {
		const response = await sendCommand( action, ajaxParams, ajaxUrl );
		return response;
	};

	/**
	 * Show the edit button.
	 *
	 * @param {Element} button The element to show.
	 */
	const showEditButton = ( button ) => {
		button.classList.remove( 'sce-hide' );
		button.classList.add( 'sce-show' );
	};

	/**
	 * Gets the AJAX URL from an inline action.
	 *
	 * @param {Element} action The inline action.
	 * @return {string} The AJAX URL.
	 */
	const getAjaxUrl = ( action ) => action.dataset.sceAjaxUrl || action.getAttribute( 'href' );

	/**
	 * Gets a useful error message from a WordPress AJAX response.
	 *
	 * @param {Object} response The Axios response, when available.
	 * @param {string} fallback The fallback error message.
	 * @return {string} The error message.
	 */
	const getErrorMessage = ( response, fallback ) => {
		const responseData = response && response.data ? response.data : {};
		const data = responseData.data || {};
		return data.error || data.message || responseData.error || fallback;
	};

	/**
	 * Shows an editor status message without rendering server content as HTML.
	 *
	 * @param {number}  commentId The comment ID.
	 * @param {string}  message   The message to show.
	 * @param {boolean} isError   Whether the message is an error.
	 */
	const showStatus = ( commentId, message, isError = false ) => {
		const status = document.querySelector( `#sce-edit-comment-status${ commentId }` );
		if ( null === status ) {
			return;
		}

		status.className = '';
		status.classList.add( 'sce-status' );
		if ( isError ) {
			status.classList.add( 'error' );
		}
		status.setAttribute( 'aria-live', isError ? 'assertive' : 'polite' );
		status.textContent = message;
		status.style.display = 'block';
	};

	/**
	 * Dispatch a documented editor lifecycle event and WordPress hook.
	 *
	 * @param {string} eventName The document event name.
	 * @param {string} hookName  The WordPress action name.
	 * @param {Object} detail    The editor lifecycle data.
	 */
	const dispatchEditorLifecycle = ( eventName, hookName, detail ) => {
		document.dispatchEvent( new CustomEvent( eventName, { detail } ) );
		doAction( hookName, detail );
	};

	/**
	 * Initialize an optional native Quicktags editor exactly once per comment.
	 *
	 * @param {Element} textarea  The comment textarea.
	 * @param {number}  commentId The comment ID.
	 * @param {number}  postId    The post ID.
	 */
	const initializeCommentEditor = ( textarea, commentId, postId ) => {
		if ( editorInstances[ commentId ] ) {
			return;
		}

		const editor = {
			textareaId: textarea.id,
			quicktags: false,
		};
		editorInstances[ commentId ] = editor;

		if ( ! simple_comment_editing.enable_quicktags || ! wp.editor || 'function' !== typeof wp.editor.initialize ) {
			return;
		}

		wp.editor.initialize( textarea.id, {
			mediaButtons: false,
			quicktags: true,
			tinymce: false,
		} );
		editor.quicktags = true;
		dispatchEditorLifecycle( 'sceCommentQuicktagsInit', 'sce.comment.editor.quicktags.init', {
			textarea,
			commentId,
			postId,
		} );
	};

	/**
	 * Remove an editor after consumers have released their own editor instance.
	 *
	 * @param {Element} textarea  The comment textarea.
	 * @param {number}  commentId The comment ID.
	 * @param {number}  postId    The post ID.
	 */
	const destroyCommentEditor = ( textarea, commentId, postId ) => {
		const editor = editorInstances[ commentId ];
		if ( ! editor ) {
			return;
		}

		dispatchEditorLifecycle( 'sceCommentEditorDestroy', 'sce.comment.editor.destroy', {
			textarea,
			commentId,
			postId,
		} );
		if ( editor.quicktags && wp.editor && 'function' === typeof wp.editor.remove ) {
			wp.editor.remove( editor.textareaId );
		}
		delete editorInstances[ commentId ];
	};

	/**
	 * Disables or enables each editor button and inline action.
	 *
	 * @param {Element} wrapper  The comment editor wrapper.
	 * @param {boolean} disabled Whether to disable the controls.
	 */
	const setEditorControlsDisabled = ( wrapper, disabled ) => {
		wrapper.querySelectorAll( 'button' ).forEach( ( editorButton ) => {
			editorButton.disabled = disabled;
		} );
		wrapper.querySelectorAll( '.sce-edit-button-main, .sce-delete-button-main' ).forEach( ( action ) => {
			action.setAttribute( 'aria-disabled', disabled ? 'true' : 'false' );
		} );
	};

	/**
	 * Restores an editor after an AJAX response.
	 *
	 * @param {Element} wrapper      The comment editor wrapper.
	 * @param {Element} editButton   The edit action.
	 * @param {boolean} showTextArea Whether to show the editor.
	 */
	const restoreEditor = ( wrapper, editButton, showTextArea ) => {
		const editor = wrapper.querySelector( '.sce-textarea' );
		const loading = wrapper.querySelector( '.sce-loading' );
		const actionWrapper = wrapper.querySelector( '.sce-edit-button' );

		setEditorControlsDisabled( wrapper, false );
		wrapper.dataset.sceSubmitting = 'false';
		if ( null !== loading ) {
			loading.style.display = 'none';
		}
		if ( null !== editor ) {
			editor.style.display = showTextArea ? 'block' : 'none';
		}
		if ( null !== actionWrapper ) {
			actionWrapper.style.display = showTextArea ? 'none' : 'block';
		}
		if ( null !== editButton ) {
			editButton.setAttribute( 'aria-expanded', showTextArea ? 'true' : 'false' );
		}
	};

	/**
	 * Hide the edit button.
	 *
	 * @param {Element} button The element to show.
	 */
	const hideEditButton = ( button ) => {
		button.classList.remove( 'sce-show' );
		button.classList.add( 'sce-hide' );
	};

	/**
	 * Gets a timer's text label.
	 *
	 * @param {number} minutes Number of minutes.
	 * @param {number} seconds Number of seconds.
	 * @return {string} Timer label.
	 */
	const getTimerText = ( minutes, seconds ) => {
		if ( seconds < 0 ) {
			minutes -= 1; seconds = 59;
		}
		//Create timer text
		let text = '';
		let days = 0;
		let hours = 0;
		let currentMinutes = 0;
		if ( minutes >= 1 ) {
			// Get mniutes in seconds
			let minute_to_seconds = Math.abs( minutes * 60 );
			days = Math.floor( minute_to_seconds / 86400 );

			// Get Days
			if ( days >= 1 ) {
				// Get days
				text += days + ' ' + _n( 'day', 'days', days, 'simple-comment-editing' );
				text += ' ' + __( 'and', 'simple-comment-editing' ) + ' ';
				minute_to_seconds -= days * 86400;
			}

			// Get hours
			hours = Math.floor( minute_to_seconds / 3600 ) % 24;
			if ( hours >= 0 ) {
				if ( hours > 0 ) {
					text += hours + ' ' + _n( 'hour', 'hours', hours, 'simple-comment-editing' );
					text += ' ' + __( 'and', 'simple-comment-editing' ) + ' ';
				}
				minute_to_seconds -= hours * 3600;
			}

			// Get minutes
			currentMinutes = Math.floor( minute_to_seconds / 60 ) % 60;
			minute_to_seconds -= currentMinutes;
			if ( minutes > 0 ) {
				text += currentMinutes + ' ' + _n( 'minute', 'minutes', currentMinutes, 'simple-comment-editing' );
			}

			// Get seconds
			if ( seconds > 0 ) {
				text += ' ' + __( 'and', 'simple-comment-editing' ) + ' ';
				text += seconds + ' ' + _n( 'second', 'seconds', seconds, 'simple-comment-editing' );
			}
		} else {
			text += seconds + ' ' + _n( 'second', 'seconds', seconds, 'simple-comment-editing' );
		}
		/**
		 * JSFilter: sce.comment.timer.text
		 *
		 * Filter triggered before a timer is returned
		 *
		 * @since 1.4.0
		 *
		 * @param  string comment text
		 * @param  string minute text,
		 * @param  string second text,
		 * @param  int    number of minutes left
		 * @param  int    seconds left
		 */
		text = applyFilters( 'sce.comment.timer.text', text, _n( 'day', 'days', days, 'simple-comment-editing' ), _n( 'hour', 'hours', hours, 'simple-comment-editing' ), _n( 'minute', 'minutes', currentMinutes, 'simple-comment-editing' ), _n( 'second', 'seconds', seconds, 'simple-comment-editing' ), days, hours, currentMinutes, seconds );
		return text;
	};

	/**
	 * Deletes a comment.
	 *
	 * @param {Element} element   The element to show.
	 * @param {number}  commentId The Comment ID.
	 * @param {number}  postId    The Post ID.
	 * @param {string}  nonce     The ajax nonce.
	 * @param {string}  ajaxUrl   The ajax url.
	 */
	const deleteComment = async( element, commentId, postId, nonce, ajaxUrl ) => {
		const wrapper = element.closest( '.sce-edit-comment' );

		if ( 'true' === wrapper.dataset.sceSubmitting ) {
			return;
		}

		const editButton = wrapper.querySelector( '.sce-edit-button-main' );
		const editor = wrapper.querySelector( '.sce-textarea' );
		const wasEditing = null !== editor && 'none' !== editor.style.display;
		const loading = wrapper.querySelector( '.sce-loading' );

		wrapper.dataset.sceSubmitting = 'true';
		setEditorControlsDisabled( wrapper, true );
		if ( null !== loading ) {
			loading.style.display = 'block';
		}

		try {
			const response = await sendCommand( 'sce_delete_comment', { comment_id: commentId, post_id: postId, nonce }, ajaxUrl );
			if ( ! response.data || true !== response.data.success ) {
				throw new Error( getErrorMessage( response, simple_comment_editing.comment_deleted_error ) );
			}

			showStatus( commentId, simple_comment_editing.comment_deleted );
			destroyCommentEditor( wrapper.querySelector( '.sce-comment-text' ), commentId, postId );
			setTimeout( function() {
				const comment = document.querySelector( '#comment-' + commentId );
				if ( null !== comment ) {
					comment.remove();
				}
				wrapper.remove();
			}, 3000 ); // Attempt to remove the comment from the theme interface.
		} catch ( error ) {
			restoreEditor( wrapper, editButton, wasEditing );
			showStatus( commentId, error.message || simple_comment_editing.comment_deleted_error, true );
		}
	};

	// Function to run to initialize the edit comment buttons.
	window.initEditCommentButton = ( button ) => {
		// Get first link
		const firstAction = button.querySelector( '.sce-edit-button-main, .sce-delete-button-main' );
		if ( null === firstAction ) {
			return;
		}
		const ajaxUrl = getAjaxUrl( firstAction );

		// Get comment ID from URL query param.
		const urlParams = new URLSearchParams( ajaxUrl );
		const commentId = urlParams.get( 'cid' );
		const postId = urlParams.get( 'pid' );
		const nonce = urlParams.get( 'nonce' );

		// Get the time left for the comment.
		getTimeLeft( commentId, postId, simple_comment_editing.nonce, ajaxUrl ).then( ( response ) => {
			// Get the timer element.
			if ( ! response.data || true !== response.data.success || ! response.data.data ) {
				throw new Error( getErrorMessage( response, simple_comment_editing.comment_deleted_error ) );
			}
			const { data } = response.data;
			let { minutes, seconds } = data;
			const { can_edit } = data;

			// Show button if time left.
			if ( 'unlimited' === minutes && 'unlimited' === seconds ) {
				showEditButton( button );
				// Trigger new event for timer being loaded for the button.
				const timerLoadedEvent = new CustomEvent( 'sceTimerLoaded', {
					detail: {
						button,
						commentId,
						postId,
					},
				} );
				document.dispatchEvent( timerLoadedEvent );
				return;
			}

			// Convert to integers.
			minutes = parseInt( minutes );
			seconds = parseInt( seconds );

			//Determine via JS if a user can edit a comment - Note that if someone were to finnagle with this, there is still a server side check when saving the comment
			if ( ! can_edit ) {
				//Remove elements
				button.parentNode.remove();
				return;
			}

			// Update the timer text placeholder.
			const timerText = getTimerText( minutes, seconds );
			const timerTextElement = button.querySelector( '.sce-timer' );
			if ( null !== timerTextElement ) {
				timerTextElement.textContent = timerText;
			}

			// Update other timers that are siblings.
			const siblingTimers = button.parentNode.querySelectorAll( '.sce-textarea .sce-timer' );
			if ( null !== siblingTimers ) {
				siblingTimers.forEach( ( siblingTimer ) => {
					siblingTimer.textContent = timerText;
				} );
			}

			// Show the edit interface.
			showEditButton( button );

			// Trigger new event for timer being loaded for the button.
			const timerLoadedEvent = new CustomEvent( 'sceTimerLoaded', {
				detail: {
					button,
					commentId,
					postId,
				},
			} );
			document.dispatchEvent( timerLoadedEvent );

			// Save textarea.
			const currentTextarea = document.querySelector( `#sce-edit-comment${ commentId } textarea` );
			if ( null !== currentTextarea ) {
				textareas[ commentId ] = currentTextarea.value;
			}

			// Save and set timer for one second.
			timers[ commentId ] = {
				minutes,
				seconds,
				start: new Date().getTime(),
				time: 0,
				timer: () => {
					let timer_seconds = timers[ commentId ].seconds - 1;
					let timer_minutes = timers[ commentId ].minutes;
					if ( timer_minutes <= 0 && timer_seconds <= 0 ) {
						// Remove any editor instance before its wrapper leaves the page.
						destroyCommentEditor( button.parentNode.querySelector( '.sce-comment-text' ), commentId, postId );
						button.parentNode.remove();
						return;
					}
					if ( timer_seconds < 0 ) {
						timer_minutes -= 1; timer_seconds = 59;
					}
					const newTimerText = getTimerText( timer_minutes, timer_seconds );
					const sceTimer = button.querySelector( '.sce-timer' );
					if ( null !== sceTimer ) {
						sceTimer.textContent = newTimerText;
					}
					//Update other timers that are siblings
					const newSiblingTimers = button.parentNode.querySelectorAll( '.sce-textarea .sce-timer' );
					if ( null !== newSiblingTimers ) {
						newSiblingTimers.forEach( ( siblingTimer ) => {
							siblingTimer.textContent = newTimerText;
						} );
					}

					// Trigger countdown event.
					const timerCountdownEvent = new CustomEvent( 'sceTimerCountdown', {
						detail: {
							button,
							commentId,
							postId,
							timer_seconds,
							timer_minutes,
						},
					} );
					document.dispatchEvent( timerCountdownEvent );

					// Update timer with new values.
					timers[ commentId ].seconds = timer_seconds;
					timers[ commentId ].minutes = timer_minutes;
					//Get accurate time
					const timer_obj = timers[ commentId ];
					timer_obj.time += 1000;
					const diff = ( new Date().getTime() - timer_obj.start ) - timer_obj.time;
					window.setTimeout( timer_obj.timer, ( 1000 - diff ) );
				},
			};
			window.setTimeout( timers[ commentId ].timer, 1000 );
		} ).catch( ( error ) => {
			showEditButton( button );
			showStatus( commentId, error.message || simple_comment_editing.comment_deleted_error, true );
		} );

		//Set up event for when the edit button is clicked
		const editButton = document.querySelector( `#sce-edit-comment${ commentId } .sce-edit-button-main` );
		if ( null !== editButton ) {
			editButton.addEventListener( 'click', ( e ) => {
				e.preventDefault();
				const editButtonWrapper = editButton.closest( '.sce-edit-comment' );
				if ( 'true' === editButtonWrapper.dataset.sceSubmitting ) {
					return;
				}
				const statusElement = document.querySelector( `#sce-edit-comment-status${ commentId }` );
				if ( null !== statusElement ) {
					statusElement.classList.remove( 'sce-error' );
					statusElement.classList.remove( 'sce-success' );
					statusElement.classList.add( 'sce-status' );
					statusElement.style.display = 'none';
				}
				//Hide the edit button and show the textarea
				setEditorControlsDisabled( editButtonWrapper, false );
				editButtonWrapper.querySelector( '.sce-textarea' ).style.display = 'block';
				editButton.parentNode.style.display = 'none';
				editButton.setAttribute( 'aria-expanded', 'true' );

				const textarea = editButtonWrapper.querySelector( '.sce-textarea textarea:first-of-type' );
				initializeCommentEditor( textarea, commentId, postId );
				const showEditTextAreaEvent = new CustomEvent( 'sceEditTextareaShow', {
					detail: {
						textarea,
						commentId,
						postId,
					},
				} );
				document.dispatchEvent( showEditTextAreaEvent );
				doAction( 'sce.comment.editor.show', {
					textarea,
					commentId,
					postId,
				} );
				textarea.focus();
			} );

			// For when the delete button is clicked.
			const deleteButton = button.querySelector( '.sce-delete-button-main' );
			if ( null !== deleteButton ) {
				deleteButton.addEventListener( 'click', ( e ) => {
					e.preventDefault();
					if ( simple_comment_editing.allow_delete_confirmation ) {
						if ( confirm( simple_comment_editing.confirm_delete ) ) {
							deleteComment( button, commentId, postId, nonce, ajaxUrl );
						}
					} else {
						deleteComment( button, commentId, postId, nonce, ajaxUrl );
					}
				} );
			}

			// Set up main delete button event.
			const commentDeleteButton = button.parentNode.querySelector( '.sce-textarea .sce-comment-delete' );
			if ( null !== commentDeleteButton ) {
				commentDeleteButton.addEventListener( 'click', ( e ) => {
					e.preventDefault();
					if ( simple_comment_editing.allow_delete_confirmation ) {
						if ( confirm( simple_comment_editing.confirm_delete ) ) {
							deleteComment( button, commentId, postId, nonce, ajaxUrl );
						}
					} else {
						deleteComment( button, commentId, postId, nonce, ajaxUrl );
					}
				} );
			}

			//Cancel button
			const cancelButton = button.parentNode.querySelector( '.sce-textarea .sce-comment-cancel' );
			if ( null !== cancelButton ) {
				cancelButton.addEventListener( 'click', ( e ) => {
					e.preventDefault();
					// Hide the textarea and restore the last saved content.
					const editButtonWrapper = cancelButton.closest( '.sce-edit-comment' );
					const textarea = editButtonWrapper.querySelector( '.sce-textarea textarea:first-of-type' );
					const savedContent = textareas[ commentId ];
					textarea.value = savedContent;
					dispatchEditorLifecycle( 'sceCommentEditorReset', 'sce.comment.editor.reset', {
						textarea,
						commentId,
						postId,
						content: savedContent,
					} );
					destroyCommentEditor( textarea, commentId, postId );
					restoreEditor( editButtonWrapper, editButton, false );
				} );
			}

			//Save button
			const saveButton = button.parentNode.querySelector( '.sce-textarea .sce-comment-save' );
			if ( null !== saveButton ) {
				saveButton.addEventListener( 'click', ( e ) => {
					e.preventDefault();
					const editorWrapper = button.closest( '.sce-edit-comment' );
					if ( 'true' === editorWrapper.dataset.sceSubmitting ) {
						return;
					}

					//Disable all buttons.
					editorWrapper.dataset.sceSubmitting = 'true';
					setEditorControlsDisabled( editorWrapper, true );
					button.parentNode.querySelector( '.sce-textarea' ).style.display = 'none';
					button.parentNode.querySelector( '.sce-loading' ).style.display = 'block';

					// Give native and external editors a synchronous chance to update the textarea.
					const textarea = button.parentNode.querySelector( '.sce-textarea textarea:first-of-type' );
					dispatchEditorLifecycle( 'sceCommentSavePre', 'sce.comment.editor.save.pre', {
						button,
						textarea,
						commentId,
						postId,
					} );
					const commentToSave = textarea.value.trim();

					//If the comment is blank, see if the user wants to delete their comment
					if ( commentToSave === '' && simple_comment_editing.allow_delete ) {
						if ( confirm( simple_comment_editing.empty_comment ) ) {
							editorWrapper.dataset.sceSubmitting = 'false';
							deleteComment( button, commentId, postId, nonce, ajaxUrl );
							return;
						}

						//Revert value.
						textarea.value = textareas[ commentId ];
						dispatchEditorLifecycle( 'sceCommentEditorReset', 'sce.comment.editor.reset', {
							textarea,
							commentId,
							postId,
							content: textareas[ commentId ],
						} );
						restoreEditor( editorWrapper, editButton, true );
						return;
					}

					// Save the comment.
					let ajaxSaveParams = {
						action: 'sce_save_comment',
						comment_content: commentToSave,
						comment_id: commentId,
						post_id: postId,
						nonce,
					};

					/**
					 * JSFilter: sce.comment.save.data
					 *
					 * Event triggered before a comment is saved
					 *
					 * @since 1.4.0
					 *
					 * @param  object $ajax_save_params
					 */
					ajaxSaveParams = applyFilters( 'sce.comment.save.data', ajaxSaveParams );

					saveComment( 'sce_save_comment', ajaxSaveParams, ajaxUrl ).then( ( response ) => {
						const { data } = response;
						if ( data && true === data.success && data.data && 'string' === typeof data.data.comment_text ) {
							// Update comment HTML.
							document.querySelector( `#sce-comment${ commentId }` ).innerHTML = data.data.comment_text;

							// Update textarea placeholder.
							textareas[ commentId ] = document.querySelector( `#sce-edit-comment${ commentId } textarea` ).value;

							// Set up post save event.
							const savePostEvent = new CustomEvent( 'sceCommentSavePost', {
								detail: {
									button,
									textarea,
									commentId,
									postId,
									ajaxData: data.data,
								},
							} );
							document.dispatchEvent( savePostEvent );
							doAction( 'sce.comment.editor.save.post', {
								button,
								textarea,
								commentId,
								postId,
								ajaxData: data.data,
							} );
							destroyCommentEditor( textarea, commentId, postId );
							restoreEditor( editorWrapper, editButton, false );
						} else {
							restoreEditor( editorWrapper, editButton, true );
							showStatus( commentId, getErrorMessage( response, simple_comment_editing.comment_deleted_error ), true );
						}
					} ).catch( ( error ) => {
						restoreEditor( editorWrapper, editButton, true );
						showStatus( commentId, error.message || simple_comment_editing.comment_deleted_error, true );
					} );
				} );
			}
		}
	};

	// Loop through all edit buttons.
	comment_edit_buttons.forEach( ( button ) => {
		initEditCommentButton( button );
	} );

	// If jQuery is enabled, hook into WooCommerce ratings.
	if ( 'undefined' !== typeof jQuery ) {
		jQuery( document ).ready( function() {
			const initializeStarRating = ( $rating ) => {
				const $ratingContainer = $rating.closest( '.comment-form-rating' );
				let $stars = $ratingContainer.find( '.stars' );

				if ( 0 === $stars.length ) {
					$rating.hide().before(
						'<p class="stars">\
							<span>\
								<a class="star-1" href="#">1</a>\
								<a class="star-2" href="#">2</a>\
								<a class="star-3" href="#">3</a>\
								<a class="star-4" href="#">4</a>\
								<a class="star-5" href="#">5</a>\
							</span>\
						</p>'
					);
					$stars = $ratingContainer.find( '.stars' );
				}

				const selectedRating = $ratingContainer.data( 'selected-rating' );
				$rating.val( selectedRating || '' );
				$stars.find( 'a' ).removeClass( 'active' );
				$stars.toggleClass( 'selected', Boolean( selectedRating ) );
				if ( selectedRating ) {
					$stars.find( '.star-' + selectedRating ).addClass( 'active' );
				}
			};

			// Add a control only to the SCE editor, leaving WooCommerce's new-review form untouched.
			jQuery( '.sce-edit-comment .comment-form-rating select' ).each( function() {
				initializeStarRating( jQuery( this ) );
			} );

			/**
			 * Each time a text area shows, select the correct star in the interface.
			 */
			document.addEventListener( 'sceEditTextareaShow', ( e ) => {
				const { commentId } = e.detail;
				const $rating = jQuery( `#sce-edit-comment${ commentId } .comment-form-rating select` );
				if ( 0 !== $rating.length ) {
					initializeStarRating( $rating );
				}
			} );

			// Update the rating after a successful SCE save when one was returned.
			document.addEventListener( 'sceCommentSavePost', ( e ) => {
				const { commentId, ajaxData } = e.detail;
				if ( ! ajaxData.rating ) {
					return;
				}

				const $ratingContainer = jQuery( `#sce-edit-comment${ commentId } .comment-form-rating` );
				const $stars = $ratingContainer.find( '.stars' );
				$stars.find( 'a' ).removeClass( 'active' );
				$stars.find( '.star-' + ajaxData.rating ).addClass( 'active' );
				$stars.addClass( 'selected' );

				// Set CSS width of star rating on frontend.
				const $starRating = $ratingContainer.closest( '#comment-' + commentId ).find( '.star-rating span' );

				// Assign ratings to the star string represented by width.
				$starRating.css( { width: ( ajaxData.rating * 20 ) + '%' } );

				// Update data param.
				$ratingContainer.attr( 'data-selected-rating', ajaxData.rating ).data( 'selected-rating', ajaxData.rating );
				$ratingContainer.find( 'select' ).val( ajaxData.rating );
			} );

			/**
			 * When someone selects a star, update the select field and add the active class.
			 */
			jQuery( 'body' ).on( 'click', '.sce-edit-comment .comment-form-rating p.stars a', function( e ) {
				e.preventDefault();
				const $star = jQuery( this ),
					$rating = jQuery( this ).closest( '.comment-form-rating' ).find( 'select' ),
					$container = jQuery( this ).closest( '.stars' );

				$rating.val( $star.text() );
				$star.siblings( 'a' ).removeClass( 'active' );
				$star.addClass( 'active' );
				$container.addClass( 'selected' );
				return false;
			} );
		} );
	}

	if ( 'compact' === simple_comment_editing.timer_appearance ) {
		addFilter( 'sce.comment.timer.text', 'simple-comment-editing', function( timer_text, days_text, hours_text, minutes_text, seconds_text, days, hours, minutes, seconds ) {
			timer_text = '';
			if ( days >= 1 ) {
				if ( days < 10 ) {
					timer_text += '' + '0' + days;
				} else {
					timer_text += days;
				}
				timer_text += ':';
			}
			if ( hours > 0 ) {
				if ( hours < 10 ) {
					timer_text += '' + '0' + hours;
				} else {
					timer_text += hours;
				}
				timer_text += ':';
			} else if ( hours === 0 && days > 0 ) {
				timer_text += '00';
				timer_text += ':';
			}
			if ( minutes > 0 ) {
				if ( minutes < 10 ) {
					timer_text += '' + '0' + minutes;
				} else {
					timer_text += minutes;
				}
				timer_text += ':';
			} else if ( minutes === 0 && hours > 0 ) {
				timer_text += '00';
				timer_text += ':';
			}
			if ( seconds > 0 ) {
				if ( seconds < 10 ) {
					timer_text += '' + '0' + seconds;
				} else {
					timer_text += seconds;
				}
			} else if ( seconds === 0 && minutes > 0 ) {
				timer_text += '00';
			}
			return timer_text;
		}, 10 );
	}
} );
//Callback when comments have been updated (for wp-ajaxify-comments compatibility) - http://wordpress.org/plugins/wp-ajaxify-comments/faq/
window.SCE_comments_updated = ( comment_url ) => {
	const match = comment_url.match( /comment-(\d+)/ );
	if ( ! match ) {
		return;
	}
	const comment_id = match[ 1 ];

	// Get comment.
	const comment = document.querySelector( `#comment-${ comment_id }` );
	if ( null === comment ) {
		return;
	}

	// Get the edit button.
	const editButton = comment.querySelector( '.sce-edit-button' );
	if ( null !== editButton ) {
		// Re-initialize the edit button.
		window.initEditCommentButton( editButton );
	}
};
