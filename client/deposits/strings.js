/** @format **/

/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';

export const displayType = {
	deposit: __( 'Deposit', 'woocommerce-payments' ),
	withdrawal: __( 'Withdrawal', 'woocommerce-payments' ),
};

export const displayStatus = {
	paid: __( 'Paid', 'woocommerce-payments' ),
	pending: __( 'Pending', 'woocommerce-payments' ),
	// eslint-disable-next-line camelcase
	in_transit: __( 'In Transit', 'woocommerce-payments' ),
	canceled: __( 'Canceled', 'woocommerce-payments' ),
	failed: __( 'Failed', 'woocommerce-payments' ),
};