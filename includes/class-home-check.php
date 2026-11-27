<?php
/**
 * Check of the home page as search engines get it (request of the site to itself).
 *
 * @package EoSeo
 */

namespace EoSeo;

defined( 'ABSPATH' ) || exit;

/**
 * Loads the home page of the site (and its http:// address) and reads its HTML: one title,
 * one meta description, one canonical, no noindex, one H1, language and viewport, and the
 * redirection of http to https. The request goes to the site itself only, from the dashboard
 * button or the nightly maintenance; the result is kept a day.
 */
final class Home_Check {

	/**
	 * Option holding the last result.
	 */
	public const OPTION = 'eo_seo_home_check';

	/**
	 * Last result.
	 *
	 * @return array{time: int, items: array<int, array{id: string, status: string, label: string, message: string}>}|null
	 *         Null when the home page was never checked.
	 */
	public static function last(): ?array {
		$result = get_option( self::OPTION );

		return is_array( $result ) && isset( $result['time'], $result['items'] ) ? $result : null;
	}

	/**
	 * Checks the home page now.
	 *
	 * @return array{time: int, items: array<int, array{id: string, status: string, label: string, message: string}>}
	 */
	public static function run(): array {
		$items = array();
		$url   = home_url( '/' );
		$args  = array(
			'timeout'     => 15,
			'redirection' => 3,
			/** This filter is documented in wp-includes/class-wp-http-streams.php */
			'sslverify'   => (bool) apply_filters( 'https_local_ssl_verify', false ), // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WordPress core filter of the loopback requests.
			'headers'     => array( 'Cache-Control' => 'no-cache' ),
		);

		$response = wp_remote_get( $url, $args );
		$code     = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );

		if ( 200 !== $code ) {
			$items[] = self::item(
				'home_status',
				'error',
				__( 'Home page', 'eo-seo' ),
				is_wp_error( $response )
					/* translators: %s: error message. */
					? sprintf( __( 'The home page could not be loaded: %s', 'eo-seo' ), $response->get_error_message() )
					/* translators: %d: HTTP status code. */
					: sprintf( __( 'The home page answers with the HTTP code %d instead of 200.', 'eo-seo' ), $code )
			);
		} else {
			$items = self::read_html( (string) wp_remote_retrieve_body( $response ) );
		}

		$items[] = self::https_redirect( $args );

		$result = array(
			'time'  => time(),
			'items' => $items,
		);
		update_option( self::OPTION, $result, false );

		return $result;
	}

	/**
	 * Checks of the HTML of the home page.
	 *
	 * @param string $html HTML.
	 * @return array<int, array{id: string, status: string, label: string, message: string}>
	 */
	public static function read_html( string $html ): array {
		$items = array();
		$head  = preg_match( '#<head\b.*?</head>#is', $html, $match ) ? $match[0] : $html;

		$titles  = preg_match_all( '#<title\b[^>]*>(.*?)</title>#is', $head, $title_matches );
		$title   = $titles ? trim( wp_strip_all_tags( html_entity_decode( $title_matches[1][0], ENT_QUOTES, 'UTF-8' ) ) ) : '';
		$items[] = 1 === $titles && '' !== $title
			/* translators: %s: title of the home page. */
			? self::item( 'home_title', 'good', __( 'Title of the home page', 'eo-seo' ), sprintf( __( 'One title: "%s".', 'eo-seo' ), $title ) )
			: self::item(
				'home_title',
				'error',
				__( 'Title of the home page', 'eo-seo' ),
				$titles > 1
					/* translators: %d: number of title tags. */
					? sprintf( __( '%d title tags: the theme or another plugin prints one too.', 'eo-seo' ), $titles )
					: __( 'No title: the theme must support the "title-tag" feature.', 'eo-seo' )
			);

		$descriptions = preg_match_all( '#<meta\s[^>]*name=["\']description["\'][^>]*>#i', $head );
		$items[]      = self::item(
			'home_description',
			1 === $descriptions ? 'good' : ( $descriptions > 1 ? 'error' : 'warning' ),
			__( 'Meta description of the home page', 'eo-seo' ),
			1 === $descriptions
				? __( 'One meta description.', 'eo-seo' )
				: ( $descriptions > 1
					/* translators: %d: number of meta descriptions. */
					? sprintf( __( '%d meta descriptions: another plugin or the theme prints one too.', 'eo-seo' ), $descriptions )
					: __( 'No meta description: set the description template of the home page (Settings › Search appearance).', 'eo-seo' ) )
		);

		$canonicals = preg_match_all( '#<link\s[^>]*rel=["\']canonical["\'][^>]*>#i', $head );
		$items[]    = self::item(
			'home_canonical',
			$canonicals > 1 ? 'error' : 'good',
			__( 'Canonical URL of the home page', 'eo-seo' ),
			$canonicals > 1
				/* translators: %d: number of canonical links. */
				? sprintf( __( '%d canonical links: search engines may ignore them all.', 'eo-seo' ), $canonicals )
				: __( 'No duplicate canonical link.', 'eo-seo' )
		);

		$noindex = (bool) preg_match( '#<meta\s[^>]*name=["\']robots["\'][^>]*content=["\'][^"\']*noindex#i', $head );
		$items[] = self::item(
			'home_noindex',
			$noindex ? 'error' : 'good',
			__( 'Indexing of the home page', 'eo-seo' ),
			$noindex ? __( 'The home page asks search engines not to index it (noindex).', 'eo-seo' ) : __( 'The home page may be indexed.', 'eo-seo' )
		);

		$h1      = preg_match_all( '#<h1\b#i', $html );
		$items[] = self::item(
			'home_h1',
			1 === $h1 ? 'good' : 'warning',
			__( 'H1 heading of the home page', 'eo-seo' ),
			1 === $h1
				? __( 'One H1 heading.', 'eo-seo' )
				: ( $h1
					/* translators: %d: number of H1 headings. */
					? sprintf( __( '%d H1 headings: keep only one (often the site title of the header is one too).', 'eo-seo' ), $h1 )
					: __( 'No H1 heading on the home page.', 'eo-seo' ) )
		);

		$lang     = (bool) preg_match( '#<html\b[^>]*\slang=["\'][^"\']+["\']#i', $html );
		$viewport = (bool) preg_match( '#<meta\s[^>]*name=["\']viewport["\']#i', $head );
		$items[]  = self::item(
			'home_basics',
			$lang && $viewport ? 'good' : 'warning',
			__( 'Language and mobile display', 'eo-seo' ),
			$lang && $viewport
				? __( 'The page declares its language and its mobile display (viewport).', 'eo-seo' )
				: implode(
					' ',
					array_filter(
						array(
							$lang ? '' : __( 'No lang attribute on the <html> tag.', 'eo-seo' ),
							$viewport ? '' : __( 'No viewport meta tag: the page is not adapted to mobiles.', 'eo-seo' ),
						)
					)
				)
		);

		return $items;
	}

	/**
	 * The http:// address must send a permanent redirection to the https:// one.
	 *
	 * @param array<string, mixed> $args Request arguments.
	 * @return array{id: string, status: string, label: string, message: string}
	 */
	private static function https_redirect( array $args ): array {
		$label = __( 'Redirection from http to https', 'eo-seo' );
		$home  = home_url( '/' );

		if ( ! str_starts_with( $home, 'https://' ) ) {
			return self::item( 'https_redirect', 'warning', $label, __( 'The site does not use HTTPS: nothing to redirect yet.', 'eo-seo' ) );
		}

		$response = wp_remote_head( 'http://' . substr( $home, 8 ), array_merge( $args, array( 'redirection' => 0 ) ) );
		if ( is_wp_error( $response ) ) {
			/* translators: %s: error message. */
			return self::item( 'https_redirect', 'warning', $label, sprintf( __( 'The http:// address could not be checked: %s', 'eo-seo' ), $response->get_error_message() ) );
		}

		$code     = (int) wp_remote_retrieve_response_code( $response );
		$location = (string) wp_remote_retrieve_header( $response, 'location' );
		if ( in_array( $code, array( 301, 308 ), true ) && str_starts_with( $location, 'https://' ) ) {
			return self::item( 'https_redirect', 'good', $label, __( 'The http:// address redirects permanently to https://.', 'eo-seo' ) );
		}

		return self::item(
			'https_redirect',
			'error',
			$label,
			in_array( $code, array( 302, 303, 307 ), true )
				? __( 'The http:// address redirects to https:// temporarily (302): the redirection must be permanent (301), on the server.', 'eo-seo' )
				/* translators: %d: HTTP status code. */
				: sprintf( __( 'The http:// address does not redirect to https:// (code %d): set up a 301 redirection on the server (hosting panel or .htaccess).', 'eo-seo' ), $code )
		);
	}

	/**
	 * One result.
	 *
	 * @param string $id      ID.
	 * @param string $status  good, warning or error.
	 * @param string $label   Label.
	 * @param string $message Message.
	 * @return array{id: string, status: string, label: string, message: string}
	 */
	private static function item( string $id, string $status, string $label, string $message ): array {
		return compact( 'id', 'status', 'label', 'message' );
	}
}
