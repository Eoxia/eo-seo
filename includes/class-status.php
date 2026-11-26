<?php
/**
 * Per-page SEO indicator, stored when a content is saved.
 *
 * @package EoSeo
 */

namespace EoSeo;

defined( 'ABSPATH' ) || exit;

/**
 * Computes and stores the indicator (level + points) of the contents.
 */
final class Status {

	/**
	 * Hooks.
	 */
	public static function init(): void {
		// Fires after the metas sent by the block editor are saved (unlike save_post).
		add_action( 'wp_after_insert_post', array( self::class, 'on_save' ), 20, 2 );
	}

	/**
	 * Refreshes the indicator of a saved content.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Content.
	 */
	public static function on_save( int $post_id, \WP_Post $post ): void {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) || 'auto-draft' === $post->post_status || 'trash' === $post->post_status ) {
			return;
		}
		if ( ! isset( Settings::post_types()[ $post->post_type ] ) || Compat::has_conflict() ) {
			return;
		}

		self::refresh( $post );
	}

	/**
	 * Computes and stores the indicator of a content.
	 *
	 * @param \WP_Post $post Content.
	 * @return array{level: string, passed: int, total: int, checks: array<int, array<string, mixed>>}
	 */
	public static function refresh( \WP_Post $post ): array {
		$result = Checks::run( new Analysis( $post ) );

		// Failed checks, worst first, shown when hovering the badge in the content lists.
		$issues = array();
		foreach ( $result['checks'] as $check ) {
			if ( ! $check['passed'] ) {
				$issues[] = array(
					'id'       => $check['id'],
					'severity' => $check['severity'],
					'message'  => $check['message'],
				);
			}
		}

		update_post_meta( $post->ID, Fields::STATUS_META, $result['level'] );
		update_post_meta( $post->ID, Fields::POINTS_META, $result['passed'] . '/' . $result['total'] );
		update_post_meta( $post->ID, Fields::ISSUES_META, $issues );

		return $result;
	}

	/**
	 * Stored indicator of a content.
	 *
	 * @param int $post_id Post ID.
	 * @return array{level: string, points: string, issues: array<int, array{id: string, severity: string, message: string}>}
	 *         Level is empty when never computed.
	 */
	public static function get( int $post_id ): array {
		$issues = get_post_meta( $post_id, Fields::ISSUES_META, true );

		return array(
			'level'  => (string) get_post_meta( $post_id, Fields::STATUS_META, true ),
			'points' => (string) get_post_meta( $post_id, Fields::POINTS_META, true ),
			'issues' => is_array( $issues ) ? $issues : array(),
		);
	}

	/**
	 * Number of published contents per level.
	 *
	 * @return array<string, int> Keyed by level, plus "unknown" (never computed).
	 */
	public static function count_by_level(): array {
		global $wpdb;

		$types = array_keys( Settings::post_types() );
		if ( ! $types ) {
			return array();
		}

		$placeholders = implode( ',', array_fill( 0, count( $types ), '%s' ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- One placeholder per post type; dashboard only.
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT COALESCE(m.meta_value, 'unknown') AS level, COUNT(*) AS total FROM {$wpdb->posts} p LEFT JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = %s WHERE p.post_status = 'publish' AND p.post_type IN ({$placeholders}) GROUP BY level", Fields::STATUS_META, ...$types ) );

		$counts = array_fill_keys( array( Checks::LEVEL_PROBLEM, Checks::LEVEL_IMPROVE, Checks::LEVEL_GOOD, Checks::LEVEL_NOINDEX, 'unknown' ), 0 );
		foreach ( (array) $rows as $row ) {
			$counts[ $row->level ] = (int) $row->total;
		}

		return $counts;
	}
}
