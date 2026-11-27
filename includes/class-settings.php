<?php
/**
 * Settings of the plugin (one option, eo_seo_settings).
 *
 * @package EoSeo
 */

namespace EoSeo;

defined( 'ABSPATH' ) || exit;

/**
 * Reads, sanitizes and registers the settings.
 */
final class Settings {

	public const OPTION = 'eo_seo_settings';

	/**
	 * Settings tabs and the keys each one saves.
	 */
	public const TABS = array(
		'appearance' => array( 'separator', 'templates' ),
		'indexing'   => array( 'index', 'noindex_search', 'noindex_date', 'author_archives', 'robots_rules', 'cleanup' ),
		'identity'   => array( 'identity' ),
		'social'     => array( 'default_og_image', 'x_username' ),
		'webmaster'  => array( 'verification' ),
		'tools'      => array( 'not_found_retention', 'delete_data' ),
	);

	/**
	 * Allowed title separators.
	 */
	public const SEPARATORS = array( '-', '–', '—', '|', '·', '•', '/', '»' );

	/**
	 * Settings merged with the defaults, cached for the request.
	 *
	 * @var array<string, mixed>|null
	 */
	private static ?array $cache = null;

	/**
	 * Hooks.
	 */
	public static function init(): void {
		add_action( 'init', array( self::class, 'register' ) );
		add_action( 'update_option_' . self::OPTION, array( self::class, 'flush' ) );
		add_action( 'add_option_' . self::OPTION, array( self::class, 'flush' ) );
		// The defaults depend on the registered post types and taxonomies.
		add_action( 'registered_post_type', array( self::class, 'flush' ) );
		add_action( 'registered_taxonomy', array( self::class, 'flush' ) );
	}

	/**
	 * Registers the option.
	 */
	public static function register(): void {
		register_setting(
			'eo_seo',
			self::OPTION,
			array(
				'type'              => 'object',
				'description'       => __( 'EO SEO settings.', 'eo-seo' ),
				'sanitize_callback' => array( self::class, 'sanitize_input' ),
				'show_in_rest'      => false,
				'default'           => array(),
			)
		);
	}

	/**
	 * Empties the request cache.
	 */
	public static function flush(): void {
		self::$cache = null;
	}

	/**
	 * Post types EO SEO handles (public, with an admin interface).
	 *
	 * @return \WP_Post_Type[] Keyed by name.
	 */
	public static function post_types(): array {
		// Every public post type with an admin screen: pages, posts and the custom post types (products, events…).
		$types = get_post_types(
			array(
				'public'  => true,
				'show_ui' => true,
			),
			'objects'
		);
		unset( $types['attachment'] );

		/**
		 * Filters the post types handled by EO SEO (fields, indicator, sitemap, settings).
		 *
		 * @param \WP_Post_Type[] $types Post types keyed by name.
		 */
		return array_filter( (array) apply_filters( 'eo_seo_post_types', $types ), static fn( $type ) => $type instanceof \WP_Post_Type );
	}

	/**
	 * Taxonomies EO SEO handles.
	 *
	 * @return \WP_Taxonomy[] Keyed by name.
	 */
	public static function taxonomies(): array {
		$taxonomies = get_taxonomies(
			array(
				'public'  => true,
				'show_ui' => true,
			),
			'objects'
		);
		unset( $taxonomies['post_format'] );

		/**
		 * Filters the taxonomies handled by EO SEO (fields, indicator, sitemap, settings).
		 *
		 * @param \WP_Taxonomy[] $taxonomies Taxonomies keyed by name.
		 */
		return array_filter( (array) apply_filters( 'eo_seo_taxonomies', $taxonomies ), static fn( $taxonomy ) => $taxonomy instanceof \WP_Taxonomy );
	}

	/**
	 * Default settings.
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults(): array {
		$templates = array(
			'home'   => array(
				'title'       => '%sitename% %sep% %tagline%',
				'description' => '%tagline%',
			),
			'author' => array(
				'title'       => '%author% %sep% %sitename%',
				'description' => '',
			),
			'date'   => array(
				'title'       => '%date% %sep% %sitename%',
				'description' => '',
			),
			'search' => array(
				'title'       => '%search% %sep% %sitename%',
				'description' => '',
			),
		);
		$index     = array(
			'post_types' => array(),
			'taxonomies' => array(),
		);

		foreach ( self::post_types() as $name => $type ) {
			$templates[ 'post_type:' . $name ] = array(
				'title'       => '%title% %page% %sep% %sitename%',
				'description' => '%excerpt%',
			);
			if ( $type->has_archive ) {
				$templates[ 'archive:' . $name ] = array(
					'title'       => '%archive% %page% %sep% %sitename%',
					'description' => '',
				);
			}
			$index['post_types'][ $name ] = true;
		}

		foreach ( array_keys( self::taxonomies() ) as $name ) {
			$templates[ 'taxonomy:' . $name ] = array(
				'title'       => '%term% %page% %sep% %sitename%',
				'description' => '%excerpt%',
			);
			$index['taxonomies'][ $name ] = true;
		}

		return array(
			'separator'           => '–',
			'templates'           => $templates,
			'index'               => $index,
			'noindex_search'      => true,
			'noindex_date'        => true,
			'author_archives'     => true,
			'robots_rules'        => '',
			'cleanup'             => array(
				'extra_feeds'   => false,
				'head_links'    => false,
				'replytocom'    => true,
				'comment_pages' => true,
			),
			'default_og_image'    => 0,
			'x_username'          => '',
			'identity'            => array(
				'type'        => 'organization',
				'name'        => '',
				'logo'        => 0,
				'street'      => '',
				'postal_code' => '',
				'city'        => '',
				'country'     => '',
				'phone'       => '',
				'same_as'     => array(),
			),
			'verification'        => array(
				'google' => '',
				'bing'   => '',
			),
			'not_found_retention' => 90,
			'delete_data'         => false,
		);
	}

	/**
	 * JSON schema of the settings (top-level keys). Static: also the input schema of
	 * eo-seo/update-settings, which MCP clients cache.
	 *
	 * @return array<string, mixed>
	 */
	public static function schema(): array {
		$template = array(
			'type'       => 'object',
			'properties' => array(
				'title'       => array( 'type' => 'string' ),
				'description' => array( 'type' => 'string' ),
			),
		);
		$switches = array(
			'type'                 => 'object',
			'additionalProperties' => array( 'type' => 'boolean' ),
		);

		return array(
			'type'       => 'object',
			'properties' => array(
				'separator'           => array(
					'type' => 'string',
					'enum' => self::SEPARATORS,
				),
				'templates'           => array(
					'type'                 => 'object',
					'description'          => 'Title and description templates, keyed by context: home, post_type:{name}, archive:{name}, taxonomy:{name}, author, date, search. Variables: %title%, %excerpt%, %term%, %archive%, %author%, %date%, %search%, %sitename%, %tagline%, %sep%, %page%.',
					'additionalProperties' => $template,
				),
				'index'               => array(
					'type'        => 'object',
					'description' => 'Shown to search engines (false: noindex and out of the sitemap), per post type and per taxonomy.',
					'properties'  => array(
						'post_types' => $switches,
						'taxonomies' => $switches,
					),
				),
				'noindex_search'      => array( 'type' => 'boolean' ),
				'noindex_date'        => array( 'type' => 'boolean' ),
				'author_archives'     => array(
					'type'        => 'boolean',
					'description' => 'false: author archives redirect to the home page.',
				),
				'robots_rules'        => array(
					'type'        => 'string',
					'description' => 'Extra lines of the virtual robots.txt.',
				),
				'cleanup'             => array(
					'type'       => 'object',
					'properties' => array(
						'extra_feeds'   => array( 'type' => 'boolean' ),
						'head_links'    => array( 'type' => 'boolean' ),
						'replytocom'    => array( 'type' => 'boolean' ),
						'comment_pages' => array( 'type' => 'boolean' ),
					),
				),
				'default_og_image'    => array(
					'type'        => 'integer',
					'description' => 'Attachment ID of the default sharing image.',
				),
				'x_username'          => array( 'type' => 'string' ),
				'identity'            => array(
					'type'       => 'object',
					'properties' => array(
						'type'        => array(
							'type' => 'string',
							'enum' => array( 'organization', 'person', 'local_business' ),
						),
						'name'        => array( 'type' => 'string' ),
						'logo'        => array( 'type' => 'integer' ),
						'street'      => array( 'type' => 'string' ),
						'postal_code' => array( 'type' => 'string' ),
						'city'        => array( 'type' => 'string' ),
						'country'     => array( 'type' => 'string' ),
						'phone'       => array( 'type' => 'string' ),
						'same_as'     => array(
							'type'  => 'array',
							'items' => array( 'type' => 'string' ),
						),
					),
				),
				'verification'        => array(
					'type'       => 'object',
					'properties' => array(
						'google' => array( 'type' => 'string' ),
						'bing'   => array( 'type' => 'string' ),
					),
				),
				'not_found_retention' => array(
					'type'    => 'integer',
					'minimum' => 7,
					'maximum' => 365,
				),
				'delete_data'         => array( 'type' => 'boolean' ),
			),
		);
	}

	/**
	 * Changes some settings (code, abilities): only the given keys change, the others are kept.
	 * Each change is logged with its old value.
	 *
	 * @param array<string, mixed> $changes Settings keyed by top-level key; nested objects may be partial.
	 * @param string               $source  Who changes them, for the log (ai, …).
	 * @return array<string, array{before: mixed, after: mixed}> Changes really made.
	 */
	public static function update( array $changes, string $source = 'ai' ): array {
		$current = self::all();
		$next    = $current;

		foreach ( $changes as $key => $value ) {
			if ( ! array_key_exists( $key, $current ) || in_array( $key, array( 'delete_data' ), true ) ) {
				continue; // Unknown keys, and the deletion of the data at uninstallation (human only).
			}
			// Partial nested values: the missing sub-keys keep their current value.
			if ( is_array( $value ) && is_array( $current[ $key ] ) && ! array_is_list( $current[ $key ] ) ) {
				$value = array_replace_recursive( $current[ $key ], $value );
			}
			$next[ $key ] = self::sanitize_value( $key, $value, $current[ $key ] );
		}

		$made = array();
		foreach ( $next as $key => $value ) {
			if ( $value !== $current[ $key ] ) {
				$made[ $key ] = array(
					'before' => $current[ $key ],
					'after'  => $value,
				);
			}
		}

		if ( $made ) {
			update_option( self::OPTION, $next );
			self::log( $made, $source );
		}

		return $made;
	}

	/**
	 * Keeps the last changes made outside the settings screen, with their old value.
	 *
	 * @param array<string, array{before: mixed, after: mixed}> $changes Changes.
	 * @param string                                            $source  Who made them.
	 */
	private static function log( array $changes, string $source ): void {
		$log   = get_option( 'eo_seo_settings_log', array() );
		$log   = is_array( $log ) ? $log : array();
		$log[] = array(
			'time'    => time(),
			'user'    => get_current_user_id(),
			'source'  => $source,
			'changes' => $changes,
		);

		update_option( 'eo_seo_settings_log', array_slice( $log, -50 ), false );
	}

	/**
	 * Last changes made outside the settings screen, newest first.
	 *
	 * @return array<int, array{time: int, user: int, source: string, changes: array<string, array{before: mixed, after: mixed}>}>
	 */
	public static function get_log(): array {
		$log = get_option( 'eo_seo_settings_log', array() );

		return is_array( $log ) ? array_reverse( $log ) : array();
	}

	/**
	 * All the settings, merged with the defaults.
	 *
	 * @return array<string, mixed>
	 */
	public static function all(): array {
		if ( null !== self::$cache ) {
			return self::$cache;
		}

		$stored = get_option( self::OPTION, array() );
		$all    = self::merge( self::defaults(), is_array( $stored ) ? $stored : array() );

		// Post types and taxonomies are only all known once init has run.
		if ( did_action( 'init' ) ) {
			self::$cache = $all;
		}

		return $all;
	}

	/**
	 * One setting.
	 *
	 * @param string $key Top-level key.
	 * @return mixed
	 */
	public static function get( string $key ): mixed {
		$all = self::all();

		return $all[ $key ] ?? null;
	}

	/**
	 * Title and description templates of a context.
	 *
	 * @param string $key Template key (home, post_type:page, taxonomy:category…).
	 * @return array{title: string, description: string}
	 */
	public static function template( string $key ): array {
		$templates = (array) self::get( 'templates' );

		return wp_parse_args(
			$templates[ $key ] ?? array(),
			array(
				'title'       => '%title% %sep% %sitename%',
				'description' => '',
			)
		);
	}

	/**
	 * Whether a post type is shown to search engines.
	 *
	 * @param string $post_type Post type.
	 */
	public static function is_post_type_indexed( string $post_type ): bool {
		$index = (array) self::get( 'index' );

		return ! empty( $index['post_types'][ $post_type ] );
	}

	/**
	 * Whether a taxonomy is shown to search engines.
	 *
	 * @param string $taxonomy Taxonomy.
	 */
	public static function is_taxonomy_indexed( string $taxonomy ): bool {
		$index = (array) self::get( 'index' );

		return ! empty( $index['taxonomies'][ $taxonomy ] );
	}

	/**
	 * Recursive merge where the stored values win, and only known keys are kept.
	 *
	 * @param array<string, mixed> $defaults Defaults.
	 * @param array<string, mixed> $stored   Stored values.
	 * @return array<string, mixed>
	 */
	private static function merge( array $defaults, array $stored ): array {
		foreach ( $defaults as $key => $default ) {
			if ( ! array_key_exists( $key, $stored ) ) {
				continue;
			}
			if ( is_array( $default ) && is_array( $stored[ $key ] ) && ! array_is_list( $default ) ) {
				$defaults[ $key ] = self::merge( $default, $stored[ $key ] );
			} elseif ( is_array( $default ) === is_array( $stored[ $key ] ) ) {
				$defaults[ $key ] = $stored[ $key ];
			}
		}

		return $defaults;
	}

	/**
	 * Sanitizes the input of a settings form (one tab at a time).
	 *
	 * @param mixed $input Raw input.
	 * @return array<string, mixed> Stored value: the current settings with the submitted tab replaced.
	 */
	public static function sanitize_input( mixed $input ): array {
		$current = self::all();

		if ( ! is_array( $input ) ) {
			return $current;
		}

		// A full settings array (update_option() from code) is saved as is, once sanitized.
		$tabs = isset( $input['_tab'] ) ? array( sanitize_key( $input['_tab'] ) ) : array_keys( self::TABS );

		foreach ( $tabs as $tab ) {
			if ( ! isset( self::TABS[ $tab ] ) ) {
				continue;
			}
			foreach ( self::TABS[ $tab ] as $key ) {
				$current[ $key ] = self::sanitize_value( $key, $input[ $key ] ?? null, $current[ $key ] );
			}
		}

		// Attachment pages are a WordPress option (6.4+), set from the Indexing tab.
		if ( in_array( 'indexing', $tabs, true ) && array_key_exists( 'attachment_pages', $input ) ) {
			update_option( 'wp_attachment_pages_enabled', empty( $input['attachment_pages'] ) ? '0' : '1' );
		}

		return $current;
	}

	/**
	 * Sanitizes one top-level setting.
	 *
	 * @param string $key      Setting.
	 * @param mixed  $value    Submitted value (null when a checkbox was not sent).
	 * @param mixed  $previous Current value.
	 * @return mixed
	 */
	public static function sanitize_value( string $key, mixed $value, mixed $previous ): mixed {
		switch ( $key ) {
			case 'separator':
				return in_array( $value, self::SEPARATORS, true ) ? $value : $previous;

			case 'templates':
				$clean = (array) $previous;
				foreach ( (array) $value as $context => $template ) {
					$context = preg_replace( '/[^a-z0-9_:\-]/', '', strtolower( (string) $context ) );
					if ( ! isset( $clean[ $context ] ) || ! is_array( $template ) ) {
						continue;
					}
					$clean[ $context ] = array(
						'title'       => sanitize_text_field( (string) ( $template['title'] ?? '' ) ),
						'description' => sanitize_textarea_field( (string) ( $template['description'] ?? '' ) ),
					);
				}
				return $clean;

			case 'index':
				$clean = (array) $previous;
				foreach ( array( 'post_types', 'taxonomies' ) as $group ) {
					foreach ( array_keys( (array) ( $clean[ $group ] ?? array() ) ) as $name ) {
						$clean[ $group ][ $name ] = ! empty( $value[ $group ][ $name ] );
					}
				}
				return $clean;

			case 'cleanup':
				$clean = array();
				foreach ( array_keys( (array) $previous ) as $name ) {
					$clean[ $name ] = ! empty( $value[ $name ] );
				}
				return $clean;

			case 'noindex_search':
			case 'noindex_date':
			case 'author_archives':
			case 'delete_data':
				return ! empty( $value );

			case 'robots_rules':
				return sanitize_textarea_field( (string) $value );

			case 'default_og_image':
				return absint( $value );

			case 'not_found_retention':
				return min( 365, max( 7, absint( $value ) ) );

			case 'x_username':
				return ltrim( sanitize_text_field( (string) $value ), '@' );

			case 'identity':
				$value = (array) $value;
				$type  = sanitize_key( (string) ( $value['type'] ?? '' ) );
				$urls  = is_array( $value['same_as'] ?? null ) ? $value['same_as'] : preg_split( '/\R/', (string) ( $value['same_as'] ?? '' ) );
				return array(
					'type'        => in_array( $type, array( 'organization', 'person', 'local_business' ), true ) ? $type : 'organization',
					'name'        => sanitize_text_field( (string) ( $value['name'] ?? '' ) ),
					'logo'        => absint( $value['logo'] ?? 0 ),
					'street'      => sanitize_text_field( (string) ( $value['street'] ?? '' ) ),
					'postal_code' => sanitize_text_field( (string) ( $value['postal_code'] ?? '' ) ),
					'city'        => sanitize_text_field( (string) ( $value['city'] ?? '' ) ),
					'country'     => sanitize_text_field( (string) ( $value['country'] ?? '' ) ),
					'phone'       => sanitize_text_field( (string) ( $value['phone'] ?? '' ) ),
					'same_as'     => array_values( array_filter( array_map( 'esc_url_raw', array_map( 'trim', (array) $urls ) ) ) ),
				);

			case 'verification':
				$value = (array) $value;
				return array(
					'google' => self::sanitize_verification( (string) ( $value['google'] ?? '' ) ),
					'bing'   => self::sanitize_verification( (string) ( $value['bing'] ?? '' ) ),
				);
		}

		return $previous;
	}

	/**
	 * Keeps only the code of a verification tag (the whole <meta> tag may be pasted).
	 *
	 * @param string $value Code or tag.
	 */
	private static function sanitize_verification( string $value ): string {
		if ( preg_match( '/content=["\']([^"\']+)["\']/i', $value, $match ) ) {
			$value = $match[1];
		}

		return preg_replace( '/[^A-Za-z0-9_\-]/', '', $value );
	}
}
