/* eslint-disable no-undef */
/* eslint-disable camelcase */
/* eslint-disable no-unused-vars */
import React, { useEffect, useState } from 'react';
import Spinner from './Spinner';
import SendCommand from '../utils/SendCommand';
import Section from './Section';
import TogglPlanLogo from './TogglPlanLogo';
import Error from './Error';

const { __ } = wp.i18n;

const AuthInterface = () => {
	const [ loading, setLoading ] = useState( true );
	const [ errors, setErrors ] = useState( false );
	const [ errorMessage, setErrorMessage ] = useState( '' );

	useEffect( () => {
		const state = document.querySelector( '#toggl_state' ).value;
		const code = document.querySelector( '#toggl_code' ).value;
		// Load App Key and Secret Key.
		SendCommand( 'toggl_plan_get_auth_key', {
			// eslint-disable-next-line no-undef, camelcase
			nonce: gforms_toggl_admin_auth_js_strings.nonce,
			code,
			state,
		} )
			.then( ( response ) => {
				const { data, success } = response.data;
				if ( ! success ) {
					setErrors( true );
					setErrorMessage( data.message );
				} else {
					window.open( gforms_toggl_admin_auth_js_strings.main_url, '_self' );
				}
			} )
			.catch( ( response ) => {} )
			.then( ( response ) => {
				//setLoading( false );
			} );
	}, [] );

	const loadingInterface = () => {
		return (
			<div id="toggl-getting-started" className="friendly-notice">
				<h3>
					{ __( 'Authenticating with Toggl Plan', 'tazker-for-toggl-plan' ) }&nbsp;&nbsp;
					<Spinner width="16" height="16" />
				</h3>
				<p className="description">
					{ __( 'We are authenticating with Toggl Plan. It will just be a moment.', 'tazker-for-toggl-plan' ) }
				</p>
			</div>
		);
	};

	return (
		<>
			<Section title={ __( 'Toggl Plan Authorization', 'tazker-for-toggl-plan' ) } description={ __( 'Let us connect to Toggl Plan.', 'tazker-for-toggl-plan' ) } Logo={ TogglPlanLogo }>
				{ errors && <Error error={ errorMessage } /> }
				<div id="toggl-plan-main-ui-container">
					{ loading ? loadingInterface() : <>hi</> }
				</div>
			</Section>
		</>
	);
};

export default AuthInterface;
