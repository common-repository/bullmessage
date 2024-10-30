<?php
/**
 * BullMessage API
 *
 * Handles BM-API endpoint requests
 *
 * @package BullMessage/
 * @since   0.1.0
 */

namespace Bullmessage;

use WP_REST_Server;
use WP_REST_Request;

class Api {

	const VERSION                      = '0.1.0';
	const BULLMESSAGE_BASE_URL         = 'bullmessage/v1';
	const SETTINGS_ENDPOINT            = 'settings';
	const SETTINGS_DEACTIVATE_ENDPOINT = 'settings/deactivate';
	const ACCOUNT_ENDPOINT             = 'account';
	const VERSION_ENDPOINT             = 'version';

	// HTTP CODES
	const STATUS_CODE_AUTHENTICATION_ERROR  = 401;
	const STATUS_CODE_AUTHORIZATION_ERROR   = 403;
	const STATUS_CODE_INTERNAL_SERVER_ERROR = 500;

	const PERMISSION_READ       = 'read';
	const PERMISSION_WRITE      = 'write';
	const PERMISSION_READ_WRITE = 'read_write';
	const PERMISSION_METHOD_MAP = array(
		self::PERMISSION_READ       => array( 'GET' ),
		self::PERMISSION_WRITE      => array( 'POST' ),
		self::PERMISSION_READ_WRITE => array( 'GET', 'POST' ),
	);

	/**
	 * Check if there is a new version of the BullMessage plugin available for download. WordPress stores this info in the
	 * database as an object with the following properties:
	 *   - last_checked (int) Unix timestamp when request was last made to WordPress server.
	 *   - response (array) Contains plugin data with updates available stored by key e.g.'bullmessage/bullmessage.php'.
	 *   - no_update (array) Contains plugin data for plugins without updates stored by key e.g. 'bullmessage/bullmessage.php'.
	 *   - translations (array) Not relevant to us here.
	 *
	 * The response and no_update arrays are mutually exclusive so we can see if BullMessage's plugin has been checked for
	 * and if it's in the response array.
	 *
	 * See wp_update_plugins function in `wordpress/wp-includes/update.php` for more information on how this is set.
	 *
	 * @param  stdClass $plugins_transient Optional arg if the transient value is already in scope e.g. during update check.
	 * @return bool
	 */
	public static function is_most_recent_version(
		stdClass $plugins_transient = null
	) {
		if ( ! $plugins_transient ) {
			$plugins_transient = get_site_transient( 'update_plugins' );
		}
		// True if response property isn't set, we don't want to alert on a false positive here.
		if ( ! isset( $plugins_transient->response ) ) {
			return true;
		}
		// True if BullMessage plugin is not in the response array meaning no update available.
		return ! array_key_exists(
			BULLMESSAGE_BASENAME,
			$plugins_transient->response
		);
	}

	/**
	 * Build payload for version endpoint and webhooks.
	 *
	 * @param  bool $is_updating Short circuit checking version if plugin is being updated, we know it's most recent.
	 * @return array
	 */
	public static function build_version_payload( $is_updating = false ) {
		return array(
			'plugin_version'         => self::VERSION,
			'is_most_recent_version' =>
			$is_updating || self::is_most_recent_version(),
		);
	}
}

/**
 * Validate incoming requests to custom endpoints.
 *
 * @param  WP_REST_Request $request Incoming request object.
 * @return bool|WP_Error True if validation succeeds, otherwise WP_Error to be handled by rest server.
 */
function credentials_guard( WP_REST_Request $request ) {
	$consumer_key    = $request->get_param( 'consumer_key' );
	$consumer_secret = $request->get_param( 'consumer_secret' );
	if ( empty( $consumer_key ) || empty( $consumer_secret ) ) {
		return new WP_Error(
			'bullmessage_missing_key_secret',
			'One or more of consumer key and secret are missing.',
			array( 'status' => Api::STATUS_CODE_AUTHENTICATION_ERROR )
		);
	}

	global $wpdb;
	// this is stored as a hash so we need to query on the hash.
	$key  = hash_hmac( 'sha256', $consumer_key, 'wc-api' );
	$user = $wpdb->get_row(
		$wpdb->prepare(
			"
    SELECT consumer_key, consumer_secret, permissions
    FROM {$wpdb->prefix}woocommerce_api_keys
    WHERE consumer_key = %s
     ",
			$key
		)
	);
	// User query lookup on consumer key can return null or false.
	if ( ! $user ) {
		return new WP_Error(
			'bullmessage_cannot_authentication',
			'Cannot authenticate with provided credentials.',
			array( 'status' => 401 )
		);
	}
	// User does not have proper permissions.
	if ( ! in_array(
		$request->get_method(),
		Api::PERMISSION_METHOD_MAP[ $user->permissions ],
		true
	)
	) {
		return new WP_Error(
			'bullmessage_improper_permissions',
			'Improper permissions to access this resource.',
			array( 'status' => Api::STATUS_CODE_AUTHORIZATION_ERROR )
		);
	}
	// Success!
	if ( $user->consumer_secret === $consumer_secret ) {
		return true;
	}
	// Consumer secret didn't match or some other issue authenticating.
	return new WP_Error(
		'bullmessage_invalid_authentication',
		'Invalid authentication.',
		array( 'status' => Api::STATUS_CODE_AUTHENTICATION_ERROR )
	);
}

/**
 * Handle GET request to /bullmessage/v1/account. Returns the current connected account
 *
 * @return array
 */
function get_account() {
	$settings = get_option( 'bullmessage_settings', false );
	if ( ! $settings ) {
		return false;
	}
	return $settings['account'];
}

/**
 * Handle GET request to /bullmessage/v1/version. Returns the current version and if
 * the installed version is the most recenft available in the plugin directory.
 *
 * @return array
 */
function get_plugin_version() {
	return Api::build_version_payload();
}

/**
 * Handle POST request to /bullmessage/v1/settings and update plugin settings.
 *
 * @param  WP_REST_Request $request Incoming request object.
 * @return bool|mixed|void|WP_Error
 */
function update_bullmessage_settings( WP_REST_Request $request ) {
	$body = json_decode( $request->get_body(), $assoc = true );
	if ( ! $body ) {
		return new WP_Error(
			'bullmessage_empty_body',
			'Body of request cannot be empty.',
			array( 'status' => 400 )
		);
	}

	// Cleanup credentials before save it to database
	unset( $body['consumer_key'] );
	unset( $body['consumer_secret'] );

	$options = get_option( 'bullmessage_settings' );
	if ( ! $options ) {
		$options = array();
	}

	$updated_options = array_replace( $options, $body );
	$is_update       = (bool) array_diff_assoc( $options, $updated_options );
	// If there is no change between existing and new settings `update_option` returns false. Want to distinguish
	// between that scenario and an actual problem when updating the plugin options.
	if ( ! update_option( 'bullmessage_settings', $updated_options )
		&& $is_update
	) {
		return new WP_Error(
			'bullmessage_update_failed',
			'Options update failed.',
			array(
				'status'  => Api::STATUS_CODE_INTERNAL_SERVER_ERROR,
				'options' => get_option( 'bullmessage_settings' ),
			)
		);
	}

	// Return plugin version info so this can be saved in BullMessage when setting up integration for the first time.
	return array_merge( $updated_options, Api::build_version_payload() );
}

/**
 * Handle DELETE request to /bullmessage/v1/settings and delete plugin settings.
 *
 * @return bool|mixed|void|WP_Error
 */
function deactivate_bullmessage() {
	// Deactivate the BullMessage plugin
	deactivate_plugins( 'bullmessage/bullmessage.php' );
}

/**
 * Handle GET request to /bullmessage/v1/settings and return options set for plugin.
 *
 * @return array BullMessage plugin settings.
 */
function get_bullmessage_settings() {
	return get_option( 'bullmessage_settings' );
}

add_action(
	'rest_api_init',
	function () {
		register_rest_route(
			Api::BULLMESSAGE_BASE_URL,
			Api::VERSION_ENDPOINT,
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => function () {
					return get_plugin_version();
				},
				'permission_callback' => '__return_true',
			)
		);
		register_rest_route(
			Api::BULLMESSAGE_BASE_URL,
			Api::ACCOUNT_ENDPOINT,
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => function () {
					return get_account();
				},
				'permission_callback' => '__return_true',
			)
		);
		register_rest_route(
			Api::BULLMESSAGE_BASE_URL,
			Api::SETTINGS_ENDPOINT,
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => function ( WP_REST_Request $request ) {
						return update_bullmessage_settings( $request );
					},
					'permission_callback' => function ( WP_REST_Request $request ) {
						return credentials_guard( $request );
					},
				),
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => function () {
						return get_bullmessage_settings();
					},
					'permission_callback' => function ( WP_REST_Request $request ) {
						return credentials_guard( $request );
					},
				),
			)
		);
		register_rest_route(
			Api::BULLMESSAGE_BASE_URL,
			Api::SETTINGS_DEACTIVATE_ENDPOINT,
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => function () {
						return deactivate_bullmessage();
					},
					'permission_callback' => function ( WP_REST_Request $request ) {
						return credentials_guard( $request );
					},
				),
			)
		);
	}
);
