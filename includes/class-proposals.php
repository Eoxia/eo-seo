<?php
/**
 * Changes proposed by the AI, applied by a human.
 *
 * @package EoSeo
 */

namespace EoSeo;

defined( 'ABSPATH' ) || exit;

/**
 * Stores the proposals (private post type, before / after values) and applies or rejects them.
 * No ability can apply a proposal: only a person, from the "Proposals" screen or EO Tools' "To validate" tab.
 */
final class Proposals {

	public const POST_TYPE = 'eo_seo_proposal';

	/**
	 * Meta holding the proposal payload.
	 */
	private const PAYLOAD_META = '_eo_seo_payload';

	/**
	 * Days before a pending proposal expires.
	 */
	public const EXPIRATION_DAYS = 30;

	/**
	 * Hooks.
	 */
	public static function init(): void {
		add_action( 'init', array( self::class, 'register_post_type' ) );
		add_filter( 'eo_tools_mcp_proposal_types', array( self::class, 'eo_tools_types' ) );
	}

	/**
	 * Registers the private post type.
	 */
	public static function register_post_type(): void {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'          => array(
					'name'          => __( 'SEO proposals', 'eo-seo' ),
					'singular_name' => __( 'SEO proposal', 'eo-seo' ),
				),
				'public'          => false,
				'show_ui'         => false,
				'show_in_rest'    => false,
				'rewrite'         => false,
				'query_var'       => false,
				'supports'        => array( 'title' ),
				'capability_type' => 'post',
				'map_meta_cap'    => true,
			)
		);
	}

	/**
	 * Proposes new SEO fields for a content or a term.
	 *
	 * @param string               $object_type post or term.
	 * @param int                  $object_id   ID.
	 * @param array<string, mixed> $fields      New values keyed by field name.
	 * @param string               $reason      Why the AI proposes it.
	 * @return int|\WP_Error Proposal ID.
	 */
	public static function propose_fields( string $object_type, int $object_id, array $fields, string $reason = '' ): int|\WP_Error {
		$definitions = Fields::all();

		if ( 'post' === $object_type ) {
			$object = get_post( $object_id );
			if ( ! $object || ! isset( Settings::post_types()[ $object->post_type ] ) ) {
				/* translators: %d: ID. */
				return new \WP_Error( 'eo_seo_not_found', sprintf( __( 'Content %d not found.', 'eo-seo' ), $object_id ) );
			}
			$current = Fields::get_post_values( $object_id );
			$title   = get_the_title( $object );
		} else {
			$object = get_term( $object_id );
			if ( ! $object instanceof \WP_Term || ! isset( Settings::taxonomies()[ $object->taxonomy ] ) ) {
				/* translators: %d: ID. */
				return new \WP_Error( 'eo_seo_not_found', sprintf( __( 'Term %d not found.', 'eo-seo' ), $object_id ) );
			}
			$current = Fields::get_term_values( $object_id );
			$title   = $object->name;
		}

		$changes = array();
		foreach ( $fields as $name => $value ) {
			if ( ! isset( $definitions[ $name ] ) ) {
				/* translators: %s: field name. */
				return new \WP_Error( 'eo_seo_unknown_field', sprintf( __( 'Unknown field: %s', 'eo-seo' ), $name ) );
			}
			$value = Fields::sanitize( $definitions[ $name ], $value );
			if ( $value !== $current[ $name ] ) {
				$changes[ $name ] = array(
					'before' => $current[ $name ],
					'after'  => $value,
				);
			}
		}

		if ( ! $changes ) {
			return new \WP_Error( 'eo_seo_no_change', __( 'The proposed values are the current ones.', 'eo-seo' ) );
		}

		return self::insert(
			$title,
			array(
				'kind'        => 'fields',
				'object_type' => $object_type,
				'object_id'   => $object_id,
				'changes'     => $changes,
				'reason'      => sanitize_textarea_field( $reason ),
			)
		);
	}

	/**
	 * Proposes a redirection.
	 *
	 * @param string $from   Source.
	 * @param string $to     Target.
	 * @param int    $type   301, 302 or 410.
	 * @param string $reason Why the AI proposes it.
	 * @return array{id: int, warnings: string[]}|\WP_Error
	 */
	public static function propose_redirection( string $from, string $to, int $type = 301, string $reason = '' ): array|\WP_Error {
		$redirection = Redirections::validate( $from, $to, $type );
		if ( is_wp_error( $redirection ) ) {
			return $redirection;
		}

		$id = self::insert(
			Url::source_url( $redirection['source'] ),
			array(
				'kind'        => 'redirection',
				'redirection' => $redirection,
				'reason'      => sanitize_textarea_field( $reason ),
			)
		);

		return is_wp_error( $id ) ? $id : array(
			'id'       => $id,
			'warnings' => $redirection['warnings'],
		);
	}

	/**
	 * Saves a proposal.
	 *
	 * @param string               $title   Label.
	 * @param array<string, mixed> $payload Data.
	 * @return int|\WP_Error
	 */
	private static function insert( string $title, array $payload ): int|\WP_Error {
		$id = wp_insert_post(
			array(
				'post_type'   => self::POST_TYPE,
				'post_status' => 'pending',
				'post_title'  => wp_slash( $title ),
				'post_author' => get_current_user_id(),
			),
			true
		);
		if ( is_wp_error( $id ) ) {
			return $id;
		}

		update_post_meta( $id, self::PAYLOAD_META, wp_slash( $payload ) );

		return (int) $id;
	}

	/**
	 * Pending proposals.
	 *
	 * @param string $kind fields, redirection or empty for all.
	 * @return array<int, array<string, mixed>>
	 */
	public static function pending( string $kind = '' ): array {
		$posts = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => 'pending',
				'posts_per_page' => 200,
				'orderby'        => 'date',
				'order'          => 'ASC',
			)
		);

		$items = array();
		foreach ( $posts as $post ) {
			$item = self::get( $post->ID );
			if ( $item && ( '' === $kind || $kind === $item['kind'] ) ) {
				$items[] = $item;
			}
		}

		return $items;
	}

	/**
	 * Number of pending proposals.
	 */
	public static function count_pending(): int {
		$counts = wp_count_posts( self::POST_TYPE );

		return isset( $counts->pending ) ? (int) $counts->pending : 0;
	}

	/**
	 * One proposal, with its conflict state.
	 *
	 * @param int $id Proposal ID.
	 * @return array<string, mixed>|null
	 */
	public static function get( int $id ): ?array {
		$post = get_post( $id );
		if ( ! $post || self::POST_TYPE !== $post->post_type ) {
			return null;
		}

		$payload = get_post_meta( $id, self::PAYLOAD_META, true );
		if ( ! is_array( $payload ) ) {
			return null;
		}

		return $payload + array(
			'id'       => $post->ID,
			'title'    => $post->post_title,
			'status'   => $post->post_status,
			'created'  => (int) get_post_time( 'U', true, $post ),
			'author'   => (int) $post->post_author,
			'conflict' => self::conflict( $payload ),
		);
	}

	/**
	 * Why a proposal can no longer be applied as is (the value changed since).
	 *
	 * @param array<string, mixed> $payload Data.
	 * @return string Empty when there is no conflict.
	 */
	private static function conflict( array $payload ): string {
		if ( 'fields' === $payload['kind'] ) {
			$current = 'post' === $payload['object_type'] ? Fields::get_post_values( (int) $payload['object_id'] ) : Fields::get_term_values( (int) $payload['object_id'] );
			foreach ( $payload['changes'] as $name => $change ) {
				if ( ( $current[ $name ] ?? null ) !== $change['before'] ) {
					/* translators: %s: field label. */
					return sprintf( __( '"%s" changed since the proposal.', 'eo-seo' ), Fields::all()[ $name ]['label'] ?? $name );
				}
			}
		}

		return '';
	}

	/**
	 * Applies a proposal (human action).
	 *
	 * @param int  $id    Proposal ID.
	 * @param bool $force Apply even when the value changed since.
	 * @return true|\WP_Error
	 */
	public static function apply( int $id, bool $force = false ): bool|\WP_Error {
		$item = self::get( $id );
		if ( ! $item || 'pending' !== $item['status'] ) {
			return new \WP_Error( 'eo_seo_proposal', __( 'This proposal is no longer pending.', 'eo-seo' ) );
		}
		if ( ! self::can_apply( $item ) ) {
			return new \WP_Error( 'eo_seo_forbidden', __( 'You are not allowed to apply this proposal.', 'eo-seo' ) );
		}
		if ( '' !== $item['conflict'] && ! $force ) {
			return new \WP_Error( 'eo_seo_conflict', $item['conflict'] );
		}

		if ( 'fields' === $item['kind'] ) {
			Fields::save( $item['object_type'], (int) $item['object_id'], array_map( static fn( $c ) => $c['after'], $item['changes'] ) );
			if ( 'post' === $item['object_type'] ) {
				$post = get_post( (int) $item['object_id'] );
				if ( $post ) {
					Status::refresh( $post );
				}
			}
		} else {
			// Checked again: other redirections may have been created since.
			$redirection = Redirections::validate( Url::source_url( $item['redirection']['source'] ), Url::target_url( (string) $item['redirection']['target'] ), (int) $item['redirection']['type'] );
			$result      = is_wp_error( $redirection ) ? $redirection : Redirections::save( $redirection, 'ai' );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}

		wp_update_post(
			array(
				'ID'          => $id,
				'post_status' => 'publish',
			)
		);

		return true;
	}

	/**
	 * Rejects a proposal (deleted).
	 *
	 * @param int $id Proposal ID.
	 * @return true|\WP_Error
	 */
	public static function reject( int $id ): bool|\WP_Error {
		$item = self::get( $id );
		if ( ! $item || 'pending' !== $item['status'] ) {
			return new \WP_Error( 'eo_seo_proposal', __( 'This proposal is no longer pending.', 'eo-seo' ) );
		}
		if ( ! self::can_apply( $item ) ) {
			return new \WP_Error( 'eo_seo_forbidden', __( 'You are not allowed to reject this proposal.', 'eo-seo' ) );
		}

		wp_delete_post( $id, true );

		return true;
	}

	/**
	 * Whether the current user may apply a proposal.
	 *
	 * @param array<string, mixed> $item Proposal.
	 */
	public static function can_apply( array $item ): bool {
		if ( 'redirection' === $item['kind'] ) {
			return current_user_can( 'manage_options' );
		}

		return 'post' === $item['object_type']
			? current_user_can( 'edit_post', (int) $item['object_id'] )
			: current_user_can( 'edit_term', (int) $item['object_id'] );
	}

	/**
	 * Deletes the expired proposals and the applied ones older than the expiration delay.
	 */
	public static function purge_expired(): void {
		$ids = get_posts(
			array(
				'post_type'      => self::POST_TYPE,
				'post_status'    => array( 'pending', 'publish' ),
				'posts_per_page' => 200,
				'fields'         => 'ids',
				'date_query'     => array(
					array(
						'before' => self::EXPIRATION_DAYS . ' days ago',
					),
				),
			)
		);

		foreach ( $ids as $id ) {
			wp_delete_post( (int) $id, true );
		}
	}

	/**
	 * HTML summary of a proposal (before / after).
	 *
	 * @param array<string, mixed> $item Proposal.
	 */
	public static function details_html( array $item ): string {
		$html = '<ul>';

		if ( 'fields' === $item['kind'] ) {
			$fields = Fields::all();
			foreach ( $item['changes'] as $name => $change ) {
				$html .= sprintf(
					'<li><strong>%1$s</strong> : <del>%2$s</del> → <ins>%3$s</ins></li>',
					esc_html( $fields[ $name ]['label'] ?? $name ),
					esc_html( self::display_value( $change['before'] ) ),
					esc_html( self::display_value( $change['after'] ) )
				);
			}
		} else {
			$redirection = $item['redirection'];
			$html       .= sprintf(
				'<li>%1$s → %2$s (%3$d)</li>',
				esc_html( Url::source_url( $redirection['source'] ) ),
				esc_html( 410 === (int) $redirection['type'] ? __( 'gone', 'eo-seo' ) : Url::target_url( (string) $redirection['target'] ) ),
				(int) $redirection['type']
			);
		}

		if ( '' !== (string) $item['reason'] ) {
			/* translators: %s: reason given by the AI. */
			$html .= '<li><em>' . esc_html( sprintf( __( 'Reason: %s', 'eo-seo' ), $item['reason'] ) ) . '</em></li>';
		}

		return $html . '</ul>';
	}

	/**
	 * Readable form of a field value.
	 *
	 * @param mixed $value Value.
	 */
	private static function display_value( mixed $value ): string {
		if ( is_bool( $value ) ) {
			return $value ? __( 'yes', 'eo-seo' ) : __( 'no', 'eo-seo' );
		}

		return '' === (string) $value || 0 === $value ? __( '(empty)', 'eo-seo' ) : (string) $value;
	}

	/**
	 * Declares the proposals to EO Tools, which lists them in its "To validate" tab.
	 *
	 * @param array<string, array<string, mixed>> $types Proposal types.
	 * @return array<string, array<string, mixed>>
	 */
	public static function eo_tools_types( array $types ): array {
		$items = static fn( string $kind ) => static fn() => array_map(
			static fn( $item ) => array(
				'id'      => (string) $item['id'],
				'title'   => $item['title'],
				'details' => self::details_html( $item ),
				'created' => $item['created'],
				'warning' => $item['conflict'],
			),
			self::pending( $kind )
		);

		$types['eo-seo-fields']       = array(
			'label'       => __( 'SEO fields', 'eo-seo' ),
			'description' => __( 'SEO title, description and other fields of published contents and terms.', 'eo-seo' ),
			'provider'    => 'EO SEO',
			'capability'  => 'edit_others_posts',
			'items'       => $items( 'fields' ),
			'apply'       => static fn( $id ) => self::apply( (int) $id ),
			'reject'      => static fn( $id ) => self::reject( (int) $id ),
		);
		$types['eo-seo-redirections'] = array(
			'label'       => __( 'Redirections', 'eo-seo' ),
			'description' => __( 'Created in EO SEO once validated.', 'eo-seo' ),
			'provider'    => 'EO SEO',
			'capability'  => 'manage_options',
			'items'       => $items( 'redirection' ),
			'apply'       => static fn( $id ) => self::apply( (int) $id ),
			'reject'      => static fn( $id ) => self::reject( (int) $id ),
		);

		return $types;
	}
}
