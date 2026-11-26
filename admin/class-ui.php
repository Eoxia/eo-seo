<?php
/**
 * Shared interface pieces of the EO SEO screens.
 *
 * @package EoSeo
 */

namespace EoSeo\Admin;

use EoSeo\Not_Found_Log;
use EoSeo\Proposals;

defined( 'ABSPATH' ) || exit;

/**
 * Header with the navigation between screens, toggle switches.
 */
final class Ui {

	/**
	 * Screens of the navigation.
	 *
	 * @return array<string, array{label: string, count: int}> Keyed by page slug.
	 */
	public static function screens(): array {
		$screens = array(
			'eo-seo'              => array(
				'label' => __( 'Dashboard', 'eo-seo' ),
				'count' => 0,
			),
			'eo-seo-settings'     => array(
				'label' => __( 'Settings', 'eo-seo' ),
				'count' => 0,
			),
			'eo-seo-redirections' => array(
				'label' => __( 'Redirections', 'eo-seo' ),
				'count' => 0,
			),
			'eo-seo-404'          => array(
				'label' => __( '404 errors', 'eo-seo' ),
				'count' => Not_Found_Log::count_new(),
			),
		);

		$pending = Proposals::count_pending();
		if ( $pending ) {
			$screens['eo-seo-proposals'] = array(
				'label' => __( 'Proposals', 'eo-seo' ),
				'count' => $pending,
			);
		}

		return $screens;
	}

	/**
	 * Prints the header of a screen.
	 *
	 * @param string $current Current page slug.
	 * @param string $title   Title of the screen.
	 * @param string $intro   Short introduction.
	 */
	public static function header( string $current, string $title, string $intro = '' ): void {
		$screens = current_user_can( 'manage_options' ) ? self::screens() : array();
		include EO_SEO_DIR . 'admin/views/partials/header.php';
	}

	/**
	 * Prints a toggle switch (a styled checkbox).
	 *
	 * @param string $name    Input name.
	 * @param bool   $checked Whether it is on.
	 * @param string $label   Label.
	 * @param string $help    Help text.
	 */
	public static function toggle( string $name, bool $checked, string $label, string $help = '' ): void {
		$id = 'eo-seo-' . md5( $name );
		include EO_SEO_DIR . 'admin/views/partials/toggle.php';
	}
}
