<?php

declare(strict_types=1);

namespace GR\Wordpress\Core\logger;

class Gr_Logger_Configuration {

    private const LOG_DIR               = 'gr4wp-logs';
    private const DEFAULT_LOG_RETENTION = 7;
    private const DEFAULT_LOG_ENABLED   = true;
    private const LOGGER_RETENTION_KEY  = 'gr4wp-logger-retention';
    private const LOGGER_SALT_KEY       = 'gr4wp-logger-salt';
    private const LOGGER_ENABLED_KEY    = 'gr4wp-logger-enabled';

    public function get_retention(): int {
        return (int) get_option( self::LOGGER_RETENTION_KEY, self::DEFAULT_LOG_RETENTION );
    }

    public function set_retention( int $retention ): void {
        update_option( self::LOGGER_RETENTION_KEY, $retention );
    }

    public function is_logger_enabled(): bool {
        return (bool) get_option( self::LOGGER_ENABLED_KEY, self::DEFAULT_LOG_ENABLED );
    }

    public function set_logger_enabled( bool $enabled ): void {
        update_option( self::LOGGER_ENABLED_KEY, $enabled );
    }

    public function get_salt(): string {
        $salt = get_option( self::LOGGER_SALT_KEY );
        if ( false === $salt ) {
            // phpcs:ignore
            $salt = base64_encode( random_bytes( 10 ) );
            update_option( self::LOGGER_SALT_KEY, $salt );
        }
        return $salt;
    }

    public function get_log_dir(): string {
        $upload_dir = wp_upload_dir( null, false );
        return $upload_dir['basedir'] . '/' . self::LOG_DIR . '/';
    }

    public function get_log_base_url(): string {
        $upload_dir = wp_upload_dir( null, false );
        return $upload_dir['baseurl'] . '/' . self::LOG_DIR . '/';
    }

    public function delete_configuration(): void {
        delete_option( self::LOGGER_SALT_KEY );
        delete_option( self::LOGGER_ENABLED_KEY );
        delete_option( self::LOGGER_RETENTION_KEY );
    }
}
