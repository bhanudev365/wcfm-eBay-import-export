<?php


class wcfmEbayFunctions{
	// function __construct(){

	// }
	public function displayAlert(){
		if(isset($_SESSION['alert']) && !empty($_SESSION['alert'])){
			$notify = '<div class="alert alert-'.$_SESSION['alert']['type'].' alert-dismissible fade show" role="alert">
			'.$_SESSION['alert']['msg'].'
			<button type="button" class="close" data-dismiss="alert" aria-label="Close">
			<span aria-hidden="true">&times;</span>
			</button>
			</div>';
			unset($_SESSION['alert']);
			return $notify;
		}
	}

	public function addAlert($type,$msg){
		$_SESSION['alert']['type'] = $type;
		$_SESSION['alert']['msg'] = $msg;
	}

	public function geteBayVendors(){
		global $wpdb;
		$results = $wpdb->get_results("SELECT * FROM ".$wpdb->prefix."wc_ebay_multiseller_details WHERE vendor_id = ".get_current_user_id()."");
		return (array) $results;
	}

	public function geteBayVendor($vendor_id){
		global $wpdb;
		$results = $wpdb->get_row("SELECT * FROM ".$wpdb->prefix."wc_ebay_multiseller_details WHERE vendor_id = '".$vendor_id."' AND is_primary = '1' ");
		return (array) $results;
	}
	public function geteBayVendorDetail($ebay_user_id,$vendor_id,$type){
		global $wpdb;
		$results = $wpdb->get_row("SELECT * FROM ".$wpdb->prefix."wc_ebay_multiseller_details WHERE ebay_user_id= '".$ebay_user_id."' AND vendor_id = ".$vendor_id." AND type = '".$type."' ");
		return (array) $results;
	}

	public function get_username($user_id){
		$user_info = get_userdata( $user_id );
		$username = $user_info->user_login.'_'.$user_info->ID;
		return $username;
	}

	public function getRequestByRequestId($request_id){
		global $wpdb;
		$results = $wpdb->get_row("SELECT * FROM ".$wpdb->prefix."wcfm_ie_request WHERE wcfm_ie_request_id= ".$request_id." ");
		return (array) $results;
	}

	public function getCronSchedules(){
		global $wpdb;
		$results = $wpdb->get_results("SELECT * FROM ".$wpdb->prefix."wcfm_ie_cron_request WHERE status = 1 ",ARRAY_A);
		return $results;
	}
	public function getSellerCronScheduleEvents($vendor_id,$type){
		global $wpdb;
		$results = $wpdb->get_results("SELECT * FROM ".$wpdb->prefix."wcfm_ie_cron_request WHERE vendor_id = ".$vendor_id." AND type = '".$type."' AND status = 1 ",ARRAY_A);
		return (array) $results;
	}
	public function getSellerSingleCronScheduleEvent($vendor_id,$type,$hook,$single=false){
		global $wpdb;
		if($single){
			$results = $wpdb->get_row("SELECT * FROM ".$wpdb->prefix."wcfm_ie_cron_request WHERE vendor_id = ".$vendor_id." AND type = '".$type."' AND hook_name = '".$hook."' AND status = 1 LIMIT 1 ",ARRAY_A);
		}else{
			$results = $wpdb->get_results("SELECT * FROM ".$wpdb->prefix."wcfm_ie_cron_request WHERE vendor_id = ".$vendor_id." AND type = '".$type."' AND hook_name = '".$hook."' AND status = 1 ",ARRAY_A);
		}
		return (array) $results;
	}


}