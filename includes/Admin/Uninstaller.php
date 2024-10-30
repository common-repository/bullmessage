<?php

namespace Bullmessage\Admin;

use WC_Data_Store;

/**
 * Bullmessage Uninstall Class
 *
 * @since 0.1.0
 */
class Uninstaller {

	/**
	 * Deletes the Bullmessage plugin options.
	 *
	 * @since 0.1.0
	 */
	public function delete_options() {
		delete_option( 'bullmessage_settings' );
	}

	/**
	 * Deletes the Bullmessage webhooks.
	 *
	 * @since 0.1.0
	 */
	public function delete_webhooks() {
		$webhook_data_store = WC_Data_Store::load( 'webhook' );
		$webhooks_by_status = $webhook_data_store->get_count_webhooks_by_status();
		$count              = array_sum( $webhooks_by_status );

		if ( 0 === $count ) {
			return;
		}

		// We can only get IDs and there's not a way to search by delivery url which is the only way to identify
		// a webhook created by BullMessage. We'll have to iterate no matter what so might as well get them all.
		$webhook_ids = $webhook_data_store->get_webhooks_ids();

		foreach ( $webhook_ids as $webhook_id ) {
			$webhook = wc_get_webhook( $webhook_id );
			if ( ! $webhook ) {
				continue;
			}

			if ( false !== strpos(
				$webhook->get_delivery_url(),
				'/v1/woocommerce_integrations/webhook'
			)
			) {
				$webhook_data_store->delete( $webhook );
			}
		}
	}
}
