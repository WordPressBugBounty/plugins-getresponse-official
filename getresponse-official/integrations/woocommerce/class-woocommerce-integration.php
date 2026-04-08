<?php

declare(strict_types=1);

namespace GR\WordPress\Integrations\Woocommerce;

use GR\WordPress\Core\Functions;
use GR\WordPress\Core\Gr_Configuration;
use GR\WordPress\Core\Gr_User_Marketing_Consent_Buffer;
use GR\WordPress\Core\Hook\Gr_Hook_Service;
use GR\WordPress\Integrations\Integration;
use Psr\Log\LoggerInterface;
use WC_Customer;
use WC_Order;
use WC_Product;
use WP_REST_Request;

class Woocommerce_Integration implements Integration {

	private Gr_Configuration $gr_configuration;
	private Gr_Hook_Service $gr_hook_service;
	private Gr_Cart_Service $gr_cart_service;
	private LoggerInterface $logger;

	public function __construct(
		Gr_Configuration $gr_configuration,
		Gr_Hook_Service $gr_hook_service,
		Gr_Cart_Service $gr_cart_service,
		LoggerInterface $logger
	) {
		$this->gr_configuration = $gr_configuration;
		$this->gr_hook_service  = $gr_hook_service;
		$this->gr_cart_service  = $gr_cart_service;
		$this->logger           = $logger;
	}

	public static function is_woo_commerce_installed(): bool {
		return in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ), true );
	}

	public function init(): void {

		add_action( 'woocommerce_new_product', array( $this, 'handle_product_upsert' ) );
		add_action( 'woocommerce_update_product', array( $this, 'handle_product_upsert' ) );

		add_action( 'woocommerce_product_set_stock', array( $this, 'handle_product_stock_change' ) );
		add_action( 'woocommerce_variation_set_stock', array( $this, 'handle_product_stock_change' ) );

		add_action( 'woocommerce_new_order', array( $this, 'handle_new_order' ), 10, 2 );
		add_action( 'woocommerce_order_status_changed', array( $this, 'handle_order_status_changed' ), 10, 4 );

		add_action( 'woocommerce_add_to_cart', array( $this, 'handle_cart_upsert' ), 30, );
		add_action( 'woocommerce_cart_item_removed', array( $this, 'handle_cart_upsert' ), 30, );
		add_action( 'woocommerce_update_cart_action_cart_updated', array( $this, 'handle_cart_upsert' ), 30, );

		add_action( 'woocommerce_register_form', array( $this, 'add_woocommerce_marketing_consent_checkbox' ) );
		add_action( 'woocommerce_after_order_notes', array( $this, 'add_marketing_consent_checkbox' ) );

		add_action( 'woocommerce_update_customer', array( $this, 'handle_customer_upsert' ), 10 );

		add_action( 'profile_update', array( $this, 'handle_customer_upsert_in_admin' ), 20 );

		add_action( 'wp_loaded', array( $this, 'rebuild_cart' ) );

		add_filter( 'woocommerce_rest_customer_query', array( $this, 'add_updated_at_filter' ), 10, 2 );

		add_action( 'woocommerce_init', array( $this, 'handle_woocommerce_init' ) );

		add_action( 'woocommerce_store_api_checkout_update_customer_from_request', array( $this, 'handle_woocommerce_store_api_checkout_update_customer' ), 10, 2 );
	}

	public function handle_product_upsert( int $product_id ): void {
		$handler = new Product_Upsert_Handler( $this->gr_configuration, $this->gr_hook_service, $this->logger );
		$handler->handle( $product_id );
	}

	public function handle_product_stock_change( WC_Product $product ): void {
		$handler = new Product_Upsert_Handler( $this->gr_configuration, $this->gr_hook_service, $this->logger );
		$handler->handle( $product->get_id() );
	}

	public function handle_order_status_changed( ?int $order_id, string $status_from, string $status_to, WC_Order $order ): void {

		do_action( 'gr4wp_order_upsert', $order );

		$handler = new Order_Upsert_Handler(
			$this->gr_configuration,
			$this->gr_hook_service,
			$this->gr_cart_service,
			$this->logger
		);

		$handler->handle( $order );
	}

	public function handle_new_order( ?int $order_id, WC_Order $order ): void {

		$handler = new New_Order_Handler(
			$this->gr_configuration,
			$this->gr_cart_service,
			$this->logger
		);

		do_action( 'gr4wp_order_upsert', $order );

		$handler->handle( $order );
	}

	public function handle_cart_upsert(): void {
		if ( ! empty( $_GET['grcart'] ) || false === self::is_woo_commerce_installed() ) {
			return;
		}

		$cart = WC()->cart;

		if ( $cart === null ) {
			return;
		}

		do_action( 'gr4wp_cart_upsert', $cart );

		$handler = new Cart_Upsert_Handler(
			$this->gr_configuration,
			$this->gr_hook_service,
			$this->gr_cart_service,
			$this->logger
		);
		$handler->handle( $cart );
	}

	public function add_marketing_consent_checkbox(): void {
		Functions::add_marketing_consent_checkbox(
			$this->gr_configuration->get_marketing_consent_text()
		);
	}

	public function add_woocommerce_marketing_consent_checkbox(): void {
		$marketing_consent_key  = Gr_Configuration::MARKETING_CONSENT_META_NAME;
		$marketing_consent_text = $this->gr_configuration->get_marketing_consent_text();

		if ( empty( $marketing_consent_text ) ) {
			return;
		}

		woocommerce_form_field(
			esc_attr( Gr_Configuration::MARKETING_CONSENT_META_NAME ),
			array(
				'type'        => 'checkbox',
				'required'    => false,
				'label'       => esc_attr( $marketing_consent_text ),
				'value'       => '1',
				'class'       => array( Gr_Configuration::CSS_MARKETING_CONSENT_WRAPPER_CLASS ),
				'input_class' => array( Gr_Configuration::CSS_MARKETING_CONSENT_CHECKBOX_CLASS ),
				'label_class' => array( Gr_Configuration::CSS_MARKETING_CONSENT_LABEL_CLASS ),
			),
			isset( $_POST[ $marketing_consent_key ] ) ? sanitize_text_field( $_POST[ $marketing_consent_key ] ) : ''
		);
	}

	public function handle_customer_upsert( int $user_id ): void {

		if ( false === self::is_woo_commerce_installed() ) {
			return;
		}

		$customer = new WC_Customer( $user_id );

		$handler = new Customer_Upsert_Handler(
			$this->gr_configuration,
			$this->gr_hook_service,
			$this->logger
		);

		$handler->handle( $customer );
	}

	public function handle_customer_upsert_in_admin( int $user_id ): void {

		if ( false === is_admin() || false === self::is_woo_commerce_installed() ) {
			return;
		}

		$customer = new WC_Customer( $user_id );

		$handler = new Customer_Upsert_Handler(
			$this->gr_configuration,
			$this->gr_hook_service,
			$this->logger
		);

		$handler->handle( $customer );
	}

	public function rebuild_cart(): bool {

		if ( false === self::is_woo_commerce_installed() ) {
			return false;
		}

		$cart = WC()->cart;

		if ( empty( $cart ) ) {
			return false;
		}

		$gr_cart = $_GET['grcart'] ?? null;
		if ( empty( $gr_cart ) ) {
			return false;
		}

        // phpcs:ignore
        $decoded_gr_cart = json_decode( base64_decode( $gr_cart ), true );
		if ( ! $decoded_gr_cart || empty( $decoded_gr_cart['cartItems'] ) || empty( $decoded_gr_cart['cartId'] ) ) {
			return wp_safe_redirect( wc_get_cart_url() );
		}

		$cart->empty_cart();
		foreach ( $decoded_gr_cart['cartItems'] as $item ) {
			$cart->add_to_cart( $item['p'], $item['q'], $item['v'] );
		}

		$this->gr_cart_service->set_cart_id( $decoded_gr_cart['cartId'] );

		return wp_safe_redirect( wc_get_cart_url() );
	}

	public function add_updated_at_filter( $args, $request ) {
		$filter_name = Gr_Configuration::USER_UPDATED_AFTER_FILTER_NAME;
		if ( ! empty( $request[ $filter_name ] ) ) {
			$timestamp            = sanitize_text_field( strtotime( $request[ $filter_name ] ) );
			$args['meta_query'][] = array(
				'relation' => 'OR',
				array(
					'key'     => 'last_update',
					'value'   => $timestamp,
					'compare' => '>',
					'type'    => 'numeric',
				),
				array(
					'key'     => 'last_update',
					'compare' => 'NOT EXISTS',
				),
			);
		}
		return $args;
	}

	public function handle_woocommerce_init(): void {
		$this->add_woocommerce_marketing_consent_checkbox_for_checkout_blocks();
	}

	public function handle_woocommerce_store_api_checkout_update_customer( WC_Customer $customer, WP_REST_Request $request ): void {
		$params                  = $request->get_params();
		$marketing_consent_field = Gr_Configuration::CHECKOUT_FIELD_MARKETING_CONSENT;

		if ( false === array_key_exists( 'additional_fields', $params ) || false === array_key_exists( $marketing_consent_field, $params['additional_fields'] ) ) {
			return;
		}

		$marketing_consent = $params['additional_fields'][ $marketing_consent_field ];

		Gr_User_Marketing_Consent_Buffer::add_user_marketing_consent( $marketing_consent );
	}

	private function add_woocommerce_marketing_consent_checkbox_for_checkout_blocks(): void {
		if (
			is_admin()
			|| ! function_exists( 'woocommerce_register_additional_checkout_field' )
			|| is_user_logged_in()
		) {
			return;
		}

		$marketing_consent_text = $this->gr_configuration->get_marketing_consent_text();

		if ( empty( $marketing_consent_text ) ) {
			return;
		}

		woocommerce_register_additional_checkout_field(
			array(
				'id'       => Gr_Configuration::CHECKOUT_FIELD_MARKETING_CONSENT,
				'label'    => esc_attr( $marketing_consent_text ),
				'location' => 'order',
				'type'     => 'checkbox',
				'required' => false,
			)
		);
	}
}
