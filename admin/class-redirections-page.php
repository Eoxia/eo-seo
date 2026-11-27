<?php
/**
 * Screen "Redirections".
 *
 * @package EoSeo
 */

namespace EoSeo\Admin;

use EoSeo\Redirections;

defined( 'ABSPATH' ) || exit;

/**
 * List, add, delete, import and export the redirections.
 */
final class Redirections_Page {

	/**
	 * Rows per page.
	 */
	private const PER_PAGE = 50;

	/**
	 * Renders the screen.
	 */
	public static function render(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Search and pagination.
		$search = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$paged  = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		$prefill_source = isset( $_GET['source'] ) ? sanitize_text_field( wp_unslash( $_GET['source'] ) ) : '';
		// phpcs:enable

		$result  = Redirections::query( $search, $paged, self::PER_PAGE );
		$rows    = $result['rows'];
		$total   = $result['total'];
		$pages   = (int) ceil( $total / self::PER_PAGE );
		$origins = array(
			'manual' => __( 'Manual', 'eo-seo' ),
			'404'    => __( '404 log', 'eo-seo' ),
			'slug'   => __( 'Address change', 'eo-seo' ),
			'ai'     => __( 'AI proposal', 'eo-seo' ),
			'import' => __( 'Import', 'eo-seo' ),
		);

		include EO_SEO_DIR . 'admin/views/redirections.php';
	}
}
