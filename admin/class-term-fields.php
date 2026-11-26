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
		$fields = Fields::all();
		$values = Fields::get_term_values( $term->term_id );
		$preview = array(
			'title'       => Meta::term_title( $term ),
			'description' => Meta::term_description( $term ),
		);
		unset( $fields['focus_keyword'] );

		include EO_SEO_DIR . 'admin/views/term-fields.php';
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
