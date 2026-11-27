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

<?php $eo_seo_log = array_slice( EoSeo\Settings::get_log(), 0, 10 ); ?>
<section class="eo-seo-card">
	<header class="eo-seo-card__head">
		<h2 class="eo-seo-card__title"><?php esc_html_e( 'Changes made by the AI', 'eo-seo' ); ?></h2>
		<p><?php esc_html_e( 'Settings changed through EO Tools (eo-seo/update-settings), with their old value. The last 50 are kept.', 'eo-seo' ); ?></p>
	</header>
	<?php if ( ! $eo_seo_log ) : ?>
		<p class="eo-seo-muted"><?php esc_html_e( 'No change yet.', 'eo-seo' ); ?></p>
	<?php else : ?>
		<ul class="eo-seo-review">
			<?php foreach ( $eo_seo_log as $eo_seo_entry ) : ?>
				<?php $eo_seo_user = get_userdata( (int) $eo_seo_entry['user'] ); ?>
				<li class="eo-seo-review__item">
					<span class="eo-seo-review__text">
						<strong>
							<?php
							echo esc_html(
								sprintf(
									/* translators: 1: date, 2: user name. */
									__( '%1$s, by %2$s', 'eo-seo' ),
									wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $eo_seo_entry['time'] ),
									$eo_seo_user ? $eo_seo_user->display_name : __( 'an unknown user', 'eo-seo' )
								)
							);
							?>
						</strong>
						<?php foreach ( $eo_seo_entry['changes'] as $eo_seo_key => $eo_seo_change ) : ?>
							<span><code><?php echo esc_html( $eo_seo_key ); ?></code> : <del><?php echo esc_html( wp_json_encode( $eo_seo_change['before'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ); ?></del> → <ins><?php echo esc_html( wp_json_encode( $eo_seo_change['after'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ); ?></ins></span>
						<?php endforeach; ?>
					</span>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
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
