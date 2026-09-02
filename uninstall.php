<?php
/**
 * Removes the plugin's data when it is deleted.
 *
 * @package SalesByStateReportForBigCommerce
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

$sbsbc_options = array(
	'sbsbc_db_version',
	'sbsbc_backfill_cursor',
	'sbsbc_year_start',
);

foreach ( $sbsbc_options as $sbsbc_option ) {
	delete_option( $sbsbc_option );
}

delete_transient( 'sbsbc_order_count' );
delete_transient( 'sbsbc_store_country' );
delete_transient( 'sbsbc_currency_code' );

if ( is_multisite() ) {
	foreach ( $sbsbc_options as $sbsbc_option ) {
		delete_site_option( $sbsbc_option );
	}

	delete_site_transient( 'sbsbc_order_count' );
	delete_site_transient( 'sbsbc_store_country' );
	delete_site_transient( 'sbsbc_currency_code' );
}

// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}sbsbc_order_state" );

if ( function_exists( 'as_unschedule_all_actions' ) ) {
	as_unschedule_all_actions( 'sbsbc_backfill_batch', array(), 'sales-by-state-report-for-bigcommerce' );
	as_unschedule_all_actions( 'sbsbc_sync_recent', array(), 'sales-by-state-report-for-bigcommerce' );
}

wp_unschedule_hook( 'sbsbc_backfill_batch' );
wp_unschedule_hook( 'sbsbc_sync_recent' );
