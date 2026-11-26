<?php
/**
 * Template variables (%title%, %sitename%…).
 *
 * @package EoSeo
 */

namespace EoSeo;

defined( 'ABSPATH' ) || exit;

/**
 * Replaces the variables of the title and description templates.
 */
final class Variables {

	/**
	 * Variables and their description, for the settings screen.
	 *
	 * @return array<string, string>
	 */
	public static function available(): array {
		return array(
			'%title%'    => __( 'Title of the content', 'eo-seo' ),
			'%excerpt%'  => __( 'Excerpt of the content, or description of the term', 'eo-seo' ),
			'%term%'     => __( 'Name of the category, tag or term', 'eo-seo' ),
			'%archive%'  => __( 'Name of the archive', 'eo-seo' ),
			'%author%'   => __( 'Name of the author', 'eo-seo' ),
			'%date%'     => __( 'Date of a date archive', 'eo-seo' ),
			'%search%'   => __( 'Searched words', 'eo-seo' ),
			'%sitename%' => __( 'Name of the site', 'eo-seo' ),
			'%tagline%'  => __( 'Tagline of the site', 'eo-seo' ),
			'%sep%'      => __( 'Separator', 'eo-seo' ),
			'%page%'     => __( 'Page number ("Page 2 of 5"), only from page 2', 'eo-seo' ),
		);
	}

	/**
	 * Replaces the variables of a template.
	 *
	 * @param string               $template Template.
	 * @param array<string, mixed> $values   Values of the context, keyed by variable name without %.
	 */
	public static function replace( string $template, array $values ): string {
		$values += array(
			'sitename' => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
			'tagline'  => wp_specialchars_decode( get_bloginfo( 'description' ), ENT_QUOTES ),
			'sep'      => (string) Settings::get( 'separator' ),
			'page'     => self::page(),
		);

		$text = preg_replace_callback(
			'/%([a-z_]+)%/',
			static fn( array $match ) => isset( $values[ $match[1] ] ) ? (string) $values[ $match[1] ] : '',
			$template
		);

		return self::tidy( (string) $text, (string) $values['sep'] );
	}

	/**
	 * Values of a content.
	 *
	 * @param \WP_Post $post Content.
	 * @return array<string, string>
	 */
	public static function for_post( \WP_Post $post ): array {
		return array(
			'title'   => wp_specialchars_decode( get_the_title( $post ), ENT_QUOTES ),
			'excerpt' => self::excerpt( $post ),
			'author'  => (string) get_the_author_meta( 'display_name', (int) $post->post_author ),
		);
	}

	/**
	 * Values of a term.
	 *
	 * @param \WP_Term $term Term.
	 * @return array<string, string>
	 */
	public static function for_term( \WP_Term $term ): array {
		return array(
			'term'    => $term->name,
			'title'   => $term->name,
			'excerpt' => self::shorten( wp_strip_all_tags( (string) $term->description ) ),
		);
	}

	/**
	 * Short description of a content: its excerpt, or the beginning of its text.
	 *
	 * @param \WP_Post $post Content.
	 */
	public static function excerpt( \WP_Post $post ): string {
		$text = '' !== trim( $post->post_excerpt ) ? $post->post_excerpt : excerpt_remove_blocks( $post->post_content );

		return self::shorten( wp_strip_all_tags( strip_shortcodes( $text ) ) );
	}

	/**
	 * Cuts a text at a word boundary, around the length of a meta description.
	 *
	 * @param string $text Plain text.
	 * @param int    $max  Maximum length.
	 */
	public static function shorten( string $text, int $max = 155 ): string {
		$text = trim( (string) preg_replace( '/\s+/u', ' ', html_entity_decode( $text, ENT_QUOTES, 'UTF-8' ) ) );
		if ( mb_strlen( $text ) <= $max ) {
			return $text;
		}

		$cut   = mb_substr( $text, 0, $max );
		$space = mb_strrpos( $cut, ' ' );

		return rtrim( false !== $space ? mb_substr( $cut, 0, $space ) : $cut, ' ,;:.' ) . '…';
	}

	/**
	 * "Page 2 of 5" on paginated archives and contents, empty on the first page.
	 */
	private static function page(): string {
		global $wp_query;

		$paged = max( (int) get_query_var( 'paged' ), (int) get_query_var( 'page' ) );
		if ( $paged < 2 ) {
			return '';
		}

		$total = $wp_query instanceof \WP_Query ? (int) $wp_query->max_num_pages : 0;

		return $total > 1
			/* translators: 1: page number, 2: number of pages. */
			? sprintf( __( 'Page %1$d of %2$d', 'eo-seo' ), $paged, $total )
			/* translators: %d: page number. */
			: sprintf( __( 'Page %d', 'eo-seo' ), $paged );
	}

	/**
	 * Removes the double spaces and the separators left alone by empty variables.
	 *
	 * @param string $text      Text.
	 * @param string $separator Separator.
	 */
	private static function tidy( string $text, string $separator ): string {
		$text = trim( (string) preg_replace( '/\s+/u', ' ', $text ) );
		if ( '' === $separator ) {
			return $text;
		}

		$sep  = preg_quote( $separator, '/' );
		$text = (string) preg_replace( '/(' . $sep . '\s*){2,}/u', $separator . ' ', $text );

		return trim( (string) preg_replace( '/^(\s*' . $sep . '\s*)+|(\s*' . $sep . '\s*)+$/u', '', $text ) );
	}
}
