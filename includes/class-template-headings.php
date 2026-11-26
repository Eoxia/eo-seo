<?php
/**
 * H1 headings printed by the template of a content (block themes).
 *
 * @package EoSeo
 */

namespace EoSeo;

defined( 'ABSPATH' ) || exit;

/**
 * Reads the block template that displays a content (with its template parts and patterns)
 * to know whether the theme prints the page title as an H1, and which other H1 it prints.
 */
final class Template_Headings {

	/**
	 * Maximum nesting of template parts and patterns followed.
	 */
	private const MAX_DEPTH = 6;

	/**
	 * Results per template ID, for the request.
	 *
	 * @var array<string, array{known: bool, title_h1: bool, other_h1: string[], template: string}>
	 */
	private static array $cache = array();

	/**
	 * H1 headings of the template of a content.
	 *
	 * @param \WP_Post $post Content.
	 * @return array{known: bool, title_h1: bool, other_h1: string[], template: string}
	 *         known: false when the template cannot be read (classic theme): title_h1 then comes from the filter.
	 */
	public static function for_post( \WP_Post $post ): array {
		$result = array(
			'known'    => false,
			'title_h1' => true,
			'other_h1' => array(),
			'template' => '',
		);

		if ( function_exists( 'wp_is_block_theme' ) && wp_is_block_theme() ) {
			$template = self::find_template( $post );
			if ( $template ) {
				if ( ! isset( self::$cache[ $template->id ] ) ) {
					$found                         = self::scan( parse_blocks( (string) $template->content ), 0 );
					self::$cache[ $template->id ] = array(
						'known'    => true,
						'title_h1' => $found['title_h1'],
						'other_h1' => array_values( array_unique( $found['other_h1'] ) ),
						'template' => (string) $template->slug,
					);
				}
				$result = self::$cache[ $template->id ];
			}
		}

		/**
		 * Filters whether the template of a content prints the page title as an H1.
		 * Useful for classic themes, whose PHP templates cannot be read.
		 *
		 * @param bool     $title_h1 Whether the title is an H1.
		 * @param \WP_Post $post     Content.
		 * @param bool     $known    Whether EO SEO could read the template.
		 */
		$result['title_h1'] = (bool) apply_filters( 'eo_seo_template_title_is_h1', $result['title_h1'], $post, $result['known'] );

		return $result;
	}

	/**
	 * Block template displaying a content, as WordPress picks it (custom template, then the hierarchy).
	 *
	 * @param \WP_Post $post Content.
	 */
	private static function find_template( \WP_Post $post ): ?\WP_Block_Template {
		$slugs  = array();
		$custom = (string) get_page_template_slug( $post );
		if ( '' !== $custom ) {
			$slugs[] = preg_replace( '/\.php$/', '', $custom );
		}

		if ( 'page' === get_option( 'show_on_front' ) && (int) get_option( 'page_on_front' ) === $post->ID ) {
			$slugs[] = 'front-page';
		}

		if ( 'page' === $post->post_type ) {
			array_push( $slugs, 'page-' . rawurldecode( $post->post_name ), 'page-' . $post->ID, 'page' );
		} else {
			array_push( $slugs, 'single-' . $post->post_type . '-' . rawurldecode( $post->post_name ), 'single-' . $post->post_type, 'single' );
		}
		array_push( $slugs, 'singular', 'index' );

		foreach ( $slugs as $slug ) {
			$template = get_block_template( get_stylesheet() . '//' . $slug, 'wp_template' );
			if ( $template && '' !== (string) $template->content ) {
				return $template;
			}
		}

		return null;
	}

	/**
	 * Looks for the H1 headings in blocks, following template parts and patterns.
	 *
	 * @param array<int, array<string, mixed>> $blocks Parsed blocks.
	 * @param int                              $depth  Nesting level.
	 * @return array{title_h1: bool, other_h1: string[]}
	 */
	private static function scan( array $blocks, int $depth ): array {
		$found = array(
			'title_h1' => false,
			'other_h1' => array(),
		);

		foreach ( $blocks as $block ) {
			$name  = (string) ( $block['blockName'] ?? '' );
			$attrs = (array) ( $block['attrs'] ?? array() );
			$inner = (array) ( $block['innerBlocks'] ?? array() );

			switch ( $name ) {
				case 'core/post-title':
					// Default level of the block: 2.
					if ( 1 === (int) ( $attrs['level'] ?? 2 ) ) {
						$found['title_h1'] = true;
					}
					break;

				case 'core/site-title':
					// Default level of the block: 1 (0 prints a paragraph).
					if ( 1 === (int) ( $attrs['level'] ?? 1 ) ) {
						$found['other_h1'][] = __( 'site title', 'eo-seo' );
					}
					break;

				case 'core/heading':
					if ( 1 === (int) ( $attrs['level'] ?? 2 ) ) {
						$text                = trim( wp_strip_all_tags( (string) ( $block['innerHTML'] ?? '' ) ) );
						$found['other_h1'][] = '' !== $text ? $text : __( 'heading', 'eo-seo' );
					}
					break;

				case 'core/template-part':
					if ( $depth < self::MAX_DEPTH && ! empty( $attrs['slug'] ) ) {
						$theme = ! empty( $attrs['theme'] ) ? $attrs['theme'] : get_stylesheet();
						$part  = get_block_template( $theme . '//' . $attrs['slug'], 'wp_template_part' );
						if ( $part ) {
							$inner = array_merge( $inner, parse_blocks( (string) $part->content ) );
						}
					}
					break;

				case 'core/pattern':
					if ( $depth < self::MAX_DEPTH && ! empty( $attrs['slug'] ) ) {
						$pattern = \WP_Block_Patterns_Registry::get_instance()->get_registered( (string) $attrs['slug'] );
						if ( $pattern && ! empty( $pattern['content'] ) ) {
							$inner = array_merge( $inner, parse_blocks( (string) $pattern['content'] ) );
						}
					}
					break;

				case 'core/block':
					if ( $depth < self::MAX_DEPTH && ! empty( $attrs['ref'] ) ) {
						$synced = get_post( (int) $attrs['ref'] );
						if ( $synced && 'wp_block' === $synced->post_type ) {
							$inner = array_merge( $inner, parse_blocks( $synced->post_content ) );
						}
					}
					break;
			}

			if ( $inner ) {
				$sub               = self::scan( $inner, $depth + 1 );
				$found['title_h1'] = $found['title_h1'] || $sub['title_h1'];
				$found['other_h1'] = array_merge( $found['other_h1'], $sub['other_h1'] );
			}
		}

		return $found;
	}
}
