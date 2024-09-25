<?php

declare(strict_types=1);

namespace GR\Wordpress\Core;

class Gr_Rest_Api_Service {

    public const GR_WEB_CONNECT_SNIPPET        = 'gr4wp-web-connect-snippet';
    public const GR_RECOMMENDATION_SNIPPET     = 'gr4wp-recommendation-snippet';
    public const GR_LIVE_SYNC_URL              = 'gr4wp-live-sync-url';
    public const GR_LIVE_SYNC_TYPE             = 'gr4wp-live-sync-type';
    public const GR_MARKETING_CONSENT_TEXT     = 'gr4wp-marketing-consent-text';
    public const GR_SHOP_ID                    = 'gr4wp-shop-id';
    public const INTEGRATE_WITH_CONTACT_FORM_7 = 'gr4wp_integrate_with_contact_form_7';

    public function get_configuration(): Gr_Configuration {
        return new Gr_Configuration(
            (string) $this->get_gr_option( self::GR_WEB_CONNECT_SNIPPET ),
            (string) $this->get_gr_option( self::GR_RECOMMENDATION_SNIPPET ),
            (string) $this->get_gr_option( self::GR_LIVE_SYNC_URL ),
            (string) $this->get_gr_option( self::GR_LIVE_SYNC_TYPE ),
            (string) $this->get_gr_option( self::GR_MARKETING_CONSENT_TEXT ),
            (string) $this->get_gr_option( self::GR_SHOP_ID ),
            (bool) $this->get_gr_option( self::INTEGRATE_WITH_CONTACT_FORM_7 )
        );
    }

    public function update_configuration( Gr_Configuration $configuration ): void {
        $this->update_gr_option( self::GR_WEB_CONNECT_SNIPPET, $configuration->get_web_connect_snippet() );
        $this->update_gr_option( self::GR_RECOMMENDATION_SNIPPET, $configuration->get_recommendation_snippet() );
        $this->update_gr_option( self::GR_LIVE_SYNC_URL, $configuration->get_live_sync_url() );
        $this->update_gr_option( self::GR_LIVE_SYNC_TYPE, $configuration->get_live_sync_type() );
        $this->update_gr_option( self::GR_MARKETING_CONSENT_TEXT, $configuration->get_marketing_consent_text() );
        $this->update_gr_option( self::GR_SHOP_ID, $configuration->get_getresponse_shop_id() );
        $this->update_gr_option( self::INTEGRATE_WITH_CONTACT_FORM_7, $configuration->integrate_with_contact_form_7() );
    }

    public function clear_configuration(): void {
        $this->update_gr_option( self::GR_WEB_CONNECT_SNIPPET, '' );
        $this->update_gr_option( self::GR_RECOMMENDATION_SNIPPET, '' );
        $this->update_gr_option( self::GR_LIVE_SYNC_URL, '' );
        $this->update_gr_option( self::GR_LIVE_SYNC_TYPE, '' );
        $this->update_gr_option( self::GR_MARKETING_CONSENT_TEXT, '' );
        $this->update_gr_option( self::GR_SHOP_ID, '' );
        $this->update_gr_option( self::INTEGRATE_WITH_CONTACT_FORM_7, '' );
    }

	public function delete_configuration(): void {
		$this->delete_gr_option( self::GR_WEB_CONNECT_SNIPPET );
		$this->delete_gr_option( self::GR_RECOMMENDATION_SNIPPET );
		$this->delete_gr_option( self::GR_LIVE_SYNC_URL );
		$this->delete_gr_option( self::GR_LIVE_SYNC_TYPE );
		$this->delete_gr_option( self::GR_MARKETING_CONSENT_TEXT );
		$this->delete_gr_option( self::GR_SHOP_ID );
		$this->delete_gr_option( self::INTEGRATE_WITH_CONTACT_FORM_7 );
	}

    public function get_sites(): array {
        $is_multisite = is_multisite();

        if ( $is_multisite ) {
            $sites = get_sites(
                [
					'public'   => '1',
					'archived' => '0',
					'deleted'  => '0',
                ]
            );

            $response_sites = [];
            foreach ( $sites as $site ) {
                $response_sites[] = [
                    'id'     => $site->blog_id,
                    'domain' => get_home_url( $site->blog_id ),
                ];
            }
        } else {
            $current_blog_id = get_current_blog_id();

            $response_sites = [
                [
                    'id'     => $current_blog_id,
                    'domain' => get_home_url( $current_blog_id ) . '/',
                ],
            ];
        }

        return [
            'isMultisite' => $is_multisite,
            'sites'       => $response_sites,
        ];
    }

	private function update_gr_option( $name, $value ): void {
		update_option( $name, $value );
	}

	private function get_gr_option( $name ) {
		return get_option( $name );
	}

	private function delete_gr_option( $name ): void {
		delete_option( $name );
	}
}
