<?php
/*
Plugin Name: Local Google Fonts
Description: Host your used Google fonts on your server and make your site GDPR compliant.
Version: 0.12
Author: EverPress
Author URI: https://everpress.co
License: GPLv2 or later
*/

namespace EverPress;

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'LGF_PLUGIN_FILE' ) ) {
	define( 'LGF_PLUGIN_FILE', __FILE__ );
}

if ( ! class_exists( 'EverPress\LGF' ) ) {
	include_once 'includes/class-local-google-fonts.php';
	include_once 'includes/class-local-google-fonts-admin.php';
}

add_action( 'plugins_loaded', array( 'EverPress\LGF', 'get_instance' ) );
add_action( 'plugins_loaded', array( 'EverPress\LGF_Admin', 'get_instance' ) );
