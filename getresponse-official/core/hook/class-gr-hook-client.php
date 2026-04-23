<?php

declare(strict_types=1);

namespace GetResponse\WordPress\Core\Hook;

use GetResponse\WordPress\Core\Functions;
use WP_Error;

class Gr_Hook_Client {

	private const API_TIMEOUT   = 30;
	private const API_REDIRECTS = 1;

	private string $site_url;
	private array $request_body_hashes = array();

	public function __construct( string $site_url ) {
		$this->site_url = $site_url;
	}

	/**
	 * @throws Gr_Hook_Exception
	 */
	public function post( string $url, array $body ): void {

		$hash = $this->create_hash_from_body( $body );

		if ( isset( $this->request_body_hashes[ $hash ] ) ) {
			return;
		}

		$args = array(
			'method'      => 'POST',
			'body'        => wp_json_encode( $body ),
			'timeout'     => self::API_TIMEOUT,
			'redirection' => self::API_REDIRECTS,
			'blocking'    => true,
			'headers'     => array(
				'Content-Type'       => 'application/json',
				'X-Shop-Domain'      => $this->site_url,
				'X-Timestamp'        => gmdate( 'Y-m-d H:i:s.' ) . gettimeofday()['usec'],
				'X-Platform-Version' => Functions::get_wp_version(),
				'X-PHP-Version'      => Functions::get_php_version(),
				'X-Plugin-Version'   => Functions::get_plugin_version(),
			),
		);

		$response = wp_remote_request( $url, $args );

		if ( $response instanceof WP_Error ) {
			throw Gr_Hook_Exception::createFromWPError(
				esc_attr(
					sprintf( '[%s]: %s', $response->get_error_code(), $response->get_error_message() ),
				),
			);
		}

		$this->request_body_hashes[ $hash ] = true;
	}

	private function create_hash_from_body( array $body ): string {

		if ( isset( $body['updated_at'] ) ) {
			unset( $body['updated_at'] );
		}
		return md5( wp_json_encode( $body ) );
	}
}
