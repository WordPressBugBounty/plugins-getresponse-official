<?php

declare(strict_types=1);

namespace GR\Wordpress\Integrations\Recommendation;

use GR\Wordpress\Core\Gr_Configuration;
use GR\Wordpress\Integrations\Integration;
use WC_Product;

class Recommendation_Integration implements Integration {

    private Gr_Configuration $gr_configuration;

    public function __construct( Gr_Configuration $gr_configuration ) {
        $this->gr_configuration = $gr_configuration;
    }

    public function init(): void {
        add_action( 'wp_enqueue_scripts', array( $this, 'inject_base_snippet' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'inject_home_page_snippet' ) );
        add_filter( 'woocommerce_after_single_product', array( $this, 'inject_product_page_snippet' ) );
    }

    public function inject_base_snippet(): void {
        $recommendation_snippet = $this->gr_configuration->get_recommendation_snippet();
        if ( $recommendation_snippet === '' ) {
            return;
        }

        wp_enqueue_script( 'gr-reco-snippet', $recommendation_snippet, [], 1, true );
        wp_script_add_data( 'gr-reco-snippet', 'async', true );
        add_filter(
            'script_loader_tag',
            function ( $tag, $handle ) {
                if ( 'gr-reco-snippet' !== $handle ) {
                    return $tag;
                }
                return str_replace( ' src', ' async src', $tag );
            },
            10,
            2
        );

        $recommendation_payload = null;

        if ( function_exists( 'is_product_category' ) && is_product_category() ) {
            $category = get_queried_object();
            if ( $category ) {
                $recommendation_payload = [
                    'pageType' => 'category',
                    'pageData' => [],
                ];
            }
        }

        if ( function_exists( 'is_cart' ) && is_cart() ) {
            $recommendation_payload = [
                'pageType' => 'cart',
                'pageData' => [],
            ];
        }

        if ( $recommendation_payload !== null ) {
            $this->add_recommendation_payload_script( $recommendation_payload );
        }
    }

    public function inject_home_page_snippet(): void {
        if ( ! is_home() && ! is_front_page() ) {
            return;
        }

        if ( ! $this->is_recommendation_snippet() ) {
            return;
        }

        $recommendation_payload = [
            'pageType' => 'home',
            'pageData' => [],
        ];
        $this->add_recommendation_payload_script( $recommendation_payload );
    }

    public function inject_product_page_snippet(): void {

        if ( ! $this->is_recommendation_snippet() ) {
            return;
        }

        global $product;

        $variation = $this->get_product_or_first_variant( $product );

        $description = strlen( $product->get_description() ) > 30000
            ? substr( $product->get_description(), 0, 30000 - 3 ) . '...'
            : $product->get_description();

        $sale_price = $variation->is_on_sale() ? number_format( (float) $variation->get_regular_price(), 2, '.', '' ) : null;

        $terms = get_the_terms( $product->get_id(), 'product_cat' );

        $recommendation_payload = [
            'pageType' => 'product',
            'pageData' => [
                'productUrl'        => get_permalink( $variation->get_id() ),
                'pageUrl'           => get_permalink( $variation->get_id() ),
                'productExternalId' => $variation->get_id(),
                'productName'       => $variation->get_name(),
                'price'             => number_format( (float) $variation->get_price(), 2, '.', '' ),
                'imageUrl'          => wp_get_attachment_image_src( $variation->get_image_id(), 'full' )[0],
                'description'       => wp_strip_all_tags( $description, true ),
                'category'          => ! empty( $terms ) ? $this->get_category( $terms[0] ) : '',
                'available'         => $variation->is_in_stock(),
                'sku'               => $variation->get_sku(),
                'attribute1'        => $sale_price > 0 ? $sale_price : null,
                'attribute2'        => $variation->is_on_sale() ? esc_html__( 'Sale!', 'woocommerce' ) : null,
                'attribute3'        => wp_json_encode( $variation->get_attributes() ),
            ],
        ];

        $this->add_recommendation_payload_script( $recommendation_payload );
    }

    private function get_product_or_first_variant( WC_Product $product ): WC_Product {
        if ( $product->is_type( 'variable' ) ) {
            foreach ( $product->get_children() as $product_children_id ) {
                $child_product = wc_get_product( $product_children_id );
                if ( 'publish' === $child_product->get_status() ) {
                    return $child_product;
                }
            }
        }
        return $product;
    }

    private function add_recommendation_payload_script( array $recommendation_payload ): void {
        wp_register_script( 'gr-reco-payload', false, [], [], true );
        wp_enqueue_script( 'gr-reco-payload' );
        wp_add_inline_script(
            'gr-reco-payload',
            'const recommendationPayload = ' . wp_json_encode( $recommendation_payload ) . ';'
        );
    }

    private function get_category( $category, $categories = [] ): string {
        $categories[] = $category->name;

        if ( $category->parent > 0 ) {
            $product_parent_categories = get_ancestors( $category->term_id, 'product_cat' );
            $parent_term               = get_term_by( 'id', $product_parent_categories[0], 'product_cat' );

            return self::get_category( $parent_term, $categories );
        }

        return implode( ' > ', array_reverse( $categories ) );
    }

    private function is_recommendation_snippet(): bool {
        return $this->gr_configuration->get_recommendation_snippet() !== '';
    }
}
