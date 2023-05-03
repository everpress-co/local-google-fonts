<?php
/*
Plugin Name: Local Google Fonts
Description: Host your used Google fonts on your server and make your site GDPR compliant.
Version: 0.21.0
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
	include_once 'includes/class-local-google-fonts-upgrade.php';
}

LGF::get_instance();
LGF_Admin::get_instance();
LGF_Upgrade::get_instance();
