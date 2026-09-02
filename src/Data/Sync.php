<?php
/**
 * Keeps the report table in step with BigCommerce orders.
 *
 * @package SalesByStateReportForBigCommerce
 */

namespace SBSBC\Data;

use SBSBC\Install\Schema;
use SBSBC\Regions;

defined( 'ABSPATH' ) || exit;

/**
 * Writes one row per order.
 *
 * Orders live on the BigCommerce API, not in WordPress. Rows are written
 * during the one-off import and by the recent-order poll. Refunds are not
 * modelled: a refunded order is included or excluded by the status filter
 * like any other order.
 */
class Sync {

	/**
	 * Register hooks.
	 *
	 * Live order writes happen through Scheduler::sync_recent(); BigCommerce
	 * for WordPress does not fire a local order-save hook for cloud orders.
	 *
	 * @return void
	 */
	public function register() {
	}

	/**
	 * Insert or update the row for one order.
	 *
	 * @param array|string|int $order Order array or ID.
	 * @return bool
	 */
	public function upsert( $order ) {
		global $wpdb;

		if ( is_string( $order ) || is_numeric( $order ) ) {
			$order = $this->load_order( (string) $order );
		}

		if ( ! is_array( $order ) ) {
			return false;
		}

		$row = self::build_row( $order );

		if ( ! $row ) {
			return false;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return false !== $wpdb->replace(
			Schema::table(),
			$row,
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%f', '%f', '%f', '%f' )
		);
	}

	/**
	 * Remove the row for an order.
	 *
	 * @param string $order_id Order ID.
	 * @return void
	 */
	public function delete( $order_id ) {
		global $wpdb;

		$order_id = (string) $order_id;

		if ( ! $order_id ) {
			return;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete( Schema::table(), array( 'order_id' => $order_id ), array( '%s' ) );
	}

	/**
	 * Build the row for a BigCommerce V2 order.
	 *
	 * Money is stored as decimals on the API and in the report table.
	 * Net Sales is total including tax, minus tax, minus shipping excluding tax.
	 * Gross Sales is total including tax.
	 *
	 * @param array $order Order.
	 * @return array<string,mixed>|false
	 */
	public static function build_row( $order ) {
		if ( ! is_array( $order ) ) {
			return false;
		}

		$order_id = isset( $order['id'] ) ? (string) $order['id'] : '';

		if ( ! $order_id ) {
			return false;
		}

		$billing  = self::address_of( $order, 'billing_address' );
		$shipping = self::address_of( $order, 'shipping_address' );

		if ( '' === $shipping['country'] ) {
			$shipping = $billing;
		}

		$billing_state  = Regions::normalize_state( $billing['country'], $billing['state'] );
		$shipping_state = Regions::normalize_state( $shipping['country'], $shipping['state'] );

		$total           = self::money_of( $order['total_inc_tax'] ?? 0 );
		$tax             = self::money_of( $order['total_tax'] ?? 0 );
		$shipping_total  = self::money_of( $order['shipping_cost_ex_tax'] ?? 0 );
		$status          = self::normalize_status( $order );
		$created         = self::normalize_datetime( $order['date_created'] ?? null );
		$paid            = self::is_paid_status( $status ) ? $created : null;
		$currency        = strtoupper( substr( (string) ( $order['currency_code'] ?? '' ), 0, 3 ) );

		if ( ! $currency ) {
			$currency = strtoupper( substr( (string) get_option( 'bigcommerce_currency_code', 'USD' ), 0, 3 ) );
		}

		return array(
			'order_id'         => substr( $order_id, 0, 64 ),
			'status'           => substr( $status, 0, 32 ),
			'date_created'     => $created ? $created : '0000-00-00 00:00:00',
			'date_paid'        => $paid ? $paid : null,
			'billing_country'  => $billing['country'],
			'billing_state'    => substr( $billing_state, 0, 50 ),
			'shipping_country' => $shipping['country'],
			'shipping_state'   => substr( $shipping_state, 0, 50 ),
			'currency'         => $currency,
			'total_sales'      => $total,
			'tax_total'        => $tax,
			'shipping_total'   => $shipping_total,
			'net_total'        => $total - $tax - $shipping_total,
		);
	}

	/**
	 * Load one order from the API.
	 *
	 * @param string $order_id Order ID.
	 * @return array|null
	 */
	private function load_order( $order_id ) {
		$order_id = preg_replace( '/[^0-9]/', '', (string) $order_id );

		if ( ! $order_id || ! Client::ready() ) {
			return null;
		}

		$client = new Client();
		$result = $client->get( '/orders/' . $order_id );

		return ( ! is_wp_error( $result ) && is_array( $result ) ) ? $result : null;
	}

	/**
	 * Country and state from a V2 address object.
	 *
	 * @param array  $order Order.
	 * @param string $key   billing_address or shipping_address.
	 * @return array{country:string,state:string}
	 */
	private static function address_of( array $order, $key ) {
		$address = $order[ $key ] ?? null;

		if ( 'shipping_address' === $key && ! self::is_address( $address ) ) {
			$address = $order['shipping_addresses'] ?? null;

			if ( is_array( $address ) && isset( $address[0] ) && self::is_address( $address[0] ) ) {
				$address = $address[0];
			}
		}

		if ( ! self::is_address( $address ) ) {
			return array(
				'country' => '',
				'state'   => '',
			);
		}

		$country = strtoupper( substr( (string) ( $address['country_iso2'] ?? '' ), 0, 2 ) );

		if ( ! preg_match( '/^[A-Z]{2}$/', $country ) ) {
			$country = self::country_code_from_name( (string) ( $address['country'] ?? '' ) );
		}

		return array(
			'country' => $country,
			'state'   => (string) ( $address['state'] ?? '' ),
		);
	}

	/**
	 * Whether a value looks like a V2 address rather than a resource link.
	 *
	 * @param mixed $address Address.
	 * @return bool
	 */
	private static function is_address( $address ) {
		return is_array( $address ) && ( isset( $address['country_iso2'] ) || isset( $address['country'] ) || isset( $address['state'] ) );
	}

	/**
	 * Map a country display name onto an ISO code.
	 *
	 * @param string $name Country name.
	 * @return string
	 */
	private static function country_code_from_name( $name ) {
		$map = array(
			'united states'            => 'US',
			'united states of america' => 'US',
			'usa'                      => 'US',
			'canada'                   => 'CA',
			'united kingdom'           => 'GB',
			'great britain'            => 'GB',
			'england'                  => 'GB',
		);

		$key = strtolower( trim( (string) $name ) );

		return $map[ $key ] ?? '';
	}

	/**
	 * Map a V2 order onto a report status key.
	 *
	 * @param array $order Order.
	 * @return string
	 */
	private static function normalize_status( array $order ) {
		$by_id = self::status_keys();
		$id    = isset( $order['status_id'] ) ? (int) $order['status_id'] : -1;

		if ( isset( $by_id[ $id ] ) ) {
			return $by_id[ $id ];
		}

		$status = sanitize_key( (string) ( $order['status'] ?? '' ) );

		if ( 'canceled' === $status ) {
			$status = 'cancelled';
		}

		return $status;
	}

	/**
	 * V2 status_id => report key.
	 *
	 * @return array<int,string>
	 */
	private static function status_keys() {
		return array(
			0  => 'incomplete',
			1  => 'pending',
			2  => 'shipped',
			3  => 'partially_shipped',
			4  => 'refunded',
			5  => 'cancelled',
			6  => 'declined',
			7  => 'awaiting_payment',
			8  => 'awaiting_pickup',
			9  => 'awaiting_shipment',
			10 => 'completed',
			11 => 'awaiting_fulfillment',
			12 => 'awaiting_manual_verification',
			13 => 'disputed',
			14 => 'partially_refunded',
		);
	}

	/**
	 * Statuses that represent a sale for the year filter.
	 *
	 * @param string $status Status key.
	 * @return bool
	 */
	private static function is_paid_status( $status ) {
		return in_array(
			$status,
			array(
				'completed',
				'shipped',
				'partially_shipped',
				'awaiting_pickup',
				'awaiting_shipment',
				'awaiting_fulfillment',
				'refunded',
				'partially_refunded',
				'disputed',
			),
			true
		);
	}

	/**
	 * Parse a V2 money string.
	 *
	 * @param mixed $value Amount.
	 * @return float
	 */
	private static function money_of( $value ) {
		return round( (float) $value, 2 );
	}

	/**
	 * Normalise a unix timestamp or datetime string.
	 *
	 * @param mixed $value Datetime.
	 * @return string|null
	 */
	private static function normalize_datetime( $value ) {
		if ( empty( $value ) || '0000-00-00 00:00:00' === $value ) {
			return null;
		}

		if ( is_numeric( $value ) ) {
			return gmdate( 'Y-m-d H:i:s', (int) $value );
		}

		$ts = strtotime( (string) $value );

		return $ts ? gmdate( 'Y-m-d H:i:s', $ts ) : null;
	}
}
