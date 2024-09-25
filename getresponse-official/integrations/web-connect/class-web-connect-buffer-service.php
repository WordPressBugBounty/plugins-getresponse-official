<?php

declare(strict_types=1);

namespace GR\Wordpress\Integrations\WebConnect;

use GR\Wordpress\Core\Functions;
use GR\Wordpress\Integrations\WebConnect\Model\Buffer\Cart_Model;
use GR\Wordpress\Integrations\WebConnect\Model\Buffer\Order_Model;

class Web_Connect_Buffer_Service {

    private const CART_WEB_CONNECT_BUFFER  = 'gr4wp_web_connect_buffer_cart';
    private const ORDER_WEB_CONNECT_BUFFER = 'gr4wp_web_connect_buffer_order';

    public function add_cart_to_buffer( Cart_Model $model ): void {
        Functions::session_set( self::CART_WEB_CONNECT_BUFFER, $model->to_session() );
    }

    public function get_cart_from_buffer(): array {
        $buffer = Functions::session_get_and_clear( self::CART_WEB_CONNECT_BUFFER );

        if ( empty( $buffer ) ) {
            return [];
        }

        return json_decode( $buffer, true );
    }

    public function add_order_to_buffer( Order_Model $model ): void {
        Functions::session_set( self::ORDER_WEB_CONNECT_BUFFER, $model->to_session() );
    }

    public function get_order_from_buffer(): array {
        $buffer = Functions::session_get_and_clear( self::ORDER_WEB_CONNECT_BUFFER );

        if ( empty( $buffer ) ) {
            return [];
        }

        return json_decode( $buffer, true );
    }
}
