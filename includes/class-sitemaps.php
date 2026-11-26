<?php
/**
 * WordPress sitemaps and virtual robots.txt.
 *
 * @package EoSeo
 */

namespace EoSeo;

defined( 'ABSPATH' ) || exit;

/**
 * Drives the native sitemaps (no sitemap of our own) and adds rules to the virtual robots.txt.
 */
final class Sitemaps {

	/**
	 * Hooks.
	 */
	public static function init(): void {
		add_filter( 'wp_sitemaps_post_types', array( self::class, 'post_types' ) );
		add_filter( 'wp_sitemaps_taxonomies', array( self::class, 'taxonomies' ) );
		add_filter( 'wp_sitemaps_add_provider', array( self::class, 'providers' ), 10, 2 );
		add_filter( 'wp_sitemaps_posts_query_args', array( self::class, 'posts_query' ) );
		add_filter( 'wp_sitemaps_taxonomies_query_args', array( self::class, 'terms_query' ) );
		add_filter( 'wp_sitemaps_posts_entry', array( self::class, 'post_entry' ), 10, 2 );
		add_filter( 'robots_txt', array( self::class, 'robots_txt' ), 20, 2 );
	}

	/**
	 * Only the post types shown to search engines.
	 *
	 * @param \WP_Post_Type[] $post_types Post types.
	 * @return \WP_Post_Type[]
	 */
	public static function post_types( array $post_types ): array {
		return array_filter( $post_types, static fn( $type ) => Settings::is_post_type_indexed( $type->name ) );
	}

	/**
	 * Only the taxonomies shown to search engines.
	 *
	 * @param \WP_Taxonomy[] $taxonomies Taxonomies.
	 * @return \WP_Taxonomy[]
	 */
	public static function taxonomies( array $taxonomies ): array {
		return array_filter( $taxonomies, static fn( $taxonomy ) => Settings::is_taxonomy_indexed( $taxonomy->name ) );
	}

	/**
	 * No users sitemap when the author archives are turned off.
	 *
	 * @param \WP_Sitemaps_Provider|false $provider Provider.
	 * @param string                      $name     Provider name.
	 * @return \WP_Sitemaps_Provider|false
	 */
	public static function providers( $provider, string $name ) {
		return 'users' === $name && ! Settings::get( 'author_archives' ) ? false : $provider;
	}

	/**
	 * Contents in noindex are left out.
	 *
	 * @param array<string, mixed> $args WP_Query arguments.
	 * @return array<string, mixed>
	 */
	public static function posts_query( array $args ): array {
		$args['meta_query'] = self::without_noindex( $args['meta_query'] ?? array() ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Sitemaps are paginated and cached by search engines.

		return $args;
	}

	/**
	 * Terms in noindex are left out.
	 *
	 * @param array<string, mixed> $args WP_Term_Query arguments.
	 * @return array<string, mixed>
	 */
	public static function terms_query( array $args ): array {
		$args['meta_query'] = self::without_noindex( $args['meta_query'] ?? array() ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Sitemaps are paginated and cached by search engines.

		return $args;
	}

	/**
	 * Adds the "not in noindex" clause to a meta query.
	 *
	 * @param array<int|string, mixed> $meta_query Meta query.
	 * @return array<int|string, mixed>
	 */
	private static function without_noindex( array $meta_query ): array {
		$meta_query[] = array(
			'relation' => 'OR',
			array(
				'key'     => '_eo_seo_noindex',
				'compare' => 'NOT EXISTS',
			),
			array(
				'key'     => '_eo_seo_noindex',
				'value'   => '1',
				'compare' => '!=',
			),
		);

		return $meta_query;
	}

	/**
	 * Adds the date of last modification.
	 *
	 * @param array<string, string> $entry Sitemap entry.
	 * @param \WP_Post              $post  Content.
	 * @return array<string, string>
	 */
	public static function post_entry( array $entry, \WP_Post $post ): array {
		$entry['lastmod'] = (string) get_post_modified_time( DATE_W3C, true, $post );

		return $entry;
	}

	/**
	 * Extra rules of the virtual robots.txt (WordPress adds the Sitemap line itself).
	 *
	 * @param string     $output Content of robots.txt.
	 * @param string|int $public Option blog_public ("1": visible to search engines).
	 */
	public static function robots_txt( string $output, $public ): string {
		$rules = trim( (string) Settings::get( 'robots_rules' ) );
		if ( (int) $public && '' !== $rules ) {
			$output .= "\n" . $rules . "\n";
		}

		return $output;
	}
}
