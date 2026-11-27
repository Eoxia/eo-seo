<?php
/**
 * Administration: menu, assets, notices.
 *
 * @package EoSeo
 */

namespace EoSeo\Admin;

use EoSeo\Compat;
use EoSeo\Not_Found_Log;
use EoSeo\Proposals;
use EoSeo\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Wires the admin screens.
 */
final class Admin {

	/**
	 * Hook suffixes of the plugin screens.
	 *
	 * @var string[]
	 */
	private static array $screens = array();

	/**
	 * Hooks.
	 */
	public static function init(): void {
		add_action( 'admin_menu', array( self::class, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( self::class, 'assets' ) );
		add_action( 'admin_notices', array( self::class, 'notices' ) );

		Actions::init();
		Term_Fields::init();
		if ( ! Compat::has_conflict() ) {
			List_Column::init();
			Editor::init();
			Metabox::init();
		}
	}

	/**
	 * Version of an asset: the plugin version plus the time of the file, so that browsers
	 * never keep an old script or style after an update.
	 *
	 * @param string $path Path relative to the plugin folder.
	 */
	public static function asset_version( string $path ): string {
		$time = @filemtime( EO_SEO_DIR . $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- A missing file only drops the suffix.

		return EO_SEO_VERSION . ( $time ? '.' . $time : '' );
	}

	/**
	 * Address of a plugin screen.
	 *
	 * @param string               $page Page slug.
	 * @param array<string, mixed> $args Query arguments.
	 */
	public static function page_url( string $page, array $args = array() ): string {
		return add_query_arg( array_merge( array( 'page' => $page ), $args ), admin_url( 'admin.php' ) );
	}

	/**
	 * Menu "EO SEO".
	 */
	public static function menu(): void {
		$not_found = Not_Found_Log::count_new();
		$bubble    = $not_found ? ' <span class="awaiting-mod">' . number_format_i18n( $not_found ) . '</span>' : '';

		self::$screens[] = add_menu_page( __( 'EO SEO', 'eo-seo' ), __( 'EO SEO', 'eo-seo' ), 'manage_options', 'eo-seo', array( Dashboard_Page::class, 'render' ), 'dashicons-search', 81 );
		self::$screens[] = add_submenu_page( 'eo-seo', __( 'SEO dashboard', 'eo-seo' ), __( 'Dashboard', 'eo-seo' ), 'manage_options', 'eo-seo', array( Dashboard_Page::class, 'render' ) );
		self::$screens[] = add_submenu_page( 'eo-seo', __( 'SEO settings', 'eo-seo' ), __( 'Settings', 'eo-seo' ), 'manage_options', 'eo-seo-settings', array( Settings_Page::class, 'render' ) );
		self::$screens[] = add_submenu_page( 'eo-seo', __( 'Redirections', 'eo-seo' ), __( 'Redirections', 'eo-seo' ), 'manage_options', 'eo-seo-redirections', array( Redirections_Page::class, 'render' ) );
		self::$screens[] = add_submenu_page( 'eo-seo', __( '404 errors', 'eo-seo' ), __( '404 errors', 'eo-seo' ) . $bubble, 'manage_options', 'eo-seo-404', array( Not_Found_Page::class, 'render' ) );

		// Shown only when there is something to validate.
		$pending = Proposals::count_pending();
		$slug    = $pending ? 'eo-seo' : ''; // Empty parent: the page exists but is not in the menu.
		$label   = __( 'Proposals', 'eo-seo' ) . ( $pending ? ' <span class="awaiting-mod">' . number_format_i18n( $pending ) . '</span>' : '' );

		self::$screens[] = add_submenu_page( $slug, __( 'SEO proposals', 'eo-seo' ), $label, 'edit_posts', 'eo-seo-proposals', array( Proposals_Page::class, 'render' ) );
	}

	/**
	 * Styles of the plugin screens, of the content lists and of the term screens.
	 *
	 * @param string $hook Hook suffix of the screen.
	 */
	public static function assets( string $hook ): void {
		$screen  = get_current_screen();
		$ours    = in_array( $hook, self::$screens, true );
		$listing = $screen && in_array( $screen->base, array( 'edit', 'term', 'edit-tags' ), true );
		// Classic editor: the SEO box is a metabox (the block editor has its own sidebar script).
		$classic = $screen && 'post' === $screen->base && ! $screen->is_block_editor() && isset( Settings::post_types()[ $screen->post_type ] );

		if ( ! $ours && ! $listing && ! $classic ) {
			return;
		}

		wp_enqueue_style( 'eo-seo-admin', EO_SEO_URL . 'assets/css/admin.css', array(), self::asset_version( 'assets/css/admin.css' ) );

		if ( $ours || $classic || ( $screen && 'term' === $screen->base ) ) {
			wp_enqueue_media();
			wp_enqueue_script( 'eo-seo-admin', EO_SEO_URL . 'assets/js/admin.js', array( 'wp-api-fetch', 'wp-i18n' ), self::asset_version( 'assets/js/admin.js' ), true );
			wp_set_script_translations( 'eo-seo-admin', 'eo-seo', EO_SEO_DIR . 'languages' );
		}
	}

	/**
	 * Result of the last action, and the cohabitation notice on the plugin screens.
	 */
	public static function notices(): void {
		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->id, self::$screens, true ) ) {
			return;
		}

		$notice = Actions::pull_notice();
		if ( $notice ) {
			wp_admin_notice(
				wp_kses_post( $notice['message'] ),
				array(
					'type'        => $notice['type'],
					'dismissible' => true,
				)
			);
		}

		if ( Compat::has_conflict() ) {
			wp_admin_notice(
				esc_html(
					sprintf(
						/* translators: %s: plugin names. */
						__( '%s is active: EO SEO adds nothing on the front (tags, sitemap, robots.txt, structured data) and hides its editor panel. Redirections and the 404 log keep working.', 'eo-seo' ),
						implode( ', ', Compat::active_plugins() )
					)
				),
				array( 'type' => 'info' )
			);
		}
	}
}
