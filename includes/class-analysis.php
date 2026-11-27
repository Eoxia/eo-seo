<?php
/**
 * Parsed view of a content, read by the checks.
 *
 * @package EoSeo
 */

namespace EoSeo;

defined( 'ABSPATH' ) || exit;

/**
 * Everything the checks need about one content, computed once: resolved metadata,
 * text, headings, images, links. Works on the stored content or on unsaved editor values.
 */
final class Analysis {

	public \WP_Post $post;

	/**
	 * Field values.
	 *
	 * @var array<string, mixed>
	 */
	public array $values;

	public string $seo_title;
	public string $description;
	public string $post_title;
	public string $slug;
	public string $keyword;
	public string $html;
	public string $text;
	public string $intro;
	public int $word_count;
	public bool $noindex;
	public bool $is_front_page;

	/**
	 * Headings: level and text.
	 *
	 * @var array<int, array{level: int, text: string}>
	 */
	public array $headings = array();

	/**
	 * Images: src and alt (null when the attribute is missing).
	 *
	 * @var array<int, array{src: string, alt: ?string}>
	 */
	public array $images = array();

	/**
	 * Links: href and whether they point to this site.
	 *
	 * @var array<int, array{href: string, internal: bool}>
	 */
	public array $links = array();

	/**
	 * Paragraphs, as plain text.
	 *
	 * @var string[]
	 */
	public array $paragraphs = array();

	/**
	 * Builds the analysis of a content.
	 *
	 * @param \WP_Post             $post      Content.
	 * @param array<string, mixed> $overrides Unsaved values from the editor: title, slug, content and fields.
	 */
	public function __construct( \WP_Post $post, array $overrides = array() ) {
		$post = clone $post;
		if ( isset( $overrides['title'] ) ) {
			$post->post_title = (string) $overrides['title'];
		}
		if ( isset( $overrides['slug'] ) ) {
			$post->post_name = sanitize_title( (string) $overrides['slug'] );
		}
		if ( isset( $overrides['content'] ) ) {
			$post->post_content = (string) $overrides['content'];
		}
		if ( isset( $overrides['excerpt'] ) ) {
			$post->post_excerpt = (string) $overrides['excerpt'];
		}

		$values = Fields::get_post_values( $post->ID );
		foreach ( (array) ( $overrides['fields'] ?? array() ) as $name => $value ) {
			$fields = Fields::all();
			if ( isset( $fields[ $name ] ) ) {
				$values[ $name ] = Fields::sanitize( $fields[ $name ], $value );
			}
		}

		$this->post          = $post;
		$this->values        = $values;
		$this->seo_title     = Meta::post_title( $post, $values );
		$this->description   = Meta::post_description( $post, $values );
		$this->noindex       = Meta::post_noindex( $post, $values );
		$this->post_title    = wp_strip_all_tags( $post->post_title );
		$this->slug          = rawurldecode( $post->post_name );
		$this->keyword       = trim( (string) $values['focus_keyword'] );
		$this->is_front_page = (int) get_option( 'page_on_front' ) === $post->ID;
		$this->html          = (string) preg_replace( '/<!--.*?-->/s', '', $post->post_content );

		$this->parse();
	}

	/**
	 * Lower-case, accent-free text, for comparisons.
	 *
	 * @param string $text Text.
	 */
	public static function normalize( string $text ): string {
		return trim( (string) preg_replace( '/\s+/u', ' ', remove_accents( mb_strtolower( wp_strip_all_tags( $text ) ) ) ) );
	}

	/**
	 * Whether the focus keyphrase appears in a text.
	 *
	 * @param string $text Text.
	 */
	public function contains_keyword( string $text ): bool {
		return '' !== $this->keyword && str_contains( self::normalize( $text ), self::normalize( $this->keyword ) );
	}

	/**
	 * Number of words of a text (any language using spaces).
	 *
	 * @param string $text Plain text.
	 */
	public static function count_words( string $text ): int {
		return (int) preg_match_all( '/[\p{L}\p{N}][\p{L}\p{N}\'’-]*/u', $text );
	}

	/**
	 * Sentences of a text.
	 *
	 * @param string $text Plain text.
	 * @return string[]
	 */
	public static function sentences( string $text ): array {
		return array_values( array_filter( array_map( 'trim', (array) preg_split( '/(?<=[.!?…])\s+/u', $text ) ) ) );
	}

	/**
	 * Extracts headings, images, links and paragraphs.
	 */
	private function parse(): void {
		$html = $this->html;

		preg_match_all( '/<h([1-6])\b[^>]*>(.*?)<\/h\1>/is', $html, $matches, PREG_SET_ORDER );
		foreach ( $matches as $match ) {
			$this->headings[] = array(
				'level' => (int) $match[1],
				'text'  => trim( wp_strip_all_tags( $match[2] ) ),
			);
		}

		preg_match_all( '/<img\b[^>]*>/i', $html, $matches );
		foreach ( $matches[0] as $tag ) {
			$this->images[] = array(
				'src' => self::attribute( $tag, 'src' ) ?? '',
				'alt' => self::attribute( $tag, 'alt' ),
			);
		}

		$home_host = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		preg_match_all( '/<a\b[^>]*>/i', $html, $matches );
		foreach ( $matches[0] as $tag ) {
			$href = (string) self::attribute( $tag, 'href' );
			if ( '' === $href || str_starts_with( $href, '#' ) || preg_match( '/^(mailto|tel|javascript):/i', $href ) ) {
				continue;
			}
			$host          = (string) wp_parse_url( $href, PHP_URL_HOST );
			$this->links[] = array(
				'href'     => $href,
				'internal' => '' === $host || strtolower( $host ) === strtolower( $home_host ),
			);
		}

		preg_match_all( '/<p\b[^>]*>(.*?)<\/p>/is', $html, $matches );
		foreach ( $matches[1] as $paragraph ) {
			$text = trim( html_entity_decode( wp_strip_all_tags( $paragraph ), ENT_QUOTES, 'UTF-8' ) );
			if ( '' !== $text ) {
				$this->paragraphs[] = $text;
			}
		}

		$this->text       = trim( (string) preg_replace( '/\s+/u', ' ', html_entity_decode( wp_strip_all_tags( str_replace( '<', ' <', $html ) ), ENT_QUOTES, 'UTF-8' ) ) );
		$this->word_count = self::count_words( $this->text );
		$this->intro      = $this->paragraphs[0] ?? wp_trim_words( $this->text, 60, '' );
	}

	/**
	 * Value of an HTML attribute.
	 *
	 * @param string $tag  HTML tag.
	 * @param string $name Attribute name.
	 * @return string|null Null when the attribute is missing.
	 */
	private static function attribute( string $tag, string $name ): ?string {
		if ( preg_match( '/\s' . $name . '\s*=\s*(["\'])(.*?)\1/is', $tag, $match ) ) {
			return html_entity_decode( $match[2], ENT_QUOTES, 'UTF-8' );
		}

		return null;
	}
}
