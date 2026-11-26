<?php
/**
 * View: settings, "Tools" section.
 *
 * @package EoSeo
 *
 * @var array<string, mixed> $settings Settings.
 * @var string               $option   Option name.
 */

defined( 'ABSPATH' ) || exit;
?>
<section class="eo-seo-card">
	<header class="eo-seo-card__head">
		<h2 class="eo-seo-card__title"><?php esc_html_e( '404 log', 'eo-seo' ); ?></h2>
		<p><?php esc_html_e( 'Addresses without a new view during this time are removed from the log.', 'eo-seo' ); ?></p>
	</header>
	<div class="eo-seo-input-suffix">
		<label class="screen-reader-text" for="eo-seo-retention"><?php esc_html_e( '404 log retention', 'eo-seo' ); ?></label>
		<input type="number" id="eo-seo-retention" class="eo-seo-input eo-seo-input--number" min="7" max="365" name="<?php echo esc_attr( $option ); ?>[not_found_retention]" value="<?php echo esc_attr( (string) $settings['not_found_retention'] ); ?>" />
		<span><?php esc_html_e( 'days', 'eo-seo' ); ?></span>
	</div>
</section>

<section class="eo-seo-card eo-seo-card--danger">
	<header class="eo-seo-card__head">
		<h2 class="eo-seo-card__title"><?php esc_html_e( 'Uninstallation', 'eo-seo' ); ?></h2>
	</header>
	<?php
	EoSeo\Admin\Ui::toggle(
		$option . '[delete_data]',
		(bool) $settings['delete_data'],
		__( 'Delete all EO SEO data when the plugin is deleted', 'eo-seo' ),
		__( 'SEO fields, redirections, 404 log and settings. Cannot be undone.', 'eo-seo' )
	);
	?>
</section>
