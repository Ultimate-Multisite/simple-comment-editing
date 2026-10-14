/* eslint-disable no-undef */
/**
 * Covers comment editing on the frontend.
 */
import sendCommand from '../SendCommand';

// get i18n variables.
const { __, _n } = wp.i18n;

// get hooks.
const { addFilter, applyFilters } = wp.hooks;

// Get the timer placeholder.
const timers = [];

// Get the textareas placeholder.
const textareas = [];

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

		// Get star rating placeholder.
		let starRating = null;

		if ( null !== comment ) {
			starRating = comment.querySelector( '.stars' );
		}

		// Only send an edited rating when the SCE review editor has a selected star and nonce.
		if ( null !== starRating ) {
			// Get selected star.
			const selectedStar = starRating.querySelector( '.active' );
			const nonceElement = comment.querySelector( '#woo_edit_comment_nonce_' + ajaxSaveParams.comment_id );

			if ( null === selectedStar || null === nonceElement ) {
				return ajaxSaveParams;
			}

			// Add nonce to Ajax params.
			ajaxSaveParams.wooEditCommentNonce = nonceElement.value;

			// Add selected rating to Ajax params.
			ajaxSaveParams.rating = selectedStar.textContent;

			return ajaxSaveParams;
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
		//Remove elements
		element.parentNode.remove();
		await sendCommand( 'sce_delete_comment', { comment_id: commentId, post_id: postId, nonce }, ajaxUrl ).then( ( response ) => {
			if ( ! response.data.success ) {
				alert( simple_comment_editing.comment_deleted_error );
			} else {
				const status = document.querySelector( '#sce-edit-comment-status' + commentId );

				// Remove all classes.
				status.classList.remove( 'sce-status', 'sce-status-error', 'sce-status-updated' );
				status.classList.add( 'sce-status', 'updated' );
				status.innerHTML = simple_comment_editing.comment_deleted;
				status.style.display = 'block';
				setTimeout( function() {
					document.querySelector( '#comment-' + commentId ).remove();
				}, 3000 ); //Attempt to remove the comment from the theme interface
			}
			return response;
		} );
	};

	// Function to run to initialize the edit comment buttons.
	window.initEditCommentButton = ( button ) => {
		// Get first link
		const ajaxUrl = button.querySelector( 'a:first-of-type' ).getAttribute( 'href' );

		// Get comment ID from URL query param.
		const urlParams = new URLSearchParams( ajaxUrl );
		const commentId = urlParams.get( 'cid' );
		const postId = urlParams.get( 'pid' );
		const nonce = urlParams.get( 'nonce' );

		// Get the time left for the comment.
		getTimeLeft( commentId, postId, simple_comment_editing.nonce, ajaxUrl ).then( ( response ) => {
			// Get the timer element.
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
						//Remove elements
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
		} );

		//Set up event for when the edit button is clicked
		const editButton = document.querySelector( `#sce-edit-comment${ commentId } .sce-edit-button-main` );
		if ( null !== editButton ) {
			editButton.addEventListener( 'click', ( e ) => {
				e.preventDefault();
				const statusElement = document.querySelector( `#sce-edit-comment-status${ commentId }` );
				if ( null !== statusElement ) {
					statusElement.classList.remove( 'sce-error' );
					statusElement.classList.remove( 'sce-success' );
					statusElement.classList.add( 'sce-status' );
					statusElement.style.display = 'none';
				}
				//Hide the edit button and show the textarea
				const editButtonWrapper = editButton.closest( '.sce-edit-comment' );
				editButtonWrapper.querySelectorAll( '.sce-textarea button' ).disabled = false;
				editButtonWrapper.querySelector( '.sce-textarea' ).style.display = 'block';
				editButton.parentNode.style.display = 'none';

				const textarea = editButtonWrapper.querySelector( '.sce-textarea textarea:first-of-type' );
				const showEditTextAreaEvent = new CustomEvent( 'sceEditTextareaShow', {
					detail: {
						textarea,
						commentId,
						postId,
					},
				} );
				document.dispatchEvent( showEditTextAreaEvent );
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
					//Hide the textarea and show the edit button
					const editButtonWrapper = cancelButton.closest( '.sce-edit-comment' );
					editButtonWrapper.querySelector( '.sce-textarea' ).style.display = 'none';
					editButtonWrapper.querySelector( '.sce-edit-button' ).style.display = 'block';

					document.querySelector( `#sce-edit-comment${ commentId } textarea` ).value = textareas[ commentId ];
				} );
			}

			//Save button
			const saveButton = button.parentNode.querySelector( '.sce-textarea .sce-comment-save' );
			if ( null !== saveButton ) {
				saveButton.addEventListener( 'click', ( e ) => {
					e.preventDefault();

					//Disable all buttons.
					button.parentNode.querySelectorAll( '.sce-textarea button' ).disabled = true;
					button.parentNode.querySelector( '.sce-textarea' ).style.display = 'none';
					button.parentNode.querySelector( '.sce-loading' ).style.display = 'block';

					//Save the comment
					const textarea = button.parentNode.querySelector( '.sce-textarea textarea:first-of-type' );
					const commentToSave = textarea.value.trim();

					//If the comment is blank, see if the user wants to delete their comment
					if ( commentToSave === '' && simple_comment_editing.allow_delete ) {
						if ( confirm( simple_comment_editing.empty_comment ) ) {
							deleteComment( button, commentId, postId, nonce, ajaxUrl );
						} else {
							//Revert value.
							textarea.value = textareas[ commentId ];
							button.parentNode.querySelectorAll( '.sce-textarea button' ).disabled = false;
							button.style.display = 'block';
						}
					}

					// Set up pre save event.
					const savePreEvent = new CustomEvent( 'sceCommentSavePre', {
						detail: {
							button,
							textarea,
							commentId,
							postId,
						},
					} );
					document.dispatchEvent( savePreEvent );

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
						// Hide loading.
						button.parentNode.querySelector( '.sce-loading' ).style.display = 'none';

						// Show the edit button.
						button.style.display = 'block';

						// Check if no errors.
						const { data } = response;
						if ( ! data.errors ) {
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
						} else {
							if ( data.data.remove === true ) {
								// Remove event handlers.
								button.parentNode.querySelector( '.sce-textarea' ).removeEventListener( 'submit' );
								button.removeEventListener( 'click' );

								// Remove elements.
								button.parentNode.remove();
							}

							// Clear all classes from status area.
							document.querySelector( `#sce-edit-comment-status${ commentId }` ).className = '';

							// Add class to status area and show it.
							document.querySelector( `#sce-edit-comment-status${ commentId }` ).classList.add( 'sce-status', 'error' );
							document.querySelector( `#sce-edit-comment-status${ commentId }` ).innerHTML = data.data.error;
							document.querySelector( `#sce-edit-comment-status${ commentId }` ).style.display = 'block';
						}
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
