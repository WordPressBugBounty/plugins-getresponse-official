<?php

declare(strict_types=1);

namespace GR\Wordpress\Integrations\Woocommerce;

use Exception;
use GR\Wordpress\Core\Functions;
use GR\Wordpress\Core\Gr_Configuration;
use GR\Wordpress\Core\Hook\Gr_Hook_Exception;
use GR\Wordpress\Core\Hook\Gr_Hook_Service;
use GR\Wordpress\Core\Hook\Model\User_Model;
use Psr\Log\LoggerInterface;
use WC_Customer;

class Customer_Upsert_Handler {

    private Gr_Configuration $gr_configuration;
    private Gr_Hook_Service $gr_hook_service;
    private LoggerInterface $logger;

    public function __construct(
        Gr_Configuration $gr_configuration,
        Gr_Hook_Service $gr_hook_service,
        LoggerInterface $logger
    ) {
        $this->gr_configuration = $gr_configuration;
        $this->gr_hook_service  = $gr_hook_service;
        $this->logger           = $logger;
    }

    public function handle( WC_Customer $customer ): void {
        try {
            if ( ! $this->gr_configuration->is_full_ecommerce_live_sync_active() ) {
                return;
            }

            $this->send_callback( $customer );
        } catch ( Exception $e ) {
            $this->logger->error( 'Cart handler error', Functions::get_error_context( $e ) );
        }
    }

    /**
     * @throws Gr_Hook_Exception
     */
    private function send_callback( WC_Customer $customer ): void {

        $user_id = $customer->get_id();

        $user_data         = get_userdata( $user_id );
        $marketing_consent = (int) get_user_meta( $user_id, Gr_Configuration::MARKETING_CONSENT_META_NAME, true );

        $first_name = get_user_meta( $user_id, 'first_name', true );
        $last_name  = get_user_meta( $user_id, 'last_name', true );

        $model = new User_Model(
            $user_id,
            $user_data->user_email,
            (bool) $marketing_consent,
            $first_name,
            $last_name,
            null,
            $this->prepare_custom_fields( $customer )
        );

        $this->gr_hook_service->send_callback( $this->gr_configuration, $model );
    }

    private function prepare_custom_fields( WC_Customer $customer ): array {
        $custom_fields = [];

        foreach ( $customer->get_billing() as $key => $value ) {
            $custom_fields[ 'billing_' . $key ] = $value;
        }

        foreach ( $customer->get_shipping() as $key => $value ) {
            $custom_fields[ 'shipping_' . $key ] = $value;
        }

        return $custom_fields;
    }
}
