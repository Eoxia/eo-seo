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

$eo_seo_networks = EoSeo\Settings::networks();
?>
<section class="eo-seo-card">
	<header class="eo-seo-card__head">
		<h2 class="eo-seo-card__title"><?php esc_html_e( 'Accounts of the site', 'eo-seo' ); ?></h2>
		<p><?php esc_html_e( 'Google links these accounts to the organization (structured data). Leave empty the networks the site does not use.', 'eo-seo' ); ?></p>
	</header>
	<div class="eo-seo-form-grid">
		<?php foreach ( $eo_seo_networks as $eo_seo_key => $eo_seo_network ) : ?>
			<div class="eo-seo-form-row">
				<label class="eo-seo-input-label" for="eo-seo-profile-<?php echo esc_attr( $eo_seo_key ); ?>"><?php echo esc_html( $eo_seo_network['label'] ); ?></label>
				<input type="url" id="eo-seo-profile-<?php echo esc_attr( $eo_seo_key ); ?>" class="eo-seo-input" name="<?php echo esc_attr( $option . '[profiles][' . $eo_seo_key . ']' ); ?>" value="<?php echo esc_attr( $settings['profiles'][ $eo_seo_key ] ?? '' ); ?>" placeholder="<?php echo esc_attr( $eo_seo_network['placeholder'] ); ?>" />
			</div>
		<?php endforeach; ?>
		<div class="eo-seo-form-row">
			<label class="eo-seo-input-label" for="eo-seo-x"><?php esc_html_e( 'X (Twitter)', 'eo-seo' ); ?></label>
			<div class="eo-seo-input-prefix">
				<span aria-hidden="true">@</span>
				<input type="text" id="eo-seo-x" class="eo-seo-input" name="<?php echo esc_attr( $option ); ?>[x_username]" value="<?php echo esc_attr( $settings['x_username'] ); ?>" />
			</div>
			<p class="eo-seo-help"><?php esc_html_e( 'Also shown with the pages shared on X.', 'eo-seo' ); ?></p>
		</div>
	</div>
</section>

<section class="eo-seo-card">
	<header class="eo-seo-card__head">
		<h2 class="eo-seo-card__title"><?php esc_html_e( 'Other profiles', 'eo-seo' ); ?></h2>
		<p><?php esc_html_e( 'Other pages about the organization: Wikipedia, Google Business Profile, directories, other networks… One address per line.', 'eo-seo' ); ?></p>
	</header>
	<label class="screen-reader-text" for="eo-seo-same-as"><?php esc_html_e( 'Other profiles', 'eo-seo' ); ?></label>
	<textarea id="eo-seo-same-as" class="eo-seo-input eo-seo-input--code" rows="3" name="<?php echo esc_attr( $option ); ?>[same_as]" placeholder="https://fr.wikipedia.org/wiki/…"><?php echo esc_textarea( implode( "\n", (array) $settings['same_as'] ) ); ?></textarea>
</section>

<section class="eo-seo-card">
	<header class="eo-seo-card__head">
		<h2 class="eo-seo-card__title"><?php esc_html_e( 'Default sharing image', 'eo-seo' ); ?></h2>
		<p><?php esc_html_e( 'Used when a page is shared (Facebook, LinkedIn, WhatsApp, X…) and has neither a social image nor a featured image. Recommended size: 1200 × 630 pixels.', 'eo-seo' ); ?></p>
	</header>
	<?php EoSeo\Admin\Media_Field::render( $option . '[default_og_image]', (int) $settings['default_og_image'], 'large' ); ?>
</section>
