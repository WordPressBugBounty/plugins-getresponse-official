<?php

declare(strict_types=1);

namespace GetResponse\WordPress\Integrations\Woocommerce;

use Exception;
use GetResponse\WordPress\Core\Functions;
use GetResponse\WordPress\Core\Gr_Configuration;
use GetResponse\WordPress\Core\Gr_Nonce_Field;
use GetResponse\WordPress\Core\Gr_User_Marketing_Consent_Buffer;
use GetResponse\WordPress\Core\Gr_User_Marketing_Consent_Buffer_Exception;
use GetResponse\WordPress\Core\Hook\Gr_Hook_Exception;
use GetResponse\WordPress\Core\Hook\Gr_Hook_Service;
use GetResponse\WordPress\Core\Hook\Model\Address_Model;
use GetResponse\WordPress\Core\Hook\Model\Line_Model;
use GetResponse\WordPress\Core\Hook\Model\Order_Model;
use GetResponse\WordPress\Core\Hook\Model\User_Model;
use Psr\Log\LoggerInterface;
use WC_Order;
use WC_Order_Item_Product;

class Order_Upsert_Handler {

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

	public function handle( WC_Order $order ): void {

		try {
			if ( ! $this->gr_configuration->is_full_ecommerce_live_sync_active() ) {
				return;
			}

			$this->send_callback( $order );
		} catch ( Exception $e ) {
			$this->logger->error( 'Order handler error', Functions::get_error_context( $e ) );
		}
	}

	private function get_customer( WC_Order $order ): User_Model {
		$customer_id = $order->get_customer_id();

		if ( 0 === $customer_id ) {
			$raw_billing_address = $order->get_address();
			$billing_address     = $this->get_address( $order, 'billing' );
			$marketing_consent   = $this->has_customer_marketing_consent();

			return new User_Model(
				0,
				$raw_billing_address['email'],
				$marketing_consent,
				$raw_billing_address['first_name'],
				$raw_billing_address['last_name'],
				$billing_address,
				array(
					'billing_first_name'  => $order->get_billing_first_name(),
					'billing_last_name'   => $order->get_billing_last_name(),
					'billing_company'     => $order->get_billing_company(),
					'billing_address_1'   => $order->get_billing_address_1(),
					'billing_address_2'   => $order->get_billing_address_2(),
					'billing_city'        => $order->get_billing_city(),
					'billing_postcode'    => $order->get_billing_postcode(),
					'billing_country'     => $order->get_billing_country(),
					'billing_state'       => $order->get_billing_state(),
					'billing_phone'       => $order->get_billing_phone(),
					'shipping_first_name' => $order->get_shipping_first_name(),
					'shipping_last_name'  => $order->get_shipping_last_name(),
					'shipping_company'    => $order->get_shipping_company(),
					'shipping_address_1'  => $order->get_shipping_address_1(),
					'shipping_address_2'  => $order->get_shipping_address_2(),
					'shipping_city'       => $order->get_shipping_city(),
					'shipping_postcode'   => $order->get_shipping_postcode(),
					'shipping_country'    => $order->get_shipping_country(),
					'shipping_state'      => $order->get_shipping_state(),
					'shipping_phone'      => $order->get_shipping_phone(),
				)
			);
		}

		$user_data = get_userdata( $customer_id );
		$user_meta = get_user_meta( $customer_id );

		$first_name        = $user_meta['first_name'][0] ?? '';
		$last_name         = $user_meta['last_name'][0] ?? '';
		$marketing_consent = (bool) ( $user_meta[ Gr_Configuration::MARKETING_CONSENT_META_NAME ][0] ?? false );

		$billing_first_name = $user_meta['billing_first_name'][0] ?? '';
		$billing_last_name  = $user_meta['billing_last_name'][0] ?? '';
		$billing_country    = $user_meta['billing_country'][0] ?? '';
		$billing_address_1  = $user_meta['billing_address_1'][0] ?? '';
		$billing_address_2  = $user_meta['billing_address_2'][0] ?? '';
		$billing_city       = $user_meta['billing_city'][0] ?? '';
		$billing_postcode   = $user_meta['billing_postcode'][0] ?? '';
		$billing_state      = $user_meta['billing_state'][0] ?? '';
		$billing_phone      = $user_meta['billing_phone'][0] ?? '';
		$billing_company    = $user_meta['billing_company'][0] ?? '';

		$address_model = new Address_Model(
			$billing_country,
			$billing_first_name,
			$billing_last_name,
			$billing_address_1,
			$billing_address_2,
			$billing_city,
			$billing_postcode,
			$billing_state,
			null,
			$billing_phone,
			$billing_company
		);

		return new User_Model(
			$customer_id,
			$user_data->user_email,
			$marketing_consent,
			$first_name,
			$last_name,
			$address_model,
			array(
				'billing_first_name'  => $billing_first_name,
				'billing_last_name'   => $billing_last_name,
				'billing_company'     => $billing_company,
				'billing_address_1'   => $billing_address_1,
				'billing_address_2'   => $billing_address_2,
				'billing_city'        => $billing_city,
				'billing_postcode'    => $billing_postcode,
				'billing_country'     => $billing_country,
				'billing_state'       => $billing_state,
				'billing_phone'       => $billing_phone,
				'shipping_first_name' => $user_meta['shipping_first_name'][0] ?? '',
				'shipping_last_name'  => $user_meta['shipping_last_name'][0] ?? '',
				'shipping_company'    => $user_meta['shipping_company'][0] ?? '',
				'shipping_address_1'  => $user_meta['shipping_address_1'][0] ?? '',
				'shipping_address_2'  => $user_meta['shipping_address_2'][0] ?? '',
				'shipping_city'       => $user_meta['shipping_city'][0] ?? '',
				'shipping_postcode'   => $user_meta['shipping_postcode'][0] ?? '',
				'shipping_country'    => $user_meta['shipping_country'][0] ?? '',
				'shipping_state'      => $user_meta['shipping_state'][0] ?? '',
				'shipping_phone'      => $user_meta['shipping_phone'][0] ?? '',
			)
		);
	}

	private function get_callback_products( WC_Order $order ): array {
		$lines = array();

		/** @var WC_Order_Item_Product $item */
		foreach ( $order->get_items() as $item ) {
			$product = $item->get_product();
			if ( $product ) {
				$product_price = ( (float) $item->get_total() + (float) $item->get_total_tax() ) / $item->get_quantity();
				$lines[]       = new Line_Model(
					(int) $product->get_id(),
					round( $product_price, 2 ),
					round( $product_price, 2 ),
					(int) round( (float) $item->get_quantity() ),
					$product->get_sku()
				);
			}
		}

		return $lines;
	}

	private function get_address( WC_Order $order, string $type ): ?Address_Model {
		$address = $order->get_address( $type );

		if ( empty( $address['country'] ) || empty( $address['first_name'] ) || empty( $address['last_name'] ) ) {
			return null;
		}

		return Address_Model::fromRawData( $address );
	}

	/**
	 * @throws Gr_Hook_Exception
	 */
	private function send_callback( WC_Order $order ): void {
		$model = new Order_Model(
			$order->get_id(),
			$order->get_order_number(),
			$order->get_meta( $this->gr_cart_service::CART_ID_META_NAME ) ?? '',
			$order->get_billing_email(),
			$this->get_customer( $order ),
			$this->get_callback_products( $order ),
			$order->get_view_order_url(),
			round( (float) $order->get_total(), 2 ),
			round( (float) $order->get_total(), 2 ),
			round( (float) $order->get_shipping_total(), 2 ),
			$order->get_currency(),
			$order->get_status(),
			$this->get_address( $order, 'shipping' ),
			$this->get_address( $order, 'billing' ),
			$order->get_date_created()->date_i18n( DATE_ATOM ),
			null === $order->get_date_modified() ? null : $order->get_date_modified()->date_i18n( DATE_ATOM )
		);

		$this->gr_hook_service->send_callback( $this->gr_configuration, $model );
	}

	private function has_customer_marketing_consent(): bool {
		if (
			! isset( $_POST[ Gr_Nonce_Field::FIELD_NAME ] )
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ Gr_Nonce_Field::FIELD_NAME ] ) ), Gr_Nonce_Field::ACTION_NAME )
		) {
			return false;
		}

		try {
			return Gr_User_Marketing_Consent_Buffer::get_user_marketing_consent();
		} catch ( Gr_User_Marketing_Consent_Buffer_Exception $buffer_exception ) {
			if ( isset( $_POST[ Gr_Configuration::MARKETING_CONSENT_META_NAME ] ) ) {
				return (bool) sanitize_text_field(
					wp_unslash( $_POST[ Gr_Configuration::MARKETING_CONSENT_META_NAME ] )
				);
			}
		}

		return false;
	}
}
