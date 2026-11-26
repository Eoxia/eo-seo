<?php
/**
 * Scan of the site: refreshes the indicators that depend on other contents.
 *
 * @package EoSeo
 */

namespace EoSeo;

defined( 'ABSPATH' ) || exit;

/**
 * The indicator of a content is computed when it is saved, but some checks depend on the
 * other contents (duplicate titles, descriptions and keyphrases, orphan pages, broken links).
 * The scan recomputes every published content in small batches, through WP-Cron (nightly and
 * a few minutes after a change) or from the dashboard, which runs the batches live.
 */
final class Site_Scan {

	/**
	 * Option holding the state of the scan.
	 */
	public const OPTION = 'eo_seo_scan';

	/**
	 * Scheduled batch.
	 */
	public const BATCH_HOOK = 'eo_seo_scan_batch';

	/**
	 * Contents per batch.
	 */
	public const BATCH_SIZE = 25;

	/**
	 * Delay before the scan that follows a change, in seconds (several saves make one scan).
	 */
	private const AFTER_CHANGE_DELAY = 5 * MINUTE_IN_SECONDS;

	/**
	 * Lock preventing two batches from running at the same time.
	 */
	private const LOCK = 'eo_seo_scan_lock';

	/**
	 * Maximum number of linked contents refreshed when a content is saved.
	 */
	private const MAX_LINKED = 20;

	/**
	 * Hooks.
	 */
	public static function init(): void {
		add_action( self::BATCH_HOOK, array( self::class, 'cron_batch' ) );
		add_action( 'wp_after_insert_post', array( self::class, 'on_save' ), 30, 4 );
		add_action( 'deleted_post', array( self::class, 'on_delete' ) );
		add_action( 'trashed_post', array( self::class, 'on_delete' ) );
	}

	/**
	 * A content was deleted or trashed: its duplicates and the pages it linked to may change.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function on_delete( int $post_id ): void {
		$type = get_post_type( $post_id );
		if ( $type && isset( Settings::post_types()[ $type ] ) ) {
			self::schedule_after_change();
		}
	}

	/**
	 * State of the scan.
	 *
	 * @return array{status: string, last_id: int, done: int, total: int, started: int, finished: int, scheduled: int}
	 *         status: idle or running; finished: time of the last complete scan (0: never);
	 *         scheduled: time of the next scan planned after a change (0: none).
	 */
	public static function state(): array {
		$state = get_option( self::OPTION, array() );
		$state = wp_parse_args(
			is_array( $state ) ? $state : array(),
			array(
				'status'   => 'idle',
				'last_id'  => 0,
				'done'     => 0,
				'total'    => 0,
				'started'  => 0,
				'finished' => 0,
			)
		);

		$next               = wp_next_scheduled( self::BATCH_HOOK );
		$state['scheduled'] = 'idle' === $state['status'] && $next ? (int) $next : 0;

		return $state;
	}

	/**
	 * Starts a complete scan (or keeps the one running). WP-Cron runs it if nobody follows it on the dashboard.
	 *
	 * @return array<string, int|string> State.
	 */
	public static function start(): array {
		// A scan planned a few minutes after a change is brought forward.
		$planned = self::state()['scheduled'];
		self::begin();
		if ( ! wp_next_scheduled( self::BATCH_HOOK ) || $planned ) {
			wp_clear_scheduled_hook( self::BATCH_HOOK );
			wp_schedule_single_event( time() + 30, self::BATCH_HOOK );
		}

		return self::state();
	}

	/**
	 * Marks a complete scan as running, from the first content.
	 *
	 * @return array<string, int|string> State.
	 */
	private static function begin(): array {
		$state = self::state();
		if ( 'running' === $state['status'] ) {
			return $state;
		}

		$state = array_merge(
			$state,
			array(
				'status'  => 'running',
				'last_id' => 0,
				'done'    => 0,
				'total'   => self::count_contents(),
				'started' => time(),
			)
		);
		unset( $state['scheduled'] );
		update_option( self::OPTION, $state, false );

		return $state;
	}

	/**
	 * Batch run by WP-Cron (nightly scan, scan after a change, or one left by the dashboard):
	 * schedules the next one until the scan is over.
	 */
	public static function cron_batch(): void {
		self::begin();
		$state = self::run_batch();
		if ( 'running' === $state['status'] && ! wp_next_scheduled( self::BATCH_HOOK ) ) {
			wp_schedule_single_event( time() + 10, self::BATCH_HOOK );
		}
	}

	/**
	 * Recomputes the next batch of contents.
	 *
	 * @return array<string, int|string> State after the batch.
	 */
	public static function run_batch(): array {
		$state = self::state();
		if ( 'running' !== $state['status'] || Compat::has_conflict() ) {
			return $state;
		}

		// Another batch is running (cron and dashboard at the same time): report the state.
		if ( get_transient( self::LOCK ) ) {
			return $state;
		}
		set_transient( self::LOCK, 1, MINUTE_IN_SECONDS );

		$ids = self::next_ids( (int) $state['last_id'], self::BATCH_SIZE );
		foreach ( $ids as $id ) {
			$post = get_post( $id );
			if ( $post ) {
				Status::refresh( $post );
			}
			$state['last_id'] = $id;
			++$state['done'];
		}

		if ( count( $ids ) < self::BATCH_SIZE ) {
			$state['status']   = 'idle';
			$state['finished'] = time();
			$state['done']     = max( $state['done'], $state['total'] );
			wp_clear_scheduled_hook( self::BATCH_HOOK );
		}

		unset( $state['scheduled'] );
		update_option( self::OPTION, $state, false );
		delete_transient( self::LOCK );

		return self::state();
	}

	/**
	 * When a content is saved: refreshes at once the contents it now shares a value with and the
	 * contents it links to, and schedules a complete scan for those it no longer matches.
	 *
	 * @param int           $post_id     Post ID.
	 * @param \WP_Post      $post        Content.
	 * @param bool          $update      Whether it is an update.
	 * @param \WP_Post|null $post_before Content before the update.
	 */
	public static function on_save( int $post_id, \WP_Post $post, bool $update, ?\WP_Post $post_before ): void {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) || ! isset( Settings::post_types()[ $post->post_type ] ) || Compat::has_conflict() ) {
			return;
		}

		$was_published = $post_before && 'publish' === $post_before->post_status;
		if ( 'publish' !== $post->post_status && ! $was_published ) {
			return; // Drafts do not affect the other contents.
		}

		foreach ( self::related_ids( $post ) as $id ) {
			$related = get_post( $id );
			if ( $related && 'publish' === $related->post_status ) {
				Status::refresh( $related );
			}
		}

		self::schedule_after_change();
	}

	/**
	 * Schedules a complete scan a few minutes after a change (published content saved, deleted, trashed).
	 */
	public static function schedule_after_change(): void {
		if ( 'running' === self::state()['status'] ) {
			return;
		}

		// Several saves in a row make one scan: the first one schedules it.
		if ( ! wp_next_scheduled( self::BATCH_HOOK ) ) {
			wp_schedule_single_event( time() + self::AFTER_CHANGE_DELAY, self::BATCH_HOOK );
		}
	}

	/**
	 * Contents whose checks depend on a content: same SEO title, description or keyphrase, and linked contents.
	 *
	 * @param \WP_Post $post Content.
	 * @return int[] Post IDs.
	 */
	public static function related_ids( \WP_Post $post ): array {
		global $wpdb;

		$ids    = array();
		$values = Fields::get_post_values( $post->ID );

		foreach ( array( '_eo_seo_title' => $values['title'], '_eo_seo_description' => $values['description'], '_eo_seo_focus_keyword' => $values['focus_keyword'] ) as $key => $value ) {
			if ( '' === trim( (string) $value ) ) {
				continue;
			}
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Run when a content is saved, on indexed columns.
			$found = $wpdb->get_col( $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = %s AND post_id <> %d LIMIT 50", $key, trim( (string) $value ), $post->ID ) );
			$ids   = array_merge( $ids, array_map( 'intval', $found ) );
		}

		if ( '' === (string) $values['title'] ) {
			// Same page title without a custom SEO title: same title from the template.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Run when a content is saved.
			$found = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_title = %s AND post_type = %s AND post_status = 'publish' AND ID <> %d LIMIT 50", $post->post_title, $post->post_type, $post->ID ) );
			$ids   = array_merge( $ids, array_map( 'intval', $found ) );
		}

		// Contents it links to: their "orphan page" check may change.
		$analysis = new Analysis( $post );
		$linked   = 0;
		foreach ( $analysis->links as $link ) {
			if ( ! $link['internal'] || $linked >= self::MAX_LINKED ) {
				continue;
			}
			$target = url_to_postid( str_starts_with( $link['href'], '/' ) ? Url::source_url( $link['href'] ) : $link['href'] );
			if ( $target && $target !== $post->ID ) {
				$ids[] = $target;
				++$linked;
			}
		}

		return array_values( array_unique( $ids ) );
	}

	/**
	 * Number of published contents of the handled post types.
	 */
	private static function count_contents(): int {
		$total = 0;
		foreach ( array_keys( Settings::post_types() ) as $post_type ) {
			$counts = wp_count_posts( $post_type );
			$total += isset( $counts->publish ) ? (int) $counts->publish : 0;
		}

		return $total;
	}

	/**
	 * IDs of the next published contents.
	 *
	 * @param int $after Last ID handled.
	 * @param int $limit Number of IDs.
	 * @return int[]
	 */
	private static function next_ids( int $after, int $limit ): array {
		global $wpdb;

		$types = array_keys( Settings::post_types() );
		if ( ! $types ) {
			return array();
		}

		$placeholders = implode( ',', array_fill( 0, count( $types ), '%s' ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- One placeholder per post type; paginated on the primary key.
		return array_map( 'intval', $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type IN ({$placeholders}) AND ID > %d ORDER BY ID ASC LIMIT %d", ...array_merge( $types, array( $after, $limit ) ) ) ) );
	}
}
