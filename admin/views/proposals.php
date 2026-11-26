<?php
/**
 * View: proposals.
 *
 * @package EoSeo
 *
 * @var array<int, array<string, mixed>> $items Pending proposals the user may apply.
 */

defined( 'ABSPATH' ) || exit;

$eo_seo_post = admin_url( 'admin-post.php' );
?>
<div class="wrap eo-seo-app">
	<?php
	EoSeo\Admin\Ui::header(
		'eo-seo-proposals',
		__( 'SEO proposals', 'eo-seo' ),
		sprintf(
			/* translators: %d: number of days. */
			__( 'Changes prepared by the AI. Nothing is changed on the site until you apply them. Proposals expire after %d days.', 'eo-seo' ),
			EoSeo\Proposals::EXPIRATION_DAYS
		)
	);
	?>

	<?php if ( ! $items ) : ?>
		<div class="eo-seo-card eo-seo-empty">
			<span class="eo-seo-badge eo-seo-badge--good eo-seo-badge--large" aria-hidden="true"></span>
			<p><?php esc_html_e( 'No proposal to validate.', 'eo-seo' ); ?></p>
		</div>
	<?php else : ?>
		<form method="post" action="<?php echo esc_url( $eo_seo_post ); ?>">
			<input type="hidden" name="action" value="eo_seo_proposal" />
			<?php wp_nonce_field( 'eo_seo_proposal' ); ?>

			<div class="eo-seo-proposals">
				<?php foreach ( $items as $eo_seo_item ) : ?>
					<div class="eo-seo-card eo-seo-proposal<?php echo '' !== $eo_seo_item['conflict'] ? ' has-conflict' : ''; ?>">
						<input type="checkbox" name="ids[]" value="<?php echo esc_attr( (string) $eo_seo_item['id'] ); ?>" checked class="eo-seo-proposal__check" aria-label="<?php esc_attr_e( 'Select', 'eo-seo' ); ?>" />
						<div class="eo-seo-proposal__body">
							<div class="eo-seo-proposal__head">
								<strong>
									<?php if ( 'fields' === $eo_seo_item['kind'] ) : ?>
										<?php echo esc_html( $eo_seo_item['title'] ); ?>
									<?php else : ?>
										<?php esc_html_e( 'Redirection', 'eo-seo' ); ?>
									<?php endif; ?>
								</strong>
								<span class="eo-seo-muted"><?php echo esc_html( wp_date( get_option( 'date_format' ), $eo_seo_item['created'] ) ); ?></span>
								<?php if ( 'fields' === $eo_seo_item['kind'] ) : ?>
									<?php $eo_seo_link = 'post' === $eo_seo_item['object_type'] ? get_edit_post_link( (int) $eo_seo_item['object_id'] ) : get_edit_term_link( (int) $eo_seo_item['object_id'] ); ?>
									<a href="<?php echo esc_url( (string) $eo_seo_link ); ?>"><?php esc_html_e( 'Open', 'eo-seo' ); ?></a>
								<?php endif; ?>
							</div>
							<?php if ( '' !== $eo_seo_item['conflict'] ) : ?>
								<p class="eo-seo-warning"><?php echo esc_html( $eo_seo_item['conflict'] ); ?></p>
							<?php endif; ?>
							<div class="eo-seo-diff"><?php echo wp_kses_post( EoSeo\Proposals::details_html( $eo_seo_item ) ); ?></div>
						</div>
					</div>
				<?php endforeach; ?>
			</div>

			<div class="eo-seo-savebar">
				<label class="eo-seo-inline-check"><input type="checkbox" name="force" value="1" /> <?php esc_html_e( 'Also apply when the value changed since the proposal', 'eo-seo' ); ?></label>
				<button type="submit" name="proposal_action" value="reject" class="eo-seo-button"><?php esc_html_e( 'Reject the selected proposals', 'eo-seo' ); ?></button>
				<button type="submit" name="proposal_action" value="apply" class="eo-seo-button eo-seo-button--primary"><?php esc_html_e( 'Apply the selected proposals', 'eo-seo' ); ?></button>
			</div>
		</form>
	<?php endif; ?>
</div>
