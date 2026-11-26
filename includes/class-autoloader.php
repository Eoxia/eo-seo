<?php
/**
 * Class autoloader.
 *
 * @package EoSeo
 */

namespace EoSeo;

defined( 'ABSPATH' ) || exit;

/**
 * Loads the plugin classes, following the WordPress file naming:
 * EoSeo\Not_Found_Log → includes/class-not-found-log.php,
 * EoSeo\Admin\Settings_Page → admin/class-settings-page.php.
 */
final class Autoloader {

	/**
	 * Registers the autoloader.
	 */
	public static function register(): void {
		spl_autoload_register( array( self::class, 'load' ) );
	}

	/**
	 * Loads a class of the plugin.
	 *
	 * @param string $class_name Fully qualified class name.
	 */
	public static function load( string $class_name ): void {
		if ( ! str_starts_with( $class_name, __NAMESPACE__ . '\\' ) ) {
			return;
		}

		$parts = explode( '\\', substr( $class_name, strlen( __NAMESPACE__ ) + 1 ) );
		$name  = array_pop( $parts );
		$dir   = 'includes';

		if ( array( 'Admin' ) === $parts ) {
			$dir = 'admin';
		} elseif ( array() !== $parts ) {
			$dir = 'includes/' . strtolower( implode( '/', $parts ) );
		}

		$file = EO_SEO_DIR . $dir . '/class-' . strtolower( str_replace( '_', '-', $name ) ) . '.php';
		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
}
