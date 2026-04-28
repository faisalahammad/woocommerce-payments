/**
 * External dependencies
 */
import React from 'react';
import { createRoot } from 'react-dom/client';

/**
 * Internal dependencies
 */
import PostKycActivationNotice from 'components/post-kyc-activation-notice';

const containerId = 'wcpay-post-kyc-activation-notice';
let observer: MutationObserver | null = null;

const tryMount = () => {
	let container = document.getElementById(
		containerId
	) as HTMLElement | null;

	if ( ! container ) {
		container = document.createElement( 'div' );
		container.id = containerId;

		const sectionNav = document.querySelector( '#mainform .subsubsub' );
		const tabNav = document.querySelector(
			'#mainform .woo-nav-tab-wrapper'
		);
		const settingsAnchor = sectionNav ?? tabNav;

		if ( settingsAnchor ) {
			settingsAnchor.after( container );
		} else {
			const target =
				document.querySelector( '.woocommerce-layout__main' ) ??
				document.querySelector( '#wpbody-content .wrap' );

			if ( ! target ) {
				return;
			}

			const headerEnd = target.querySelector( '.wp-header-end' );
			if ( headerEnd ) {
				headerEnd.after( container );
			} else {
				target.prepend( container );
			}
		}
	}

	createRoot( container ).render( <PostKycActivationNotice /> );
	observer?.disconnect();
};

observer = new MutationObserver( tryMount );
observer.observe( document.body, { childList: true, subtree: true } );
tryMount();
