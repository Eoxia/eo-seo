<?php
/**
 * SEO fields of the term edit screen.
 *
 * @package EoSeo
 */

namespace EoSeo\Admin;

use EoSeo\Compat;
use EoSeo\Fields;
use EoSeo\Meta;
use EoSeo\Settings;
use EoSeo\Term_Analysis;
use EoSeo\Term_Checks;
use EoSeo\Variables;

defined( 'ABSPATH' ) || exit;

/**
 * Adds the SEO fields to the edit screen of the categories, tags and public terms.
 */
final class Term_Fields {

	/**
	 * Hooks.
	 */
	public static function init(): void {
		add_action( 'admin_init', array( self::class, 'register' ) );
	}

	/**
	 * Hooks of every handled taxonomy.
	 */
	public static function register(): void {
		if ( Compat::has_conflict() ) {
			return;
		}

		foreach ( array_keys( Settings::taxonomies() ) as $taxonomy ) {
			add_action( "{$taxonomy}_edit_form", array( self::class, 'render' ), 10, 1 );
			add_action( "edited_{$taxonomy}", array( self::class, 'save' ), 10, 1 );
		}
	}

	/**
	 * Prints the fields.
	 *
	 * @param \WP_Term $term Term.
	 */
	public static function render( \WP_Term $term ): void {
		$fields   = Fields::all();
		$template = Settings::template( 'taxonomy:' . $term->taxonomy );
		$vars     = Variables::for_term( $term );
		$link     = get_term_link( $term );
		$preview  = array(
			'title'                => Meta::term_title( $term ),
			'description'          => Meta::term_description( $term ),
			'url'                  => is_wp_error( $link ) ? '' : $link,
			'title_template'       => Variables::replace( $template['title'], $vars ),
			'description_template' => Variables::replace( $template['description'], $vars ),
		);
		unset( $fields['focus_keyword'] ); // No focus keyphrase for an archive.

		wp_nonce_field( 'eo_seo_term_' . $term->term_id, 'eo_seo_term_nonce' );
		echo '<h2 class="eo-seo-box-title">' . esc_html__( 'SEO', 'eo-seo' ) . '</h2>';
		Ui::seo_box( $fields, Fields::get_term_values( $term->term_id ), $preview, Term_Checks::run( new Term_Analysis( $term ) ), $term->name );
	}

	/**
	 * Saves the fields.
	 *
	 * @param int $term_id Term ID.
	 */
	public static function save( int $term_id ): void {
		if ( ! isset( $_POST['eo_seo_term_nonce'] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST['eo_seo_term_nonce'] ) ), 'eo_seo_term_' . $term_id ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_term', $term_id ) ) {
			return;
		}

		$input  = isset( $_POST['eo_seo'] ) && is_array( $_POST['eo_seo'] ) ? wp_unslash( $_POST['eo_seo'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized per field by Fields::save().
		$values = array();
		foreach ( Fields::all() as $name => $field ) {
			if ( 'focus_keyword' === $name ) {
				continue;
			}
			$values[ $name ] = $input[ $name ] ?? Fields::default_value( $field );
		}

		Fields::save( 'term', $term_id, $values );
	}
}
