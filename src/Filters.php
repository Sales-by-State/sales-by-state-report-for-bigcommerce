<?php
/**
 * The values the report's three filters can take.
 *
 * @package SalesByStateReportForBigCommerce
 */

namespace SBSBC;

defined( 'ABSPATH' ) || exit;

/**
 * Builds the filter options and validates what comes back from the browser.
 *
 * The admin page uses these lists to draw its controls and the REST controller
 * uses the same lists to check the request, so the two can never disagree about
 * what is allowed.
 */
class Filters {

	/**
	 * Option holding the first year offered by the year filter.
	 */
	const YEAR_START_OPTION = 'sbsbc_year_start';

	/**
	 * Number of years offered when the list is first created.
	 */
	const YEAR_WINDOW = 10;

	/**
	 * The columns shown in the report table and summary.
	 *
	 * @return array<string,array{label:string,type:string}>
	 */
	public static function measures() {
		return array(
			'net_revenue'   => array(
				'label' => __( 'Net Sales', 'sales-by-state-report-for-bigcommerce' ),
				'type'  => 'currency',
			),
			'gross_revenue' => array(
				'label' => __( 'Gross Sales', 'sales-by-state-report-for-bigcommerce' ),
				'type'  => 'currency',
			),
		);
	}

	/**
	 * Measure keys.
	 *
	 * @return string[]
	 */
	public static function measure_keys() {
		return array_keys( self::measures() );
	}

	/**
	 * Order statuses the filter offers.
	 *
	 * Keys match BigCommerce V2 status_id slugs.
	 *
	 * @return array<string,string>
	 */
	public static function order_statuses() {
		return array(
			'completed'                     => __( 'Completed', 'sales-by-state-report-for-bigcommerce' ),
			'awaiting_fulfillment'          => __( 'Awaiting Fulfillment', 'sales-by-state-report-for-bigcommerce' ),
			'awaiting_shipment'             => __( 'Awaiting Shipment', 'sales-by-state-report-for-bigcommerce' ),
			'awaiting_pickup'               => __( 'Awaiting Pickup', 'sales-by-state-report-for-bigcommerce' ),
			'shipped'                       => __( 'Shipped', 'sales-by-state-report-for-bigcommerce' ),
			'partially_shipped'             => __( 'Partially Shipped', 'sales-by-state-report-for-bigcommerce' ),
			'pending'                       => __( 'Pending', 'sales-by-state-report-for-bigcommerce' ),
			'awaiting_payment'              => __( 'Awaiting Payment', 'sales-by-state-report-for-bigcommerce' ),
			'awaiting_manual_verification'  => __( 'Awaiting Manual Verification', 'sales-by-state-report-for-bigcommerce' ),
			'incomplete'                    => __( 'Incomplete', 'sales-by-state-report-for-bigcommerce' ),
			'disputed'                      => __( 'Disputed', 'sales-by-state-report-for-bigcommerce' ),
			'refunded'                      => __( 'Refunded', 'sales-by-state-report-for-bigcommerce' ),
			'partially_refunded'            => __( 'Partially Refunded', 'sales-by-state-report-for-bigcommerce' ),
			'cancelled'                     => __( 'Cancelled', 'sales-by-state-report-for-bigcommerce' ),
			'declined'                      => __( 'Declined', 'sales-by-state-report-for-bigcommerce' ),
		);
	}

	/**
	 * The statuses ticked when the report is opened with no explicit filter.
	 *
	 * @return string[]
	 */
	public static function default_statuses() {
		/**
		 * Filters the order statuses the report starts on.
		 *
		 * @since 1.0.0
		 *
		 * @param string[] $statuses Status keys.
		 */
		$statuses = (array) apply_filters( 'sbsbc_default_statuses', array( 'completed' ) );

		return array_values( array_intersect( $statuses, array_keys( self::order_statuses() ) ) );
	}

	/**
	 * Reduce a request value to statuses this report recognises.
	 *
	 * @param string|array $value Comma-separated list or array of statuses.
	 * @return string[]
	 */
	public static function normalize_statuses( $value ) {
		if ( is_string( $value ) ) {
			$value = explode( ',', $value );
		}

		if ( ! is_array( $value ) ) {
			return array();
		}

		$offered  = array_keys( self::order_statuses() );
		$accepted = array();

		foreach ( $value as $status ) {
			$status = sanitize_key( trim( (string) $status ) );

			if ( 'canceled' === $status ) {
				$status = 'cancelled';
			}

			if ( in_array( $status, $offered, true ) ) {
				$accepted[] = $status;
			}
		}

		return array_values( array_unique( $accepted ) );
	}

	/**
	 * Years the filter offers, newest first.
	 *
	 * @return int[]
	 */
	public static function years() {
		$current  = self::default_year();
		$earliest = (int) get_option( self::YEAR_START_OPTION, 0 );

		if ( $earliest <= 0 ) {
			$earliest = $current - ( self::YEAR_WINDOW - 1 );
			update_option( self::YEAR_START_OPTION, $earliest, false );
		}

		if ( $earliest > $current ) {
			$earliest = $current;
		}

		return array_map( 'intval', range( $current, $earliest ) );
	}

	/**
	 * The year the report opens on.
	 *
	 * @return int
	 */
	public static function default_year() {
		return (int) current_time( 'Y' );
	}

	/**
	 * Reduce a request value to a year the filter offers.
	 *
	 * @param mixed $year Requested year.
	 * @return int
	 */
	public static function normalize_year( $year ) {
		$year = (int) $year;

		return in_array( $year, self::years(), true ) ? $year : self::default_year();
	}

	/**
	 * The country the report opens on.
	 *
	 * @return string Two-letter country code.
	 */
	public static function default_country() {
		$cached = get_transient( 'sbsbc_store_country' );

		if ( is_string( $cached ) && Regions::states_for( $cached ) ) {
			return $cached;
		}

		$code = 'US';

		if ( ! Regions::states_for( $code ) ) {
			$code = 'US';
		}

		set_transient( 'sbsbc_store_country', $code, 12 * HOUR_IN_SECONDS );

		return $code;
	}

	/**
	 * Reduce a request value to a two-letter country code.
	 *
	 * @param mixed $country Requested country.
	 * @return string
	 */
	public static function normalize_country( $country ) {
		$country = strtoupper( (string) $country );

		return preg_match( '/^[A-Z]{2}$/', $country ) ? $country : self::default_country();
	}

	/**
	 * Countries that have states or provinces, for the country dropdown.
	 *
	 * @return array<string,string>
	 */
	public static function countries_with_states() {
		return Regions::countries();
	}

	/**
	 * State code => name for a country.
	 *
	 * @param string $country Country code.
	 * @return array<string,string>
	 */
	public static function states_for( $country ) {
		return Regions::states_for( $country );
	}
}
