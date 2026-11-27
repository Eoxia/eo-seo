<?php
/**
 * View: settings, "Webmaster tools" section.
 *
 * @package EoSeo
 *
 * @var array<string, mixed> $settings Settings.
 * @var string               $option   Option name.
 */

defined( 'ABSPATH' ) || exit;

$eo_seo_help = array(
	'google'    => __( 'Search Console: performance in Google, indexing, errors.', 'eo-seo' ),
	'bing'      => __( 'Bing Webmaster Tools: Bing, and the search engines that use it (DuckDuckGo, Ecosia…).', 'eo-seo' ),
	'pinterest' => __( 'Claims the site on Pinterest (statistics, rich pins).', 'eo-seo' ),
	'facebook'  => __( 'Verifies the domain in the Meta Business Suite (advertising, editing of the link previews).', 'eo-seo' ),
);
?>
<section class="eo-seo-card">
	<header class="eo-seo-card__head">
		<h2 class="eo-seo-card__title"><?php esc_html_e( 'Site verification', 'eo-seo' ); ?></h2>
		<p><?php esc_html_e( 'Paste the verification code, or the whole <meta> tag: only the code is kept. The tag is added to the home page.', 'eo-seo' ); ?></p>
	</header>
	<div class="eo-seo-form-grid">
		<?php foreach ( EoSeo\Settings::verification_tools() as $eo_seo_key => $eo_seo_tool ) : ?>
			<div class="eo-seo-form-row eo-seo-form-row--wide">
				<label class="eo-seo-input-label" for="eo-seo-<?php echo esc_attr( $eo_seo_key ); ?>"><?php echo esc_html( $eo_seo_tool['label'] ); ?></label>
				<input type="text" id="eo-seo-<?php echo esc_attr( $eo_seo_key ); ?>" class="eo-seo-input eo-seo-input--code" name="<?php echo esc_attr( $option . '[verification][' . $eo_seo_key . ']' ); ?>" value="<?php echo esc_attr( $settings['verification'][ $eo_seo_key ] ?? '' ); ?>" placeholder="<?php echo esc_attr( '<meta name="' . $eo_seo_tool['meta'] . '" content="…" />' ); ?>" />
				<p class="eo-seo-help"><?php echo esc_html( $eo_seo_help[ $eo_seo_key ] ?? '' ); ?></p>
			</div>
		<?php endforeach; ?>
	</div>
</section>
