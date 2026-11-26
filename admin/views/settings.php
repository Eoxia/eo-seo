<?php
/**
 * View: settings (side menu + one form per section).
 *
 * @package EoSeo
 *
 * @var array<string, array{label: string, description: string}> $tabs Sections.
 * @var string                 $tab        Current section.
 * @var array<string, mixed>   $settings   Settings.
 * @var string                 $option     Option name.
 * @var \WP_Post_Type[]        $post_types Post types.
 * @var \WP_Taxonomy[]         $taxonomies Taxonomies.
 * @var array<string, string>  $variables  Template variables.
 * @var string[]               $separators Separators.
 * @var array<string, string>  $contexts   Template contexts.
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap eo-seo-app">
	<?php EoSeo\Admin\Ui::header( 'eo-seo-settings', __( 'SEO settings', 'eo-seo' ) ); ?>

	<div class="eo-seo-settings">
		<nav class="eo-seo-sidenav" aria-label="<?php esc_attr_e( 'Settings sections', 'eo-seo' ); ?>">
			<?php foreach ( $tabs as $eo_seo_tab => $eo_seo_info ) : ?>
				<a href="<?php echo esc_url( EoSeo\Admin\Admin::page_url( 'eo-seo-settings', array( 'tab' => $eo_seo_tab ) ) ); ?>" class="eo-seo-sidenav__link<?php echo $eo_seo_tab === $tab ? ' is-current' : ''; ?>"<?php echo $eo_seo_tab === $tab ? ' aria-current="page"' : ''; ?>>
					<strong><?php echo esc_html( $eo_seo_info['label'] ); ?></strong>
					<span><?php echo esc_html( $eo_seo_info['description'] ); ?></span>
				</a>
			<?php endforeach; ?>
		</nav>

		<form method="post" action="<?php echo esc_url( admin_url( 'options.php' ) ); ?>" class="eo-seo-settings__form">
			<?php settings_fields( 'eo_seo' ); ?>
			<input type="hidden" name="<?php echo esc_attr( $option ); ?>[_tab]" value="<?php echo esc_attr( $tab ); ?>" />
			<?php include EO_SEO_DIR . 'admin/views/settings-' . $tab . '.php'; ?>
			<div class="eo-seo-savebar">
				<button type="submit" class="eo-seo-button eo-seo-button--primary"><?php esc_html_e( 'Save the changes', 'eo-seo' ); ?></button>
			</div>
		</form>
	</div>
</div>
