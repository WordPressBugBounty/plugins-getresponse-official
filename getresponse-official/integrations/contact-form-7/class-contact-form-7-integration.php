<?php

declare(strict_types=1);

namespace GetResponse\WordPress\Integrations\ContactForm7;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Exception;
use GetResponse\WordPress\Core\Functions;
use GetResponse\WordPress\Core\Gr_Configuration;
use GetResponse\WordPress\Core\Gr_Nonce_Field;
use GetResponse\WordPress\Core\Hook\Gr_Hook_Service;
use GetResponse\WordPress\Core\Hook\Model\Contact_Model;
use GetResponse\WordPress\Integrations\Integration;
use Psr\Log\LoggerInterface;

class Contact_Form_7_Integration implements Integration {

	private const GR4WP_FIELD_PREFIX  = 'gr4wp_';
	private const CUSTOM_FIELD_PREFIX = 'gr4wp_custom_';
	private const TAG_FIELD_PREFIX    = 'gr4wp_tag';

	private Gr_Configuration $gr_configuration;
	private Gr_Hook_Service $gr_hook_service;
	private LoggerInterface $logger;

	public function __construct( Gr_Configuration $gr_configuration, Gr_Hook_Service $gr_hook_service, LoggerInterface $logger ) {
		$this->gr_configuration = $gr_configuration;
		$this->gr_hook_service  = $gr_hook_service;
		$this->logger           = $logger;
	}

	public function init(): void {
		if ( $this->gr_configuration->integrate_with_contact_form_7() && $this->gr_configuration->is_live_sync_active() ) {
			add_action( 'wpcf7_init', array( $this, 'wpcf7_init' ) );
			add_action( 'wpcf7_mail_sent', array( $this, 'handle_email_sent' ), 1 );

			add_filter( 'wpcf7_form_elements', array( $this, 'add_nonce_to_cf7_form' ) );
		}
	}

	public function wpcf7_init(): void {
		$marketing_consent_text = $this->gr_configuration->get_marketing_consent_text();
		if ( empty( $marketing_consent_text ) ) {
			return;
		}

		if ( function_exists( 'wpcf7_add_form_tag' ) ) {
			wpcf7_add_form_tag( 'gr4wp_checkbox', array( $this, 'add_custom_tag' ) );
		} else {
			wpcf7_add_shortcode( 'gr4wp_checkbox', array( $this, 'add_custom_tag' ) );
		}
	}

	public function add_custom_tag(): string {
		$marketing_consent_text = $this->gr_configuration->get_marketing_consent_text();

		ob_start();

		printf( '<label class="%s">', esc_attr( Gr_Configuration::CSS_MARKETING_CONSENT_LABEL_CLASS ) );
		printf( '<input type="checkbox" name="%s" class="%s" value="1" />', esc_attr( Gr_Configuration::MARKETING_CONSENT_META_NAME ), esc_attr( Gr_Configuration::CSS_MARKETING_CONSENT_CHECKBOX_CLASS ) );
		printf( '<span>%s</span>', esc_attr( esc_attr( $marketing_consent_text ) ) );
		printf( '</label>' );

		$html = ob_get_clean();

		return wp_kses( $html, Functions::get_allowed_html_elements() );
	}

	public function add_nonce_to_cf7_form( string $content ): string {
		$nonce = wp_nonce_field(
			Gr_Nonce_Field::ACTION_NAME,
			Gr_Nonce_Field::FIELD_NAME,
			true,
			false
		);

		return $content . $nonce;
	}

	public function handle_email_sent(): void {

		try {
			if (
				! isset( $_POST[ Gr_Nonce_Field::FIELD_NAME ] )
				|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ Gr_Nonce_Field::FIELD_NAME ] ) ), Gr_Nonce_Field::ACTION_NAME )
			) {
				return;
			}

			$email = isset( $_POST['email'] )
				? sanitize_email( wp_unslash( $_POST['email'] ) )
				: null;

			if ( is_null( $email ) ) {
				return;
			}

			$custom_fields = array();
			$tags          = array();

			foreach ( $_POST as $raw_key => $raw_value ) {
				$key   = sanitize_key( $raw_key );
				$value = is_array( $raw_value ) ? $this->sanitize_array_values( $raw_value ) : $raw_value;

				if ( $this->is_gr_custom_field( $key ) ) {
					$name                   = (string) str_replace( self::CUSTOM_FIELD_PREFIX, self::GR4WP_FIELD_PREFIX, $key );
					$custom_fields[ $name ] = $value;
				} elseif ( $this->is_gr_tag( $key ) ) {
					$tags[] = $value;
				} elseif ( $this->is_gr4wp_field( $key ) ) {
					$custom_fields[ $key ] = $value;
				}
			}

			$marketing_consent_key = Gr_Configuration::MARKETING_CONSENT_META_NAME;

			$model = new Contact_Model(
				$email,
				isset( $_POST[ $marketing_consent_key ] ) && $_POST[ $marketing_consent_key ] === '1',
				$this->get_value( $_POST, array( 'fullname', 'full-name', 'NAME' ) ),
				$this->get_value( $_POST, array( 'firstname', 'first-name', 'FIRSTNAME' ) ),
				$this->get_value( $_POST, array( 'lastname', 'last-name', 'LASTNAME' ) ),
				$custom_fields,
				$tags
			);

			$this->gr_hook_service->send_callback( $this->gr_configuration, $model );
		} catch ( Exception $e ) {
			$this->logger->error( 'CF7 handler error', Functions::get_error_context( $e ) );
		}
	}

	private function get_value( array $data, array $keys_in_order ): ?string {
		foreach ( $keys_in_order as $key ) {
			if ( isset( $data[ $key ] ) ) {
				return sanitize_text_field( $data[ $key ] );
			}
		}

		return null;
	}

	private function is_gr_custom_field( string $key ): bool {
		return 0 === strpos( $key, self::CUSTOM_FIELD_PREFIX );
	}

	private function is_gr_tag( string $key ): bool {
		return 0 === strpos( $key, self::TAG_FIELD_PREFIX );
	}

	private function is_gr4wp_field( string $key ): bool {
		return 0 === strpos( $key, self::GR4WP_FIELD_PREFIX );
	}

	private function sanitize_array_values( array $raw_value ): array {
		$sanitized_values = array();
		foreach ( $raw_value as $key => $value ) {
			$sanitized_values[ $key ] = sanitize_text_field( $value );
		}

		return $sanitized_values;
	}
}
