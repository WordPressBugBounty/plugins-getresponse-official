<?php

declare(strict_types=1);

namespace GetResponse\WordPress\Integrations\WPRegistrationForm;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Exception;
use GetResponse\WordPress\Core\Functions;
use GetResponse\WordPress\Core\Gr_Configuration;
use GetResponse\WordPress\Core\Gr_Nonce_Field;
use GetResponse\WordPress\Core\Gr_User_Marketing_Consent_Buffer;
use GetResponse\WordPress\Core\Gr_User_Marketing_Consent_Buffer_Exception;
use GetResponse\WordPress\Core\Hook\Gr_Hook_Service;
use GetResponse\WordPress\Core\Hook\Model\User_Model;
use GetResponse\WordPress\Integrations\Integration;
use Psr\Log\LoggerInterface;

class WP_Registration_Form_Integration implements Integration {

	private Gr_Configuration $gr_configuration;
	private Gr_Hook_Service $gr_hook_service;
	private LoggerInterface $logger;

	public function __construct( Gr_Configuration $gr_configuration, Gr_Hook_Service $gr_hook_service, LoggerInterface $logger ) {
		$this->gr_configuration = $gr_configuration;
		$this->gr_hook_service  = $gr_hook_service;
		$this->logger           = $logger;
	}

	public function init(): void {
		add_action( 'user_register', array( $this, 'handle_user_registered' ), 10, 2 );
		add_action( 'register_form', array( $this, 'add_marketing_consent_checkbox' ) );
	}

	public function handle_user_registered( int $user_id, array $user_data ): void {
		try {
			$marketing_consent_key = Gr_Configuration::MARKETING_CONSENT_META_NAME;

			try {
				$gr_marketing_consent = Gr_User_Marketing_Consent_Buffer::get_user_marketing_consent();
			} catch ( Gr_User_Marketing_Consent_Buffer_Exception $buffer_exception ) {
				$gr_marketing_consent = false;
				if (
					isset( $_POST[ $marketing_consent_key ] )
					&& isset( $_POST[ Gr_Nonce_Field::FIELD_NAME ] )
					&& wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ Gr_Nonce_Field::FIELD_NAME ] ) ), Gr_Nonce_Field::ACTION_NAME )
				) {
					$gr_marketing_consent = (bool) sanitize_text_field( wp_unslash( $_POST[ $marketing_consent_key ] ) );
				}
			}

			if ( $gr_marketing_consent ) {
				add_user_meta( $user_id, $marketing_consent_key, 1 );
			}

			if ( ! $this->gr_configuration->is_contact_live_sync_active() ) {
				return;
			}

			$model = new User_Model( $user_id, $user_data['user_email'], $gr_marketing_consent );

			$this->gr_hook_service->send_callback( $this->gr_configuration, $model );
		} catch ( Exception $e ) {
			$this->logger->error( 'Registration handler error', Functions::get_error_context( $e ) );
		}
	}

	public function add_marketing_consent_checkbox(): void {
		Functions::add_marketing_consent_checkbox(
			$this->gr_configuration->get_marketing_consent_text()
		);
	}
}
