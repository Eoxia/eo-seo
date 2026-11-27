<?php
/**
 * View: settings, "Search appearance" section.
 *
 * @package EoSeo
 *
 * @var array<string, mixed>  $settings   Settings.
 * @var string                $option     Option name.
 * @var array<string, string> $variables  Template variables.
 * @var string[]              $separators Separators.
 * @var array<string, string> $contexts   Template contexts.
 */

defined( 'ABSPATH' ) || exit;
?>
<section class="eo-seo-card">
	<header class="eo-seo-card__head">
		<h2 class="eo-seo-card__title"><?php esc_html_e( 'Title separator', 'eo-seo' ); ?></h2>
		<p>
			<?php
			/* translators: %s: name of the template variable (%sep%). */
			echo esc_html( sprintf( __( 'Placed between the title of the page and the name of the site (variable %s).', 'eo-seo' ), '%sep%' ) );
			?>
		</p>
	</header>
	<fieldset class="eo-seo-choices">
		<legend class="screen-reader-text"><?php esc_html_e( 'Title separator', 'eo-seo' ); ?></legend>
		<?php foreach ( $separators as $eo_seo_separator ) : ?>
			<label class="eo-seo-choice eo-seo-choice--square">
				<input type="radio" name="<?php echo esc_attr( $option ); ?>[separator]" value="<?php echo esc_attr( $eo_seo_separator ); ?>" <?php checked( $settings['separator'], $eo_seo_separator ); ?> />
				<span><?php echo esc_html( $eo_seo_separator ); ?></span>
			</label>
		<?php endforeach; ?>
	</fieldset>
</section>

<section class="eo-seo-card">
	<header class="eo-seo-card__head">
		<h2 class="eo-seo-card__title"><?php esc_html_e( 'Title and description templates', 'eo-seo' ); ?></h2>
		<p><?php esc_html_e( 'Used when a content or a term has no SEO title or description of its own. Click a variable to insert it in the field you are editing.', 'eo-seo' ); ?></p>
	</header>

	<div class="eo-seo-chips" data-eo-seo-variables>
		<?php foreach ( $variables as $eo_seo_variable => $eo_seo_help ) : ?>
			<button type="button" class="eo-seo-chip" data-eo-seo-variable="<?php echo esc_attr( $eo_seo_variable ); ?>" title="<?php echo esc_attr( $eo_seo_help ); ?>"><?php echo esc_html( $eo_seo_variable ); ?></button>
		<?php endforeach; ?>
	</div>

	<div class="eo-seo-templates">
		<?php foreach ( $contexts as $eo_seo_context => $eo_seo_label ) : ?>
			<?php
			$eo_seo_template = $settings['templates'][ $eo_seo_context ] ?? array(
				'title'       => '',
				'description' => '',
			);
			$eo_seo_id       = 'eo-seo-tpl-' . sanitize_html_class( str_replace( ':', '-', $eo_seo_context ) );
			?>
			<div class="eo-seo-template">
				<h3 class="eo-seo-template__name"><?php echo esc_html( $eo_seo_label ); ?></h3>
				<div class="eo-seo-template__fields">
					<label class="eo-seo-input-label" for="<?php echo esc_attr( $eo_seo_id ); ?>-title"><?php esc_html_e( 'Title', 'eo-seo' ); ?></label>
					<input type="text" id="<?php echo esc_attr( $eo_seo_id ); ?>-title" class="eo-seo-input eo-seo-input--code" data-eo-seo-template name="<?php echo esc_attr( $option . '[templates][' . $eo_seo_context . '][title]' ); ?>" value="<?php echo esc_attr( $eo_seo_template['title'] ); ?>" />
					<label class="eo-seo-input-label" for="<?php echo esc_attr( $eo_seo_id ); ?>-description"><?php esc_html_e( 'Description', 'eo-seo' ); ?></label>
					<input type="text" id="<?php echo esc_attr( $eo_seo_id ); ?>-description" class="eo-seo-input eo-seo-input--code" data-eo-seo-template name="<?php echo esc_attr( $option . '[templates][' . $eo_seo_context . '][description]' ); ?>" value="<?php echo esc_attr( $eo_seo_template['description'] ); ?>" />
				</div>
			</div>
		<?php endforeach; ?>
	</div>
</section>
