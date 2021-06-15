<?php
global $wpdb;
$ebay_user_id = '';
$ebay_token = '';
$ebay_dev_id = '';
$ebay_app_id = '';
$ebay_cert_id = '';
$form_action = 'add_new';

if(isset($_POST['form_action']) && !empty($_POST['form_action']) && $_POST['form_action']=='edit'){

	$exist = $wpdb->get_var("SELECT count(*) FROM ".$wpdb->prefix."wc_ebay_multiseller_details where wc_ebay_multiseller_details_id = '".$_GET['ebay_multiseller_details_id']."' && vendor_id = '".get_current_user_id()."' ");
	if($exist){
		$ebay_user_status = is_connect_ebay_account($_POST);
		$wpdb->update($wpdb->prefix.'wc_ebay_multiseller_details', array('ebay_user_id'=> $_POST['ebay_user_id'], 'vendor_id'=> get_current_user_id(), 'ebay_token'=>$_POST['ebay_token'], 'ebay_dev_id'=>$_POST['ebay_dev_id'], 'ebay_app_id'=>$_POST['ebay_app_id'], 'ebay_cert_id'=>$_POST['ebay_cert_id'],'status'=>$ebay_user_status, 'date_modified'=> current_time( 'mysql' )), array('wc_ebay_multiseller_details_id'=>$_GET['ebay_multiseller_details_id']));
		
		addAlert('success','Successfully to edit eBay user.');
		echo'<script> window.location="'.get_wcfm_custom_menus_url( 'wcfm-ebay-import-export' ).'"; </script> ';
		exit;
	}else{
		$exe = $wpdb->insert($wpdb->prefix.'wc_ebay_multiseller_details',
			array(
				'ebay_user_id'    	=> $_POST['ebay_user_id'],
				'ebay_country'    	=> '0',
				'vendor_id'			=> get_current_user_id(),
				'ebay_token'      	=> $_POST['ebay_token'],
				'ebay_dev_id'     	=> $_POST['ebay_dev_id'],
				'ebay_app_id'     	=> $_POST['ebay_app_id'],
				'ebay_cert_id'    	=> $_POST['ebay_cert_id'],
				'date_added'      	=>  date( 'Y-m-d H:i:s', current_time( 'timestamp', 0 ) ),
				'date_modified'   	=>  date( 'Y-m-d H:i:s', current_time( 'timestamp', 0 ) ),
			),
			array(
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
			) 
		);
		addAlert('success','Successfully to added eBay user.');
		echo'<script> window.location="'.get_wcfm_custom_menus_url( 'wcfm-ebay-import-export' ).'"; </script> ';
		die;
	}
}


if(isset($_GET['action']) && $_GET['action']=='edit' && isset($_GET['ebay_multiseller_details_id']) ){
	$form_action = $_GET['action'];
	$query = $wpdb->get_results("SELECT * FROM ".$wpdb->prefix."wc_ebay_multiseller_details where wc_ebay_multiseller_details_id = '".$_GET['ebay_multiseller_details_id']."' && vendor_id = '".get_current_user_id()."' ");
	if(isset($query[0]->ebay_user_id) & !empty($query[0]->ebay_user_id)){
		$ebay_user_id = $query[0]->ebay_user_id;
	}
	if(isset($query[0]->ebay_token) & !empty($query[0]->ebay_token)){
		$ebay_token = $query[0]->ebay_token;
	}
	if(isset($query[0]->ebay_dev_id) & !empty($query[0]->ebay_dev_id)){
		$ebay_dev_id = $query[0]->ebay_dev_id;
	}
	if(isset($query[0]->ebay_app_id) & !empty($query[0]->ebay_app_id)){
		$ebay_app_id = $query[0]->ebay_app_id;
	}
	if(isset($query[0]->ebay_cert_id) & !empty($query[0]->ebay_cert_id)){
		$ebay_cert_id = $query[0]->ebay_cert_id;
	}
}

if(isset($_POST['ebay_user_id']) & !empty($_POST['ebay_user_id'])){
	$ebay_user_id = $_POST['ebay_user_id'];
}
if(isset($_POST['ebay_token']) & !empty($_POST['ebay_token'])){
	$ebay_token = $_POST['ebay_token'];
}
if(isset($_POST['ebay_dev_id']) & !empty($_POST['ebay_dev_id'])){
	$ebay_dev_id = $_POST['ebay_dev_id'];
}
if(isset($_POST['ebay_app_id']) & !empty($_POST['ebay_app_id'])){
	$ebay_app_id = $_POST['ebay_app_id'];
}
if(isset($_POST['ebay_cert_id']) & !empty($_POST['ebay_cert_id'])){
	$ebay_cert_id = $_POST['ebay_cert_id'];
}

if(isset($_POST) && !empty($_POST) && $_POST['form_action']=='add_new'){

	$ebay_user_status = is_connect_ebay_account($_POST);

	$exe = $wpdb->insert($wpdb->prefix.'wc_ebay_multiseller_details',
		array(
			'ebay_user_id'    	=> $_POST['ebay_user_id'],
			'ebay_country'    	=> '0',
			'vendor_id'			=> get_current_user_id(),
			'ebay_token'      	=> $_POST['ebay_token'],
			'ebay_dev_id'     	=> $_POST['ebay_dev_id'],
			'ebay_app_id'     	=> $_POST['ebay_app_id'],
			'ebay_cert_id'    	=> $_POST['ebay_cert_id'],
			'status'    		=> $ebay_user_status,
			'date_added'      	=>  date( 'Y-m-d H:i:s', current_time( 'timestamp', 0 ) ),
			'date_modified'   	=>  date( 'Y-m-d H:i:s', current_time( 'timestamp', 0 ) ),
		),
		array(
			'%s',
			'%s',
			'%s',
			'%s',
			'%s',
			'%s',
			'%s',
			'%s',
		) 
	);
	addAlert('success','Successfully to added eBay user.');
	echo'<script> window.location="'.get_wcfm_custom_menus_url( 'wcfm-ebay-import-export' ).'"; </script> ';
	die;
}