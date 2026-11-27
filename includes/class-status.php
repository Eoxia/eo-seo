<?php
/**
 * SEO indicator of the contents and terms, stored when they are saved.
 *
 * @package EoSeo
 */

namespace EoSeo;

defined( 'ABSPATH' ) || exit;

/**
 * Computes and stores the indicator (level, points, points to fix) of the contents (post metas)
 * and of the terms (term metas, same keys).
 */
final class Status {

	/**
	 * Hooks.
	 */
	public static function init(): void {
		// Fires after the metas sent by the block editor are saved (unlike save_post).
		add_action( 'wp_after_insert_post', array( self::class, 'on_save' ), 20, 2 );
		// Fires after edited_{$taxonomy}, where the term form saves its SEO fields.
		add_action( 'saved_term', array( self::class, 'on_save_term' ), 20, 3 );
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
	 * Refreshes the indicator of a saved term.
	 *
	 * @param int    $term_id  Term ID.
	 * @param int    $tt_id    Term taxonomy ID.
	 * @param string $taxonomy Taxonomy.
	 */
	public static function on_save_term( int $term_id, int $tt_id, string $taxonomy ): void {
		if ( ! isset( Settings::taxonomies()[ $taxonomy ] ) || Compat::has_conflict() ) {
			return;
		}

		$term = get_term( $term_id, $taxonomy );
		if ( ! $term instanceof \WP_Term ) {
			return;
		}

		self::refresh_term( $term );

		// Terms that may share its SEO title (same name, same custom title): their "unique title" check changes.
		foreach ( Term_Checks::candidates( $term ) as $other ) {
			self::refresh_term( $other );
		}
	}

	/**
	 * Computes and stores the indicator of a content.
	 *
	 * @param \WP_Post $post Content.
	 * @return array{level: string, passed: int, total: int, checks: array<int, array<string, mixed>>}
	 */
	public static function refresh( \WP_Post $post ): array {
		$result = Checks::run( new Analysis( $post ) );
		self::store( 'post', $post->ID, $result );

		return $result;
	}

	/**
	 * Computes and stores the indicator of a term.
	 *
	 * @param \WP_Term $term Term.
	 * @return array{level: string, passed: int, total: int, checks: array<int, array<string, mixed>>}
	 */
	public static function refresh_term( \WP_Term $term ): array {
		$result = Term_Checks::run( new Term_Analysis( $term ) );
		self::store( 'term', $term->term_id, $result );

		return $result;
	}

	/**
	 * Stores an indicator.
	 *
	 * @param string               $object_type post or term.
	 * @param int                  $object_id   ID.
	 * @param array<string, mixed> $result      Result of the checks.
	 */
	private static function store( string $object_type, int $object_id, array $result ): void {
		// Failed checks, worst first, shown when hovering the badge in the lists.
		$issues = array();
		foreach ( $result['checks'] as $check ) {
			// Advice (not counted) is left out: the tooltip explains the level and the points.
			if ( ! $check['passed'] && ( $check['scored'] ?? true ) ) {
				$issues[] = array(
					'id'       => $check['id'],
					'severity' => $check['severity'],
					'message'  => $check['message'],
				);
			}
		}

		update_metadata( $object_type, $object_id, Fields::STATUS_META, $result['level'] );
		update_metadata( $object_type, $object_id, Fields::POINTS_META, $result['passed'] . '/' . $result['total'] );
		update_metadata( $object_type, $object_id, Fields::ISSUES_META, $issues );
	}

	/**
	 * Stored indicator of a content.
	 *
	 * @param int $post_id Post ID.
	 * @return array{level: string, points: string, issues: array<int, array{id: string, severity: string, message: string}>}
	 *         Level is empty when never computed.
	 */
	public static function get( int $post_id ): array {
		return self::read( 'post', $post_id );
	}

	/**
	 * Stored indicator of a term.
	 *
	 * @param int $term_id Term ID.
	 * @return array{level: string, points: string, issues: array<int, array{id: string, severity: string, message: string}>}
	 */
	public static function get_term( int $term_id ): array {
		return self::read( 'term', $term_id );
	}

	/**
	 * Reads a stored indicator.
	 *
	 * @param string $object_type post or term.
	 * @param int    $object_id   ID.
	 * @return array{level: string, points: string, issues: array<int, array{id: string, severity: string, message: string}>}
	 */
	private static function read( string $object_type, int $object_id ): array {
		$issues = get_metadata( $object_type, $object_id, Fields::ISSUES_META, true );

		return array(
			'level'  => (string) get_metadata( $object_type, $object_id, Fields::STATUS_META, true ),
			'points' => (string) get_metadata( $object_type, $object_id, Fields::POINTS_META, true ),
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

		return self::levels_from_rows( (array) $rows );
	}

	/**
	 * Number of terms per level, in the handled taxonomies.
	 *
	 * @return array<string, int> Keyed by level, plus "unknown" (never computed).
	 */
	public static function count_terms_by_level(): array {
		global $wpdb;

		$taxonomies = array_keys( Settings::taxonomies() );
		if ( ! $taxonomies ) {
			return self::levels_from_rows( array() );
		}

		$placeholders = implode( ',', array_fill( 0, count( $taxonomies ), '%s' ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- One placeholder per taxonomy; dashboard only.
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT COALESCE(m.meta_value, 'unknown') AS level, COUNT(*) AS total FROM {$wpdb->term_taxonomy} tt LEFT JOIN {$wpdb->termmeta} m ON m.term_id = tt.term_id AND m.meta_key = %s WHERE tt.taxonomy IN ({$placeholders}) GROUP BY level", Fields::STATUS_META, ...$taxonomies ) );

		return self::levels_from_rows( (array) $rows );
	}

	/**
	 * Counts per level from GROUP BY rows.
	 *
	 * @param array<int, object> $rows Rows with level and total.
	 * @return array<string, int>
	 */
	private static function levels_from_rows( array $rows ): array {
		$counts = array_fill_keys( array( Checks::LEVEL_PROBLEM, Checks::LEVEL_IMPROVE, Checks::LEVEL_GOOD, Checks::LEVEL_NOINDEX, 'unknown' ), 0 );
		foreach ( $rows as $row ) {
			$counts[ $row->level ] = (int) $row->total;
		}

		return $counts;
	}
}
