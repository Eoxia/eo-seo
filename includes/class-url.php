<?php
/**
 * Normalization of the addresses of the site.
 *
 * @package EoSeo
 */

namespace EoSeo;

defined( 'ABSPATH' ) || exit;

/**
 * Turns the addresses typed by people, found in contents or requested by visitors into one comparable form.
 */
final class Url {

	/**
	 * Path of the home page, without the trailing slash ("" at the root of the domain).
	 */
	public static function home_path(): string {
		return untrailingslashit( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ) );
	}

	/**
	 * Percent-encodes the non-ASCII characters, as browsers send them.
	 *
	 * @param string $path Path.
	 */
	public static function encode( string $path ): string {
		return (string) preg_replace_callback(
			'/[^\x00-\x7F]/',
			static fn( array $char ) => '%' . strtoupper( bin2hex( $char[0] ) ),
			rawurldecode( $path )
		);
	}

	/**
	 * Source form of an address: path from the domain root, without trailing slash, with the query string.
	 *
	 * @param string $url Full address of the site, or path ("/old-page/", relative to the home page).
	 * @return string|\WP_Error
	 */
	public static function normalize_source( string $url ): string|\WP_Error {
		$url   = trim( $url );
		$parts = wp_parse_url( $url );

		if ( '' === $url || false === $parts ) {
			return new \WP_Error( 'eo_seo_invalid_source', __( 'The address to redirect is empty or invalid.', 'eo-seo' ) );
		}

		if ( ! empty( $parts['host'] ) ) {
			if ( strtolower( $parts['host'] ) !== strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) ) {
				/* translators: %s: address. */
				return new \WP_Error( 'eo_seo_invalid_source', sprintf( __( '"%s" is not an address of this site.', 'eo-seo' ), $url ) );
			}
			$path = $parts['path'] ?? '/';
		} else {
			$path = '/' . ltrim( $parts['path'] ?? '', '/' );
			$home = self::home_path();
			if ( '' !== $home && ! str_starts_with( $path . '/', $home . '/' ) ) {
				$path = $home . $path;
			}
		}

		$path = self::encode( $path );
		$path = '/' === $path ? $path : untrailingslashit( $path );
		if ( ! empty( $parts['query'] ) ) {
			$path .= '?' . $parts['query'];
		}

		return esc_url_raw( $path );
	}

	/**
	 * Target form of an address: path relative to the home page, or full address of another site.
	 *
	 * @param string $url Address or path.
	 * @return string|\WP_Error
	 */
	public static function normalize_target( string $url ): string|\WP_Error {
		$url = trim( $url );
		if ( '' === $url ) {
			return new \WP_Error( 'eo_seo_invalid_target', __( 'The destination address is empty.', 'eo-seo' ) );
		}

		$parts = wp_parse_url( $url );
		if ( false === $parts ) {
			/* translators: %s: address. */
			return new \WP_Error( 'eo_seo_invalid_target', sprintf( __( 'Invalid destination address: %s', 'eo-seo' ), $url ) );
		}

		if ( ! empty( $parts['host'] ) ) {
			if ( ! wp_http_validate_url( $url ) ) {
				/* translators: %s: address. */
				return new \WP_Error( 'eo_seo_invalid_target', sprintf( __( 'Invalid destination address: %s', 'eo-seo' ), $url ) );
			}
			$home = home_url();
			if ( 0 !== stripos( $url, $home ) ) {
				return esc_url_raw( $url ); // Another site.
			}
			$url = substr( $url, strlen( $home ) );
		} else {
			$home = self::home_path();
			if ( '' !== $home && str_starts_with( '/' . ltrim( $url, '/' ) . '/', $home . '/' ) ) {
				$url = substr( '/' . ltrim( $url, '/' ), strlen( $home ) );
			}
		}

		return esc_url_raw( '/' . ltrim( self::encode( $url ), '/' ) );
	}

	/**
	 * Full address of a normalized target.
	 *
	 * @param string $target Result of normalize_target().
	 */
	public static function target_url( string $target ): string {
		return str_starts_with( $target, 'http' ) ? $target : home_url( $target );
	}

	/**
	 * Full address of a normalized source.
	 *
	 * @param string $source Result of normalize_source().
	 */
	public static function source_url( string $source ): string {
		$home = wp_parse_url( home_url() );

		return $home['scheme'] . '://' . $home['host'] . ( isset( $home['port'] ) ? ':' . $home['port'] : '' ) . $source;
	}

	/**
	 * Address requested by the visitor, in source form.
	 *
	 * @return string|null Null when it cannot be read.
	 */
	public static function current_source(): ?string {
		if ( ! isset( $_SERVER['REQUEST_URI'] ) ) {
			return null;
		}

		$source = self::normalize_source( esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) );

		return is_wp_error( $source ) ? null : $source;
	}
}
