<?php

declare(strict_types=1);

namespace GR\Wordpress\Integrations\Woocommerce;

use Exception;
use GR\Wordpress\Core\Functions;
use GR\Wordpress\Core\Gr_Configuration;
use GR\Wordpress\Core\Hook\Gr_Hook_Exception;
use GR\Wordpress\Core\Hook\Gr_Hook_Service;
use GR\Wordpress\Core\Hook\Model\Address_Model;
use GR\Wordpress\Core\Hook\Model\Cart_Model;
use GR\Wordpress\Core\Hook\Model\Line_Model;
use GR\Wordpress\Core\Hook\Model\User_Model;
use Psr\Log\LoggerInterface;
use WC_Cart;

class Cart_Upsert_Handler {

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

    public function handle( WC_Cart $cart ): void {
        try {
            if ( ! $this->gr_configuration->is_full_ecommerce_live_sync_active() ) {
                return;
            }

            $cart_id = $this->gr_cart_service->get_cart_id();

            $this->send_callback( $cart_id, $cart );
        } catch ( Exception $e ) {
            $this->logger->error( 'Cart handler error', Functions::get_error_context( $e ) );
        }
    }

    private function get_customer( WC_Cart $cart ): User_Model {
        $customer = $cart->get_customer();

        $marketing_consent = (bool) get_user_meta( $customer->get_id(), Gr_Configuration::MARKETING_CONSENT_META_NAME, true );

        $address_model = new Address_Model(
            $customer->get_billing_country() ?? '',
            $customer->get_billing_first_name() ?? '',
            $customer->get_billing_last_name() ?? '',
            $customer->get_billing_address_1() ?? '',
            $customer->get_billing_address_2(),
            $customer->get_billing_city() ?? '',
            $customer->get_billing_postcode() ?? '',
            $customer->get_billing_state(),
            null,
            $customer->get_billing_phone(),
            $customer->get_billing_company()
        );

        return new User_Model(
            $customer->get_id(),
            $customer->get_email(),
            $marketing_consent,
            $customer->get_first_name(),
            $customer->get_last_name(),
            $address_model
        );
    }

    private function get_products( WC_Cart $cart ): array {
        $lines = [];

        foreach ( $cart->get_cart() as $cart_item ) {
            $product = $cart_item['data'];

            $variant_id = 0 !== (int) $cart_item['variation_id'] ? $cart_item['variation_id'] : $cart_item['product_id'];

            $lines[] = new Line_Model(
                (int) $variant_id,
                round( wc_get_price_including_tax( $product, [ 'price' => $product->get_price() ] ), 2 ),
                round( wc_get_price_including_tax( $product, [ 'price' => $product->get_price() ] ), 2 ),
                (int) round( $cart_item['quantity'] ),
                $cart_item['data']->get_sku()
            );
        }

        return $lines;
    }

    /**
     * @throws Gr_Hook_Exception
     */
    private function send_callback( int $cart_id, ?WC_Cart $cart ): void {
        if ( null === $cart ) {
            return;
        }

        $model = new Cart_Model(
            $cart_id,
            $this->get_customer( $cart ),
            $this->get_products( $cart ),
            round( (float) $cart->get_total( '' ), 2 ),
            round( (float) $cart->get_total( '' ), 2 ),
            get_woocommerce_currency(),
            wc_get_cart_url()
        );

        if ( ! $model->is_valuable() ) {
            return;
        }

        $this->gr_hook_service->send_callback( $this->gr_configuration, $model );
    }
}
