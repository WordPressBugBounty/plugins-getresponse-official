<?php

declare(strict_types=1);

namespace GR\Wordpress\Integrations\WebConnect;

use GR\Wordpress\Core\Gr_Configuration;
use GR\Wordpress\Integrations\WebConnect\Model\Buffer\Cart_Model as Buffer_Cart_Model;
use GR\Wordpress\Integrations\WebConnect\Model\Buffer\Category_Model;
use GR\Wordpress\Integrations\WebConnect\Model\Buffer\Product_Model;
use GR\Wordpress\Integrations\Woocommerce\Gr_Cart_Service;
use WC_Cart;
use WC_Product;

class Cart_Service {

    private Gr_Configuration $gr_configuration;

    private Web_Connect_Buffer_Service $buffer_service;

    private Gr_Cart_Service $gr_cart_service;

    public function __construct(
        Gr_Configuration $gr_configuration,
        Gr_Cart_Service $gr_cart_service,
        Web_Connect_Buffer_Service $buffer_service
    ) {
         $this->gr_configuration = $gr_configuration;
        $this->gr_cart_service   = $gr_cart_service;
        $this->buffer_service    = $buffer_service;
    }

    public function add_cart_to_buffer( WC_Cart $cart ): void {
        if ( $this->gr_configuration->get_web_connect_snippet() === '' ) {
            return;
        }

        $cart_id = $this->gr_cart_service->get_cart_id();

        $this->add_event_to_buffer( $cart_id, $cart );
    }

    public function get_cart_from_buffer(): array {
        return $this->buffer_service->get_cart_from_buffer();
    }

    private function add_event_to_buffer( int $cart_id, WC_Cart $cart ): void {
        $cart->calculate_totals();

        $model = new Buffer_Cart_Model(
            $cart_id,
            round( (float) $cart->get_total( '' ), 2 ),
            get_woocommerce_currency(),
            wc_get_cart_url(),
            $this->get_products( $cart ),
        );

        $this->buffer_service->add_cart_to_buffer( $model );
    }

    private function get_products( WC_Cart $cart ): array {
        $products = [];

        foreach ( $cart->get_cart() as $cart_item ) {
            /** @var WC_Product $product */
            $product = $cart_item['data'];

            $products[] = new Product_Model(
                $product->get_id(),
                $product->get_name(),
                round( wc_get_price_including_tax( $product, [ 'price' => $product->get_price() ] ), 2 ),
                $cart_item['data']->get_sku(),
                get_woocommerce_currency(),
                (int) round( $cart_item['quantity'] ),
                $this->get_product_categories( $product )
            );
        }

        return $products;
    }

    private function get_product_categories( WC_Product $product ): array {
        $categories = [];

        foreach ( $product->get_category_ids() as $category_id ) {
            $term = get_term_by( 'id', $category_id, 'product_cat' );
            if ( $term ) {
                $categories[] = new Category_Model(
                    $term->term_id,
                    $term->name
                );
            }
        }

        return $categories;
    }
}
