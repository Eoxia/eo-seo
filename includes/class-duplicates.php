<?php
/**
 * Duplicate titles, descriptions and keyphrases.
 *
 * @package EoSeo
 */

namespace EoSeo;

defined( 'ABSPATH' ) || exit;

/**
 * Finds another published and indexed content sharing a value (a noindex page competes with nobody).
 * Metas are read directly: no index table.
 */
final class Duplicates {

	/**
	 * Another published content with the same SEO title.
	 *
	 * @param Analysis $analysis Content.
	 * @return int Post ID, 0 when none.
	 */
	public static function post_with_title( Analysis $analysis ): int {
		global $wpdb;

		if ( '' !== (string) $analysis->values['title'] ) {
			return self::post_with_meta( $analysis->post, '_eo_seo_title', (string) $analysis->values['title'] );
		}

		// Same page title, same post type, no custom title: the template gives the same SEO title.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Run when a content is saved or analyzed, on indexed columns.
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT p.ID FROM {$wpdb->posts} p
				LEFT JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_eo_seo_title'
				LEFT JOIN {$wpdb->postmeta} n ON n.post_id = p.ID AND n.meta_key = '_eo_seo_noindex'
				WHERE p.post_title = %s AND p.post_type = %s AND p.post_status = 'publish' AND p.ID <> %d AND ( m.meta_value IS NULL OR m.meta_value = '' )
				AND ( n.meta_value IS NULL OR n.meta_value <> '1' )
				LIMIT 1",
				$analysis->post->post_title,
				$analysis->post->post_type,
				$analysis->post->ID
			)
		);
	}

	/**
	 * Another published content with the same meta value (case-insensitive, as the collation).
	 *
	 * @param \WP_Post $post  Content.
	 * @param string   $key   Meta key.
	 * @param string   $value Value.
	 * @return int Post ID, 0 when none.
	 */
	public static function post_with_meta( \WP_Post $post, string $key, string $value ): int {
		global $wpdb;

		if ( '' === trim( $value ) ) {
			return 0;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Run when a content is saved or analyzed, on indexed columns.
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT p.ID FROM {$wpdb->postmeta} m
				INNER JOIN {$wpdb->posts} p ON p.ID = m.post_id
				LEFT JOIN {$wpdb->postmeta} n ON n.post_id = p.ID AND n.meta_key = '_eo_seo_noindex'
				WHERE m.meta_key = %s AND m.meta_value = %s AND p.ID <> %d AND p.post_status = 'publish'
				AND ( n.meta_value IS NULL OR n.meta_value <> '1' )
				LIMIT 1",
				$key,
				trim( $value ),
				$post->ID
			)
		);
	}
}
