<?php

declare(strict_types=1);

namespace GR\Wordpress\Core\Hook;

class Gr_Image_Url extends Gr_Url {

    public function __construct( string $url ) {
        parent::__construct( $url );
        $this->normalize_file_name();
    }

    public function get_url(): string {
        return $this->url;
    }

    private function normalize_file_name(): void {
        if ( empty( $this->url ) ) {
            return;
        }

        $this->url = dirname( $this->url ) . DIRECTORY_SEPARATOR . rawurlencode( basename( $this->url ) );
    }
}
