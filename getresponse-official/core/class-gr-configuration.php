<?php

declare(strict_types=1);

namespace GR\Wordpress\Core;

class Gr_Configuration {

    public const MARKETING_CONSENT_META_NAME = 'gr_marketing_consent';

    public const CSS_MARKETING_CONSENT_WRAPPER_CLASS  = 'gr-marketing-consent';
    public const CSS_MARKETING_CONSENT_CHECKBOX_CLASS = 'gr-marketing-consent-checkbox';
    public const CSS_MARKETING_CONSENT_LABEL_CLASS    = 'gr-marketing-consent-label';

    public const LIVE_SYNC_TYPE_CONTACT        = 'Contacts';
    public const LIVE_SYNC_TYPE_FULL_ECOMMERCE = 'FullEcommerce';
	public const WEB_CONNECT_SNIPPET_KEY       = 'webConnectSnippet';
	public const RECOMMENDATION_SNIPPET_KEY    = 'recommendationSnippet';
	public const LIVE_SYNC_URL_KEY             = 'liveSyncUrl';
	public const LIVE_SYNC_TYPE_KEY            = 'liveSyncType';
	public const MARKETING_CONSENT_TEXT_KEY    = 'marketingConsentText';
	public const GETRESPONSE_SHOP_ID           = 'grShopId';
    public const INTEGRATE_WITH_CONTACT_FORM_7 = 'integrateWithContactForm7';

    private string $web_connect_snippet;
    private string $recommendation_snippet;
    private string $live_sync_url;
    private string $live_sync_type;
    private string $marketing_consent_text;
    private string $getresponse_shop_id;
    private bool $integrate_with_contact_form_7;

    public function __construct( string $web_connect_snippet, string $recommendation_snippet, string $live_sync_url, string $live_sync_type, string $marketing_consent_text, string $getresponse_shop_id, bool $integrate_with_contact_form_7 ) {
        $this->web_connect_snippet           = $web_connect_snippet;
        $this->recommendation_snippet        = $recommendation_snippet;
        $this->live_sync_url                 = $live_sync_url;
        $this->live_sync_type                = $live_sync_type;
        $this->marketing_consent_text        = $marketing_consent_text;
        $this->getresponse_shop_id           = $getresponse_shop_id;
        $this->integrate_with_contact_form_7 = $integrate_with_contact_form_7;
    }

	public static function make_from_array( array $params ): self {
		return new self(
            ! empty( $params[ self::WEB_CONNECT_SNIPPET_KEY ] ) ? $params[ self::WEB_CONNECT_SNIPPET_KEY ] : '',
            ! empty( $params[ self::RECOMMENDATION_SNIPPET_KEY ] ) ? $params[ self::RECOMMENDATION_SNIPPET_KEY ] : '',
            ! empty( $params[ self::LIVE_SYNC_URL_KEY ] ) ? $params[ self::LIVE_SYNC_URL_KEY ] : '',
            ! empty( $params[ self::LIVE_SYNC_TYPE_KEY ] ) ? $params[ self::LIVE_SYNC_TYPE_KEY ] : '',
            ! empty( $params[ self::MARKETING_CONSENT_TEXT_KEY ] ) ? $params[ self::MARKETING_CONSENT_TEXT_KEY ] : '',
            ! empty( $params[ self::GETRESPONSE_SHOP_ID ] ) ? $params[ self::GETRESPONSE_SHOP_ID ] : '',
            empty( $params[ self::INTEGRATE_WITH_CONTACT_FORM_7 ] ) ? false : (bool) $params[ self::INTEGRATE_WITH_CONTACT_FORM_7 ],
		);
	}

    public function get_web_connect_snippet(): string {
        return $this->web_connect_snippet;
    }
    public function get_recommendation_snippet(): string {
        return $this->recommendation_snippet;
    }

    public function get_live_sync_url(): string {
        return $this->live_sync_url;
    }

    public function get_live_sync_type(): string {
        return $this->live_sync_type;
    }

    public function get_marketing_consent_text(): string {
        return $this->marketing_consent_text;
    }
    public function get_getresponse_shop_id(): string {
        return $this->getresponse_shop_id;
    }

    public function to_array(): array {
		return [
			self::WEB_CONNECT_SNIPPET_KEY       => $this->get_web_connect_snippet(),
			self::RECOMMENDATION_SNIPPET_KEY    => $this->get_recommendation_snippet(),
			self::LIVE_SYNC_URL_KEY             => $this->get_live_sync_url(),
			self::LIVE_SYNC_TYPE_KEY            => $this->get_live_sync_type(),
			self::MARKETING_CONSENT_TEXT_KEY    => $this->get_marketing_consent_text(),
			self::GETRESPONSE_SHOP_ID           => $this->get_getresponse_shop_id(),
            self::INTEGRATE_WITH_CONTACT_FORM_7 => $this->integrate_with_contact_form_7(),
        ];
	}

    public function is_live_sync_active(): bool {
        return $this->live_sync_url !== '';
    }

    private function is_contact_sync_type(): bool {
        return $this->live_sync_type === self::LIVE_SYNC_TYPE_CONTACT;
    }

    private function is_full_ecommerce_sync_type(): bool {
        return $this->live_sync_type === self::LIVE_SYNC_TYPE_FULL_ECOMMERCE;
    }

    public function is_contact_live_sync_active(): bool {
        return $this->is_live_sync_active();
    }

    public function is_full_ecommerce_live_sync_active(): bool {
        return $this->is_live_sync_active() && $this->is_full_ecommerce_sync_type();
    }

    public function integrate_with_contact_form_7(): bool {
        return $this->integrate_with_contact_form_7;
    }
}
