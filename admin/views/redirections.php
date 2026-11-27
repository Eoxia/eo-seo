<?php
/**
 * View: redirections.
 *
 * @package EoSeo
 *
 * @var array<int, object>    $rows           Redirections of the page.
 * @var int                   $total          Number of redirections.
 * @var int                   $pages          Number of pages.
 * @var int                   $paged          Current page.
 * @var string                $search         Searched text.
 * @var string                $prefill_source Source to prefill.
 * @var array<string, string> $origins        Origin labels.
 */

defined( 'ABSPATH' ) || exit;

$eo_seo_post = admin_url( 'admin-post.php' );
?>
<div class="wrap eo-seo-app">
	<?php EoSeo\Admin\Ui::header( 'eo-seo-redirections', __( 'Redirections', 'eo-seo' ), __( 'Send visitors and search engines from an old address to the new one.', 'eo-seo' ) ); ?>

	<div class="eo-seo-grid eo-seo-grid--aside">
		<form method="post" action="<?php echo esc_url( $eo_seo_post ); ?>" class="eo-seo-card">
			<h2 class="eo-seo-card__title"><?php esc_html_e( 'Add a redirection', 'eo-seo' ); ?></h2>
			<input type="hidden" name="action" value="eo_seo_redirection_save" />
			<?php wp_nonce_field( 'eo_seo_redirection_save' ); ?>
			<div class="eo-seo-redirect-form">
				<div class="eo-seo-form-row">
					<label class="eo-seo-input-label" for="eo-seo-source"><?php esc_html_e( 'Old address', 'eo-seo' ); ?></label>
					<input type="text" id="eo-seo-source" name="source" class="eo-seo-input eo-seo-input--code" required placeholder="/old-page/" value="<?php echo esc_attr( $prefill_source ); ?>" />
				</div>
				<span class="eo-seo-redirect-form__arrow" aria-hidden="true">→</span>
				<div class="eo-seo-form-row">
					<label class="eo-seo-input-label" for="eo-seo-target"><?php esc_html_e( 'New address', 'eo-seo' ); ?></label>
					<input type="text" id="eo-seo-target" name="target" class="eo-seo-input eo-seo-input--code" placeholder="/new-page/" />
				</div>
				<div class="eo-seo-form-row">
					<label class="eo-seo-input-label" for="eo-seo-type"><?php esc_html_e( 'Type', 'eo-seo' ); ?></label>
					<select id="eo-seo-type" name="type" class="eo-seo-input">
						<option value="301"><?php esc_html_e( '301 – Moved permanently', 'eo-seo' ); ?></option>
						<option value="302"><?php esc_html_e( '302 – Moved temporarily', 'eo-seo' ); ?></option>
						<option value="410"><?php esc_html_e( '410 – Deleted for good', 'eo-seo' ); ?></option>
					</select>
				</div>
				<button type="submit" class="eo-seo-button eo-seo-button--primary"><?php esc_html_e( 'Add', 'eo-seo' ); ?></button>
			</div>
		</form>

		<div class="eo-seo-card">
			<h2 class="eo-seo-card__title"><?php esc_html_e( 'Import and export', 'eo-seo' ); ?></h2>
			<p class="eo-seo-help"><?php esc_html_e( 'CSV file with three columns: old address, new address, type (301 by default). The tool for a redesign: prepare the list, import it on launch day.', 'eo-seo' ); ?></p>
			<form method="post" action="<?php echo esc_url( $eo_seo_post ); ?>" enctype="multipart/form-data" class="eo-seo-inline">
				<input type="hidden" name="action" value="eo_seo_redirections_import" />
				<?php wp_nonce_field( 'eo_seo_redirections_import' ); ?>
				<label class="screen-reader-text" for="eo-seo-csv"><?php esc_html_e( 'CSV file', 'eo-seo' ); ?></label>
				<input type="file" id="eo-seo-csv" name="csv" accept=".csv,text/csv" required class="eo-seo-file" />
				<button type="submit" class="eo-seo-button"><?php esc_html_e( 'Import', 'eo-seo' ); ?></button>
			</form>
			<form method="post" action="<?php echo esc_url( $eo_seo_post ); ?>">
				<input type="hidden" name="action" value="eo_seo_redirections_export" />
				<?php wp_nonce_field( 'eo_seo_redirections_export' ); ?>
				<button type="submit" class="eo-seo-button eo-seo-button--link"><?php esc_html_e( 'Export as CSV', 'eo-seo' ); ?></button>
			</form>
		</div>
	</div>

	<section class="eo-seo-card eo-seo-card--flush">
		<div class="eo-seo-card__bar">
			<h2 class="eo-seo-card__title">
				<?php
				/* translators: %s: number of redirections. */
				echo esc_html( sprintf( _n( '%s redirection', '%s redirections', $total, 'eo-seo' ), number_format_i18n( $total ) ) );
				?>
			</h2>
			<form method="get" class="eo-seo-search" role="search">
				<input type="hidden" name="page" value="eo-seo-redirections" />
				<label class="screen-reader-text" for="eo-seo-search"><?php esc_html_e( 'Search redirections', 'eo-seo' ); ?></label>
				<input type="search" id="eo-seo-search" name="s" class="eo-seo-input" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search an address…', 'eo-seo' ); ?>" />
			</form>
		</div>

		<form method="post" action="<?php echo esc_url( $eo_seo_post ); ?>">
			<input type="hidden" name="action" value="eo_seo_redirection_delete" />
			<?php wp_nonce_field( 'eo_seo_redirection_delete' ); ?>
			<table class="eo-seo-table">
				<thead>
					<tr>
						<td class="eo-seo-table__check"><span class="screen-reader-text"><?php esc_html_e( 'Select', 'eo-seo' ); ?></span></td>
						<th scope="col"><?php esc_html_e( 'Old address', 'eo-seo' ); ?></th>
						<th scope="col"><?php esc_html_e( 'New address', 'eo-seo' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Type', 'eo-seo' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Origin', 'eo-seo' ); ?></th>
						<th scope="col" class="eo-seo-table__num"><?php esc_html_e( 'Hits', 'eo-seo' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Last hit', 'eo-seo' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( ! $rows ) : ?>
						<tr><td colspan="7" class="eo-seo-table__empty"><?php esc_html_e( 'No redirection yet.', 'eo-seo' ); ?></td></tr>
					<?php endif; ?>
					<?php foreach ( $rows as $eo_seo_row ) : ?>
						<tr>
							<td class="eo-seo-table__check"><input type="checkbox" name="ids[]" value="<?php echo esc_attr( $eo_seo_row->id ); ?>" aria-label="<?php esc_attr_e( 'Select', 'eo-seo' ); ?>" /></td>
							<td><code class="eo-seo-url"><?php echo esc_html( rawurldecode( $eo_seo_row->source ) ); ?></code></td>
							<td><?php echo 410 === (int) $eo_seo_row->type ? '<span class="eo-seo-muted">—</span>' : '<code class="eo-seo-url">' . esc_html( rawurldecode( $eo_seo_row->target ) ) . '</code>'; ?></td>
							<td><span class="eo-seo-tag eo-seo-tag--<?php echo esc_attr( $eo_seo_row->type ); ?>"><?php echo esc_html( $eo_seo_row->type ); ?></span></td>
							<td class="eo-seo-muted"><?php echo esc_html( $origins[ $eo_seo_row->origin ] ?? $eo_seo_row->origin ); ?></td>
							<td class="eo-seo-table__num"><?php echo esc_html( number_format_i18n( (int) $eo_seo_row->hits ) ); ?></td>
							<td class="eo-seo-muted"><?php echo $eo_seo_row->last_hit ? esc_html( get_date_from_gmt( $eo_seo_row->last_hit, get_option( 'date_format' ) ) ) : '—'; ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<?php if ( $rows || $pages > 1 ) : ?>
				<div class="eo-seo-card__foot">
					<?php if ( $rows ) : ?>
						<details class="eo-seo-confirm">
							<summary class="eo-seo-button eo-seo-button--small"><?php esc_html_e( 'Delete the selected redirections', 'eo-seo' ); ?></summary>
							<div class="eo-seo-confirm__box">
								<?php esc_html_e( 'The old addresses will answer "page not found" again.', 'eo-seo' ); ?>
								<button type="submit" class="eo-seo-button eo-seo-button--small eo-seo-button--danger-solid"><?php esc_html_e( 'Confirm the deletion', 'eo-seo' ); ?></button>
							</div>
						</details>
					<?php endif; ?>
					<?php if ( $pages > 1 ) : ?>
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
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</form>
	</section>
</div>
