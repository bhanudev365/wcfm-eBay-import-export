<?php
/**
 * Plugin Name: WCFM - eBay Import Export
 * Plugin URI: 
 * Description: eBay Import Export.
 * Author: 
 * Version: 1.0.0
 * Author URI: 
 *
 * Text Domain: weie
 * Domain Path: /lang/
 *
 * WC requires at least: 3.0.0
 * WC tested up to: 3.2.0
 *
 */

if(!defined('ABSPATH')) exit; // Exit if accessed directly

if(!class_exists('WCFM')) return; // Exit if WCFM not installed

//use controller/wcfm-controller-ebay-import-export-classes/wcfmEbayImportExportVendorsAPi;

/**
 * WCFM - Custom Menus Query Var
 */

// Plugin folder Path.
if( ! defined( 'WCFM_IE_PLUGIN_DIR' ) ){
	define( 'WCFM_IE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}

// Plugin folder URL.
if( ! defined( 'WCFM_IE_PLUGIN_URL' ) ){
	define( 'WCFM_IE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}

function start_session() {
	if( !session_id()) {
		session_start();
	}
}
add_action('init', 'start_session',1);


function wcfmcsm_query_vars( $query_vars ) {
	$wcfm_modified_endpoints = (array) get_option( 'wcfm_endpoints' );
	
	$query_custom_menus_vars = array(
		//'wcfm-ebay-import-export'               => ! empty( $wcfm_modified_endpoints['wcfm-ebay-import-export'] ) ? $wcfm_modified_endpoints['wcfm-ebay-import-export'] : 'ebay-import-export',
		//'wcfm-ebay-import-export-form'               => ! empty( $wcfm_modified_endpoints['wcfm-ebay-import-export-form'] ) ? $wcfm_modified_endpoints['wcfm-ebay-import-export-form'] : 'ebay-import-export-form',
		//'wcfm-ebay-import-export-sync'               => ! empty( $wcfm_modified_endpoints['wcfm-ebay-import-export-sync'] ) ? $wcfm_modified_endpoints['wcfm-ebay-import-export-sync'] : 'ebay-import-export-sync',
		'wcfm-ebay-import-export-settings'               => ! empty( $wcfm_modified_endpoints['wcfm-ebay-import-export-settings'] ) ? $wcfm_modified_endpoints['wcfm-ebay-import-export-settings'] : 'ebay-import-export-settings',
	);
	
	$query_vars = array_merge( $query_vars, $query_custom_menus_vars );
	
	return $query_vars;
}
add_filter( 'wcfm_query_vars', 'wcfmcsm_query_vars', 50 );

/**
 * WCFM - Custom Menus End Point Title
 */
function wcfmcsm_endpoint_title( $title, $endpoint ) {
	global $wp;
	switch ( $endpoint ) {
		// case 'wcfm-ebay-import-export' :
		// $title = __( 'eBay Import Export', 'ebay-import-export-addon' );
		// break;

		// case 'wcfm-ebay-import-export-form' :
		// $title = __( 'eBay Import Export Form', 'ebay-import-export' );
		// break;
		// case 'wcfm-ebay-import-export-sync' :
		// $title = __( 'eBay Import Export Form', 'ebay-import-export-addon' );
		// break;
		case 'wcfm-ebay-import-export-settings' :
		$title = __( 'eBay Import Export Settings', 'ebay-import-export-settings' );
		break;
	}
	
	return $title;
}
add_filter( 'wcfm_endpoint_title', 'wcfmcsm_endpoint_title', 50, 2 );

/**
 * WCFM - Custom Menus Endpoint Intialize
 */
function wcfmcsm_init() {

	global $WCFM_Query;

	// Intialize WCFM End points
	$WCFM_Query->init_query_vars();
	$WCFM_Query->add_endpoints();
	
	if( !get_option( 'wcfm_updated_end_point_cms' ) ) {
		// Flush rules after endpoint update
		flush_rewrite_rules();
		update_option( 'wcfm_updated_end_point_cms', 1 );
	}
	require_once( WCFM_IE_PLUGIN_DIR . 'controllers/functions.php' );
	require_once(	WCFM_IE_PLUGIN_DIR . 'controllers/wcfm-controller-ebay-import-export-classes.php');
	$GLOBALS['WCFM_IE_MAINCLASS'] = new wcfmEbayImportExportVendorsAPi();
	
}
add_action( 'init', 'wcfmcsm_init', 50 );

/**
 * WCFM - Custom Menus Endpoiint Edit
 */
function wcfm_custom_menus_endpoints_slug( $endpoints ) {
	
	$custom_menus_endpoints = array(
		//'wcfm-ebay-import-export'       	=> 'ebay-import-export',
		//'wcfm-ebay-import-export-form'      => 'ebay-import-export-form',
		//'wcfm-ebay-import-export-sync'      => 'ebay-import-export-sync',
		'wcfm-ebay-import-export-settings'  => 'ebay-import-export-settings',
	);
	
	$endpoints = array_merge( $endpoints, $custom_menus_endpoints );
	
	return $endpoints;
}
add_filter( 'wcfm_endpoints_slug', 'wcfm_custom_menus_endpoints_slug' );

if(!function_exists('get_wcfm_custom_menus_url')) {
	function get_wcfm_custom_menus_url( $endpoint ) {
		global $WCFM;
		$wcfm_page = get_wcfm_page();
		$wcfm_custom_menus_url = wcfm_get_endpoint_url( $endpoint, '', $wcfm_page );
		return $wcfm_custom_menus_url;
	}
}

/**
 * WCFM - Custom Menus
 */
function wcfmcsm_wcfm_menus( $menus ) {
	global $WCFM;
	$custom_menus = array( 
		'wcfm-ebay-import-export-settings' => array(   'label'  => __( 'eBay Import', 'ebay-import-export-settings'),
			'url'       => get_wcfm_custom_menus_url( 'wcfm-ebay-import-export-settings' ),
			'icon'      => 'sync',
			'priority'  => 5.1
		),
	);
	
	$menus = array_merge( $menus, $custom_menus );

	return $menus;
}
add_filter( 'wcfm_menus', 'wcfmcsm_wcfm_menus', 20 );

/**
 *  WCFM - Custom Menus Views
 */
function wcfm_csm_load_views( $end_point ) {
	global $WCFM, $WCFMu;
	$plugin_path = trailingslashit( dirname( __FILE__  ) );
	
	switch( $end_point ) {
		case 'wcfm-ebay-import-export-sync':
		//require_once( $plugin_path . 'controllers/functions.php' );
		require_once( $plugin_path . 'views/wcfm-views-ebay-import-export-sync.php' );
		break;
		case 'wcfm-ebay-import-export-settings':
		//require_once( $plugin_path . 'controllers/functions.php' );
		require_once( $plugin_path . 'views/wcfm-views-ebay-import-export-settings.php' );
		break;
	}
}
add_action( 'wcfm_load_views', 'wcfm_csm_load_views', 50 );
add_action( 'before_wcfm_load_views', 'wcfm_csm_load_views', 50 );

// Custom Load WCFM Scripts
// function wcfm_csm_load_scripts( $end_point ) {
// 	global $WCFM;
// 	$plugin_url = trailingslashit( plugins_url( '', __FILE__ ) );
	
// 	switch( $end_point ) {
// 		// case 'wcfm-ebay-import-export':
// 		// wp_enqueue_script( 'wcfm_ebay-import-export_js', $plugin_url . 'js/wcfm-script-ebay-import-export.js', array( 'jquery' ), $WCFM->version, true );
// 		// break;
// 		// case 'wcfm-ebay-import-export-sync':
// 		// wp_enqueue_script( 'wcfm_ebay-import-export_js', $plugin_url . 'js/wcfm-script-ebay-import-export.js', array( 'jquery' ), '1.0.5', true );
// 		// break;
// 	}
// }

// add_action( 'wcfm_load_scripts', 'wcfm_csm_load_scripts' );
// add_action( 'after_wcfm_load_scripts', 'wcfm_csm_load_scripts' );

// Custom Load WCFM Styles
function wcfm_csm_load_styles( $end_point ) {
	global $WCFM, $WCFMu;
	$plugin_url = trailingslashit( plugins_url( '', __FILE__ ) );
	switch( $end_point ) {
		case 'wcfm-ebay-import-export-settings':
		wp_enqueue_style( 'wcfmu_ebay-import-export_css', $plugin_url . 'css/wcfm-style-ebay-import-export.css', array(), $WCFM->version );
		break;
	}
}
add_action( 'wcfm_load_styles', 'wcfm_csm_load_styles' );
add_action( 'after_wcfm_load_styles', 'wcfm_csm_load_styles' );

/**
 *  WCFM - Custom Menus Ajax Controllers
 */
// function wcfm_csm_ajax_controller() {
// 	global $WCFM, $WCFMu;
	
// 	$plugin_path = trailingslashit( dirname( __FILE__  ) );
	
// 	$controller = '';
// 	if( isset( $_POST['controller'] ) ) {
// 		$controller = $_POST['controller'];
		
// 		switch( $controller ) {
// 			// case 'wcfm-ebay-import-export':
// 			// require_once( $plugin_path . 'controllers/wcfm-controller-ebay-import-export.php' );
// 			// new WCFM_Build_Controller();
// 			// break;

// 			// case 'wcfm-ebay-import-export-form':
// 			// require_once( $plugin_path . 'controllers/wcfm-controller-ebay-import-export-form.php' );
// 			// break;
// 		}
// 	}
// }
// add_action( 'after_wcfm_ajax_controller', 'wcfm_csm_ajax_controller' );

// add_action('wp_enqueue_scripts', 'wcfm_import_export_css_js');
// function wcfm_import_export_css_js(){
// 	global $WCFM;
// 	$plugin_url = trailingslashit( plugins_url( '', __FILE__ ) );
// 	wp_enqueue_style( 'wcfmu_ebay-import-export_toast_css', $plugin_url . 'css/wcfm-style-ebay-import-export-toast.css', array(), $WCFM->version );
// 	wp_enqueue_script( 'wcfm_ebay-import-export_js', $plugin_url . 'js/wcfm-script-ebay-import-export.js', array( 'jquery' ), $WCFM->version, true );
// }

function enqueue_admin_styles( $hook ) {
	$css_dir = WCFM_IE_PLUGIN_URL . 'css/';
	wp_enqueue_style('wcfm_ie-admin', $css_dir . 'wcfm-style-ebay-import-export-admin.css', false, "" );
}
add_action( 'admin_enqueue_scripts', 'enqueue_admin_styles' );


function Wcfm_eBay_Import_Export_Activator() {
	require_once $plugin_path . 'controllers/wcfm-controller-ebay-import-export-activator.php';
	Wcfm_eBay_Import_Export_Activator::activate();
}

function Wcfm_eBay_Import_Export_Deactivator() {
	require_once $plugin_path . 'controllers/wcfm-controller-ebay-import-export-deactivator.php';
	Wcfm_eBay_Import_Export_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'Wcfm_eBay_Import_Export_Activator' );
register_deactivation_hook( __FILE__, 'Wcfm_eBay_Import_Export_Deactivator' );