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
?>
<section class="eo-seo-card">
	<header class="eo-seo-card__head">
		<h2 class="eo-seo-card__title"><?php esc_html_e( 'Claim the site', 'eo-seo' ); ?></h2>
		<p><?php esc_html_e( 'These tools ask you to prove that the site is yours. Copy the verification code they give you and paste it here: EO SEO adds it to the home page for you.', 'eo-seo' ); ?></p>
	</header>
	<div class="eo-seo-verifications">
		<?php foreach ( EoSeo\Settings::verification_tools() as $eo_seo_key => $eo_seo_tool ) : ?>
			<div class="eo-seo-form-row">
				<label class="eo-seo-input-label" for="eo-seo-<?php echo esc_attr( $eo_seo_key ); ?>"><?php echo esc_html( $eo_seo_tool['label'] ); ?></label>
				<input type="text" id="eo-seo-<?php echo esc_attr( $eo_seo_key ); ?>" class="eo-seo-input" name="<?php echo esc_attr( $option . '[verification][' . $eo_seo_key . ']' ); ?>" value="<?php echo esc_attr( $settings['verification'][ $eo_seo_key ] ?? '' ); ?>" placeholder="<?php esc_attr_e( 'Paste the verification code', 'eo-seo' ); ?>" autocomplete="off" spellcheck="false" />
				<p class="eo-seo-help">
					<?php
					printf(
						/* translators: %s: link to the tool (e.g. Google Search Console). */
						esc_html__( 'Get your code in %s.', 'eo-seo' ),
						'<a href="' . esc_url( $eo_seo_tool['url'] ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $eo_seo_tool['tool'] ) . '<span class="screen-reader-text"> ' . esc_html__( '(opens in a new tab)', 'eo-seo' ) . '</span></a>'
					);
					?>
					<?php echo esc_html( $eo_seo_tool['purpose'] ); ?>
				</p>
			</div>
		<?php endforeach; ?>
	</div>
</section>
