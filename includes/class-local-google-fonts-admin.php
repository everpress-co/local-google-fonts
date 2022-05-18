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

		$url  = plugin_dir_url( LGF_PLUGIN_FILE ) . 'assets/admin.js';
		$path = plugin_dir_path( LGF_PLUGIN_FILE ) . 'assets/admin.js';

		wp_enqueue_script( 'local-google-fonts-admin', $url, array( 'jquery' ), filemtime( $path ), true );

	}

	public function local_google_fonts_validate() {

		if ( ! isset( $_POST['hostlocal'] ) ) {
			return;
		}

		$class = LGF::get_instance();

		foreach ( $_POST['hostlocal'] as $handle => $url ) {
			$class->process_url( $url, $handle );
		}
	}

	public function get_font_info( $src ) {

		$params = parse_url( $src );
		parse_str( $params['query'], $args );
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
			$families[ $fam ] = array_unique( array_merge( $families[ $fam ], explode( ',', $parts[1] ) ) );
			sort( $families[ $fam ] );
		}

		foreach ( $families as $family => $variants ) {
			$url      = 'https://google-webfonts-helper.herokuapp.com/api/fonts/';
			$the_url  = add_query_arg(
				array(
					'variants' => implode( ',', $variants ),
					'subset'   => $args['subset'],
				),
				$url . $family
			);
			$response = wp_remote_get( $the_url );
			$code     = wp_remote_retrieve_response_code( $response );

			if ( 200 == $code ) {
				$body       = wp_remote_retrieve_body( $response );
				$info       = json_decode( $body );
				$fontinfo[] = $info;

			}
		}

		return $fontinfo;

	}

	public function render_settings() {

		$buffer = get_option( 'local_google_fonts_buffer', array() );

		$folder     = WP_CONTENT_DIR . '/uploads/fonts';
		$folder_url = WP_CONTENT_URL . '/uploads/fonts';
		$count      = count( $buffer );

		?>
	<div class="wrap">
	<h1><?php printf( esc_html__( _n( '%d Google font source found on your site.', '%d Google font sources found on your site.', $count, 'mailster' ) ), $count ); ?></h1>

	<?php if ( ! $count ) : ?>
		<p><?php esc_html_e( 'You have currently no Google fonts in use on your site.', 'local-google-fonts' ); ?></p>
	<?php endif; ?>
	<form action="options.php" method="post">
		<?php
		settings_fields( 'local_google_fonts' );
		do_settings_sections( 'local_google_fonts_section' );
		?>

		<?php foreach ( $buffer as $handle => $data ) : ?>

		<h2><?php esc_html_e( 'Handle', 'local-google-fonts' ); ?>: <code><?php esc_html_e( $handle ); ?></code></h2>

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
				<td><strong><?php echo esc_html( $set->family ); ?></strong> <br>
					
				</td>
				<td>
					<p class="code">
					<?php foreach ( $set->variants as $variant ) : ?>
						<?php printf( '%s %s', $variant->fontStyle, $variant->fontWeight ); ?>, 
					<?php endforeach ?>
					</p>
					<details>
						<summary><strong><?php printf( '%d files from Google Servers', count( $set->variants ) * 5 ); ?></strong></summary>
						<div style="max-height: 100px; overflow: scroll;font-size: small;white-space: nowrap; overflow: hidden; overflow-y: auto;" class="code">
						<?php foreach ( $set->variants as $variant ) : ?>
							<code><?php echo esc_url( $variant->woff2 ); ?></code><br>
							<code><?php echo esc_url( $variant->ttf ); ?></code><br>
							<code><?php echo esc_url( $variant->svg ); ?></code><br>
							<code><?php echo esc_url( $variant->eot ); ?></code><br>
							<code><?php echo esc_url( $variant->woff ); ?></code><br>
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
			<button class="host-locally button button-primary" name="hostlocal[<?php echo esc_attr( $handle ); ?>]" value="<?php echo esc_attr( $data['src'] ); ?>"><?php esc_html_e( 'Host locally', 'local-google-fonts' ); ?></button>
		</p>
	<?php endforeach ?>
	</form>
</div>
		<?php

	}

}
