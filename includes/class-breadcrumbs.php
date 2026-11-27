<?php
/**
 * Breadcrumbs (block and BreadcrumbList).
 *
 * @package EoSeo
 */

namespace EoSeo;

defined( 'ABSPATH' ) || exit;

/**
 * Builds the trail of the current page, shared by the block and the structured data.
 */
final class Breadcrumbs {

	/**
	 * Hooks.
	 */
	public static function init(): void {
		add_action( 'init', array( self::class, 'register_block' ) );
	}

	/**
	 * Registers the "Breadcrumbs" block (rendered on the server).
	 */
	public static function register_block(): void {
		register_block_type( EO_SEO_DIR . 'blocks/breadcrumbs' );
	}

	/**
	 * Trail of the current page.
	 *
	 * @return array<int, array{name: string, url: string}> From the home page to the current page.
	 */
	public static function items(): array {
		$items  = array(
			array(
				'name' => __( 'Home', 'eo-seo' ),
				'url'  => home_url( '/' ),
			),
		);
		$object = get_queried_object();

		if ( is_front_page() ) {
			return $items;
		}

		if ( is_singular() && $object instanceof \WP_Post ) {
			$type = get_post_type_object( $object->post_type );

			if ( 'post' === $object->post_type ) {
				$posts_page = (int) get_option( 'page_for_posts' );
				if ( $posts_page ) {
					$items[] = self::post_item( $posts_page );
				}
				$categories = get_the_category( $object->ID );
				if ( $categories ) {
					$items = array_merge( $items, self::term_trail( $categories[0] ) );
				}
			} elseif ( $type ) {
				if ( $type->has_archive ) {
					$items[] = array(
						'name' => (string) $type->labels->name,
						'url'  => (string) get_post_type_archive_link( $type->name ),
					);
				}
				// Custom post types: their first category-like taxonomy (products › product category…).
				$term = self::primary_term( $object );
				if ( $term ) {
					$items = array_merge( $items, self::term_trail( $term ) );
				}
			}

			foreach ( array_reverse( get_post_ancestors( $object ) ) as $ancestor ) {
				$items[] = self::post_item( (int) $ancestor );
			}
			$items[] = self::post_item( $object->ID );
		} elseif ( $object instanceof \WP_Term ) {
			$items = array_merge( $items, self::term_trail( $object ) );
		} elseif ( is_home() && $object instanceof \WP_Post ) {
			$items[] = self::post_item( $object->ID );
		} elseif ( is_search() ) {
			$items[] = array(
				/* translators: %s: searched words. */
				'name' => sprintf( __( 'Search results for "%s"', 'eo-seo' ), get_search_query( false ) ),
				'url'  => '',
			);
		} elseif ( is_404() ) {
			$items[] = array(
				'name' => __( 'Page not found', 'eo-seo' ),
				'url'  => '',
			);
		} elseif ( is_archive() ) {
			$items[] = array(
				'name' => wp_strip_all_tags( get_the_archive_title() ),
				'url'  => '',
			);
		}

		/**
		 * Filters the breadcrumbs of the current page.
		 *
		 * @param array<int, array{name: string, url: string}> $items Trail.
		 */
		return (array) apply_filters( 'eo_seo_breadcrumbs', $items );
	}

	/**
	 * Item of a content.
	 *
	 * @param int $post_id Post ID.
	 * @return array{name: string, url: string}
	 */
	private static function post_item( int $post_id ): array {
		return array(
			'name' => wp_strip_all_tags( get_the_title( $post_id ) ),
			'url'  => (string) get_permalink( $post_id ),
		);
	}

	/**
	 * Main term of a custom content: first term of its first public hierarchical taxonomy.
	 *
	 * @param \WP_Post $post Content.
	 */
	private static function primary_term( \WP_Post $post ): ?\WP_Term {
		if ( is_post_type_hierarchical( $post->post_type ) ) {
			return null; // Pages and page-like types use their parents.
		}

		foreach ( get_object_taxonomies( $post->post_type, 'objects' ) as $taxonomy ) {
			if ( ! $taxonomy->hierarchical || ! $taxonomy->public ) {
				continue;
			}
			$terms = get_the_terms( $post, $taxonomy->name );
			if ( $terms && ! is_wp_error( $terms ) ) {
				return $terms[0];
			}
		}

		return null;
	}

	/**
	 * A term and its parents.
	 *
	 * @param \WP_Term $term Term.
	 * @return array<int, array{name: string, url: string}>
	 */
	private static function term_trail( \WP_Term $term ): array {
		$items = array();
		$ids   = array_reverse( get_ancestors( $term->term_id, $term->taxonomy, 'taxonomy' ) );
		$ids[] = $term->term_id;

		foreach ( $ids as $id ) {
			$item = get_term( (int) $id, $term->taxonomy );
			$link = $item instanceof \WP_Term ? get_term_link( $item ) : '';
			if ( $item instanceof \WP_Term && ! is_wp_error( $link ) ) {
				$items[] = array(
					'name' => $item->name,
					'url'  => $link,
				);
			}
		}

		return $items;
	}
}
