<?php

declare(strict_types=1);

namespace GR\WordPress\Integrations\Woocommerce;

use Exception;
use GR\WordPress\Core\Functions;
use GR\WordPress\Core\Gr_Configuration;
use GR\WordPress\Core\Hook\Gr_Hook_Exception;
use GR\WordPress\Core\Hook\Gr_Hook_Service;
use GR\WordPress\Core\Hook\Model\Address_Model;
use GR\WordPress\Core\Hook\Model\Cart_Model;
use GR\WordPress\Core\Hook\Model\Line_Model;
use GR\WordPress\Core\Hook\Model\User_Model;
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
		$lines = array();

		foreach ( $cart->get_cart() as $cart_item ) {
			$product = $cart_item['data'];

			$variant_id = 0 !== (int) $cart_item['variation_id'] ? $cart_item['variation_id'] : $cart_item['product_id'];

			$lines[] = new Line_Model(
				(int) $variant_id,
				round( (float) wc_get_price_including_tax( $product, array( 'price' => $product->get_price() ) ), 2 ),
				round( (float) wc_get_price_including_tax( $product, array( 'price' => $product->get_price() ) ), 2 ),
				(int) round( (float) $cart_item['quantity'] ),
				$cart_item['data']->get_sku()
			);
		}

		return $lines;
	}

	/**
	 * @throws Gr_Hook_Exception
	 */
	private function send_callback( string $cart_id, ?WC_Cart $cart ): void {
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
			$this->build_url( $cart, $cart_id ),
			$_COOKIE['gaVisitorUuid'] ?? null
		);

		if ( ! $model->is_valuable() ) {
			return;
		}

		$this->gr_hook_service->send_callback( $this->gr_configuration, $model );
	}

	private function build_url( WC_Cart $cart, $cart_id ): string {
		$payload = array();

		foreach ( $cart->get_cart() as $cart_item ) {
			$payload['cartItems'][] = array(
				'p' => $cart_item['product_id'],
				'q' => $cart_item['quantity'],
				'v' => $cart_item['variation_id'],
			);
		}

		$payload['cartId'] = $cart_id;
        // phpcs:ignore
        return home_url( '?grcart=' . base64_encode( wp_json_encode( $payload ) ) );
	}
}
