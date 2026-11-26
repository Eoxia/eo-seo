<?php
/**
 * REST route of the editor panel (live analysis).
 *
 * @package EoSeo
 */

namespace EoSeo;

defined( 'ABSPATH' ) || exit;

/**
 * Analyzes the unsaved values of the editor. The fields themselves are saved by the
 * block editor as registered metas: no custom route writes anything.
 */
final class Rest {

	public const NAMESPACE = 'eo-seo/v1';

	/**
	 * Hooks.
	 */
	public static function init(): void {
		add_action( 'rest_api_init', array( self::class, 'register_routes' ) );
	}

	/**
	 * Registers the routes.
	 */
	public static function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/analyze/(?P<id>\d+)',
			array(
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => array( self::class, 'analyze' ),
				'permission_callback' => static fn( \WP_REST_Request $request ) => current_user_can( 'edit_post', (int) $request['id'] ),
				'args'                => array(
					'id'      => array(
						'type'     => 'integer',
						'required' => true,
					),
					'title'   => array( 'type' => 'string' ),
					'slug'    => array( 'type' => 'string' ),
					'content' => array( 'type' => 'string' ),
					'excerpt' => array( 'type' => 'string' ),
					'fields'  => array(
						'type'       => 'object',
						'properties' => Fields::json_schema(),
					),
				),
			)
		);
	}

	/**
	 * Analysis of the content being edited.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function analyze( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$post = get_post( (int) $request['id'] );
		if ( ! $post ) {
			return new \WP_Error( 'eo_seo_not_found', __( 'Content not found.', 'eo-seo' ), array( 'status' => 404 ) );
		}

		$overrides = array();
		foreach ( array( 'title', 'slug', 'content', 'excerpt', 'fields' ) as $key ) {
			if ( null !== $request[ $key ] ) {
				$overrides[ $key ] = $request[ $key ];
			}
		}

		$analysis = new Analysis( $post, $overrides );
		$result   = Checks::run( $analysis );

		return rest_ensure_response(
			$result + array(
				'preview' => array(
					'title'       => $analysis->seo_title,
					'description' => $analysis->description,
					'url'         => (string) get_permalink( $post ),
				),
			)
		);
	}
}
