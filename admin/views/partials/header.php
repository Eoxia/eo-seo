<?php
/**
 * View: header of an EO SEO screen.
 *
 * @package EoSeo
 *
 * @var string                                         $current Current page slug.
 * @var string                                         $title   Title of the screen.
 * @var string                                         $intro   Introduction.
 * @var array<string, array{label: string, count: int}> $screens Navigation.
 */

defined( 'ABSPATH' ) || exit;
?>
<header class="eo-seo-topbar">
	<div class="eo-seo-topbar__brand">
		<span class="eo-seo-logo" aria-hidden="true"></span>
		<span><?php esc_html_e( 'EO SEO', 'eo-seo' ); ?></span>
	</div>
	<?php if ( $screens ) : ?>
		<nav class="eo-seo-topbar__nav" aria-label="<?php esc_attr_e( 'EO SEO screens', 'eo-seo' ); ?>">
			<?php foreach ( $screens as $eo_seo_slug => $eo_seo_screen ) : ?>
				<a href="<?php echo esc_url( EoSeo\Admin\Admin::page_url( $eo_seo_slug ) ); ?>" class="eo-seo-topbar__link<?php echo $eo_seo_slug === $current ? ' is-current' : ''; ?>"<?php echo $eo_seo_slug === $current ? ' aria-current="page"' : ''; ?>>
					<?php echo esc_html( $eo_seo_screen['label'] ); ?>
					<?php if ( $eo_seo_screen['count'] ) : ?>
						<span class="eo-seo-count"><?php echo esc_html( number_format_i18n( $eo_seo_screen['count'] ) ); ?></span>
					<?php endif; ?>
				</a>
			<?php endforeach; ?>
		</nav>
	<?php endif; ?>
</header>
<div class="eo-seo-pagehead">
	<h1><?php echo esc_html( $title ); ?></h1>
	<?php if ( '' !== $intro ) : ?>
		<p><?php echo esc_html( $intro ); ?></p>
	<?php endif; ?>
</div>
<hr class="wp-header-end" />
