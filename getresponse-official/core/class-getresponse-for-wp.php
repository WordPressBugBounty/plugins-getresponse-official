<?php

declare(strict_types=1);

namespace GR\Wordpress\Core;

use Exception;
use GR\Wordpress\Controllers\GR_API_Controller;
use GR\Wordpress\Core\Hook\Gr_Hook_Service;
use GR\Wordpress\Core\Hook\Gr_Hook_Client;
use GR\Wordpress\Core\logger\File_Logger;
use GR\Wordpress\Core\logger\Gr_Logger_Configuration;
use GR\Wordpress\Integrations\ContactForm7\Contact_Form_7_Integration;
use GR\Wordpress\Integrations\Recommendation\Recommendation_Integration;
use GR\Wordpress\Integrations\WebConnect\Cart_Service;
use GR\Wordpress\Integrations\WebConnect\Order_Service;
use GR\Wordpress\Integrations\WebConnect\Web_Connect_Integration;
use GR\Wordpress\Integrations\WebConnect\Web_Connect_Buffer_Service;
use GR\Wordpress\Integrations\Woocommerce\Gr_Cart_Service;
use GR\Wordpress\Integrations\Woocommerce\Woocommerce_Integration;
use GR\Wordpress\Integrations\WPRegistrationForm\WP_Registration_Form_Integration;
use GR\Wordpress\Integrations\WPUserProfile\WP_User_Profile_Integration;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class Getresponse_For_Wp {

    private string $plugin_name;
    private string $version;
    private LoggerInterface $logger;

	public function __construct() {
		if ( defined( 'GETRESPONSE_FOR_WP_VERSION' ) ) {
			$this->version = GETRESPONSE_FOR_WP_VERSION;
		} else {
			$this->version = '1.0.0';
		}
		$this->plugin_name = 'getresponse-for-wp';
	}

    public function run(): void {
        $this->init_logger();
        try {
            $this->extend_api();
            $this->register_marketing_consent_meta();
            $this->register_integrations();
            $this->check_if_old_plugin_is_installed();
        } catch ( Exception $exception ) {
            $this->logger->error( 'Run error', Functions::get_error_context( $exception ) );
        }
    }

    private function register_integrations(): void {
        $rest_api_service = new Gr_Rest_Api_Service();
        $gr_configuration = $rest_api_service->get_configuration();

        $gr_hook_client  = new Gr_Hook_Client( get_home_url() );
        $gr_hook_service = new Gr_Hook_Service( $gr_hook_client );
        $gr_cart_service = new Gr_Cart_Service();
        $buffer_service  = new Web_Connect_Buffer_Service();

        ( new Contact_Form_7_Integration( $gr_configuration, $gr_hook_service, $this->logger ) )->init();
        ( new WP_Registration_Form_Integration( $gr_configuration, $gr_hook_service, $this->logger ) )->init();
        ( new Woocommerce_Integration( $gr_configuration, $gr_hook_service, $gr_cart_service, $this->logger ) )->init();
        ( new WP_User_Profile_Integration( $gr_configuration, $gr_hook_service, $this->logger ) )->init();
        ( new Recommendation_Integration( $gr_configuration ) )->init();
        ( new Web_Connect_Integration(
            $gr_configuration,
            new Cart_Service( $gr_configuration, $gr_cart_service, $buffer_service ),
            new Order_Service( $gr_configuration, $gr_cart_service, $buffer_service )
        ) )->init();
    }

	public function get_plugin_name(): string {
		return $this->plugin_name;
	}

	public function get_version(): string {
		return $this->version;
	}

    private function check_if_old_plugin_is_installed() {

        if ( ! function_exists( 'get_plugins' ) ) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $all_plugins = get_plugins();

        foreach ( $all_plugins as $plugin ) {
            if ( $plugin['Name'] === 'GetResponse for WordPress' ) {

                $class   = 'notice notice-error';
                $message = __( 'We\'ve detected you\'re using an old GetResponse plugin for WordPress. To ensure your integration works properly, uninstall the outdated plugin.', 'sample-text-domain' );

                add_action(
                    'admin_notices',
                    function () use ( $message, $class ) {
                        printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $message ) );
                    }
                );
            }
        }
    }

    public function extend_api(): void {

        add_action(
            'rest_api_init',
            function() {
                ( new GR_API_Controller( $this->version ) )->register_routes();
            }
        );
    }

    private function register_marketing_consent_meta(): void {
        register_meta(
            'user',
            Gr_Configuration::MARKETING_CONSENT_META_NAME,
            [
                'type'         => 'boolean',
                'single'       => true,
                'show_in_rest' => true,
            ]
        );
    }

    private function init_logger(): void {

        $logger_configuration = new Gr_Logger_Configuration();

        if ( ! $logger_configuration->is_logger_enabled() ) {
            $this->logger = new NullLogger();
            return;
        }

        $log_dir = $logger_configuration->get_log_dir();

        if ( ! is_dir( $log_dir ) ) {
            mkdir( $log_dir );
            // phpcs:ignore
            fclose( fopen( $log_dir . 'index.html', 'w+' ) );
        }

        $files = glob( $log_dir . 'log*' );

        $threshold = strtotime( '-' . $logger_configuration->get_retention() . ' day' );

        foreach ( $files as $file ) {
            if ( is_file( $file ) ) {
                if ( $threshold >= filemtime( $file ) ) {
                    unlink( $file );
                }
            }
        }

        $this->logger = new File_Logger( $log_dir, $logger_configuration->get_salt() );
    }
}
