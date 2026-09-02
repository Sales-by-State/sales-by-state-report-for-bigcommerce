<?php
/**
 * Unattended backfill via Action Scheduler or WP-Cron.
 *
 * @package SalesByStateReportForBigCommerce
 */

namespace SBSBC\Data;

use SBSBC\Install\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Fills the report table in the background, then polls for new orders.
 *
 * Action Scheduler is used when another plugin provides it. Otherwise
 * WP-Cron runs the same hooks. The report page also imports orders
 * through the REST backfill endpoint.
 */
class Scheduler {

	/**
	 * Backfill hook name.
	 */
	const HOOK = 'sbsbc_backfill_batch';

	/**
	 * Recent-order poll hook name.
	 */
	const SYNC_HOOK = 'sbsbc_sync_recent';

	/**
	 * Action Scheduler group.
	 */
	const GROUP = 'sales-by-state-report-for-bigcommerce';

	/**
	 * Orders per backfill batch (API-bound).
	 */
	const BATCH = 15;

	/**
	 * Newest orders to refresh on each poll.
	 */
	const RECENT = 50;

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( self::HOOK, array( $this, 'run_batch' ) );
		add_action( self::SYNC_HOOK, array( $this, 'sync_recent' ) );
		add_action( 'admin_init', array( $this, 'maybe_schedule' ) );
		add_filter( 'cron_schedules', array( $this, 'cron_schedules' ) );
	}

	/**
	 * Fifteen-minute interval for the recent-order poll.
	 *
	 * @param array $schedules Existing schedules.
	 * @return array
	 */
	public function cron_schedules( $schedules ) {
		if ( ! is_array( $schedules ) ) {
			$schedules = array();
		}

		if ( empty( $schedules['sbsbc_fifteen_minutes'] ) ) {
			$schedules['sbsbc_fifteen_minutes'] = array(
				'interval' => 15 * MINUTE_IN_SECONDS,
				'display'  => __( 'Every 15 minutes', 'sales-by-state-report-for-bigcommerce' ),
			);
		}

		return $schedules;
	}

	/**
	 * Queue work if the table still needs filling or a poll is due.
	 *
	 * @return void
	 */
	public function maybe_schedule() {
		if ( ! Schema::table_exists() ) {
			return;
		}

		if ( ! Backfill::is_complete() ) {
			$this->queue_backfill();
			return;
		}

		$this->queue_sync();
	}

	/**
	 * Process one batch and requeue if more remains.
	 *
	 * @return void
	 */
	public function run_batch() {
		$result = Backfill::run_batch( self::BATCH );

		if ( $result['complete'] ) {
			$this->queue_sync();
			return;
		}

		$this->queue_backfill( 10 );
	}

	/**
	 * Refresh the newest orders after the first import.
	 *
	 * @return void
	 */
	public function sync_recent() {
		if ( ! Client::ready() ) {
			return;
		}

		$sync   = new Sync();
		$orders = OrderSource::recent( self::RECENT );

		foreach ( $orders as $order ) {
			$sync->upsert( $order );
		}
	}

	/**
	 * Queue the next backfill batch.
	 *
	 * @param int $delay Seconds to wait.
	 * @return void
	 */
	private function queue_backfill( $delay = 0 ) {
		$when = time() + max( 0, (int) $delay );

		if ( function_exists( 'as_schedule_single_action' ) && function_exists( 'as_next_scheduled_action' ) ) {
			if ( ! as_next_scheduled_action( self::HOOK, null, self::GROUP ) ) {
				as_schedule_single_action( $when, self::HOOK, array(), self::GROUP );
			}

			return;
		}

		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_single_event( $when, self::HOOK );
		}
	}

	/**
	 * Queue the recurring recent-order poll.
	 *
	 * @return void
	 */
	private function queue_sync() {
		if ( ! Client::ready() ) {
			return;
		}

		if ( function_exists( 'as_schedule_recurring_action' ) && function_exists( 'as_next_scheduled_action' ) ) {
			if ( ! as_next_scheduled_action( self::SYNC_HOOK, null, self::GROUP ) ) {
				as_schedule_recurring_action(
					time() + MINUTE_IN_SECONDS,
					15 * MINUTE_IN_SECONDS,
					self::SYNC_HOOK,
					array(),
					self::GROUP
				);
			}

			return;
		}

		if ( ! wp_next_scheduled( self::SYNC_HOOK ) ) {
			wp_schedule_event( time() + MINUTE_IN_SECONDS, 'sbsbc_fifteen_minutes', self::SYNC_HOOK );
		}
	}
}
