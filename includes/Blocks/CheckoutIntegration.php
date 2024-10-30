<?php
/**
 * BullMessage Checkout Integration
 *
 * CheckoutIntegration used to process consents at checkout and inject scripts to checkout page in order to track events
 *
 * @package BullMessage/
 * @since   0.1.0
 */

namespace Bullmessage\Blocks;

/**
 * Class CheckoutIntegration
 *
 * This class implements the IntegrationInterface for the WooCommerce Blocks plugin.
 * It provides integration functionality for the checkout block.
 */
class CheckoutIntegration implements
	\Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface {

	/**
	 * Holds BullMessage settings.
	 *
	 * @var mixed $settings The settings for the checkout integration.
	 */
	protected $settings;

	/**
	 * Class CheckoutIntegration
	 *
	 * This class represents the integration of the BullMessage plugin with the WooCommerce checkout process.
	 */
	public function __construct() {
		$this->settings = get_option( 'bullmessage_settings' );
	}

	/**
	 * Retrieves the name of the checkout block.
	 *
	 * @return string The name of the checkout block.
	 */
	public function get_name() {
		return 'bullmessage_checkout_block';
	}

	/**
	 * Initializes the CheckoutIntegration class following IntegrationInterface
	 */
	public function initialize() {
	}

	/**
	 * Returns an array of script handles to enqueue in the frontend context.
	 *
	 * Note: first element in array matches namespace/block-name from block
	 * name in block.json e.g. bullmessage/bullmessage-consent-block matches to
	 * bullmessage-bullmessage-consent-block. Mismatch gets caught and very hard to
	 * identify.
	 * Usually, it you see page loaded by checkout component is still under loading skeleton that means the script name is not found
	 *
	 * @return string[]
	 */
	public function get_script_handles() {
		return array( 'bullmessage-bullmessage-consent-block-view-script' );
	}

	/**
	 * Returns an array of script handles to enqueue in the editor context.
	 *
	 * Note: first element in array matches namespace/block-name from block
	 * name in block.json e.g. bullmessage/bullmessage-consent-block matches to
	 * bullmessage-bullmessage-consent-block. Mismatch gets caught and very hard to
	 * identify.
	 * Usually, it you see page loaded by checkout component is still under loading skeleton that means the script name is not found
	 *
	 * @return string[]
	 */
	public function get_editor_script_handles() {
		return array( 'bullmessage-bullmessage-consent-block-editor-script' );
	}

	/**
	 * Returns an array of key, value pairs of data made available to the block on the client side.
	 *
	 * @return array
	 */
	public function get_script_data() {
		return array(
			'emailEnabled'             => $this->get_email_enabled(),
			'emailConsentText'         => $this->get_email_consent_text(),
			'smsEnabled'               => $this->get_sms_enabled(),
			'smsConsentText'           => $this->get_sms_consent_text(),
			'smsConsentDisclosureText' => $this->get_sms_consent_disclosure_text(),
		);
	}

	/**
	 * Determines whether email consent collection is enabled in the integration settings.
	 *
	 * @return false|mixed
	 */
	public function get_email_enabled() {
		return $this->settings['email_subscribe_enabled'] ?? false;
	}

	/**
	 * Retrieves the email consent text from the settings.
	 *
	 * @return mixed
	 */
	public function get_email_consent_text() {
		return $this->settings['email_subscribe_consent_label'] ??
		__(
			'Sign me up to receive email updates and news (optional)',
			'bullmessage'
		);
	}

	/**
	 * Determines whether SMS consent collection is enabled in the integration settings.
	 *
	 * @return false|mixed
	 */
	public function get_sms_enabled() {
		return $this->settings['sms_subscribe_enabled'] ?? false;
	}

	/**
	 * Retrieves the SMS consent text from the settings.
	 *
	 * @return mixed
	 */
	public function get_sms_consent_text() {
		return $this->settings['sms_subscribe_consent_label'] ??
		__(
			'Sign me up to receive SMS updates and news (optional)',
			'bullmessage'
		);
	}

	/**
	 * Retrieves the SMS consent disclosure text from the settings.
	 *
	 * @return mixed
	 */
	public function get_sms_consent_disclosure_text() {
		return $this->settings['sms_subscribe_disclosure_text'] ??
		__(
			'By checking this box and entering your phone number above, you consent to receive marketing text messages (such as [promotion codes] and [cart reminders]) from [company name] at the number provided, including messages sent by autodialer. Consent is not a condition of any purchase. Message and data rates may apply. Message frequency varies. You can unsubscribe at any time by replying STOP or clicking the unsubscribe link (where available) in one of our messages. View our Privacy Policy [link] and Terms of Service [link]',
			'bullmessage'
		);
	}

	/**
	 * Get the file modified time as a cache buster if we're in dev mode.
	 *
	 * @param  string $file Local path to the file.
	 * @return string The cache buster value to use for the given file.
	 */
	protected function get_file_version( $file ) {
		if ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG && file_exists( $file ) ) {
			return filemtime( $file );
		}

		return \BullMessage::get_version();
	}
}
