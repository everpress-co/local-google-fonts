<?php


namespace EverPress;

class LGF_Admin {

	private static $instance = null;

	private function __construct() {

		add_filter( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_menu', array( $this, 'settings_page' ) );

	}

	public static function get_instance() {
		if ( self::$instance == null ) {
			self::$instance = new LGF_Admin();
		}

		return self::$instance;
	}

	public function register_settings() {
		register_setting( 'local_google_fonts', 'local_google_fonts', array( $this, 'local_google_fonts_validate' ) );
	}

	public function settings_page() {
		$page = add_options_page( __( 'Google Fonts', 'local-google-fonts' ), __( 'Google Fonts', 'local-google-fonts' ), 'manage_options', 'lgf-settings', array( $this, 'render_settings' ) );
		add_action( 'load-' . $page, array( &$this, 'script_styles' ) );

	}

	public function script_styles() {

		$url  = plugin_dir_url( LGF_PLUGIN_FILE ) . 'assets';
		$path = plugin_dir_path( LGF_PLUGIN_FILE ) . 'assets';

		wp_enqueue_script( 'local-google-fonts-admin', $url . '/admin.js', array( 'jquery' ), filemtime( $path . '/admin.js' ), true );
		wp_enqueue_style( 'local-google-fonts-admin', $url . '/admin.css', array(), filemtime( $path . '/admin.css' ) );

	}

	public function local_google_fonts_validate() {

		$folder     = WP_CONTENT_DIR . '/uploads/fonts';
		$folder_url = WP_CONTENT_URL . '/uploads/fonts';

		$class = LGF::get_instance();

		$buffer = get_option( 'local_google_fonts_buffer', array() );

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

		if ( isset( $_POST['preload'] ) ) {
			$handle = $_POST['preload'];
			if ( isset( $buffer[ $handle ] ) ) {

				$id             = $buffer[ $handle ]['id'];
				$stylesheet     = $folder . '/' . $id . '/font.css';
				$stylesheet_url = $folder_url . '/' . $id . '/font.css';

				if ( $fontinfo = $this->get_font_info( $buffer[ $handle ]['src'] ) ) {
					foreach ( $fontinfo as $font ) {
						$filenames                    = wp_list_pluck( $font->variants, 'filename' );
						$buffer[ $handle ]['preload'] = array_unique( array_merge( (array) $buffer[ $handle ]['preload'], $filenames ) );
					}
				}
				update_option( 'local_google_fonts_buffer', $buffer );
			}
		}
	}

	public function get_font_info( $src ) {

		// a bit sanitation as URLs are often registered with esc_url
		$src = str_replace( array( '#038;', '&amp;' ), '&', $src );

		$params = wp_parse_url( $src );
		wp_parse_str( $params['query'], $args );
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

		foreach ( $families as $family => $variants ) {
			$url      = 'https://google-webfonts-helper.herokuapp.com/api/fonts/';
			$the_url  = add_query_arg(
				array(
					// doesn't seem to have an effect so we filter it later
					'variants' => implode( ',', $variants ),
					'subsets'  => $args['subset'],
				),
				$url . $family
			);
			$response = wp_remote_get( $the_url );
			$code     = wp_remote_retrieve_response_code( $response );

			if ( 200 == $code ) {
				$body     = wp_remote_retrieve_body( $response );
				$info     = json_decode( $body );
				$filename = $info->id . '-' . $info->version . '-' . $info->defSubset;
				foreach ( $info->variants as $i => $variant ) {
					$info->variants[ $i ]->filename = $filename . '-' . $variant->id;
					// special case for italic 400
					if ( 'italic' == $variant->id && in_array( '400italic', $variants ) ) {

					} elseif ( ! in_array( $variant->id, $variants ) ) {
						unset( $info->variants[ $i ] );
					}
				}
				$info->variants = array_values( $info->variants );
				$fontinfo[]     = $info;

			}
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
			$variants = explode( ',', $variants );
		}

		return $variants;

	}

	public function render_settings() {

		$buffer = get_option( 'local_google_fonts_buffer', array() );

		$folder     = WP_CONTENT_DIR . '/uploads/fonts';
		$folder_url = WP_CONTENT_URL . '/uploads/fonts';
		$count      = count( $buffer );

		?>
	<div class="wrap">
	<h1><?php printf( esc_html__( _n( '%d Google font source found on your site.', '%d Google font sources found on your site.', $count, 'mailster' ) ), $count ); ?></h1>

	<p><?php esc_html_e( 'This page shows all discovered Google Fonts over time. If you miss a font start browsing your front end so they end up showing here.', 'local-google-fonts' ); ?></p>
	
		<?php if ( ! $count ) : ?>
		<p><?php esc_html_e( 'You have currently no Google fonts in use on your site.', 'local-google-fonts' ); ?></p>
	<?php endif; ?>

	<form action="options.php" method="post">
		<?php
		settings_fields( 'local_google_fonts' );
		do_settings_sections( 'local_google_fonts_section' );
		?>

		<?php foreach ( $buffer as $id => $data ) : ?>

		<h2><?php esc_html_e( 'Handle', 'local-google-fonts' ); ?>: <code><?php esc_html_e( $data['handle'] ); ?></code></h2>
		<p><?php esc_html_e( 'Original URL', 'local-google-fonts' ); ?>: <code><?php echo rawurldecode( $data['src'] ); ?></code> <a href="<?php echo esc_url( $data['src'] ); ?>" class="dashicons dashicons-external" target="_blank" title="<?php esc_attr_e( 'show original URL', 'local-google-fonts' ); ?>"></a></p>

	<table class="wp-list-table widefat fixed striped table-view-list ">
		<thead>
			<tr>
				<th scope="col" id="name" class="manage-column column-name column-primary" style="width: 150px"><?php esc_html_e( 'Name', 'local-google-fonts' ); ?></th>
				<th scope="col" id="description" class="manage-column column-description"><?php esc_html_e( 'Variants', 'local-google-fonts' ); ?></th>
				<th scope="col" id="auto-updates" class="manage-column column-auto-updates"  style="width: 250px"><?php esc_html_e( 'Status', 'local-google-fonts' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php $fontinfo = $this->get_font_info( $data['src'] ); ?>

			<?php foreach ( $fontinfo as $i => $set ) : ?>
			<tr>
				<td><strong><?php echo esc_html( $set->family ); ?></strong><br>
				</td>
				<td>
					<p class="code">
					<?php foreach ( $set->variants as $variant ) : ?>
							<span class="variant"><?php printf( '%s %s', $variant->fontStyle, $variant->fontWeight ); ?></span> 
					<?php endforeach ?>
					</p>
					<details>
						<summary><strong><?php printf( '%d files from Google Servers', count( $set->variants ) * 5 ); ?></strong></summary>
						<div style="max-height: 200px; overflow: scroll;font-size: small;white-space: nowrap; overflow: hidden; overflow-y: auto;" class="code">
						<?php foreach ( $set->variants as $variant ) : ?>
							<p>
							<strong><?php printf( '%s %s', $variant->fontStyle, $variant->fontWeight ); ?></strong><br>
							<code><?php echo esc_url( $variant->woff2 ); ?></code><br>
							<code><?php echo esc_url( $variant->ttf ); ?></code><br>
							<code><?php echo esc_url( $variant->svg ); ?></code><br>
							<code><?php echo esc_url( $variant->eot ); ?></code><br>
							<code><?php echo esc_url( $variant->woff ); ?></code>
							</p>
						<?php endforeach ?>
						</div>
					</details>
				</td>
				<td>
					<?php if ( is_dir( $folder . '/' . $data['id'] ) ) : ?>
						<strong class="">✔</strong> loaded, served from your server
					<?php else : ?>
						<strong class="wp-ui-text-notification">✕</strong> not loaded, served from Google servers
					<?php endif; ?>
				
				</td>

			</tr>
		<?php endforeach ?>						
		</tbody>
	</table>		
		<p>
			<button class="host-locally button button-primary" name="hostlocal" value="<?php echo esc_attr( $data['handle'] ); ?>"><?php esc_html_e( 'Host locally', 'local-google-fonts' ); ?></button>
			<?php if ( is_dir( $folder . '/' . $data['id'] ) ) : ?>
			<button class="host-locally button button-secondary" name="preload" value="<?php echo esc_attr( $data['handle'] ); ?>"><?php esc_html_e( 'Preload', 'local-google-fonts' ); ?></button>
			<button class="host-locally button button-link-delete" name="removelocal" value="<?php echo esc_attr( $data['handle'] ); ?>"><?php esc_html_e( 'Remove hosted files', 'local-google-fonts' ); ?></button>
			<?php endif; ?>
		</p>
	<?php endforeach ?>
	<p class="textright">
		<button class="host-locally button button-link-delete" name="flush" value="1"><?php esc_html_e( 'Remove all stored data', 'local-google-fonts' ); ?></button>
	</p>
	</form>
</div>
		<?php

	}

}
