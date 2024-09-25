<?php

declare(strict_types=1);

namespace GR\Wordpress\Integrations\Woocommerce;

use GR\Wordpress\Core\Functions;
use GR\Wordpress\Core\Gr_Configuration;
use GR\Wordpress\Core\Hook\Gr_Hook_Service;
use GR\Wordpress\Integrations\Integration;
use Psr\Log\LoggerInterface;
use WC_Customer;
use WC_Order;
use WC_Product;

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

        add_action( 'woocommerce_new_product', [ $this, 'handle_product_upsert' ] );
        add_action( 'woocommerce_update_product', [ $this, 'handle_product_upsert' ] );

        add_action( 'woocommerce_product_set_stock', [ $this, 'handle_product_stock_change' ] );
        add_action( 'woocommerce_variation_set_stock', [ $this, 'handle_product_stock_change' ] );

        add_action( 'woocommerce_order_status_pending', [ $this, 'handle_order_upsert' ], 10, 2 );
        add_action( 'woocommerce_order_status_on-hold', [ $this, 'handle_order_upsert' ], 10, 2 );
        add_action( 'woocommerce_order_status_failed', [ $this, 'handle_order_upsert' ], 10, 2 );
        add_action( 'woocommerce_order_status_processing', [ $this, 'handle_order_upsert' ], 10, 2 );
        add_action( 'woocommerce_order_status_completed', [ $this, 'handle_order_upsert' ], 10, 2 );
        add_action( 'woocommerce_order_status_refunded', [ $this, 'handle_order_upsert' ], 10, 2 );
        add_action( 'woocommerce_order_status_cancelled', [ $this, 'handle_order_upsert' ], 10, 2 );

        add_action( 'woocommerce_add_to_cart', [ $this, 'handle_cart_upsert' ], 30, );
        add_action( 'woocommerce_cart_item_removed', [ $this, 'handle_cart_upsert' ], 30, );
        add_action( 'woocommerce_update_cart_action_cart_updated', [ $this, 'handle_cart_upsert' ], 30, );

        add_action( 'woocommerce_register_form', [ $this, 'add_woocommerce_marketing_consent_checkbox' ] );
        add_action( 'woocommerce_after_order_notes', [ $this, 'add_marketing_consent_checkbox' ] );

        add_action( 'woocommerce_update_customer', [ $this, 'handle_customer_upsert' ], 10 );

        add_action( 'profile_update', [ $this, 'handle_customer_upsert_in_admin' ] );
    }

    public function handle_product_upsert( int $product_id ): void {
        $handler = new Product_Upsert_Handler( $this->gr_configuration, $this->gr_hook_service, $this->logger );
        $handler->handle( $product_id );
    }

    public function handle_product_stock_change( WC_Product $product ): void {
        $handler = new Product_Upsert_Handler( $this->gr_configuration, $this->gr_hook_service, $this->logger );
        $handler->handle( $product->get_id() );
    }

    public function handle_order_upsert( ?int $order_id, WC_Order $order ): void {

        do_action( 'gr4wp_order_upsert', $order );

        $handler = new Order_Upsert_Handler(
            $this->gr_configuration,
            $this->gr_hook_service,
            $this->gr_cart_service,
            $this->logger
        );
        $handler->handle( $order );
    }

    public function handle_cart_upsert(): void {

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
            [
                'type'     => 'checkbox',
                'required' => false,
                'label'    => esc_attr( $marketing_consent_text ),
                'value'    => '1',
            ],
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
}
