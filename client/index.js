/** @format **/

/**
 * External dependencies
 */
import { __ } from '@wordpress/i18n';
import { addFilter } from '@wordpress/hooks';

/**
 * Internal dependencies
 */
import './style.scss';
import ConnectAccountPage from 'connect-account-page';
import DepositsPage from 'deposits';
import DepositDetailsPage from 'deposits/details';
import TransactionsPage from 'transactions';
import PaymentDetailsPage from 'payment-details';
import DisputesPage from 'disputes';
import DisputeDetailsPage from 'disputes/details';
import DisputeEvidencePage from 'disputes/evidence';
import { withTestNotice, topics } from 'components/test-mode-notice';

addFilter(
	'woocommerce_admin_pages_list',
	'woocommerce-payments',
	( pages ) => {
		const { menuID, rootLink } = getMenuSettings();

		pages.push( {
			container: ConnectAccountPage,
			path: '/payments/connect',
			wpOpenMenu: menuID,
			breadcrumbs: [ rootLink, __( 'Connect', 'woocommerce-payments' ) ],
		} );
		pages.push( {
			container: withTestNotice( DepositsPage, topics.deposits ),
			path: '/payments/deposits',
			wpOpenMenu: menuID,
			breadcrumbs: [ rootLink, __( 'Deposits', 'woocommerce-payments' ) ],
		} );
		pages.push( {
			container: withTestNotice(
				DepositDetailsPage,
				topics.depositDetails
			),
			path: '/payments/deposits/details',
			wpOpenMenu: menuID,
			breadcrumbs: [
				rootLink,
				[
					'/payments/deposits',
					__( 'Deposits', 'woocommerce-payments' ),
				],
				__( 'Deposit details', 'woocommerce-payments' ),
			],
		} );
		pages.push( {
			container: withTestNotice( TransactionsPage, topics.transactions ),
			path: '/payments/transactions',
			wpOpenMenu: menuID,
			breadcrumbs: [
				rootLink,
				__( 'Transactions', 'woocommerce-payments' ),
			],
		} );
		pages.push( {
			container: withTestNotice(
				PaymentDetailsPage,
				topics.paymentDetails
			),
			path: '/payments/transactions/details',
			wpOpenMenu: menuID,
			breadcrumbs: [
				rootLink,
				[
					'/payments/transactions',
					__( 'Transactions', 'woocommerce-payments' ),
				],
				__( 'Payment details', 'woocommerce-payments' ),
			],
		} );
		pages.push( {
			container: withTestNotice( DisputesPage, topics.disputes ),
			path: '/payments/disputes',
			wpOpenMenu: menuID,
			breadcrumbs: [ rootLink, __( 'Disputes', 'woocommerce-payments' ) ],
		} );
		pages.push( {
			container: withTestNotice(
				DisputeDetailsPage,
				topics.disputeDetails
			),
			path: '/payments/disputes/details',
			wpOpenMenu: menuID,
			breadcrumbs: [
				rootLink,
				[
					'/payments/disputes',
					__( 'Disputes', 'woocommerce-payments' ),
				],
				__( 'Dispute details', 'woocommerce-payments' ),
			],
		} );
		pages.push( {
			container: withTestNotice(
				DisputeEvidencePage,
				topics.disputeDetails
			),
			path: '/payments/disputes/challenge',
			wpOpenMenu: menuID,
			breadcrumbs: [
				rootLink,
				[
					'/payments/disputes',
					__( 'Disputes', 'woocommerce-payments' ),
				],
				__( 'Challenge dispute', 'woocommerce-payments' ),
			],
		} );
		return pages;
	}
);

/**
 * Get menu settings based on the top level link being connect or deposits
 *
 * @returns { { menuID, rootLink } }  Object containing menuID and rootLink
 */
function getMenuSettings() {
	const connectPage = document.querySelector(
		'#toplevel_page_wc-admin-path--payments-connect'
	);
	const topLevelPage = connectPage ? 'connect' : 'deposits';

	return {
		menuID: `toplevel_page_wc-admin-path--payments-${ topLevelPage }`,
		rootLink: [
			`/payments/${ topLevelPage }`,
			__( 'Payments', 'woocommerce-payments' ),
		],
	};
}
