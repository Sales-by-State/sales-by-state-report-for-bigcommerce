<?php
/**
 * The report page and its assets.
 *
 * @package SalesByStateReportForBigCommerce
 */

namespace SBSBC\Admin;

use SBSBC\Filters;
use SBSBC\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the report under BigCommerce products.
 */
class Page {

	/**
	 * Menu slug.
	 */
	const SLUG = 'sbsbc-sales-by-state';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_menu', array( $this, 'register_page' ), 99 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * Add the report under Products.
	 *
	 * @return void
	 */
	public function register_page() {
		add_submenu_page(
			'edit.php?post_type=bigcommerce_product',
			__( 'Sales by State', 'sales-by-state-report-for-bigcommerce' ),
			__( 'Sales by State', 'sales-by-state-report-for-bigcommerce' ),
			'manage_options',
			self::SLUG,
			array( $this, 'render' )
		);
	}

	/**
	 * Render the root element for the standalone page.
	 *
	 * @return void
	 */
	public function render() {
		if ( ! Plugin::can_view() ) {
			return;
		}

		printf(
			'<div class="wrap sbsbc-wrap">
				<div class="sbsbc-page-header"><h1 class="sbsbc-page-header__title">%s</h1></div>
				<div id="sbsbc-root"></div>
			</div>',
			esc_html__( 'Sales by State', 'sales-by-state-report-for-bigcommerce' )
		);
	}

	/**
	 * Enqueue the report bundle on this screen only.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue( $hook ) {
		if ( ! $this->is_screen( $hook ) ) {
			return;
		}

		$script = SBSBC_DIR . 'assets/js/report.js';
		$style  = SBSBC_DIR . 'assets/css/report.css';

		wp_register_script(
			'sbsbc-report',
			SBSBC_URL . 'assets/js/report.js',
			array(
				'wp-hooks',
				'wp-element',
				'wp-i18n',
				'wp-api-fetch',
				'wp-url',
				'wp-components',
			),
			file_exists( $script ) ? (string) filemtime( $script ) : SBSBC_VERSION,
			true
		);

		wp_set_script_translations( 'sbsbc-report', 'sales-by-state-report-for-bigcommerce', SBSBC_DIR . 'languages' );
		wp_localize_script( 'sbsbc-report', 'sbsbcConfig', $this->config() );
		wp_enqueue_script( 'sbsbc-report' );

		wp_enqueue_style( 'wp-components' );

		wp_enqueue_style(
			'sbsbc-report',
			SBSBC_URL . 'assets/css/report.css',
			array( 'wp-components' ),
			file_exists( $style ) ? (string) filemtime( $style ) : SBSBC_VERSION
		);
	}

	/**
	 * Whether this screen is showing.
	 *
	 * @param string $hook Optional enqueue hook.
	 * @return bool
	 */
	private function is_screen( $hook = '' ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- reading the current screen, not acting on it.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

		if ( self::SLUG === $page ) {
			return true;
		}

		return is_string( $hook ) && false !== strpos( $hook, self::SLUG );
	}

	/**
	 * Data the bundle needs to draw its controls.
	 *
	 * @return array
	 */
	private function config() {
		$measures = array();

		foreach ( Filters::measures() as $key => $measure ) {
			$measures[] = array(
				'key'   => $key,
				'label' => $measure['label'],
				'type'  => $measure['type'],
			);
		}

		$statuses = array();

		foreach ( Filters::order_statuses() as $key => $label ) {
			$statuses[] = array(
				'value' => $key,
				'label' => $label,
			);
		}

		$years = array();

		foreach ( Filters::years() as $year ) {
			$years[] = array(
				'value' => (string) $year,
				'label' => (string) $year,
			);
		}

		$countries = array();

		foreach ( Filters::countries_with_states() as $code => $label ) {
			$countries[] = array(
				'value' => $code,
				'label' => $label,
			);
		}

		return array(
			'measures'        => $measures,
			'statuses'        => $statuses,
			'years'           => $years,
			'countries'       => $countries,
			'defaultCountry'  => Filters::default_country(),
			'defaultYear'     => (string) Filters::default_year(),
			'defaultStatuses' => Filters::default_statuses(),
			'perPageOptions'  => array( 10, 25, 50, 100 ),
			'title'           => __( 'Sales by State', 'sales-by-state-report-for-bigcommerce' ),
			'canBuild'        => Plugin::can_manage(),
			'mode'            => 'standalone',
		);
	}
}
