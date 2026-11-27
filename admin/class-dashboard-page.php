<?php
/**
 * Screen "Dashboard".
 *
 * @package EoSeo
 */

namespace EoSeo\Admin;

use EoSeo\Checks;
use EoSeo\Site_Audit;
use EoSeo\Site_Scan;
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
		$scan   = Site_Scan::state();
		$labels = Checks::levels();
		$statuses = array_column( $items, 'status' );
		$score    = array(
			'good'    => count( array_keys( $statuses, 'good', true ) ),
			'total'   => count( $items ),
			// Color of the bar: the worst point of the review, like the badges.
			'level'   => in_array( 'error', $statuses, true ) ? 'problem' : ( in_array( 'warning', $statuses, true ) ? 'improve' : 'good' ),
			'percent' => 0,
		);
		if ( $score['total'] ) {
			$score['percent'] = (int) round( 100 * $score['good'] / $score['total'] );
		}

		include EO_SEO_DIR . 'admin/views/dashboard.php';
	}
}
