<?php

declare(strict_types=1);

namespace GetResponse\WordPress\Core;

use Throwable;
use WC_Product;

class Functions {


	public static function get_allowed_html_elements(): array {
		return array(
			'label' => array(
				'class' => true,
			),
			'p'     => array(
				'class' => true,
			),
			'input' => array(
				'type'  => true,
				'name'  => true,
				'value' => true,
				'class' => true,
				'id'    => true,
			),
			'span'  => array(),
			'br'    => array(),
		);
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

	public static function get_error_context( Throwable $exception ): array {
		return array(
			'file'    => basename( $exception->getFile() ),
			'line'    => $exception->getLine(),
			'message' => $exception->getMessage(),
			'trace'   => $exception->getTraceAsString(),
		);
	}

	public static function add_marketing_consent_checkbox( string $marketing_consent_text ): void {

		if ( is_user_logged_in() ) {
			return;
		}

		if ( empty( $marketing_consent_text ) ) {
			return;
		}

		ob_start();

		printf( '<p class="%s">', esc_attr( Gr_Configuration::CSS_MARKETING_CONSENT_WRAPPER_CLASS ) );
		printf( '<label class="%s">', esc_attr( Gr_Configuration::CSS_MARKETING_CONSENT_LABEL_CLASS ) );
		printf( '<input type="checkbox" name="%s" value="1" class="%s" />', esc_attr( Gr_Configuration::MARKETING_CONSENT_META_NAME ), esc_attr( Gr_Configuration::CSS_MARKETING_CONSENT_CHECKBOX_CLASS ) );
		printf( '<span>%s</span>', esc_attr( $marketing_consent_text ) );
		echo '</label>';
		wp_nonce_field( Gr_Nonce_Field::ACTION_NAME, Gr_Nonce_Field::FIELD_NAME );
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

		if ( ! isset( $_SESSION[ $key ] ) ) {
			return null;
		}

		return sanitize_text_field( wp_unslash( $_SESSION[ $key ] ) );
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


	public static function set_cookie( $name, $value, $ttl ): void {
		setcookie( $name, (string) $value, time() + $ttl, '/' );
	}

	public static function get_cookie( $name ): ?string {
		$name = sanitize_key( $name );

		if ( empty( $_COOKIE[ $name ] ) ) {
			return null;
		}

		return sanitize_text_field( wp_unslash( $_COOKIE[ $name ] ) );
	}

	public static function delete_cookie( $name ): void {
		setcookie( $name, '', time() - 3600, '/' );
	}

	public static function get_product_price( WC_Product $product ): string {
		foreach ( $product->get_children() as $product_children_id ) {
			$child_product = wc_get_product( $product_children_id );
			if ( 'publish' === $child_product->get_status() ) {
				return (string) $child_product->get_price();
			}
		}

		return (string) $product->get_price();
	}

	public static function get_categories( WC_Product $product ): array {
		$categories = array();

		$terms = get_the_terms( $product->get_id(), 'product_cat' );

		if ( empty( $terms ) ) {
			return $categories;
		}

		foreach ( $terms as $category ) {
			$categories[] = array(
				'id'   => (string) $category->term_id,
				'name' => $category->name,
			);
		}
		return $categories;
	}
}
