<?php

declare(strict_types=1);

namespace GR\Wordpress\Core\Hook;

class Gr_Url {

    const BITCOIN = 'bitcoin';
    const DNS     = 'dns';
    const FTP     = 'ftp';
    const FTPS    = 'ftps';
    const GIT     = 'git';
    const HTTP    = 'http';
    const HTTPS   = 'https';
    const IMAP    = 'imap';
    const IRC     = 'irc';
    const JABBER  = 'jabber';
    const POP     = 'pop';
    const SKYPE   = 'skype';
    const SMTP    = 'smtp';
    const SVN     = 'svn';

    protected static array $allowed_protocols = [
        self::BITCOIN,
        self::DNS,
        self::FTP,
        self::FTPS,
        self::GIT,
        self::HTTP,
        self::HTTPS,
        self::IMAP,
        self::IRC,
        self::JABBER,
        self::POP,
        self::SKYPE,
        self::SMTP,
        self::SVN,
    ];

    protected string $url;

    public function __construct( string $url ) {
        $this->url = $url;
    }

    public function get_url(): string {
        return $this->url;
    }

    public function is_valid(): bool {
        $url = str_replace( '_', '-', $this->url );
        if ( false === filter_var( $url, FILTER_VALIDATE_URL ) ) {
            return false;
        }

        $url_parts = wp_parse_url( $this->url );
        $protocol  = $url_parts['scheme'] ? $url_parts['scheme'] : strtok( $this->url, ':' );

        return $this->is_allowed_protocol( $protocol );
    }

    protected function is_allowed_protocol( string $protocol ): bool {
        if ( ! in_array( $protocol, static::$allowed_protocols, true ) ) {
            return false;
        }

        return true;
    }
}
