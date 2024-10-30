<?php
/**
 * Plugin Name: SMS Marketing by BullMessage
 * Plugin URI: https://wordpress.org/plugins/bullmessage/
 * Description: BullMessage plugin integrates your store with BullMessage, syncing customers for efficient campaigns and automated workflows to enhance communication.
 * Version: 0.2.0
 * Author: BullMessage, Inc
 * Author URI: https://bullmessage.com
 * Requires at least: 4.4
 * Requires PHP: 7.0
 * Tested up to: 6.5
 * WC requires at least: 2.0
 * WC tested up to: 5.5.2
 * Text Domain: bullmessage
 * Domain Path: /languages
 *
 * License: GPLv3 or later
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 *
 * @package BullMessage
 */

defined( 'ABSPATH' ) || exit();

if ( ! defined( 'BULLMESSAGE_MAIN_PLUGIN_FILE' ) ) {
	define( 'BULLMESSAGE_MAIN_PLUGIN_FILE', __FILE__ );
}
if ( ! defined( 'BULLMESSAGE_BASENAME' ) ) {
	define( 'BULLMESSAGE_BASENAME', plugin_basename( __FILE__ ) );
}
require_once plugin_dir_path( __FILE__ ) . '/vendor/autoload_packages.php';

use Bullmessage\Admin\Installer;
use Bullmessage\Admin\Uninstaller;
use Bullmessage\Api;
use Bullmessage\CheckoutBlock;
use Bullmessage\CheckoutShortcode;

// phpcs:disable WordPress.Files.FileName

add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
				'cart_checkout_blocks',
				__FILE__,
				true
			);
		}
	}
);

add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
				'custom_order_tables',
				__FILE__,
				true
			);
		}
	}
);

/**
 * WooCommerce fallback notice.
 *
 * @since 0.1.0
 */
function bullmessage_missing_wc_notice() {
	echo '<div class="error"><p><strong>' .
	sprintf(
		/* translators: %s WC download URL link. */
		esc_html__(
			'Bullmessage requires WooCommerce to be installed and active. You can download %s here.',
			'bullmessage'
		),
		'<a href="https://woo.com/" target="_blank">WooCommerce</a>'
	) .
	'</strong></p></div>';
}

register_activation_hook( __FILE__, 'bullmessage_activate' );

register_deactivation_hook( __FILE__, 'bullmessage_deactivate' );

/**
 * Activation hook.
 *
 * @since 0.1.0
 */
function bullmessage_activate() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'bullmessage_missing_wc_notice' );
		return;
	}
}

function bullmessage_deactivate() {
	$uninstaller = new Uninstaller();
	$uninstaller->delete_webhooks();
	$uninstaller->delete_options();
}

if ( ! class_exists( 'BullMessage' ) ) :
	/**
	 * The bullmessage class.
	 */
	class BullMessage {

		/**
		 * The plugin version.
		 *
		 * @var string
		 */
		public static $version = '0.2.0';

		/**
		 * This class instance.
		 *
		 * @var \BullMessage single instance of this class.
		 */
		private static $instance;

		/**
		 * Get plugin version number.
		 *
		 * @since  0.1.0
		 * @static
		 * @return string
		 */
		public static function get_version() {
				return self::$version;
		}

		/**
		 * Constructor.
		 */
		public function __construct() {
			if ( is_admin() ) {
					new Installer();
			}
			new Api();
			new CheckoutShortcode();

			// Enable checkout block integration only if block API is enabled
			if ( class_exists(
				\Automattic\WooCommerce\StoreApi\Schemas\V1\CheckoutSchema::class
			)
			) {
					new CheckoutBlock();
			}

			do_action( 'bullmessage_loaded' );
		}

		/**
		 * Cloning is forbidden.
		 */
		public function __clone() {
				wc_doing_it_wrong(
					__FUNCTION__,
					__( 'Cloning is forbidden.', 'bullmessage' ),
					$this->version
				);
		}

		/**
		 * Unserializing instances of this class is forbidden.
		 */
		public function __wakeup() {
				wc_doing_it_wrong(
					__FUNCTION__,
					__(
						'Unserializing instances of this class is forbidden.',
						'bullmessage'
					),
					$this->version
				);
		}

		/**
		 * Gets the main instance.
		 *
		 * Ensures only one instance can be loaded.
		 *
		 * @return \bullmessage
		 */
		public static function instance() {
			if ( null === self::$instance ) {
					self::$instance = new self();
			}

			return self::$instance;
		}
	}
endif;

add_action( 'plugins_loaded', 'bullmessage_init', 10 );

/**
 * Initialize the plugin.
 *
 * @since 0.1.0
 */
function bullmessage_init() {
	load_plugin_textdomain(
		'bullmessage',
		false,
		plugin_basename( __DIR__ ) . '/languages'
	);

	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'bullmessage_missing_wc_notice' );
		return;
	}

	BullMessage::instance();
}
