<?php

declare(strict_types=1);

namespace GR\Wordpress\Core\Hook;

use GR\Wordpress\Core\Functions;
use WP_Error;

class Gr_Hook_Client {

    private const API_APP_SECRET = '010b02c432482c288dca40f5dae0b132';
    private const API_TIMEOUT    = 30;
    private const API_REDIRECTS  = 1;

    private string $site_url;
    private array $request_body_hashes = [];

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

        $args = [
            'method'      => 'POST',
            'body'        => wp_json_encode( $body ),
            'timeout'     => self::API_TIMEOUT,
            'redirection' => self::API_REDIRECTS,
            'blocking'    => true,
            'headers'     => [
                'Content-Type'       => 'application/json',
                'X-Shop-Domain'      => $this->site_url,
                'X-Hmac-Sha256'      => $this->create_hmac( $body ),
                'X-Timestamp'        => gmdate( 'Y-m-d H:i:s.' ) . gettimeofday()['usec'],
                'X-Platform-Version' => Functions::get_wp_version(),
                'X-PHP-Version'      => Functions::get_php_version(),
                'X-Plugin-Version'   => Functions::get_plugin_version(),
            ],
        ];

        $response = wp_remote_request( $url, $args );

        if ( $response instanceof WP_Error ) {
            throw Gr_Hook_Exception::createFromWPError( $response );
        }

        $this->request_body_hashes[ $hash ] = true;
    }

    private function create_hmac( array $body ): string {
        // phpcs:ignore
        return base64_encode(
            hash_hmac(
                'sha256',
                wp_json_encode( $body ),
                self::API_APP_SECRET,
                true
            )
        );
    }

    private function create_hash_from_body( array $body ): string {

        if ( isset( $body['updated_at'] ) ) {
            unset( $body['updated_at'] );
        }
        return md5( wp_json_encode( $body ) );
    }
}
