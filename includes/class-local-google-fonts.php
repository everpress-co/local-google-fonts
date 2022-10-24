<?php

namespace EverPress;

class LGF {

	private static $instance = null;

	private $upload_dir;

	private function __construct() {

		register_activation_hook( LGF_PLUGIN_FILE, array( $this, 'activate' ) );
		register_deactivation_hook( LGF_PLUGIN_FILE, array( $this, 'deactivate' ) );

		add_filter( 'style_loader_src', array( $this, 'switch_stylesheet_src' ), 10, 2 );
		add_filter( 'switch_theme', array( $this, 'clear' ) );
		add_filter( 'wp_resource_hints', array( $this, 'remove_dns_prefetch' ), PHP_INT_MAX, 2 );

		add_filter( 'local_google_fonts_replace_in_content', array( $this, 'replace_in_content' ) );
		add_filter( 'local_google_fonts_replace_url', array( $this, 'google_to_local_url' ), 10, 2 );

		add_action( 'admin_notices', array( $this, 'maybe_welcome_message' ) );

		add_filter( 'plugin_action_links', array( &$this, 'add_action_link' ), 10, 2 );

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
				if ( ! isset( $url['href'] ) ) {
					continue;
				}
				if ( preg_match( '/\/\/fonts\.(gstatic|googleapis)\.com/', $url['href'] ) ) {
					unset( $urls[ $key ] );
				}
			}
		}

		return $urls;
	}


	public function process_url( $src, $handle ) {

		// remove 'ver' query arg as it is added by WP
		$src = preg_replace( '/&ver=([^&]+)/', '', $src );

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
		$style .= ' * Original URL: ' . esc_attr( $src ) . "\n";
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

		$upload_dir = wp_get_upload_dir();
		$folder     = $upload_dir['error'] ? WP_CONTENT_DIR . '/uploads/fonts' : $upload_dir['basedir'] . '/fonts';
		$folder_url = $upload_dir['error'] ? WP_CONTENT_URL . '/uploads/fonts' : $upload_dir['baseurl'] . '/fonts';

		$new_src = $folder_url . '/' . $id . '/font.css';
		$new_dir = $folder . '/' . $id . '/font.css';

		$class  = LGF_Admin::get_instance();
		$parser = $class->get_parser( $src );

		$info       = $parser->get_info();
		$stylesheet = $parser->get_remote_styles();

		if ( is_wp_error( $parser ) ) {
			return $src;
		}

		foreach ( $info as $font ) {

			foreach ( $font['faces'] as $face ) {

				$tmp_file = download_url( $face['remote_url'] );
				if ( ! is_wp_error( $tmp_file ) ) {
					if ( ! is_dir( dirname( $face['file'] ) ) ) {
						wp_mkdir_p( dirname( $face['file'] ) );
					}
					$WP_Filesystem->copy( $tmp_file, $face['file'] );
					$WP_Filesystem->delete( $tmp_file );
					$local_file = add_query_arg( 'c', time(), $face['local_url'] );
					$stylesheet = str_replace( $face['remote_url'], $local_file, $stylesheet );

				}
			}
		}

		$style .= $stylesheet;

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

		$org = $src;

		// remove 'ver' query arg as it is added by WP
		$src = preg_replace( '/&ver=([^&]+)/', '', $src );

		$id = md5( $src );

		$upload_dir = wp_get_upload_dir();
		$folder     = $upload_dir['error'] ? WP_CONTENT_DIR . '/uploads/fonts' : $upload_dir['basedir'] . '/fonts';
		$folder_url = $upload_dir['error'] ? WP_CONTENT_URL . '/uploads/fonts' : $upload_dir['baseurl'] . '/fonts';

		$stylesheet     = $folder . '/' . $id . '/font.css';
		$stylesheet_url = $folder_url . '/' . $id . '/font.css';

		if ( file_exists( $stylesheet ) ) {
			$src = add_query_arg( 'v', filemtime( $stylesheet ), $stylesheet_url );
		} else {

			// do not load on customizer preview.
			if ( is_customize_preview() ) {
				return $org;
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
			} else {
				$src = $org;
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
		$upload_dir = wp_get_upload_dir();
		$folder     = $upload_dir['error'] ? WP_CONTENT_DIR . '/uploads/fonts' : $upload_dir['basedir'] . '/fonts';
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
		$upload_dir = wp_get_upload_dir();
		$folder     = $upload_dir['error'] ? WP_CONTENT_DIR . '/uploads/fonts' : $upload_dir['basedir'] . '/fonts';
		if ( ! is_null( $id ) ) {
			$folder .= '/' . basename( $id );
		}
		if ( is_dir( $folder ) ) {
			$WP_Filesystem = $this->wp_filesystem();
			return $WP_Filesystem->delete( $folder, true );
		}
		return true;
	}

	public function maybe_welcome_message() {

		if ( get_option( 'local_google_fonts_buffer' ) || get_option( 'local_google_fonts' ) ) {
			return;
		}
		if ( get_current_screen()->id == 'settings_page_lgf-settings' ) {
			return;
		}
		?>
	<div class="notice notice-info">
		<p><?php printf( esc_html__( 'Thanks for using Local Google Fonts. Please check the %s.', 'local-google-fonts' ), '<a href="' . admin_url( 'options-general.php?page=lgf-settings' ) . '">' . esc_html__( 'settings page', 'local-google-fonts' ) . '</a>' ); ?></p>
	</div>
		<?php
	}

	public function add_action_link( $links, $file ) {

		if ( $file == 'local-google-fonts/local-google-fonts.php' ) {
			array_unshift( $links, '<a href="' . admin_url( 'options-general.php?page=lgf-settings' ) . '">' . esc_html__( 'Settings', 'local-google-fonts' ) . '</a>' );
		}

		return $links;
	}

	private function wp_filesystem() {
		global $wp_filesystem;

		if ( ! function_exists( '\WP_Filesystem' ) ) {
			include ABSPATH . 'wp-admin/includes/file.php';
		}

		\WP_Filesystem();

		return $wp_filesystem;

	}

	public function activate() {}


	public function deactivate() {
		$this->clear();
	}

}
