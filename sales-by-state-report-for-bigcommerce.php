<?php
/**
 * Plugin Name:          Sales by State Report for BigCommerce
 * Plugin URI:           https://salesbystate.com/
 * Description:          See a yearly breakdown of BigCommerce sales by state / county / province for a given country, filterable by order status.
 * Version:              1.0.0
 * Author:               Rodolfo Melogli
 * Author URI:           https://www.businessbloomer.com/
 * Developer:            Rodolfo Melogli
 * Developer URI:        https://www.businessbloomer.com/
 * Text Domain:          sales-by-state-report-for-bigcommerce
 * Domain Path:          /languages
 * Requires at least:    6.4
 * Requires PHP:         7.4
 * Requires Plugins:     bigcommerce
 * License:              GPL-2.0-or-later
 * License URI:          https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package SalesByStateReportForBigCommerce
 * @copyright 2026 Rodolfo Melogli
 */

defined( 'ABSPATH' ) || exit;

define( 'SBSBC_VERSION', '1.0.0' );
define( 'SBSBC_FILE', __FILE__ );
define( 'SBSBC_DIR', plugin_dir_path( __FILE__ ) );
define( 'SBSBC_URL', plugin_dir_url( __FILE__ ) );

spl_autoload_register(
	function ( $class_name ) {
		$prefix = 'SBSBC\\';

		if ( 0 !== strpos( $class_name, $prefix ) ) {
			return;
		}

		$relative = substr( $class_name, strlen( $prefix ) );
		$path     = SBSBC_DIR . 'src/' . str_replace( '\\', '/', $relative ) . '.php';

		if ( is_readable( $path ) ) {
			require_once $path;
		}
	}
);

add_action(
	'plugins_loaded',
	function () {
		if ( ! defined( 'BIGCOMMERCE_PHP_MINIMUM_VERSION' ) && ! class_exists( '\BigCommerce\Plugin' ) ) {
			add_action(
				'admin_notices',
				function () {
					if ( ! current_user_can( 'activate_plugins' ) ) {
						return;
					}

					printf(
						'<div class="notice notice-error"><p>%s</p></div>',
						esc_html__( 'Sales by State Report for BigCommerce requires BigCommerce to be installed and active.', 'sales-by-state-report-for-bigcommerce' )
					);
				}
			);

			return;
		}

		SBSBC\Plugin::instance()->init();
	},
	20
);

register_activation_hook(
	SBSBC_FILE,
	function () {
		require_once SBSBC_DIR . 'src/Install/Schema.php';
		SBSBC\Install\Schema::install();
	}
);

register_deactivation_hook(
	SBSBC_FILE,
	function () {
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( 'sbsbc_backfill_batch', array(), 'sales-by-state-report-for-bigcommerce' );
			as_unschedule_all_actions( 'sbsbc_sync_recent', array(), 'sales-by-state-report-for-bigcommerce' );
		}

		wp_unschedule_hook( 'sbsbc_backfill_batch' );
		wp_unschedule_hook( 'sbsbc_sync_recent' );
	}
);
