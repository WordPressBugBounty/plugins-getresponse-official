<?php

declare(strict_types=1);

namespace GR\Wordpress\Integrations\Woocommerce;

use GR\Wordpress\Core\Functions;

class Gr_Cart_Service {
    private const CART_ID_SESSION_NAME = 'gr4wp_cart_id';

    public function get_cart_id(): ?int {
        $cart_id = time() + wp_rand( 0, 99999 );
        $cart_id = Functions::session_get_or_set( self::CART_ID_SESSION_NAME, $cart_id );
        return null === $cart_id ? null : (int) $cart_id;
    }

    public function get_cart_id_and_reset(): ?int {
        $cart_id = Functions::session_get_and_clear( self::CART_ID_SESSION_NAME );
        return null === $cart_id ? null : (int) $cart_id;
    }
}
