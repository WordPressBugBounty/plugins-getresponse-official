<?php

declare(strict_types=1);

namespace GetResponse\WordPress\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class User_Custom_Fields_Rest {

	private const IGNORED_META_KEYS = array(
		'admin_color',
		'comment_shortcuts',
		'community-events-location',
		'delay_botiga_disable_review_notice_partially',
		'deleted',
		'description',
		'gr_updated_at',
		'infinite_scrolling',
		'last_activity',
		'last_update',
		'paying_customer',
		'primary_blog',
		'rich_editing',
		'session_tokens',
		'show_admin_bar_front',
		'show_welcome_panel',
		'source_domain',
		'spam',
		'syntax_highlighting',
		'use_ssl',
		'user_activation_key',
		'user_email',
		'user_nicename',
		'user_pass',
		'user_registered',
		'user_status',
		'user_url',
		'wc_last_active',
	);

	private const IGNORED_PREFIXES = array(
		'closedpostboxes_',
		'dismissed_',
		'jetpack_',
		'manageedit-',
		'managenav-',
		'managetoplevel_page_',
		'meta-box-order_',
		'metaboxhidden_',
		'woocommerce_',
		'gr_',
		'wp_',
	);

	private const IGNORED_SUFFIXES = array(
		'_capabilities',
		'_dashboard_quick_press_last_post_id',
		'_per_page',
		'_persisted_preferences',
		'_user-settings',
		'_user-settings-time',
		'_user_level',
		'columnshidden',
	);

	private array $mapped_contact_custom_fields;
	private array $acf_field_keys_by_group = array();

	public function __construct( array $mapped_contact_custom_fields = array() ) {
		$this->mapped_contact_custom_fields = $mapped_contact_custom_fields;
	}

	public function init(): void {
		add_action( 'rest_api_init', array( $this, 'register_rest_field' ) );
		add_filter( 'woocommerce_rest_prepare_customer', array( $this, 'prepare_customer_custom_fields' ), 10, 3 );
	}

	public function register_rest_field(): void {
		register_rest_field(
			'user',
			'gr4wp_custom_fields',
			array(
				'get_callback' => array( $this, 'get_user_custom_fields_for_wp_rest' ),
				'schema'       => array(
					'description' => __( 'User custom fields / metadata.', 'getresponse-official' ),
					'type'        => 'object',
					'context'     => array( 'view', 'edit' ),
					'readonly'    => true,
				),
			)
		);
	}

	/**
	 * Extract user ID from user or response payload.
	 *
	 * @param mixed $user     User object, array, or ID.
	 * @param mixed $response Response object or array.
	 * @return int User ID or 0.
	 */
	private function extract_user_id( $user, $response = null ): int {
		if ( is_numeric( $user ) ) {
			return (int) $user;
		}

		if ( is_array( $user ) && isset( $user['id'] ) ) {
			return (int) $user['id'];
		}

		if ( is_object( $user ) ) {
			if ( isset( $user->ID ) ) {
				return (int) $user->ID;
			}
			if ( isset( $user->id ) ) {
				return (int) $user->id;
			}
			if ( method_exists( $user, 'get_id' ) ) {
				return (int) $user->get_id();
			}
		}

		if ( null !== $response ) {
			if ( is_object( $response ) && isset( $response->data['id'] ) ) {
				return (int) $response->data['id'];
			}
			if ( is_array( $response ) && isset( $response['id'] ) ) {
				return (int) $response['id'];
			}
		}

		return 0;
	}

	/**
	 * Callback for register_rest_field on the WP user object.
	 *
	 * @param array|object|mixed $user User response array or object.
	 * @return array<string, mixed> Cleaned custom fields.
	 */
	public function get_user_custom_fields_for_wp_rest( $user ): array {
		$user_id = $this->extract_user_id( $user );

		if ( $user_id <= 0 ) {
			return array();
		}

		return $this->get_mapped_user_custom_fields( $user_id );
	}

	/**
	 * Filter callback for woocommerce_rest_prepare_customer.
	 *
	 * @param mixed $response Response object or array.
	 * @param mixed $customer Customer object or data.
	 * @param mixed $request  REST request.
	 * @return mixed Response with custom fields added.
	 */
	public function prepare_customer_custom_fields( $response, $customer = null, $request = null ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$user_id       = $this->extract_user_id( $customer, $response );
		$custom_fields = $user_id > 0 ? $this->get_mapped_user_custom_fields( $user_id ) : array();

		if ( is_object( $response ) && method_exists( $response, 'get_data' ) && method_exists( $response, 'set_data' ) ) {
			$data = $response->get_data();
			if ( is_array( $data ) ) {
				$data['gr4wp_custom_fields'] = $custom_fields;
				$response->set_data( $data );
			}
		} elseif ( is_array( $response ) ) {
			$response['gr4wp_custom_fields'] = $custom_fields;
		}

		return $response;
	}

	/**
	 * Retrieve and clean custom fields for the given user ID.
	 *
	 * @param int $user_id User ID.
	 * @return array<string, mixed> Associative array of custom fields.
	 */
	public static function get_clean_user_custom_fields( int $user_id ): array {
		if ( $user_id <= 0 ) {
			return array();
		}

		$custom_fields = self::clean_user_meta( get_user_meta( $user_id ) );
		$custom_fields = self::normalize_locale( $custom_fields, $user_id );
		$acf_fields    = self::get_acf_user_custom_fields( $user_id );

		// Saved user meta has precedence over ACF default values.
		return array_replace( $acf_fields, $custom_fields );
	}

	/**
	 * Retrieve mapped user fields from user metadata and ACF values/defaults.
	 *
	 * @param int $user_id User ID.
	 * @return array<string, mixed> Mapped custom fields.
	 */
	public function get_mapped_user_custom_fields( int $user_id ): array {
		if ( $user_id <= 0 || empty( $this->mapped_contact_custom_fields ) ) {
			return array();
		}

		$acf_fields = array();
		if ( function_exists( 'acf_get_field_groups' ) && function_exists( 'acf_get_fields' ) && function_exists( 'get_field' ) ) {
			$field_groups = acf_get_field_groups(
				array(
					'user_id'   => $user_id,
					'user_form' => 'edit',
				)
			);
			if ( is_array( $field_groups ) ) {
				foreach ( $field_groups as $field_group ) {
					if ( empty( $field_group['show_in_rest'] ) ) {
						continue;
					}

					$group_key = ( function_exists( 'get_current_blog_id' ) ? get_current_blog_id() : 0 ) . ':' . ( $field_group['key'] ?? $field_group['ID'] ?? '' );
					if ( ! isset( $this->acf_field_keys_by_group[ $group_key ] ) ) {
						$this->acf_field_keys_by_group[ $group_key ] = array();
						$fields                                      = acf_get_fields( $field_group );
						if ( is_array( $fields ) ) {
							foreach ( $fields as $field ) {
								if ( ! is_array( $field ) || empty( $field['name'] ) || empty( $field['key'] ) || self::is_ignored_meta_key( (string) $field['name'] ) ) {
									continue;
								}
								if ( in_array( $field['name'], $this->mapped_contact_custom_fields, true ) ) {
									$this->acf_field_keys_by_group[ $group_key ][ $field['name'] ] = $field['key'];
								}
							}
						}
					}

					foreach ( $this->acf_field_keys_by_group[ $group_key ] as $name => $field_key ) {
						$value = get_field( $field_key, 'user_' . $user_id, false );
						if ( null !== $value ) {
							$acf_fields[ $name ] = $value;
						}
					}
				}
			}
		}

		// Explicitly stored metadata takes precedence over an ACF default.
		return self::filter_mapped_contact_custom_fields(
			array_replace( $acf_fields, self::get_stored_mapped_user_custom_fields( $user_id, $this->mapped_contact_custom_fields ) ),
			$this->mapped_contact_custom_fields
		);
	}

	/**
	 * Retrieve mapped stored user metadata without ACF defaults.
	 *
	 * @param int      $user_id User ID.
	 * @param string[] $mapped_contact_custom_fields Mapped metadata keys.
	 * @return array<string, mixed> Mapped stored custom fields.
	 */
	private static function get_stored_mapped_user_custom_fields( int $user_id, array $mapped_contact_custom_fields ): array {
		$custom_fields = array();

		foreach ( $mapped_contact_custom_fields as $key ) {
			if ( ! is_string( $key ) ) {
				continue;
			}

			$values = get_user_meta( $user_id, $key, false );

			if ( ! is_array( $values ) || empty( $values ) ) {
				continue;
			}

			$custom_fields[ $key ] = self::normalize_meta_value( $values );
		}

		return self::normalize_locale( $custom_fields, $user_id );
	}

	/**
	 * Filter custom fields by the configured contact custom field mapping.
	 *
	 * @param array<string, mixed> $custom_fields Custom fields.
	 * @param string[]             $mapped_contact_custom_fields Mapped metadata keys.
	 * @return array<string, mixed> Mapped custom fields.
	 */
	public static function filter_mapped_contact_custom_fields( array $custom_fields, array $mapped_contact_custom_fields ): array {
		$mapped_custom_fields = array();

		foreach ( $mapped_contact_custom_fields as $key ) {
			if ( ! is_string( $key ) || ! array_key_exists( $key, $custom_fields ) ) {
				continue;
			}

			$mapped_custom_fields[ $key ] = $custom_fields[ $key ];
		}

		return $mapped_custom_fields;
	}

	/**
	 * Retrieve stored user metadata keys and active ACF user field definitions.
	 *
	 * @return string[] Custom field names.
	 */
	public static function get_available_contact_custom_fields(): array {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- A distinct user meta key catalog has no non-N+1 WordPress API equivalent.
		$meta_keys = $wpdb->get_col( "SELECT DISTINCT meta_key FROM {$wpdb->usermeta}" );

		if ( ! is_array( $meta_keys ) ) {
			return array();
		}

		$custom_fields = array();

		foreach ( $meta_keys as $meta_key ) {
			$meta_key = (string) $meta_key;

			if (
				self::is_ignored_meta_key( $meta_key )
				|| self::is_woocommerce_meta_key( $meta_key )
			) {
				continue;
			}

			$custom_fields[] = $meta_key;
		}

		if ( function_exists( 'acf_get_field_groups' ) && function_exists( 'acf_get_fields' ) ) {
			// Discovery lists user fields across roles and forms, rather than matching one profile screen.
			$field_groups = acf_get_field_groups();

			if ( is_array( $field_groups ) ) {
				foreach ( $field_groups as $field_group ) {
					if ( ! self::is_acf_user_field_group( $field_group ) ) {
						continue;
					}

					$fields = acf_get_fields( $field_group );

					if ( ! is_array( $fields ) ) {
						continue;
					}

					foreach ( $fields as $field ) {
						if ( ! is_array( $field ) || empty( $field['key'] ) || empty( $field['name'] ) ) {
							continue;
						}

						$meta_key = (string) $field['name'];

						if ( self::is_ignored_meta_key( $meta_key ) || self::is_woocommerce_meta_key( $meta_key ) ) {
							continue;
						}

						$custom_fields[] = $meta_key;
					}
				}
			}
		}

		sort( $custom_fields, SORT_STRING );

		return array_values( array_unique( $custom_fields ) );
	}

	/**
	 * Check whether an active ACF group defines fields for users.
	 *
	 * @param array $field_group ACF field group definition.
	 * @return bool Whether any location alternative contains a user rule.
	 */
	private static function is_acf_user_field_group( array $field_group ): bool {
		if ( empty( $field_group['active'] ) ) {
			return false;
		}

		foreach ( $field_group['location'] ?? array() as $rules ) {
			foreach ( $rules as $rule ) {
				if ( in_array( $rule['param'] ?? '', array( 'user_role', 'user_form' ), true ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Clean user meta and preserve WordPress value semantics.
	 *
	 * @param mixed $user_meta Raw user meta.
	 * @return array<string, mixed> Cleaned user fields.
	 */
	private static function clean_user_meta( $user_meta ): array {
		if ( ! is_array( $user_meta ) ) {
			return array();
		}

		$custom_fields = array();

		foreach ( $user_meta as $key => $values ) {
			$key = (string) $key;

			if ( self::is_ignored_meta_key( $key ) ) {
				continue;
			}

			$custom_fields[ $key ] = self::normalize_meta_value( $values );
		}

		return $custom_fields;
	}

	/**
	 * Normalize one user meta value.
	 *
	 * @param mixed $values Raw value or array of raw values.
	 * @return mixed Normalized value.
	 */
	private static function normalize_meta_value( $values ) {
		if ( ! is_array( $values ) ) {
			return maybe_unserialize( $values );
		}

		$stored_values = array_values( $values );

		if ( empty( $stored_values ) ) {
			return '';
		}

		if ( count( $stored_values ) > 1 ) {
			return array_map( 'maybe_unserialize', $stored_values );
		}

		return maybe_unserialize( $stored_values[0] );
	}

	/**
	 * Replace an empty stored locale with WordPress's effective user locale.
	 *
	 * @param array<string, mixed> $custom_fields Custom fields.
	 * @param int                  $user_id       User ID.
	 * @return array<string, mixed> Custom fields with normalized locale.
	 */
	private static function normalize_locale( array $custom_fields, int $user_id ): array {
		if ( isset( $custom_fields['locale'] ) && '' === $custom_fields['locale'] && function_exists( 'get_user_locale' ) ) {
			$custom_fields['locale'] = get_user_locale( $user_id );
		}

		return $custom_fields;
	}

	/**
	 * Retrieve ACF user fields, including default values not saved as user meta.
	 *
	 * @param int $user_id User ID.
	 * @return array<string, mixed> ACF custom fields.
	 */
	private static function get_acf_user_custom_fields( int $user_id ): array {
		if (
			! function_exists( 'acf_get_field_groups' )
			|| ! function_exists( 'acf_get_fields' )
			|| ! function_exists( 'get_field' )
		) {
			return array();
		}

		$custom_fields = array();
		$field_groups  = acf_get_field_groups(
			array(
				'user_id'   => $user_id,
				'user_form' => 'edit',
			)
		);

		if ( ! is_array( $field_groups ) ) {
			return array();
		}

		foreach ( $field_groups as $field_group ) {
			if ( empty( $field_group['show_in_rest'] ) ) {
				continue;
			}

			$fields = acf_get_fields( $field_group );

			if ( ! is_array( $fields ) ) {
				continue;
			}

			foreach ( $fields as $field ) {
				if ( ! is_array( $field ) || empty( $field['name'] ) || empty( $field['key'] ) ) {
					continue;
				}

				$key = (string) $field['name'];

				if ( self::is_ignored_meta_key( $key ) ) {
					continue;
				}

				$value = get_field( $field['key'], 'user_' . $user_id, false );

				if ( null !== $value ) {
					$custom_fields[ $key ] = $value;
				}
			}
		}

		return $custom_fields;
	}

	/**
	 * Check whether a meta key should be ignored/filtered out.
	 *
	 * @param string $key Meta key.
	 * @return bool True if ignored, false otherwise.
	 */
	private static function is_ignored_meta_key( string $key ): bool {
		if ( '' === $key || '_' === $key[0] ) {
			return true;
		}

		if ( in_array( $key, self::IGNORED_META_KEYS, true ) ) {
			return true;
		}

		foreach ( self::IGNORED_PREFIXES as $prefix ) {
			if ( 0 === strpos( $key, $prefix ) ) {
				return true;
			}
		}

		foreach ( self::IGNORED_SUFFIXES as $suffix ) {
			if ( self::str_ends_with( $key, $suffix ) ) {
				return true;
			}
		}

		return false;
	}

	private static function is_woocommerce_meta_key( string $key ): bool {
		foreach ( array( 'billing_', 'shipping_', 'woocommerce_', 'wc_' ) as $prefix ) {
			if ( self::str_starts_with( $key, $prefix ) ) {
				return true;
			}
		}

		return false;
	}

	private static function str_starts_with( string $haystack, string $needle ): bool {
		if ( '' === $needle ) {
			return true;
		}

		return 0 === strpos( $haystack, $needle );
	}

	private static function str_ends_with( string $haystack, string $needle ): bool {
		if ( '' === $needle ) {
			return true;
		}
		$len = strlen( $needle );
		return substr( $haystack, -$len ) === $needle;
	}
}
