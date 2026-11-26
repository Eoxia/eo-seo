<?php
/**
 * Plugin Name:       EO SEO
 * Plugin URI:        https://github.com/Eoxia/eo-seo
 * Description:       A complete, lightweight SEO plugin: metadata, per-page SEO indicator, indexing, structured data, redirections and 404 log.
 * Version:           0.1.0
 * Requires at least: 7.0
 * Requires PHP:      8.1
 * Author:            Eoxia
 * Author URI:        https://www.eoxia.com/
 * License:           GPL-3.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       eo-seo
 * Domain Path:       /languages
 *
 * @package EoSeo
 */

defined( 'ABSPATH' ) || exit;

define( 'EO_SEO_VERSION', '0.1.0' );
define( 'EO_SEO_FILE', __FILE__ );
define( 'EO_SEO_DIR', plugin_dir_path( __FILE__ ) );
define( 'EO_SEO_URL', plugin_dir_url( __FILE__ ) );

require_once EO_SEO_DIR . 'includes/class-autoloader.php';
EoSeo\Autoloader::register();

register_activation_hook( __FILE__, array( 'EoSeo\Installer', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'EoSeo\Installer', 'deactivate' ) );

add_action( 'plugins_loaded', array( 'EoSeo\Plugin', 'boot' ) );
