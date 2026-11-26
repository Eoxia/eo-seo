<?php
/**
 * SEO column of the content lists.
 *
 * @package EoSeo
 */

namespace EoSeo\Admin;

use EoSeo\Checks;
use EoSeo\Fields;
use EoSeo\Settings;
use EoSeo\Status;

defined( 'ABSPATH' ) || exit;

/**
 * Indicator badge in the lists, and a filter "SEO: problem / to improve…".
 */
final class List_Column {

	/**
	 * Hooks.
	 */
	public static function init(): void {
		add_action( 'admin_init', array( self::class, 'register' ) );
		add_action( 'restrict_manage_posts', array( self::class, 'filter_dropdown' ) );
		add_action( 'pre_get_posts', array( self::class, 'filter_query' ) );
	}

	/**
	 * Adds the column to every handled post type.
	 */
	public static function register(): void {
		foreach ( array_keys( Settings::post_types() ) as $post_type ) {
			add_filter( "manage_{$post_type}_posts_columns", array( self::class, 'columns' ) );
			add_action( "manage_{$post_type}_posts_custom_column", array( self::class, 'cell' ), 10, 2 );
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
	 * Column cell.
	 *
	 * @param string $column  Column.
	 * @param int    $post_id Post ID.
	 */
	public static function cell( string $column, int $post_id ): void {
		if ( 'eo_seo' !== $column ) {
			return;
		}

		static $definitions = null, $levels = null;
		$definitions ??= Checks::definitions();
		$levels      ??= Checks::levels();

		$status      = Status::get( $post_id );
		$level       = '' === $status['level'] ? 'unknown' : $status['level'];
		$title       = $levels[ $level ] ?? __( 'Not analyzed yet', 'eo-seo' );
		$tooltip_id  = 'eo-seo-tip-' . $post_id;
		$issues      = array();

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
	 * Filter of the list.
	 *
	 * @param string $post_type Listed post type.
	 */
	public static function filter_dropdown( string $post_type ): void {
		if ( ! isset( Settings::post_types()[ $post_type ] ) ) {
			return;
		}

		$current = isset( $_GET['eo_seo_level'] ) ? sanitize_key( wp_unslash( $_GET['eo_seo_level'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- List filter.
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
	 * Applies the filter.
	 *
	 * @param \WP_Query $query Query.
	 */
	public static function filter_query( \WP_Query $query ): void {
		if ( ! is_admin() || ! $query->is_main_query() || empty( $_GET['eo_seo_level'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- List filter.
			return;
		}

		$level = sanitize_key( wp_unslash( $_GET['eo_seo_level'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- List filter.
		if ( isset( Checks::levels()[ $level ] ) ) {
			$query->set( 'meta_key', Fields::STATUS_META ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Admin list filter.
			$query->set( 'meta_value', $level ); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Admin list filter.
		}
	}
}
