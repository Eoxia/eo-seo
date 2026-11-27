<?php
/**
 * SEO box of the classic editor.
 *
 * @package EoSeo
 */

namespace EoSeo\Admin;

use EoSeo\Analysis;
use EoSeo\Checks;
use EoSeo\Fields;
use EoSeo\Meta;
use EoSeo\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Contents edited without the block editor (custom post types without show_in_rest, Classic Editor plugin)
 * get the SEO box as a metabox; the block editor has the EO SEO sidebar instead.
 */
final class Metabox {

	/**
	 * Hooks.
	 */
	public static function init(): void {
		add_action( 'add_meta_boxes', array( self::class, 'register' ), 10, 2 );
		add_action( 'save_post', array( self::class, 'save' ), 10, 2 );
	}

	/**
	 * Whether a content is edited with the classic editor.
	 *
	 * @param \WP_Post $post Content.
	 */
	public static function is_classic( \WP_Post $post ): bool {
		return isset( Settings::post_types()[ $post->post_type ] ) && ! use_block_editor_for_post( $post );
	}

	/**
	 * Adds the metabox.
	 *
	 * @param string $post_type Post type.
	 * @param mixed  $post      Content (a comment on the comment screen).
	 */
	public static function register( string $post_type, $post ): void {
		if ( ! $post instanceof \WP_Post || ! self::is_classic( $post ) ) {
			return;
		}

		add_meta_box( 'eo-seo', __( 'EO SEO', 'eo-seo' ), array( self::class, 'render' ), $post_type, 'normal', 'high' );
	}

	/**
	 * Prints the metabox.
	 *
	 * @param \WP_Post $post Content.
	 */
	public static function render( \WP_Post $post ): void {
		$values  = Fields::get_post_values( $post->ID );
		$empty   = array_merge( $values, array( 'title' => '', 'description' => '' ) ); // What the templates give.
		$preview = array(
			'title'                => Meta::post_title( $post, $values ),
			'description'          => Meta::post_description( $post, $values ),
			'url'                  => (string) get_permalink( $post ),
			'title_template'       => Meta::post_title( $post, $empty ),
			'description_template' => Meta::post_description( $post, $empty ),
		);

		wp_nonce_field( 'eo_seo_metabox_' . $post->ID, 'eo_seo_metabox_nonce' );
		Ui::seo_box( Fields::all(), $values, $preview, Checks::run( new Analysis( $post ) ), wp_strip_all_tags( get_the_title( $post ) ) );
	}

	/**
	 * Saves the fields of the metabox.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Content.
	 */
	public static function save( int $post_id, \WP_Post $post ): void {
		if ( ! isset( $_POST['eo_seo_metabox_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['eo_seo_metabox_nonce'] ) ), 'eo_seo_metabox_' . $post_id ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$input  = isset( $_POST['eo_seo'] ) && is_array( $_POST['eo_seo'] ) ? wp_unslash( $_POST['eo_seo'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized per field by Fields::save().
		$values = array();
		foreach ( Fields::all() as $name => $field ) {
			$values[ $name ] = $input[ $name ] ?? Fields::default_value( $field );
		}

		// The indicator is computed afterwards, on wp_after_insert_post.
		Fields::save( 'post', $post_id, $values );
	}
}
