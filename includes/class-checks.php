<?php
/**
 * Registry of the content checks and computation of the indicator.
 *
 * @package EoSeo
 */

namespace EoSeo;

defined( 'ABSPATH' ) || exit;

/**
 * Single source of truth for the checks: used by the per-page indicator, the editor
 * panel, the dashboard and the audit abilities.
 *
 * Severity: "problem" (red when it fails), "improve" (orange), "readability" (orange, never red).
 */
final class Checks {

	public const LEVEL_PROBLEM = 'problem';
	public const LEVEL_IMPROVE = 'improve';
	public const LEVEL_GOOD    = 'good';
	public const LEVEL_NOINDEX = 'noindex';

	/**
	 * Labels of the indicator levels.
	 *
	 * @return array<string, string>
	 */
	public static function levels(): array {
		return array(
			self::LEVEL_PROBLEM => __( 'Problem', 'eo-seo' ),
			self::LEVEL_IMPROVE => __( 'Needs improvement', 'eo-seo' ),
			self::LEVEL_GOOD    => __( 'Good', 'eo-seo' ),
			self::LEVEL_NOINDEX => __( 'Not indexed', 'eo-seo' ),
		);
	}

	/**
	 * Check definitions.
	 *
	 * Each check: label, group, severity, keyword (true: skipped without a focus keyphrase)
	 * and a callback receiving an Analysis and returning [ passed (bool|null: null = not applicable), message ].
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function definitions(): array {
		$fields = Fields::all();

		$checks = array(
			'title_custom'          => array(
				'label'    => __( 'Custom SEO title', 'eo-seo' ),
				'group'    => 'title',
				'severity' => 'improve',
				'callback' => static fn( Analysis $a ) => '' !== (string) $a->values['title']
					? array( true, __( 'The SEO title is written for this page.', 'eo-seo' ) )
					: array( false, __( 'The SEO title comes from the template: write one for this page.', 'eo-seo' ) ),
			),
			'title_length'          => array(
				'label'    => __( 'SEO title length', 'eo-seo' ),
				'group'    => 'title',
				'severity' => 'improve',
				'callback' => static fn( Analysis $a ) => self::length_result( $a->seo_title, $fields['title'] ),
			),
			'title_unique'          => array(
				'label'    => __( 'Unique SEO title', 'eo-seo' ),
				'group'    => 'title',
				'severity' => 'problem',
				'callback' => static function ( Analysis $a ) {
					$other = Duplicates::post_with_title( $a );
					return $other
						/* translators: %s: title of another content. */
						? array( false, sprintf( __( 'Same SEO title as "%s".', 'eo-seo' ), get_the_title( $other ) ) )
						: array( true, __( 'No other content has this SEO title.', 'eo-seo' ) );
				},
			),
			'description_present'   => array(
				'label'    => __( 'Meta description', 'eo-seo' ),
				'group'    => 'description',
				'severity' => 'improve',
				'callback' => static function ( Analysis $a ) {
					if ( '' !== (string) $a->values['description'] ) {
						return array( true, __( 'The meta description is written for this page.', 'eo-seo' ) );
					}
					// Empty field: the template may still print an automatic description (beginning of the text).
					return '' !== $a->description
						? array( false, __( 'Automatic description, taken from the beginning of the text by the template: write one for this page.', 'eo-seo' ) )
						: array( false, __( 'No meta description: search engines will pick a piece of text themselves.', 'eo-seo' ) );
				},
			),
			'description_length'    => array(
				'label'    => __( 'Meta description length', 'eo-seo' ),
				'group'    => 'description',
				'severity' => 'improve',
				// Only a written description is measured: the automatic one is cut to the right length.
				'callback' => static fn( Analysis $a ) => '' === (string) $a->values['description'] ? array( null, '' ) : self::length_result( $a->description, $fields['description'] ),
			),
			'description_unique'    => array(
				'label'    => __( 'Unique meta description', 'eo-seo' ),
				'group'    => 'description',
				'severity' => 'improve',
				'callback' => static function ( Analysis $a ) {
					if ( '' === (string) $a->values['description'] ) {
						return array( null, '' );
					}
					$other = Duplicates::post_with_meta( $a->post, '_eo_seo_description', (string) $a->values['description'] );
					return $other
						/* translators: %s: title of another content. */
						? array( false, sprintf( __( 'Same meta description as "%s".', 'eo-seo' ), get_the_title( $other ) ) )
						: array( true, __( 'No other content has this meta description.', 'eo-seo' ) );
				},
			),
			'keyword_set'           => array(
				'label'    => __( 'Focus keyphrase', 'eo-seo' ),
				'group'    => 'keyword',
				'severity' => 'improve',
				'callback' => static fn( Analysis $a ) => '' !== $a->keyword
					? array( true, __( 'A focus keyphrase is set.', 'eo-seo' ) )
					: array( false, __( 'Set a focus keyphrase to check how well the page targets it.', 'eo-seo' ) ),
			),
			'keyword_unique'        => array(
				'label'    => __( 'Keyphrase not targeted elsewhere', 'eo-seo' ),
				'group'    => 'keyword',
				'severity' => 'problem',
				'keyword'  => true,
				'callback' => static function ( Analysis $a ) {
					$other = Duplicates::post_with_meta( $a->post, '_eo_seo_focus_keyword', $a->keyword );
					return $other
						/* translators: %s: title of another content. */
						? array( false, sprintf( __( '"%s" targets the same keyphrase: the two pages compete in search results.', 'eo-seo' ), get_the_title( $other ) ) )
						: array( true, __( 'No other content targets this keyphrase.', 'eo-seo' ) );
				},
			),
			'keyword_in_title'      => self::keyword_check( __( 'Keyphrase in the SEO title', 'eo-seo' ), static fn( Analysis $a ) => $a->seo_title ),
			'keyword_in_post_title' => self::keyword_check( __( 'Keyphrase in the page title', 'eo-seo' ), static fn( Analysis $a ) => $a->post_title ),
			'keyword_in_description' => self::keyword_check( __( 'Keyphrase in the meta description', 'eo-seo' ), static fn( Analysis $a ) => $a->description ),
			'keyword_in_intro'      => self::keyword_check( __( 'Keyphrase in the introduction', 'eo-seo' ), static fn( Analysis $a ) => $a->intro ),
			'keyword_in_slug'       => array(
				'label'    => __( 'Keyphrase in the address', 'eo-seo' ),
				'group'    => 'keyword',
				'severity' => 'improve',
				'keyword'  => true,
				'callback' => static function ( Analysis $a ) {
					if ( $a->is_front_page ) {
						return array( null, '' );
					}
					return str_contains( $a->slug, sanitize_title( $a->keyword ) )
						? array( true, __( 'The address contains the keyphrase.', 'eo-seo' ) )
						: array( false, __( 'Put the keyphrase in the address (slug).', 'eo-seo' ) );
				},
			),
			'content_not_empty'     => array(
				'label'    => __( 'Content', 'eo-seo' ),
				'group'    => 'content',
				'severity' => 'problem',
				'callback' => static fn( Analysis $a ) => $a->word_count >= 50
					? array( true, __( 'The page has content.', 'eo-seo' ) )
					/* translators: %d: number of words. */
					: array( false, sprintf( _n( 'Almost empty page (%d word).', 'Almost empty page (%d words).', $a->word_count, 'eo-seo' ), $a->word_count ) ),
			),
			'content_length'        => array(
				'label'    => __( 'Text length', 'eo-seo' ),
				'group'    => 'content',
				'severity' => 'improve',
				'callback' => static fn( Analysis $a ) => $a->word_count >= 300
					/* translators: %d: number of words. */
					? array( true, sprintf( __( '%d words.', 'eo-seo' ), $a->word_count ) )
					/* translators: %d: number of words. */
					: array( false, sprintf( __( '%d words: aim for at least 300.', 'eo-seo' ), $a->word_count ) ),
			),
			'canonical'             => array(
				'label'    => __( 'Canonical URL', 'eo-seo' ),
				'group'    => 'content',
				'severity' => 'problem',
				'callback' => static function ( Analysis $a ) {
					$canonical = (string) $a->values['canonical'];
					if ( '' === $canonical ) {
						return array( null, '' );
					}
					return untrailingslashit( $canonical ) === untrailingslashit( (string) get_permalink( $a->post ) )
						? array( true, __( 'The canonical URL is the address of the page.', 'eo-seo' ) )
						: array( false, __( 'The canonical URL points to another page: this page will not appear in search results.', 'eo-seo' ) );
				},
			),
			'h1'                    => array(
				'label'    => __( 'H1 heading', 'eo-seo' ),
				'group'    => 'content',
				'severity' => 'improve',
				'callback' => static function ( Analysis $a ) {
					$h1       = count( array_filter( $a->headings, static fn( $h ) => 1 === $h['level'] ) );
					$template = Template_Headings::for_post( $a->post );

					$in_template = $template['title_h1'] ? array_merge( array( __( 'page title', 'eo-seo' ) ), $template['other_h1'] ) : $template['other_h1'];

					// The templates already print an H1 (page title, site title…): the content must not add one.
					if ( $in_template ) {
						return 0 === $h1
							/* translators: %s: H1 headings of the templates (e.g. "page title, site title"). */
							? array( true, sprintf( __( 'The templates of the site provide the H1 (%s): the content uses lower headings.', 'eo-seo' ), implode( ', ', $in_template ) ) )
							/* translators: %s: H1 headings of the templates (e.g. "page title, site title"). */
							: array( false, sprintf( __( 'Your site already uses the H1 in its templates (%s). Your page must not contain one: use H2 headings.', 'eo-seo' ), implode( ', ', $in_template ) ) );
					}

					// No H1 in the templates: the content brings the only H1 of the page.
					if ( 1 === $h1 ) {
						return array( true, __( 'The content has its H1 heading (the templates of the site have none).', 'eo-seo' ) );
					}

					return 0 === $h1
						? array( false, __( 'The templates of the site have no H1: add one H1 heading at the top of the content.', 'eo-seo' ) )
						/* translators: %d: number of H1 headings. */
						: array( false, sprintf( __( '%d H1 headings in the content: keep only one.', 'eo-seo' ), $h1 ) );
				},
			),
			'images_alt'            => array(
				'label'    => __( 'Alternative texts', 'eo-seo' ),
				'group'    => 'content',
				'severity' => 'improve',
				'callback' => static function ( Analysis $a ) {
					if ( ! $a->images ) {
						return array( null, '' );
					}
					$missing = count( array_filter( $a->images, static fn( $i ) => null === $i['alt'] || '' === trim( $i['alt'] ) ) );
					return 0 === $missing
						? array( true, __( 'Every image has an alternative text.', 'eo-seo' ) )
						/* translators: %d: number of images. */
						: array( false, sprintf( _n( '%d image has no alternative text.', '%d images have no alternative text.', $missing, 'eo-seo' ), $missing ) );
				},
			),
			'internal_links'        => array(
				'label'    => __( 'Internal links', 'eo-seo' ),
				'group'    => 'links',
				'severity' => 'improve',
				'callback' => static function ( Analysis $a ) {
					$count = count( array_filter( $a->links, static fn( $l ) => $l['internal'] ) );
					return $count > 0
						/* translators: %d: number of links. */
						? array( true, sprintf( _n( '%d link to another page of the site.', '%d links to other pages of the site.', $count, 'eo-seo' ), $count ) )
						: array( false, __( 'Add links to other pages of the site.', 'eo-seo' ) );
				},
			),
			'broken_links'          => array(
				'label'    => __( 'Broken or redirected links', 'eo-seo' ),
				'group'    => 'links',
				'severity' => 'improve',
				'callback' => static function ( Analysis $a ) {
					$bad = Link_Checker::bad_internal_links( $a->links );
					return ! $bad
						? array( true, __( 'No internal link to a missing or redirected page.', 'eo-seo' ) )
						/* translators: %s: list of addresses. */
						: array( false, sprintf( __( 'Fix these links: %s', 'eo-seo' ), implode( ', ', $bad ) ) );
				},
			),
			'orphan'                => array(
				'label'    => __( 'Incoming links', 'eo-seo' ),
				'group'    => 'links',
				'severity' => 'improve',
				'callback' => static function ( Analysis $a ) {
					if ( $a->is_front_page || 'publish' !== $a->post->post_status ) {
						return array( null, '' );
					}
					return Link_Checker::has_incoming_link( $a->post )
						? array( true, __( 'Other pages or a menu link to this page.', 'eo-seo' ) )
						: array( false, __( 'Orphan page: no other page or menu links to it.', 'eo-seo' ) );
				},
			),
			'long_sentences'        => array(
				'label'    => __( 'Sentence length', 'eo-seo' ),
				'group'    => 'readability',
				'severity' => 'readability',
				'callback' => static function ( Analysis $a ) {
					$sentences = Analysis::sentences( implode( ' ', $a->paragraphs ) );
					if ( count( $sentences ) < 5 ) {
						return array( null, '' );
					}
					$long  = count( array_filter( $sentences, static fn( $s ) => Analysis::count_words( $s ) > 25 ) );
					$share = (int) round( 100 * $long / count( $sentences ) );
					return $share <= 25
						? array( true, __( 'Sentences are short enough.', 'eo-seo' ) )
						/* translators: %d: percentage. */
						: array( false, sprintf( __( '%d%% of the sentences have more than 25 words: shorten some of them.', 'eo-seo' ), $share ) );
				},
			),
			'long_paragraphs'       => array(
				'label'    => __( 'Paragraph length', 'eo-seo' ),
				'group'    => 'readability',
				'severity' => 'readability',
				'callback' => static function ( Analysis $a ) {
					if ( ! $a->paragraphs ) {
						return array( null, '' );
					}
					$long = count( array_filter( $a->paragraphs, static fn( $p ) => Analysis::count_words( $p ) > 150 ) );
					return 0 === $long
						? array( true, __( 'Paragraphs are short enough.', 'eo-seo' ) )
						/* translators: %d: number of paragraphs. */
						: array( false, sprintf( _n( '%d paragraph has more than 150 words.', '%d paragraphs have more than 150 words.', $long, 'eo-seo' ), $long ) );
				},
			),
			'subheadings'           => array(
				'label'    => __( 'Subheadings', 'eo-seo' ),
				'group'    => 'readability',
				'severity' => 'readability',
				'callback' => static function ( Analysis $a ) {
					if ( $a->word_count < 300 ) {
						return array( null, '' );
					}
					$sections = preg_split( '/<h[2-6]\b/i', $a->html );
					$longest  = max( array_map( static fn( $s ) => Analysis::count_words( wp_strip_all_tags( (string) $s ) ), (array) $sections ) );
					return $longest <= 300
						? array( true, __( 'The text is divided by subheadings.', 'eo-seo' ) )
						: array( false, __( 'A part of the text has more than 300 words without a subheading.', 'eo-seo' ) );
				},
			),
		);

		/**
		 * Filters the content checks.
		 *
		 * @param array<string, array<string, mixed>> $checks Checks keyed by ID.
		 */
		return (array) apply_filters( 'eo_seo_checks', $checks );
	}

	/**
	 * Runs every check on a content and computes the indicator.
	 *
	 * @param Analysis $analysis Content.
	 * @return array{level: string, passed: int, total: int, checks: array<int, array<string, mixed>>}
	 */
	public static function run( Analysis $analysis ): array {
		$results     = array();
		$has_keyword = '' !== $analysis->keyword;
		$failed      = array();

		if ( $analysis->noindex ) {
			return array(
				'level'  => self::LEVEL_NOINDEX,
				'passed' => 0,
				'total'  => 0,
				'checks' => array(),
			);
		}

		foreach ( self::definitions() as $id => $check ) {
			if ( ! empty( $check['keyword'] ) && ! $has_keyword ) {
				continue;
			}

			[ $passed, $message ] = call_user_func( $check['callback'], $analysis );
			if ( null === $passed ) {
				continue;
			}

			$results[] = array(
				'id'       => $id,
				'label'    => $check['label'],
				'group'    => $check['group'],
				'severity' => $check['severity'],
				'passed'   => (bool) $passed,
				'message'  => $message,
			);
			if ( ! $passed ) {
				$failed[] = $check['severity'];
			}
		}

		if ( in_array( 'problem', $failed, true ) ) {
			$level = self::LEVEL_PROBLEM;
		} elseif ( $failed || ! $has_keyword ) {
			// Without a focus keyphrase, the indicator does not go above orange.
			$level = self::LEVEL_IMPROVE;
		} else {
			$level = self::LEVEL_GOOD;
		}

		// Failed checks first, problems before improvements.
		$order = array(
			'problem'     => 0,
			'improve'     => 1,
			'readability' => 2,
		);
		usort(
			$results,
			static fn( $a, $b ) => array( (int) $a['passed'], $order[ $a['severity'] ] ?? 3 ) <=> array( (int) $b['passed'], $order[ $b['severity'] ] ?? 3 )
		);

		return array(
			'level'  => $level,
			'passed' => count( $results ) - count( $failed ),
			'total'  => count( $results ),
			'checks' => $results,
		);
	}

	/**
	 * Result of a length check.
	 *
	 * @param string               $text  Text.
	 * @param array<string, mixed> $field Field with min and max.
	 * @return array{0: bool, 1: string}
	 */
	private static function length_result( string $text, array $field ): array {
		$length = mb_strlen( $text );

		if ( $length < $field['min'] ) {
			/* translators: 1: length, 2: minimum length. */
			return array( false, sprintf( __( '%1$d characters: too short (at least %2$d).', 'eo-seo' ), $length, $field['min'] ) );
		}
		if ( $length > $field['max'] ) {
			/* translators: 1: length, 2: maximum length. */
			return array( false, sprintf( __( '%1$d characters: too long, it will be cut (at most %2$d).', 'eo-seo' ), $length, $field['max'] ) );
		}

		/* translators: %d: length. */
		return array( true, sprintf( __( '%d characters.', 'eo-seo' ), $length ) );
	}

	/**
	 * Definition of a "keyphrase in …" check.
	 *
	 * @param string   $label Label.
	 * @param callable $text  Returns the text to search in.
	 * @return array<string, mixed>
	 */
	private static function keyword_check( string $label, callable $text ): array {
		return array(
			'label'    => $label,
			'group'    => 'keyword',
			'severity' => 'improve',
			'keyword'  => true,
			'callback' => static fn( Analysis $a ) => $a->contains_keyword( (string) $text( $a ) )
				? array( true, __( 'The keyphrase is there.', 'eo-seo' ) )
				: array( false, __( 'The keyphrase is missing.', 'eo-seo' ) ),
		);
	}
}
