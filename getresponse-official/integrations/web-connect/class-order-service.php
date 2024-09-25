<?php

declare(strict_types=1);

namespace GR\Wordpress\Integrations\WebConnect;

use GR\Wordpress\Core\Gr_Configuration;
use GR\Wordpress\Integrations\WebConnect\Model\Buffer\Category_Model;
use GR\Wordpress\Integrations\WebConnect\Model\Buffer\Order_Model as Buffer_Order_Model;
use GR\Wordpress\Integrations\WebConnect\Model\Buffer\Product_Model;
use GR\Wordpress\Integrations\Woocommerce\Gr_Cart_Service;
use WC_Order;
use WC_Order_Item_Product;

class Order_Service {

    private Gr_Configuration $gr_configuration;
    private Web_Connect_Buffer_Service $buffer_service;

    private Gr_Cart_Service $gr_cart_service;

    public function __construct(
        Gr_Configuration $gr_configuration,
        Gr_Cart_Service $gr_cart_service,
        Web_Connect_Buffer_Service $buffer_service
    ) {
        $this->gr_configuration = $gr_configuration;
        $this->gr_cart_service  = $gr_cart_service;
        $this->buffer_service   = $buffer_service;
    }

    public function add_order_to_buffer( WC_Order $order ) : void {

        if ( $this->gr_configuration->get_web_connect_snippet() === '' ) {
            return;
        }

        $cart_id = $this->gr_cart_service->get_cart_id();

        $model = new Buffer_Order_Model(
            $order->get_id(),
            $cart_id,
            round( (float) $order->get_total(), 2 ),
            $order->get_currency(),
            $this->get_buffer_products( $order ),
        );

        $this->buffer_service->add_order_to_buffer( $model );
    }

    public function get_order_from_buffer(): array {
        return $this->buffer_service->get_order_from_buffer();
    }

    private function get_buffer_products( WC_Order $order ): array {
        $products = [];

        /** @var WC_Order_Item_Product $item */
        foreach ( $order->get_items() as $item ) {

            $product_price = ( (float) $item->get_total() + (float) $item->get_total_tax() ) / $item->get_quantity();

            $products[] = new Product_Model(
                $item->get_id(),
                $item->get_name(),
                $product_price,
                $item->get_product()->get_sku(),
                get_woocommerce_currency(),
                (int) round( $item->get_quantity() ),
                $this->get_product_categories( $item )
            );
        }

        return $products;
    }

    private function get_product_categories( WC_Order_Item_Product $product ): array {
        $categories = [];

        foreach ( $product->get_product()->get_category_ids() as $category_id ) {
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
