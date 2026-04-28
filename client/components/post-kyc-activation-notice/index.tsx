/**
 * External dependencies
 */
import React from 'react';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import BannerNotice from '../banner-notice';

const stageContent: Record< number, { heading: string; body: string } > = {
	7: {
		heading: __( 'Ready to make your first sale?', 'woocommerce-payments' ),
		body: __(
			'Your account is approved and ready to accept payments. Add your products and share your store to get started.',
			'woocommerce-payments'
		),
	},
	14: {
		heading: __( 'Still setting up your store?', 'woocommerce-payments' ),
		body: __(
			"It's been two weeks since your account was approved. Make sure your products, shipping, and checkout are ready so you don't miss your first sale.",
			'woocommerce-payments'
		),
	},
	30: {
		heading: __(
			'30-day check-in: your first sale is within reach',
			'woocommerce-payments'
		),
		body: __(
			"Your account has been approved for 30 days. Stores that complete their setup early see faster first sales — let's make sure yours is ready.",
			'woocommerce-payments'
		),
	},
};

const PostKycActivationNotice: React.FC = () => {
	const { stage, dismissUrl } =
		window.wcpayPostKycActivationNoticeSettings ?? {};

	const content = stage ? stageContent[ stage ] : null;
	if ( ! content ) {
		return null;
	}

	return (
		<BannerNotice
			status="info"
			isDismissible={ true }
			onRemove={ () => {
				window.location.href = dismissUrl ?? '';
			} }
		>
			<strong>{ content.heading }</strong> { content.body }
		</BannerNotice>
	);
};

export default PostKycActivationNotice;
