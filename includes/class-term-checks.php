<?php
/**
 * Checks of the terms (categories, tags, public taxonomies).
 *
 * @package EoSeo
 */

namespace EoSeo;

defined( 'ABSPATH' ) || exit;

/**
 * Registry of the term checks. Same levels and rules as the contents (Checks::evaluate()).
 */
final class Term_Checks {

	/**
	 * Below this number of contents, an indexed archive is thin: with a single content,
	 * it repeats that content. Google gives no figure; an empty archive is the real problem
	 * (Google advises a 404 or a noindex for it).
	 */
	public const MIN_CONTENTS = 2;

	/**
	 * Below this number of words, the introduction text of an archive is short. Google gives no
	 * figure and advises against long texts on listing pages; 30 words is the threshold of Yoast SEO.
	 */
	public const MIN_INTRO_WORDS = 30;

	/**
	 * Check definitions (same format as Checks::definitions(), callbacks receive a Term_Analysis).
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function definitions(): array {
		$fields = Fields::all();

		$checks = array(
			'term_contents'    => array(
				'label'    => __( 'Contents of the archive', 'eo-seo' ),
				'group'    => 'content',
				'severity' => 'improve',
				'callback' => static function ( Term_Analysis $a ) {
					if ( 0 === $a->contents ) {
						return array( null, '' ); // Reported by "Empty archive", as a problem.
					}
					return $a->contents >= self::MIN_CONTENTS
						/* translators: %d: number of contents. */
						? array( true, sprintf( _n( '%d content in this archive.', '%d contents in this archive.', $a->contents, 'eo-seo' ), $a->contents ) )
						/* translators: 1: number of contents, 2: minimum number of contents. */
						: array( false, sprintf( _n( 'Thin archive: %1$d content (at least %2$d recommended), or hide it (noindex).', 'Thin archive: %1$d contents (at least %2$d recommended), or hide it (noindex).', $a->contents, 'eo-seo' ), $a->contents, self::MIN_CONTENTS ) );
				},
			),
			'term_empty'       => array(
				'label'    => __( 'Empty archive', 'eo-seo' ),
				'group'    => 'content',
				'severity' => 'problem',
				'callback' => static fn( Term_Analysis $a ) => 0 === $a->contents
					? array( false, __( 'This archive has no content: search engines see an empty page. Add contents, or hide it (noindex).', 'eo-seo' ) )
					: array( null, '' ),
			),
			'term_intro'       => array(
				'label'    => __( 'Introduction text', 'eo-seo' ),
				'group'    => 'content',
				'severity' => 'improve',
				'callback' => static function ( Term_Analysis $a ) {
					if ( 0 === $a->intro_words ) {
						return array( false, __( 'No description: write an introduction text, shown at the top of the archive by most themes.', 'eo-seo' ) );
					}
					return $a->intro_words >= self::MIN_INTRO_WORDS
						/* translators: %d: number of words. */
						? array( true, sprintf( __( 'Introduction text of %d words.', 'eo-seo' ), $a->intro_words ) )
						/* translators: 1: number of words, 2: minimum number of words. */
						: array( false, sprintf( __( 'Short introduction text (%1$d words): aim for at least %2$d.', 'eo-seo' ), $a->intro_words, self::MIN_INTRO_WORDS ) );
				},
			),
			'term_description' => array(
				'label'    => __( 'Meta description', 'eo-seo' ),
				'group'    => 'description',
				'severity' => 'improve',
				'callback' => static function ( Term_Analysis $a ) {
					if ( '' !== (string) $a->values['description'] ) {
						return array( true, __( 'The meta description is written for this archive.', 'eo-seo' ) );
					}
					return '' !== $a->description
						? array( true, __( 'Automatic description, taken from the description of the term (Google accepts it). A description written for search results often makes people click more.', 'eo-seo' ) )
						: array( false, __( 'No meta description: search engines will pick a piece of text themselves.', 'eo-seo' ) );
				},
			),
			'term_title_length' => array(
				'label'    => __( 'SEO title length', 'eo-seo' ),
				'group'    => 'title',
				'severity' => 'improve',
				'callback' => static fn( Term_Analysis $a ) => Checks::length_result( $a->seo_title, $fields['title'] ),
			),
			'term_title_unique' => array(
				'label'    => __( 'Unique SEO title', 'eo-seo' ),
				'group'    => 'title',
				'severity' => 'problem',
				'callback' => static function ( Term_Analysis $a ) {
					$other = self::term_with_same_title( $a );
					return $other
						/* translators: 1: term name, 2: taxonomy name. */
						? array( false, sprintf( __( 'Same SEO title as "%1$s" (%2$s).', 'eo-seo' ), $other->name, self::taxonomy_label( $other->taxonomy ) ) )
						: array( true, __( 'No other archive has this SEO title.', 'eo-seo' ) );
				},
			),
		);

		$checks = Checks::classify( $checks, array( 'term_empty', 'term_title_unique', 'term_description' ), array() );

		/**
		 * Filters the term checks.
		 *
		 * @param array<string, array<string, mixed>> $checks Checks keyed by ID.
		 */
		return (array) apply_filters( 'eo_seo_term_checks', $checks );
	}

	/**
	 * Runs every check on a term and computes its indicator.
	 *
	 * @param Term_Analysis $analysis Term.
	 * @return array{level: string, passed: int, total: int, checks: array<int, array<string, mixed>>}
	 */
	public static function run( Term_Analysis $analysis ): array {
		return $analysis->noindex ? Checks::noindex_result() : Checks::evaluate( self::definitions(), $analysis );
	}

	/**
	 * Another indexed term with the same resolved SEO title.
	 *
	 * @param Term_Analysis $analysis Term.
	 */
	public static function term_with_same_title( Term_Analysis $analysis ): ?\WP_Term {
		foreach ( self::candidates( $analysis->term ) as $term ) {
			if ( ! Meta::term_noindex( $term ) && Analysis::normalize( Meta::term_title( $term ) ) === Analysis::normalize( $analysis->seo_title ) ) {
				return $term;
			}
		}

		return null;
	}

	/**
	 * Terms that may share the SEO title of a term: same custom SEO title, or same name.
	 *
	 * @param \WP_Term $term Term.
	 * @return \WP_Term[]
	 */
	public static function candidates( \WP_Term $term ): array {
		global $wpdb;

		$ids    = array();
		$custom = (string) get_term_meta( $term->term_id, '_eo_seo_title', true );
		if ( '' !== $custom ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Run when a term is saved or analyzed, on indexed columns.
			$ids = $wpdb->get_col( $wpdb->prepare( "SELECT term_id FROM {$wpdb->termmeta} WHERE meta_key = '_eo_seo_title' AND meta_value = %s AND term_id <> %d LIMIT 20", $custom, $term->term_id ) );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Run when a term is saved or analyzed, on indexed columns.
		$same_name = $wpdb->get_col( $wpdb->prepare( "SELECT term_id FROM {$wpdb->terms} WHERE name = %s AND term_id <> %d LIMIT 20", $term->name, $term->term_id ) );

		$terms = array();
		foreach ( array_unique( array_merge( $ids, $same_name ) ) as $id ) {
			$other = get_term( (int) $id );
			if ( $other instanceof \WP_Term && isset( Settings::taxonomies()[ $other->taxonomy ] ) ) {
				$terms[] = $other;
			}
		}

		return $terms;
	}

	/**
	 * Singular label of a taxonomy.
	 *
	 * @param string $taxonomy Taxonomy.
	 */
	private static function taxonomy_label( string $taxonomy ): string {
		$object = get_taxonomy( $taxonomy );

		return $object ? (string) $object->labels->singular_name : $taxonomy;
	}
}
