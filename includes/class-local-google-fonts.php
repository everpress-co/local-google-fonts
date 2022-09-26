<?php

namespace EverPress;

class LGF {

	private static $instance = null;

	private $seed = AUTH_SALT;
	private $upload_dir;

	private function __construct() {

		register_deactivation_hook( LGF_PLUGIN_FILE, array( $this, 'deactivate' ) );

		add_filter( 'style_loader_src', array( $this, 'switch_stylesheet_src' ), 10, 2 );
		add_filter( 'switch_theme', array( $this, 'clear' ) );
		add_filter( 'wp_resource_hints', array( $this, 'remove_dns_prefetch' ), PHP_INT_MAX, 2 );

		add_filter( 'local_google_fonts_replace_in_content', array( $this, 'replace_in_content' ) );
		add_filter( 'local_google_fonts_replace_url', array( $this, 'google_to_local_url' ), 10, 2 );

	}

	public static function get_instance() {
		if ( self::$instance == null ) {
			self::$instance = new LGF();
		}

		return self::$instance;
	}

	public static function remove_dns_prefetch( $urls, $relation_type ) {

		if ( 'dns-prefetch' === $relation_type ) {
			$urls = array_diff( $urls, array( 'fonts.googleapis.com' ) );
		} elseif ( 'preconnect' === $relation_type ) {
			foreach ( $urls as $key => $url ) {
				if ( false !== strpos( $url['href'], '//fonts.gstatic.com' ) ) {
					unset( $urls[ $key ] );
				}
			}
		}

		return $urls;
	}


	public function process_url( $src, $handle ) {

		$id = md5( $src );

		if ( ! function_exists( 'download_url' ) ) {
			include ABSPATH . 'wp-admin/includes/file.php';
		}

		$WP_Filesystem = $this->wp_filesystem();

		if ( ! function_exists( 'get_plugin_data' ) ) {
			include ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$time = time();

		$plugin_data = get_plugin_data( LGF_PLUGIN_FILE );

		$style  = "/*\n";
		$style .= ' * ' . sprintf( 'Font file created by %s %s', $plugin_data['Name'], $plugin_data['Version'] ) . "\n";
		$style .= ' * Created: ' . date( 'r' ) . "\n";
		$style .= ' * Handle: ' . esc_attr( $handle ) . "\n";
		$style .= "*/\n\n";

		$query = wp_parse_url( $src, PHP_URL_QUERY );
		wp_parse_str( $query, $args );
		$args = wp_parse_args(
			$args,
			array(
				'subset'  => null,
				'display' => 'fallback',
			)
		);

		$folder     = WP_CONTENT_DIR . '/uploads/fonts';
		$folder_url = WP_CONTENT_URL . '/uploads/fonts';

		$new_src = $folder_url . '/' . $id . '/font.css';
		$new_dir = $folder . '/' . $id . '/font.css';

		$class    = LGF_Admin::get_instance();
		$fontinfo = $class->get_font_info( $src, $handle );

		if ( is_wp_error( $fontinfo ) ) {
			return $src;
		}

		foreach ( $fontinfo as $font ) {
			$filename = $font->id . '-' . $font->version . '-' . $font->defSubset;
			foreach ( $font->variants as $v ) {

				$file = $filename . '-' . $v->id;
				if ( ! is_dir( $folder . '/' . $id ) ) {
					wp_mkdir_p( $folder . '/' . $id );
				}
				foreach ( array( 'woff', 'svg', 'woff2', 'ttf', 'eot' ) as $ext ) {

					if ( $v->{$ext} ) {
						$tmp_file = download_url( $v->{$ext} );
						if ( ! is_wp_error( $tmp_file ) ) {
							$filepath = $folder . '/' . $id . '/' . $file . '.' . $ext;
							$WP_Filesystem->copy( $tmp_file, $filepath );
							$WP_Filesystem->delete( $tmp_file );
						} else {
							$v->{$ext} = null;
						}
					}
				}
				$style .= "@font-face {\n";
				$style .= "\tfont-family: " . $v->fontFamily . ";\n";
				$style .= "\tfont-style: " . $v->fontStyle . ";\n";
				$style .= "\tfont-weight: " . $v->fontWeight . ";\n";
				if ( $args['display'] && $args['display'] !== 'auto' ) {
					$style .= "\tfont-display: " . $args['display'] . ";\n";
				}

				$style .= "\tsrc: url('" . $folder_url . '/' . $id . '/' . $file . ".eot?v=$time');\n";
				$style .= "\tsrc: local(''),\n";
				$style .= "\t\turl('" . $folder_url . '/' . $id . '/' . $file . ".eot?v=$time#iefix') format('embedded-opentype'),\n";

				if ( $v->woff2 ) {
					$style .= "\t\turl('" . $folder_url . '/' . $id . '/' . $file . ".woff2?v=$time') format('woff2'),\n";
				}
				if ( $v->woff ) {
					$style .= "\t\turl('" . $folder_url . '/' . $id . '/' . $file . ".woff?v=$time') format('woff'),\n";
				}
				if ( $v->ttf ) {
					$style .= "\t\turl('" . $folder_url . '/' . $id . '/' . $file . ".ttf?v=$time') format('truetype'),\n";
				}
				if ( $v->svg ) {
					$style .= "\t\turl('" . $folder_url . '/' . $id . '/' . $file . ".svg?v=$time" . strrchr( $v->svg, '#' ) . "') format('svg');\n";
				}
				$style .= "}\n\n";

			}
		}

		$WP_Filesystem->put_contents( $new_dir, $style );

		return $new_src;

	}


	public function replace_in_content( $content ) {

		if ( false !== strpos( $content, '//fonts.googleapis.com/css' ) ) {

			$regex = "/\b(?:(?:https?):\/\/fonts\.googleapis\.com\/css)[-a-z0-9+&@#\/%?=~_|!:,.;]*[-a-z0-9+&@#\/%=~_|]/i";

			if ( $urls = preg_match_all( $regex, $content, $matches ) ) {
				foreach ( $matches[0] as $i => $url ) {
					$local_url = $this->google_to_local_url( $url );
					if ( $local_url != $url ) {
						$content = str_replace( $url, $local_url, $content );
					}
				}
			}
		}

		return $content;
	}


	public function google_to_local_url( $src, $handle = null ) {

		$id = md5( $src );

		$folder     = WP_CONTENT_DIR . '/uploads/fonts';
		$folder_url = WP_CONTENT_URL . '/uploads/fonts';

		$stylesheet     = $folder . '/' . $id . '/font.css';
		$stylesheet_url = $folder_url . '/' . $id . '/font.css';

		if ( file_exists( $stylesheet ) ) {
			$src = add_query_arg( 'v', filemtime( $stylesheet ), $stylesheet_url );
		} else {

			// do not load on customizer preview.
			if ( is_customize_preview() ) {
				return $src;
			}

			if ( is_null( $handle ) ) {
				$handle = $id;
			}

			$buffer            = get_option( 'local_google_fonts_buffer', array() );
			$buffer[ $handle ] = array(
				'id'     => $id,
				'handle' => $handle,
				'src'    => $src,
			);

			update_option( 'local_google_fonts_buffer', $buffer );

			$options = get_option( 'local_google_fonts' );
			if ( isset( $options['auto_load'] ) ) {
				$src = $this->process_url( $src, $handle );
			}
		}

		return $src;
	}


	public function switch_stylesheet_src( $src, $handle ) {

		if ( false !== strpos( $src, '//fonts.googleapis.com/css' ) ) {
			$src = $this->google_to_local_url( $src, $handle );
		}
		return $src;
	}


	public function clear() {
		$folder = WP_CONTENT_DIR . '/uploads/fonts';
		if ( is_dir( $folder ) ) {
			$WP_Filesystem = $this->wp_filesystem();
			$WP_Filesystem->delete( $folder, true );
		}
		$this->clear_option();

	}

	public function clear_option() {
		delete_option( 'local_google_fonts_buffer' );
	}

	public function remove_set( $id = null ) {
		$folder = WP_CONTENT_DIR . '/uploads/fonts';
		if ( ! is_null( $id ) ) {
			$folder .= '/' . basename( $id );
		}
		if ( is_dir( $folder ) ) {
			$WP_Filesystem = $this->wp_filesystem();
			return $WP_Filesystem->delete( $folder, true );
		}
		return true;
	}

	private function wp_filesystem() {
		global $wp_filesystem;

		if ( ! function_exists( '\WP_Filesystem' ) ) {
			include ABSPATH . 'wp-admin/includes/file.php';
		}

		\WP_Filesystem();

		return $wp_filesystem;

	}

	public function deactivate() {
		$this->clear();
	}

}
