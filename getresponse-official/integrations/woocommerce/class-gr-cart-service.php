<?php

declare(strict_types=1);

namespace GetResponse\WordPress\Integrations\Woocommerce;

use GetResponse\WordPress\Core\Functions;

class Gr_Cart_Service {
	private const CART_ID_NAME     = 'gr4wp_cart_id';
	public const CART_ID_META_NAME = '_gr_cart_id';
	const CART_TTL                 = 3600 * 24 * 30;

	public function get_cart_id(): ?string {
		$cart_id = Functions::session_get( self::CART_ID_NAME );

		if ( $cart_id === null ) {
			$cart_id = Functions::get_cookie( self::CART_ID_NAME );
		}

		if ( $cart_id === null ) {
			$cart_id = $this->generate_cart_id();
		}

		Functions::session_set( self::CART_ID_NAME, $cart_id );
		Functions::set_cookie( self::CART_ID_NAME, $cart_id, self::CART_TTL );

		return $cart_id;
	}

	public function get_cart_id_and_reset(): ?string {
		$cart_id = Functions::session_get_and_clear( self::CART_ID_NAME );

		if ( $cart_id === null ) {
			$cart_id = Functions::get_cookie( self::CART_ID_NAME );
		}

		Functions::delete_cookie( self::CART_ID_NAME );

		return $cart_id !== null ? $cart_id : null;
	}

	public function set_cart_id( string $cart_id ): void {
		Functions::session_set( self::CART_ID_NAME, $cart_id );
		Functions::set_cookie( self::CART_ID_NAME, $cart_id, self::CART_TTL );
	}

	private function generate_cart_id(): string {
		$time   = time();
		$random = wp_rand( 10000, 99999 );

		return sprintf( 'cart_%x_%x', $time, $random );
	}
}
