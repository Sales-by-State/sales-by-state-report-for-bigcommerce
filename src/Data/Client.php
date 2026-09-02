<?php
/**
 * BigCommerce REST client for the report import.
 *
 * @package SalesByStateReportForBigCommerce
 */

namespace SBSBC\Data;

defined( 'ABSPATH' ) || exit;

/**
 * Reads store credentials from BigCommerce for WordPress.
 */
class Client {

	/**
	 * GET helper.
	 *
	 * @param string $path Path starting with /.
	 * @param string $api  v2|v3.
	 * @return array|\WP_Error
	 */
	public function get( $path, $api = 'v2' ) {
		return $this->request( 'GET', $path, $api );
	}

	/**
	 * Whether store hash and access token are present.
	 *
	 * @return bool
	 */
	public static function ready() {
		$creds = self::credentials();

		return ! empty( $creds['store_hash'] ) && ! empty( $creds['access_token'] );
	}

	/**
	 * Resolved credentials from the official plugin or environment.
	 *
	 * @return array{store_hash:string,client_id:string,access_token:string}
	 */
	public static function credentials() {
		$url   = (string) get_option( 'bigcommerce_store_url', '' );
		$token = (string) get_option( 'bigcommerce_access_token', '' );
		$cid   = (string) get_option( 'bigcommerce_client_id', '' );

		if ( function_exists( 'bigcommerce_get_env' ) ) {
			$env_url   = (string) bigcommerce_get_env( 'BIGCOMMERCE_API_URL' );
			$env_token = (string) bigcommerce_get_env( 'BIGCOMMERCE_ACCESS_TOKEN' );
			$env_cid   = (string) bigcommerce_get_env( 'BIGCOMMERCE_CLIENT_ID' );

			if ( $env_url ) {
				$url = $env_url;
			}

			if ( $env_token ) {
				$token = $env_token;
			}

			if ( $env_cid ) {
				$cid = $env_cid;
			}
		}

		$hash = '';

		if ( preg_match( '#/stores/([a-z0-9]+)#i', $url, $match ) ) {
			$hash = strtolower( $match[1] );
		}

		return array(
			'store_hash'   => $hash,
			'client_id'    => $cid,
			'access_token' => $token,
		);
	}

	/**
	 * Perform one request. Retries once on HTTP 429.
	 *
	 * @param string $method GET.
	 * @param string $path   Path.
	 * @param string $api    v2|v3.
	 * @param int    $attempt Attempt.
	 * @return array|\WP_Error
	 */
	public function request( $method, $path, $api = 'v2', $attempt = 1 ) {
		$creds = self::credentials();

		if ( empty( $creds['store_hash'] ) || empty( $creds['access_token'] ) ) {
			return new \WP_Error(
				'sbsbc_disconnected',
				__( 'Connect a BigCommerce store before importing orders.', 'sales-by-state-report-for-bigcommerce' )
			);
		}

		$api  = 'v3' === $api ? 'v3' : 'v2';
		$path = '/' . ltrim( (string) $path, '/' );
		$url  = sprintf(
			'https://api.bigcommerce.com/stores/%s/%s%s',
			rawurlencode( $creds['store_hash'] ),
			$api,
			$path
		);

		$response = wp_remote_request(
			$url,
			array(
				'method'  => strtoupper( $method ),
				'timeout' => 30,
				'headers' => array(
					'Accept'        => 'application/json',
					'Content-Type'  => 'application/json',
					'X-Auth-Token'  => $creds['access_token'],
					'X-Auth-Client' => $creds['client_id'],
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$raw  = (string) wp_remote_retrieve_body( $response );

		if ( 429 === $code && $attempt < 3 ) {
			sleep( 2 * $attempt );
			return $this->request( $method, $path, $api, $attempt + 1 );
		}

		$decoded = array();

		if ( '' !== $raw ) {
			$maybe   = json_decode( $raw, true );
			$decoded = is_array( $maybe ) ? $maybe : array();
		}

		if ( $code >= 400 ) {
			$detail = '';

			if ( ! empty( $decoded['title'] ) ) {
				$detail = (string) $decoded['title'];
			} elseif ( ! empty( $decoded['message'] ) ) {
				$detail = (string) $decoded['message'];
			}

			if ( ! $detail ) {
				$detail = sprintf(
					/* translators: 1: HTTP status, 2: path. */
					__( 'HTTP %1$d from %2$s', 'sales-by-state-report-for-bigcommerce' ),
					$code,
					$path
				);
			}

			return new \WP_Error( 'sbsbc_api', $detail, array( 'status' => $code ) );
		}

		return $decoded;
	}
}
