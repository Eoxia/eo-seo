<?php
/**
 * View: toggle switch.
 *
 * @package EoSeo
 *
 * @var string $id      Input ID.
 * @var string $name    Input name.
 * @var bool   $checked Whether it is on.
 * @var string $label   Label.
 * @var string $help    Help text.
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="eo-seo-switch">
	<input type="hidden" name="<?php echo esc_attr( $name ); ?>" value="0" />
	<input type="checkbox" class="eo-seo-switch__input" role="switch" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" value="1" <?php checked( $checked ); ?> />
	<label class="eo-seo-switch__label" for="<?php echo esc_attr( $id ); ?>">
		<span class="eo-seo-switch__track" aria-hidden="true"></span>
		<span class="eo-seo-switch__text">
			<span><?php echo esc_html( $label ); ?></span>
			<?php if ( '' !== $help ) : ?>
				<small><?php echo esc_html( $help ); ?></small>
			<?php endif; ?>
		</span>
	</label>
</div>
