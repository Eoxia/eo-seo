<?php
/**
 * Parsed view of a term (category, tag…), read by the term checks.
 *
 * @package EoSeo
 */

namespace EoSeo;

defined( 'ABSPATH' ) || exit;

/**
 * Resolved metadata and contents of a term archive.
 */
final class Term_Analysis {

	public \WP_Term $term;

	/**
	 * Field values.
	 *
	 * @var array<string, mixed>
	 */
	public array $values;

	public string $seo_title;
	public string $description;
	public string $intro;
	public int $intro_words;
	public int $contents;
	public bool $noindex;

	/**
	 * Builds the analysis of a term.
	 *
	 * @param \WP_Term $term Term.
	 */
	public function __construct( \WP_Term $term ) {
		$this->term        = $term;
		$this->values      = Fields::get_term_values( $term->term_id );
		$this->seo_title   = Meta::term_title( $term );
		$this->description = Meta::term_description( $term );
		$this->noindex     = Meta::term_noindex( $term );
		$this->intro       = trim( wp_strip_all_tags( (string) $term->description ) );
		$this->intro_words = Analysis::count_words( $this->intro );
		$this->contents    = (int) $term->count;
	}
}
