<?php
/**
 * View: SEO fields of a term.
 *
 * @package EoSeo
 *
 * @var \WP_Term                            $term    Term.
 * @var array<string, array<string, mixed>> $fields  Fields.
 * @var array<string, mixed>                $values  Values.
 * @var array<string, string>               $preview Resolved title and description.
 */

defined( 'ABSPATH' ) || exit;
?>
<h2><?php esc_html_e( 'SEO', 'eo-seo' ); ?></h2>
<?php wp_nonce_field( 'eo_seo_term_' . $term->term_id, 'eo_seo_term_nonce' ); ?>
<div class="eo-seo-serp" aria-label="<?php esc_attr_e( 'Search result preview', 'eo-seo' ); ?>">
	<span class="eo-seo-serp__url"><?php echo esc_html( (string) get_term_link( $term ) ); ?></span>
	<span class="eo-seo-serp__title"><?php echo esc_html( $preview['title'] ); ?></span>
	<span class="eo-seo-serp__description"><?php echo esc_html( $preview['description'] ); ?></span>
</div>
<table class="form-table" role="presentation">
	<?php foreach ( $fields as $eo_seo_name => $eo_seo_field ) : ?>
		<?php
		$eo_seo_id    = 'eo-seo-' . $eo_seo_name;
		$eo_seo_input = 'eo_seo[' . $eo_seo_name . ']';
		$eo_seo_count = isset( $eo_seo_field['max'] ) ? $eo_seo_field['min'] . ',' . $eo_seo_field['max'] : '';
		?>
		<tr class="form-field">
			<th scope="row"><label for="<?php echo esc_attr( $eo_seo_id ); ?>"><?php echo esc_html( $eo_seo_field['label'] ); ?></label></th>
			<td>
				<?php if ( 'checkbox' === $eo_seo_field['input'] ) : ?>
					<input type="checkbox" id="<?php echo esc_attr( $eo_seo_id ); ?>" name="<?php echo esc_attr( $eo_seo_input ); ?>" value="1" <?php checked( (bool) $values[ $eo_seo_name ] ); ?> />
				<?php elseif ( 'image' === $eo_seo_field['input'] ) : ?>
					<?php EoSeo\Admin\Media_Field::render( $eo_seo_input, (int) $values[ $eo_seo_name ] ); ?>
				<?php elseif ( 'textarea' === $eo_seo_field['input'] ) : ?>
					<textarea id="<?php echo esc_attr( $eo_seo_id ); ?>" name="<?php echo esc_attr( $eo_seo_input ); ?>" rows="3" <?php echo $eo_seo_count ? 'data-eo-seo-counter="' . esc_attr( $eo_seo_count ) . '"' : ''; ?>><?php echo esc_textarea( (string) $values[ $eo_seo_name ] ); ?></textarea>
				<?php else : ?>
					<input type="<?php echo 'url' === $eo_seo_field['input'] ? 'url' : 'text'; ?>" id="<?php echo esc_attr( $eo_seo_id ); ?>" name="<?php echo esc_attr( $eo_seo_input ); ?>" value="<?php echo esc_attr( (string) $values[ $eo_seo_name ] ); ?>" <?php echo $eo_seo_count ? 'data-eo-seo-counter="' . esc_attr( $eo_seo_count ) . '"' : ''; ?> />
				<?php endif; ?>
				<?php if ( '' !== $eo_seo_field['help'] ) : ?>
					<p class="description"><?php echo esc_html( $eo_seo_field['help'] ); ?></p>
				<?php endif; ?>
			</td>
		</tr>
	<?php endforeach; ?>
</table>
