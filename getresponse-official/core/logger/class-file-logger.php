<?php

declare(strict_types=1);

namespace GR\Wordpress\Core\logger;

use DateTime;
use Psr\Log\AbstractLogger;

class File_Logger extends AbstractLogger {

    private string $log_dir;
    private string $salt;

    public function __construct( string $log_dir, string $salt ) {
        $this->log_dir = $log_dir;
        $this->salt    = $salt;
    }

    public function log( $level, $message, array $context = array() ): void {

        if ( ! $this->should_log( $level ) ) {
            return;
        }

        $date_time = new DateTime();

        // phpcs:ignore
        @file_put_contents(
            $this->log_dir . $this->get_file_name( $date_time ),
            $this->format_message( $date_time, $level, $message, $context ) . PHP_EOL,
            FILE_APPEND
        );
    }

    private function format_message( DateTime $date_time, $level, $message, array $context ): string {
        return sprintf(
            '%s | %s | %s | %s',
            $date_time->format( 'Y-m-d H:i:s' ),
            $level,
            $message,
            wp_json_encode( $context )
        );
    }

    private function get_file_name( DateTime $date_time ): string {
        $date = $date_time->format( 'Y-m-d' );
        return 'log-' . $date . '-' . md5( $date . $this->salt ) . '.log';
    }

    private function should_log( $level ): bool {
        return true;
    }
}
