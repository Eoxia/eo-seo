<?php
/**
 * Log of the 404 errors.
 *
 * @package EoSeo
 */

namespace EoSeo;

defined( 'ABSPATH' ) || exit;

/**
 * One row per address (views, first and last date, last referrer). No IP address is stored.
 */
final class Not_Found_Log {

	public const STATUS_NEW  = 'new';
	public const STATUS_DONE = 'done';

	/**
	 * Transient caching the number of addresses to handle (menu counter).
	 */
	private const COUNT_TRANSIENT = 'eo_seo_404_count';

	/**
	 * Hooks.
	 */
	public static function init(): void {
		add_action( 'template_redirect', array( self::class, 'maybe_log' ), 99 );
	}

	/**
	 * Table name.
	 */
	public static function table(): string {
		global $wpdb;

		return $wpdb->prefix . 'eo_seo_404';
	}

	/**
	 * Logs the current request when it ends in a 404.
	 */
	public static function maybe_log(): void {
		if ( ! is_404() || is_admin() ) {
			return;
		}

		$source = Url::current_source();
		if ( null === $source || self::is_probe( $source ) || self::is_scanner() ) {
			return;
		}

		$referrer = wp_get_raw_referer();
		self::log( $source, $referrer ? esc_url_raw( $referrer ) : '' );
	}

	/**
	 * Adds a view to an address.
	 *
	 * @param string $source   Normalized address.
	 * @param string $referrer Referring page.
	 */
	public static function log( string $source, string $referrer = '' ): void {
		global $wpdb;

		$now = current_time( 'mysql', true );
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table, one row per address.
		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO %i (url, url_hash, hits, first_seen, last_seen, referrer, status) VALUES (%s, %s, 1, %s, %s, %s, %s)
				ON DUPLICATE KEY UPDATE hits = hits + 1, last_seen = VALUES(last_seen), referrer = IF(VALUES(referrer) = '', referrer, VALUES(referrer)), status = %s",
				self::table(),
				substr( $source, 0, 2048 ),
				md5( $source ),
				$now,
				$now,
				substr( $referrer, 0, 2048 ),
				self::STATUS_NEW,
				self::STATUS_NEW
			)
		);
		// phpcs:enable
		delete_transient( self::COUNT_TRANSIENT );
	}

	/**
	 * Whether an address is in the log and still to handle.
	 *
	 * @param string $source Normalized address.
	 */
	public static function is_logged( string $source ): bool {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table, indexed lookup.
		return (bool) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM %i WHERE url_hash = %s AND status = %s', self::table(), md5( $source ), self::STATUS_NEW ) );
	}

	/**
	 * Marks an address as handled (a redirection was created).
	 *
	 * @param string $source Normalized address.
	 */
	public static function mark_done( string $source ): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table.
		$wpdb->query( $wpdb->prepare( 'UPDATE %i SET status = %s WHERE url_hash = %s', self::table(), self::STATUS_DONE, md5( $source ) ) );
		delete_transient( self::COUNT_TRANSIENT );
	}

	/**
	 * Changes the status of rows.
	 *
	 * @param int[]  $ids    IDs.
	 * @param string $status new, done or delete.
	 */
	public static function update( array $ids, string $status ): void {
		global $wpdb;

		$ids = array_filter( array_map( 'absint', $ids ) );
		if ( ! $ids ) {
			return;
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table, admin action.
		foreach ( $ids as $id ) {
			if ( 'delete' === $status ) {
				$wpdb->delete( self::table(), array( 'id' => $id ), array( '%d' ) );
			} elseif ( in_array( $status, array( self::STATUS_NEW, self::STATUS_DONE ), true ) ) {
				$wpdb->update( self::table(), array( 'status' => $status ), array( 'id' => $id ), array( '%s' ), array( '%d' ) );
			}
		}
		// phpcs:enable
		delete_transient( self::COUNT_TRANSIENT );
	}

	/**
	 * Page of the log, most viewed first.
	 *
	 * @param string $status   new or done.
	 * @param int    $page     Page number.
	 * @param int    $per_page Rows per page.
	 * @return array{rows: array<int, object>, total: int}
	 */
	public static function query( string $status = self::STATUS_NEW, int $page = 1, int $per_page = 50 ): array {
		global $wpdb;

		$table = self::table();
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table, admin screen.
		$rows  = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE status = %s ORDER BY hits DESC, last_seen DESC LIMIT %d OFFSET %d', $table, $status, $per_page, max( 0, $page - 1 ) * $per_page ) );
		$total = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE status = %s', $table, $status ) );
		// phpcs:enable

		return array(
			'rows'  => (array) $rows,
			'total' => $total,
		);
	}

	/**
	 * Number of addresses to handle.
	 */
	public static function count_new(): int {
		global $wpdb;

		$count = get_transient( self::COUNT_TRANSIENT );
		if ( false === $count ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table, cached in a transient.
			$count = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE status = %s', self::table(), self::STATUS_NEW ) );
			set_transient( self::COUNT_TRANSIENT, $count, HOUR_IN_SECONDS );
		}

		return (int) $count;
	}

	/**
	 * Suggested destination: a published content whose slug matches the last part of the address.
	 *
	 * @param string $source Normalized address.
	 * @return string Address, empty when nothing matches.
	 */
	public static function suggest_target( string $source ): string {
		global $wpdb;

		$path = trim( (string) wp_parse_url( $source, PHP_URL_PATH ), '/' );
		$slug = sanitize_title( (string) preg_replace( '/\.[a-z0-9]{2,4}$/i', '', basename( $path ) ) );
		if ( strlen( $slug ) < 3 ) {
			return '';
		}

		$types        = array_keys( Settings::post_types() );
		$placeholders = implode( ',', array_fill( 0, count( $types ), '%s' ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- One placeholder per post type; run on the admin screen only.
		$post_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type IN ({$placeholders}) AND ( post_name = %s OR post_name LIKE %s ) ORDER BY post_name = %s DESC, post_date DESC LIMIT 1", ...array_merge( $types, array( $slug, $wpdb->esc_like( $slug ) . '%', $slug ) ) ) );

		return $post_id ? (string) get_permalink( $post_id ) : '';
	}

	/**
	 * Purges the old rows.
	 */
	public static function purge(): void {
		global $wpdb;

		$limit = gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS * (int) Settings::get( 'not_found_retention' ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table.
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE last_seen < %s', self::table(), $limit ) );
		delete_transient( self::COUNT_TRANSIENT );
	}

	/**
	 * Whether an address is a vulnerability probe (wp-login.php, .env, phpMyAdmin…), not a lost page.
	 *
	 * @param string $source Address.
	 */
	public static function is_probe( string $source ): bool {
		$path = strtolower( (string) wp_parse_url( $source, PHP_URL_PATH ) );

		return (bool) preg_match( '#\.(php\d?|asp|aspx|jsp|cgi|env|ini|log|sql|bak|old|swp|zip|tar|gz|rar|7z|ya?ml|config)$|/[._]|/(wp-admin|wp-includes|wp-login|xmlrpc|cgi-bin|phpmyadmin|pma|adminer|server-status|server-info|actuator|vendor|\.git)\b|phpinfo|/info$#', $path );
	}

	/**
	 * Whether the visitor is a scanner or a script rather than a browser or a search engine.
	 */
	private static function is_scanner(): bool {
		$agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';

		return '' === $agent || (bool) preg_match( '/curl|wget|python|go-http|java\/|libwww|httpclient|scrapy|masscan|nikto|sqlmap|zgrab|nuclei/i', $agent );
	}
}
