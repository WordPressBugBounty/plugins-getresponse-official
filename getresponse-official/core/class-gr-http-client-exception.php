<?php

declare(strict_types=1);

namespace GR\WordPress\Core;

use Exception;
use WP_Error;

class Gr_Http_Client_Exception extends Exception {

	public static function createFromWPError( $error_message, $error_code ): self {
		return new self( $error_message, $error_code );
	}
}
