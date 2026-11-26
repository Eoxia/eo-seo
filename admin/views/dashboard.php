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
 * @var array<string, int|string>         $scan   State of the site scan.
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
			<span class="eo-seo-progress eo-seo-progress--<?php echo esc_attr( $score['level'] ); ?>" aria-hidden="true"><span style="width: <?php echo esc_attr( (string) $score['percent'] ); ?>%"></span></span>
			<span class="eo-seo-stat__hint">
				<?php
				$eo_seo_to_fix = $score['total'] - $score['good'];
				echo esc_html(
					$eo_seo_to_fix
						/* translators: %d: number of points of the site review that are not good. */
						? sprintf( _n( 'points OK · %d to fix below', 'points OK · %d to fix below', $eo_seo_to_fix, 'eo-seo' ), $eo_seo_to_fix )
						: __( 'points OK', 'eo-seo' )
				);
				?>
			</span>
		</div>
		<?php foreach ( array( 'problem', 'improve', 'good', 'noindex' ) as $eo_seo_level ) : ?>
			<a class="eo-seo-card eo-seo-stat" href="<?php echo esc_url( admin_url( 'edit.php?post_type=page&eo_seo_level=' . $eo_seo_level ) ); ?>">
				<span class="eo-seo-stat__label"><span class="eo-seo-badge eo-seo-badge--<?php echo esc_attr( $eo_seo_level ); ?>" aria-hidden="true"></span> <?php echo esc_html( $labels[ $eo_seo_level ] ); ?></span>
				<span class="eo-seo-stat__value"><?php echo esc_html( number_format_i18n( $levels[ $eo_seo_level ] ?? 0 ) ); ?></span>
				<span class="eo-seo-stat__hint"><?php esc_html_e( 'published contents', 'eo-seo' ); ?></span>
			</a>
		<?php endforeach; ?>
	</div>

	<?php $eo_seo_running = 'running' === $scan['status']; ?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="eo-seo-card eo-seo-scan<?php echo $eo_seo_running ? ' is-running' : ''; ?>" data-eo-seo-scan data-running="<?php echo $eo_seo_running ? '1' : '0'; ?>">
		<input type="hidden" name="action" value="eo_seo_scan_start" />
		<?php wp_nonce_field( 'eo_seo_scan_start' ); ?>
		<div class="eo-seo-scan__text">
			<h2 class="eo-seo-card__title"><?php esc_html_e( 'Site scan', 'eo-seo' ); ?></h2>
			<p class="eo-seo-help" data-eo-seo-scan-status>
				<?php if ( $eo_seo_running ) : ?>
					<?php
					/* translators: 1: contents analyzed, 2: contents to analyze. */
					echo esc_html( sprintf( __( 'Scan in progress: %1$d of %2$d contents.', 'eo-seo' ), $scan['done'], $scan['total'] ) );
					?>
				<?php elseif ( ! empty( $levels['unknown'] ) ) : ?>
					<?php
					/* translators: %d: number of contents. */
					echo esc_html( sprintf( _n( '%d published content has not been analyzed yet.', '%d published contents have not been analyzed yet.', $levels['unknown'], 'eo-seo' ), $levels['unknown'] ) );
					?>
				<?php elseif ( $scan['finished'] ) : ?>
					<?php
					/* translators: %s: time since the last scan, e.g. "2 hours". */
					echo esc_html( sprintf( __( 'Last complete scan %s ago. Duplicates and orphan pages are checked again every night and a few minutes after a change.', 'eo-seo' ), human_time_diff( $scan['finished'] ) ) );
					?>
				<?php else : ?>
					<?php esc_html_e( 'Checks again every content against the others (duplicates, orphan pages, broken links).', 'eo-seo' ); ?>
				<?php endif; ?>
				<?php if ( $scan['scheduled'] && ! $eo_seo_running ) : ?>
					<?php
					/* translators: %s: time until the next scan, e.g. "4 minutes". */
					echo esc_html( sprintf( __( 'Next scan in %s, after the latest changes.', 'eo-seo' ), human_time_diff( $scan['scheduled'] ) ) );
					?>
				<?php endif; ?>
			</p>
			<span class="eo-seo-progress" aria-hidden="true"<?php echo $eo_seo_running ? '' : ' hidden'; ?> data-eo-seo-scan-bar><span style="width: <?php echo esc_attr( (string) ( $scan['total'] ? round( 100 * $scan['done'] / $scan['total'] ) : 0 ) ); ?>%"></span></span>
		</div>
		<button type="submit" class="eo-seo-button eo-seo-button--primary" data-eo-seo-scan-button<?php disabled( $eo_seo_running ); ?>><?php esc_html_e( 'Scan the whole site', 'eo-seo' ); ?></button>
	</form>

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
