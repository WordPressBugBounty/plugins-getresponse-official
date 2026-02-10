<?php

declare(strict_types=1);

namespace GR\WordPress\Integrations\Woocommerce;

use Exception;
use GR\WordPress\Core\Functions;
use GR\WordPress\Core\Gr_Configuration;
use Psr\Log\LoggerInterface;
use WC_Order;

class New_Order_Handler {

	private Gr_Configuration $gr_configuration;
	private Gr_Cart_Service $gr_cart_service;
	private LoggerInterface $logger;

	public function __construct(
		Gr_Configuration $gr_configuration,
		Gr_Cart_Service $gr_cart_service,
		LoggerInterface $logger
	) {
		$this->gr_configuration = $gr_configuration;
		$this->gr_cart_service  = $gr_cart_service;
		$this->logger           = $logger;
	}

	public function handle( WC_Order $order ): void {

		try {
			if ( ! $this->gr_configuration->is_full_ecommerce_live_sync_active() ) {
				return;
			}

			if ( ! $order->get_meta( $this->gr_cart_service::CART_ID_META_NAME ) ) {
				$cart_id = $this->gr_cart_service->get_cart_id_and_reset();
				if ( $cart_id ) {
					$order->update_meta_data( $this->gr_cart_service::CART_ID_META_NAME, $cart_id );
					$order->save();
				}
			}
		} catch ( Exception $e ) {
			$this->logger->error( 'Order handler error', Functions::get_error_context( $e ) );
		}
	}
}
