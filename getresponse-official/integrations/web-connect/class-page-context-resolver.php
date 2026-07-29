<?php

declare(strict_types=1);

namespace GetResponse\WordPress\Integrations\WebConnect;

use GetResponse\WordPress\Core\Functions;
use WC_Product;

class Page_Context_Resolver {

	public function inject_page_context( string $getresponse_shop_id ): void {
		if ( function_exists( 'is_product' ) && is_product() ) {
			$this->inject_product_context( $getresponse_shop_id );
			return;
		}

		if ( function_exists( 'is_product_category' ) && is_product_category() ) {
			$this->inject_category_context( $getresponse_shop_id );
			return;
		}

		if ( function_exists( 'is_front_page' ) && is_front_page() ) {
			$this->inject_home_context( $getresponse_shop_id );
			return;
		}

		if ( function_exists( 'is_cart' ) && is_cart() ) {
			$this->inject_cart_context( $getresponse_shop_id );
			return;
		}

		$this->inject_fallback_context( $getresponse_shop_id );
	}

	private function inject_category_context( string $getresponse_shop_id ): void {
		$category = get_queried_object();
		if ( ! $category ) {
			return;
		}

		$this->inject(
			$getresponse_shop_id,
			'category',
			array(
				'category' => array(
					'id'   => (string) $category->term_id,
					'name' => $category->name,
				),
			)
		);
	}

	private function inject_home_context( string $getresponse_shop_id ): void {
		$this->inject(
			$getresponse_shop_id,
			'home',
			array()
		);
	}

	private function inject_product_context( string $getresponse_shop_id ): void {

		$product_id = get_the_ID();

		if ( ! $product_id ) {
			return;
		}

		$product = wc_get_product( $product_id );

		$this->inject(
			$getresponse_shop_id,
			'product',
			array(
				'product' => array(
					'id'         => (string) $product->get_id(),
					'name'       => $product->get_name(),
					'sku'        => $product->get_sku(),
					'price'      => (float) Functions::get_product_price( $product ),
					'currency'   => get_woocommerce_currency(),
					'categories' => array_map(
						function ( $category ) {
							$category['id'] = (string) $category['id'];
							return $category;
						},
						Functions::get_categories( $product )
					),
				),
			)
		);
	}

	private function inject_cart_context( string $getresponse_shop_id ): void {
		if ( ! WC()->cart ) {
			return;
		}

		$products_in_cart = array();

		foreach ( WC()->cart->get_cart() as $cart_item ) {
			$product = $cart_item['data'];

			if ( ! $product instanceof WC_Product ) {
				continue;
			}

			$products_in_cart[] = array(
				'productId' => (string) $cart_item['product_id'],
				'variantId' => $cart_item['variation_id'] > 0 ? (string) $cart_item['variation_id'] : null,
				'quantity'  => (int) $cart_item['quantity'],
				'name'      => $product->get_name(),
				'sku'       => $product->get_sku(),
				'price'     => (float) Functions::get_product_price( $product ),
				'lineTotal' => (float) $cart_item['line_total'],
			);
		}

		$this->inject(
			$getresponse_shop_id,
			'cart',
			array(
				'cart' => array(
					'total'    => (float) WC()->cart->get_cart_contents_total(),
					'currency' => get_woocommerce_currency(),
					'items'    => $products_in_cart,
				),
			)
		);
	}

	private function inject_fallback_context( string $getresponse_shop_id ): void {
		$this->inject(
			$getresponse_shop_id,
			'other',
			array()
		);
	}

	private function inject( string $getresponse_shop_id, string $page_type, array $context ): void {
		$page_context = array(
			'page'    => array( 'type' => $page_type ),
			'context' => array_merge(
				array( 'type' => $page_type ),
				array( 'shop' => array( 'id' => $getresponse_shop_id ) ),
				$context
			),
		);

		wp_register_script( 'gr-page-context', false, array(), GETRESPONSE_FOR_WP_VERSION, true );
		wp_enqueue_script( 'gr-page-context' );

		wp_add_inline_script(
			'gr-page-context',
			'window.GR_PAGE_CONTEXT = ' . wp_json_encode( $page_context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . ';'
		);
	}
}
