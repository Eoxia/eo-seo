<?php
/**
 * Screen "Dashboard".
 *
 * @package EoSeo
 */

namespace EoSeo\Admin;

use EoSeo\Checks;
use EoSeo\Site_Audit;
use EoSeo\Status;

defined( 'ABSPATH' ) || exit;

/**
 * SEO review of the site, with a link to fix each point.
 */
final class Dashboard_Page {

	/**
	 * Renders the screen.
	 */
	public static function render(): void {
		$items  = Site_Audit::run();
		$groups = array(
			'settings' => __( 'Settings', 'eo-seo' ),
			'indexing' => __( 'Indexing', 'eo-seo' ),
			'contents' => __( 'Contents', 'eo-seo' ),
		);
		$levels = Status::count_by_level();
		$labels = Checks::levels();
		$score  = array(
			'good'  => count( array_filter( $items, static fn( $item ) => 'good' === $item['status'] ) ),
			'total' => count( $items ),
		);

		include EO_SEO_DIR . 'admin/views/dashboard.php';
	}
}
