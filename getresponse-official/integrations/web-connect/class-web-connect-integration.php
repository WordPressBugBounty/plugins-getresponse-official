<?php

declare(strict_types=1);

namespace GR\Wordpress\Integrations\WebConnect;

use GR\Wordpress\Core\Gr_Configuration;
use GR\Wordpress\Integrations\Integration;
use WC_Cart;
use WC_Order;
use WC_Product;

class Web_Connect_Integration implements Integration {

    private Gr_Configuration $gr_configuration;

    private Cart_Service $cart_service;

    private Order_Service $order_service;

    public function __construct(
        Gr_Configuration $gr_configuration,
        Cart_Service $cart_service,
        Order_Service $order_service
    ) {
        $this->gr_configuration = $gr_configuration;
        $this->cart_service     = $cart_service;
        $this->order_service    = $order_service;
    }

    public function init(): void {
        add_action( 'wp_enqueue_scripts', [ $this, 'inject_base_snippet' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'inject_category_view_snippet' ] );
        add_filter( 'woocommerce_after_single_product', [ $this, 'inject_product_view_snippet' ] );
        add_action( 'wp_enqueue_scripts', [ $this, 'inject_web_connect_buffered_events' ] );
        add_action( 'gr4wp_cart_upsert', [ $this, 'handle_cart_upsert' ] );
        add_action( 'gr4wp_order_upsert', [ $this, 'handle_order_upsert' ] );
    }

    public function handle_cart_upsert( WC_Cart $cart ) : void {

        $this->cart_service->add_cart_to_buffer( $cart );
    }

    public function handle_order_upsert( ?WC_Order $order ) : void {
        if ( null === $order ) {
            return;
        }

        $this->order_service->add_order_to_buffer( $order );
    }

    public function inject_base_snippet(): void {
        $web_connect_snippet = $this->gr_configuration->get_web_connect_snippet();

        if ( $web_connect_snippet === '' ) {
            return;
        }

        preg_match( '/(https:\/\/[a-zA-Z0-9.-]+\/script\/[a-z0-9\-]+\/ga\.js)/', $web_connect_snippet, $matches );

        if ( empty( $matches ) ) {
            return;
        }

        $user = wp_get_current_user();

        $ga_custom_code = ! empty( $user->user_email )
            ? "GrTracking('setUserId', '" . $user->user_email . "');"
            : "GrTracking('push');";

        wp_register_script( 'gr-tracking-code', false, array(), array(), false );
        wp_enqueue_script( 'gr-tracking-code' );

        wp_add_inline_script(
            'gr-tracking-code',
            "(function(m, o, n, t, e, r, _){
                  m['__GetResponseAnalyticsObject'] = e;m[e] = m[e] || function() {(m[e].q = m[e].q || []).push(arguments)};
                  r = o.createElement(n);_ = o.getElementsByTagName(n)[0];r.async = 1;r.src = t;r.setAttribute('crossorigin', 'use-credentials');_.parentNode .insertBefore(r, _);
              })(window, document, 'script', '" . esc_url( $matches[0] ) . "', 'GrTracking');
        
               GrTracking('setDomain', 'auto');
               " . $ga_custom_code
        );
    }

    public function inject_category_view_snippet(): void {

        if ( ! function_exists( 'is_product_category' ) || ! is_product_category() ) {
            return;
        }

        $web_connect_snippet = $this->gr_configuration->get_web_connect_snippet();

        if ( $web_connect_snippet === '' ) {
            return;
        }

        $getresponse_shop_id = $this->gr_configuration->get_getresponse_shop_id();

        if ( $getresponse_shop_id === '' ) {
            return;
        }

        $category = get_queried_object();
        if ( ! $category ) {
            return;
        }

        $view_category_payload = [
            'shop' => [ 'id' => $getresponse_shop_id ],
            'id'   => (string) $category->term_id,
            'name' => $category->name,
        ];

        wp_register_script( 'gr-category-view', false, array(), array(), true );
        wp_enqueue_script( 'gr-category-view' );

        wp_add_inline_script(
            'gr-category-view',
            "GrTracking('importScript', 'ec');
             GrTracking('viewCategory', " . wp_json_encode( $view_category_payload ) . ');'
        );
    }

    public function inject_product_view_snippet(): void {

        $web_connect_snippet = $this->gr_configuration->get_web_connect_snippet();

        if ( $web_connect_snippet === '' ) {
            return;
        }

        $getresponse_shop_id = $this->gr_configuration->get_getresponse_shop_id();

        if ( $getresponse_shop_id === '' ) {
            return;
        }

        global $product;
        $view_item_payload = [
            'shop'       => [ 'id' => $getresponse_shop_id ],
            'product'    => [
                'id'       => (string) $product->get_id(),
                'name'     => $product->get_name(),
                'sku'      => $product->get_sku(),
                'vendor'   => '',
                'price'    => $this->get_product_price( $product ),
                'currency' => get_option( 'woocommerce_currency' ),
            ],
            'categories' => $this->get_categories( $product ),
        ];

        wp_register_script( 'gr-product-view', false, array(), array(), true );
        wp_enqueue_script( 'gr-product-view' );

        wp_add_inline_script(
            'gr-product-view',
            "GrTracking('importScript', 'ec');
             GrTracking('viewItem', " . wp_json_encode( $view_item_payload ) . ');'
        );
    }

    public function inject_web_connect_buffered_events(): void {

        $web_connect_snippet = $this->gr_configuration->get_web_connect_snippet();

        if ( $web_connect_snippet === '' ) {
            return;
        }

        $getresponse_shop_id = $this->gr_configuration->get_getresponse_shop_id();

        if ( $getresponse_shop_id === '' ) {
            return;
        }

        $web_connect_script = '';
        $buffered_cart      = [];
        $buffered_order     = [];

        if ( $_SERVER['REQUEST_METHOD'] !== 'POST' ) {
            $buffered_cart  = $this->cart_service->get_cart_from_buffer();
            $buffered_order = $this->order_service->get_order_from_buffer();
        }

        if ( ! empty( $buffered_cart ) ) {
            $web_connect_script .= PHP_EOL . "GrTracking('cartUpdate', " . wp_json_encode( $buffered_cart ) . ');';
        }

        if ( ! empty( $buffered_order ) ) {
            $web_connect_script .= PHP_EOL . "GrTracking('orderPlaced', " . wp_json_encode( $buffered_order ) . ');';
        }

        wp_register_script( 'gr-web-connect-events', false, [], 1, true );
        wp_enqueue_script( 'gr-web-connect-events' );

        wp_add_inline_script(
            'gr-web-connect-events',
            "GrTracking('importScript', 'ec'); " . PHP_EOL . $web_connect_script
        );
    }

    private function get_categories( WC_Product $product ): array {
        $categories = [];

        $terms = get_the_terms( $product->get_id(), 'product_cat' );

        if ( empty( $terms ) ) {
            return $categories;
        }

        foreach ( $terms as $category ) {
            $categories[] = [
                'id'   => (string) $category->term_id,
                'name' => $category->name,
            ];
        }
        return $categories;
    }

    private function get_product_price( WC_Product $product ): string {
        foreach ( $product->get_children() as $product_children_id ) {
            $child_product = wc_get_product( $product_children_id );
            if ( 'publish' === $child_product->get_status() ) {
                return (string) $child_product->get_price();
            }
        }

        return (string) $product->get_price();
    }
}
