<?php

declare(strict_types=1);

namespace GetResponse\WordPress\Core\Hook;

use Exception;
use WP_Error;

class Gr_Hook_Exception extends Exception {

	public static function createFromWPError( $error_message ): self {
		return new self( $error_message );
	}
}
