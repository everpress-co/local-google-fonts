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

		add_settings_field( 'settings', __( 'Options', 'local-google-fonts' ), array( $this, 'settings' ), 'local_google_fonts_settings_page', 'default' );
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

	public function settings( $args ) {

		$options = get_option( 'local_google_fonts' );
		?>
		<p>
			<label><input type="checkbox" value="1" name="local_google_fonts[auto_load]" <?php checked( isset( $options['auto_load'] ) ); ?>>
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


	public function get_parser( $src ) {

		include_once dirname( LGF_PLUGIN_FILE ) . '/includes/class-local-google-fonts-parser.php';

		$parser = new LGF_Parser( $src );
		$parser->parse();

		return $parser;

	}

	public function admin_footer_text( $default ) {
		return sprintf( esc_html__( 'If you like %1$s please leave a %2$s&#9733;&#9733;&#9733;&#9733;&#9733;%3$s rating. Thanks in advance!', 'local-google-fonts' ), '<strong>Local Google Fonts</strong>', '<a href="https://wordpress.org/support/view/plugin-reviews/local-google-fonts?filter=5#new-post" target="_blank" rel="noopener noreferrer">', '</a>' );
	}
}
