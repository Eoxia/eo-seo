<?php
/**
 * View: settings, "Identity" section.
 *
 * @package EoSeo
 *
 * @var array<string, mixed> $settings Settings.
 * @var string               $option   Option name.
 */

defined( 'ABSPATH' ) || exit;

$eo_seo_identity = $settings['identity'];
$eo_seo_name     = $option . '[identity]';
$eo_seo_types    = array(
	'organization'   => __( 'Organization', 'eo-seo' ),
	'local_business' => __( 'Local business', 'eo-seo' ),
	'person'         => __( 'Person', 'eo-seo' ),
);
$eo_seo_address  = array(
	'street'      => __( 'Street address', 'eo-seo' ),
	'postal_code' => __( 'Postal code', 'eo-seo' ),
	'city'        => __( 'City', 'eo-seo' ),
	'country'     => __( 'Country', 'eo-seo' ),
	'phone'       => __( 'Phone', 'eo-seo' ),
);
?>
<section class="eo-seo-card">
	<header class="eo-seo-card__head">
		<h2 class="eo-seo-card__title"><?php esc_html_e( 'Who is behind the site', 'eo-seo' ); ?></h2>
		<p><?php esc_html_e( 'Used in the structured data read by search engines.', 'eo-seo' ); ?></p>
	</header>

	<fieldset class="eo-seo-segmented" data-eo-seo-identity-type>
		<legend class="screen-reader-text"><?php esc_html_e( 'The site represents', 'eo-seo' ); ?></legend>
		<?php foreach ( $eo_seo_types as $eo_seo_value => $eo_seo_label ) : ?>
			<label class="eo-seo-segmented__option">
				<input type="radio" name="<?php echo esc_attr( $eo_seo_name ); ?>[type]" value="<?php echo esc_attr( $eo_seo_value ); ?>" <?php checked( $eo_seo_identity['type'], $eo_seo_value ); ?> />
				<span><?php echo esc_html( $eo_seo_label ); ?></span>
			</label>
		<?php endforeach; ?>
	</fieldset>

	<div class="eo-seo-form-grid">
		<div class="eo-seo-form-row">
			<label class="eo-seo-input-label" for="eo-seo-identity-name"><?php esc_html_e( 'Name', 'eo-seo' ); ?></label>
			<input type="text" id="eo-seo-identity-name" class="eo-seo-input" name="<?php echo esc_attr( $eo_seo_name ); ?>[name]" value="<?php echo esc_attr( $eo_seo_identity['name'] ); ?>" placeholder="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>" />
		</div>
		<div class="eo-seo-form-row">
			<span class="eo-seo-input-label"><?php esc_html_e( 'Logo', 'eo-seo' ); ?></span>
			<?php EoSeo\Admin\Media_Field::render( $eo_seo_name . '[logo]', (int) $eo_seo_identity['logo'] ); ?>
			<p class="eo-seo-help"><?php esc_html_e( 'Defaults to the logo of the theme.', 'eo-seo' ); ?></p>
		</div>
	</div>
</section>

<section class="eo-seo-card" data-eo-seo-local-business<?php echo 'local_business' === $eo_seo_identity['type'] ? '' : ' hidden'; ?>>
	<header class="eo-seo-card__head">
		<h2 class="eo-seo-card__title"><?php esc_html_e( 'Address and phone', 'eo-seo' ); ?></h2>
		<p><?php esc_html_e( 'Shown by Google for a local business.', 'eo-seo' ); ?></p>
	</header>
	<div class="eo-seo-form-grid">
		<?php foreach ( $eo_seo_address as $eo_seo_key => $eo_seo_label ) : ?>
			<div class="eo-seo-form-row<?php echo 'street' === $eo_seo_key ? ' eo-seo-form-row--wide' : ''; ?>">
				<label class="eo-seo-input-label" for="eo-seo-identity-<?php echo esc_attr( $eo_seo_key ); ?>"><?php echo esc_html( $eo_seo_label ); ?></label>
				<input type="text" id="eo-seo-identity-<?php echo esc_attr( $eo_seo_key ); ?>" class="eo-seo-input" name="<?php echo esc_attr( $eo_seo_name . '[' . $eo_seo_key . ']' ); ?>" value="<?php echo esc_attr( $eo_seo_identity[ $eo_seo_key ] ); ?>" />
			</div>
		<?php endforeach; ?>
	</div>
</section>

<p class="eo-seo-help">
	<?php esc_html_e( 'The accounts of the site on the social networks are set in the "Social networks" section.', 'eo-seo' ); ?>
	<a href="<?php echo esc_url( EoSeo\Admin\Admin::page_url( 'eo-seo-settings', array( 'tab' => 'social' ) ) ); ?>"><?php esc_html_e( 'Set the accounts', 'eo-seo' ); ?></a>
</p>
