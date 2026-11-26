<?php
/**
 * Uninstallation: data is deleted only if the administrator asked for it (Settings › Tools).
 *
 * @package EoSeo
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * Deletes the data of the current site when "Delete all EO SEO data" is checked.
 */
function eo_seo_uninstall_site(): void {
	global $wpdb;

	$settings = get_option( 'eo_seo_settings', array() );
	if ( empty( $settings['delete_data'] ) ) {
		return;
	}

	// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Uninstallation: drops the plugin tables and metas.
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}eo_seo_redirections, {$wpdb->prefix}eo_seo_404" );
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE %s", $wpdb->esc_like( '_eo_seo_' ) . '%' ) );
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->termmeta} WHERE meta_key LIKE %s", $wpdb->esc_like( '_eo_seo_' ) . '%' ) );
	$wpdb->query( $wpdb->prepare( "DELETE p, m FROM {$wpdb->posts} p LEFT JOIN {$wpdb->postmeta} m ON m.post_id = p.ID WHERE p.post_type = %s", 'eo_seo_proposal' ) );
	// phpcs:enable

	foreach ( array( 'eo_seo_settings', 'eo_seo_db_version', 'eo_seo_redirections_count', 'eo_seo_scan' ) as $option ) {
		delete_option( $option );
	}
	delete_transient( 'eo_seo_404_count' );
	wp_clear_scheduled_hook( 'eo_seo_daily' );
	wp_clear_scheduled_hook( 'eo_seo_scan_batch' );
}

if ( is_multisite() ) {
	foreach ( get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	) as $eo_seo_site_id ) {
		switch_to_blog( (int) $eo_seo_site_id );
		eo_seo_uninstall_site();
		restore_current_blog();
	}
} else {
	eo_seo_uninstall_site();
}
