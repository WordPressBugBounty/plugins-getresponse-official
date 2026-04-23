<?php

declare(strict_types=1);

namespace GetResponse\WordPress\Core;

class Gr_User_Marketing_Consent_Buffer {
	private static ?bool $marketing_consent = null;

	public static function add_user_marketing_consent( bool $consent ): void {
		self::$marketing_consent = $consent;
	}

	/**
	 * @throws Gr_User_Marketing_Consent_Buffer_Exception
	 */
	public static function get_user_marketing_consent(): bool {
		if ( null === self::$marketing_consent ) {
			throw Gr_User_Marketing_Consent_Buffer_Exception::createForEmptyConsent();
		}

		return self::$marketing_consent;
	}
}
