<?php


namespace EverPress;

class LGF_Upgrade {

	private static $instance = null;

	private $from;
	private $to;

	private function __construct() {

		add_filter( 'upgrader_pre_install', array( $this, 'upgrader_pre_install' ), 10, 2 );
	}

	public static function get_instance() {
		if ( self::$instance === null ) {
			self::$instance = new LGF_Upgrade();
		}

		return self::$instance;
	}

	public function upgrader_pre_install( $res, $hook_extra ) {

		// store the current version of the plugin before the update kicks in
		if ( $res && isset( $hook_extra['plugin'] ) ) {
			if ( $hook_extra['plugin'] === plugin_basename( LGF_PLUGIN_FILE ) ) {
				$plugin_data = get_plugin_data( LGF_PLUGIN_FILE, false, false );
				$this->from  = $plugin_data['Version'];
				add_filter( 'upgrader_post_install', array( $this, 'upgrader_post_install' ), 10, 3 );
			}
		}
		return $res;
	}

	public function upgrader_post_install( $res, $hook_extra, $result ) {

		// on success run the delta updates
		if ( $res && isset( $hook_extra['plugin'] ) ) {
			if ( $hook_extra['plugin'] === plugin_basename( LGF_PLUGIN_FILE ) ) {
				$plugin_data = get_plugin_data( LGF_PLUGIN_FILE, false, false );
				$this->to    = $plugin_data['Version'];

				$this->run();

			}
		}

		return $res;
	}


	private function run() {
		$class   = new \ReflectionClass( $this );
		$methods = $class->getMethods( \ReflectionMethod::IS_PRIVATE );

		$updates = wp_list_pluck( $methods, 'name' );
		$updates = preg_grep( '/^update_([\d_+])/', $updates );

		foreach ( $updates as $update_method ) {

			// get version number from method
			$version = str_replace( 'update_', '', $update_method );
			$version = str_replace( '_', '.', $version );

			// call method only if needed
			if ( version_compare( $version, $this->from, '>' ) && version_compare( $version, $this->to, '<=' ) ) {
				call_user_func( array( $this, $update_method ) );
			}
		}

	}


	/*
	 * run updates for specific versions
	 */
	private function update_0_17() {
		
	}



}
