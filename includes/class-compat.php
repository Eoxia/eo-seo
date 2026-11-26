<?php
/**
 * Cohabitation with other SEO plugins.
 *
 * @package EoSeo
 */

namespace EoSeo;

defined( 'ABSPATH' ) || exit;

/**
 * Detects the other SEO plugins. When one is active, EO SEO adds nothing on the front
 * (tags, sitemap, robots.txt, structured data) and hides its editor panel; redirections
 * and the 404 log keep working.
 */
final class Compat {

	/**
	 * Names of the active SEO plugins, computed once.
	 *
	 * @var string[]|null
	 */
	private static ?array $active = null;

	/**
	 * Known SEO plugins: name => constant defined by the plugin.
	 *
	 * @return array<string, string>
	 */
	public static function known_plugins(): array {
		$plugins = array(
			'Yoast SEO'            => 'WPSEO_VERSION',
			'SEOKEY'               => 'SEOKEY_VERSION',
			'Rank Math'            => 'RANK_MATH_VERSION',
			'SEOPress'             => 'SEOPRESS_VERSION',
			'All in One SEO'       => 'AIOSEO_VERSION',
			'The SEO Framework'    => 'THE_SEO_FRAMEWORK_VERSION',
		);

		/**
		 * Filters the SEO plugins EO SEO steps aside for.
		 *
		 * @param array<string, string> $plugins Plugin name => constant it defines.
		 */
		return (array) apply_filters( 'eo_seo_conflicting_plugins', $plugins );
	}

	/**
	 * Active SEO plugins.
	 *
	 * @return string[] Names.
	 */
	public static function active_plugins(): array {
		if ( null === self::$active ) {
			self::$active = array();
			foreach ( self::known_plugins() as $name => $constant ) {
				if ( defined( $constant ) ) {
					self::$active[] = (string) $name;
				}
			}
		}

		return self::$active;
	}

	/**
	 * Whether another SEO plugin is active.
	 */
	public static function has_conflict(): bool {
		return array() !== self::active_plugins();
	}
}
