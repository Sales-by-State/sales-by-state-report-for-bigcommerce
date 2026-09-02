<?php
/**
 * Locates BigCommerce orders through the API.
 *
 * @package SalesByStateReportForBigCommerce
 */

namespace SBSBC\Data;

defined( 'ABSPATH' ) || exit;

/**
 * Pages orders from the BigCommerce V2 Orders API.
 */
class OrderSource {

	/**
	 * Total number of orders on the BigCommerce store.
	 *
	 * @return int
	 */
	public static function count() {
		$cached = get_transient( 'sbsbc_order_count' );

		if ( false !== $cached ) {
			return (int) $cached;
		}

		if ( ! Client::ready() ) {
			set_transient( 'sbsbc_order_count', 0, MINUTE_IN_SECONDS );
			return 0;
		}

		$client = new Client();
		$result = $client->get( '/orders/count' );

		if ( is_wp_error( $result ) ) {
			return 0;
		}

		$total = isset( $result['count'] ) ? (int) $result['count'] : 0;

		set_transient( 'sbsbc_order_count', $total, MINUTE_IN_SECONDS );

		return $total;
	}

	/**
	 * One page of orders, oldest first so the cursor is stable.
	 *
	 * @param int $page  Page number (1-based).
	 * @param int $limit Page size.
	 * @return array{orders:array,count:int}|\WP_Error
	 */
	public static function page( $page, $limit ) {
		if ( ! Client::ready() ) {
			return new \WP_Error(
				'sbsbc_disconnected',
				__( 'Connect a BigCommerce store before importing orders.', 'sales-by-state-report-for-bigcommerce' )
			);
		}

		$page  = max( 1, (int) $page );
		$limit = max( 1, min( 50, (int) $limit ) );

		$client = new Client();
		$path   = sprintf(
			'/orders?page=%d&limit=%d&sort=id:asc',
			$page,
			$limit
		);

		$result = $client->get( $path );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$orders = array();

		if ( is_array( $result ) ) {
			foreach ( $result as $row ) {
				if ( is_array( $row ) && ! empty( $row['id'] ) ) {
					$orders[] = $row;
				}
			}
		}

		return array(
			'orders' => $orders,
			'count'  => self::count(),
		);
	}

	/**
	 * Newest orders, used to keep the table current after the first import.
	 *
	 * @param int $limit Page size.
	 * @return array
	 */
	public static function recent( $limit = 50 ) {
		if ( ! Client::ready() ) {
			return array();
		}

		$limit  = max( 1, min( 50, (int) $limit ) );
		$client = new Client();
		$result = $client->get( '/orders?page=1&limit=' . $limit . '&sort=id:desc' );

		if ( is_wp_error( $result ) || ! is_array( $result ) ) {
			return array();
		}

		$orders = array();

		foreach ( $result as $row ) {
			if ( is_array( $row ) && ! empty( $row['id'] ) ) {
				$orders[] = $row;
			}
		}

		return $orders;
	}
}
