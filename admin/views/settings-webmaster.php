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

$eo_seo_tools = array(
	'google' => array( __( 'Google Search Console', 'eo-seo' ), 'google-site-verification' ),
	'bing'   => array( __( 'Bing Webmaster Tools', 'eo-seo' ), 'msvalidate.01' ),
);
?>
<section class="eo-seo-card">
	<header class="eo-seo-card__head">
		<h2 class="eo-seo-card__title"><?php esc_html_e( 'Site verification', 'eo-seo' ); ?></h2>
		<p><?php esc_html_e( 'Paste the verification code, or the whole <meta> tag: only the code is kept. The tag is added to the home page.', 'eo-seo' ); ?></p>
	</header>
	<div class="eo-seo-form-grid">
		<?php foreach ( $eo_seo_tools as $eo_seo_key => $eo_seo_tool ) : ?>
			<div class="eo-seo-form-row eo-seo-form-row--wide">
				<label class="eo-seo-input-label" for="eo-seo-<?php echo esc_attr( $eo_seo_key ); ?>"><?php echo esc_html( $eo_seo_tool[0] ); ?></label>
				<input type="text" id="eo-seo-<?php echo esc_attr( $eo_seo_key ); ?>" class="eo-seo-input eo-seo-input--code" name="<?php echo esc_attr( $option . '[verification][' . $eo_seo_key . ']' ); ?>" value="<?php echo esc_attr( $settings['verification'][ $eo_seo_key ] ); ?>" placeholder="<?php echo esc_attr( '<meta name="' . $eo_seo_tool[1] . '" content="…" />' ); ?>" />
			</div>
		<?php endforeach; ?>
	</div>
</section>
