<?php
/**
 * "EO SEO" panel of the block editor.
 *
 * @package EoSeo
 */

namespace EoSeo\Admin;

use EoSeo\Checks;
use EoSeo\Compat;
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
	 * Another SEO plugin is active: the EO SEO panel is hidden, a dismissible notice says why
	 * in the editors (once a month per person, so that it stays rare).
	 */
	public static function init_compat(): void {
		add_action( 'enqueue_block_editor_assets', array( self::class, 'compat_block_editor' ) );
		add_action( 'admin_notices', array( self::class, 'compat_classic' ) );
	}

	/**
	 * Message of the compatibility notice, when it is due for the current user.
	 *
	 * @return string Empty when the notice was shown less than a month ago.
	 */
	private static function compat_message(): string {
		$user = get_current_user_id();
		$last = (int) get_user_meta( $user, 'eo_seo_compat_notice', true );
		if ( $last > time() - MONTH_IN_SECONDS ) {
			return '';
		}
		update_user_meta( $user, 'eo_seo_compat_notice', time() );

		return sprintf(
			/* translators: %s: plugin names. */
			__( '%s is active: it handles the SEO of this content, so the EO SEO panel is hidden. Keep only one SEO plugin.', 'eo-seo' ),
			implode( ', ', Compat::active_plugins() )
		);
	}

	/**
	 * Notice of the block editor.
	 */
	public static function compat_block_editor(): void {
		$screen = get_current_screen();
		if ( ! $screen || 'post' !== $screen->base || ! isset( Settings::post_types()[ $screen->post_type ] ) ) {
			return;
		}

		$message = self::compat_message();
		if ( '' === $message ) {
			return;
		}

		wp_register_script( 'eo-seo-compat', false, array( 'wp-data', 'wp-dom-ready', 'wp-notices' ), EO_SEO_VERSION, true );
		wp_enqueue_script( 'eo-seo-compat' );
		wp_add_inline_script(
			'eo-seo-compat',
			'wp.domReady( function () { wp.data.dispatch( "core/notices" ).createInfoNotice( ' . wp_json_encode( $message ) . ', { id: "eo-seo-compat", isDismissible: true } ); } );'
		);
	}

	/**
	 * Notice of the classic editor and of the term screens.
	 */
	public static function compat_classic(): void {
		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->base, array( 'post', 'term' ), true ) || $screen->is_block_editor() ) {
			return;
		}

		$message = self::compat_message();
		if ( '' !== $message ) {
			wp_admin_notice(
				esc_html( $message ),
				array(
					'type'        => 'info',
					'dismissible' => true,
				)
			);
		}
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
			Admin::asset_version( 'assets/js/editor.js' ),
			true
		);
		wp_set_script_translations( 'eo-seo-editor', 'eo-seo', EO_SEO_DIR . 'languages' );
		wp_enqueue_style( 'eo-seo-editor', EO_SEO_URL . 'assets/css/editor.css', array(), Admin::asset_version( 'assets/css/editor.css' ) );

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
					'route'    => '/' . Rest::NAMESPACE . '/analyze/',
					'fields'   => $fields,
					'levels'   => Checks::levels(),
					'families' => Checks::families(),
				)
			) . ';',
			'before'
		);
	}
}
