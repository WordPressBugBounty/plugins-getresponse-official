<?php

declare(strict_types=1);

namespace GR\Wordpress\Integrations\Woocommerce;

use Exception;
use GR\Wordpress\Core\Functions;
use GR\Wordpress\Core\Gr_Configuration;
use GR\Wordpress\Core\Hook\Gr_Hook_Service;
use GR\Wordpress\Core\Hook\Gr_Image_Url;
use GR\Wordpress\Core\Hook\Model\Category_Model;
use GR\Wordpress\Core\Hook\Model\Image_Model;
use GR\Wordpress\Core\Hook\Model\Product_Model;
use GR\Wordpress\Core\Hook\Model\Variant_Model;
use Psr\Log\LoggerInterface;
use WC_Product;
use WC_Product_Variation;

class Product_Upsert_Handler {

    private const PRODUCT_TYPE_SIMPLE   = 'simple';
    private const PRODUCT_TYPE_VARIABLE = 'variable';
    private const PRODUCT_TYPE_EXTERNAL = 'external';

    private Gr_Configuration $gr_configuration;
    private Gr_Hook_Service $hook_service;
    private LoggerInterface $logger;

    public function __construct( Gr_Configuration $gr_configuration, Gr_Hook_Service $hook_service, LoggerInterface $logger ) {
        $this->gr_configuration = $gr_configuration;
        $this->hook_service     = $hook_service;
        $this->logger           = $logger;
    }

    public function handle( int $product_id ): void {

        try {
            if ( ! $this->gr_configuration->is_full_ecommerce_live_sync_active() ) {
                return;
            }

            $product = wc_get_product( $product_id );

            if ( 'draft' === $product->get_status() ) {
                return;
            }

            if ( ! in_array(
                $product->get_type(),
                [ self::PRODUCT_TYPE_SIMPLE, self::PRODUCT_TYPE_VARIABLE, self::PRODUCT_TYPE_EXTERNAL ],
                true
            ) ) {
                return;
            }

            $variants = $product->is_type( self::PRODUCT_TYPE_VARIABLE ) ?
                $this->get_variable_product_variants( $product ) :
                $this->get_simple_product_variant( $product );

            $model = new Product_Model(
                $product->get_id(),
                $product->get_name(),
                $product->get_type(),
                $product->get_permalink(),
                '',
                $this->get_product_categories( $product ),
                $variants,
                $product->get_date_created() ? $product->get_date_created()->date_i18n( DATE_ATOM ) : gmdate( DATE_ATOM ),
                null === $product->get_date_modified() ? null : $product->get_date_modified()->date_i18n( DATE_ATOM ),
                $product->get_status()
            );

            $this->hook_service->send_callback( $this->gr_configuration, $model );
        } catch ( Exception $e ) {
            $this->logger->error( 'Product handler error', Functions::get_error_context( $e ) );
        }
    }

    private function get_product_categories( WC_Product $product ): array {
        $categories = [];

        foreach ( $product->get_category_ids() as $category_id ) {
            $term = get_term_by( 'id', $category_id, 'product_cat' );
            if ( $term ) {
                $categories[] = new Category_Model(
                    $term->term_id,
                    $term->parent,
                    $term->name
                );
            }
        }

        return $categories;
    }

    private function get_variable_product_variants( WC_Product $product ): array {
        $variants = [];

        foreach ( $product->get_available_variations() as $available_variation ) {
            $variation = new WC_Product_Variation( $available_variation['variation_id'] );

            $variants[] = new Variant_Model(
                $variation->get_id(),
                $variation->get_name(),
                $variation->get_sku(),
                round( (float) $variation->get_regular_price(), 2 ),
                round( (float) $variation->get_regular_price(), 2 ),
                null,
                null,
                (int) $variation->get_stock_quantity(),
                $variation->get_permalink(),
                null,
                null,
                $variation->get_short_description(),
                $variation->get_description(),
                $this->get_product_images( $variation ),
                $variation->get_status(),
                '' === $variation->get_sale_price() ? null : round( (float) $variation->get_sale_price(), 2 ),
                null === $variation->get_date_on_sale_from() ? null : $variation->get_date_on_sale_from()->date_i18n( DATE_ATOM ),
                null === $variation->get_date_on_sale_to() ? null : $variation->get_date_on_sale_to()->date_i18n( DATE_ATOM )
            );
        }

        if ( empty( $variants ) ) {
            return $this->get_simple_product_variant( $product );
        }

        return $variants;
    }

    private function get_simple_product_variant( WC_Product $product ): array {
        $variant = new Variant_Model(
            $product->get_id(),
            $product->get_name(),
            $product->get_sku(),
            round( (float) $product->get_regular_price(), 2 ),
            round( (float) $product->get_regular_price(), 2 ),
            null,
            null,
            (int) $product->get_stock_quantity(),
            $product->get_permalink(),
            null,
            null,
            $product->get_short_description(),
            $product->get_description(),
            $this->get_product_images( $product ),
            $product->get_status(),
            '' === $product->get_sale_price() ? null : round( (float) $product->get_sale_price(), 2 ),
            null === $product->get_date_on_sale_from() ? null : $product->get_date_on_sale_from()->date_i18n( DATE_ATOM ),
            null === $product->get_date_on_sale_to() ? null : $product->get_date_on_sale_to()->date_i18n( DATE_ATOM )
        );

        return [ $variant ];
    }

    /**
     * @return array<Image_Model>
     */
    private function get_product_images( WC_Product $product ): array {
        $image_number = 0;
        $image_model  = [];

        if ( ! empty( $product->get_image_id() ) ) {
            $image_url = new Gr_Image_Url( wp_get_attachment_url( $product->get_image_id() ) );

            if ( $image_url->is_valid() ) {
                $image_model[] = new Image_Model( $image_url->get_url(), $image_number++ );
            }
        }

        $gallery_image_ids = $product->get_gallery_image_ids();

        foreach ( $gallery_image_ids as $image_id ) {
            $image_url = new Gr_Image_Url( wp_get_attachment_url( $image_id ) );
            if ( $image_url->is_valid() ) {
                $image_model[] = new Image_Model( $image_url->get_url(), $image_number++ );
            }
        }

        return $image_model;
    }
}
