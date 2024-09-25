<?php

declare(strict_types=1);

namespace GR\Wordpress\Integrations\Woocommerce;

use Exception;
use GR\Wordpress\Core\Functions;
use GR\Wordpress\Core\Gr_Configuration;
use GR\Wordpress\Core\Hook\Gr_Hook_Exception;
use GR\Wordpress\Core\Hook\Gr_Hook_Service;
use GR\Wordpress\Core\Hook\Model\Address_Model;
use GR\Wordpress\Core\Hook\Model\Line_Model;
use GR\Wordpress\Core\Hook\Model\Order_Model;
use GR\Wordpress\Core\Hook\Model\User_Model;
use Psr\Log\LoggerInterface;
use WC_Order;
use WC_Order_Item_Product;

class Order_Upsert_Handler {

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

    public function handle( WC_Order $order ): void {

        try {
            if ( ! $this->gr_configuration->is_full_ecommerce_live_sync_active() ) {
                return;
            }

            $cart_id = $this->gr_cart_service->get_cart_id_and_reset();

            $this->send_callback( $cart_id, $order );
        } catch ( Exception $e ) {
            $this->logger->error( 'Order handler error', Functions::get_error_context( $e ) );
        }
    }

    private function get_customer( WC_Order $order ): User_Model {
        $marketing_consent_key = Gr_Configuration::MARKETING_CONSENT_META_NAME;
        $customer_id           = $order->get_customer_id();

        if ( 0 === $customer_id ) {

            $raw_billing_address = $order->get_address();
            $billing_address     = $this->get_address( $order, 'billing' );
            $marketing_consent   = isset( $_POST[ $marketing_consent_key ] ) ? sanitize_text_field( $_POST[ $marketing_consent_key ] ) : false;

            return new User_Model(
                0,
                $raw_billing_address['email'],
                (bool) $marketing_consent,
                $raw_billing_address['first_name'],
                $raw_billing_address['last_name'],
                $billing_address
            );
        }

        $user_data = get_userdata( $customer_id );

        $first_name        = get_user_meta( $customer_id, 'first_name', true );
        $last_name         = get_user_meta( $customer_id, 'last_name', true );
        $marketing_consent = (bool) get_user_meta( $customer_id, $marketing_consent_key, true );

        $billing_first_name = get_user_meta( $customer_id, 'billing_first_name', true );
        $billing_last_name  = get_user_meta( $customer_id, 'billing_last_name', true );
        $billing_country    = get_user_meta( $customer_id, 'billing_country', true );
        $billing_address_1  = get_user_meta( $customer_id, 'billing_address_1', true );
        $billing_address_2  = get_user_meta( $customer_id, 'billing_address_2', true );
        $billing_city       = get_user_meta( $customer_id, 'billing_city', true );
        $billing_postcode   = get_user_meta( $customer_id, 'billing_postcode', true );
        $billing_state      = get_user_meta( $customer_id, 'billing_state', true );
        $billing_phone      = get_user_meta( $customer_id, 'billing_phone', true );
        $billing_company    = get_user_meta( $customer_id, 'billing_company', true );

        $address_model = new Address_Model(
            $billing_country ?? '',
            $billing_first_name ?? '',
            $billing_last_name ?? '',
            $billing_address_1 ?? '',
            $billing_address_2,
            $billing_city ?? '',
            $billing_postcode ?? '',
            $billing_state,
            null,
            $billing_phone,
            $billing_company
        );

        return new User_Model(
            $customer_id,
            $user_data->user_email,
            $marketing_consent,
            $first_name,
            $last_name,
            $address_model
        );
    }

    private function get_callback_products( WC_Order $order ): array {
        $lines = [];

        /** @var WC_Order_Item_Product $item */
        foreach ( $order->get_items() as $item ) {
            $product = $item->get_product();
            if ( $product ) {
                $product_price = ( (float) $item->get_total() + (float) $item->get_total_tax() ) / $item->get_quantity();
                $lines[]       = new Line_Model(
                    (int) $product->get_id(),
                    round( $product_price, 2 ),
                    round( $product_price, 2 ),
                    (int) round( $item->get_quantity() ),
                    $product->get_sku()
                );
            }
        }

        return $lines;
    }

    private function get_address( WC_Order $order, string $type ): ?Address_Model {
        $address = $order->get_address( $type );

        if ( empty( $address['country'] ) || empty( $address['first_name'] ) || empty( $address['last_name'] ) ) {
            return null;
        }

        return Address_Model::fromRawData( $address );
    }

    /**
     * @throws Gr_Hook_Exception
     */
    private function send_callback( ?int $cart_id, WC_Order $order ): void {
        $model = new Order_Model(
            $order->get_id(),
            $order->get_order_number(),
            $cart_id,
            $order->get_billing_email(),
            $this->get_customer( $order ),
            $this->get_callback_products( $order ),
            $order->get_view_order_url(),
            round( (float) $order->get_total(), 2 ),
            round( (float) $order->get_total(), 2 ),
            round( (float) $order->get_shipping_total(), 2 ),
            $order->get_currency(),
            $order->get_status(),
            $this->get_address( $order, 'shipping' ),
            $this->get_address( $order, 'billing' ),
            $order->get_date_created()->date_i18n( DATE_ATOM ),
            null === $order->get_date_modified() ? null : $order->get_date_modified()->date_i18n( DATE_ATOM )
        );

        $this->gr_hook_service->send_callback( $this->gr_configuration, $model );
    }
}
