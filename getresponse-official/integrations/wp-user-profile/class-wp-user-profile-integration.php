<?php

declare(strict_types=1);

namespace GR\Wordpress\Integrations\WPUserProfile;

use Exception;
use GR\Wordpress\Core\Functions;
use GR\Wordpress\Core\Gr_Configuration;
use GR\Wordpress\Core\Hook\Gr_Hook_Service;
use GR\Wordpress\Core\Hook\Model\User_Model;
use GR\Wordpress\Integrations\Integration;
use GR\Wordpress\Integrations\Woocommerce\Woocommerce_Integration;
use Psr\Log\LoggerInterface;
use WP_User;

class WP_User_Profile_Integration implements Integration {

    private Gr_Configuration $gr_configuration;
    private Gr_Hook_Service $gr_hook_service;
    private LoggerInterface $logger;

    public function __construct( Gr_Configuration $gr_configuration, Gr_Hook_Service $gr_hook_service, LoggerInterface $logger ) {
        $this->gr_configuration = $gr_configuration;
        $this->gr_hook_service  = $gr_hook_service;
        $this->logger           = $logger;
    }

    public function init(): void {
        add_action( 'edit_user_profile', array( $this, 'extend_user_profile' ) );
        add_action( 'profile_update', array( $this, 'handle_profile_update' ) );
    }

    public function handle_profile_update( int $user_id ): void {
        $marketing_consent_key = Gr_Configuration::MARKETING_CONSENT_META_NAME;

        try {
            $marketing_consent = (int) get_user_meta( $user_id, $marketing_consent_key, true );

            if ( isset( $_POST[ $marketing_consent_key ] ) ) {
                $new_marketing_consent = (int) sanitize_text_field( $_POST[ $marketing_consent_key ] );
                if ( $marketing_consent !== $new_marketing_consent ) {
                    update_user_meta( $user_id, $marketing_consent_key, $new_marketing_consent );
                    $marketing_consent = $new_marketing_consent;
                }
            }

            if ( ! $this->gr_configuration->is_contact_live_sync_active() || Woocommerce_Integration::is_woo_commerce_installed() ) {
                return;
            }

            $user_data = get_userdata( $user_id );

            $first_name = get_user_meta( $user_id, 'first_name', true );
            $last_name  = get_user_meta( $user_id, 'last_name', true );

            $model = new User_Model(
                $user_id,
                $user_data->user_email,
                (bool) $marketing_consent,
                $first_name,
                $last_name
            );

            $this->gr_hook_service->send_callback( $this->gr_configuration, $model );
        } catch ( Exception $e ) {
            $this->logger->error( 'User profile handler error', Functions::get_error_context( $e ) );
        }
    }

    public function extend_user_profile( WP_User $user ): void {
        $is_gr_marketing_consent_checked = (bool) get_user_meta( $user->ID, Gr_Configuration::MARKETING_CONSENT_META_NAME, true );
        $marketing_consent_text          = $this->gr_configuration->get_marketing_consent_text();

        require_once __DIR__ . '/partials/user-profile.php';
    }
}
