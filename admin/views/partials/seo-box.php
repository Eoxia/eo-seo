<?php
/**
 * View: SEO box (term edit screen, classic editor metabox).
 *
 * @package EoSeo
 *
 * @var array<string, array<string, mixed>> $fields  Fields shown, keyed by name.
 * @var array<string, mixed>                $values  Values.
 * @var array<string, string>               $preview Resolved title, description, address, and title_template / description_template (value of the templates, for the placeholders).
 * @var array<string, mixed>                $result  Indicator and checks.
 * @var array<string, string>               $levels  Level labels.
 * @var array<string, string>               $vars    Values of the template variables for the live preview (sitename, sep, title).
 */

defined( 'ABSPATH' ) || exit;

use EoSeo\Admin\Ui;

$eo_seo_groups = array(
	'search'   => array( 'title', 'description', 'focus_keyword' ),
	'social'   => array( 'og_title', 'og_description', 'og_image_id' ),
	'advanced' => array( 'noindex', 'canonical' ),
);
$eo_seo_failed = array_values( array_filter( $result['checks'], static fn( $check ) => ! $check['passed'] ) );
$eo_seo_passed = array_values( array_filter( $result['checks'], static fn( $check ) => $check['passed'] ) );
?>
<div class="eo-seo-app eo-seo-box" data-eo-seo-box data-sitename="<?php echo esc_attr( $vars['sitename'] ); ?>" data-sep="<?php echo esc_attr( $vars['sep'] ); ?>" data-title="<?php echo esc_attr( $vars['title'] ); ?>">
	<div class="eo-seo-box__head">
		<span class="eo-seo-badge eo-seo-badge--large eo-seo-badge--<?php echo esc_attr( $result['level'] ); ?>" aria-hidden="true"></span>
		<div>
			<strong class="eo-seo-box__level"><?php echo esc_html( $levels[ $result['level'] ] ?? '' ); ?></strong>
			<span class="eo-seo-muted">
				<?php if ( 'noindex' === $result['level'] ) : ?>
					<?php esc_html_e( 'Hidden from search engines: nothing to check.', 'eo-seo' ); ?>
				<?php else : ?>
					<?php
					/* translators: 1: number of checks passed, 2: number of checks. */
					echo esc_html( sprintf( __( '%1$d/%2$d points OK', 'eo-seo' ), $result['passed'], $result['total'] ) );
					?>
					· <?php esc_html_e( 'updated when you save', 'eo-seo' ); ?>
				<?php endif; ?>
			</span>
		</div>
	</div>

	<div class="eo-seo-box__grid">
		<div class="eo-seo-box__main">
			<div class="eo-seo-serp" aria-label="<?php esc_attr_e( 'Search result preview', 'eo-seo' ); ?>">
				<span class="eo-seo-serp__url"><?php echo esc_html( $preview['url'] ); ?></span>
				<span class="eo-seo-serp__title" data-eo-seo-serp="title"><?php echo esc_html( $preview['title'] ); ?></span>
				<span class="eo-seo-serp__description" data-eo-seo-serp="description"><?php echo esc_html( $preview['description'] ); ?></span>
			</div>

			<section class="eo-seo-box__section">
				<h3 class="eo-seo-subtitle"><?php esc_html_e( 'Search engines', 'eo-seo' ); ?></h3>
				<?php
				foreach ( $eo_seo_groups['search'] as $eo_seo_name ) {
					if ( isset( $fields[ $eo_seo_name ] ) ) {
						Ui::field( $eo_seo_name, $fields[ $eo_seo_name ], $values[ $eo_seo_name ], $preview[ $eo_seo_name . '_template' ] ?? '' );
					}
				}
				?>
			</section>

			<details class="eo-seo-box__section eo-seo-box__fold">
				<summary><?php esc_html_e( 'Social sharing', 'eo-seo' ); ?></summary>
				<?php
				foreach ( $eo_seo_groups['social'] as $eo_seo_name ) {
					if ( isset( $fields[ $eo_seo_name ] ) ) {
						Ui::field( $eo_seo_name, $fields[ $eo_seo_name ], $values[ $eo_seo_name ] );
					}
				}
				?>
			</details>

			<details class="eo-seo-box__section eo-seo-box__fold"<?php echo ! empty( $values['noindex'] ) || '' !== (string) ( $values['canonical'] ?? '' ) ? ' open' : ''; ?>>
				<summary><?php esc_html_e( 'Advanced', 'eo-seo' ); ?></summary>
				<?php
				foreach ( $eo_seo_groups['advanced'] as $eo_seo_name ) {
					if ( isset( $fields[ $eo_seo_name ] ) ) {
						Ui::field( $eo_seo_name, $fields[ $eo_seo_name ], $values[ $eo_seo_name ] );
					}
				}
				?>
			</details>
		</div>

		<aside class="eo-seo-box__checks" aria-label="<?php esc_attr_e( 'Checks', 'eo-seo' ); ?>">
			<h3 class="eo-seo-subtitle"><?php esc_html_e( 'Checks', 'eo-seo' ); ?></h3>
			<?php if ( ! $result['checks'] ) : ?>
				<p class="eo-seo-muted"><?php esc_html_e( 'Hidden from search engines: nothing to check.', 'eo-seo' ); ?></p>
			<?php endif; ?>
			<?php if ( $eo_seo_failed ) : ?>
				<ul class="eo-seo-box__list">
					<?php foreach ( $eo_seo_failed as $eo_seo_check ) : ?>
						<li class="eo-seo-box__check eo-seo-box__check--<?php echo esc_attr( $eo_seo_check['severity'] ); ?>">
							<strong><?php echo esc_html( $eo_seo_check['label'] ); ?></strong>
							<span><?php echo esc_html( $eo_seo_check['message'] ); ?></span>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
			<?php if ( $eo_seo_passed ) : ?>
				<details class="eo-seo-box__ok">
					<summary>
						<?php
						/* translators: %d: number of checks. */
						echo esc_html( sprintf( _n( '%d point OK', '%d points OK', count( $eo_seo_passed ), 'eo-seo' ), count( $eo_seo_passed ) ) );
						?>
					</summary>
					<ul class="eo-seo-box__list">
						<?php foreach ( $eo_seo_passed as $eo_seo_check ) : ?>
							<li class="eo-seo-box__check eo-seo-box__check--ok">
								<strong><?php echo esc_html( $eo_seo_check['label'] ); ?></strong>
								<span><?php echo esc_html( $eo_seo_check['message'] ); ?></span>
							</li>
						<?php endforeach; ?>
					</ul>
				</details>
			<?php endif; ?>
		</aside>
	</div>
</div>
