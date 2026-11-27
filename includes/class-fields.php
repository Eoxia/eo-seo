<?php
/**
 * Registry of the SEO fields.
 *
 * @package EoSeo
 */

namespace EoSeo;

defined( 'ABSPATH' ) || exit;

/**
 * Single source of truth for the SEO fields of contents and terms: it declares the
 * metas, and feeds the editor panel, the term form, the front tags, the checks and
 * the schemas of the abilities.
 */
final class Fields {

	/**
	 * Meta storing the per-page indicator level (problem, improve, good, noindex).
	 */
	public const STATUS_META = '_eo_seo_status';

	/**
	 * Meta storing the points of the indicator ("9/12").
	 */
	public const POINTS_META = '_eo_seo_points';

	/**
	 * Meta storing the failed checks of the indicator (id, severity, message).
	 */
	public const ISSUES_META = '_eo_seo_issues';

	/**
	 * Hooks.
	 */
	public static function init(): void {
		add_action( 'init', array( self::class, 'register_meta' ), 99 );
		// Post types and taxonomies registered late (after init 99) by a theme or a plugin.
		add_action( 'wp_loaded', array( self::class, 'register_meta' ) );
	}

	/**
	 * Field definitions.
	 *
	 * Each field: meta key, type (string, boolean, integer), input (text, textarea, url, checkbox, image),
	 * label, help, and the recommended length (min, max) when relevant.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function all(): array {
		$fields = array(
			'title'          => array(
				'meta'  => '_eo_seo_title',
				'type'  => 'string',
				'input' => 'text',
				'label' => __( 'SEO title', 'eo-seo' ),
				'help'  => __( 'Title shown by search engines. Leave empty to use the template.', 'eo-seo' ),
				'min'   => 30,
				'max'   => 60,
			),
			'description'    => array(
				'meta'  => '_eo_seo_description',
				'type'  => 'string',
				'input' => 'textarea',
				'label' => __( 'Meta description', 'eo-seo' ),
				'help'  => __( 'Summary shown under the title in search results. Leave empty to use the template (beginning of the text).', 'eo-seo' ),
				'min'   => 120,
				'max'   => 160,
			),
			'focus_keyword'  => array(
				'meta'  => '_eo_seo_focus_keyword',
				'type'  => 'string',
				'input' => 'text',
				'label' => __( 'Focus keyphrase', 'eo-seo' ),
				'help'  => __( 'The search query this page should rank for.', 'eo-seo' ),
			),
			'noindex'        => array(
				'meta'  => '_eo_seo_noindex',
				'type'  => 'boolean',
				'input' => 'checkbox',
				'label' => __( 'Hide from search engines (noindex)', 'eo-seo' ),
				'help'  => '',
			),
			'canonical'      => array(
				'meta'  => '_eo_seo_canonical',
				'type'  => 'string',
				'input' => 'url',
				'label' => __( 'Canonical URL', 'eo-seo' ),
				'help'  => __( 'Only if this page duplicates another one.', 'eo-seo' ),
			),
			'og_title'       => array(
				'meta'  => '_eo_seo_og_title',
				'type'  => 'string',
				'input' => 'text',
				'label' => __( 'Social title', 'eo-seo' ),
				'help'  => __( 'Title when the page is shared. Defaults to the SEO title.', 'eo-seo' ),
			),
			'og_description' => array(
				'meta'  => '_eo_seo_og_description',
				'type'  => 'string',
				'input' => 'textarea',
				'label' => __( 'Social description', 'eo-seo' ),
				'help'  => __( 'Defaults to the meta description.', 'eo-seo' ),
			),
			'og_image_id'    => array(
				'meta'  => '_eo_seo_og_image_id',
				'type'  => 'integer',
				'input' => 'image',
				'label' => __( 'Social image', 'eo-seo' ),
				'help'  => __( 'Defaults to the featured image, then to the default image of the site.', 'eo-seo' ),
			),
		);

		/**
		 * Filters the SEO fields.
		 *
		 * @param array<string, array<string, mixed>> $fields Fields keyed by name.
		 */
		return (array) apply_filters( 'eo_seo_fields', $fields );
	}

	/**
	 * Declares the post and term metas (show_in_rest: the block editor saves them itself).
	 */
	public static function register_meta(): void {
		Settings::flush();

		foreach ( array_keys( Settings::post_types() ) as $post_type ) {
			// Without "custom-fields", the REST API of a custom post type hides its metas: the editor could not save them.
			if ( ! post_type_supports( $post_type, 'custom-fields' ) ) {
				add_post_type_support( $post_type, 'custom-fields' );
			}
		}

		foreach ( self::all() as $field ) {
			$args = array(
				'type'              => $field['type'],
				'single'            => true,
				'show_in_rest'      => true,
				'default'           => self::default_value( $field ),
				'sanitize_callback' => static fn( $value ) => self::sanitize( $field, $value ),
			);

			foreach ( array_keys( Settings::post_types() ) as $post_type ) {
				if ( registered_meta_key_exists( 'post', $field['meta'], $post_type ) ) {
					continue; // Called again on wp_loaded for the late post types.
				}
				register_post_meta(
					$post_type,
					$field['meta'],
					$args + array(
						'auth_callback' => static fn( $allowed, $meta_key, $post_id ) => current_user_can( 'edit_post', (int) $post_id ),
					)
				);
			}

			foreach ( array_keys( Settings::taxonomies() ) as $taxonomy ) {
				if ( registered_meta_key_exists( 'term', $field['meta'], $taxonomy ) ) {
					continue;
				}
				register_term_meta(
					$taxonomy,
					$field['meta'],
					$args + array(
						'auth_callback' => static fn( $allowed, $meta_key, $term_id ) => current_user_can( 'edit_term', (int) $term_id ),
					)
				);
			}
		}

		// The indicator is computed by EO SEO: readable in the editor, never written through REST.
		foreach ( array( self::STATUS_META, self::POINTS_META ) as $meta ) {
			foreach ( array_keys( Settings::post_types() ) as $post_type ) {
				if ( registered_meta_key_exists( 'post', $meta, $post_type ) ) {
					continue;
				}
				register_post_meta(
					$post_type,
					$meta,
					array(
						'type'          => 'string',
						'single'        => true,
						'show_in_rest'  => true,
						'auth_callback' => '__return_false',
					)
				);
			}
		}
	}

	/**
	 * Default value of a field.
	 *
	 * @param array<string, mixed> $field Field.
	 * @return string|bool|int
	 */
	public static function default_value( array $field ): string|bool|int {
		return match ( $field['type'] ) {
			'boolean' => false,
			'integer' => 0,
			default   => '',
		};
	}

	/**
	 * Sanitizes the value of a field.
	 *
	 * @param array<string, mixed> $field Field.
	 * @param mixed                $value Raw value.
	 * @return string|bool|int
	 */
	public static function sanitize( array $field, mixed $value ): string|bool|int {
		return match ( $field['input'] ) {
			'checkbox' => (bool) rest_sanitize_boolean( $value ),
			'image'    => absint( $value ),
			'url'      => esc_url_raw( trim( (string) $value ) ),
			'textarea' => sanitize_textarea_field( (string) $value ),
			default    => sanitize_text_field( (string) $value ),
		};
	}

	/**
	 * Values of the fields of a content.
	 *
	 * @param int $post_id Post ID.
	 * @return array<string, string|bool|int> Keyed by field name.
	 */
	public static function get_post_values( int $post_id ): array {
		$values = array();
		foreach ( self::all() as $name => $field ) {
			$raw             = get_post_meta( $post_id, $field['meta'], true );
			$values[ $name ] = '' === $raw ? self::default_value( $field ) : self::sanitize( $field, $raw );
		}

		return $values;
	}

	/**
	 * Values of the fields of a term.
	 *
	 * @param int $term_id Term ID.
	 * @return array<string, string|bool|int> Keyed by field name.
	 */
	public static function get_term_values( int $term_id ): array {
		$values = array();
		foreach ( self::all() as $name => $field ) {
			$raw             = get_term_meta( $term_id, $field['meta'], true );
			$values[ $name ] = '' === $raw ? self::default_value( $field ) : self::sanitize( $field, $raw );
		}

		return $values;
	}

	/**
	 * Saves fields of a content or a term (empty values delete the meta).
	 *
	 * @param string                $object_type post or term.
	 * @param int                   $object_id   ID.
	 * @param array<string, mixed>  $values      Values keyed by field name; unknown names are ignored.
	 */
	public static function save( string $object_type, int $object_id, array $values ): void {
		$fields = self::all();
		foreach ( $values as $name => $value ) {
			if ( ! isset( $fields[ $name ] ) ) {
				continue;
			}
			$clean = self::sanitize( $fields[ $name ], $value );
			if ( '' === $clean || false === $clean || 0 === $clean ) {
				delete_metadata( $object_type, $object_id, $fields[ $name ]['meta'] );
			} else {
				update_metadata( $object_type, $object_id, $fields[ $name ]['meta'], $clean );
			}
		}
	}

	/**
	 * JSON schema of the fields, for the abilities.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function json_schema(): array {
		$properties = array();
		foreach ( self::all() as $name => $field ) {
			$schema = array(
				'type'        => $field['type'],
				'description' => trim( $field['label'] . '. ' . $field['help'] ),
			);
			if ( isset( $field['max'] ) ) {
				$schema['description'] .= ' ' . sprintf(
					/* translators: 1: minimum length, 2: maximum length. */
					__( 'Recommended length: %1$d to %2$d characters.', 'eo-seo' ),
					$field['min'],
					$field['max']
				);
			}
			$properties[ $name ] = $schema;
		}

		return $properties;
	}
}
