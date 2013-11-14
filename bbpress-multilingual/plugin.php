<?php
/*
Plugin Name: bbPress Multilingual
Description: Adds compatibility between WPML plugin.
Version: 0.9
Author: OnTheGoSystems
Author URI: http://www.onthegosystems.com
*/

if( defined( 'BBPML_VERSION' ) ) return;

define( 'BBPML_VERSION', '0.9' );
define( 'BBPML_PATH', dirname( __FILE__ ) );
define( 'BBPML_FOLDER', basename( BBPML_PATH ) );
define( 'BBPML_URL', plugins_url() . '/' . BBPML_FOLDER );

require BBPML_PATH . '/classes/bbpml.class.php';

$bbpml = new BBPML;