<?php
/**
 * BullMessage Store Api
 *
 * StoreApi used to process consents at checkout
 *
 * @package BullMessage/
 * @since   0.1.0
 */

namespace Bullmessage\Blocks;

use Automattic\WooCommerce\StoreApi\Schemas\V1\CheckoutSchema;
use WP_REST_Request;
use WC_Order;

/**
 * Class StoreApi
 *
 * @package Bullmessage\Blocks
 */
class StoreApi {

	/**
	 * Plugin Identifier, unique to bullmessage plugin.
	 *
	 * @var string
	 */
	private $name = 'bullmessage';

	/**
	 * Plugin settings.
	 *
	 * @var array
	 */
	protected $settings;

	/**
	 * StoreApi constructor.
	 */
	public function __construct() {
		$this->settings = get_option( 'bullmessage_settings' );
		if ( ! $this->settings ) {
			return;
		}

		$this->save_optin_consent();
		add_action( 'init', array( $this, 'register_store_api_routes' ) );
	}

	/**
	 * Register store API routes.
	 */
	public function register_store_api_routes() {
		$args = array(
			'endpoint'        => CheckoutSchema::IDENTIFIER,
			'namespace'       => 'bullmessage',
			'schema_callback' => function () {
				return array(
					'email' => array(
						'description' => __(
							'Subscribe to email marketing newsletter.',
							'bullmessage'
						),
						'type'        => array( 'boolean', 'null' ),
						'context'     => array(),
						'arg_options' => $this->optional_boolean_arg_options(),
					),
					'sms'   => array(
						'description' => __(
							'Subscribe to sms marketing newsletter.',
							'bullmessage'
						),
						'type'        => array( 'boolean', 'null' ),
						'context'     => array(),
						'arg_options' => $this->optional_boolean_arg_options(),
					),
				);
			},
		);

		woocommerce_store_api_register_endpoint_data( $args );
	}

	/**
	 * Save opt-in consent.
	 */
	public function save_optin_consent() {
		add_action(
			'woocommerce_store_api_checkout_update_order_from_request',
			function ( WC_Order $order, WP_REST_Request $request ) {
				$request_data  = $request['extensions'][ $this->name ];
				$sms_consent   = $request_data['sms'];
				$email_consent = $request_data['email'];

				$order->update_meta_data(
					'bm_sms_subscribe_consent_collected',
					true === $sms_consent ? 'true' : 'false'
				);
				// $order->update_meta_data('bm_email_subscribe_consent_collected', $email_consent === true ? 'true' : 'false't);

				$order->save();
			},
			10,
			2
		);
	}

	/**
	 * Validate if the value is a boolean or null. Make sure we accept null as false.
	 *
	 * @return array Array of options for the argument. See https://developer.wordpress.org/reference/functions/register_rest_route/#arguments
	 * @since  0.1.0
	 */
	protected function optional_boolean_arg_options() {
		return array(
			'validate_callback' => function ( $value ) {
				if ( ! is_null( $value ) && ! is_bool( $value ) ) {
					return new \WP_Error(
						'api-error',
						'value of type ' .
						gettype( $value ) .
						' was posted to the bullmessage opt-in callback'
					);
				}
				return true;
			},
			'sanitize_callback' => function ( $value ) {
				if ( is_bool( $value ) ) {
					return $value;
				}
				return false;
			},
		);
	}
}
