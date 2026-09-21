/* eslint-disable no-unused-vars */
/* global sceIntegrations */
import React, { useState, Suspense, useEffect } from 'react';
import { useForm, Controller, useWatch, useFormState } from 'react-hook-form';
import classNames from 'classnames';
import { useAsyncResource } from 'use-async-resource';
import BeatLoader from 'react-spinners/BeatLoader';

import {
	TextControl,
	Button,
	RadioControl,
	ToggleControl,
} from '@wordpress/components';
import Spinner from '../../components/icons/Spinner';
import ClipboardCheck from '../../components/icons/ClipboardCheck';
import sendCommand from '../../../SendCommand';
import CircularExclamationIcon from '../../components/icons/CircularExplanation';
import Notice from '../../components/Notice';

const { __, sprintf } = wp.i18n;

const retrieveAvatarOptions = () => {
	return sendCommand( 'sce_get_mailchimp_options', {
		nonce: sceIntegrations.get_nonce,
	} );
};

const Mailchimp = ( props ) => {
	const [ defaults, getDefaults ] = useAsyncResource( retrieveAvatarOptions, [] );
	return (
		<Suspense
			fallback={
				<>
					<h2>{ __( 'Mailchimp', 'simple-comment-editing' ) }</h2>
					<BeatLoader
						color={ '#9c68b0' }
						loading={ true }
						cssOverride={ true }
						size={ 25 }
						speedMultiplier={ 0.65 }
					/>
				</>
			}
		>
			<Interface defaults={ defaults } { ...props } />
		</Suspense>
	);
};

const Interface = ( props ) => {
	const { defaults } = props;
	const response = defaults();
	const { data, success } = response.data;
	const [ saving, setSaving ] = useState( false );
	const [ isSaved, setIsSaved ] = useState( false );
	const [ resetting, setResetting ] = useState( false );
	const [ isReset, setIsReset ] = useState( false );

	const {
		register,
		control,
		handleSubmit,
		setValue,
		getValues,
		reset,
		setError,
	} = useForm( {
		defaultValues: {
			enableMailchimp: data.enableMailchimp,
			apiKey: data.apiKey,
			mailchimpServerPrefix: data.mailchimpServerPrefix,
			mailchimpLists: data.mailchimpLists,
			selectedList: data.selectedList,
			signUpLabel: data.signUpLabel,
			checkboxEnabled: data.checkboxEnabled,
		},
	} );
	const formValues = useWatch( { control } );
	const { errors, isDirty, dirtyFields, touchedFields } = useFormState( {
		control,
	} );

	const hasErrors = () => {
		return Object.keys( errors ).length > 0;
	};

	useEffect( () => {
		const apiKey = formValues.apiKey;
		if ( '' === apiKey || null === apiKey ) {
			return;
		}
		// Try to get server prefix for Mailchimp API key.
		const regex = new RegExp( /-(\w+)$/ );
		const matches = apiKey.match( regex );

		// If no matches, return empty string.
		if ( null === matches ) {
			return;
		}

		// We have matches! Match should be index 1.
		const serverPrefix = matches[ 1 ];

		setValue( 'mailchimpServerPrefix', serverPrefix, {
			shouldDirty: false,
			shouldValidate: false,
			shouldTouch: false,
		} );
	}, [ formValues.apiKey, setValue ] );

	const onSubmit = ( formData ) => {
		setSaving( true );
		sendCommand( 'sce_save_mailchimp_options', {
			nonce: sceIntegrations.save_nonce,
			...formData,
		} )
			.then( ( ajaxResponse ) => {
				const ajaxData = ajaxResponse.data.data;
				const ajaxSuccess = ajaxResponse.data.success;
				if ( ajaxSuccess ) {
					const { lists, mailchimp_selected_list, mailchimp_signup_label } =
						ajaxData;
					const listsValues = [];
					lists.forEach( ( keys, index ) => {
						listsValues.push( keys );
					} );
					setValue( 'selectedList', mailchimp_selected_list );
					setValue( 'mailchimpLists', listsValues );
					setValue( 'signUpLabel', mailchimp_signup_label );
					setIsSaved( true );
					setTimeout( () => {
						setIsSaved( false );
					}, 3000 );
				} else {
					setError( 'apiKey', { type: 'noLists', message: ajaxData.message } );
				}
			} )
			.catch( ( ajaxResponse ) => {} )
			.then( ( ajaxResponse ) => {
				setSaving( false );
			} );
	};

	const handleReset = () => {
		setResetting( true );
		sendCommand( 'sce_reset_mailchimp_options', {
			nonce: sceIntegrations.reset_nonce,
		} )
			.then( ( ajaxResponse ) => {
				const ajaxData = ajaxResponse.data.data;
				const ajaxSuccess = ajaxResponse.data.success;
				if ( ajaxSuccess ) {
					setIsReset( true );
					reset( {
						enableMailchimp: false,
						mailchimpServerPrefix: '',
						apiKey: '',
						mailchimpLists: [],
						selectedList: '',
						signUpLabel: __( 'Sign Up for Updates', 'simple-comment-editing' ),
					} );
					setTimeout( () => {
						setIsReset( false );
					}, 3000 );
				}
			} )
			.catch( ( ajaxResponse ) => {} )
			.then( ( ajaxResponse ) => {
				setResetting( false );
			} );
	};

	const getNewsletterLabel = () => {
		if ( ! getValues( 'selectedList' ) ) {
			return <></>;
		}
		return (
			<tr>
				<th scope="row">{ __( 'Sign Up Label', 'simple-comment-editing' ) }</th>
				<td>
					<Controller
						name="signUpLabel"
						control={ control }
						rules={ { required: true } }
						render={ ( { field } ) => (
							<TextControl
								{ ...field }
								label={ __( 'Sign-up Label', 'simple-comment-editing' ) }
								className={ classNames( 'qdlx-admin__text-control', {
									'has-error': 'required' === errors.signUpLabel?.type,
									'is-required': true,
								} ) }
								aria-required="true"
								help={ __(
									'This text will be shown in the comment section when a user leaves a comment.',
									'simple-comment-editing'
								) }
							/>
						) }
					/>
					{ 'required' === errors.signUpLabel?.type && (
						<Notice
							message={ __( 'This field is a required field.', 'simple-comment-editing' ) }
							status="error"
							politeness="assertive"
							inline={ true }
							icon={ CircularExclamationIcon }
						/>
					) }
				</td>
			</tr>
		);
	};

	const getSignupCheckbox = () => {
		if ( ! getValues( 'selectedList' ) ) {
			return <></>;
		}
		return (
			<tr>
				<th scope="row">{ __( 'Sign Up Checkbox', 'simple-comment-editing' ) }</th>
				<td>
					<Controller
						name="checkboxEnabled"
						control={ control }
						render={ ( { field: { onChange, value } } ) => (
							<ToggleControl
								label={ __( 'Sign Up Checkbox Checked', 'simple-comment-editing' ) }
								className="sce-admin__toggle-control"
								checked={ value }
								onChange={ ( boolValue ) => {
									onChange( boolValue );
								} }
								help={ __(
									'Check the newsletter checkbox by default (not recommended).',
									'simple-comment-editing'
								) }
							/>
						) }
					/>
				</td>
			</tr>
		);
	};

	const getMailchimpLists = () => {
		const mailchimpCurrentLists = getValues( 'mailchimpLists' );
		if ( mailchimpCurrentLists.length === 0 ) {
			return <></>;
		}

		return (
			<tr>
				<th scope="row">{ __( 'Mailchimp Lists', 'simple-comment-editing' ) }</th>
				<td>
					<Controller
						name="mailchimpLists"
						control={ control }
						rules={ { required: true } }
						render={ ( { field: { onChange, value } } ) => (
							<RadioControl
								label={ __( 'Select a default list', 'simple-comment-editing' ) }
								help={ __(
									'Select a default list that will be used for your newsletter prompt',
									'simple-comment-editing'
								) }
								selected={ getValues( 'selectedList' ) }
								options={ getValues( 'mailchimpLists' ) }
								onChange={ ( listValue ) => {
									onChange( getValues( 'mailchimpLists' ) );
									setValue( 'selectedList', listValue );
								} }
							/>
						) }
					/>
					<Controller
						name="selectedList"
						control={ control }
						rules={ { required: true } }
						render={ ( { field } ) => (
							<TextControl { ...field } type="hidden" aria-hidden="true" />
						) }
					/>
					{ 'required' === errors.selectedList?.type && (
						<Notice
							message={ __( 'This field is a required field.', 'simple-comment-editing' ) }
							status="error"
							politeness="assertive"
							inline={ true }
							icon={ CircularExclamationIcon }
						/>
					) }
				</td>
			</tr>
		);
	};

	const getServerPrefix = () => {
		const apiKey = getValues( 'apiKey' );
		if ( '' === apiKey ) {
			return <></>;
		}

		// Output server prefix in readonly mode.
		return (
			<tr>
				<th scope="row">{ __( 'Server Prefix', 'simple-comment-editing' ) }</th>
				<td>
					<Controller
						name="mailchimpServerPrefix"
						control={ control }
						rules={ { required: true } }
						render={ ( { field } ) => (
							<TextControl
								label={ __( 'Mailchimp Server Prefix', 'simple-comment-editing' ) }
								{ ...field }
								className={ classNames( 'qdlx-admin__text-control', {
									'has-error': 'required' === errors.apiKey?.type,
									'is-required': true,
								} ) }
								disabled={ true }
								help={ __(
									'A server prefix is automatically generated for API access.',
									'simple-comment-editing'
								) }
								aria-required="true"
							/>
						) }
					/>
				</td>
			</tr>
		);
	};

	const getAPIKey = () => {
		return (
			<tr>
				<th scope="row">{ __( 'API Key', 'simple-comment-editing' ) }</th>
				<td>
					<Controller
						name="apiKey"
						control={ control }
						rules={ { required: true } }
						render={ ( { field } ) => (
							<TextControl
								label={ __( 'Mailchimp API Key', 'simple-comment-editing' ) }
								{ ...field }
								className={ classNames( 'qdlx-admin__text-control', {
									'has-error': 'required' === errors.apiKey?.type,
									'is-required': true,
								} ) }
								help={ __(
									'Enter your Mailchimp API Key in order to choose a default list.',
									'simple-comment-editing'
								) }
								aria-required="true"
							/>
						) }
					/>
					{ 'required' === errors.apiKey?.type && (
						<Notice
							message={ __( 'This field is a required field.', 'simple-comment-editing' ) }
							status="error"
							politeness="assertive"
							inline={ true }
							icon={ CircularExclamationIcon }
						/>
					) }
					{ 'noLists' === errors.apiKey?.type && (
						<Notice
							message={ __(
								'No Mailchimp lists for the API key were found.',
								'simple-comment-editing'
							) }
							status="error"
							politeness="assertive"
							inline={ true }
							icon={ CircularExclamationIcon }
						/>
					) }
				</td>
			</tr>
		);
	};

	const getSaveIcon = () => {
		if ( saving ) {
			return Spinner;
		}
		if ( isSaved ) {
			return ClipboardCheck;
		}
		return false;
	};

	const getSaveText = () => {
		if ( saving ) {
			return __( 'Saving…', 'simple-comment-editing' );
		}
		if ( isSaved ) {
			return __( 'Saved', 'simple-comment-editing' );
		}
		return __( 'Save Mailchimp Options', 'simple-comment-editing' );
	};

	const getResetText = () => {
		if ( resetting ) {
			return __( 'Disconnecting…', 'simple-comment-editing' );
		}
		if ( isReset ) {
			return __( 'Disconnected', 'simple-comment-editing' );
		}
		return __( 'Disconnect From Mailchimp', 'simple-comment-editing' );
	};

	return (
		<>
			<h2>{ __( 'Mailchimp', 'simple-comment-editing' ) }</h2>
			<p className="description">
				{ __(
					'When someone leaves a comment, they can be given the option to subscribe to your newsletter on Mailchimp.',
					'simple-comment-editing'
				) }
			</p>
			<form onSubmit={ handleSubmit( onSubmit ) }>
				<table className="form-table">
					<tbody>
						<tr>
							<th scope="row">{ __( 'Mailchimp', 'simple-comment-editing' ) }</th>
							<td>
								<Controller
									name="enableMailchimp"
									control={ control }
									render={ ( { field: { onChange, value } } ) => (
										<ToggleControl
											label={ __( 'Enable Mailchimp', 'simple-comment-editing' ) }
											className="sce-admin__toggle-control"
											checked={ value }
											onChange={ ( boolValue ) => {
												//setShowButtonOptions( boolValue );
												onChange( boolValue );
											} }
											help={ __(
												'Enable or Disable the Mailchimp Integration.',
												'simple-comment-editing'
											) }
										/>
									) }
								/>
							</td>
						</tr>
						{ getValues( 'enableMailchimp' ) && (
							<>
								{ getAPIKey() }
								{ getServerPrefix() }
								{ getMailchimpLists() }
								{ getNewsletterLabel() }
								{ getSignupCheckbox() }
							</>
						) }
					</tbody>
				</table>
				<div className="sce-admin-buttons">
					<Button
						className={ classNames(
							'qdlx__btn qdlx__btn-primary qdlx__btn--icon-right',
							{ 'has-error': hasErrors() },
							{ 'has-icon': saving || isSaved },
							{ 'is-saving': saving && ! isSaved },
							{ 'is-saved': isSaved }
						) }
						type="submit"
						text={ getSaveText() }
						icon={ getSaveIcon() }
						iconSize="18"
						iconPosition="right"
						disabled={ saving }
					/>
					{ '' !== getValues( 'selectedList' ) && (
						<Button
							className={ classNames(
								'qdlx__btn qdlx__btn-danger qdlx__btn--icon-right',
								{ 'has-icon': resetting },
								{ 'is-resetting': { resetting } }
							) }
							type="button"
							text={ getResetText() }
							icon={ resetting ? Spinner : false }
							iconSize="18"
							iconPosition="right"
							disabled={ saving || resetting }
							onClick={ ( e ) => {
								setResetting( true );
								handleReset( e );
							} }
						/>
					) }
				</div>
				{ hasErrors() && (
					<Notice
						message={ __(
							'There are form validation errors. Please correct them above.',
							'simple-comment-editing'
						) }
						status="error"
						politeness="polite"
					/>
				) }
				{ isSaved && (
					<Notice
						message={ __( 'Your settings have been saved.', 'simple-comment-editing' ) }
						status="success"
						politeness="assertive"
					/>
				) }
				{ isReset && (
					<Notice
						message={ __( 'Your settings have been reset.', 'simple-comment-editing' ) }
						status="success"
						politeness="assertive"
					/>
				) }
			</form>
		</>
	);
};
export default Mailchimp;
