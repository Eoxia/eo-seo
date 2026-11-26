<?php
/**
 * View: dashboard.
 *
 * @package EoSeo
 *
 * @var array<int, array<string, string>> $items  Review items.
 * @var array<string, string>             $groups Group labels.
 * @var array<string, int>                $levels Contents per level.
 * @var array<string, string>             $labels Level labels.
 * @var array<string, int>                $score  Review points: good, total.
 */

defined( 'ABSPATH' ) || exit;

$eo_seo_status_labels = array(
	'good'    => __( 'Good', 'eo-seo' ),
	'warning' => __( 'To improve', 'eo-seo' ),
	'error'   => __( 'To fix', 'eo-seo' ),
);
?>
<div class="wrap eo-seo-app">
	<?php EoSeo\Admin\Ui::header( 'eo-seo', __( 'SEO dashboard', 'eo-seo' ), __( 'The SEO review of the whole site. Each point links to where you fix it.', 'eo-seo' ) ); ?>

	<div class="eo-seo-grid eo-seo-grid--stats">
		<div class="eo-seo-card eo-seo-stat eo-seo-stat--score">
			<span class="eo-seo-stat__label"><?php esc_html_e( 'Site review', 'eo-seo' ); ?></span>
			<span class="eo-seo-stat__value"><?php echo esc_html( $score['good'] . '/' . $score['total'] ); ?></span>
			<span class="eo-seo-progress" aria-hidden="true"><span style="width: <?php echo esc_attr( (string) ( $score['total'] ? round( 100 * $score['good'] / $score['total'] ) : 0 ) ); ?>%"></span></span>
			<span class="eo-seo-stat__hint"><?php esc_html_e( 'points OK', 'eo-seo' ); ?></span>
		</div>
		<?php foreach ( array( 'problem', 'improve', 'good', 'noindex' ) as $eo_seo_level ) : ?>
			<a class="eo-seo-card eo-seo-stat" href="<?php echo esc_url( admin_url( 'edit.php?post_type=page&eo_seo_level=' . $eo_seo_level ) ); ?>">
				<span class="eo-seo-stat__label"><span class="eo-seo-badge eo-seo-badge--<?php echo esc_attr( $eo_seo_level ); ?>" aria-hidden="true"></span> <?php echo esc_html( $labels[ $eo_seo_level ] ); ?></span>
				<span class="eo-seo-stat__value"><?php echo esc_html( number_format_i18n( $levels[ $eo_seo_level ] ?? 0 ) ); ?></span>
				<span class="eo-seo-stat__hint"><?php esc_html_e( 'published contents', 'eo-seo' ); ?></span>
			</a>
		<?php endforeach; ?>
	</div>

	<?php if ( ! empty( $levels['unknown'] ) ) : ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="eo-seo-callout">
			<input type="hidden" name="action" value="eo_seo_fill_status" />
			<?php wp_nonce_field( 'eo_seo_fill_status' ); ?>
			<span>
				<?php
				echo esc_html(
					sprintf(
						/* translators: %d: number of contents. */
						_n( '%d published content has not been analyzed yet.', '%d published contents have not been analyzed yet.', $levels['unknown'], 'eo-seo' ),
						$levels['unknown']
					)
				);
				?>
			</span>
			<button type="submit" class="eo-seo-button eo-seo-button--primary"><?php esc_html_e( 'Analyze them', 'eo-seo' ); ?></button>
		</form>
	<?php endif; ?>

	<div class="eo-seo-grid eo-seo-grid--review">
		<?php foreach ( $groups as $eo_seo_group => $eo_seo_group_label ) : ?>
			<section class="eo-seo-card">
				<h2 class="eo-seo-card__title"><?php echo esc_html( $eo_seo_group_label ); ?></h2>
				<ul class="eo-seo-review">
					<?php foreach ( $items as $eo_seo_item ) : ?>
						<?php
						if ( $eo_seo_item['group'] !== $eo_seo_group ) {
							continue;
						}
						?>
						<li class="eo-seo-review__item eo-seo-review__item--<?php echo esc_attr( $eo_seo_item['status'] ); ?>">
							<span class="eo-seo-dot eo-seo-dot--<?php echo esc_attr( $eo_seo_item['status'] ); ?>" aria-hidden="true"></span>
							<span class="eo-seo-review__text">
								<strong><?php echo esc_html( $eo_seo_item['label'] ); ?></strong>
								<span class="screen-reader-text"><?php echo esc_html( $eo_seo_status_labels[ $eo_seo_item['status'] ] ?? '' ); ?></span>
								<span><?php echo esc_html( $eo_seo_item['message'] ); ?></span>
							</span>
							<?php if ( ! empty( $eo_seo_item['view_url'] ) || ( 'good' !== $eo_seo_item['status'] && '' !== $eo_seo_item['fix_url'] ) ) : ?>
								<span class="eo-seo-review__actions">
									<?php if ( ! empty( $eo_seo_item['view_url'] ) ) : ?>
										<a class="eo-seo-button eo-seo-button--small eo-seo-button--link" href="<?php echo esc_url( $eo_seo_item['view_url'] ); ?>" target="_blank" rel="noopener">
											<?php esc_html_e( 'View', 'eo-seo' ); ?>
											<span class="screen-reader-text"><?php esc_html_e( '(opens in a new tab)', 'eo-seo' ); ?></span>
										</a>
									<?php endif; ?>
									<?php if ( 'good' !== $eo_seo_item['status'] && '' !== $eo_seo_item['fix_url'] ) : ?>
										<a class="eo-seo-button eo-seo-button--small" href="<?php echo esc_url( $eo_seo_item['fix_url'] ); ?>"><?php esc_html_e( 'Fix', 'eo-seo' ); ?></a>
									<?php endif; ?>
								</span>
							<?php endif; ?>
						</li>
					<?php endforeach; ?>
				</ul>
			</section>
		<?php endforeach; ?>
	</div>
</div>
