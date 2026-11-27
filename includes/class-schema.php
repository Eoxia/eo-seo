<?php
/**
 * Structured data (JSON-LD graph).
 *
 * @package EoSeo
 */

namespace EoSeo;

defined( 'ABSPATH' ) || exit;

/**
 * Prints one JSON-LD graph: WebSite, the site identity, WebPage, Article and BreadcrumbList.
 */
final class Schema {

	/**
	 * Hooks.
	 */
	public static function init(): void {
		add_action( 'wp_head', array( self::class, 'output' ), 30 );
	}

	/**
	 * Prints the graph.
	 */
	public static function output(): void {
		if ( is_404() || Meta::current()['noindex'] ) {
			return;
		}

		$graph = self::graph();
		if ( ! $graph ) {
			return;
		}

		wp_print_inline_script_tag(
			(string) wp_json_encode(
				array(
					'@context' => 'https://schema.org',
					'@graph'   => $graph,
				),
				JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
			),
			array( 'type' => 'application/ld+json' )
		);
	}

	/**
	 * Nodes of the current page.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function graph(): array {
		$home     = home_url( '/' );
		$identity = self::identity();
		$meta     = Meta::current();
		$url      = is_singular() ? (string) wp_get_canonical_url() : ( '' !== $meta['canonical'] ? $meta['canonical'] : $home );
		$graph    = array();

		$website = array(
			'@type'      => 'WebSite',
			'@id'        => $home . '#website',
			'url'        => $home,
			'name'       => get_bloginfo( 'name' ),
			'inLanguage' => get_bloginfo( 'language' ),
		);
		if ( '' !== get_bloginfo( 'description' ) ) {
			$website['description'] = get_bloginfo( 'description' );
		}
		if ( $identity ) {
			$website['publisher'] = array( '@id' => $identity['@id'] );
			$graph[]              = $identity;
		}
		$graph[] = $website;

		$webpage = array(
			'@type'      => is_singular() || is_front_page() ? 'WebPage' : 'CollectionPage',
			'@id'        => $url . '#webpage',
			'url'        => $url,
			'name'       => $meta['title'],
			'isPartOf'   => array( '@id' => $home . '#website' ),
			'inLanguage' => get_bloginfo( 'language' ),
		);
		if ( '' !== $meta['description'] ) {
			$webpage['description'] = $meta['description'];
		}

		$crumbs = Breadcrumbs::items();
		if ( count( $crumbs ) > 1 ) {
			$webpage['breadcrumb'] = array( '@id' => $url . '#breadcrumb' );
			$graph[]               = self::breadcrumb_list( $crumbs, $url );
		}
		$graph[] = $webpage;

		$post = get_queried_object();
		if ( is_singular( 'post' ) && $post instanceof \WP_Post ) {
			$graph[] = self::article( $post, $url, $identity );
		}

		/**
		 * Filters the JSON-LD graph of the page.
		 *
		 * @param array<int, array<string, mixed>> $graph Nodes.
		 */
		return array_values( (array) apply_filters( 'eo_seo_schema_graph', $graph ) );
	}

	/**
	 * Organization, Person or LocalBusiness representing the site.
	 *
	 * @return array<string, mixed>|null
	 */
	private static function identity(): ?array {
		$settings = (array) Settings::get( 'identity' );
		$name     = '' !== $settings['name'] ? $settings['name'] : get_bloginfo( 'name' );
		$types    = array(
			'organization'   => 'Organization',
			'person'         => 'Person',
			'local_business' => 'LocalBusiness',
		);
		$type     = $types[ $settings['type'] ] ?? 'Organization';
		$node     = array(
			'@type' => $type,
			'@id'   => home_url( '/' ) . '#identity',
			'name'  => $name,
			'url'   => home_url( '/' ),
		);

		$logo = (int) $settings['logo'] ? (int) $settings['logo'] : (int) get_theme_mod( 'custom_logo' );
		if ( $logo ) {
			$image = wp_get_attachment_image_src( $logo, 'full' );
			if ( $image ) {
				$node[ 'Person' === $type ? 'image' : 'logo' ] = array(
					'@type'  => 'ImageObject',
					'url'    => $image[0],
					'width'  => $image[1],
					'height' => $image[2],
				);
			}
		}

		if ( $settings['same_as'] ) {
			$node['sameAs'] = array_values( (array) $settings['same_as'] );
		}

		if ( 'LocalBusiness' === $type ) {
			$address = array_filter(
				array(
					'@type'           => 'PostalAddress',
					'streetAddress'   => $settings['street'],
					'postalCode'      => $settings['postal_code'],
					'addressLocality' => $settings['city'],
					'addressCountry'  => $settings['country'],
				)
			);
			if ( count( $address ) > 1 ) {
				$node['address'] = $address;
			}
			if ( '' !== $settings['phone'] ) {
				$node['telephone'] = $settings['phone'];
			}
		}

		return $node;
	}

	/**
	 * BreadcrumbList of the page.
	 *
	 * @param array<int, array{name: string, url: string}> $crumbs Trail.
	 * @param string                                       $url    Address of the page.
	 * @return array<string, mixed>
	 */
	private static function breadcrumb_list( array $crumbs, string $url ): array {
		$elements = array();
		foreach ( array_values( $crumbs ) as $position => $crumb ) {
			$element = array(
				'@type'    => 'ListItem',
				'position' => $position + 1,
				'name'     => $crumb['name'],
			);
			if ( '' !== $crumb['url'] ) {
				$element['item'] = $crumb['url'];
			}
			$elements[] = $element;
		}

		return array(
			'@type'           => 'BreadcrumbList',
			'@id'             => $url . '#breadcrumb',
			'itemListElement' => $elements,
		);
	}

	/**
	 * Article node of a post.
	 *
	 * @param \WP_Post                  $post     Post.
	 * @param string                    $url      Address.
	 * @param array<string, mixed>|null $identity Identity node.
	 * @return array<string, mixed>
	 */
	private static function article( \WP_Post $post, string $url, ?array $identity ): array {
		$node = array(
			'@type'            => 'Article',
			'@id'              => $url . '#article',
			'headline'         => wp_strip_all_tags( get_the_title( $post ) ),
			'mainEntityOfPage' => array( '@id' => $url . '#webpage' ),
			'datePublished'    => (string) get_post_time( DATE_W3C, true, $post ),
			'dateModified'     => (string) get_post_modified_time( DATE_W3C, true, $post ),
			'author'           => array(
				'@type' => 'Person',
				'name'  => (string) get_the_author_meta( 'display_name', (int) $post->post_author ),
			),
			'inLanguage'       => get_bloginfo( 'language' ),
		);

		if ( Settings::get( 'author_archives' ) ) {
			$node['author']['url'] = get_author_posts_url( (int) $post->post_author );
		}

		if ( $identity ) {
			$node['publisher'] = array( '@id' => $identity['@id'] );
		}

		$image = get_the_post_thumbnail_url( $post, 'full' );
		if ( $image ) {
			$node['image'] = $image;
		}

		return $node;
	}
}
