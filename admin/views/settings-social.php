<?php
/**
 * View: settings, "Social networks" section.
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
		<h2 class="eo-seo-card__title"><?php esc_html_e( 'Default sharing image', 'eo-seo' ); ?></h2>
		<p><?php esc_html_e( 'Used when a page is shared and has neither a social image nor a featured image. Recommended size: 1200 × 630 pixels.', 'eo-seo' ); ?></p>
	</header>
	<?php EoSeo\Admin\Media_Field::render( $option . '[default_og_image]', (int) $settings['default_og_image'], 'large' ); ?>
</section>

<section class="eo-seo-card">
	<header class="eo-seo-card__head">
		<h2 class="eo-seo-card__title"><?php esc_html_e( 'X (Twitter) account', 'eo-seo' ); ?></h2>
		<p><?php esc_html_e( 'Shown with the pages shared on X.', 'eo-seo' ); ?></p>
	</header>
	<div class="eo-seo-input-prefix">
		<span aria-hidden="true">@</span>
		<label class="screen-reader-text" for="eo-seo-x"><?php esc_html_e( 'X (Twitter) account', 'eo-seo' ); ?></label>
		<input type="text" id="eo-seo-x" class="eo-seo-input" name="<?php echo esc_attr( $option ); ?>[x_username]" value="<?php echo esc_attr( $settings['x_username'] ); ?>" />
	</div>
</section>
