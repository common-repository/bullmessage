<?php

namespace Bullmessage\Admin;

/**
 * Bullmessage Setup Class
 */
class Installer {

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 */
	public function __construct() {
		add_action( 'admin_enqueue_scripts', array( $this, 'register_scripts' ) );
		add_filter(
			'woocommerce_marketing_menu_items',
			array(
				$this,
				'register_menu_item',
			)
		);
		add_action( 'admin_menu', array( $this, 'fix_menu_item_url' ) );
	}

	/**
	 * Load all necessary dependencies.
	 *
	 * @since 0.1.0
	 */
	public function register_scripts() {
		if ( ! method_exists(
			'Automattic\WooCommerce\Admin\PageController',
			'is_admin_or_embed_page'
		)
			|| ! \Automattic\WooCommerce\Admin\PageController::is_admin_or_embed_page()
		) {
			return;
		}

		$script_path       = '/build/admin/index.js';
		$script_asset_path =
		dirname( BULLMESSAGE_MAIN_PLUGIN_FILE ) . '/build/admin/index.asset.php';
		$script_asset      = file_exists( $script_asset_path )
		? include $script_asset_path
		: array(
			'dependencies' => array(),
			'version'      => filemtime( $script_path ),
		);
		$script_url        = plugins_url( $script_path, BULLMESSAGE_MAIN_PLUGIN_FILE );

		wp_register_script(
			'bullmessage',
			$script_url,
			$script_asset['dependencies'],
			$script_asset['version'],
			true
		);

		wp_register_style(
			'bullmessage',
			plugins_url( '/build/admin/index.css', BULLMESSAGE_MAIN_PLUGIN_FILE ),
			// Add any dependencies styles may have, such as wp-components.
			array(),
			filemtime( dirname( BULLMESSAGE_MAIN_PLUGIN_FILE ) . '/build/admin/index.css' )
		);

		wp_enqueue_script( 'bullmessage' );
		wp_enqueue_style( 'bullmessage' );
	}

	public function register_menu_item( $marketing_pages ) {
		$marketing_pages[] = array(
			'id'         => 'woocommerce-bullmessage',
			'title'      => __( 'BullMessage', 'bullmessage' ),
			'path'       => '/bullmessage_settings',
			'parent'     => 'woocommerce-marketing',
			'capability' => 'manage_woocommerce',
			'nav_args'   => array(
				'parent' => 'woocommerce-marketing',
				'order'  => 10,
			),
		);

		return $marketing_pages;
	}

	public function fix_menu_item_url() {
		global $submenu;
		if ( ! isset( $submenu['woocommerce-marketing'] ) ) {
			return;
		}
		foreach ( $submenu['woocommerce-marketing'] as &$item ) {
			if ( 'BullMessage' !== $item[0] ) {
				continue;
			}
			if ( 0 === strpos( $item[2], 'wc-admin' ) ) {
				$item[2] = 'admin.php?page=' . $item[2];
			}
		}
	}
}
