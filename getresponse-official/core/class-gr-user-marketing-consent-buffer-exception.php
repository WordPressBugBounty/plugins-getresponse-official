<?php

declare(strict_types=1);

namespace GetResponse\WordPress\Core;

use RuntimeException;

class Gr_User_Marketing_Consent_Buffer_Exception extends RuntimeException {

	public static function createForEmptyConsent(): self {
		return new self( 'GR consent not set' );
	}
}
