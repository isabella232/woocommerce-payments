<?php
/**
 * Class WC_REST_Payments_Webhook_Controller
 *
 * @package WooCommerce\Payments\Admin
 */

use WCPay\Exceptions\WC_Payments_Rest_Request_Exception;
use WCPay\Logger;

defined( 'ABSPATH' ) || exit;

/**
 * REST controller for webhooks.
 */
class WC_REST_Payments_Webhook_Controller extends WC_Payments_REST_Controller {

	/**
	 * Result codes for returning to the WCPay server API. They don't have any special meaning, but can will be logged
	 * and are therefore useful when debugging how we reacted to a webhook.
	 */
	const RESULT_SUCCESS     = 'success';
	const RESULT_BAD_REQUEST = 'bad_request';
	const RESULT_ERROR       = 'error';

	/**
	 * Endpoint path.
	 *
	 * @var string
	 */
	protected $rest_base = 'payments/webhook';

	/**
	 * DB wrapper.
	 *
	 * @var WC_Payments_DB
	 */
	private $wcpay_db;

	/**
	 * WC Payments Account.
	 *
	 * @var WC_Payments_Account
	 */
	private $account;

	/**
	 * WC_REST_Payments_Webhook_Controller constructor.
	 *
	 * @param WC_Payments_API_Client $api_client WC_Payments_API_Client instance.
	 * @param WC_Payments_DB         $wcpay_db   WC_Payments_DB instance.
	 * @param WC_Payments_Account    $account    WC_Payments_Account instance.
	 */
	public function __construct( WC_Payments_API_Client $api_client, WC_Payments_DB $wcpay_db, WC_Payments_Account $account ) {
		parent::__construct( $api_client );
		$this->wcpay_db = $wcpay_db;
		$this->account  = $account;
	}

	/**
	 * Configure REST API routes.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'handle_webhook' ],
				'permission_callback' => [ $this, 'check_permission' ],
			]
		);
	}

	/**
	 * Retrieve transactions to respond with via API.
	 *
	 * @param WP_REST_Request $request Full data about the request.
	 *
	 * @return WP_REST_Response
	 */
	public function handle_webhook( $request ) {
		$body = $request->get_json_params();

		try {
			// Extract information about the webhook event.
			$event_type = $this->read_rest_property( $body, 'type' );

			Logger::debug( 'Webhook received: ' . $event_type );
			Logger::debug( 'Webhook body: ' . var_export( $body, true ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export

			switch ( $event_type ) {
				case 'charge.refund.updated':
					$this->process_webhook_refund_updated( $body );
					break;
				case 'account.updated':
					$this->account->refresh_account_data();
					break;
				case 'charge.dispute.created':
					$this->process_dispute_created( $body );
					break;
				case 'charge.dispute.closed':
					$this->process_dispute_closed( $body );
					break;
			}
		} catch ( WC_Payments_Rest_Request_Exception $e ) {
			Logger::error( $e );
			return new WP_REST_Response( [ 'result' => self::RESULT_BAD_REQUEST ], 400 );
		} catch ( Exception $e ) {
			Logger::error( $e );
			return new WP_REST_Response( [ 'result' => self::RESULT_ERROR ], 500 );
		}

		return new WP_REST_Response( [ 'result' => self::RESULT_SUCCESS ] );
	}

	/**
	 * Process webhook refund updated.
	 *
	 * @param array $event_body The event that triggered the webhook.
	 *
	 * @throws WC_Payments_Rest_Request_Exception Required parameters not found.
	 * @throws Exception                  Unable to resolve charge ID to order.
	 */
	private function process_webhook_refund_updated( $event_body ) {
		$event_data   = $this->read_rest_property( $event_body, 'data' );
		$event_object = $this->read_rest_property( $event_data, 'object' );

		// First, check the reason for the update. We're only interesting in a status of failed.
		$status = $this->read_rest_property( $event_object, 'status' );
		if ( 'failed' !== $status ) {
			return;
		}

		// Fetch the details of the failed refund so that we can find the associated order and write a note.
		$charge_id = $this->read_rest_property( $event_object, 'charge' );
		$refund_id = $this->read_rest_property( $event_object, 'id' );
		$amount    = $this->read_rest_property( $event_object, 'amount' );

		// Look up the order related to this charge.
		$order = $this->wcpay_db->order_from_charge_id( $charge_id );
		if ( ! $order ) {
			throw new Exception(
				sprintf(
					/* translators: %1: charge ID */
					__( 'Could not find order via charge ID: %1$s', 'woocommerce-payments' ),
					$charge_id
				)
			);
		}

		$note = sprintf(
			WC_Payments_Utils::esc_interpolated_html(
				/* translators: %1: the refund amount, %2: ID of the refund */
				__( 'A refund of %1$s was <strong>unsuccessful</strong> using WooCommerce Payments (<code>%2$s</code>).', 'woocommerce-payments' ),
				[
					'strong' => '<strong>',
					'code'   => '<code>',
				]
			),
			wc_price( $amount / 100 ),
			$refund_id
		);
		$order->add_order_note( $note );
	}

	/**
	 * Process a newly created dispute.
	 *
	 * @param array $event_body The event that triggered the webhook.
	 *
	 * @throws WC_Payments_Rest_Request_Exception Required parameters not found.
	 * @throws Exception                          Unable to resolve charge ID to order.
	 */
	private function process_dispute_created( $event_body ) {
		$event_data   = $this->read_rest_property( $event_body, 'data' );
		$event_object = $this->read_rest_property( $event_data, 'object' );

		// Fetch the details of the disputed order and move it to the "Disputed" status.
		$dispute_id = $this->read_rest_property( $event_object, 'id' );
		$charge_id  = $this->read_rest_property( $event_object, 'charge' );

		// Look up the order related to this dispute (via the disputed charge).
		$order = $this->wcpay_db->order_from_charge_id( $charge_id );
		if ( ! $order ) {
			throw new Exception(
				sprintf(
				/* translators: %1: charge ID */
					__( 'Could not find order via charge ID: %1$s', 'woocommerce-payments' ),
					$charge_id
				)
			);
		}

		// Store old status.
		$current_status = $order->get_status();
		$order->add_meta_data( '_pre_dispute_status', $current_status, true );

		// Build link to dispute details.
		// TODO: This uses the URL the WCPay server thinks we're at (so a Docker one for the dev environment).
		$dispute_url = add_query_arg(
			[
				'page' => 'wc-admin',
				'path' => rawurlencode( '/payments/disputes/details' ),
				'id'   => rawurlencode( $dispute_id ),
			],
			admin_url( 'admin.php' )
		);

		$note = WC_Payments_Utils::esc_interpolated_html(
			__(
				'A dispute was created for this order. Response is needed. Please go to your <a>dashboard</a> to review this dispute.',
				'woocommerce-payments'
			),
			[ 'a' => '<a title="Dispute Details" href="' . $dispute_url . '">' ]
		);

		$order->set_status( 'disputed', $note );
		$order->save();
	}

	/**
	 * Process the closure of a dispute.
	 *
	 * @param array $event_body The event that triggered the webhook.
	 *
	 * @throws WC_Payments_Rest_Request_Exception Required parameters not found.
	 * @throws Exception                          Unable to resolve charge ID to order.
	 */
	private function process_dispute_closed( $event_body ) {
		// TODO: Break out some of this duplicate code.
		$event_data   = $this->read_rest_property( $event_body, 'data' );
		$event_object = $this->read_rest_property( $event_data, 'object' );

		// Fetch the details of the disputed order.
		$charge_id         = $this->read_rest_property( $event_object, 'charge' );
		$dispute_status    = $this->read_rest_property( $event_object, 'status' );
		$dispute_meta_data = $this->read_rest_property( $event_object, 'metadata' );

		// Look up the order related to this dispute (via the disputed charge).
		$order = $this->wcpay_db->order_from_charge_id( $charge_id );
		if ( ! $order ) {
			throw new Exception(
				sprintf(
					/* translators: %1: charge ID */
					__( 'Could not find order via charge ID: %1$s', 'woocommerce-payments' ),
					$charge_id
				)
			);
		}

		switch ( $dispute_status ) {
			case 'lost':
				if ( isset( $dispute_meta_data['__closed_by_merchant'] ) && '1' === $dispute_meta_data['__closed_by_merchant'] ) {
					// Dispute accepted by merchant.
					// TODO: Only move to "Disputed - Accepted" if the current status is "Disputed"?
					// TODO: Wording?
					$order->update_status( 'disputed-accepted', __( 'The dispute was accepted.', 'woocommerce-payments' ) );
				} else {
					// Dispute was lost.
					// TODO: Wording?
					$order->update_status( 'disputed-lost', __( 'The dispute was lost.', 'woocommerce-payments' ) );
				}
				break;
			case 'won':
				// Dispute was won - get the pre-dispute status and reset the order status.
				$old_status     = $order->get_meta( '_pre_dispute_status', true );
				$current_status = $order->get_status();

				// If the current status is "Disputed" and we have a pre-dispute status saved, then update the order
				// status to whatever it was before the dispute.
				if ( 'disputed' === $current_status && '' !== $old_status ) {
					// TODO: Wording?
					$order->update_status( $old_status, __( 'The dispute was won.', 'woocommerce-payments' ) );
				} else {
					// TODO: Wording?
					$order->add_order_note( __( 'The dispute was won', 'woocommerce-payments' ) );
				}
				break;
		}
	}

	/**
	 * Safely get a value from the REST request body array.
	 *
	 * @param array  $array Array to read from.
	 * @param string $key   ID to fetch on.
	 *
	 * @return string|array
	 * @throws WC_Payments_Rest_Request_Exception Thrown if ID not set.
	 */
	private function read_rest_property( $array, $key ) {
		if ( ! isset( $array[ $key ] ) ) {
			throw new WC_Payments_Rest_Request_Exception(
				sprintf(
					/* translators: %1: ID being fetched */
					__( '%1$s not found in array', 'woocommerce-payments' ),
					$key
				)
			);
		}
		return $array[ $key ];
	}
}
