<?php
/**
 * Screen "404 errors".
 *
 * @package EoSeo
 */

namespace EoSeo\Admin;

use EoSeo\Not_Found_Log;

defined( 'ABSPATH' ) || exit;

/**
 * Missing addresses, most viewed first, with a suggested destination and a quick redirect form.
 */
final class Not_Found_Page {

	/**
	 * Rows per page.
	 */
	private const PER_PAGE = 50;

	/**
	 * Renders the screen.
	 */
	public static function render(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Filter and pagination.
		$status = isset( $_GET['status'] ) && Not_Found_Log::STATUS_DONE === $_GET['status'] ? Not_Found_Log::STATUS_DONE : Not_Found_Log::STATUS_NEW;
		$paged  = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		// phpcs:enable

		$result      = Not_Found_Log::query( $status, $paged, self::PER_PAGE );
		$rows        = $result['rows'];
		$pages       = (int) ceil( $result['total'] / self::PER_PAGE );
		$suggestions = array();
		foreach ( $rows as $row ) {
			$suggestions[ $row->id ] = Not_Found_Log::STATUS_NEW === $status ? Not_Found_Log::suggest_target( $row->url ) : '';
		}

		include EO_SEO_DIR . 'admin/views/not-found.php';
	}
}
