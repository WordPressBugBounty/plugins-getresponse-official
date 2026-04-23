<?php

declare(strict_types=1);

namespace GetResponse\WordPress\Integrations\Woocommerce;

use GetResponse\WordPress\Core\Functions;
use GetResponse\WordPress\Core\Gr_Configuration;
use GetResponse\WordPress\Core\Hook\Gr_Hook_Service;
use GetResponse\WordPress\Core\Hook\Gr_Image_Url;
use GetResponse\WordPress\Core\Hook\Model\Category_Model;
use GetResponse\WordPress\Core\Hook\Model\Image_Model;
use GetResponse\WordPress\Core\Hook\Model\Product_Model;
use GetResponse\WordPress\Core\Hook\Model\Variant_Model;
use Psr\Log\LoggerInterface;
use Throwable;
use WC_Product;
use WC_Product_Variation;

class Product_Upsert_Handler {
	private const PRODUCT_TYPE_VARIABLE = 'variable';
	private const MAX_DESC_LENGTH       = 1000;

	private const NOT_ALLOWED_PRODUCT_TYPES = array(
		'variation',
	);

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

			if ( in_array( $product->get_type(), self::NOT_ALLOWED_PRODUCT_TYPES, true ) ) {
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
		} catch ( Throwable $e ) {
			$this->logger->error( 'Product handler error', Functions::get_error_context( $e ) );
		}
	}

	private function get_product_categories( WC_Product $product ): array {
		$categories = array();

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
		$variants = array();

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
				$this->reduce_description( $variation->get_short_description(), self::MAX_DESC_LENGTH ),
				$this->reduce_description( $variation->get_description(), self::MAX_DESC_LENGTH ),
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
			$this->reduce_description( $product->get_short_description(), self::MAX_DESC_LENGTH ),
			$this->reduce_description( $product->get_description(), self::MAX_DESC_LENGTH ),
			$this->get_product_images( $product ),
			$product->get_status(),
			'' === $product->get_sale_price() ? null : round( (float) $product->get_sale_price(), 2 ),
			null === $product->get_date_on_sale_from() ? null : $product->get_date_on_sale_from()->date_i18n( DATE_ATOM ),
			null === $product->get_date_on_sale_to() ? null : $product->get_date_on_sale_to()->date_i18n( DATE_ATOM )
		);

		return array( $variant );
	}

	private function reduce_description( string $description, int $max_length ): string {
		$clean_description = (string) preg_replace( '#<style(.*?)>(.*?)</style>#is', '', $description );
		$clean_description = (string) preg_replace( '#<script(.*?)>(.*?)</script>#is', '', $clean_description );
		$clean_description = html_entity_decode( $clean_description, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$clean_description = html_entity_decode( $clean_description, ENT_COMPAT );
		$clean_description = wp_strip_all_tags( $clean_description );
		$clean_description = trim( $clean_description );
		$clean_description = (string) preg_replace( '/\s+/', ' ', $clean_description );

		if ( mb_strlen( $clean_description ) <= $max_length ) {
			return $clean_description;
		}

		return mb_substr( $clean_description, 0, $max_length - 3 ) . '...';
	}

	/**
	 * @return array<Image_Model>
	 */
	private function get_product_images( WC_Product $product ): array {
		$image_model = array();

		if ( ! empty( $product->get_image_id() ) ) {
			$url = wp_get_attachment_url( $product->get_image_id() );
			if ( is_string( $url ) ) {
				$image_url = new Gr_Image_Url( $url );
				if ( $image_url->is_valid() ) {
					$image_model[] = new Image_Model( $image_url->get_url(), 0 );

					return $image_model;
				}
			}
		}

		$gallery_image_ids = $product->get_gallery_image_ids();

		if ( ! empty( $gallery_image_ids ) ) {
			$url = wp_get_attachment_url( $gallery_image_ids[0] );
			if ( is_string( $url ) ) {
				$image_url = new Gr_Image_Url( $url );
				if ( $image_url->is_valid() ) {
					$image_model[] = new Image_Model( $image_url->get_url(), 0 );
				}
			}
		}

		return $image_model;
	}
}
