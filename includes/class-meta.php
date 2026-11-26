<?php
/**
 * Resolution of the SEO metadata (custom field, else template).
 *
 * @package EoSeo
 */

namespace EoSeo;

defined( 'ABSPATH' ) || exit;

/**
 * Computes the title, description, robots, canonical and social data of a content,
 * a term or the current page. Shared by the front, the editor preview and the checks.
 */
final class Meta {

	/**
	 * SEO title of a content.
	 *
	 * @param \WP_Post                  $post   Content.
	 * @param array<string, mixed>|null $values Field values (null: stored values).
	 */
	public static function post_title( \WP_Post $post, ?array $values = null ): string {
		$values   = $values ?? Fields::get_post_values( $post->ID );
		$template = '' !== (string) $values['title'] ? (string) $values['title'] : Settings::template( 'post_type:' . $post->post_type )['title'];

		if ( (int) get_option( 'page_on_front' ) === $post->ID && 'page' === get_option( 'show_on_front' ) && '' === (string) $values['title'] ) {
			$template = Settings::template( 'home' )['title'];
		}

		return Variables::replace( $template, Variables::for_post( $post ) );
	}

	/**
	 * Meta description of a content.
	 *
	 * @param \WP_Post                  $post   Content.
	 * @param array<string, mixed>|null $values Field values (null: stored values).
	 */
	public static function post_description( \WP_Post $post, ?array $values = null ): string {
		$values   = $values ?? Fields::get_post_values( $post->ID );
		$template = '' !== (string) $values['description'] ? (string) $values['description'] : Settings::template( 'post_type:' . $post->post_type )['description'];

		return Variables::replace( $template, Variables::for_post( $post ) );
	}

	/**
	 * Whether a content is hidden from search engines.
	 *
	 * @param \WP_Post                  $post   Content.
	 * @param array<string, mixed>|null $values Field values (null: stored values).
	 */
	public static function post_noindex( \WP_Post $post, ?array $values = null ): bool {
		$values = $values ?? Fields::get_post_values( $post->ID );

		return ! empty( $values['noindex'] ) || ! Settings::is_post_type_indexed( $post->post_type );
	}

	/**
	 * SEO title of a term.
	 *
	 * @param \WP_Term $term Term.
	 */
	public static function term_title( \WP_Term $term ): string {
		$values   = Fields::get_term_values( $term->term_id );
		$template = '' !== (string) $values['title'] ? (string) $values['title'] : Settings::template( 'taxonomy:' . $term->taxonomy )['title'];

		return Variables::replace( $template, Variables::for_term( $term ) );
	}

	/**
	 * Meta description of a term.
	 *
	 * @param \WP_Term $term Term.
	 */
	public static function term_description( \WP_Term $term ): string {
		$values   = Fields::get_term_values( $term->term_id );
		$template = '' !== (string) $values['description'] ? (string) $values['description'] : Settings::template( 'taxonomy:' . $term->taxonomy )['description'];

		return Variables::replace( $template, Variables::for_term( $term ) );
	}

	/**
	 * Whether a term is hidden from search engines.
	 *
	 * @param \WP_Term $term Term.
	 */
	public static function term_noindex( \WP_Term $term ): bool {
		return ! empty( Fields::get_term_values( $term->term_id )['noindex'] ) || ! Settings::is_taxonomy_indexed( $term->taxonomy );
	}

	/**
	 * Metadata of the page being displayed on the front.
	 *
	 * @return array{title: string, description: string, noindex: bool, canonical: string, og_title: string, og_description: string, og_image: int, type: string}
	 */
	public static function current(): array {
		static $cache = null;
		if ( null !== $cache ) {
			return $cache;
		}

		$data = array(
			'title'          => '',
			'description'    => '',
			'noindex'        => false,
			'canonical'      => '',
			'og_title'       => '',
			'og_description' => '',
			'og_image'       => 0,
			'type'           => 'website',
		);

		$object = get_queried_object();

		if ( is_front_page() && is_home() ) {
			$template            = Settings::template( 'home' );
			$data['title']       = Variables::replace( $template['title'], array() );
			$data['description'] = Variables::replace( $template['description'], array() );
			$data['canonical']   = home_url( '/' );
		} elseif ( is_singular() && $object instanceof \WP_Post ) {
			$values                 = Fields::get_post_values( $object->ID );
			$data['title']          = self::post_title( $object, $values );
			$data['description']    = self::post_description( $object, $values );
			$data['noindex']        = self::post_noindex( $object, $values );
			$data['canonical']      = (string) $values['canonical'];
			$data['og_title']       = (string) $values['og_title'];
			$data['og_description'] = (string) $values['og_description'];
			$data['og_image']       = (int) $values['og_image_id'] ? (int) $values['og_image_id'] : (int) get_post_thumbnail_id( $object );
			$data['type']           = is_front_page() ? 'website' : 'article';
		} elseif ( ( is_category() || is_tag() || is_tax() ) && $object instanceof \WP_Term ) {
			$values                 = Fields::get_term_values( $object->term_id );
			$data['title']          = self::term_title( $object );
			$data['description']    = self::term_description( $object );
			$data['noindex']        = self::term_noindex( $object );
			$data['canonical']      = (string) $values['canonical'];
			$data['og_title']       = (string) $values['og_title'];
			$data['og_description'] = (string) $values['og_description'];
			$data['og_image']       = (int) $values['og_image_id'];
			if ( '' === $data['canonical'] ) {
				$link              = get_term_link( $object );
				$data['canonical'] = is_wp_error( $link ) ? '' : self::paged_url( $link );
			}
		} elseif ( is_home() && $object instanceof \WP_Post ) {
			// Posts page.
			$data['title']       = self::post_title( $object );
			$data['description'] = self::post_description( $object );
			$data['canonical']   = self::paged_url( (string) get_permalink( $object ) );
		} elseif ( is_post_type_archive() ) {
			$post_type         = get_query_var( 'post_type' );
			$post_type         = is_array( $post_type ) ? (string) reset( $post_type ) : (string) $post_type;
			$template          = Settings::template( 'archive:' . $post_type );
			$archive           = array( 'archive' => post_type_archive_title( '', false ) );
			$data['title']     = Variables::replace( $template['title'], $archive );
			$data['noindex']   = ! Settings::is_post_type_indexed( $post_type );
			$link              = get_post_type_archive_link( $post_type );
			$data['canonical'] = $link ? self::paged_url( $link ) : '';
			if ( '' !== $template['description'] ) {
				$data['description'] = Variables::replace( $template['description'], $archive );
			}
		} elseif ( is_author() && $object instanceof \WP_User ) {
			$template          = Settings::template( 'author' );
			$data['title']     = Variables::replace( $template['title'], array( 'author' => $object->display_name ) );
			$data['canonical'] = self::paged_url( get_author_posts_url( $object->ID ) );
		} elseif ( is_date() ) {
			$template        = Settings::template( 'date' );
			$data['title']   = Variables::replace( $template['title'], array( 'date' => wp_strip_all_tags( get_the_archive_title() ) ) );
			$data['noindex'] = (bool) Settings::get( 'noindex_date' );
		} elseif ( is_search() ) {
			$template        = Settings::template( 'search' );
			$data['title']   = Variables::replace( $template['title'], array( 'search' => get_search_query( false ) ) );
			$data['noindex'] = (bool) Settings::get( 'noindex_search' );
		} elseif ( is_404() ) {
			$data['noindex'] = true;
		}

		if ( ! $data['og_image'] ) {
			$data['og_image'] = (int) Settings::get( 'default_og_image' );
		}

		/**
		 * Filters the metadata of the current page.
		 *
		 * @param array<string, mixed> $data Metadata.
		 */
		$cache = (array) apply_filters( 'eo_seo_current_meta', $data );

		return $cache;
	}

	/**
	 * Adds the page number to an archive address.
	 *
	 * @param string $url Address of the first page.
	 */
	private static function paged_url( string $url ): string {
		$paged = (int) get_query_var( 'paged' );

		return $paged > 1 ? get_pagenum_link( $paged ) : $url;
	}
}
