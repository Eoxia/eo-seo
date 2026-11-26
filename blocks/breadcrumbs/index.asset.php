<?php
/**
 * Dependencies of the block editor script (written by hand: no build step).
 *
 * @package EoSeo
 */

defined( 'ABSPATH' ) || exit;

return array(
	'dependencies' => array( 'wp-block-editor', 'wp-blocks', 'wp-components', 'wp-element', 'wp-i18n', 'wp-server-side-render' ),
	'version'      => '0.1.0',
);
