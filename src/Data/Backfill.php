<?php
/**
 * Populates the report table from existing BigCommerce orders.
 *
 * @package SalesByStateReportForBigCommerce
 */

namespace SBSBC\Data;

use SBSBC\Install\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Walks every order once, one API page at a time.
 */
class Backfill {

	/**
	 * Option holding the current API page cursor.
	 */
	const CURSOR_OPTION = 'sbsbc_backfill_cursor';

	/**
	 * Process one batch.
	 *
	 * @param int $limit Orders per batch.
	 * @return array{processed:int,remaining:int,complete:bool,cursor:array}
	 */
	public static function run_batch( $limit = 25 ) {
		Schema::maybe_install();

		$cursor = self::cursor();
		$limit  = max( 1, min( 50, (int) $limit ) );

		if ( 'done' === $cursor['phase'] ) {
			return array(
				'processed' => 0,
				'remaining' => 0,
				'complete'  => true,
				'cursor'    => $cursor,
			);
		}

		$page   = max( 1, (int) $cursor['page'] );
		$result = OrderSource::page( $page, $limit );

		if ( is_wp_error( $result ) ) {
			return array(
				'processed' => 0,
				'remaining' => OrderSource::count(),
				'complete'  => false,
				'cursor'    => $cursor,
			);
		}

		$orders = $result['orders'];
		$total  = (int) $result['count'];
		$sync   = new Sync();
		$done   = 0;

		foreach ( $orders as $order ) {
			if ( $sync->upsert( $order ) ) {
				++$done;
			}
		}

		$fetched = count( $orders );
		$seen    = ( ( $page - 1 ) * $limit ) + $fetched;

		if ( 0 === $fetched || $fetched < $limit || ( $total && $seen >= $total ) ) {
			$cursor = array(
				'phase' => 'done',
				'page'  => $page,
			);
		} else {
			$cursor['page'] = $page + 1;
		}

		update_option( self::CURSOR_OPTION, wp_json_encode( $cursor ), false );
		delete_transient( 'sbsbc_order_count' );

		$remaining = self::remaining_from( $cursor, $limit );

		return array(
			'processed' => $done,
			'remaining' => $remaining,
			'complete'  => 'done' === $cursor['phase'],
			'cursor'    => $cursor,
		);
	}

	/**
	 * Number of orders still to be walked.
	 *
	 * @return int
	 */
	public static function remaining() {
		return self::remaining_from( self::cursor(), 25 );
	}

	/**
	 * Whether the table has been built.
	 *
	 * @return bool
	 */
	public static function is_complete() {
		return 'done' === self::cursor()['phase'];
	}

	/**
	 * Empty the table and start again.
	 *
	 * @return void
	 */
	public static function reset() {
		Schema::maybe_install();
		Schema::truncate();
		delete_option( self::CURSOR_OPTION );
		delete_transient( 'sbsbc_order_count' );
	}

	/**
	 * Restart the import when the table is empty but the store has orders.
	 *
	 * @return void
	 */
	public static function heal_if_empty() {
		if ( ! Schema::table_exists() || ! self::is_complete() ) {
			return;
		}

		if ( Schema::row_count() > 0 ) {
			return;
		}

		if ( OrderSource::count() <= 0 ) {
			return;
		}

		delete_option( self::CURSOR_OPTION );
		delete_transient( 'sbsbc_order_count' );
	}

	/**
	 * Current cursor.
	 *
	 * @return array{phase:string,page:int}
	 */
	private static function cursor() {
		$raw = get_option( self::CURSOR_OPTION, '' );

		if ( is_array( $raw ) ) {
			$decoded = $raw;
		} else {
			$decoded = json_decode( (string) $raw, true );
		}

		$phase = isset( $decoded['phase'] ) ? (string) $decoded['phase'] : 'page';
		$page  = isset( $decoded['page'] ) ? (int) $decoded['page'] : 1;

		if ( ! in_array( $phase, array( 'page', 'done' ), true ) ) {
			$phase = 'page';
		}

		return array(
			'phase' => $phase,
			'page'  => max( 1, $page ),
		);
	}

	/**
	 * Estimate remaining API rows from the cursor.
	 *
	 * @param array $cursor Cursor.
	 * @param int   $limit  Page size.
	 * @return int
	 */
	private static function remaining_from( array $cursor, $limit ) {
		if ( 'done' === $cursor['phase'] ) {
			return 0;
		}

		$total = OrderSource::count();
		$page  = max( 1, (int) $cursor['page'] );
		$seen  = ( $page - 1 ) * max( 1, (int) $limit );

		return max( 0, $total - $seen );
	}
}
