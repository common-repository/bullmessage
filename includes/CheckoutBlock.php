<?php
/**
 * BullMessage Checkout Block
 *
 * Add hooks for Checkout page based on block API
 *
 * @package BullMessage/
 * @since   0.1.0
 */

namespace Bullmessage;

use Bullmessage\Blocks\StoreApi;
use Bullmessage\Blocks\CheckoutIntegration;

class CheckoutBlock {

	public function __construct() {
		add_action(
			'woocommerce_blocks_loaded',
			function () {
				new StoreApi();
			}
		);
		add_action(
			'init',
			function () {
				register_block_type( dirname( __DIR__ ) . '/build/consent-block' );
			}
		);
		add_action(
			'woocommerce_blocks_checkout_block_registration',
			function ( $integration_registry ) {
				$integration_registry->register( new CheckoutIntegration() );
			},
			10,
			1
		);
	}
}
