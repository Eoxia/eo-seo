<?php
/**
 * View: one SEO field of the SEO box.
 *
 * @package EoSeo
 *
 * @var string               $name        Field name.
 * @var array<string, mixed> $field       Field definition.
 * @var mixed                $value       Value.
 * @var string               $placeholder Value given by the template when the field is empty.
 * @var string               $id          Input ID.
 * @var string               $input       Input name.
 */

defined( 'ABSPATH' ) || exit;

$eo_seo_counter = isset( $field['max'] ) ? $field['min'] . ',' . $field['max'] : '';
$eo_seo_preview = in_array( $name, array( 'title', 'description' ), true ) ? $name : '';
?>
<div class="eo-seo-box__field">
	<?php if ( 'checkbox' === $field['input'] ) : ?>
		<?php EoSeo\Admin\Ui::toggle( $input, (bool) $value, $field['label'], $field['help'] ); ?>
	<?php else : ?>
		<?php if ( 'image' === $field['input'] ) : ?>
			<span class="eo-seo-input-label"><?php echo esc_html( $field['label'] ); ?></span>
			<?php EoSeo\Admin\Media_Field::render( $input, (int) $value, 'large' ); ?>
		<?php else : ?>
			<label class="eo-seo-input-label" for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $field['label'] ); ?></label>
			<?php if ( 'textarea' === $field['input'] ) : ?>
				<textarea id="<?php echo esc_attr( $id ); ?>" class="eo-seo-input" rows="3" name="<?php echo esc_attr( $input ); ?>" placeholder="<?php echo esc_attr( $placeholder ); ?>"<?php echo $eo_seo_counter ? ' data-eo-seo-counter="' . esc_attr( $eo_seo_counter ) . '"' : ''; ?><?php echo $eo_seo_preview ? ' data-eo-seo-preview="' . esc_attr( $eo_seo_preview ) . '"' : ''; ?>><?php echo esc_textarea( (string) $value ); ?></textarea>
			<?php else : ?>
				<input type="<?php echo 'url' === $field['input'] ? 'url' : 'text'; ?>" id="<?php echo esc_attr( $id ); ?>" class="eo-seo-input" name="<?php echo esc_attr( $input ); ?>" value="<?php echo esc_attr( (string) $value ); ?>" placeholder="<?php echo esc_attr( $placeholder ); ?>"<?php echo $eo_seo_counter ? ' data-eo-seo-counter="' . esc_attr( $eo_seo_counter ) . '"' : ''; ?><?php echo $eo_seo_preview ? ' data-eo-seo-preview="' . esc_attr( $eo_seo_preview ) . '"' : ''; ?> />
			<?php endif; ?>
		<?php endif; ?>
		<?php if ( '' !== $field['help'] ) : ?>
			<p class="eo-seo-help"><?php echo esc_html( $field['help'] ); ?></p>
		<?php endif; ?>
	<?php endif; ?>
</div>
