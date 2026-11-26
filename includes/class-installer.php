<?php
/**
 * Installation: database tables and scheduled events.
 *
 * @package EoSeo
 */

namespace EoSeo;

defined( 'ABSPATH' ) || exit;

/**
 * Creates and upgrades the two tables (redirections, 404 log) on every site.
 */
final class Installer {

	/**
	 * Version of the database schema.
	 */
	public const DB_VERSION = '1';

	/**
	 * Option that stores the installed schema version.
	 */
	public const DB_OPTION = 'eo_seo_db_version';

	/**
	 * Scheduled daily cleanup.
	 */
	public const CRON_HOOK = 'eo_seo_daily';

	/**
	 * Hooks used while the plugin runs.
	 */
	public static function init(): void {
		add_action( 'wp_initialize_site', array( self::class, 'on_new_site' ), 200 );
		add_action( 'admin_init', array( self::class, 'maybe_upgrade' ) );
	}

	/**
	 * Activation, network-wide or on one site.
	 *
	 * @param bool $network_wide Whether the plugin is network activated.
	 */
	public static function activate( bool $network_wide = false ): void {
		if ( is_multisite() && $network_wide ) {
			foreach ( get_sites( array( 'fields' => 'ids', 'number' => 0 ) ) as $site_id ) {
				switch_to_blog( (int) $site_id );
				self::install();
				restore_current_blog();
			}
			return;
		}

		self::install();
	}

	/**
	 * Deactivation: the data is kept, only the scheduled event is removed.
	 */
	public static function deactivate(): void {
		wp_clear_scheduled_hook( self::CRON_HOOK );
	}

	/**
	 * Installs the plugin on a site created after a network activation.
	 *
	 * @param \WP_Site $site New site.
	 */
	public static function on_new_site( \WP_Site $site ): void {
		if ( ! is_plugin_active_for_network( plugin_basename( EO_SEO_FILE ) ) ) {
			return;
		}

		switch_to_blog( (int) $site->blog_id );
		self::install();
		restore_current_blog();
	}

	/**
	 * Upgrades the tables after an update of the plugin.
	 */
	public static function maybe_upgrade(): void {
		if ( self::DB_VERSION !== get_option( self::DB_OPTION ) ) {
			self::install();
		}
	}

	/**
	 * Creates or upgrades the tables of the current site.
	 */
	public static function install(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset     = $wpdb->get_charset_collate();
		$redirection = Redirections::table();
		$not_found   = Not_Found_Log::table();

		dbDelta(
			"CREATE TABLE {$redirection} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				source varchar(2048) NOT NULL,
				source_hash char(32) NOT NULL,
				target varchar(2048) NOT NULL DEFAULT '',
				type smallint(3) unsigned NOT NULL DEFAULT 301,
				origin varchar(20) NOT NULL DEFAULT 'manual',
				hits bigint(20) unsigned NOT NULL DEFAULT 0,
				last_hit datetime DEFAULT NULL,
				created datetime NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY source_hash (source_hash)
			) {$charset};
			CREATE TABLE {$not_found} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				url varchar(2048) NOT NULL,
				url_hash char(32) NOT NULL,
				hits bigint(20) unsigned NOT NULL DEFAULT 1,
				first_seen datetime NOT NULL,
				last_seen datetime NOT NULL,
				referrer varchar(2048) NOT NULL DEFAULT '',
				status varchar(20) NOT NULL DEFAULT 'new',
				PRIMARY KEY  (id),
				UNIQUE KEY url_hash (url_hash),
				KEY status (status)
			) {$charset};"
		);

		update_option( self::DB_OPTION, self::DB_VERSION, false );

		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK );
		}
	}
}
