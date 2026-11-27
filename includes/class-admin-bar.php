<?php
/**
 * SEO badge in the admin bar of the front end.
 *
 * @package EoSeo
 */

namespace EoSeo;

defined( 'ABSPATH' ) || exit;

/**
 * On a page of the site, people who can edit it see its SEO level in the admin bar,
 * and the points to fix, the SEO title and the meta description in its menu.
 * Visitors get nothing: no script, and the few styles are added to the admin bar ones.
 */
final class Admin_Bar {

	/**
	 * Failed checks shown in the menu.
	 */
	private const MAX_ISSUES = 10;

	/**
	 * Hooks.
	 */
	public static function init(): void {
		add_action( 'template_redirect', array( self::class, 'capture' ), 99 );
		add_action( 'admin_bar_menu', array( self::class, 'menu' ), 90 );
		add_action( 'wp_enqueue_scripts', array( self::class, 'styles' ) );
	}

	/**
	 * Page subject, captured before the page is rendered.
	 *
	 * @var array{type: string, object: \WP_Post|\WP_Term}|null
	 */
	private static ?array $subject = null;

	/**
	 * Captures the content or term of the page before the rendering: the admin bar is built at the
	 * end of the page, when the queries of the template (query loops, related posts…) may have
	 * replaced the main one.
	 */
	public static function capture(): void {
		self::$subject = self::subject();
	}

	/**
	 * Content or term displayed, when the current user can edit it.
	 *
	 * @return array{type: string, object: \WP_Post|\WP_Term}|null
	 */
	private static function subject(): ?array {
		if ( null !== self::$subject ) {
			return self::$subject;
		}
		if ( is_admin() || ! is_admin_bar_showing() ) {
			return null;
		}

		$object = get_queried_object();
		if ( $object instanceof \WP_Post && ( is_singular() || is_home() ) && isset( Settings::post_types()[ $object->post_type ] ) && current_user_can( 'edit_post', $object->ID ) ) {
			return array(
				'type'   => 'post',
				'object' => $object,
			);
		}
		if ( $object instanceof \WP_Term && isset( Settings::taxonomies()[ $object->taxonomy ] ) && current_user_can( 'edit_term', $object->term_id ) ) {
			return array(
				'type'   => 'term',
				'object' => $object,
			);
		}

		return null;
	}

	/**
	 * Adds the badge and its menu.
	 *
	 * @param \WP_Admin_Bar $bar Admin bar.
	 */
	public static function menu( \WP_Admin_Bar $bar ): void {
		$subject = self::subject();
		if ( ! $subject ) {
			return;
		}

		// Computed now, from what the page shows (the stored indicator may be older).
		if ( 'post' === $subject['type'] ) {
			$analysis    = new Analysis( $subject['object'] );
			$result      = Checks::run( $analysis );
			$title       = $analysis->seo_title;
			$description = $analysis->description;
			$edit        = (string) get_edit_post_link( $subject['object']->ID, 'raw' );
		} else {
			$analysis    = new Term_Analysis( $subject['object'] );
			$result      = Term_Checks::run( $analysis );
			$title       = $analysis->seo_title;
			$description = $analysis->description;
			$edit        = (string) get_edit_term_link( $subject['object']->term_id, $subject['object']->taxonomy );
		}

		$levels = Checks::levels();
		$label  = 'noindex' === $result['level']
			? $levels['noindex']
			/* translators: %s: points, as "17/23". */
			: sprintf( __( 'SEO %s', 'eo-seo' ), $result['passed'] . '/' . $result['total'] );

		$bar->add_node(
			array(
				'id'    => 'eo-seo',
				'title' => '<span class="eo-seo-ab-dot eo-seo-ab-dot--' . esc_attr( $result['level'] ) . '" aria-hidden="true"></span><span class="ab-label">' . esc_html( $label ) . '</span>',
				'href'  => $edit,
				'meta'  => array(
					/* translators: %s: SEO level (Good, Needs improvement…). */
					'title' => sprintf( __( 'EO SEO: %s', 'eo-seo' ), $levels[ $result['level'] ] ?? '' ),
					'class' => 'eo-seo-ab eo-seo-ab--' . $result['level'],
				),
			)
		);

		$failed = array_values( array_filter( $result['checks'], static fn( $check ) => ! $check['passed'] ) );
		$bar->add_group(
			array(
				'parent' => 'eo-seo',
				'id'     => 'eo-seo-checks',
			)
		);

		if ( 'noindex' === $result['level'] ) {
			self::line( $bar, 'eo-seo-checks', 'eo-seo-noindex', '', __( 'Hidden from search engines: nothing to check.', 'eo-seo' ) );
		} elseif ( ! $failed ) {
			self::line( $bar, 'eo-seo-checks', 'eo-seo-all-good', 'ok', __( 'Every check passes.', 'eo-seo' ) );
		}

		foreach ( array_slice( $failed, 0, self::MAX_ISSUES ) as $check ) {
			self::line( $bar, 'eo-seo-checks', 'eo-seo-check-' . $check['id'], $check['severity'], $check['message'], $check['label'] );
		}
		if ( count( $failed ) > self::MAX_ISSUES ) {
			$more = count( $failed ) - self::MAX_ISSUES;
			/* translators: %d: number of other points to fix. */
			self::line( $bar, 'eo-seo-checks', 'eo-seo-more', '', sprintf( _n( 'And %d other point: open it to see them all.', 'And %d other points: open it to see them all.', $more, 'eo-seo' ), $more ) );
		}

		// What search engines get.
		$bar->add_group(
			array(
				'parent' => 'eo-seo',
				'id'     => 'eo-seo-meta',
				'meta'   => array( 'class' => 'ab-sub-secondary' ),
			)
		);
		self::line( $bar, 'eo-seo-meta', 'eo-seo-title', '', '' !== $title ? $title : __( '(empty)', 'eo-seo' ), __( 'SEO title', 'eo-seo' ) );
		self::line( $bar, 'eo-seo-meta', 'eo-seo-description', '', '' !== $description ? $description : __( '(empty)', 'eo-seo' ), __( 'Meta description', 'eo-seo' ) );

		if ( '' !== $edit ) {
			$bar->add_node(
				array(
					'parent' => 'eo-seo-meta',
					'id'     => 'eo-seo-edit',
					'title'  => esc_html__( 'Edit the SEO', 'eo-seo' ),
					'href'   => $edit,
				)
			);
		}
	}

	/**
	 * Adds a text line to the menu.
	 *
	 * @param \WP_Admin_Bar $bar      Admin bar.
	 * @param string        $parent   Parent group.
	 * @param string        $id       Node ID.
	 * @param string        $severity problem, improve, readability, ok or empty (no dot).
	 * @param string        $text     Text.
	 * @param string        $label    Bold label before the text.
	 */
	private static function line( \WP_Admin_Bar $bar, string $parent, string $id, string $severity, string $text, string $label = '' ): void {
		$html = '' !== $severity ? '<span class="eo-seo-ab-dot eo-seo-ab-dot--' . esc_attr( $severity ) . '" aria-hidden="true"></span>' : '';
		if ( '' !== $label ) {
			$html .= '<strong>' . esc_html( $label ) . '</strong> ';
		}

		$bar->add_node(
			array(
				'parent' => $parent,
				'id'     => $id,
				'title'  => '<span class="eo-seo-ab-line">' . $html . esc_html( $text ) . '</span>',
			)
		);
	}

	/**
	 * Styles of the badge, added to the admin bar styles (no extra file, nothing for visitors).
	 */
	public static function styles(): void {
		if ( ! self::subject() ) {
			return;
		}

		$css = '
			#wpadminbar .eo-seo-ab > .ab-item { display: flex; align-items: center; gap: 6px; }
			#wpadminbar .eo-seo-ab-dot { display: inline-block; flex: none; width: 10px; height: 10px; border-radius: 50%; background: #a7aaad; vertical-align: middle; }
			#wpadminbar .eo-seo-ab > .ab-item .eo-seo-ab-dot { width: 12px; height: 12px; box-shadow: 0 0 0 2px rgba(255,255,255,.25); }
			#wpadminbar .eo-seo-ab-dot--problem { background: #d63638; }
			#wpadminbar .eo-seo-ab-dot--improve, #wpadminbar .eo-seo-ab-dot--readability { background: #dba617; }
			#wpadminbar .eo-seo-ab-dot--good, #wpadminbar .eo-seo-ab-dot--ok { background: #00a32a; }
			#wpadminbar #wp-admin-bar-eo-seo .ab-submenu { width: 420px; max-width: 90vw; }
			#wpadminbar #wp-admin-bar-eo-seo .ab-submenu .ab-item { height: auto; min-height: 26px; padding-top: 4px; padding-bottom: 4px; line-height: 18px; white-space: normal; }
			#wpadminbar .eo-seo-ab-line { display: block; }
			#wpadminbar .eo-seo-ab-line .eo-seo-ab-dot { margin-right: 6px; }
			#wpadminbar .eo-seo-ab-line strong { font-weight: 600; }
			@media screen and (max-width: 782px) {
				#wpadminbar #wp-admin-bar-eo-seo { display: block; }
				#wpadminbar .eo-seo-ab > .ab-item { padding: 0 8px; }
				#wpadminbar .eo-seo-ab .ab-label { display: none; }
				#wpadminbar .eo-seo-ab > .ab-item .eo-seo-ab-dot { width: 16px; height: 16px; margin: 15px 0; }
			}
		';

		wp_add_inline_style( 'admin-bar', (string) preg_replace( '/\s+/', ' ', $css ) );
	}
}
