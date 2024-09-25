<?php

declare(strict_types=1);

namespace GR\Wordpress\Controllers;

use GR\Wordpress\Core\Gr_Configuration;
use GR\Wordpress\Core\Gr_Rest_Api_Service;
use GR\Wordpress\Core\logger\Gr_Logger_Configuration;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

class GR_API_Controller extends WP_REST_Controller {

    private const LOGGER_RETENTION_KEY = 'logger_retention';
    private const LOGGER_ENABLED_KEY   = 'logger_enabled';

    private string $version;
    private Gr_Rest_Api_Service $gr_rest_api_service;

    public function __construct( string $version ) {
        $this->version             = $version;
        $this->gr_rest_api_service = new Gr_Rest_Api_Service();
    }

    public function register_routes(): void {
        register_rest_route(
            'gr4wp/v1',
            '/configuration',
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ $this, 'get_configuration' ],
                'permission_callback' => function () {
                    return is_user_logged_in();
                },
            ]
        );

        register_rest_route(
            'gr4wp/v1',
            '/configuration',
            [
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => [ $this, 'update_configuration' ],
                'permission_callback' => function () {
                    return is_user_logged_in();
                },
                'args'                => [
                    Gr_Configuration::WEB_CONNECT_SNIPPET_KEY => [
                        'required' => true,
                        'type'     => 'string',
                    ],
                    Gr_Configuration::LIVE_SYNC_URL_KEY   => [
                        'required' => true,
                        'type'     => 'string',
                    ],
                    Gr_Configuration::LIVE_SYNC_TYPE_KEY  => [
                        'required' => true,
                        'type'     => 'string',
                    ],
                    Gr_Configuration::MARKETING_CONSENT_TEXT_KEY => [
                        'required' => true,
                        'type'     => 'string',
                    ],
                    Gr_Configuration::GETRESPONSE_SHOP_ID => [
                        'required' => true,
                        'type'     => 'string',
                    ],
                    Gr_Configuration::INTEGRATE_WITH_CONTACT_FORM_7 => [
                        'required' => true,
                        'type'     => 'bool',
                    ],
                ],
            ]
        );

        register_rest_route(
            'gr4wp/v1',
            '/configuration',
            [
                'methods'             => WP_REST_Server::DELETABLE,
                'callback'            => [ $this, 'clear_configuration' ],
                'permission_callback' => function () {
                    return is_user_logged_in();
                },
            ]
        );

        register_rest_route(
            'gr4wp/v1',
            '/sites',
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ $this, 'get_sites' ],
                'permission_callback' => function () {
                    return is_user_logged_in();
                },
            ]
        );

        register_rest_route(
            'gr4wp/v1',
            '/logger',
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ $this, 'get_logger_configuration' ],
                'permission_callback' => function () {
                    return is_user_logged_in();
                },
            ]
        );

        register_rest_route(
            'gr4wp/v1',
            '/logger/files',
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ $this, 'get_log_files' ],
                'permission_callback' => function () {
                    return is_user_logged_in();
                },
            ]
        );

        register_rest_route(
            'gr4wp/v1',
            '/logger',
            [
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => [ $this, 'update_logger_configuration' ],
                'permission_callback' => function () {
                    return is_user_logged_in();
                },
                'args'                => [
                    self::LOGGER_RETENTION_KEY => [
                        'required' => true,
                        'type'     => 'int',
                    ],
                    self::LOGGER_ENABLED_KEY   => [
                        'required' => true,
                        'type'     => 'bool',
                    ],
				],
			]
        );
    }

    public function get_configuration(): array {
        return [
            'version'       => $this->version,
            'configuration' => $this->gr_rest_api_service->get_configuration()->to_array(),
        ];
    }

    public function update_configuration( $request ): WP_REST_Response {
        $configuration = Gr_Configuration::make_from_array( $request->get_params() );
        $this->gr_rest_api_service->update_configuration( $configuration );

        return new WP_REST_Response(
            [
                'version'       => $this->version,
                'configuration' => $configuration->to_array(),
            ],
            201
        );
    }

    public function clear_configuration(): WP_REST_Response {
        $this->gr_rest_api_service->clear_configuration();
        return new WP_REST_Response( null, 204 );
    }

    public function get_sites(): array {
        return $this->gr_rest_api_service->get_sites();
    }

    public function get_logger_configuration(): array {
        $logger_configuration = new Gr_Logger_Configuration();
        return [
            self::LOGGER_RETENTION_KEY => $logger_configuration->get_retention(),
            self::LOGGER_ENABLED_KEY   => $logger_configuration->is_logger_enabled(),
        ];
    }

    public function get_log_files(): array {
        $logger_configuration = new Gr_Logger_Configuration();
        $log_base_url         = $logger_configuration->get_log_base_url();
        return array_map(
            fn( $file) => $log_base_url . basename( $file ),
            glob( $logger_configuration->get_log_dir() . 'log*' )
        );
    }

    public function update_logger_configuration( WP_REST_Request $request ): WP_REST_Response {
        $logger_configuration = new Gr_Logger_Configuration();
        $logger_configuration->set_retention( (int) $request->get_param( self::LOGGER_RETENTION_KEY ) );
        $logger_configuration->set_logger_enabled( (bool) $request->get_param( self::LOGGER_ENABLED_KEY ) );

        return new WP_REST_Response(
            [
                self::LOGGER_RETENTION_KEY => $logger_configuration->get_retention(),
                self::LOGGER_ENABLED_KEY   => $logger_configuration->is_logger_enabled(),
            ],
            201
        );
    }
}
