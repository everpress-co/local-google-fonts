<?php

namespace EverPress;

defined( 'ABSPATH' ) || exit;

/**
 * Google Fonts URL validation and safe HTTP helpers.
 *
 * @since [Unreleased]
 */
class LGF_Url {

	const STYLESHEET_HOST = 'fonts.googleapis.com';
	const FONT_HOST       = 'fonts.gstatic.com';
	const MAX_CSS_BYTES   = 2097152;
	const MAX_FONT_BYTES  = 5242880;

	/**
	 * Normalize a URL for validation (protocol-relative to https).
	 *
	 * @since [Unreleased]
	 *
	 * @param string $url Raw URL.
	 * @return string
	 */
	public static function normalize_url( $url ) {
		$url = trim( (string) $url );
		if ( 0 === stripos( $url, '//' ) ) {
			$url = 'https:' . $url;
		}
		return $url;
	}

	/**
	 * Whether the URL is an allowed Google Fonts CSS stylesheet endpoint.
	 *
	 * @since [Unreleased]
	 *
	 * @param string $url Stylesheet URL.
	 * @return bool
	 */
	public static function is_allowed_stylesheet_url( $url ) {
		$url = self::normalize_url( $url );
		if ( '' === $url ) {
			return false;
		}

		$parts = wp_parse_url( $url );
		if ( false === $parts || ! is_array( $parts ) ) {
			return false;
		}

		if ( ! empty( $parts['user'] ) || ! empty( $parts['pass'] ) ) {
			return false;
		}

		if ( isset( $parts['port'] ) ) {
			return false;
		}

		if ( ! isset( $parts['scheme'] ) || 'https' !== strtolower( $parts['scheme'] ) ) {
			return false;
		}

		if ( ! isset( $parts['host'] ) || self::STYLESHEET_HOST !== strtolower( $parts['host'] ) ) {
			return false;
		}

		if ( ! isset( $parts['path'] ) || 0 !== strpos( $parts['path'], '/css' ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Whether the URL is an allowed Google Fonts static file host.
	 *
	 * @since [Unreleased]
	 *
	 * @param string $url Font file URL.
	 * @return bool
	 */
	public static function is_allowed_font_file_url( $url ) {
		$url = self::normalize_url( $url );
		if ( '' === $url ) {
			return false;
		}

		$parts = wp_parse_url( $url );
		if ( false === $parts || ! is_array( $parts ) ) {
			return false;
		}

		if ( ! empty( $parts['user'] ) || ! empty( $parts['pass'] ) ) {
			return false;
		}

		if ( isset( $parts['port'] ) ) {
			return false;
		}

		if ( ! isset( $parts['scheme'] ) || 'https' !== strtolower( $parts['scheme'] ) ) {
			return false;
		}

		if ( ! isset( $parts['host'] ) || self::FONT_HOST !== strtolower( $parts['host'] ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Perform a size-capped safe HTTP GET for stylesheet or font assets.
	 *
	 * @since [Unreleased]
	 *
	 * @param string $url  Request URL (must pass allowlist checks first).
	 * @param string $type Either `css` or `font`.
	 * @param array  $args Extra wp_safe_remote_get arguments (e.g. user-agent).
	 * @return array|\WP_Error
	 */
	public static function safe_remote_get( $url, $type = 'css', $args = array() ) {
		$limit = 'font' === $type ? self::MAX_FONT_BYTES : self::MAX_CSS_BYTES;

		$defaults = array(
			'timeout'              => 10,
			'redirection'          => 0,
			'reject_unsafe_urls'   => true,
			'limit_response_size'  => $limit,
		);

		return wp_safe_remote_get( $url, wp_parse_args( $args, $defaults ) );
	}

	/**
	 * Build a font face path under the plugin fonts directory and reject traversal.
	 *
	 * @since [Unreleased]
	 *
	 * @param string $id       Font set id (md5 hash).
	 * @param string $filename File name only.
	 * @return string|false Absolute path or false if invalid.
	 */
	public static function font_face_path( $id, $filename ) {
		$id = preg_replace( '/[^a-f0-9]/', '', strtolower( (string) $id ) );
		if ( 32 !== strlen( $id ) ) {
			return false;
		}

		$filename = sanitize_file_name( basename( (string) $filename ) );
		if ( '' === $filename ) {
			return false;
		}

		$base_dir = wp_normalize_path( LGF::get_folder() . '/' . $id );
		$path     = wp_normalize_path( $base_dir . '/' . $filename );
		$prefix   = $base_dir . '/';

		if ( 0 !== strpos( $path, $prefix ) ) {
			return false;
		}

		return $path;
	}

	/**
	 * Remove invalid entries from the discovered-fonts buffer.
	 *
	 * @since [Unreleased]
	 *
	 * @param bool $delete_dirs Whether to delete upload dirs for removed entries.
	 * @return int Number of entries removed.
	 */
	public static function scrub_font_buffer( $delete_dirs = false ) {
		$buffer  = get_option( 'local_google_fonts_buffer', array() );
		$removed = 0;
		$class   = LGF::get_instance();

		if ( ! is_array( $buffer ) ) {
			update_option( 'local_google_fonts_buffer', array() );
			return 0;
		}

		foreach ( $buffer as $handle => $entry ) {
			$src = isset( $entry['src'] ) ? $entry['src'] : '';
			if ( ! self::is_allowed_stylesheet_url( $src ) ) {
				if ( $delete_dirs && ! empty( $entry['id'] ) ) {
					$class->remove_set( $entry['id'] );
				}
				unset( $buffer[ $handle ] );
				++$removed;
			}
		}

		if ( $removed > 0 ) {
			update_option( 'local_google_fonts_buffer', $buffer );
		}

		return $removed;
	}
}
