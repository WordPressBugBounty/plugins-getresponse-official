<?php

declare(strict_types=1);

namespace GR\WordPress\Core;

use Exception;
use GR\WordPress\Controllers\GR_API_Controller;
use GR\WordPress\Core\Hook\Gr_Hook_Service;
use GR\WordPress\Core\Hook\Gr_Hook_Client;
use GR\WordPress\Core\logger\File_Logger;
use GR\WordPress\Core\logger\Gr_Logger_Configuration;
use GR\WordPress\Integrations\ContactForm7\Contact_Form_7_Integration;
use GR\WordPress\Integrations\WebConnect\Cart_Service;
use GR\WordPress\Integrations\WebConnect\Order_Service;
use GR\WordPress\Integrations\WebConnect\Web_Connect_Integration;
use GR\WordPress\Integrations\WebConnect\Web_Connect_Buffer_Service;
use GR\WordPress\Integrations\Woocommerce\Gr_Cart_Service;
use GR\WordPress\Integrations\Woocommerce\Woocommerce_Integration;
use GR\WordPress\Integrations\WPRegistrationForm\WP_Registration_Form_Integration;
use GR\WordPress\Integrations\WPUserProfile\WP_User_Profile_Integration;
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
			$this->register_updated_at_meta();
			$this->register_integrations();
			$this->check_if_old_plugin_is_installed();
			add_filter( 'rest_user_query', array( $this, 'add_updated_at_filter' ), 10, 2 );
		} catch ( Exception $exception ) {
			$this->logger->error( 'Run error', Functions::get_error_context( $exception ) );
		}
	}

	public function set_gr_updated_at_for_existing_users() {
		global $wpdb;

		$meta_key     = Gr_Configuration::USER_UPDATED_AT_META_NAME;
		$current_time = current_time( 'mysql' );

		$start_time = microtime( true );
		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$wpdb->usermeta} (user_id, meta_key, meta_value)
                        SELECT u.ID, %s, %s
                        FROM {$wpdb->users} u
                        LEFT JOIN {$wpdb->usermeta} um ON u.ID = um.user_id AND um.meta_key = %s
                        WHERE um.user_id IS NULL;",
				$meta_key,
				$current_time,
				$meta_key
			)
		);
		$execution_time = microtime( true ) - $start_time;

		$this->logger->info( 'Set GR_updated_at for ' . $wpdb->rows_affected . ' existing users. Execution time: ' . $execution_time . ' seconds' );
	}

	public function delete_gr_updated_at_metafield() {
		global $wpdb;

		$meta_key = Gr_Configuration::USER_UPDATED_AT_META_NAME;

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->usermeta} WHERE meta_key = %s",
				$meta_key
			)
		);
	}

	private function register_integrations(): void {

		$gr_hook_client   = new Gr_Hook_Client( get_home_url() );
		$rest_api_service = new Gr_Rest_Api_Service();
		$gr_configuration = $rest_api_service->get_configuration();
		$gr_hook_service  = new Gr_Hook_Service( $gr_hook_client );
		$gr_cart_service  = new Gr_Cart_Service();
		$buffer_service   = new Web_Connect_Buffer_Service();

		( new Contact_Form_7_Integration( $gr_configuration, $gr_hook_service, $this->logger ) )->init();
		( new WP_Registration_Form_Integration( $gr_configuration, $gr_hook_service, $this->logger ) )->init();
		( new Woocommerce_Integration( $gr_configuration, $gr_hook_service, $gr_cart_service, $this->logger ) )->init();
		( new WP_User_Profile_Integration( $gr_configuration, $gr_hook_service, $this->logger ) )->init();
		( new Web_Connect_Integration(
			$gr_configuration,
			new Cart_Service( $gr_configuration, $gr_cart_service, $buffer_service ),
			new Order_Service( $gr_configuration, $gr_cart_service, $buffer_service ),
			$this->logger
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

	public function add_updated_at_filter( $args, $request ) {
		$filter_name = Gr_Configuration::USER_UPDATED_AFTER_FILTER_NAME;

		if ( ! empty( $request[ $filter_name ] ) ) {
			$args['meta_query'][] = array(
				'relation' => 'OR',
				array(
					'key'     => Gr_Configuration::USER_UPDATED_AT_META_NAME,
					'value'   => sanitize_text_field( $request[ $filter_name ] ),
					'compare' => '>',
					'type'    => 'DATETIME',
				),
				array(
					'key'     => Gr_Configuration::USER_UPDATED_AT_META_NAME,
					'compare' => 'NOT EXISTS',
				),
			);
		}
		return $args;
	}

	public function extend_api(): void {
		add_action(
			'rest_api_init',
			function () {
				$gr_config_http_client   = new Gr_Configuration_Http_Client( site_url() );
				$configuration_validator = new Gr_Configuration_Validator( $gr_config_http_client );
				( new GR_API_Controller( $this->version, $configuration_validator ) )->register_routes();
			}
		);
	}

	private function register_marketing_consent_meta(): void {
		register_meta(
			'user',
			Gr_Configuration::MARKETING_CONSENT_META_NAME,
			array(
				'type'         => 'boolean',
				'single'       => true,
				'show_in_rest' => true,
			)
		);
	}

	private function register_updated_at_meta() {
		register_meta(
			'user',
			Gr_Configuration::USER_UPDATED_AT_META_NAME,
			array(
				'type'         => 'string',
				'description'  => 'Last modified date',
				'single'       => true,
				'show_in_rest' => array(
					'schema' => array(
						'type' => 'string',
					),
				),
			)
		);
	}


	private function init_logger(): void {
		$logger_configuration = new Gr_Logger_Configuration();

		if ( ! $logger_configuration->is_logger_enabled() ) {
			$this->logger = new NullLogger();
			return;
		}

		$log_dir = $logger_configuration->get_log_dir();

		global $wp_filesystem;

		if ( ! $wp_filesystem ) {
			if ( ! function_exists( 'WP_Filesystem' ) ) {
				require_once wp_normalize_path( ABSPATH . '/wp-admin/includes/file.php' );
			}
			WP_Filesystem();
		}

		if ( ! $wp_filesystem->is_dir( $log_dir ) ) {
			$wp_filesystem->mkdir( $log_dir );
			$wp_filesystem->put_contents( $log_dir . 'index.html', '', FS_CHMOD_FILE );
		}

		$files = glob( $log_dir . 'log*' );

		$threshold = strtotime( '-' . $logger_configuration->get_retention() . ' day' );

		foreach ( $files as $file ) {
			if ( $wp_filesystem->is_file( $file ) ) {
				if ( $threshold >= $wp_filesystem->mtime( $file ) ) {
					$wp_filesystem->delete( $file );
				}
			}
		}

		$this->logger = new File_Logger( $log_dir, $logger_configuration->get_salt() );
	}
}
