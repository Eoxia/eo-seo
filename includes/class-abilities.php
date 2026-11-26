<?php
/**
 * Abilities (WordPress Abilities API), served to the AI by EO Tools.
 *
 * @package EoSeo
 */

namespace EoSeo;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the eo-seo/* abilities. They are not public on MCP (mcp.public false): EO Tools
 * serves them with its switches and risk levels (meta.eo_tools). Schemas are static, as MCP
 * clients cache the tool list.
 */
final class Abilities {

	/**
	 * Hooks.
	 */
	public static function init(): void {
		add_action( 'wp_abilities_api_categories_init', array( self::class, 'register_category' ) );
		add_action( 'wp_abilities_api_init', array( self::class, 'register' ) );
	}

	/**
	 * Registers the category.
	 */
	public static function register_category(): void {
		wp_register_ability_category(
			'eo-seo',
			array(
				'label'       => __( 'SEO', 'eo-seo' ),
				'description' => __( 'Search engine optimization, by EO SEO.', 'eo-seo' ),
			)
		);
	}

	/**
	 * Registers the abilities.
	 */
	public static function register(): void {
		$id_or_url = array(
			'type'       => 'object',
			'properties' => array(
				'id'  => array(
					'type'        => 'integer',
					'description' => 'ID of the content.',
				),
				'url' => array(
					'type'        => 'string',
					'description' => 'Address of the content, if the ID is unknown.',
				),
			),
		);

		self::add(
			'audit-site',
			__( 'SEO review of the site', 'eo-seo' ),
			'Site-wide SEO review: settings, indexing, contents per indicator level, 404 errors to handle, images without alternative text. Each item has a status (good, warning, error) and the admin page where a human fixes it.',
			array(
				'type'       => 'object',
				'properties' => (object) array(),
			),
			array( self::class, 'audit_site' ),
			'edit_posts',
			'read',
			__( 'Reads the SEO review of the site (the EO SEO dashboard).', 'eo-seo' )
		);

		self::add(
			'audit-post',
			__( 'SEO audit of a content', 'eo-seo' ),
			'SEO audit of one content: SEO fields (stored and resolved by the templates), indicator (problem, improve, good, noindex) with every check, outline of the headings, internal and external links, incoming links and the focus keyphrase report.',
			$id_or_url,
			array( self::class, 'audit_post' ),
			'edit_posts',
			'read',
			__( 'Reads the SEO audit of one content.', 'eo-seo' )
		);

		self::add(
			'list-issues',
			__( 'SEO issues', 'eo-seo' ),
			'What to fix, 20 per page. type "post" (default): published contents whose SEO indicator has the given level, with their failed checks (details with eo-seo/audit-post). type "term": categories, tags and other terms at that level (thin or empty archive, no introduction text, no meta description, duplicate title); fix them with eo-seo/propose-seo (type term). type "media": images of the media library without alternative text (level ignored); fix them with eo-tools/update-media.',
			array(
				'type'       => 'object',
				'properties' => array(
					'type'      => array(
						'type'        => 'string',
						'enum'        => array( 'post', 'term', 'media' ),
						'description' => 'post (default), term or media.',
					),
					'level'     => array(
						'type'        => 'string',
						'enum'        => array( 'problem', 'improve' ),
						'description' => 'problem (red) or improve (orange). Default: problem.',
					),
					'post_type' => array(
						'type'        => 'string',
						'description' => 'type post: limit to one post type (post, page…).',
					),
					'taxonomy'  => array(
						'type'        => 'string',
						'description' => 'type term: limit to one taxonomy (category, post_tag…).',
					),
					'page'      => array(
						'type'    => 'integer',
						'minimum' => 1,
					),
				),
			),
			array( self::class, 'list_issues' ),
			'edit_posts',
			'read',
			__( 'Lists the contents and terms in red or orange with their failed checks, and the images without alternative text.', 'eo-seo' )
		);

		self::add(
			'list-404',
			__( '404 errors', 'eo-seo' ),
			'Addresses visitors or search engines requested and that do not exist (most viewed first), with the last referrer and a suggested destination. Fix them with eo-seo/propose-redirections.',
			array(
				'type'       => 'object',
				'properties' => array(
					'page' => array(
						'type'    => 'integer',
						'minimum' => 1,
					),
				),
			),
			array( self::class, 'list_404' ),
			'manage_options',
			'read',
			__( 'Reads the 404 log of EO SEO.', 'eo-seo' )
		);

		self::add(
			'get-settings',
			__( 'EO SEO settings', 'eo-seo' ),
			'Settings of EO SEO: title and description templates, indexing per post type and taxonomy, robots.txt rules, site identity, social and webmaster settings.',
			array(
				'type'       => 'object',
				'properties' => (object) array(),
			),
			array( self::class, 'get_settings' ),
			'manage_options',
			'read',
			__( 'Reads the EO SEO settings.', 'eo-seo' )
		);

		self::add(
			'update-draft-seo',
			__( 'SEO fields of a draft', 'eo-seo' ),
			'Writes the SEO fields of a content that is not published (draft, pending): nothing changes on the live site. For published contents use eo-seo/propose-seo.',
			array(
				'type'       => 'object',
				'required'   => array( 'id', 'fields' ),
				'properties' => array(
					'id'     => array( 'type' => 'integer' ),
					'fields' => array(
						'type'       => 'object',
						'properties' => Fields::json_schema(),
					),
				),
			),
			array( self::class, 'update_draft_seo' ),
			'edit_posts',
			'write',
			__( 'Writes the SEO fields of unpublished drafts only.', 'eo-seo' )
		);

		self::add(
			'propose-seo',
			__( 'Propose SEO fields', 'eo-seo' ),
			'Proposes new SEO fields for published contents and terms, in batch. Nothing changes until a human applies each proposal in EO SEO › Proposals (or EO Tools › To validate).',
			array(
				'type'       => 'object',
				'required'   => array( 'items' ),
				'properties' => array(
					'items' => array(
						'type'     => 'array',
						'maxItems' => 50,
						'items'    => array(
							'type'       => 'object',
							'required'   => array( 'type', 'id', 'fields' ),
							'properties' => array(
								'type'   => array(
									'type' => 'string',
									'enum' => array( 'post', 'term' ),
								),
								'id'     => array( 'type' => 'integer' ),
								'fields' => array(
									'type'       => 'object',
									'properties' => Fields::json_schema(),
								),
								'reason' => array(
									'type'        => 'string',
									'description' => 'Why, in one sentence, for the person who validates.',
								),
							),
						),
					),
				),
			),
			array( self::class, 'propose_seo' ),
			'edit_posts',
			'sensitive',
			__( 'Prepares changes of the SEO fields of published contents, applied by a human.', 'eo-seo' )
		);

		self::add(
			'propose-redirections',
			__( 'Propose redirections', 'eo-seo' ),
			'Proposes redirections (301 permanent, 302 temporary, 410 gone). Chains are refused: redirect straight to the final address. Nothing changes until a human applies them.',
			array(
				'type'       => 'object',
				'required'   => array( 'items' ),
				'properties' => array(
					'items' => array(
						'type'     => 'array',
						'maxItems' => 100,
						'items'    => array(
							'type'       => 'object',
							'required'   => array( 'from' ),
							'properties' => array(
								'from'   => array(
									'type'        => 'string',
									'description' => 'Address or path to redirect.',
								),
								'to'     => array(
									'type'        => 'string',
									'description' => 'Destination (not needed for a 410).',
								),
								'type'   => array(
									'type' => 'integer',
									'enum' => Redirections::TYPES,
								),
								'reason' => array( 'type' => 'string' ),
							),
						),
					),
				),
			),
			array( self::class, 'propose_redirections' ),
			'manage_options',
			'sensitive',
			__( 'Prepares redirections, created once a human applies them.', 'eo-seo' )
		);
	}

	/**
	 * Registers one ability.
	 *
	 * @param string               $name        Name without the eo-seo/ prefix.
	 * @param string               $label       Label.
	 * @param string               $description Description read by the AI (English, not translated: a stable schema).
	 * @param array<string, mixed> $input       Input schema.
	 * @param callable             $callback    Execute callback.
	 * @param string               $capability  Capability required.
	 * @param string               $risk        read, write or sensitive (EO Tools levels).
	 * @param string               $summary     Summary for the administrator.
	 */
	private static function add( string $name, string $label, string $description, array $input, callable $callback, string $capability, string $risk, string $summary ): void {
		wp_register_ability(
			'eo-seo/' . $name,
			array(
				'label'               => $label,
				'description'         => $description,
				'category'            => 'eo-seo',
				'input_schema'        => $input,
				'execute_callback'    => $callback,
				'permission_callback' => static fn() => current_user_can( $capability ),
				'meta'                => array(
					'annotations' => array(
						'readonly'    => 'read' === $risk,
						'destructive' => false,
						'idempotent'  => 'read' === $risk,
					),
					'mcp'         => array( 'public' => false ),
					'eo_tools'    => array(
						'risk'     => $risk,
						'summary'  => $summary,
						'provider' => 'EO SEO',
					),
				),
			)
		);
	}

	/**
	 * Execute: eo-seo/audit-site.
	 *
	 * @return array<string, mixed>
	 */
	public static function audit_site(): array {
		return array(
			'items'              => Site_Audit::run(),
			'contents_by_level'  => Status::count_by_level(),
			'terms_by_level'     => Status::count_terms_by_level(),
			'pending_proposals'  => Proposals::count_pending(),
			'other_seo_plugins'  => Compat::active_plugins(),
			'scan'               => Site_Scan::state(),
		);
	}

	/**
	 * Execute: eo-seo/audit-post.
	 *
	 * @param array<string, mixed>|null $input Input.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function audit_post( ?array $input = null ): array|\WP_Error {
		$post = self::find_post( (array) $input );
		if ( is_wp_error( $post ) ) {
			return $post;
		}

		$analysis = new Analysis( $post );
		$result   = Checks::run( $analysis );

		return array(
			'id'          => $post->ID,
			'title'       => $analysis->post_title,
			'url'         => (string) get_permalink( $post ),
			'status'      => $post->post_status,
			'post_type'   => $post->post_type,
			'fields'      => $analysis->values,
			'resolved'    => array(
				'seo_title'   => $analysis->seo_title,
				'description' => $analysis->description,
				'noindex'     => $analysis->noindex,
			),
			'indicator'   => array(
				'level'  => $result['level'],
				'points' => $result['passed'] . '/' . $result['total'],
			),
			'checks'      => $result['checks'],
			'word_count'  => $analysis->word_count,
			'headings'    => $analysis->headings,
			'links'       => array(
				'internal' => array_values( array_map( static fn( $l ) => $l['href'], array_filter( $analysis->links, static fn( $l ) => $l['internal'] ) ) ),
				'external' => array_values( array_map( static fn( $l ) => $l['href'], array_filter( $analysis->links, static fn( $l ) => ! $l['internal'] ) ) ),
				'incoming' => 'publish' === $post->post_status ? Link_Checker::has_incoming_link( $post ) : null,
			),
			'keyword'     => '' === $analysis->keyword ? null : array(
				'keyphrase'      => $analysis->keyword,
				'in_seo_title'   => $analysis->contains_keyword( $analysis->seo_title ),
				'in_title'       => $analysis->contains_keyword( $analysis->post_title ),
				'in_description' => $analysis->contains_keyword( $analysis->description ),
				'in_intro'       => $analysis->contains_keyword( $analysis->intro ),
				'in_slug'        => str_contains( $analysis->slug, sanitize_title( $analysis->keyword ) ),
				'in_headings'    => (bool) array_filter( $analysis->headings, static fn( $h ) => $analysis->contains_keyword( $h['text'] ) ),
			),
		);
	}

	/**
	 * Execute: eo-seo/list-issues.
	 *
	 * @param array<string, mixed>|null $input Input.
	 * @return array<string, mixed>
	 */
	public static function list_issues( ?array $input = null ): array {
		$input = (array) $input;
		$level = in_array( $input['level'] ?? '', array( Checks::LEVEL_PROBLEM, Checks::LEVEL_IMPROVE ), true ) ? $input['level'] : Checks::LEVEL_PROBLEM;
		$page  = max( 1, (int) ( $input['page'] ?? 1 ) );

		if ( 'term' === ( $input['type'] ?? '' ) ) {
			return self::list_term_issues( $level, (string) ( $input['taxonomy'] ?? '' ), $page );
		}
		if ( 'media' === ( $input['type'] ?? '' ) ) {
			return self::list_media_issues( $page );
		}

		$types = array_keys( Settings::post_types() );
		if ( ! empty( $input['post_type'] ) && in_array( $input['post_type'], $types, true ) ) {
			$types = array( $input['post_type'] );
		}

		$query = new \WP_Query(
			array(
				'post_type'      => $types,
				'post_status'    => 'publish',
				'posts_per_page' => 20,
				'paged'          => $page,
				'meta_key'       => Fields::STATUS_META, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Paginated, on demand.
				'meta_value'     => $level, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Paginated, on demand.
			)
		);

		$items = array();
		foreach ( $query->posts as $post ) {
			$result  = Checks::run( new Analysis( $post ) );
			$items[] = array(
				'id'     => $post->ID,
				'title'  => get_the_title( $post ),
				'url'    => (string) get_permalink( $post ),
				'points' => $result['passed'] . '/' . $result['total'],
				'failed' => array_values( array_map( static fn( $c ) => $c['id'] . ': ' . $c['message'], array_filter( $result['checks'], static fn( $c ) => ! $c['passed'] ) ) ),
			);
		}

		return array(
			'type'  => 'post',
			'level' => $level,
			'total' => (int) $query->found_posts,
			'pages' => (int) $query->max_num_pages,
			'items' => $items,
		);
	}

	/**
	 * Terms at a level, with their failed checks.
	 *
	 * @param string $level    problem or improve.
	 * @param string $taxonomy Taxonomy, empty for every handled one.
	 * @param int    $page     Page number.
	 * @return array<string, mixed>
	 */
	private static function list_term_issues( string $level, string $taxonomy, int $page ): array {
		$taxonomies = array_keys( Settings::taxonomies() );
		if ( '' !== $taxonomy && in_array( $taxonomy, $taxonomies, true ) ) {
			$taxonomies = array( $taxonomy );
		}

		$args = array(
			'taxonomy'   => $taxonomies,
			'hide_empty' => false,
			'meta_key'   => Fields::STATUS_META, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Paginated, on demand.
			'meta_value' => $level, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Paginated, on demand.
		);
		$total = (int) wp_count_terms( $args );
		$terms = get_terms(
			$args + array(
				'number' => 20,
				'offset' => ( $page - 1 ) * 20,
			)
		);

		$items = array();
		foreach ( is_wp_error( $terms ) ? array() : $terms as $term ) {
			$result  = Term_Checks::run( new Term_Analysis( $term ) );
			$link    = get_term_link( $term );
			$items[] = array(
				'id'       => $term->term_id,
				'name'     => $term->name,
				'taxonomy' => $term->taxonomy,
				'url'      => is_wp_error( $link ) ? '' : $link,
				'contents' => (int) $term->count,
				'points'   => $result['passed'] . '/' . $result['total'],
				'failed'   => array_values( array_map( static fn( $c ) => $c['id'] . ': ' . $c['message'], array_filter( $result['checks'], static fn( $c ) => ! $c['passed'] ) ) ),
			);
		}

		return array(
			'type'  => 'term',
			'level' => $level,
			'total' => $total,
			'pages' => (int) ceil( $total / 20 ),
			'items' => $items,
		);
	}

	/**
	 * Images of the media library without alternative text.
	 *
	 * @param int $page Page number.
	 * @return array<string, mixed>
	 */
	private static function list_media_issues( int $page ): array {
		$query = new \WP_Query(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'post_mime_type' => 'image',
				'posts_per_page' => 20,
				'paged'          => $page,
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Paginated, on demand.
					'relation' => 'OR',
					array(
						'key'     => '_wp_attachment_image_alt',
						'compare' => 'NOT EXISTS',
					),
					array(
						'key'   => '_wp_attachment_image_alt',
						'value' => '',
					),
				),
			)
		);

		$items = array();
		foreach ( $query->posts as $attachment ) {
			$items[] = array(
				'id'       => $attachment->ID,
				'title'    => get_the_title( $attachment ),
				'url'      => (string) wp_get_attachment_url( $attachment->ID ),
				'used_in'  => $attachment->post_parent ? (string) get_permalink( $attachment->post_parent ) : '',
			);
		}

		return array(
			'type'  => 'media',
			'total' => (int) $query->found_posts,
			'pages' => (int) $query->max_num_pages,
			'items' => $items,
			'fix'   => 'eo-tools/update-media',
		);
	}

	/**
	 * Execute: eo-seo/list-404.
	 *
	 * @param array<string, mixed>|null $input Input.
	 * @return array<string, mixed>
	 */
	public static function list_404( ?array $input = null ): array {
		$result = Not_Found_Log::query( Not_Found_Log::STATUS_NEW, max( 1, (int) ( $input['page'] ?? 1 ) ), 50 );

		return array(
			'total' => $result['total'],
			'items' => array_map(
				static fn( $row ) => array(
					'url'       => Url::source_url( $row->url ),
					'views'     => (int) $row->hits,
					'last_seen' => $row->last_seen,
					'referrer'  => $row->referrer,
					'suggested' => Not_Found_Log::suggest_target( $row->url ),
				),
				$result['rows']
			),
		);
	}

	/**
	 * Execute: eo-seo/get-settings.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_settings(): array {
		return Settings::all() + array( 'template_variables' => Variables::available() );
	}

	/**
	 * Execute: eo-seo/update-draft-seo.
	 *
	 * @param array<string, mixed>|null $input Input.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function update_draft_seo( ?array $input = null ): array|\WP_Error {
		$post = self::find_post( (array) $input );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		if ( in_array( $post->post_status, array( 'publish', 'future', 'private' ), true ) ) {
			return new \WP_Error( 'eo_seo_published', 'This content is live: use eo-seo/propose-seo, a human will apply the change.' );
		}
		if ( ! current_user_can( 'edit_post', $post->ID ) ) {
			return new \WP_Error( 'eo_seo_forbidden', __( 'You are not allowed to edit this content.', 'eo-seo' ) );
		}

		$fields = array_intersect_key( (array) ( $input['fields'] ?? array() ), Fields::all() );
		Fields::save( 'post', $post->ID, $fields );
		$result = Status::refresh( $post );

		return array(
			'id'        => $post->ID,
			'fields'    => Fields::get_post_values( $post->ID ),
			'indicator' => $result['level'] . ' ' . $result['passed'] . '/' . $result['total'],
		);
	}

	/**
	 * Execute: eo-seo/propose-seo.
	 *
	 * @param array<string, mixed>|null $input Input.
	 * @return array<string, mixed>
	 */
	public static function propose_seo( ?array $input = null ): array {
		$created = array();
		$errors  = array();

		foreach ( (array) ( $input['items'] ?? array() ) as $index => $item ) {
			$type = 'term' === ( $item['type'] ?? '' ) ? 'term' : 'post';
			$id   = (int) ( $item['id'] ?? 0 );
			$can  = 'term' === $type ? current_user_can( 'edit_term', $id ) : current_user_can( 'edit_post', $id );
			if ( ! $can ) {
				$errors[] = array(
					'index' => $index,
					'error' => __( 'You are not allowed to edit this item.', 'eo-seo' ),
				);
				continue;
			}

			$result = Proposals::propose_fields( $type, $id, (array) ( $item['fields'] ?? array() ), (string) ( $item['reason'] ?? '' ) );
			if ( is_wp_error( $result ) ) {
				$errors[] = array(
					'index' => $index,
					'error' => $result->get_error_message(),
				);
			} else {
				$created[] = $result;
			}
		}

		return array(
			'proposals'  => $created,
			'errors'     => $errors,
			'validation' => Admin\Admin::page_url( 'eo-seo-proposals' ),
			'note'       => 'Nothing changed on the site: a human must apply these proposals.',
		);
	}

	/**
	 * Execute: eo-seo/propose-redirections.
	 *
	 * @param array<string, mixed>|null $input Input.
	 * @return array<string, mixed>
	 */
	public static function propose_redirections( ?array $input = null ): array {
		$created = array();
		$errors  = array();

		foreach ( (array) ( $input['items'] ?? array() ) as $index => $item ) {
			$result = Proposals::propose_redirection( (string) ( $item['from'] ?? '' ), (string) ( $item['to'] ?? '' ), (int) ( $item['type'] ?? 301 ), (string) ( $item['reason'] ?? '' ) );
			if ( is_wp_error( $result ) ) {
				$errors[] = array(
					'index' => $index,
					'error' => $result->get_error_message(),
				);
			} else {
				$created[] = $result;
			}
		}

		return array(
			'proposals'  => $created,
			'errors'     => $errors,
			'validation' => Admin\Admin::page_url( 'eo-seo-proposals' ),
			'note'       => 'Nothing changed on the site: a human must apply these proposals.',
		);
	}

	/**
	 * Content from an ID or an address.
	 *
	 * @param array<string, mixed> $input Input with id or url.
	 * @return \WP_Post|\WP_Error
	 */
	private static function find_post( array $input ): \WP_Post|\WP_Error {
		$id = (int) ( $input['id'] ?? 0 );
		if ( ! $id && ! empty( $input['url'] ) ) {
			$id = url_to_postid( (string) $input['url'] );
		}

		$post = $id ? get_post( $id ) : null;
		if ( ! $post || ! isset( Settings::post_types()[ $post->post_type ] ) ) {
			return new \WP_Error( 'eo_seo_not_found', __( 'Content not found.', 'eo-seo' ) );
		}
		if ( ! current_user_can( 'read_post', $post->ID ) ) {
			return new \WP_Error( 'eo_seo_forbidden', __( 'You are not allowed to read this content.', 'eo-seo' ) );
		}

		return $post;
	}
}
