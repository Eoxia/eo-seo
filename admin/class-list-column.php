<?php
/**
 * SEO column of the content and term lists.
 *
 * @package EoSeo
 */

namespace EoSeo\Admin;

use EoSeo\Checks;
use EoSeo\Fields;
use EoSeo\Settings;
use EoSeo\Status;
use EoSeo\Term_Checks;

defined( 'ABSPATH' ) || exit;

/**
 * Indicator badge in the lists (points to fix on hover), and a filter by level.
 */
final class List_Column {

	/**
	 * Hooks.
	 */
	public static function init(): void {
		add_action( 'admin_init', array( self::class, 'register' ) );
		add_action( 'restrict_manage_posts', array( self::class, 'filter_dropdown' ) );
		add_action( 'pre_get_posts', array( self::class, 'filter_query' ) );
		add_filter( 'get_terms_args', array( self::class, 'filter_terms' ), 10, 2 );
		add_action( 'admin_notices', array( self::class, 'terms_filter_notice' ) );
	}

	/**
	 * Adds the column to every handled post type and taxonomy.
	 */
	public static function register(): void {
		foreach ( array_keys( Settings::post_types() ) as $post_type ) {
			add_filter( "manage_{$post_type}_posts_columns", array( self::class, 'columns' ) );
			add_action( "manage_{$post_type}_posts_custom_column", array( self::class, 'cell' ), 10, 2 );
		}

		foreach ( array_keys( Settings::taxonomies() ) as $taxonomy ) {
			add_filter( "manage_edit-{$taxonomy}_columns", array( self::class, 'columns' ) );
			add_filter( "manage_{$taxonomy}_custom_column", array( self::class, 'term_cell' ), 10, 3 );
		}
	}

	/**
	 * Column header.
	 *
	 * @param array<string, string> $columns Columns.
	 * @return array<string, string>
	 */
	public static function columns( array $columns ): array {
		$columns['eo_seo'] = __( 'SEO', 'eo-seo' );

		return $columns;
	}

	/**
	 * Column cell of a content.
	 *
	 * @param string $column  Column.
	 * @param int    $post_id Post ID.
	 */
	public static function cell( string $column, int $post_id ): void {
		if ( 'eo_seo' !== $column ) {
			return;
		}

		static $definitions = null;
		$definitions      ??= Checks::definitions();

		self::badge( Status::get( $post_id ), $definitions, 'eo-seo-tip-' . $post_id, __( 'Save the content to analyze it.', 'eo-seo' ) );
	}

	/**
	 * Column cell of a term.
	 *
	 * @param string $content Content of the cell.
	 * @param string $column  Column.
	 * @param int    $term_id Term ID.
	 */
	public static function term_cell( $content, string $column, int $term_id ): string {
		if ( 'eo_seo' !== $column ) {
			return (string) $content;
		}

		static $definitions = null;
		$definitions      ??= Term_Checks::definitions();

		ob_start();
		self::badge( Status::get_term( $term_id ), $definitions, 'eo-seo-tip-term-' . $term_id, __( 'Save the term, or scan the site from the EO SEO dashboard.', 'eo-seo' ) );

		return (string) ob_get_clean();
	}

	/**
	 * Prints a badge with its tooltip.
	 *
	 * @param array<string, mixed>                $status        Stored indicator.
	 * @param array<string, array<string, mixed>> $definitions   Check definitions (for the labels).
	 * @param string                              $tooltip_id    Tooltip ID.
	 * @param string                              $unknown_hint  Text when the indicator was never computed.
	 */
	private static function badge( array $status, array $definitions, string $tooltip_id, string $unknown_hint ): void {
		static $levels = null;
		$levels      ??= Checks::levels();

		$level  = '' === $status['level'] ? 'unknown' : $status['level'];
		$title  = $levels[ $level ] ?? __( 'Not analyzed yet', 'eo-seo' );
		$issues = array();

		foreach ( array_slice( $status['issues'], 0, 8 ) as $issue ) {
			$issues[] = array(
				'severity' => $issue['severity'],
				'label'    => $definitions[ $issue['id'] ]['label'] ?? '',
				'message'  => $issue['message'],
			);
		}

		$more = max( 0, count( $status['issues'] ) - count( $issues ) );

		include EO_SEO_DIR . 'admin/views/list-badge.php';
	}

	/**
	 * Filter of the content lists.
	 *
	 * @param string $post_type Listed post type.
	 */
	public static function filter_dropdown( string $post_type ): void {
		if ( ! isset( Settings::post_types()[ $post_type ] ) ) {
			return;
		}

		$current = self::requested_level();
		?>
		<label class="screen-reader-text" for="eo-seo-level"><?php esc_html_e( 'Filter by SEO indicator', 'eo-seo' ); ?></label>
		<select name="eo_seo_level" id="eo-seo-level">
			<option value=""><?php esc_html_e( 'All SEO levels', 'eo-seo' ); ?></option>
			<?php foreach ( Checks::levels() as $level => $label ) : ?>
				<option value="<?php echo esc_attr( $level ); ?>" <?php selected( $current, $level ); ?>><?php echo esc_html( $label ); ?></option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	/**
	 * Applies the filter to the content lists.
	 *
	 * @param \WP_Query $query Query.
	 */
	public static function filter_query( \WP_Query $query ): void {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}

		$level = self::requested_level();
		if ( '' !== $level ) {
			$query->set( 'meta_key', Fields::STATUS_META ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Admin list filter.
			$query->set( 'meta_value', $level ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Admin list filter.
		}
	}

	/**
	 * Applies the filter to the term lists (edit-tags.php?eo_seo_level=…, linked from the dashboard).
	 *
	 * @param array<string, mixed> $args       get_terms() arguments.
	 * @param string[]             $taxonomies Taxonomies.
	 * @return array<string, mixed>
	 */
	public static function filter_terms( array $args, array $taxonomies ): array {
		global $pagenow;

		// Only the query of the list table, which is the one with a "page" argument.
		if ( ! is_admin() || 'edit-tags.php' !== $pagenow || ! isset( $args['page'] ) ) {
			return $args;
		}

		$level = self::requested_level();
		if ( '' !== $level && array_intersect( $taxonomies, array_keys( Settings::taxonomies() ) ) ) {
			$args['meta_key']   = Fields::STATUS_META; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Admin list filter.
			$args['meta_value'] = $level; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Admin list filter.
		}

		return $args;
	}

	/**
	 * Term lists have no filter control: a notice says which level is shown, with a link to show all.
	 */
	public static function terms_filter_notice(): void {
		global $pagenow, $taxnow;

		$level = self::requested_level();
		if ( 'edit-tags.php' !== $pagenow || '' === $level || ! isset( Settings::taxonomies()[ $taxnow ] ) ) {
			return;
		}

		wp_admin_notice(
			sprintf(
				/* translators: 1: SEO level, 2: link to show every term. */
				esc_html__( 'Only the terms with the SEO level "%1$s" are shown. %2$s', 'eo-seo' ),
				esc_html( Checks::levels()[ $level ] ),
				'<a href="' . esc_url( remove_query_arg( 'eo_seo_level' ) ) . '">' . esc_html__( 'Show all', 'eo-seo' ) . '</a>'
			),
			array( 'type' => 'info' )
		);
	}

	/**
	 * Level requested in the address of a list.
	 *
	 * @return string Empty when none or unknown.
	 */
	private static function requested_level(): string {
		$level = isset( $_GET['eo_seo_level'] ) ? sanitize_key( wp_unslash( $_GET['eo_seo_level'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- List filter.

		return isset( Checks::levels()[ $level ] ) ? $level : '';
	}
}
