<?php


namespace EverPress;

class LGF_Admin {

	private static $instance = null;
	private $weightClass     = array(
		100 => 'Thin',
		200 => 'ExtraLight',
		300 => 'Light',
		400 => 'Regular',
		500 => 'Medium',
		600 => 'SemiBold',
		700 => 'Bold',
		800 => 'ExtraBold',
		900 => 'Black',
	);


	private function __construct() {

		add_filter( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_menu', array( $this, 'settings_page' ) );

	}

	public static function get_instance() {
		if ( self::$instance === null ) {
			self::$instance = new LGF_Admin();
		}

		return self::$instance;
	}

	public function register_settings() {

		register_setting( 'local_google_fonts_settings_page', 'local_google_fonts', array( $this, 'local_google_fonts_validate' ) );

		add_settings_section( 'default', '', '', 'local_google_fonts_settings_page' );

		add_settings_field( 'auto_load', __( 'Autoload', 'local-google-fonts' ), array( $this, 'auto_load_cb' ), 'local_google_fonts_settings_page', 'default' );
	}

	public function settings_page() {
		$page = add_options_page( __( 'Google Fonts', 'local-google-fonts' ), __( 'Google Fonts', 'local-google-fonts' ), 'manage_options', 'lgf-settings', array( $this, 'render_settings' ) );
		add_action( 'load-' . $page, array( &$this, 'script_styles' ) );

	}

	public function script_styles() {

		$url  = plugin_dir_url( LGF_PLUGIN_FILE ) . 'assets';
		$path = plugin_dir_path( LGF_PLUGIN_FILE ) . 'assets';

		wp_enqueue_script( 'local-google-fonts-admin', $url . '/admin.js', array( 'thickbox', 'jquery' ), filemtime( $path . '/admin.js' ), true );
		wp_enqueue_style( 'local-google-fonts-admin', $url . '/admin.css', array( 'thickbox' ), filemtime( $path . '/admin.css' ) );

		add_action( 'admin_footer_text', array( $this, 'admin_footer_text' ) );

	}

	public function local_google_fonts_validate( $options ) {

		$class = LGF::get_instance();

		$buffer = get_option( 'local_google_fonts_buffer', array() );
		if ( isset( $_POST['subsets'] ) ) {
			foreach ( $_POST['subsets'] as $handle => $subsets ) {
				if ( isset( $buffer[ $handle ] ) ) {
					if ( ! isset( $buffer[ $handle ]['subsets'] ) ) {
						$buffer[ $handle ]['subsets'] = array();
					}
					$buffer[ $handle ]['subsets'] = $subsets;
				}
			}
		}
		update_option( 'local_google_fonts_buffer', $buffer );

		if ( isset( $_POST['hostlocal'] ) ) {
			$handle = $_POST['hostlocal'];
			if ( isset( $buffer[ $handle ] ) ) {
				$class->remove_set( $buffer[ $handle ]['id'] );
				$class->process_url( $buffer[ $handle ]['src'], $handle );

			}
		}

		if ( isset( $_POST['removelocal'] ) ) {
			$handle = $_POST['removelocal'];
			if ( isset( $buffer[ $handle ] ) ) {
				$class->remove_set( $buffer[ $handle ]['id'] );
			}
		}

		if ( isset( $_POST['flush'] ) ) {
			$class->clear();
		}

		return $options;

	}

	public function auto_load_cb( $args ) {

		$options = get_option( 'local_google_fonts' );
		$checked = isset( $options['auto_load'] );
		?>
		<p>
			<label><input type="checkbox" value="1" name="local_google_fonts[auto_load]" <?php checked( $checked ); ?>>
				<?php esc_html_e( 'Load Fonts automatically', 'local-google-fonts' ); ?>
			</label>
		</p>
		<p class="description">
			<?php esc_html_e( 'If you check this option discovered fonts will get loaded automatically.', 'local-google-fonts' ); ?>
		</p>
		<?php
	}


	public function render_settings() {
		// check user capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		include_once dirname( LGF_PLUGIN_FILE ) . '/views/settings.php';

	}


	public function get_font_info( $src, $handle = null ) {

		$folder     = WP_CONTENT_DIR . '/uploads/fonts';
		$folder_url = WP_CONTENT_URL . '/uploads/fonts';

		// remove 'ver' query arg as it is added by WP
		$src = preg_replace('/&ver=([^&]+)/', '', $src);

		$id = md5( $src );

		// a bit sanitation as URLs are often registered with esc_url
		$src = str_replace( array( '#038;', '&amp;' ), '&', $src );

		$query = wp_parse_url( $src, PHP_URL_QUERY );
		wp_parse_str( $query, $args );

		// handling of multiple "family" arguments
		$parts  = explode( '&', $query );
		$groups = array();
		foreach ( $parts as $part ) {
			if ( 0 === strpos( $part, 'family=' ) ) {
				$groups[] = str_replace( 'family=', '', $part );
			}
		}

		if ( ! empty( $groups ) ) {
			$args['family'] = rawurldecode( implode( '|', $groups ) );
		}

		$args = wp_parse_args(
			$args,
			array(
				'subset'  => null,
				'display' => 'fallback',
			)
		);

		$groups = explode( '|', $args['family'] );

		$fontinfo = array();
		$families = array();
		foreach ( $groups as $i => $group ) {
			$parts = explode( ':', $group );
			$fam   = sanitize_title( str_replace( '+', '-', $parts[0] ) );
			if ( ! isset( $families[ $fam ] ) ) {
				$families[ $fam ] = array( 'regular' );
			}
			if ( isset( $parts[1] ) ) {
				$variants         = $this->normalize_variants( $parts[1] );
				$families[ $fam ] = array_unique( array_merge( $families[ $fam ], $variants ) );
			}
		}

		// do not load them as its most likely some helper font thing
		if ( count( $families ) > 30 ) {
			return new \WP_Error( 'to_many_families', esc_html__( 'This source contains more than 30 fonts and is most likely used as helper for your theme. Skipped.', 'local-google-fonts' ) );
		}

		$buffer = get_option( 'local_google_fonts_buffer', array() );

		foreach ( $families as $family => $variants ) {
			$url     = 'https://local-google-fonts.herokuapp.com/api/fonts/';
			$alias   = $this->font_family_alias( $family );
			$subsets = isset( $buffer[ $handle ]['subsets'][ $family ] ) ? implode( ',', array_filter( $buffer[ $handle ]['subsets'][ $family ] ) ) : $args['subset'];
			$the_url = add_query_arg(
				array(
					// doesn't seem to have an effect so we filter it later
					// 'variants' => implode( ',', $variants ),
					'subsets' => $subsets,
				),
				$url . $alias
			);

			$transient_key = 'lcg_s' . md5( $the_url );
			if ( false === ( $info = get_transient( $transient_key ) ) ) {
				$response = wp_remote_get( $the_url );
				// break early if there's an error here.
				if ( is_wp_error( $response ) ) {
					return $response;
				}
				$code = wp_remote_retrieve_response_code( $response );
				if ( 200 === $code ) {
					$body = wp_remote_retrieve_body( $response );
					$info = json_decode( $body );
					set_transient( $transient_key, $info, HOUR_IN_SECONDS );
				} elseif ( 503 === $code ) {
					return new \WP_Error( 'service_not_available', sprintf( esc_html__( '%s seems to be down right now. Please try again later.', 'local-google-fonts' ), $url ) );
				} else {
					continue;
				}
			}

			// if only regular is present we actually need all of them
			if ( count( $variants ) > 1 ) {

				foreach ( $info->variants as $i => $variant ) {
					// special case for italic 400
					if ( 'italic' == $variant->id && in_array( '400italic', $variants ) ) {

					} elseif ( ! in_array( $variant->id, $variants ) ) {
						unset( $info->variants[ $i ] );
					}
				}
			}

			// fonts with render bug /https://github.com/everpress-co/local-google-fonts/issues/1)
			if ( in_array( $family, array( 'montserrat', 'jost', 'inter', 'exo-2' ) ) ) {

				foreach ( $info->variants as $i => $variant ) {

					$san_family = str_replace( ' ', '', $info->family );

					$font_name = sprintf(
						'%s-%s%s',
						$san_family,
						$this->weightClass[ $variant->fontWeight ],
						( $variant->fontStyle === 'italic' ? 'Italic' : '' )
					);

					// there's no RegularItalic
					$font_name                   = str_replace( $san_family . '-RegularItalic', $san_family . '-Italic', $font_name );
					$info->variants[ $i ]->woff2 = 'https://github.com/everpress-co/local-google-fonts-render-bug/raw/main/fonts/' . $family . '/' . $font_name . '.woff2';
				}
			}

			$filename       = $id . '/' . $info->id . '-' . $info->version . '-' . $info->defSubset;
			$info->total    = count( $info->variants ) * 5;
			$info->original = $family;
			$info->loaded   = 0;

			foreach ( $info->variants as $i => $variant ) {
				$file = $filename . '-' . $variant->id;

				$info->variants[ $i ]->loaded = array();
				foreach ( array( 'woff', 'svg', 'woff2', 'ttf', 'eot' ) as $ext ) {
					if ( file_exists( $folder . '/' . $file . '.' . $ext ) ) {
						$info->loaded++;
						$info->variants[ $i ]->loaded[ $ext ] = $file . '.' . $ext;
					}
				}
			}

			$info->variants = array_values( $info->variants );
			$fontinfo[]     = $info;
		}

		if ( empty( $fontinfo ) ) {
			return new \WP_Error( 'no_fontinfo', esc_html__( 'This font is not supported. Skipped.', 'local-google-fonts' ) );
		}

		return $fontinfo;

	}

	private function normalize_variants( $variants ) {
		// possibles
		// Merriweather:400,700,400italic,700italic
		// Open+Sans:wght@400;700
		// Open+Sans:ital,wght@0,800;1,800
		// Open+Sans:ital,wght@0,400;0,700;1,800
		// Google+Sans:300,300i,400,400i,500,500i,700,700i|Roboto:300,300i,400,400i,500,500i,700,700i

		if ( false !== strpos( $variants, '@' ) ) {
			$variant_parts = explode( '@', $variants );
			$styles        = explode( ';', $variant_parts[1] );
			$variants      = array();
			foreach ( $styles as $style ) {
				// regular version
				if ( 0 === strpos( $style, '0,' ) ) {
					$variants[] = substr( $style, 2 );
					// italic version
				} elseif ( 0 === strpos( $style, '1,' ) ) {
					$variants[] = substr( $style, 2 ) . 'italic';
				} else {
					$variants[] = $style;
				}
			}
		} else {
			// handle XXXi variants
			$variants = preg_replace( '/(\d{3}+)i/', '$1italic', $variants );
			// handle XXXitalic is converted into XXXitalictalic
			$variants = str_replace( 'italictalic', 'italic', $variants );
			$variants = explode( ',', $variants );
		}

		return $variants;

	}

	private function font_family_alias( $name ) {

		$alias = array(
			'droid-sans' => 'noto-sans',
		);

		if ( isset( $alias[ $name ] ) ) {
			return $alias[ $name ];
		}

		return $name;

	}

	public function admin_footer_text( $default ) {
		return sprintf( esc_html__( 'If you like %1$s please leave a %2$s&#9733;&#9733;&#9733;&#9733;&#9733;%3$s rating. Thanks in advance!', 'local-google-fonts' ), '<strong>Local Google Fonts</strong>', '<a href="https://wordpress.org/support/view/plugin-reviews/local-google-fonts?filter=5#new-post" target="_blank" rel="noopener noreferrer">', '</a>' );
	}
}
