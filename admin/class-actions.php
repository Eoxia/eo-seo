<?php
/**
 * Form handlers of the admin screens (admin-post.php).
 *
 * @package EoSeo
 */

namespace EoSeo\Admin;

use EoSeo\Not_Found_Log;
use EoSeo\Proposals;
use EoSeo\Redirections;
use EoSeo\Status;

defined( 'ABSPATH' ) || exit;

/**
 * Handles the forms: every handler checks the capability and the nonce, then redirects back.
 */
final class Actions {

	/**
	 * Hooks.
	 */
	public static function init(): void {
		add_action( 'admin_post_eo_seo_redirection_save', array( self::class, 'redirection_save' ) );
		add_action( 'admin_post_eo_seo_redirection_delete', array( self::class, 'redirection_delete' ) );
		add_action( 'admin_post_eo_seo_redirections_import', array( self::class, 'redirections_import' ) );
		add_action( 'admin_post_eo_seo_redirections_export', array( self::class, 'redirections_export' ) );
		add_action( 'admin_post_eo_seo_404', array( self::class, 'not_found' ) );
		add_action( 'admin_post_eo_seo_proposal', array( self::class, 'proposal' ) );
		add_action( 'admin_post_eo_seo_fill_status', array( self::class, 'fill_status' ) );
	}

	/**
	 * Saves a redirection (also from the 404 screen).
	 */
	public static function redirection_save(): void {
		self::check_capability( 'manage_options' );
		check_admin_referer( 'eo_seo_redirection_save' );

		$from   = isset( $_POST['source'] ) ? sanitize_text_field( wp_unslash( $_POST['source'] ) ) : '';
		$to     = isset( $_POST['target'] ) ? sanitize_text_field( wp_unslash( $_POST['target'] ) ) : '';
		$type   = isset( $_POST['type'] ) ? absint( $_POST['type'] ) : 301;
		$origin = isset( $_POST['origin'] ) && '404' === $_POST['origin'] ? '404' : 'manual';

		$redirection = Redirections::validate( $from, $to, $type );
		$result      = is_wp_error( $redirection ) ? $redirection : Redirections::save( $redirection, $origin );

		if ( is_wp_error( $result ) ) {
			self::notice( 'error', $result->get_error_message() );
		} else {
			self::notice( 'success', __( 'Redirection saved.', 'eo-seo' ) . ( $redirection['warnings'] ? ' ' . implode( ' ', $redirection['warnings'] ) : '' ) );
		}

		self::back();
	}

	/**
	 * Deletes redirections.
	 */
	public static function redirection_delete(): void {
		self::check_capability( 'manage_options' );
		check_admin_referer( 'eo_seo_redirection_delete' );

		$ids = isset( $_POST['ids'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['ids'] ) ) : array();
		Redirections::delete( $ids );
		self::notice( 'success', __( 'Redirections deleted.', 'eo-seo' ) );
		self::back();
	}

	/**
	 * Imports a CSV file of redirections.
	 */
	public static function redirections_import(): void {
		self::check_capability( 'manage_options' );
		check_admin_referer( 'eo_seo_redirections_import' );

		// Not unslashed: wp_unslash() would break Windows temporary paths; is_uploaded_file() validates it.
		$file = isset( $_FILES['csv']['tmp_name'] ) ? sanitize_text_field( $_FILES['csv']['tmp_name'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash
		$name = isset( $_FILES['csv']['name'] ) ? sanitize_file_name( wp_unslash( $_FILES['csv']['name'] ) ) : '';

		if ( '' === $file || ! is_uploaded_file( $file ) || 'csv' !== strtolower( pathinfo( $name, PATHINFO_EXTENSION ) ) ) {
			self::notice( 'error', __( 'Choose a CSV file.', 'eo-seo' ) );
			self::back();
		}

		$result  = Redirections::import_csv( (string) file_get_contents( $file ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local uploaded file.
		$message = sprintf(
			/* translators: %d: number of redirections. */
			_n( '%d redirection imported.', '%d redirections imported.', $result['saved'], 'eo-seo' ),
			$result['saved']
		);
		if ( $result['errors'] ) {
			$message .= '<br>' . implode( '<br>', array_map( 'esc_html', array_slice( $result['errors'], 0, 20 ) ) );
		}

		self::notice( $result['errors'] ? 'warning' : 'success', $message );
		self::back();
	}

	/**
	 * Downloads the redirections as CSV.
	 */
	public static function redirections_export(): void {
		self::check_capability( 'manage_options' );
		check_admin_referer( 'eo_seo_redirections_export' );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="redirections-' . gmdate( 'Y-m-d' ) . '.csv"' );
		echo Redirections::export_csv(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSV download, not HTML.
		exit;
	}

	/**
	 * Marks 404 rows as handled, back to handle, or deletes them.
	 */
	public static function not_found(): void {
		self::check_capability( 'manage_options' );
		check_admin_referer( 'eo_seo_404' );

		$ids    = isset( $_POST['ids'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['ids'] ) ) : array();
		$status = isset( $_POST['status'] ) ? sanitize_key( $_POST['status'] ) : '';
		Not_Found_Log::update( $ids, $status );
		self::notice( 'success', __( '404 log updated.', 'eo-seo' ) );
		self::back();
	}

	/**
	 * Applies or rejects proposals.
	 */
	public static function proposal(): void {
		self::check_capability( 'edit_posts' );
		check_admin_referer( 'eo_seo_proposal' );

		$ids    = isset( $_POST['ids'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['ids'] ) ) : array();
		$action = isset( $_POST['proposal_action'] ) ? sanitize_key( $_POST['proposal_action'] ) : '';
		$force  = ! empty( $_POST['force'] );
		$done   = 0;
		$errors = array();

		foreach ( $ids as $id ) {
			$result = 'reject' === $action ? Proposals::reject( $id ) : Proposals::apply( $id, $force );
			if ( is_wp_error( $result ) ) {
				$errors[] = esc_html( $result->get_error_message() );
			} else {
				++$done;
			}
		}

		$message = 'reject' === $action
			/* translators: %d: number of proposals. */
			? sprintf( _n( '%d proposal rejected.', '%d proposals rejected.', $done, 'eo-seo' ), $done )
			/* translators: %d: number of proposals. */
			: sprintf( _n( '%d proposal applied.', '%d proposals applied.', $done, 'eo-seo' ), $done );
		if ( $errors ) {
			$message .= '<br>' . implode( '<br>', $errors );
		}

		self::notice( $errors ? 'warning' : 'success', $message );
		self::back();
	}

	/**
	 * Computes the indicator of the contents that have none.
	 */
	public static function fill_status(): void {
		self::check_capability( 'manage_options' );
		check_admin_referer( 'eo_seo_fill_status' );

		$count = Status::fill_missing( 100 );
		/* translators: %d: number of contents. */
		self::notice( 'success', sprintf( _n( '%d content analyzed.', '%d contents analyzed.', $count, 'eo-seo' ), $count ) );
		self::back();
	}

	/**
	 * Message shown after the redirect.
	 *
	 * @param string $type    success, warning, error or info.
	 * @param string $message HTML message (already escaped).
	 */
	public static function notice( string $type, string $message ): void {
		set_transient(
			'eo_seo_notice_' . get_current_user_id(),
			array(
				'type'    => $type,
				'message' => $message,
			),
			MINUTE_IN_SECONDS
		);
	}

	/**
	 * Message of the last action, removed once read.
	 *
	 * @return array{type: string, message: string}|null
	 */
	public static function pull_notice(): ?array {
		$key    = 'eo_seo_notice_' . get_current_user_id();
		$notice = get_transient( $key );
		if ( ! is_array( $notice ) ) {
			return null;
		}

		delete_transient( $key );

		return $notice;
	}

	/**
	 * Stops unless the user has the capability (each handler then checks its nonce).
	 *
	 * @param string $capability Capability.
	 */
	private static function check_capability( string $capability ): void {
		if ( ! current_user_can( $capability ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'eo-seo' ), 403 );
		}
	}

	/**
	 * Back to the screen of the form.
	 */
	private static function back(): void {
		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : Admin::page_url( 'eo-seo' ) );
		exit;
	}
}
