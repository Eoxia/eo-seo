<?php
/**
 * Image field (media library picker).
 *
 * @package EoSeo
 */

namespace EoSeo\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Hidden attachment ID, preview and buttons; driven by assets/js/admin.js.
 */
final class Media_Field {

	/**
	 * Prints the field.
	 *
	 * @param string $name     Input name.
	 * @param int    $image_id Attachment ID.
	 * @param string $size     "small" (logo) or "large" (sharing image).
	 */
	public static function render( string $name, int $image_id, string $size = 'small' ): void {
		$preview = $image_id ? wp_get_attachment_image( $image_id, 'large' === $size ? 'medium_large' : 'thumbnail' ) : '';
		?>
		<div class="eo-seo-media eo-seo-media--<?php echo esc_attr( $size ); ?><?php echo $image_id ? ' has-image' : ''; ?>" data-eo-seo-media>
			<input type="hidden" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( (string) $image_id ); ?>" data-eo-seo-media-input />
			<button type="button" class="eo-seo-media__preview" data-eo-seo-media-select aria-label="<?php esc_attr_e( 'Choose an image', 'eo-seo' ); ?>">
				<span data-eo-seo-media-preview><?php echo wp_kses_post( $preview ); ?></span>
				<span class="eo-seo-media__empty"><?php esc_html_e( 'Choose an image', 'eo-seo' ); ?></span>
			</button>
			<button type="button" class="eo-seo-button eo-seo-button--link eo-seo-button--danger" data-eo-seo-media-remove<?php echo $image_id ? '' : ' hidden'; ?>><?php esc_html_e( 'Remove', 'eo-seo' ); ?></button>
		</div>
		<?php
	}
}
