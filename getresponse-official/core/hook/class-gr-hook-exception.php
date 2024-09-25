<?php

declare(strict_types=1);

namespace GR\Wordpress\Core\Hook;

use Exception;
use WP_Error;

class Gr_Hook_Exception extends Exception {

    public static function createFromWPError( WP_Error $error ): self {
        return new self( $error->get_error_message(), (int) $error->get_error_code() );
    }
}
