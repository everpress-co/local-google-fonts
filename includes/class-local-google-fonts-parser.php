<?php

/*
Thanks to https://github.com/WPTT/webfont-loader for parsing help
*/

namespace EverPress;

defined( 'ABSPATH' ) || exit;

class LGF_Parser {

	private $src;
	private $id;
	private $styles;
	private $remote_styles;
	private $info;
	private $format;


	public function __construct( $src, $format = 'woff2' ) {

		$this->set_src( $src );
		$this->set_format( $format );
	}

	public function parse( $local_only = false ) {

		$result              = array();
		$this->remote_styles = '';
		$this->styles        = '';
		$folder              = LGF::get_folder();
		$folder_url          = LGF::get_folder_url();

		$formats = $this->format;
		if ( $local_only ) {
			$formats = array( 'woff2' );
		}

		foreach ( $formats as $format ) {
			$remote_styles = $this->get_remote_styleheet( $format, $local_only );
			if ( is_wp_error( $remote_styles ) ) {
				continue;
			}

			$font_faces = explode( '@font-face', $remote_styles );

			$styles = $remote_styles;

			// Loop all our font-face declarations.
			foreach ( $font_faces as $font_face ) {

				// Make sure we only process styles inside this declaration.
				$style = explode( '}', $font_face )[0];

				// Sanity check.
				if ( false === strpos( $style, 'font-family' ) ) {
					continue;
				}

				// Get an array of our font-families.
				preg_match_all( '/font-family.*?\;/', $style, $matched_font_families );

				// Get an array of our font-files.
				preg_match_all( '/url\(.*?\)/i', $style, $matched_font_files );

				// Get an array of our font-families.
				preg_match_all( '/font-style.*?\;/', $style, $matched_font_styles );

				// Get an array of our font-families.
				preg_match_all( '/font-weight.*?\;/', $style, $matched_font_weights );

				// Get an array of our font-families.
				preg_match_all( '/unicode-range.*?\;/', $style, $matched_unicode_ranges );

				// Get the font-family name.
				$font_family = 'unknown';
				$sanitized   = 'unknown';
				if ( isset( $matched_font_families[0] ) && isset( $matched_font_families[0][0] ) ) {
					$font_family = rtrim( ltrim( $matched_font_families[0][0], 'font-family:' ), ';' );
					$font_family = trim( str_replace( array( "'", ';' ), '', $font_family ) );
					$sanitized   = sanitize_key( strtolower( str_replace( ' ', '-', $font_family ) ) );
				}

				// Make sure the font-family is set in our array.
				if ( ! isset( $result[ $sanitized ] ) ) {
					$result[ $sanitized ] = array(
						'name'       => $font_family,
						'stylesheet' => $folder_url . '/' . $this->id . '/font.css',
						'variants'   => array(),
						'total'      => 0,
						'loaded'     => 0,
						'filesize'   => 0,
						'faces'      => array(),
					);
				}

				// Get files for this font-family and add them to the array.
				foreach ( $matched_font_files as $i => $match ) {

					// Sanity check.
					if ( ! isset( $match[0] ) ) {
						continue;
					}

					// Add the file URL.
					$remote_url = rtrim( ltrim( $match[0], 'url(' ), ')' );

					// Make sure to convert relative URLs to absolute.
					$remote_url = $this->get_absolute_path( $remote_url );

					if ( ! LGF_Url::is_allowed_font_file_url( $remote_url ) ) {
						continue;
					}

					$version = '';
					if ( preg_match( '/v(\d+)/', $remote_url, $m ) ) {
						$version = 'v' . $m[1];
					}

					$font_style = null;
					if ( isset( $matched_font_styles[ $i ][0] ) ) {
						$font_style = rtrim( ltrim( $matched_font_styles[ $i ][0], 'font-style:' ), ';' );
						$font_style = trim( str_replace( array( "'", ';' ), '', $font_style ) );
					}

					$font_weight = null;
					if ( isset( $matched_font_weights[ $i ][0] ) ) {
						$font_weight = rtrim( ltrim( $matched_font_weights[ $i ][0], 'font-weight:' ), ';' );
						$font_weight = trim( str_replace( array( "'", ';' ), '', $font_weight ) );
					}

					$unicode_range = null;
					if ( isset( $matched_unicode_ranges[ $i ][0] ) ) {
						$unicode_range = rtrim( ltrim( $matched_unicode_ranges[ $i ][0], 'unicode-range:' ), ';' );
						$unicode_range = trim( str_replace( array( "'", ';' ), '', $unicode_range ) );
					}

					$subset = $this->get_subset_by_range( $unicode_range );
					$basename = sprintf(
						'%s-%s-%s-%s-%s.%s',
						sanitize_file_name( $sanitized ),
						sanitize_file_name( (string) $subset ),
						sanitize_file_name( (string) $version ),
						sanitize_file_name( (string) $font_style ),
						sanitize_file_name( (string) $font_weight ),
						sanitize_file_name( (string) $format )
					);
					$filename = LGF_Url::font_face_path( $this->id, $basename );
					if ( false === $filename ) {
						continue;
					}

					$result[ $sanitized ]['variants'][] = sprintf( '%s %s', $font_style, $font_weight );

					$loaded = file_exists( $filename );

					++$result[ $sanitized ]['total'];
					$filesize = null;
					if ( $loaded ) {
						++$result[ $sanitized ]['loaded'];
						$loaded                            = filemtime( $filename );
						$filesize                          = filesize( $filename );
						$result[ $sanitized ]['filesize'] += $filesize;
					}
					$local_url = $folder_url . '/' . $this->id . '/' . basename( $filename );

					// store relative url
					$relative_url = str_replace( home_url(), '', $local_url );

					$styles = str_replace( $remote_url, $local_url, $styles );

					$result[ $sanitized ]['faces'][] = array(
						'remote_url'   => $remote_url,
						'local_url'    => $local_url,
						'relative_url' => $relative_url,
						'file'         => $filename,
						'filesize'     => $filesize,
						'loaded'       => $loaded,
						'version'      => $version,
						'format'       => $format,
						'style'        => $font_style,
						'weight'       => $font_weight,
						'range'        => $unicode_range,
						'subset'       => $subset,
					);
				}

				// faster array_unique
				$result[ $sanitized ]['variants'] = array_keys( array_flip( $result[ $sanitized ]['variants'] ) );

			}

			$this->remote_styles .= $remote_styles;
			$this->styles        .= $styles;
		}

		$this->info = $result;
	}

	public function get_remote_styles() {

		return $this->remote_styles;
	}

	public function get_styles() {

		return $this->styles;
	}

	public function get_info() {

		return $this->info;
	}

	public function set_src( $src ) {
		$this->id = md5( $src );
		if ( 0 === stripos( $src, '//' ) ) {
			$parsed_url = wp_parse_url( home_url() );
			$src        = $parsed_url['scheme'] . ':' . $src;
		}
		$this->src = $src;
	}

	public function get_src() {

		return $this->src;
	}

	public function set_format( $format ) {

		$this->format = (array) $format;
	}

	public function get_format() {

		return $this->format;
	}

	private function get_absolute_path( $url ) {

		if ( 0 === stripos( $url, '//' ) ) {
			$parsed_url = wp_parse_url( $this->src );
			return $parsed_url['scheme'] . ':' . $url;
		} elseif ( 0 === stripos( $url, '/' ) ) {
			$parsed_url = wp_parse_url( $this->src );
			$host       = isset( $parsed_url['host'] ) ? $parsed_url['host'] : '';
			return $parsed_url['scheme'] . '://' . $host . $url;
		}

		return $url;
	}

	private function get_remote_styleheet( $format = 'woff2', $local_only = false ) {

		if ( $local_only ) {
			$path = LGF::get_folder() . '/' . $this->id . '/font.css';
			if ( ! is_readable( $path ) ) {
				return new \WP_Error(
					'local_stylesheet_missing',
					esc_html__( 'Local font stylesheet was not found.', 'local-google-fonts' )
				);
			}
			$body = file_get_contents( $path );
			if ( false === $body ) {
				return new \WP_Error(
					'local_stylesheet_unreadable',
					esc_html__( 'Local font stylesheet could not be read.', 'local-google-fonts' )
				);
			}
			if ( preg_match( '/\*\/\s*(.*)$/s', $body, $match ) ) {
				return $match[1];
			}
			return $body;
		}

		if ( ! LGF_Url::is_allowed_stylesheet_url( $this->src ) ) {
			return new \WP_Error(
				'invalid_stylesheet_url',
				esc_html__( 'The font stylesheet URL is not allowed.', 'local-google-fonts' )
			);
		}

		$response = $this->get_remote( $this->src, array( 'user-agent' => $this->get_user_agent_by_format( $format ) ) );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return wp_remote_retrieve_body( $response );
	}

	private function get_remote( $url, $args = array() ) {

		if ( ! LGF_Url::is_allowed_stylesheet_url( $url ) ) {
			return new \WP_Error(
				'invalid_stylesheet_url',
				esc_html__( 'The font stylesheet URL is not allowed.', 'local-google-fonts' )
			);
		}

		$transient_key = 'lcg_req_' . md5( $url . serialize( $args ) );
		if ( false === ( $response = get_transient( $transient_key ) ) ) {
			$response = LGF_Url::safe_remote_get( $url, 'css', $args );

			if ( is_wp_error( $response ) ) {
				return $response;
			}
			$code = wp_remote_retrieve_response_code( $response );
			if ( 200 === (int) $code ) {
				set_transient( $transient_key, $response, DAY_IN_SECONDS );
			} else {
				/* Translators: %s: URL */
				return new \WP_Error( 'service_not_available', sprintf( esc_html__( '%s seems to be down right now. Please try again later.', 'local-google-fonts' ), $this->src ) );
			}
		}
		return $response;
	}

	private function get_user_agent_by_format( $format ) {

		$user_agents = array(
			'eot'   => 'Mozilla/5.0 (compatible; MSIE 8.0; Windows NT 6.1; Trident/4.0; GTB7.4; InfoPath.2; SV1; .NET CLR 3.3.69573; WOW64; en-US)',
			'ttf'   => 'Mozilla/5.0 (Macintosh; U; Intel Mac OS X 10_6_8; de-at) AppleWebKit/533.21.1 (KHTML, like Gecko) Version/5.0.5 Safari/533.21.1',
			'svg'   => 'Mozilla/5.0(iPad; U; CPU iPhone OS 3_2 like Mac OS X; en-us) AppleWebKit/531.21.10 (KHTML, like Gecko) Version/4.0.4 Mobile/7B314 Safari/531.21.10gin_lib.cc',
			'woff'  => 'Mozilla/5.0 (iPad; CPU OS 6_0 like Mac OS X) AppleWebKit/536.26 (KHTML, like Gecko) Version/6.0 Mobile/10A5355d Safari/8536.25',
			'woff2' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/112.0.0.0 Safari/537.36',
		);

		if ( isset( $user_agents[ $format ] ) ) {
			return $user_agents[ $format ];
		}

		return null;
	}


	private function get_subset_by_range( $unicode_range ) {

		$ranges = array(
			'cyrillic-ext' => 'U+0460-052F, U+1C80-1C88, U+20B4, U+2DE0-2DFF, U+A640-A69F, U+FE2E-FE2F',
			'cyrillic'     => 'U+0301, U+0400-045F, U+0490-0491, U+04B0-04B1, U+2116',
			'greek-ext'    => 'U+1F00-1FFF',
			'greek'        => 'U+0370-03FF',
			'hebrew'       => 'U+0590-05FF, U+200C-2010, U+20AA, U+25CC, U+FB1D-FB4F',
			'vietnamese'   => 'U+0102-0103, U+0110-0111, U+0128-0129, U+0168-0169, U+01A0-01A1, U+01AF-01B0, U+1EA0-1EF9, U+20AB',
			'latin-ext'    => 'U+0100-024F, U+0259, U+1E00-1EFF, U+2020, U+20A0-20AB, U+20AD-20CF, U+2113, U+2C60-2C7F, U+A720-A7FF',
			'latin'        => 'U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+2000-206F, U+2074, U+20AC, U+2122, U+2191, U+2193, U+2212, U+2215, U+FEFF, U+FFFD',
		);

		if ( $pos = array_search( $unicode_range, $ranges ) ) {
			return $pos;
		}

		return null;
	}
}
