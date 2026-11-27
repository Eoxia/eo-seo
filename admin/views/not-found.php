<?php
/**
 * View: 404 errors.
 *
 * @package EoSeo
 *
 * @var array<int, object>  $rows        Rows of the page.
 * @var string              $status      new or done.
 * @var int                 $pages       Number of pages.
 * @var int                 $paged       Current page.
 * @var array<int, string>  $suggestions Suggested destination per row ID.
 */

defined( 'ABSPATH' ) || exit;

$eo_seo_post = admin_url( 'admin-post.php' );
?>
<div class="wrap eo-seo-app">
	<?php EoSeo\Admin\Ui::header( 'eo-seo-404', __( '404 errors', 'eo-seo' ), __( 'Addresses requested by visitors or search engines that do not exist. Vulnerability probes and scripts are not logged, nor any IP address.', 'eo-seo' ) ); ?>

	<section class="eo-seo-card eo-seo-card--flush">
		<div class="eo-seo-card__bar">
			<nav class="eo-seo-segmented eo-seo-segmented--links" aria-label="<?php esc_attr_e( 'Filter', 'eo-seo' ); ?>">
				<a href="<?php echo esc_url( EoSeo\Admin\Admin::page_url( 'eo-seo-404' ) ); ?>" class="eo-seo-segmented__option<?php echo 'new' === $status ? ' is-current' : ''; ?>"><span><?php esc_html_e( 'To handle', 'eo-seo' ); ?></span></a>
				<a href="<?php echo esc_url( EoSeo\Admin\Admin::page_url( 'eo-seo-404', array( 'status' => 'done' ) ) ); ?>" class="eo-seo-segmented__option<?php echo 'done' === $status ? ' is-current' : ''; ?>"><span><?php esc_html_e( 'Handled', 'eo-seo' ); ?></span></a>
			</nav>
		</div>

		<table class="eo-seo-table">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Address', 'eo-seo' ); ?></th>
					<th scope="col" class="eo-seo-table__num"><?php esc_html_e( 'Views', 'eo-seo' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Last view', 'eo-seo' ); ?></th>
					<th scope="col"><?php esc_html_e( 'Action', 'eo-seo' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( ! $rows ) : ?>
					<tr><td colspan="4" class="eo-seo-table__empty"><?php esc_html_e( 'Nothing here.', 'eo-seo' ); ?></td></tr>
				<?php endif; ?>
				<?php foreach ( $rows as $eo_seo_row ) : ?>
					<tr>
						<td>
							<code class="eo-seo-url"><?php echo esc_html( rawurldecode( $eo_seo_row->url ) ); ?></code>
							<?php if ( '' !== $eo_seo_row->referrer ) : ?>
								<span class="eo-seo-muted eo-seo-referrer">
									<?php
									/* translators: %s: referring page. */
									echo esc_html( sprintf( __( 'From %s', 'eo-seo' ), $eo_seo_row->referrer ) );
									?>
								</span>
							<?php endif; ?>
						</td>
						<td class="eo-seo-table__num"><?php echo esc_html( number_format_i18n( (int) $eo_seo_row->hits ) ); ?></td>
						<td class="eo-seo-muted"><?php echo esc_html( get_date_from_gmt( $eo_seo_row->last_seen, get_option( 'date_format' ) ) ); ?></td>
						<td class="eo-seo-actions">
							<?php if ( 'new' === $status ) : ?>
								<form method="post" action="<?php echo esc_url( $eo_seo_post ); ?>" class="eo-seo-inline">
									<input type="hidden" name="action" value="eo_seo_redirection_save" />
									<input type="hidden" name="origin" value="404" />
									<input type="hidden" name="type" value="301" />
									<input type="hidden" name="source" value="<?php echo esc_attr( $eo_seo_row->url ); ?>" />
									<?php wp_nonce_field( 'eo_seo_redirection_save' ); ?>
									<label class="screen-reader-text" for="eo-seo-target-<?php echo esc_attr( $eo_seo_row->id ); ?>"><?php esc_html_e( 'New address', 'eo-seo' ); ?></label>
									<input type="text" id="eo-seo-target-<?php echo esc_attr( $eo_seo_row->id ); ?>" name="target" class="eo-seo-input eo-seo-input--code eo-seo-input--small" required value="<?php echo esc_attr( $suggestions[ $eo_seo_row->id ] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'New address', 'eo-seo' ); ?>" />
									<button type="submit" class="eo-seo-button eo-seo-button--small eo-seo-button--primary"><?php esc_html_e( 'Redirect', 'eo-seo' ); ?></button>
								</form>
							<?php endif; ?>
							<form method="post" action="<?php echo esc_url( $eo_seo_post ); ?>" class="eo-seo-inline">
								<input type="hidden" name="action" value="eo_seo_404" />
								<input type="hidden" name="ids[]" value="<?php echo esc_attr( $eo_seo_row->id ); ?>" />
								<?php wp_nonce_field( 'eo_seo_404' ); ?>
								<?php if ( 'new' === $status ) : ?>
									<button type="submit" name="status" value="done" class="eo-seo-button eo-seo-button--link"><?php esc_html_e( 'Mark as handled', 'eo-seo' ); ?></button>
								<?php else : ?>
									<button type="submit" name="status" value="new" class="eo-seo-button eo-seo-button--link"><?php esc_html_e( 'Back to handle', 'eo-seo' ); ?></button>
								<?php endif; ?>
								<button type="submit" name="status" value="delete" class="eo-seo-button eo-seo-button--link eo-seo-button--danger"><?php esc_html_e( 'Delete', 'eo-seo' ); ?></button>
							</form>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<?php if ( $pages > 1 ) : ?>
			<div class="eo-seo-card__foot">
				<div class="eo-seo-pagination">
					<?php
					echo wp_kses_post(
						paginate_links(
							array(
								'base'    => add_query_arg( 'paged', '%#%' ),
								'current' => $paged,
								'total'   => $pages,
							)
						)
					);
					?>
				</div>
			</div>
		<?php endif; ?>
	</section>
</div>
