<?php
/**
 * Render of the "Breadcrumbs" block.
 *
 * @package EoSeo
 *
 * @var array<string, mixed> $attributes Block attributes.
 */

defined( 'ABSPATH' ) || exit;

$eo_seo_items = EoSeo\Breadcrumbs::items();
if ( empty( $attributes['showHome'] ) ) {
	array_shift( $eo_seo_items );
}
if ( ! $eo_seo_items ) {
	return;
}

$eo_seo_last = count( $eo_seo_items ) - 1;
?>
<nav <?php echo get_block_wrapper_attributes( array( 'aria-label' => esc_attr__( 'Breadcrumbs', 'eo-seo' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Escaped by get_block_wrapper_attributes(). ?>>
	<ol class="eo-seo-breadcrumbs__list">
		<?php foreach ( $eo_seo_items as $eo_seo_index => $eo_seo_item ) : ?>
			<li class="eo-seo-breadcrumbs__item">
				<?php if ( $eo_seo_index < $eo_seo_last && '' !== $eo_seo_item['url'] ) : ?>
					<a href="<?php echo esc_url( $eo_seo_item['url'] ); ?>"><?php echo esc_html( $eo_seo_item['name'] ); ?></a>
					<span class="eo-seo-breadcrumbs__separator" aria-hidden="true"><?php echo esc_html( (string) $attributes['separator'] ); ?></span>
				<?php else : ?>
					<span aria-current="page"><?php echo esc_html( $eo_seo_item['name'] ); ?></span>
				<?php endif; ?>
			</li>
		<?php endforeach; ?>
	</ol>
</nav>
