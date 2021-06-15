<?php


function getEbaySellerLists(){
	global $wpdb;
	$results = geteBayVendors();
	if(sizeof($results)>0){
		foreach ($results as $result) { ?>

			<tr class="<?php echo ($result->is_primary?'selected':''); ?>">
				<td class="auth_name">
					<?php echo $result->ebay_user_id; ?>
				</td>
				<td class="ebay_auth_expires">expires <?php echo date('l d M, Y h:i',strtotime($result->expire)); ?></td>
				<td class="ebay_auth_actions">
					<?php if($result->is_primary){ ?>
						<strong class="ebay_selected_account">Account selected</strong>
					<?php }else{ ?>
						<a class="select_ebay_account" rel="nofollow" data-method="post" href="?ebay_user_name=<?php echo $result->ebay_user_id; ?>&selected=true">Switch to this account</a>
					<?php } ?>
					
				</td>
			</tr>
		<?php } ?>

	<?php }else{ ?>
		<td colspan="14" class="dataTables_empty" valign="top">No seller account exist.</td>
	<?php }
}
add_action('get_ebay_seller_lists','getEbaySellerLists');

add_action("wp_ajax_getEbaySellerLists", "getEbaySellerLists");
add_action("wp_ajax_nopriv_getEbaySellerLists", "getEbaySellerLists");

function displayAlert(){
	if(isset($_SESSION['alert']) && !empty($_SESSION['alert'])){
		$notify = '<div class="alert alert-'.$_SESSION['alert']['type'].' alert-dismissible fade show notify" role="alert">
		'.$_SESSION['alert']['msg'].'
		</div>';
		unset($_SESSION['alert']);
		return $notify;
	}
}

function addAlert($type,$msg){
	$_SESSION['alert']['type'] = $type;
	$_SESSION['alert']['msg'] = $msg;
}

function geteBayVendors(){
	global $wpdb;
	$results = $wpdb->get_results("SELECT * FROM ".$wpdb->prefix."wc_ebay_multiseller_details WHERE vendor_id = ".get_current_user_id()." ORDER BY wc_ebay_multiseller_details_id ASC ");
	return $results;
}

function add_header_modules(){
	echo '<script type="text/javascript">
	var wcfm_export_import_url = "'.admin_url('admin-ajax.php').'";
	</script>';
}
add_action('wp_head','add_header_modules');


function getCurrentVendorRequest($vendor_id,$type){
	global $wpdb;
	$results = $wpdb->get_row("SELECT * FROM ".$wpdb->prefix."wcfm_ie_request WHERE vendor_id= '".$vendor_id."' AND type = '".$type."' AND status = 1 LIMIT 1 ");
	return (array) $results;
}
function exit_loop($in_queued=array()){
	if(sizeof($in_queued)===0){
		exit();
	}
}
function getCurrentVisitorCountry(){
	if (!empty($_SERVER['HTTP_CLIENT_IP'])){
		$ip=$_SERVER['HTTP_CLIENT_IP'];
	}
	elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])){
		$ip=$_SERVER['HTTP_X_FORWARDED_FOR'];
	}else{
		$ip=$_SERVER['REMOTE_ADDR'];
	}
	$ipdat = @json_decode(file_get_contents( 
		"http://www.geoplugin.net/json.gp?ip=" . $ip),true); 
	return $ipdat;
}
