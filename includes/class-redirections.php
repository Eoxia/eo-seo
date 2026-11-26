<?php
/**
 * Redirections (301, 302, 410).
 *
 * @package EoSeo
 */

namespace EoSeo;

defined( 'ABSPATH' ) || exit;

/**
 * Stores the redirections, applies them at the start of the request (before the main query,
 * so an existing page can be redirected too) and redirects the old address of a content whose slug changed.
 */
final class Redirections {

	/**
	 * Allowed types.
	 */
	public const TYPES = array( 301, 302, 410 );

	/**
	 * Origins of a redirection.
	 */
	public const ORIGINS = array( 'manual', '404', 'slug', 'ai', 'import' );

	/**
	 * Option counting the redirections: no query at all on sites without any.
	 */
	private const COUNT_OPTION = 'eo_seo_redirections_count';

	/**
	 * Addresses of the contents being updated, before the update.
	 *
	 * @var array<int, array<int, string>>
	 */
	private static array $old_links = array();

	/**
	 * Whether the current request ends in a 410.
	 */
	private static bool $gone = false;

	/**
	 * Hooks.
	 */
	public static function init(): void {
		add_action( 'parse_request', array( self::class, 'maybe_redirect' ), 1 );
		add_action( 'template_redirect', array( self::class, 'maybe_gone' ), 1 );
		add_action( 'pre_post_update', array( self::class, 'remember_links' ), 10, 1 );
		add_action( 'post_updated', array( self::class, 'redirect_changed_links' ), 10, 3 );
	}

	/**
	 * Table name.
	 */
	public static function table(): string {
		global $wpdb;

		return $wpdb->prefix . 'eo_seo_redirections';
	}

	/**
	 * Number of redirections.
	 */
	public static function count(): int {
		return (int) get_option( self::COUNT_OPTION, 0 );
	}

	/**
	 * Redirection of a normalized source.
	 *
	 * @param string $source Normalized source.
	 * @return object{id: string, source: string, target: string, type: string}|null
	 */
	public static function find( string $source ): ?object {
		global $wpdb;

		if ( 0 === self::count() ) {
			return null;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table, indexed lookup by hash.
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT id, source, target, type FROM %i WHERE source_hash = %s', self::table(), md5( $source ) ) );

		return $row ? $row : null;
	}

	/**
	 * Applies the redirection of the requested address.
	 */
	public static function maybe_redirect(): void {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || 0 === self::count() ) {
			return;
		}

		$source = Url::current_source();
		if ( null === $source || str_contains( $source, '/' . rest_get_url_prefix() . '/' ) || str_contains( $source, 'rest_route=' ) ) {
			return;
		}

		$row = self::find( $source );
		if ( ! $row && str_contains( $source, '?' ) ) {
			$row = self::find( strtok( $source, '?' ) );
		}
		if ( ! $row ) {
			return;
		}

		self::hit( (int) $row->id );

		if ( 410 === (int) $row->type ) {
			self::$gone = true;
			return;
		}

		// Redirections to other sites are created by an administrator: wp_redirect() is intended here.
		wp_redirect( Url::target_url( $row->target ), (int) $row->type, 'EO SEO' ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
		exit;
	}

	/**
	 * Answers "410 Gone" with the 404 template.
	 */
	public static function maybe_gone(): void {
		global $wp_query;

		if ( self::$gone ) {
			$wp_query->set_404();
			status_header( 410 );
			nocache_headers();
		}
	}

	/**
	 * Counts a hit.
	 *
	 * @param int $id Redirection ID.
	 */
	private static function hit( int $id ): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table.
		$wpdb->query( $wpdb->prepare( 'UPDATE %i SET hits = hits + 1, last_hit = %s WHERE id = %d', self::table(), current_time( 'mysql', true ), $id ) );
	}

	/**
	 * Checks a redirection before it is saved.
	 *
	 * @param string $from Source typed or proposed.
	 * @param string $to   Target typed or proposed (ignored for a 410).
	 * @param int    $type 301, 302 or 410.
	 * @return array{source: string, target: string, type: int, replaces: ?string, warnings: string[]}|\WP_Error
	 */
	public static function validate( string $from, string $to, int $type = 301 ): array|\WP_Error {
		if ( ! in_array( $type, self::TYPES, true ) ) {
			return new \WP_Error( 'eo_seo_redirection_type', __( 'The type must be 301, 302 or 410.', 'eo-seo' ) );
		}

		$source = Url::normalize_source( $from );
		if ( is_wp_error( $source ) ) {
			return $source;
		}

		$source_url = Url::source_url( $source );
		if ( untrailingslashit( strtok( $source_url, '?' ) ) === untrailingslashit( home_url() ) ) {
			return new \WP_Error( 'eo_seo_redirection_home', __( 'The home page cannot be redirected.', 'eo-seo' ) );
		}

		$warnings = array();
		$target   = '';
		$removes  = 0;

		if ( 410 !== $type ) {
			$target = Url::normalize_target( $to );
			if ( is_wp_error( $target ) ) {
				return $target;
			}

			if ( untrailingslashit( $source_url ) === untrailingslashit( Url::target_url( $target ) ) ) {
				return new \WP_Error( 'eo_seo_redirection_loop', __( 'The destination is the redirected address itself.', 'eo-seo' ) );
			}

			if ( str_starts_with( $target, 'http' ) ) {
				$warnings[] = __( 'Redirection to another site.', 'eo-seo' );
			} else {
				$target_source = Url::normalize_source( $target );
				$chained       = is_wp_error( $target_source ) ? null : self::find( $target_source );
				$as_target     = Url::normalize_target( $source_url );
				if ( $chained && ! is_wp_error( $as_target ) && untrailingslashit( $chained->target ) === untrailingslashit( $as_target ) ) {
					// B → A exists and A now goes to B (a slug changed back): B answers again, its redirection goes.
					$removes = (int) $chained->id;
				} elseif ( $chained && 410 !== (int) $chained->type ) {
					/* translators: 1: address, 2: address. */
					return new \WP_Error( 'eo_seo_redirection_chain', sprintf( __( 'The destination "%1$s" is itself redirected to "%2$s": redirect straight to the final address.', 'eo-seo' ), $target, $chained->target ) );
				}
			}
		}

		$current = self::find( $source );

		return array(
			'source'   => $source,
			'target'   => $target,
			'type'     => $type,
			'replaces' => $current ? $current->target : null,
			'removes'  => $removes,
			'warnings' => $warnings,
		);
	}

	/**
	 * Saves a checked redirection. Older redirections to this source now go straight to the new target (no chain).
	 *
	 * @param array{source: string, target: string, type: int} $redirection Result of validate().
	 * @param string                                           $origin      manual, 404, slug, ai or import.
	 * @return true|\WP_Error
	 */
	public static function save( array $redirection, string $origin = 'manual' ): bool|\WP_Error {
		global $wpdb;

		$origin = in_array( $origin, self::ORIGINS, true ) ? $origin : 'manual';

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table.
		$done = $wpdb->query(
			$wpdb->prepare(
				'INSERT INTO %i (source, source_hash, target, type, origin, created) VALUES (%s, %s, %s, %d, %s, %s)
				ON DUPLICATE KEY UPDATE target = VALUES(target), type = VALUES(type), origin = VALUES(origin)',
				self::table(),
				$redirection['source'],
				md5( $redirection['source'] ),
				$redirection['target'],
				$redirection['type'],
				$origin,
				current_time( 'mysql', true )
			)
		);
		// phpcs:enable
		if ( false === $done ) {
			return new \WP_Error( 'eo_seo_redirection_save', __( 'The redirection could not be saved.', 'eo-seo' ) );
		}

		if ( 410 !== $redirection['type'] ) {
			$as_target = Url::normalize_target( Url::source_url( $redirection['source'] ) );
			if ( ! is_wp_error( $as_target ) && $as_target !== $redirection['target'] ) {
				// Targets keep the trailing slash they were typed with: compare without it.
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table.
				$wpdb->query( $wpdb->prepare( "UPDATE %i SET target = %s WHERE TRIM(TRAILING '/' FROM target) = %s AND source_hash <> %s", self::table(), $redirection['target'], untrailingslashit( $as_target ), md5( $redirection['source'] ) ) );
			}
		}

		if ( ! empty( $redirection['removes'] ) ) {
			self::delete( array( (int) $redirection['removes'] ) );
		}

		Not_Found_Log::mark_done( $redirection['source'] );
		self::refresh_count();

		return true;
	}

	/**
	 * Deletes redirections.
	 *
	 * @param int[] $ids IDs.
	 */
	public static function delete( array $ids ): void {
		global $wpdb;

		$ids = array_filter( array_map( 'absint', $ids ) );
		if ( ! $ids ) {
			return;
		}

		foreach ( $ids as $id ) {
			$wpdb->delete( self::table(), array( 'id' => $id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table.
		}
		self::refresh_count();
	}

	/**
	 * Page of redirections.
	 *
	 * @param string $search   Text searched in the source and the target.
	 * @param int    $page     Page number.
	 * @param int    $per_page Rows per page (0: all).
	 * @return array{rows: array<int, object>, total: int}
	 */
	public static function query( string $search = '', int $page = 1, int $per_page = 50 ): array {
		global $wpdb;

		$table  = self::table();
		$like   = '%' . $wpdb->esc_like( $search ) . '%';
		$limit  = $per_page > 0 ? $per_page : PHP_INT_MAX;
		$offset = $per_page > 0 ? max( 0, $page - 1 ) * $per_page : 0;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table, admin screen.
		if ( '' !== $search ) {
			$rows  = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE source LIKE %s OR target LIKE %s ORDER BY id DESC LIMIT %d OFFSET %d', $table, $like, $like, $limit, $offset ) );
			$total = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE source LIKE %s OR target LIKE %s', $table, $like, $like ) );
		} else {
			$rows  = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i ORDER BY id DESC LIMIT %d OFFSET %d', $table, $limit, $offset ) );
			$total = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $table ) );
		}
		// phpcs:enable

		return array(
			'rows'  => (array) $rows,
			'total' => $total,
		);
	}

	/**
	 * Imports redirections from a CSV text: source, target, type (optional, 301 by default).
	 *
	 * @param string $csv CSV content.
	 * @return array{saved: int, errors: string[]}
	 */
	public static function import_csv( string $csv ): array {
		$saved  = 0;
		$errors = array();
		$lines  = preg_split( '/\R/', $csv );

		foreach ( (array) $lines as $number => $line ) {
			if ( '' === trim( (string) $line ) ) {
				continue;
			}
			$delimiter = substr_count( (string) $line, ';' ) > substr_count( (string) $line, ',' ) ? ';' : ',';
			$columns   = array_map( 'trim', str_getcsv( (string) $line, $delimiter ) );
			if ( 0 === $number && ! str_starts_with( $columns[0], '/' ) && ! str_starts_with( $columns[0], 'http' ) ) {
				continue; // Header row.
			}

			$type   = isset( $columns[2] ) && '' !== $columns[2] ? (int) $columns[2] : 301;
			$result = self::validate( $columns[0], $columns[1] ?? '', $type );
			if ( ! is_wp_error( $result ) ) {
				$result = self::save( $result, 'import' );
			}
			if ( is_wp_error( $result ) ) {
				/* translators: 1: line number, 2: error message. */
				$errors[] = sprintf( __( 'Line %1$d: %2$s', 'eo-seo' ), $number + 1, $result->get_error_message() );
			} else {
				++$saved;
			}
		}

		return array(
			'saved'  => $saved,
			'errors' => $errors,
		);
	}

	/**
	 * All the redirections, as CSV.
	 */
	public static function export_csv(): string {
		$lines = array( 'source,target,type' );
		foreach ( self::query( '', 1, 0 )['rows'] as $row ) {
			$lines[] = implode(
				',',
				array_map(
					static fn( $value ) => '"' . str_replace( '"', '""', (string) $value ) . '"',
					array( Url::source_url( $row->source ), 410 === (int) $row->type ? '' : Url::target_url( $row->target ), $row->type )
				)
			);
		}

		return implode( "\n", $lines ) . "\n";
	}

	/**
	 * Remembers the addresses of a published content and of its sub-pages before an update.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function remember_links( int $post_id ): void {
		$post = get_post( $post_id );
		if ( ! $post || 'publish' !== $post->post_status || ! isset( Settings::post_types()[ $post->post_type ] ) ) {
			return;
		}

		$links = array( $post->ID => (string) get_permalink( $post ) );
		if ( is_post_type_hierarchical( $post->post_type ) ) {
			$children = get_pages(
				array(
					'child_of'    => $post->ID,
					'post_type'   => $post->post_type,
					'post_status' => 'publish',
				)
			);
			foreach ( (array) $children as $child ) {
				$links[ $child->ID ] = (string) get_permalink( $child );
			}
		}

		self::$old_links[ $post_id ] = $links;
	}

	/**
	 * Redirects the old addresses when the address of a published content changed
	 * (WordPress only does it for posts, and never for sub-pages).
	 *
	 * @param int      $post_id     Post ID.
	 * @param \WP_Post $post_after  Content after the update.
	 * @param \WP_Post $post_before Content before the update.
	 */
	public static function redirect_changed_links( int $post_id, \WP_Post $post_after, \WP_Post $post_before ): void {
		if ( empty( self::$old_links[ $post_id ] ) || 'publish' !== $post_after->post_status || 'publish' !== $post_before->post_status ) {
			return;
		}

		$old_links = self::$old_links[ $post_id ];
		unset( self::$old_links[ $post_id ] );

		foreach ( $old_links as $id => $old_link ) {
			clean_post_cache( $id );
			$new_link = (string) get_permalink( $id );
			if ( '' === $new_link || untrailingslashit( $new_link ) === untrailingslashit( $old_link ) ) {
				continue;
			}

			$redirection = self::validate( $old_link, $new_link );
			if ( ! is_wp_error( $redirection ) ) {
				self::save( $redirection, 'slug' );
			}
		}
	}

	/**
	 * Updates the counter of redirections.
	 */
	private static function refresh_count(): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table.
		update_option( self::COUNT_OPTION, (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', self::table() ) ), true );
	}
}
