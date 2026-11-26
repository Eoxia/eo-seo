<?php
/**
 * Local checks of internal links (no HTTP request).
 *
 * @package EoSeo
 */

namespace EoSeo;

defined( 'ABSPATH' ) || exit;

/**
 * Detects links to redirected or missing pages, and contents nobody links to.
 */
final class Link_Checker {

	/**
	 * Maximum number of links checked per content.
	 */
	private const MAX_LINKS = 30;

	/**
	 * Internal links pointing to a redirected address or to an address logged as 404.
	 *
	 * @param array<int, array{href: string, internal: bool}> $links Links of the content.
	 * @return string[] Faulty addresses.
	 */
	public static function bad_internal_links( array $links ): array {
		$bad     = array();
		$checked = 0;

		foreach ( $links as $link ) {
			if ( ! $link['internal'] || $checked >= self::MAX_LINKS ) {
				continue;
			}
			++$checked;

			$source = Url::normalize_source( $link['href'] );
			if ( is_wp_error( $source ) ) {
				continue;
			}
			if ( Redirections::find( $source ) || Not_Found_Log::is_logged( $source ) ) {
				$bad[] = $link['href'];
			}
		}

		return array_values( array_unique( $bad ) );
	}

	/**
	 * Whether a menu or another published content links to a content.
	 *
	 * @param \WP_Post $post Content.
	 */
	public static function has_incoming_link( \WP_Post $post ): bool {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Run when a content is saved or analyzed.
		$in_menu = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT m.post_id FROM {$wpdb->postmeta} m
				INNER JOIN {$wpdb->postmeta} t ON t.post_id = m.post_id AND t.meta_key = '_menu_item_type' AND t.meta_value = 'post_type'
				WHERE m.meta_key = '_menu_item_object_id' AND m.meta_value = %s LIMIT 1",
				(string) $post->ID
			)
		);
		if ( $in_menu ) {
			return true;
		}

		$path = untrailingslashit( (string) wp_parse_url( (string) get_permalink( $post ), PHP_URL_PATH ) );
		if ( '' === $path ) {
			return true;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Run when a content is saved or analyzed.
		return (bool) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts}
				WHERE post_status = 'publish' AND ID <> %d AND post_type NOT IN ( 'revision', 'nav_menu_item', 'eo_seo_proposal' )
				AND ( post_content LIKE %s OR post_content LIKE %s )
				LIMIT 1",
				$post->ID,
				'%' . $wpdb->esc_like( 'href="' . $path ) . '%',
				'%' . $wpdb->esc_like( $path . '/"' ) . '%'
			)
		);
	}
}
