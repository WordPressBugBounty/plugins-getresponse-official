<?php

declare(strict_types=1);

namespace GetResponse\WordPress\Integrations\WebConnect;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use GetResponse\WordPress\Core\Functions;
use GetResponse\WordPress\Core\Gr_Configuration;
use GetResponse\WordPress\Integrations\Integration;
use Psr\Log\LoggerInterface;
use WC_Cart;
use WC_Order;
use Throwable;

class Web_Connect_Integration implements Integration {

	private Gr_Configuration $gr_configuration;

	private Cart_Service $cart_service;

	private Order_Service $order_service;

	private Page_Context_Resolver $page_context_resolver;

	private LoggerInterface $logger;

	public function __construct(
		Gr_Configuration $gr_configuration,
		Cart_Service $cart_service,
		Order_Service $order_service,
		Page_Context_Resolver $page_context_resolver,
		LoggerInterface $logger
	) {
		$this->gr_configuration      = $gr_configuration;
		$this->cart_service          = $cart_service;
		$this->order_service         = $order_service;
		$this->page_context_resolver = $page_context_resolver;
		$this->logger                = $logger;
	}

	public function init(): void {
		add_action( 'wp_enqueue_scripts', array( $this, 'inject_base_snippet' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'inject_page_context' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'inject_category_view_snippet' ) );
		add_filter( 'woocommerce_after_single_product', array( $this, 'inject_product_view_snippet' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'inject_web_connect_buffered_events' ) );
		add_action( 'gr4wp_cart_upsert', array( $this, 'handle_cart_upsert' ) );
		add_action( 'gr4wp_order_upsert', array( $this, 'handle_order_upsert' ) );
	}

	public function handle_cart_upsert( WC_Cart $cart ): void {
		try {
			$this->cart_service->add_cart_to_buffer( $cart );
		} catch ( Throwable $exception ) {
			$this->logger->error( 'WebConnect integration handler error', Functions::get_error_context( $exception ) );
		}
	}

	public function handle_order_upsert( ?WC_Order $order ): void {
		try {
			if ( null === $order ) {
				return;
			}

			$this->order_service->add_order_to_buffer( $order );
		} catch ( Throwable $exception ) {
			$this->logger->error( 'WebConnect integration handler error', Functions::get_error_context( $exception ) );
		}
	}

	public function inject_base_snippet(): void {
		try {
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
		} catch ( Throwable $exception ) {
			$this->logger->error( 'WebConnect integration handler error', Functions::get_error_context( $exception ) );
		}
	}

	public function inject_category_view_snippet(): void {
		try {
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

			$view_category_payload = array(
				'shop' => array( 'id' => $getresponse_shop_id ),
				'id'   => (string) $category->term_id,
				'name' => $category->name,
			);

			wp_register_script( 'gr-category-view', false, array(), array(), true );
			wp_enqueue_script( 'gr-category-view' );

			wp_add_inline_script(
				'gr-category-view',
				"GrTracking('importScript', 'ec');
                 GrTracking('viewCategory', " . wp_json_encode( $view_category_payload ) . ');'
			);
		} catch ( Throwable $exception ) {
			$this->logger->error( 'WebConnect integration handler error', Functions::get_error_context( $exception ) );
		}
	}

	public function inject_product_view_snippet(): void {
		try {
			$web_connect_snippet = $this->gr_configuration->get_web_connect_snippet();

			if ( $web_connect_snippet === '' ) {
				return;
			}

			$getresponse_shop_id = $this->gr_configuration->get_getresponse_shop_id();

			if ( $getresponse_shop_id === '' ) {
				return;
			}

			global $product;
			$view_item_payload = array(
				'shop'       => array( 'id' => $getresponse_shop_id ),
				'product'    => array(
					'id'       => (string) $product->get_id(),
					'name'     => $product->get_name(),
					'sku'      => $product->get_sku(),
					'vendor'   => '',
					'price'    => Functions::get_product_price( $product ),
					'currency' => get_option( 'woocommerce_currency' ),
				),
				'categories' => Functions::get_categories( $product ),
			);

			wp_register_script( 'gr-product-view', false, array(), array(), true );
			wp_enqueue_script( 'gr-product-view' );

			wp_add_inline_script(
				'gr-product-view',
				"GrTracking('importScript', 'ec');
                 GrTracking('viewItem', " . wp_json_encode( $view_item_payload ) . ');'
			);
		} catch ( Throwable $exception ) {
			$this->logger->error( 'WebConnect integration handler error', Functions::get_error_context( $exception ) );
		}
	}

	public function inject_web_connect_buffered_events(): void {
		try {
			$web_connect_snippet = $this->gr_configuration->get_web_connect_snippet();

			if ( $web_connect_snippet === '' ) {
				return;
			}

			$getresponse_shop_id = $this->gr_configuration->get_getresponse_shop_id();

			if ( $getresponse_shop_id === '' ) {
				return;
			}

			$web_connect_script = '';
			$buffered_cart      = array();
			$buffered_order     = array();

			$method = isset( $_SERVER['REQUEST_METHOD'] )
				? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) )
				: '';

			if ( 'POST' !== $method ) {
				$buffered_cart  = $this->cart_service->get_cart_from_buffer();
				$buffered_order = $this->order_service->get_order_from_buffer();
			}

			if ( ! empty( $buffered_cart ) ) {
				$buffered_cart['shop'] = array( 'id' => $getresponse_shop_id );
				$web_connect_script   .= PHP_EOL . "GrTracking('cartUpdate', " . wp_json_encode( $buffered_cart ) . ');';
			}

			if ( ! empty( $buffered_order ) ) {
				$buffered_order['shop'] = array( 'id' => $getresponse_shop_id );
				$web_connect_script    .= PHP_EOL . "GrTracking('orderPlaced', " . wp_json_encode( $buffered_order ) . ');';
			}

			if ( empty( $web_connect_script ) ) {
				return;
			}

			wp_register_script( 'gr-web-connect-events', false, array(), 1, true );
			wp_enqueue_script( 'gr-web-connect-events' );

			wp_add_inline_script(
				'gr-web-connect-events',
				"GrTracking('importScript', 'ec'); " . PHP_EOL . $web_connect_script
			);
		} catch ( Throwable $exception ) {
			$this->logger->error( 'WebConnect integration handler error', Functions::get_error_context( $exception ) );
		}
	}

	public function inject_page_context(): void {
		$this->page_context_resolver->inject_page_context( $this->gr_configuration->get_getresponse_shop_id() );
	}
}
