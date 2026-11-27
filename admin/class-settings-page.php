<?php
/**
 * Screen "Settings".
 *
 * @package EoSeo
 */

namespace EoSeo\Admin;

use EoSeo\Settings;
use EoSeo\Variables;

defined( 'ABSPATH' ) || exit;

/**
 * Settings, one tab per form (options.php saves the tab given in _tab).
 */
final class Settings_Page {

	/**
	 * Tabs: label and short description.
	 *
	 * @return array<string, array{label: string, description: string}>
	 */
	public static function tabs(): array {
		return array(
			'appearance' => array(
				'label'       => __( 'Search appearance', 'eo-seo' ),
				'description' => __( 'Title and description templates', 'eo-seo' ),
			),
			'indexing'   => array(
				'label'       => __( 'Indexing', 'eo-seo' ),
				'description' => __( 'What search engines see, sitemap, robots.txt', 'eo-seo' ),
			),
			'identity'   => array(
				'label'       => __( 'Identity', 'eo-seo' ),
				'description' => __( 'Organization, logo, address', 'eo-seo' ),
			),
			'social'     => array(
				'label'       => __( 'Social networks', 'eo-seo' ),
				'description' => __( 'Accounts of the site, sharing image', 'eo-seo' ),
			),
			'webmaster'  => array(
				'label'       => __( 'Webmaster tools', 'eo-seo' ),
				'description' => __( 'Google, Bing, Pinterest, Facebook', 'eo-seo' ),
			),
			'tools'      => array(
				'label'       => __( 'Tools', 'eo-seo' ),
				'description' => __( '404 log, uninstallation', 'eo-seo' ),
			),
		);
	}

	/**
	 * Renders the screen.
	 */
	public static function render(): void {
		$tabs = self::tabs();
		$tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'appearance'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Tab navigation.
		$tab  = isset( $tabs[ $tab ] ) ? $tab : 'appearance';

		$settings   = Settings::all();
		$option     = Settings::OPTION;
		$post_types = Settings::post_types();
		$taxonomies = Settings::taxonomies();
		$variables  = Variables::available();
		$separators = Settings::SEPARATORS;
		$contexts   = self::template_contexts( $post_types, $taxonomies );

		include EO_SEO_DIR . 'admin/views/settings.php';
	}

	/**
	 * Labels of the template contexts.
	 *
	 * @param \WP_Post_Type[] $post_types Post types.
	 * @param \WP_Taxonomy[]  $taxonomies Taxonomies.
	 * @return array<string, string>
	 */
	private static function template_contexts( array $post_types, array $taxonomies ): array {
		$contexts = array( 'home' => __( 'Home page', 'eo-seo' ) );

		foreach ( $post_types as $name => $type ) {
			$contexts[ 'post_type:' . $name ] = $type->labels->name;
			if ( $type->has_archive ) {
				/* translators: %s: post type name. */
				$contexts[ 'archive:' . $name ] = sprintf( __( 'Archive of %s', 'eo-seo' ), $type->labels->name );
			}
		}
		foreach ( $taxonomies as $name => $taxonomy ) {
			$contexts[ 'taxonomy:' . $name ] = $taxonomy->labels->name;
		}

		$contexts['author'] = __( 'Author archives', 'eo-seo' );
		$contexts['date']   = __( 'Date archives', 'eo-seo' );
		$contexts['search'] = __( 'Search results', 'eo-seo' );

		return $contexts;
	}
}
