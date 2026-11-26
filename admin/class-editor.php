<?php
/**
 * "EO SEO" panel of the block editor.
 *
 * @package EoSeo
 */

namespace EoSeo\Admin;

use EoSeo\Checks;
use EoSeo\Fields;
use EoSeo\Rest;
use EoSeo\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Loads the sidebar script (badge in the top bar + EO SEO sidebar) on the edit screen of the handled post types only.
 */
final class Editor {

	/**
	 * Hooks.
	 */
	public static function init(): void {
		add_action( 'enqueue_block_editor_assets', array( self::class, 'assets' ) );
	}

	/**
	 * Script and style of the panel.
	 */
	public static function assets(): void {
		$screen = get_current_screen();
		if ( ! $screen || 'post' !== $screen->base || ! isset( Settings::post_types()[ $screen->post_type ] ) ) {
			return;
		}

		wp_enqueue_script(
			'eo-seo-editor',
			EO_SEO_URL . 'assets/js/editor.js',
			array( 'wp-api-fetch', 'wp-block-editor', 'wp-components', 'wp-data', 'wp-editor', 'wp-element', 'wp-i18n', 'wp-plugins' ),
			EO_SEO_VERSION,
			true
		);
		wp_set_script_translations( 'eo-seo-editor', 'eo-seo', EO_SEO_DIR . 'languages' );
		wp_enqueue_style( 'eo-seo-editor', EO_SEO_URL . 'assets/css/editor.css', array(), EO_SEO_VERSION );

		$fields = array();
		foreach ( Fields::all() as $name => $field ) {
			$fields[ $name ] = array(
				'meta'  => $field['meta'],
				'input' => $field['input'],
				'label' => $field['label'],
				'help'  => $field['help'],
				'min'   => $field['min'] ?? null,
				'max'   => $field['max'] ?? null,
			);
		}

		wp_add_inline_script(
			'eo-seo-editor',
			'window.eoSeoEditor = ' . wp_json_encode(
				array(
					'route'  => '/' . Rest::NAMESPACE . '/analyze/',
					'fields' => $fields,
					'levels' => Checks::levels(),
				)
			) . ';',
			'before'
		);
	}
}
