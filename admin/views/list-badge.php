<?php
/**
 * View: SEO badge of a content in the lists, with its tooltip.
 *
 * @package EoSeo
 *
 * @var string                                                         $level      Level (problem, improve, good, noindex, unknown).
 * @var string                                                         $title      Level label.
 * @var array{level: string, points: string}                          $status     Stored indicator.
 * @var array<int, array{severity: string, label: string, message: string}> $issues Failed checks shown.
 * @var int                                                            $more       Failed checks not shown.
 * @var string                                                         $tooltip_id Tooltip ID.
 * @var string                                                         $unknown_hint Text when the indicator was never computed.
 */

defined( 'ABSPATH' ) || exit;
?>
<span class="eo-seo-tip" tabindex="0" aria-describedby="<?php echo esc_attr( $tooltip_id ); ?>">
	<span class="eo-seo-badge eo-seo-badge--<?php echo esc_attr( $level ); ?>" aria-hidden="true"></span>
	<span class="screen-reader-text"><?php echo esc_html( $title ); ?></span>
	<span class="eo-seo-tip__content" role="tooltip" id="<?php echo esc_attr( $tooltip_id ); ?>">
		<span class="eo-seo-tip__title">
			<?php echo esc_html( $title ); ?>
			<?php if ( '' !== $status['points'] && 'noindex' !== $level ) : ?>
				<?php
				/* translators: %s: points, as "17/23". */
				echo esc_html( sprintf( __( '· %s points OK', 'eo-seo' ), $status['points'] ) );
				?>
			<?php endif; ?>
		</span>
		<?php if ( 'unknown' === $level ) : ?>
			<span class="eo-seo-tip__line"><?php echo esc_html( $unknown_hint ); ?></span>
		<?php elseif ( 'noindex' === $level ) : ?>
			<span class="eo-seo-tip__line"><?php esc_html_e( 'Hidden from search engines: nothing to check.', 'eo-seo' ); ?></span>
		<?php elseif ( ! $issues && 'good' === $level ) : ?>
			<span class="eo-seo-tip__line"><?php esc_html_e( 'Every check passes.', 'eo-seo' ); ?></span>
		<?php elseif ( ! $issues ) : ?>
			<span class="eo-seo-tip__line"><?php esc_html_e( 'Open it to see the points to fix.', 'eo-seo' ); ?></span>
		<?php endif; ?>
		<?php foreach ( $issues as $eo_seo_issue ) : ?>
			<span class="eo-seo-tip__line eo-seo-tip__line--<?php echo esc_attr( $eo_seo_issue['severity'] ); ?>">
				<strong><?php echo esc_html( $eo_seo_issue['label'] ); ?></strong>
				<?php echo esc_html( $eo_seo_issue['message'] ); ?>
			</span>
		<?php endforeach; ?>
		<?php if ( $more ) : ?>
			<span class="eo-seo-tip__line">
				<?php
				/* translators: %d: number of other points to fix. */
				echo esc_html( sprintf( _n( 'And %d other point: open it to see them all.', 'And %d other points: open it to see them all.', $more, 'eo-seo' ), $more ) );
				?>
			</span>
		<?php endif; ?>
	</span>
</span>
