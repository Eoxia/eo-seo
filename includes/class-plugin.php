<?php
/**
 * Plugin bootstrap.
 *
 * @package EoSeo
 */

namespace EoSeo;

defined( 'ABSPATH' ) || exit;

/**
 * Wires every module.
 */
final class Plugin {

	/**
	 * Boots the plugin (plugins_loaded).
	 */
	public static function boot(): void {
		Installer::init();
		Settings::init();
		Fields::init();
		Status::init();
		Redirections::init();
		Not_Found_Log::init();
		Proposals::init();
		Abilities::init();
		Rest::init();
		Breadcrumbs::init();
		add_action( Installer::CRON_HOOK, array( self::class, 'daily' ) );

		// Front output is skipped when another SEO plugin handles it.
		if ( ! Compat::has_conflict() ) {
			Frontend::init();
			Sitemaps::init();
			Schema::init();
		}

		if ( is_admin() ) {
			Admin\Admin::init();
		}
	}

	/**
	 * Daily maintenance.
	 */
	public static function daily(): void {
		Not_Found_Log::purge();
		Proposals::purge_expired();
	}
}
