<?php

declare(strict_types=1);

namespace GetResponse\WordPress\Core;

use GetResponse\WordPress\Core\Functions;
use WP_Error;

class Gr_Configuration_Http_Client {
	private const API_TIMEOUT       = 30;
	private const API_REDIRECTS     = 1;
	const X_CONFIGURATION_SIGNATURE = 'x_configuration_signature';
	private const INTEGRATION_NAME  = 'wordpresshybrid';
	private string $site_url;

	public function __construct( string $site_url ) {
		$this->site_url = $site_url;
	}

	public function post( string $url, array $body, array $headers = array() ): array {

		$args = array(
			'method'      => 'POST',
			'body'        => wp_json_encode( $body ),
			'timeout'     => self::API_TIMEOUT,
			'redirection' => self::API_REDIRECTS,
			'blocking'    => true,
			'cookies'     => array(),
			'headers'     => array(
				'Content-Type'              => 'application/json',
				'X-Shop-Domain'             => $this->site_url,
				'X-Timestamp'               => gmdate( 'Y-m-d H:i:s.' ) . gettimeofday()['usec'],
				'X-Platform-Version'        => Functions::get_wp_version(),
				'X-PHP-Version'             => Functions::get_php_version(),
				'X-Plugin-Version'          => Functions::get_plugin_version(),
				'X-Integration-Name'        => self::INTEGRATION_NAME,
				'X-Configuration-Signature' => $this->get_configuration_signature( $headers ),
			),
		);

		$response = wp_remote_request( $url, $args );

		if ( $response instanceof WP_Error ) {
			throw Gr_Http_Client_Exception::createFromWPError(
				esc_attr( $response->get_error_message() ),
				esc_attr( $response->get_error_code() )
			);
		}

		return $response;
	}

	private function get_configuration_signature( array $headers ) {
		return isset( $headers[ self::X_CONFIGURATION_SIGNATURE ][0] ) ? $headers[ self::X_CONFIGURATION_SIGNATURE ][0] : '';
	}
}
