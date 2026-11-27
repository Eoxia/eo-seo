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
	 * Prints one SEO field of the SEO box (input named eo_seo[name]).
	 *
	 * @param string               $name        Field name.
	 * @param array<string, mixed> $field       Field definition.
	 * @param mixed                $value       Value.
	 * @param string               $placeholder Value given by the template when the field is empty.
	 */
	public static function field( string $name, array $field, mixed $value, string $placeholder = '' ): void {
		$id    = 'eo-seo-field-' . $name;
		$input = 'eo_seo[' . $name . ']';
		include EO_SEO_DIR . 'admin/views/partials/field.php';
	}

	/**
	 * Prints the SEO box of a content or a term.
	 *
	 * @param array<string, array<string, mixed>> $fields  Fields shown.
	 * @param array<string, mixed>                $values  Values.
	 * @param array<string, string>               $preview Resolved title, description and address.
	 * @param array<string, mixed>                $result  Indicator and checks.
	 * @param string                              $title   Title of the content or name of the term (live preview).
	 */
	public static function seo_box( array $fields, array $values, array $preview, array $result, string $title ): void {
		$levels = \EoSeo\Checks::levels();
		$vars   = array(
			'sitename' => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
			'sep'      => (string) \EoSeo\Settings::get( 'separator' ),
			'title'    => $title,
		);
		include EO_SEO_DIR . 'admin/views/partials/seo-box.php';
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
