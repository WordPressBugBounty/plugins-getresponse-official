<?php

declare(strict_types=1);

namespace GR\Wordpress\Core;

use Exception;

class Functions {

    public static function get_allowed_html_elements(): array {
        return [
            'label' => [],
            'p'     => [],
            'input' => [
                'type'  => [ 'checkbox' ],
                'name'  => [],
                'value' => [],
                'class' => [],
            ],
            'span'  => [],
            'br'    => [],
        ];
    }

    public static function get_wp_version(): string {
        require ABSPATH . WPINC . '/version.php';
        return $wp_version;
    }

    public static function get_php_version(): string {
        return PHP_VERSION;
    }

    public static function get_plugin_version(): string {
        return GETRESPONSE_FOR_WP_VERSION;
    }

    public static function get_error_context( Exception $exception ): array {
        return [
            'file'    => basename( $exception->getFile() ),
            'line'    => $exception->getLine(),
            'message' => $exception->getMessage(),
            'trace'   => $exception->getTraceAsString(),
        ];
    }

    public static function add_marketing_consent_checkbox( string $marketing_consent_text ): void {

        if ( is_user_logged_in() ) {
            return;
        }

        if ( empty( $marketing_consent_text ) ) {
            return;
        }

        ob_start();

        echo sprintf( '<p class="%s">', esc_attr( Gr_Configuration::CSS_MARKETING_CONSENT_WRAPPER_CLASS ) );
        echo '<label>';
        echo sprintf( '<input type="checkbox" name="%s" value="1" class="%s" />', esc_attr( Gr_Configuration::MARKETING_CONSENT_META_NAME ), esc_attr( Gr_Configuration::CSS_MARKETING_CONSENT_CHECKBOX_CLASS ) );
        echo sprintf( '<span class="%s">%s</span>', esc_attr( Gr_Configuration::CSS_MARKETING_CONSENT_LABEL_CLASS ), esc_attr( $marketing_consent_text ) );
        echo '</label>';
        echo '</p>';
        echo '<br />';

        $html = ob_get_clean();

        echo wp_kses( $html, self::get_allowed_html_elements() );
    }

    public static function session_set( $key, $value ): void {
        if ( ! session_id() && ! headers_sent() ) {
            session_start();
        }

        $session_key   = sanitize_key( $key );
        $session_value = sanitize_text_field( $value );

        $_SESSION[ $session_key ] = $session_value;
    }

    public static function session_get( $key ): ?string {

        if ( ! session_id() && ! headers_sent() ) {
            session_start();
        }

        return isset( $_SESSION[ $key ] ) ? esc_attr( $_SESSION[ $key ] ) : null;
    }

    public static function session_get_or_set( $key, $value ): ?string {
        if ( ! session_id() && ! headers_sent() ) {
            session_start();
        }

        $session_key   = sanitize_key( $key );
        $session_value = sanitize_text_field( $value );

        if ( isset( $_SESSION[ $session_key ] ) ) {
            return esc_attr( $_SESSION[ $session_key ] );
        }

        $_SESSION[ $session_key ] = $session_value;

        return $session_value;
    }

    public static function session_get_and_clear( $key ): ?string {
        if ( ! session_id() && ! headers_sent() ) {
            session_start();
        }

        $session_key = sanitize_key( $key );
        $value       = null;

        if ( isset( $_SESSION[ $session_key ] ) ) {
            $value = sanitize_text_field( $_SESSION[ $session_key ] );
            unset( $_SESSION[ $session_key ] );
        }

        return ! empty( $value ) ? $value : null;
    }
}
