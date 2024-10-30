<?php
/**
 * BullMessage Checkout Shortcode
 *
 * Add consents to checkout based on shortcode
 *
 * @package BullMessage/
 * @since   0.1.0
 */

namespace Bullmessage;

class CheckoutShortcode {

	private $settings;

	/**
	 * Constructor method.
	 * Initializes the CheckoutShortcode class and sets the settings.
	 */
	public function __construct() {
		$this->settings = get_option( 'bullmessage_settings' );

		if ( ! empty( $this->settings )
			&& true === $this->settings['sms_subscribe_enabled']
		) {
			add_filter(
				'woocommerce_checkout_fields',
				array( $this, 'register_sms_checkbox' ),
				11
			);
			add_filter(
				'woocommerce_after_checkout_billing_form',
				array(
					$this,
					'add_compliance_text',
				)
			);
			add_action(
				'woocommerce_checkout_create_order',
				array( $this, 'save_sms_consent_to_order' ),
				10,
				2
			);
		}
	}

	/**
	 * Registers the SMS checkbox field to the WooCommerce checkout fields.
	 *
	 * @param array $fields The WooCommerce checkout fields.
	 * @return array The modified WooCommerce checkout fields.
	 */
	public function register_sms_checkbox( $fields ) {
		$fields['billing']['bm_sms_subscribe_consent_collected'] = array(
			'type'     => 'checkbox',
			'class'    => array( 'bm_checkbox_field' ),
			'label'    => $this->settings['sms_subscribe_consent_label'],
			'value'    => true,
			'default'  => 0,
			'required' => false,
		);

		return $fields;
	}

	/**
	 * Adds the compliance text to the WooCommerce checkout page.
	 */
	public function add_compliance_text() {
		echo esc_html( $this->settings['sms_subscribe_disclosure_text'] );
	}

	/**
	 * Saves the SMS consent to the order meta data.
	 *
	 * @param object $order The WooCommerce order object.
	 * @param array  $data The data from the checkout form.
	 */
	public function save_sms_consent_to_order( $order, $data ) {
		$order->update_meta_data(
			'bm_sms_subscribe_consent_collected',
			1 === $data['bm_sms_subscribe_consent_collected'] ? 'true' : 'false'
		);
	}
}
