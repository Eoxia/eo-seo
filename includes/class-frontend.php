<?php
/**
 * Front output: title, meta tags, robots, canonical, social tags.
 *
 * @package EoSeo
 */

namespace EoSeo;

defined( 'ABSPATH' ) || exit;

/**
 * Plugs EO SEO into the WordPress APIs of the front (no script, no style).
 */
final class Frontend {

	/**
	 * Hooks.
	 */
	public static function init(): void {
		add_filter( 'pre_get_document_title', array( self::class, 'document_title' ), 20 );
		add_filter( 'wp_robots', array( self::class, 'robots' ) );
		add_filter( 'get_canonical_url', array( self::class, 'canonical' ), 10, 2 );
		add_action( 'wp_head', array( self::class, 'head' ), 1 );
		add_action( 'template_redirect', array( self::class, 'redirect_author_archives' ) );
		add_action( 'init', array( self::class, 'cleanup' ) );
	}

	/**
	 * Title of the page.
	 *
	 * @param string $title Title computed by another filter.
	 */
	public static function document_title( string $title ): string {
		if ( is_feed() ) {
			return $title;
		}

		$meta = Meta::current();

		return '' !== $meta['title'] ? esc_html( $meta['title'] ) : $title;
	}

	/**
	 * Robots directives.
	 *
	 * @param array<string, bool|string> $robots Directives.
	 * @return array<string, bool|string>
	 */
	public static function robots( array $robots ): array {
		// Pages 2, 3… of the comments repeat the content: kept out of the index, links followed.
		$cleanup       = (array) Settings::get( 'cleanup' );
		$comment_pages = ! empty( $cleanup['comment_pages'] ) && is_singular() && (int) get_query_var( 'cpage' ) > 1;

		if ( Meta::current()['noindex'] || $comment_pages ) {
			$robots['noindex'] = true;
			$robots['follow']  = true;
			unset( $robots['max-image-preview'] );
		}

		return $robots;
	}

	/**
	 * Custom canonical address of a content.
	 *
	 * @param string   $url  Canonical address computed by WordPress.
	 * @param \WP_Post $post Content.
	 */
	public static function canonical( string $url, \WP_Post $post ): string {
		$custom = (string) get_post_meta( $post->ID, '_eo_seo_canonical', true );

		return '' !== $custom ? $custom : $url;
	}

	/**
	 * Tags of the <head>.
	 */
	public static function head(): void {
		$meta = Meta::current();

		if ( '' !== $meta['description'] ) {
			printf( '<meta name="description" content="%s" />' . "\n", esc_attr( $meta['description'] ) );
		}

		// WordPress prints the canonical of contents itself (rel_canonical); archives get theirs here.
		if ( ! is_singular() && ! $meta['noindex'] && '' !== $meta['canonical'] ) {
			printf( '<link rel="canonical" href="%s" />' . "\n", esc_url( $meta['canonical'] ) );
		}

		self::social_tags( $meta );
		self::verification_tags();
	}

	/**
	 * Open Graph and X tags.
	 *
	 * @param array<string, mixed> $meta Metadata of the page.
	 */
	private static function social_tags( array $meta ): void {
		$title       = '' !== $meta['og_title'] ? $meta['og_title'] : $meta['title'];
		$description = '' !== $meta['og_description'] ? $meta['og_description'] : $meta['description'];
		$url         = is_singular() ? wp_get_canonical_url() : $meta['canonical'];

		$tags = array(
			'og:locale'      => get_locale(),
			'og:type'        => $meta['type'],
			'og:site_name'   => get_bloginfo( 'name' ),
			'og:title'       => $title,
			'og:description' => $description,
			'og:url'         => $url ? $url : '',
		);

		if ( $meta['og_image'] ) {
			$image = wp_get_attachment_image_src( (int) $meta['og_image'], 'full' );
			if ( $image ) {
				$tags['og:image']        = $image[0];
				$tags['og:image:width']  = (string) $image[1];
				$tags['og:image:height'] = (string) $image[2];
			}
		}

		if ( 'article' === $meta['type'] && is_singular() ) {
			$tags['article:published_time'] = (string) get_the_date( 'c' );
			$tags['article:modified_time']  = (string) get_the_modified_date( 'c' );
		}

		foreach ( $tags as $property => $content ) {
			if ( '' !== (string) $content ) {
				printf( '<meta property="%s" content="%s" />' . "\n", esc_attr( $property ), esc_attr( (string) $content ) );
			}
		}

		printf( '<meta name="twitter:card" content="%s" />' . "\n", isset( $tags['og:image'] ) ? 'summary_large_image' : 'summary' );
		$username = (string) Settings::get( 'x_username' );
		if ( '' !== $username ) {
			printf( '<meta name="twitter:site" content="@%s" />' . "\n", esc_attr( $username ) );
		}
	}

	/**
	 * Search Console and Bing verification tags, on the home page only.
	 */
	private static function verification_tags(): void {
		if ( ! is_front_page() ) {
			return;
		}

		$codes = (array) Settings::get( 'verification' );
		$names = array(
			'google' => 'google-site-verification',
			'bing'   => 'msvalidate.01',
		);
		foreach ( $names as $key => $name ) {
			if ( ! empty( $codes[ $key ] ) ) {
				printf( '<meta name="%s" content="%s" />' . "\n", esc_attr( $name ), esc_attr( $codes[ $key ] ) );
			}
		}
	}

	/**
	 * Author archives turned off: they redirect to the home page.
	 */
	public static function redirect_author_archives(): void {
		if ( is_author() && ! Settings::get( 'author_archives' ) ) {
			wp_safe_redirect( home_url( '/' ), 301, 'EO SEO' );
			exit;
		}
	}

	/**
	 * Optional cleanup of the <head> and of the feeds.
	 */
	public static function cleanup(): void {
		$cleanup = (array) Settings::get( 'cleanup' );

		if ( ! empty( $cleanup['extra_feeds'] ) ) {
			remove_action( 'wp_head', 'feed_links_extra', 3 );
		}

		if ( ! empty( $cleanup['head_links'] ) ) {
			remove_action( 'wp_head', 'rsd_link' );
			remove_action( 'wp_head', 'wp_shortlink_wp_head' );
			remove_action( 'wp_head', 'wp_generator' );
			remove_action( 'wp_head', 'rest_output_link_wp_head' );
			remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
		}

		if ( ! empty( $cleanup['replytocom'] ) ) {
			add_filter( 'comment_reply_link', array( self::class, 'nofollow_reply_link' ) );
		}
	}

	/**
	 * Keeps the "Reply" links (?replytocom=) out of the index.
	 *
	 * @param string $link HTML link.
	 */
	public static function nofollow_reply_link( string $link ): string {
		return str_contains( $link, 'rel=' ) ? $link : str_replace( '<a ', '<a rel="nofollow" ', $link );
	}
}
