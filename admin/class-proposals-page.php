<?php
/**
 * Screen "Proposals".
 *
 * @package EoSeo
 */

namespace EoSeo\Admin;

use EoSeo\Proposals;

defined( 'ABSPATH' ) || exit;

/**
 * Changes proposed by the AI: before / after, apply, reject, apply in batch.
 */
final class Proposals_Page {

	/**
	 * Renders the screen.
	 */
	public static function render(): void {
		$items = array_values( array_filter( Proposals::pending(), array( Proposals::class, 'can_apply' ) ) );

		include EO_SEO_DIR . 'admin/views/proposals.php';
	}
}
