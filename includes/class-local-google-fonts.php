<?php

namespace EverPress;

class LGF {

	private static $instance = null;

	/**
	 * Maximum number of discovered font sources stored in the buffer.
	 *
	 * @var int
	 */
	private static $buffer_max_entries = 100;

	/**
	 * Supported font providers (stylesheet host and font file host).
	 *
	 * @var array<string, array{css_host: string, font_host: string}>
	 */
	private static $providers = array(
		'google' => array(
			'css_host'  => 'fonts.googleapis.com',
			'font_host' => 'fonts.gstatic.com',
		),
	);

	private $user_agent = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/112.0.0.0 Safari/537.36';

	private function __construct() {

		register_activation_hook( LGF_PLUGIN_FILE, array( $this, 'activate' ) );
		register_deactivation_hook( LGF_PLUGIN_FILE, array( $this, 'deactivate' ) );

		add_filter( 'style_loader_src', array( $this, 'switch_stylesheet_src' ), 10, 2 );
		add_filter( 'switch_theme', array( $this, 'clear' ) );
		add_filter( 'wp_resource_hints', array( $this, 'remove_dns_prefetch' ), PHP_INT_MAX, 2 );

		add_filter( 'local_google_fonts_replace_in_content', array( $this, 'replace_in_content' ) );
		add_filter( 'local_google_fonts_replace_url', array( $this, 'google_to_local_url' ), 10, 2 );

		add_action( 'admin_notices', array( $this, 'maybe_welcome_message' ) );
		add_action( 'admin_init', array( $this, 'remove_unsupported_sources' ) );

		add_filter( 'plugin_action_links', array( &$this, 'add_action_link' ), 10, 2 );
	}

	public static function get_instance() {
		if ( self::$instance == null ) {
			self::$instance = new LGF();
		}

		return self::$instance;
	}

	public static function get_folder() {
		$upload_dir = wp_get_upload_dir();
		$folder     = $upload_dir['error'] ? WP_CONTENT_DIR . '/uploads/fonts' : $upload_dir['basedir'] . '/fonts';

		return apply_filters( 'lgf_folder', $folder );
	}

	/**
	 * Normalize and validate a Google Fonts stylesheet URL.
	 *
	 * Only supported font hosts are accepted (HTTPS, official CSS path).
	 *
	 * @since [Unreleased]
	 *
	 * @param string $url Stylesheet URL.
	 * @return string|false Normalized HTTPS URL, or false if unsupported.
	 */
	public static function normalize_css_url( $url ) {
		$parsed = self::parse_validated_css_url( $url );

		return false === $parsed ? false : $parsed['url'];
	}

	/**
	 * Provider key for a valid stylesheet URL.
	 *
	 * @since [Unreleased]
	 *
	 * @param string $url Stylesheet URL.
	 * @return string|false Provider key, or false if unsupported.
	 */
	public static function get_provider_for_css_url( $url ) {
		$parsed = self::parse_validated_css_url( $url );

		return false === $parsed ? false : $parsed['provider'];
	}

	/**
	 * Whether a URL points at a font file on the provider's font host.
	 *
	 * @since [Unreleased]
	 *
	 * @param string $url      Font file URL.
	 * @param string $provider Provider key from get_provider_for_css_url().
	 * @return bool
	 */
	public static function is_font_file_url( $url, $provider ) {
		if ( ! isset( self::$providers[ $provider ] ) ) {
			return false;
		}

		if ( ! is_string( $url ) || '' === $url ) {
			return false;
		}

		$url = trim( $url );
		$url = trim( $url, "'\"" );

		if ( 0 === stripos( $url, '//' ) ) {
			$url = 'https:' . $url;
		}

		$parsed = wp_parse_url( $url );
		if ( ! is_array( $parsed ) || empty( $parsed['host'] ) ) {
			return false;
		}

		if ( isset( $parsed['user'] ) || isset( $parsed['pass'] ) || isset( $parsed['port'] ) ) {
			return false;
		}

		if ( ! isset( $parsed['scheme'] ) || 'https' !== strtolower( $parsed['scheme'] ) ) {
			return false;
		}

		$font_host = self::$providers[ $provider ]['font_host'];

		return strtolower( $parsed['host'] ) === strtolower( $font_host );
	}

	/**
	 * @since [Unreleased]
	 *
	 * @param string $url Stylesheet URL.
	 * @return array{url: string, provider: string}|false
	 */
	private static function parse_validated_css_url( $url ) {
		if ( ! is_string( $url ) || '' === $url ) {
			return false;
		}

		$url = trim( $url );

		if ( 0 === stripos( $url, '//' ) ) {
			$url = 'https:' . $url;
		}

		$parsed = wp_parse_url( $url );
		if ( ! is_array( $parsed ) || empty( $parsed['host'] ) ) {
			return false;
		}

		if ( isset( $parsed['user'] ) || isset( $parsed['pass'] ) || isset( $parsed['port'] ) ) {
			return false;
		}

		$scheme = isset( $parsed['scheme'] ) ? strtolower( $parsed['scheme'] ) : '';
		if ( 'http' === $scheme ) {
			$scheme = 'https';
		}
		if ( 'https' !== $scheme ) {
			return false;
		}

		$path = isset( $parsed['path'] ) ? $parsed['path'] : '';
		if ( 0 !== strpos( $path, '/css' ) ) {
			return false;
		}

		$host = strtolower( $parsed['host'] );

		foreach ( self::$providers as $provider_key => $provider ) {
			if ( $host !== strtolower( $provider['css_host'] ) ) {
				continue;
			}

			$normalized = 'https://' . $provider['css_host'] . $path;
			if ( ! empty( $parsed['query'] ) ) {
				$normalized .= '?' . $parsed['query'];
			}

			return array(
				'url'      => $normalized,
				'provider' => $provider_key,
			);
		}

		return false;
	}

	public static function get_folder_url() {
		$upload_dir = wp_get_upload_dir();
		$folder_url = $upload_dir['error'] ? WP_CONTENT_URL . '/uploads/fonts' : $upload_dir['baseurl'] . '/fonts';

		// make sure it's served over https
		if ( is_ssl() ) {
			$folder_url = set_url_scheme( $folder_url, 'https' );
		}

		return apply_filters( 'lgf_folder_url', $folder_url );
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

		$normalized = self::normalize_css_url( $src );
		if ( false === $normalized ) {
			return $src;
		}
		$src = $normalized;

		$id = md5( $src );

		if ( ! function_exists( 'download_url' ) ) {
			include ABSPATH . 'wp-admin/includes/file.php';
		}

		$WP_Filesystem = $this->wp_filesystem();

		if ( ! function_exists( 'get_plugin_data' ) ) {
			include ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$plugin_data = get_plugin_data( LGF_PLUGIN_FILE );

		$safe_handle = str_replace( '*/', '', (string) $handle );
		$safe_src    = esc_url_raw( str_replace( '*/', '', $src ) );

		$style  = "/*\n";
		$style .= ' * ' . sprintf( 'Font file created by %s %s', $plugin_data['Name'], $plugin_data['Version'] ) . "\n";
		$style .= ' * Created: ' . gmdate( 'r' ) . "\n";
		$style .= ' * Handle: ' . $safe_handle . "\n";
		$style .= ' * Original URL: ' . $safe_src . "\n";
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

		$folder     = self::get_folder();
		$folder_url = self::get_folder_url();

		$new_src = $folder_url . '/' . $id . '/font.css';
		$new_dir = $folder . '/' . $id . '/font.css';

		$class  = LGF_Admin::get_instance();
		$parser = $class->get_parser( $src );

		$stylesheet = $parser->get_remote_styles();
		if ( '' === $stylesheet ) {
			return $src;
		}

		$info     = $parser->get_info();
		$provider = self::get_provider_for_css_url( $src );

		$options = get_option( 'local_google_fonts' );
		if ( isset( $options['relative_urls'] ) ) {
			$relative_urls = true;
		} else {
			$relative_urls = false;
		}

		foreach ( $info as $font ) {

			foreach ( $font['faces'] as $face ) {

				if ( false === $provider || ! self::is_font_file_url( $face['remote_url'], $provider ) ) {
					continue;
				}

				$response = wp_safe_remote_get(
					$face['remote_url'],
					array(
						'redirection'         => 0,
						'timeout'             => 30,
						'limit_response_size' => 5242880,
						'user-agent'          => $this->user_agent,
					)
				);

				if ( is_wp_error( $response ) ) {
					continue;
				}

				if ( 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
					continue;
				}

				$content_type = wp_remote_retrieve_header( $response, 'content-type' );
				if ( is_string( $content_type ) && '' !== $content_type && ! self::is_font_content_type( $content_type ) ) {
					continue;
				}

				$face_dir = dirname( $face['file'] );
				if ( ! wp_mkdir_p( $face_dir ) ) {
					continue;
				}

				$data = wp_remote_retrieve_body( $response );
				if ( ! $WP_Filesystem->put_contents( $face['file'], $data ) ) {
					continue;
				}

				$url = $face['local_url'];
				if ( $relative_urls ) {
					$url = $face['relative_url'];
				}

				$local_file = add_query_arg( 'c', time(), $url );
				$stylesheet = str_replace( $face['remote_url'], $local_file, $stylesheet );
			}
		}

		$style .= $stylesheet;

		if ( ! wp_mkdir_p( dirname( $new_dir ) ) ) {
			return $src;
		}

		if ( ! $WP_Filesystem->put_contents( $new_dir, $style ) ) {
			return $src;
		}

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

		$normalized = self::normalize_css_url( $src );
		if ( false === $normalized ) {
			return $org;
		}
		$src = $normalized;

		$id = md5( $src );

		$folder     = self::get_folder();
		$folder_url = self::get_folder_url();

		$stylesheet     = $folder . '/' . $id . '/font.css';
		$stylesheet_url = $folder_url . '/' . $id . '/font.css';

		if ( ! file_exists( $stylesheet ) ) {

			// do not load on customizer preview.
			if ( is_customize_preview() ) {
				return $org;
			}

			if ( is_null( $handle ) ) {
				$handle = $id;
			}

			$buffer = get_option( 'local_google_fonts_buffer', array() );
			if ( ! is_array( $buffer ) ) {
				$buffer = array();
			}

			if ( ! isset( $buffer[ $handle ] ) && count( $buffer ) >= self::$buffer_max_entries ) {
				return $org;
			}

			$buffer[ $handle ] = array(
				'id'     => $id,
				'handle' => $handle,
				'src'    => $src,
			);

			update_option( 'local_google_fonts_buffer', $buffer );

			$options = get_option( 'local_google_fonts' );
			if ( ! isset( $options['auto_load'] ) ) {
				return $org;
			}

			$src = $this->process_url( $src, $handle );

		}

		if ( ! file_exists( $stylesheet ) ) {
			return $org;
		}

		$src = add_query_arg( 'v', filemtime( $stylesheet ), $stylesheet_url );

		return $src;
	}


	public function switch_stylesheet_src( $src, $handle ) {

		if ( false !== strpos( $src, '//fonts.googleapis.com/css' ) ) {
			if ( false !== self::normalize_css_url( $src ) ) {
				$src = $this->google_to_local_url( $src, $handle );
			}
		}
		return $src;
	}


	public function clear() {
		$folder = self::get_folder();
		if ( is_dir( $folder ) ) {
			$WP_Filesystem = $this->wp_filesystem();
			$WP_Filesystem->delete( $folder, true );
		}
		$this->clear_option();
	}

	public function clear_option() {
		delete_option( 'local_google_fonts_buffer' );
	}

	/**
	 * Drop buffered sources that are not on a supported font host and remove their files.
	 *
	 * @since [Unreleased]
	 */
	public function remove_unsupported_sources() {
		$buffer = get_option( 'local_google_fonts_buffer', array() );
		if ( ! is_array( $buffer ) || empty( $buffer ) ) {
			return;
		}

		$changed = false;

		foreach ( $buffer as $handle => $data ) {
			$src = isset( $data['src'] ) ? $data['src'] : '';
			if ( false !== self::normalize_css_url( $src ) ) {
				continue;
			}

			if ( ! empty( $data['id'] ) ) {
				$this->remove_set( $data['id'] );
			}

			unset( $buffer[ $handle ] );
			$changed = true;
		}

		if ( $changed ) {
			update_option( 'local_google_fonts_buffer', $buffer );
		}
	}

	public function remove_set( $id = null ) {
		$folder = self::get_folder();
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
		if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
			include ABSPATH . 'wp-admin/includes/plugin.php';
		}
		if ( strpos( LGF_PLUGIN_FILE, 'mu-plugins/' ) || is_plugin_active_for_network( plugin_basename( LGF_PLUGIN_FILE ) ) ) {
			return;
		}
		?>
	<div class="notice notice-info">
		<?php /* Translators: %s: plugin name */ ?>
		<p><?php printf( esc_html__( 'Thanks for using Local Google Fonts. Please check the %s.', 'local-google-fonts' ), '<a href="' . esc_url( admin_url( 'options-general.php?page=lgf-settings' ) ) . '">' . esc_html__( 'settings page', 'local-google-fonts' ) . '</a>' ); ?></p>
	</div>
		<?php
	}

	public function add_action_link( $links, $file ) {

		if ( $file == 'local-google-fonts/local-google-fonts.php' ) {
			array_unshift( $links, '<a href="' . esc_url( admin_url( 'options-general.php?page=lgf-settings' ) ) . '">' . esc_html__( 'Settings', 'local-google-fonts' ) . '</a>' );
		}

		return $links;
	}

	/**
	 * Whether a response Content-Type header indicates a font file.
	 *
	 * @since [Unreleased]
	 *
	 * @param string $content_type Raw Content-Type header value.
	 * @return bool
	 */
	private static function is_font_content_type( $content_type ) {
		$parts        = explode( ';', $content_type, 2 );
		$content_type = strtolower( trim( $parts[0] ) );

		if ( 0 === strpos( $content_type, 'font/' ) ) {
			return true;
		}

		if ( 0 === strpos( $content_type, 'application/font' ) ) {
			return true;
		}

		return false;
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
