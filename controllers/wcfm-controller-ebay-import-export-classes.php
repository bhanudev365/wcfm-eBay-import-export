<?php
require_once('wcfm-ebay-functions.php');
require 'vendor/autoload.php';
class wcfmEbayImportExportVendorsAPi extends wcfmEbayFunctions{
	const VENDOR_DETAIL_TABLE 			= 'wc_ebay_multiseller_details';
	const VENDOR_API_CAT_TABLE 			= 'api_category';
	const VENDOR_API_ORDER_TABLE 		= 'api_order';
	const VENDOR_IE_REQUEST_TABLE 		= 'wcfm_ie_request';
	const VENDOR_IE_CRON_REQUEST_TABLE 	= 'wcfm_ie_cron_request';
	const VENDOR_IE_MODIFIED_PRODUCTS 	= 'wcfm_ie_modified_products';
	function __construct(){
		// ini_set('display_errors', true);
		// error_reporting(E_ALL);

		set_time_limit(10000);
		global $woocomerce;
		global $wpdb;

		$this->socket_app_id = '1129424';
		$this->socket_app_key = 'c51db68eb74ee62eb5ef';
		$this->socket_app_secret = '72d522b38400eeeadc7b';
		$this->socket_app_cluster = 'ap2';
		$this->socket_options = array(
			'cluster' => 'ap2',
			'useTLS' => true
		);

		$this->app_id = 'bhanucha-fiznoint-PRD-93882c164-561b26b8';
		$this->dev_id = 'a25f93b5-b5f4-4891-8a4a-22486eee2fa9';
		$this->cert_id = 'PRD-3882c16494f4-d7e7-4e15-8eb2-b2b5';
		$this->site_id = 0;
		$this->min_date = '2019-09-01';
		$this->session_name = 'wcfm_import_export_data';
		$this->tablename = $wpdb->prefix . self::VENDOR_DETAIL_TABLE;
		$this->cat_tablename = $wpdb->prefix . self::VENDOR_API_CAT_TABLE;
		$this->order_tablename = $wpdb->prefix . self::VENDOR_API_ORDER_TABLE;
		$this->request_tablename = $wpdb->prefix . self::VENDOR_IE_REQUEST_TABLE;
		$this->cron_request_tablename = $wpdb->prefix . self::VENDOR_IE_CRON_REQUEST_TABLE;
		$this->modified_products = $wpdb->prefix . self::VENDOR_IE_MODIFIED_PRODUCTS;
		//$this->cron_request_time = 10; //in second
		$this->ebay_api_url = 'https://api.ebay.com/ws/api.dll';
		$this->file_path = ABSPATH.'wp-content/';
		$this->product_file_name = 'product_list_';
		$this->category_file_name = 'categories_list_';
		$this->order_file_name = 'orders_list_';
		$this->upload_dir       = wp_upload_dir();
		$this->plugin_path      = plugin_dir_path( __DIR__ );
		$this->logs_path     	= plugin_dir_path( __DIR__ ).'logs/';
		$this->log_file     	= plugin_dir_path( __DIR__ ).'logs/error.log';
		$this->product_header_column = array(
			'product_id','product_title','description','quantity','regular_price','featured_image','gallery_images','category_id','category_name','feedback_score','feedback_percent','stock_status','condition'
		);
		// $this->order_header_column = array(
		// 	'order_id','order_status','order_total','sub_total','order_created','paid_date','shipped_date','payment_method','shipping_address','item','buyer_user_id'
		// );
		$vendor_id = get_current_user_id();
		if($vendor_id){
			$vendor_product_request = getCurrentVendorRequest($vendor_id,'products');
			$proccessing_bar = false;
			if(sizeof($vendor_product_request)>0){
				$proccessing_bar = get_user_meta($vendor_id,'ebay_proccessing_bar',true);
			}
			if($proccessing_bar == true){
				add_filter( 'pre_get_document_title', array($this,'wcfm_importing_progressbar_site_title') );
			}elseif(sizeof($vendor_product_request)>0){
				add_filter( 'pre_get_document_title', array($this,'wcfm_importing_site_title') );
			}
		}
		add_filter( 'wcfm_is_force_shipping_address', '__return_true' );
		add_action("addEbayVendorProductData", array($this,'addEbayVendorProductData'));
		add_action("addWcfmEbayProductHook",array( $this, 'addWcfmEbayProductHook' ));
		//add_action("addWcfmEbayOrderHook",array( $this, 'addWcfmEbayOrderHook' ));
		add_action("wcfm_ie_create_all_products_file",array( $this, 'wcfm_ie_create_all_products_file' ));
		$this->check_ebay_auth();
		$this->eBaySellerSelected();
		$this->redirect_to_signin_ebay();

		$this->add_wcfm_ie_setting_save();
		$this->wcfm_ie_cancel_request();
		$this->wcfm_ie_remove_schedule();
		add_action('woocommerce_order_status_changed', array($this,'wcfm_quantity_update_on_ebay_product'), 10, 3);
		add_action( "wcfm_schedule_import_products", array($this,'wcfm_schedule_import_products') );
		if ( ! wp_next_scheduled( 'check_seller_product_import_schedules' ) ) {
			wp_schedule_event( time(), 'hourly', 'check_seller_product_import_schedules' );
		}
		add_action( 'check_seller_product_import_schedules', array($this,'check_seller_product_import_schedules'));                 
		add_action( 'woocommerce_update_product', array($this,'action_woocommerce_update_product'), 10, 1 ); 
		add_filter( 'wc_product_sku_enabled', '__return_false' );


		//echo $this->upload_dir['path'] . '/' . $this->get_img_token($gallery_image).'.jpg';

	}
	public function action_woocommerce_update_product( $product_id ) {
		if(isset($product_id) && !empty($product_id) && (int)$product_id ){
			$ebay_item_id = get_post_meta( $product_id, '_sku', true );
			$ebay_user_id = get_post_meta($product_id, "_ebay_user_id", true);
			$post_author_id = get_post_field( 'post_author', $product_id );
			if($ebay_user_id && $ebay_user_id){
				global $wpdb;
				$ebay_item_id_exist = $wpdb->get_var("SELECT ebay_item_id FROM ".$this->modified_products." WHERE vendor_id = '".$post_author_id."' AND ebay_user_id = '".$ebay_user_id."' AND ebay_item_id = '".$ebay_item_id."' LIMIT 1  ");
				if(!$ebay_item_id_exist){
					$wpdb->insert($this->modified_products, array(
						"vendor_id"     => $post_author_id,
						"ebay_user_id"  => $ebay_user_id,
						"ebay_item_id"  => $ebay_item_id,
					));
				}
			}
		}
	}
	public function setApiAccount($ebay_user,$vendor_id){
		$user_name  = $ebay_user['ebay_user_id'];
		$auth_token = $ebay_user['ebay_token'];
		$current_page = get_user_meta($vendor_id,'wcfm_ie_product_current_page',true);
		$headers = array(
			'Content-Type: text/xml',
			'X-EBAY-API-COMPATIBILITY-LEVEL:877',
			'X-EBAY-API-DEV-NAME:'.$this->dev_id,
			'X-EBAY-API-APP-NAME:'.$this->app_id,
			'X-EBAY-API-CERT-NAME:'.$this->cert_id,
			'X-EBAY-API-SITEID:'.$this->site_id,
			'X-EBAY-API-CALL-NAME:'.$ebay_user['call_name']
		);
		switch ($ebay_user['call_name']) {
			case 'GetSellerList':
			$xml = '<?xml version="1.0" encoding="utf-8"?>
			<GetSellerListRequest xmlns="urn:ebay:apis:eBLBaseComponents">
			<RequesterCredentials>
			<eBayAuthToken>'.$auth_token.'</eBayAuthToken>
			</RequesterCredentials>
			<Pagination ComplexType="PaginationType">
			<EntriesPerPage>200</EntriesPerPage>
			<PageNumber>'.$current_page.'</PageNumber>
			</Pagination>
			<StartTimeFrom>'.$ebay_user['from_date'].'</StartTimeFrom>
			<StartTimeTo>'.$ebay_user['end_date'].'</StartTimeTo>
			<DetailLevel>ReturnAll</DetailLevel>
			<UserID>'.$user_name.'</UserID>
			</GetSellerListRequest>';
			break;
		}
		$ch  = curl_init($this->ebay_api_url);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$responsexml = curl_exec($ch);
		curl_close($ch);
		$xml_response = simplexml_load_string($responsexml);
		$json_response = json_encode($xml_response);
		$result = json_decode($json_response,TRUE);
		return $result;
	}
	public function addCsvFileData($ebay_user_info,$vendor_id){
		$ebay_product_data = $this->setApiAccount($ebay_user_info,$vendor_id);
		$ebay_product_data['from_date'] 	= $ebay_user_info['from_date'];
		$ebay_product_data['end_date'] 		= $ebay_user_info['end_date'];
		switch ($ebay_user_info['call_name']) {
			case 'GetSellerList':
			$this->addProductFileData($ebay_product_data,$vendor_id);
			break;
		}
	}

	public function addProductFileData($ebay_user_response,$vendor_id){
		global $wpdb;
		//$this->setLog(json_encode($ebay_user_response));
		if(isset($ebay_user_response['Ack']) && $ebay_user_response['Ack']=='Success'){
			$ebay_user_id = $ebay_user_response['Seller']['UserID'];
			$from_date = $ebay_user_response['from_date'];
			$end_date  = $ebay_user_response['end_date'];
			$filename 	=	$this->file_path.$this->product_file_name.$vendor_id.'_'.$ebay_user_id.'.csv';
			$cron_file 	=	$this->product_file_name.$vendor_id.'_'.$ebay_user_id.'.csv';
			$wcfm_ie_options = array(); 
			$wcfm_ie_options = get_option('wcfm_ie_form_setting');
			$product_import_setting		= $wcfm_ie_options[$vendor_id];
			if($ebay_user_response['PaginationResult']['TotalNumberOfEntries']>0){
				$ebay_data = array();
				if($ebay_user_response['PaginationResult']['TotalNumberOfEntries']> 1){
					$ebay_data = $ebay_user_response['ItemArray']['Item'];
				}else{
					$ebay_data[] = $ebay_user_response['ItemArray']['Item'];
				}
				$arr_params = array( 'set_id');
				$gallery_images = array();
				$in_queued = array();
				$in_queued = getCurrentVendorRequest($vendor_id,'products');
				$product_data =array();
				$deleted_product = 0;
				try{
					foreach ($ebay_data as $ebay_vendor_data) {
						if(isset($ebay_vendor_data['ListingDetails']['EndTime']) && !empty($ebay_vendor_data['ListingDetails']['EndTime'])){
							$expire_item_date = strtotime($ebay_vendor_data['ListingDetails']['EndTime']);
							$last_sixty_days = strtotime("-60 days");
							
							if($product_import_setting['wcfm_ie_form_setting_sold_items'] == 'remove' && $expire_item_date < strtotime(date('Y-m-d\TH:i:s.000\Z'))) {
								try{
									$ebay_product_id = $this->get_product_id_by_sku($ebay_vendor_data['ItemID']);
									if($ebay_product_id){
										$deleted_product++;
										wp_delete_post( $ebay_product_id );
									}
									continue;
								}catch(Exception $e) {
									$this->setLog('Deleted product: '.$e->getMessage());
								}
							}
							
							if((isset($product_import_setting['wcfm_ie_form_setting_unsold_items']) && $product_import_setting['wcfm_ie_form_setting_unsold_items'] == true ) && ($expire_item_date > strtotime(date('Y-m-d\TH:i:s.000\Z'))) ){
								continue;
							}elseif((isset($product_import_setting['wcfm_ie_form_setting_unsold_items']) && $product_import_setting['wcfm_ie_form_setting_unsold_items'] == true ) && ($expire_item_date < $last_sixty_days) && ($ebay_vendor_data['SellingStatus']['QuantitySold']=='1') ){
								continue;
							}
						}

						if( ($product_import_setting['wcfm_ie_form_setting_sold_items'] == 'remove_sold') && ($ebay_vendor_data['SellingStatus']['QuantitySold'] == '1' && $ebay_vendor_data['SellingStatus']['ListingStatus'] == 'Completed') || $ebay_vendor_data['SellingStatus']['ListingStatus'] == 'Ended' )  {
							//$this->setLog('Sold: '.$ebay_vendor_data['ItemID']);
							try{
								$product_id = $this->get_product_id_by_sku($ebay_vendor_data['ItemID']);
								if($product_id){
									$deleted_product = wp_delete_post( $product_id );
								}
								continue;
							}catch(Exception $e) {
								$this->setLog('Deleted: '.$e->getMessage());
							}
						}

						if(isset($ebay_vendor_data['Quantity']) && $ebay_vendor_data['Quantity'] == '0' ){
							continue;
						}

						$ebay_product_id = $this->get_product_id_by_sku($ebay_vendor_data['ItemID']);
						if(isset($product_import_setting['wcfm_ie_form_setting_revise']) && ($product_import_setting['wcfm_ie_form_setting_revise'] == 'false' && $ebay_product_id)){
							continue;
						}

						if(isset($product_import_setting['wcfm_ie_form_setting_revise']) && ($product_import_setting['wcfm_ie_form_setting_revise'] == 'true')){
							$modified_product_query = "SELECT ebay_item_id FROM ".$this->modified_products." WHERE vendor_id = '".$vendor_id."' AND ebay_user_id = '".$ebay_user_id."' AND ebay_item_id = '".$ebay_vendor_data['ItemID']."' LIMIT 1  ";
							//$this->setLog('Query:'.$modified_product_query);
							$ebay_item_id = $wpdb->get_var($modified_product_query);
							$ItemID = $this->get_product_id_by_sku($ebay_vendor_data['ItemID']);
							if(!$ebay_item_id && $ItemID){
								continue;
							}
						}

						$price = $ebay_vendor_data['StartPrice'];
						
						if(isset($product_import_setting['wcfm_ie_form_setting_discount_price']) && $product_import_setting['wcfm_ie_form_setting_discount_price'] && $product_import_setting['wcfm_ie_form_setting_discount_amount'] =='full' && $ebay_vendor_data['StartPrice'] > 0 ){
							$price = ($ebay_vendor_data['StartPrice'] * 90)/100;
						}
						if((isset($product_import_setting['wcfm_ie_form_setting_without_bin']) && $product_import_setting['wcfm_ie_form_setting_without_bin']==true) && (isset($ebay_vendor_data['ListingDetails']['BuyItNowAvailable']) && $ebay_vendor_data['ListingDetails']['BuyItNowAvailable'] == 'true' ) ){
							continue;
						}else{
							if(isset($ebay_vendor_data['ListingDetails']['BuyItNowAvailable']) && $ebay_vendor_data['ListingDetails']['BuyItNowAvailable'] == 'true' ){
								$price = $ebay_vendor_data['BuyItNowPrice'];
							}
						}
						
						if(isset($ebay_vendor_data['SellingStatus']['ListingStatus']) && ($product_import_setting['wcfm_ie_form_setting_sold_items'] == 'remove' && $ebay_vendor_data['SellingStatus']['ListingStatus'] == 'Ended') ) {
							continue;
						}

						$additional_images = '';
						if(is_array($ebay_vendor_data['PictureDetails']['PictureURL']) && sizeof($ebay_vendor_data['PictureDetails']['PictureURL'])>0){
							foreach ($ebay_vendor_data['PictureDetails']['PictureURL'] as $gallery_image) {
								$gallery_images[] = esc_url( remove_query_arg($arr_params, $gallery_image ) );
							}
							$additional_images = implode(',',$gallery_images);
						}
						$wrap_category_name = explode(':', $ebay_vendor_data['PrimaryCategory']['CategoryName']);
						$category_name = end($wrap_category_name);
						if(isset($ebay_vendor_data['SellingStatus']['HighBidder']) && !empty($ebay_vendor_data['SellingStatus']['HighBidder']['FeedbackScore']) && $product_import_setting['wcfm_ie_form_setting_import_feedback'] == true){
							$feedback_score = $ebay_vendor_data['SellingStatus']['HighBidder']['FeedbackScore'];
						}else{
							$feedback_score = '';
						}
						if(isset($ebay_vendor_data['SellingStatus']['HighBidder']) && !empty($ebay_vendor_data['SellingStatus']['HighBidder']['PositiveFeedbackPercent']) && $product_import_setting['wcfm_ie_form_setting_import_feedback'] == true){
							$feedback_percent = $ebay_vendor_data['SellingStatus']['HighBidder']['PositiveFeedbackPercent'];
						}else{
							$feedback_percent = '';
						}
						$product_data[] = array(
							'product_id'		=>	$ebay_vendor_data['ItemID'],
							'product_title'		=>	$ebay_vendor_data['Title'],
							'description'		=>	$ebay_vendor_data['Description'],
							'quantity'			=>	$ebay_vendor_data['Quantity'],
							'regular_price'		=>	$price,
							'featured_image'	=>	esc_url( remove_query_arg($arr_params, $ebay_vendor_data['PictureDetails']['GalleryURL'] ) ),
							'gallery_images'	=>	$additional_images,
							'category_id'		=>	$ebay_vendor_data['PrimaryCategory']['CategoryID'],
							'category_name'		=>	$category_name,
							'feedback_score'	=>	$feedback_score,
							'feedback_percent'	=>	$feedback_percent,
							'stock_status'		=>	'instock',
							'condition'			=>	$ebay_vendor_data['ConditionDisplayName'],
						);
						unset($gallery_images);
					}
					if($deleted_product){
						$count_deleted_products = array();
						$count_deleted_products[$vendor_id][$ebay_user_id] = $deleted_product;
						update_option('wcfm_ie_deleted_products',$count_deleted_products);
					}
					if(sizeof($product_data)>0){
						$fh = fopen($filename, 'a');
						foreach ($product_data as $product) {
							if(sizeof($in_queued)===0){
								break;
							}
							fputcsv($fh, $product);
						}
						fclose($fh);
						chmod($filename,0777);
					}
					exit_loop($in_queued);
					if($ebay_user_response['HasMoreItems']=='true'){
						$pagination_current_page = get_user_meta($vendor_id,'wcfm_ie_product_current_page',true);
						$ebay_user_id 						= $ebay_user_id;
						$call_name 							= 'products';
						$ebay_user_info 					= $this->geteBayVendor($vendor_id);
						$ebay_user_info['call_name'] 		= 'GetSellerList';
						$ebay_user_info['from_date'] 		= $from_date;
						$ebay_user_info['end_date'] 		= $end_date;
						$ebay_user_info['ebay_user_id'] 	= $ebay_user_info['ebay_user_id'];
						$ebay_user_info['ebay_token'] 		= $ebay_user_info['ebay_token'];
						$current_page = $pagination_current_page+1;
						update_user_meta($vendor_id,'wcfm_ie_product_current_page',$current_page);
						$this->addCsvFileData($ebay_user_info,$vendor_id);
						die;
					}
					delete_user_meta($vendor_id,'wcfm_ie_product_current_page');
					if(strtotime(date('Y-m-d')) > strtotime($end_date) && strtotime(date('Y-m-d')) != strtotime($end_date)  ) {
						// $wpdb->insert($this->cron_request_tablename , array(
						// 	"vendor_id"     		=> 	$vendor_id,
						// 	"type"  				=> "products",
						// 	"hook_name"  			=> "wcfm_ie_create_all_products_file",
						// 	"from_date"  			=> date('Y-m-d h:i:s'),
						// 	"expire"  				=> date('Y-m-d h:i:s', time() + 10 ),
						// 	"date_added"  			=> date('Y-m-d h:i:s'),
						// ));
						$args = array ( 'file_name'=>$cron_file,'vendor_id'=>$vendor_id,'ebay_user_id' => $ebay_user_id,'end_date'=> $end_date);

						//$this->wcfm_schedule_single_event( time() + 10, "wcfm_ie_create_all_products_file", array($args) );
						do_action('wcfm_ie_create_all_products_file',$args);
						die;
					}
					// $wpdb->insert($this->cron_request_tablename , array(
					// 	"vendor_id"     		=> 	$vendor_id,
					// 	"type"  				=> "products",
					// 	"hook_name"  			=> "addWcfmEbayProductHook",
					// 	"from_date"  			=> date('Y-m-d h:i:s'),
					// 	"expire"  				=> date('Y-m-d h:i:s', time() + 10 ),
					// 	"date_added"  			=> date('Y-m-d h:i:s'),
					// ));
					$args = array ( 'file_name'=>$cron_file,'vendor_id'=>$vendor_id,'ebay_user_id' => $ebay_user_id);
					//$this->wcfm_schedule_single_event( time() + 10 , "addWcfmEbayProductHook", array($args) );
					do_action('addWcfmEbayProductHook',$args);
					die;
				}catch(Exception $e){
					$this->setLog('Fetching Products: '.$e->getMessage());
				}
				die;
			}else{
				delete_user_meta($vendor_id,'wcfm_ie_product_current_page');
				if(strtotime(date('Y-m-d')) > strtotime($end_date) && strtotime(date('Y-m-d')) != strtotime($end_date) ){
					$args = array ( 'file_name'=>$cron_file,'vendor_id'=>$vendor_id,'ebay_user_id' => $ebay_user_id,'end_date'=> $end_date);
					// $wpdb->insert($this->cron_request_tablename , array(
					// 	"vendor_id"     		=> 	$vendor_id,
					// 	"type"  				=> "products",
					// 	"hook_name"  			=> "wcfm_ie_create_all_products_file",
					// 	"from_date"  			=> date('Y-m-d h:i:s'),
					// 	"expire"  				=> date('Y-m-d h:i:s', time() + 10 ),
					// 	"date_added"  			=> date('Y-m-d h:i:s'),
					// ));
					// $this->wcfm_schedule_single_event( time() + 10, "wcfm_ie_create_all_products_file", array($args) );
					do_action('wcfm_ie_create_all_products_file',$args);
					die;
				}
				$args = array ( 'file_name'=>$cron_file,'vendor_id'=>$vendor_id,'ebay_user_id' => $ebay_user_id);
				// $wpdb->insert($this->cron_request_tablename , array(
				// 	"vendor_id"     		=> 	$vendor_id,
				// 	"type"  				=> "products",
				// 	"hook_name"  			=> "addWcfmEbayProductHook",
				// 	"from_date"  			=> date('Y-m-d h:i:s'),
				// 	"expire"  				=> date('Y-m-d h:i:s', time() + 10 ),
				// 	"date_added"  			=> date('Y-m-d h:i:s'),
				// ));
				do_action('addWcfmEbayProductHook',$args);
				//$this->wcfm_schedule_single_event( time() + 10, "addWcfmEbayProductHook", array($args) );
				die();
			}
		}else{
			//try{
			if(isset($ebay_user_response['Errors']) && $ebay_user_response['Errors']['ErrorCode']=='17470'){
				$wpdb->delete( $this->tablename, array( 'vendor_id' => $vendor_id,'is_primary' => '1' ) );
			}
			$wcfm_ie_alert[$vendor_id]['alert']['type'] = 'danger';
			$wcfm_ie_alert[$vendor_id]['alert']['msg'] =  $ebay_user_response['Errors']['ShortMessage'];
			update_user_meta($vendor_id,'ebay_in_queued',false);
			update_user_meta($vendor_id,'ebay_proccessing_bar',false);
			unlink($filename);
			//$this->clear_seller_single_schedule_event($vendor_id,'products','wcfm_ie_create_all_products_file');
			//$this->clear_seller_single_schedule_event($vendor_id,'products','addWcfmEbayProductHook');
			update_user_meta($vendor_id,'wcfm_ie_alert',$wcfm_ie_alert);
			$wpdb->delete( $this->request_tablename, array( 'vendor_id' => $vendor_id,'type' => 'products' ) );
			$redirect = array();
			$redirect['url'] = get_wcfm_url().'/ebay-import-export-settings/';
			$this->SetSocket()->trigger('fizno', 'location_redirect_'.$vendor_id, $redirect );
			die;
			// }catch(Exception $e){
			// 	$this->setLog('API Error '.$e->getMessage());
			// }
		}
		die;
	}
	// public function addOrderFieldData($ebay_user_response,$vendor_id){
	// 	global $wpdb;
	// 	if($ebay_user_response['Ack']=='Success'){
	// 		if($ebay_user_response['PaginationResult']['TotalNumberOfEntries']>0){

	// 			$ebay_user_id = $ebay_user_response['ebay_user_id'];
	// 			if(sizeof($this->get_vendor_request($ebay_user_id,$vendor_id,'orders'))>0){
	// 				$wpdb->query("UPDATE ".$this->request_tablename." SET 
	// 					current_page     =  ".$ebay_user_response['current_page']."
	// 					WHERE vendor_id = '".$vendor_id."' AND ebay_user_id = '".$ebay_user_id."' AND type  = 'orders'
	// 					");
	// 			}else{
	// 				$wpdb->insert( $this->request_tablename , array(
	// 					'vendor_id'     =>  $vendor_id,
	// 					'ebay_user_id'     =>  $ebay_user_id,
	// 					'type'  => 'orders',
	// 					'total_pages'  => $ebay_user_response['PaginationResult']['TotalNumberOfPages'],
	// 					'total_products'  => $ebay_user_response['PaginationResult']['TotalNumberOfEntries'],
	// 					'current_page'  => $ebay_user_response['current_page'],
	// 					'from_date'  => $ebay_user_response['from_date'],
	// 					'end_date'  => $ebay_user_response['end_date'],
	// 				));
	// 			}
	// 			$filename 	=	$this->file_path.$this->order_file_name.$ebay_user_id.'.csv';
	// 			$cron_file 	=	$this->order_file_name.$ebay_user_id.'.csv';
	// 			$fh = fopen($filename, 'w');
	// 			fputcsv($fh, $this->order_header_column);
	// 			$arr_params = array( 'set_id');
	// 			$order_data = array();
	// 			if($ebay_user_response['PaginationResult']['TotalNumberOfEntries']>1){
	// 				foreach ($ebay_user_response['OrderArray']['Order'] as $ebay_vendor_data) {
	// 					$shipping_address	=	array(
	// 						'name'			=>	$ebay_vendor_data['ShippingAddress']['Name'],
	// 						'address_1'		=>	$ebay_vendor_data['ShippingAddress']['Street1'],
	// 						'address_2'		=>	$ebay_vendor_data['ShippingAddress']['Street2'],
	// 						'city'			=>	$ebay_vendor_data['ShippingAddress']['CityName'],
	// 						'state_code'	=>	$ebay_vendor_data['ShippingAddress']['StateOrProvince'],
	// 						'country_code'	=>	(isset($ebay_vendor_data['ShippingAddress']['Country'])?$ebay_vendor_data['ShippingAddress']['Country']:''),
	// 						'country'		=>	$ebay_vendor_data['ShippingAddress']['CountryName'],
	// 						'phone'			=>	$ebay_vendor_data['ShippingAddress']['Phone'],
	// 						'postal_code'	=>	$ebay_vendor_data['ShippingAddress']['PostalCode'],
	// 					);
	// 					$product_item = array();
	// 					if(array_key_exists('0', $ebay_vendor_data['TransactionArray']['Transaction'])){
	// 						foreach ($ebay_vendor_data['TransactionArray']['Transaction'] as $item) {
	// 							$product_item[] = array(
	// 								'product_id'	=>	$item['Item']['ItemID'],
	// 								'qty'			=>	$item['QuantityPurchased'],
	// 								'buyer_email'	=>	($item['Buyer']['Email']!='Invalid Request'?$item['Buyer']['Email']:''),
	// 								'buyer_user_id'	=>	$ebay_vendor_data['BuyerUserID'],
	// 								'shipping_name'	=>	$ebay_vendor_data['ShippingAddress']['Name'],
	// 							);
	// 						}
	// 					}else{
	// 						$product_item[] = array(
	// 							'product_id'	=>	$ebay_vendor_data['TransactionArray']['Transaction']['Item']['ItemID'],
	// 							'qty'			=>	$ebay_vendor_data['TransactionArray']['Transaction']['QuantityPurchased'],
	// 							'buyer_email'	=>	($ebay_vendor_data['TransactionArray']['Transaction']['Buyer']['Email']!='Invalid Request'?$ebay_vendor_data['TransactionArray']['Transaction']['Buyer']['Email']:''),
	// 							'buyer_user_id'	=>	$ebay_vendor_data['BuyerUserID'],
	// 							'shipping_name'	=>	$ebay_vendor_data['ShippingAddress']['Name'],
	// 						);
	// 					}
	// 					$order_data[] = array(
	// 						'order_id'			=>	$ebay_vendor_data['OrderID'],
	// 						'order_status'		=>	$ebay_vendor_data['OrderStatus'],
	// 						'order_total'		=>	$ebay_vendor_data['Total'],
	// 						'sub_total'			=>	$ebay_vendor_data['Subtotal'],
	// 						'order_created'		=>	$ebay_vendor_data['CreatedTime'],
	// 						'paid_date'			=>	(isset($ebay_vendor_data['PaidTime'])?$ebay_vendor_data['PaidTime']:''),
	// 						'shipped_date'		=>	(isset($ebay_vendor_data['ShippedTime'])?$ebay_vendor_data['ShippedTime']:''),
	// 						'payment_method'	=>	$ebay_vendor_data['PaymentMethods'],
	// 						'shipping_address'	=>	json_encode($shipping_address),
	// 						'item'				=>	json_encode($product_item),
	// 						'buyer_user_id'		=>	$ebay_vendor_data['BuyerUserID']
	// 					);
	// 				}
	// 			}else{
	// 				$shipping_address	=	array(
	// 					'name'			=>	$ebay_user_response['OrderArray']['Order']['ShippingAddress']['Name'],
	// 					'address_1'		=>	$ebay_user_response['OrderArray']['Order']['ShippingAddress']['Street1'],
	// 					'address_2'		=>	$ebay_user_response['OrderArray']['Order']['ShippingAddress']['Street2'],
	// 					'city'			=>	$ebay_user_response['OrderArray']['Order']['ShippingAddress']['CityName'],
	// 					'state_code'	=>	$ebay_user_response['OrderArray']['Order']['ShippingAddress']['StateOrProvince'],
	// 					'country_code'	=>	$ebay_user_response['OrderArray']['Order']['ShippingAddress']['Country'],
	// 					'country'		=>	$ebay_user_response['OrderArray']['Order']['ShippingAddress']['CountryName'],
	// 					'phone'			=>	$ebay_user_response['OrderArray']['Order']['ShippingAddress']['Phone'],
	// 					'postal_code'	=>	$ebay_user_response['OrderArray']['Order']['ShippingAddress']['PostalCode'],
	// 				);
	// 				$product_item = array();
	// 				if(array_key_exists('0', $ebay_user_response['OrderArray']['Order']['TransactionArray']['Transaction'])){
	// 					foreach ($ebay_vendor_data['TransactionArray']['Transaction'] as $item) {
	// 						$product_item[] = array(
	// 							'product_id'	=>	$item['Item']['ItemID'],
	// 							'qty'			=>	$item['QuantityPurchased'],
	// 							'buyer_email'	=>	($item['Buyer']['Email']!='Invalid Request'?$item['Buyer']['Email']:''),
	// 							'buyer_user_id'	=>	$ebay_vendor_data['BuyerUserID'],
	// 							'shipping_name'	=>	$ebay_vendor_data['ShippingAddress']['Name'],
	// 						);
	// 					}
	// 				}else{
	// 					$product_item[] = array(
	// 						'product_id'			=>	$ebay_user_response['OrderArray']['Order']['TransactionArray']['Transaction']['Item']['ItemID'],
	// 						'qty'					=>	$ebay_user_response['OrderArray']['Order']['TransactionArray']['Transaction']['QuantityPurchased'],
	// 						'buyer_email'			=>	($ebay_user_response['TransactionArray']['Transaction']['Buyer']['Email']!='Invalid Request'?$ebay_user_response['TransactionArray']['Transaction']['Buyer']['Email']:''),
	// 						'buyer_user_id'			=>	$ebay_vendor_data['BuyerUserID'],
	// 						'shipping_name'		=>	$ebay_vendor_data['ShippingAddress']['Name'],
	// 					);
	// 				}
	// 				$order_data[] = array(
	// 					'order_id'			=>	$ebay_user_response['OrderArray']['Order']['OrderID'],
	// 					'order_status'		=>	$ebay_user_response['OrderArray']['Order']['OrderStatus'],
	// 					'order_total'		=>	$ebay_user_response['OrderArray']['Order']['Total'],
	// 					'sub_total'			=>	$ebay_user_response['OrderArray']['Order']['Subtotal'],
	// 					'order_created'		=>	$ebay_user_response['OrderArray']['Order']['CreatedTime'],
	// 					'paid_date'			=>	$ebay_user_response['OrderArray']['Order']['PaidTime'],
	// 					'shipped_date'		=>	$ebay_user_response['OrderArray']['Order']['ShippedTime'],
	// 					'payment_method'	=>	$ebay_user_response['OrderArray']['Order']['PaymentMethods'],
	// 					'shipping_address'	=>	json_encode($shipping_address),
	// 					'item'				=>	json_encode($product_item),
	// 					'buyer_user_id'		=>	$ebay_user_response['BuyerUserID'],
	// 				);
	// 			}
	// 			foreach ($order_data as $order) {
	// 				fputcsv($fh, $order);
	// 			}
	// 			fclose($fh);
	// 			chmod($filename,0777);
	// 			$args = array(array ( 'file_name'=>$cron_file,'vendor_id'=>$vendor_id,'ebay_user_id' => $ebay_user_id ));
	// 			if( ! wp_next_scheduled( "addWcfmEbayOrderHook" ) ) {
	// 				$this->wcfm_schedule_single_event( time() + 10, "addWcfmEbayOrderHook", $args );
	// 			}
	// 			if( !headers_sent() && '' == session_id() ) {
	// 				session_start();
	// 			}
	// 			$_SESSION['ebay_file_data'][$cron_file] = array(
	// 				'vendor_id'	=>	$vendor_id,
	// 				'ebay_user_id'	=>	$ebay_user_id,
	// 				'timestamp'	=>	current_time('timestamp'),
	// 				'msg'	=>	'Orders sync please wait...',
	// 				'type'	=>	'Orders',
	// 			);
	// 			$json['type'] = 'success';
	// 			$json['msg'] = 'Order uploading in proccessing...';
	// 		}else{
	// 			$json['type'] = 'warning';
	// 			$json['msg'] = '<b>Opps!</b> Between this Date Duration you don\'t have any ebay order listing.';
	// 		}
	// 	}else{
	// 		$json['type'] = 'danger';
	// 		$json['msg'] = '<b>Error:</b> Your ebay credientials is invalid. Please check again.';
	// 	}
	// 	echo json_encode($json);
	// 	die;
	// }
	// public function mappingCategories($ebay_user_response,$vendor_id){

	// 	//if($ebay_user_response['PaginationResult']['TotalNumberOfEntries']>0){
	// 	$filename 	=	$this->file_path.$this->category_file_name.$this->get_username($vendor_id).'.csv';
	// 	$cron_file 	=	$this->category_file_name.$this->get_username($vendor_id).'.csv';
	// 	$fh = fopen($filename, 'w');

	// 	fputcsv($fh, $this->category_header_column);
	// 	foreach ($ebay_user_response['CategoryArray']['Category'] as $ebay_vendor_data) {
	// 		$category_data[] = array(
	// 			'category_id'			=>	$ebay_vendor_data['CategoryID'],
	// 			'category_name'			=>	$ebay_vendor_data['CategoryName'],
	// 			'category_parent_id'	=>	$ebay_vendor_data['CategoryParentID'],
	// 		);
	// 	}

	// 	foreach ($category_data as $category) {
	// 		fputcsv($fh, $category);
	// 	}

	// 	fclose($fh);
	// 	chmod($filename,0777);

	// 	$args = array(array ('file_name'=>$cron_file,'vendor_id'=>$vendor_id ));
	// 	$this->wcfm_schedule_single_event( current_time( 'timestamp' ) + 10, 'addWcfmEbayCategoryHook', $args );

	// 	$json['type'] = 'success';
	// 	$json['msg'] = 'Please wait, categories are mapping';
	// 	echo json_encode($json);
	// 	die;
	// }
	public function addWcfmEbayProductHook($cron_arg){
		global $wpdb;
		$vendor_id 		= $cron_arg['vendor_id'];
		$ebay_user_id 	= $cron_arg['ebay_user_id'];
		$wcfm_ie_options = array(); 
		$wcfm_ie_options = get_option('wcfm_ie_form_setting');
		$sellers_deleted_products = array();
		$sellers_deleted_products = get_option('wcfm_ie_deleted_products');
		if($wcfm_ie_options && sizeof($wcfm_ie_options)>0){
			$product_import_setting		= $wcfm_ie_options[$vendor_id];
		}
		$wcfm_ie_alert = array();
		update_user_meta($vendor_id,'ebay_in_queued',false);
		update_user_meta($vendor_id,'ebay_proccessing_bar',true);
		$total_products=0;
		$row_count = 0;
		if (($fp = fopen($this->file_path.$this->product_file_name.$vendor_id.'_'.$ebay_user_id.".csv", "r")) !== FALSE) {
			while(!feof($fp)) {
				$data = fgetcsv($fp , 0 , ',' , '"', '"' );
				if(empty($data)) continue;
				$row_count++;
			}
			fclose($fp);
			$total_products = $row_count-1;
		}
		$wpdb->update($this->request_tablename, array('total_products'=>$total_products),array('vendor_id'=> $vendor_id,'type'=>'products'));

		$filename = $this->file_path.$cron_arg['file_name'];
		$in_queued = array();
		$in_queued = getCurrentVendorRequest($vendor_id,'products');
		exit_loop($in_queued);
		try{
			if(file_exists($filename)){
				$handle = fopen($filename, "r");
				$row_count = count(file($filename, FILE_SKIP_EMPTY_LINES));
				$skip_first_row = true;
				if($row_count>1){
					$products 		= array();
					while (($data 	= fgetcsv($handle, 10000, ",")) !== FALSE) {
						if(sizeof($in_queued)===0){
							break;
						}
						if(!$skip_first_row){
							$products[] = array_combine($this->product_header_column, $data);
						}
						$skip_first_row = false;
					}
					fclose($handle);
					if(sizeof($products)>0){
						$progress_start = array();
						$progress_start['total'] = $total_products;
						$this->SetSocket()->trigger('fizno', 'progress_start_'.$vendor_id, $progress_start );
						foreach ($products as $key=>$product) {
							if(sizeof($in_queued)===0){
								break;
							}
							$is_product_created = $this->insertProduct($product,$vendor_id,$ebay_user_id);
							if($is_product_created){
								unset($products[$key]);
								$update_file = fopen($filename, "w");
								fputcsv($update_file, $this->product_header_column);
								foreach ($products as $update_data) {
									if(sizeof($in_queued)===0){
										break;
									}
									fputcsv($update_file, $update_data);
								}
								fclose($update_file);
							}
						}
						unset($products);
						$completed_importing = array();
						$completed_importing['msg'] = 'Completed';

						$wcfm_ie_alert[$vendor_id]['alert']['type'] = 'success';
						$wcfm_ie_alert[$vendor_id]['alert']['msg'] = 'Products successfully imported as per your selection.';
						$this->SetSocket()->trigger('fizno', 'completed_importing_'.$vendor_id, $completed_importing );

					}else{
						if($sellers_deleted_products && $sellers_deleted_products[$vendor_id][$ebay_user_id]){
							$timestamp = time();
							$total_delete_products = $sellers_deleted_products[$vendor_id][$ebay_user_id];
							$wcfm_ie_multi_alert[$vendor_id][$timestamp]['alert']['type'] = 'success';
							$wcfm_ie_multi_alert[$vendor_id][$timestamp]['alert']['msg'] = 'As per your request we don\'t found any new item listed in eBay but '.$total_delete_products.' products were removed from your eBay account so we also removed same from your Fizno account';
							unset($sellers_deleted_products[$vendor_id][$ebay_user_id]);
							update_option('wcfm_ie_deleted_products',$sellers_deleted_products);
							update_option('wcfm_ie_multi_alert',$wcfm_ie_multi_alert);
						}else{	
							$wcfm_ie_alert[$vendor_id]['alert']['type'] = 'danger';
							$wcfm_ie_alert[$vendor_id]['alert']['msg'] = 'We can\'t seem to find any product that match your selection.';
						}
					}
				}else{
					if($sellers_deleted_products && $sellers_deleted_products[$vendor_id][$ebay_user_id]){
						$timestamp = time();
						$total_delete_products = $sellers_deleted_products[$vendor_id][$ebay_user_id];
						$wcfm_ie_multi_alert[$vendor_id][$timestamp]['alert']['type'] = 'success';
						$wcfm_ie_multi_alert[$vendor_id][$timestamp]['alert']['msg'] = 'As per your request we don\'t found any new item listed in eBay but '.$total_delete_products.' products were removed from your eBay account so we also removed same from your Fizno account';
						unset($sellers_deleted_products[$vendor_id][$ebay_user_id]);
						update_option('wcfm_ie_deleted_products',$sellers_deleted_products);
						update_option('wcfm_ie_multi_alert',$wcfm_ie_multi_alert);
					}else{	
						$wcfm_ie_alert[$vendor_id]['alert']['type'] = 'danger';
						$wcfm_ie_alert[$vendor_id]['alert']['msg'] = 'We can\'t seem to find any product that match your selection.';
					}
				}


				update_user_meta($vendor_id,'ebay_in_queued',false);
				update_user_meta($vendor_id,'ebay_proccessing_bar',false);
				unlink($filename);
				// $this->clear_seller_single_schedule_event($vendor_id,'products','wcfm_ie_create_all_products_file');
				// $this->clear_seller_single_schedule_event($vendor_id,'products','addWcfmEbayProductHook');
				update_user_meta($vendor_id,'wcfm_ie_alert',$wcfm_ie_alert);
				$wpdb->delete( $this->request_tablename, array( 'vendor_id' => $vendor_id,'type' => 'products'));
				$redirect = array();
				$redirect['url'] = get_wcfm_url().'/ebay-import-export-settings/';
				$this->SetSocket()->trigger('fizno', 'location_redirect_'.$vendor_id, $redirect );
				die;
			}
		}catch(Exception $e){
			$this->setLog('Inserting Products: '.$e->getMessage());
		}
	}	
	// public function addWcfmEbayOrderHook($cron_arg){
	// 	global $wpdb;
	// 	$filename = $this->file_path.$cron_arg['file_name'];
	// 	$vendor_id = $cron_arg['vendor_id'];
	// 	$ebay_user_id 	= $cron_arg['ebay_user_id'];
	// 	try{
	// 		if(file_exists($filename)){
	// 			$handle = fopen($filename, "r");
	// 			$row_count = count(file($filename, FILE_SKIP_EMPTY_LINES));
	// 			$skip_first_row = true;
	// 			if($row_count>1){
	// 				$orders = array();
	// 				while (($data = fgetcsv($handle, 10000, ",")) !== FALSE) {
	// 					if(!$skip_first_row){
	// 						$orders[] = array_combine($this->order_header_column, $data);
	// 					}
	// 					$skip_first_row = false;
	// 				}
	// 				fclose($handle);
	// 				if(sizeof($orders)>0){
	// 					foreach ($orders as $key=>$order) {
	// 						$is_order_created = $this->insertOrder($order,$vendor_id);
	// 						if($is_order_created){
	// 							unset($orders[$key]);
	// 							$update_file = fopen($filename, "w");
	// 							fputcsv($update_file, $this->order_header_column);
	// 							foreach ($orders as $update_data) {
	// 								fputcsv($update_file, $update_data);
	// 							}
	// 							fclose($update_file);
	// 						}
	// 					}
	// 					unset($orders);
	// 				}
	// 			}
	// 		}
	// 	}catch(Exception $e){
	// 		error_log($e->getMessage());
	// 	}
	// }
	public function insertProduct($product = array(),$vendor_id, $ebay_user_id){
		global $wpdb;
		$in_queued = array();
		$in_queued = getCurrentVendorRequest($vendor_id,'products');
		$wcfm_ie_options = array(); 
		$wcfm_ie_options = get_option('wcfm_ie_form_setting');
		if($wcfm_ie_options && sizeof($wcfm_ie_options)>0){
			$form_setting_sold_items = $wcfm_ie_options[$vendor_id]['wcfm_ie_form_setting_sold_items'];
			$form_setting_revise = $wcfm_ie_options[$vendor_id]['wcfm_ie_form_setting_revise'];
		}
		try{
			exit_loop($in_queued);
			$post_id = $this->get_product_id_by_sku($product['product_id']);
			if($form_setting_revise =='false' && $post_id){
				$wpdb->query("UPDATE ".$this->request_tablename." SET done = done + 1 WHERE vendor_id = '".$vendor_id."' AND type = 'products' ");
				$vendor_product_request = getCurrentVendorRequest($vendor_id,'products');
				$socket_msg = array();
				if(sizeof($vendor_product_request)>0) {
					if($vendor_product_request['total_products'] && $vendor_product_request['done']){
						$socket_msg['percentage'] = ($vendor_product_request['done']/$vendor_product_request['total_products']*100);
						$socket_msg['done'] = $vendor_product_request['done'];
						$socket_msg['total'] = $vendor_product_request['total_products'];
						$this->SetSocket()->trigger('fizno', 'importing_'.$vendor_id, $socket_msg );
					}
				}
				return $post_id;
			}
			$filename       = basename($this->get_img_token($product['featured_image']).'.jpg');
			$gallery_images = array();
			if( wp_mkdir_p( $this->upload_dir['path'] ) ) {
				$file = $this->upload_dir['path'] . '/' . $filename;
				$file_url = $this->upload_dir['url'] . '/' . $filename;
				$upload_dir = $this->upload_dir['path'];
				$upload_url = $this->upload_dir['url'];
			} else {
				$file = $this->upload_dir['basedir'] . '/' . $filename;
				$file_url = $this->upload_dir['baseurl'] . '/' . $filename;
				$upload_dir = $this->upload_dir['basedir'];
				$upload_url = $this->upload_dir['baseurl'];
			}

			if($post_id && $form_setting_revise == 'true'){
				$product_detail = new WC_Product_Simple($post_id);
				$product_detail->set_props(array(
					'sku' => $product['product_id'],
					'purchase_note' => '',
					'downloadable' => false,
					'virtual' => false,
					'featured' => false,
					'catalog_visibility' => 'visible',
					'regular_price' => $product['regular_price'],
					'manage_stock' => 'yes',
					'stock_status' => wc_clean( $product['stock_status'] ),
					'stock' => $product['quantity'],
				));

				update_post_meta( $post_id, '_ebay_user_id', $ebay_user_id );
				try{
					if(!file_exists($file)){
						$attach_id = $this->Generate_Featured_Image($product['featured_image'],$post_id);
						set_post_thumbnail( $post_id, $attach_id );
					}else{
						$attach_id = attachment_url_to_postid($file_url );
						set_post_thumbnail( $post_id, $attach_id );
					}
				}catch(Exception $e){
					$this->setLog('Featured Image: '.$e->getMessage());
				}
				try{
					if(isset($product['gallery_images']) && !empty($product['gallery_images'])){
						$gallery_images = explode(',', $product['gallery_images']);
						if(sizeof($gallery_images)>0){
							$attach_ids = array();
							foreach ($gallery_images as $gallery_image) {
								if(!file_exists($upload_dir . '/' . $this->get_img_token($gallery_image).'.jpg')){
									$attach_ids[] = $this->Generate_Featured_Image($gallery_image,$post_id);
								}else{
									$attach_ids[] = attachment_url_to_postid($upload_url . '/' . $this->get_img_token($gallery_image).'.jpg');
								}
							}
							update_post_meta($post_id, '_product_image_gallery', implode(',',$attach_ids));
						}
					}
				}catch(Exception $e){
					$this->setLog('Gallery Image: '.$e->getMessage());
				}
				try{
					if(!term_exists(sanitize_title($product['category_name']))){
						$category_data = wp_insert_term( $product['category_name'], 'product_cat', array(
							'slug'        => sanitize_title($product['category_name'])
						) );
						if(!$this->is_cat_db($product['category_id']) && $category_data['term_id']){
							$wpdb->delete( $this->cat_tablename, array( 'api_cat_id' => $product['category_id'] ) );
							$wpdb->insert( $this->cat_tablename , array(
								'term_id'     =>  $category_data['term_id'],
								'api_cat_id'  => $product['category_id']
							));
						}
						if($category_data['term_id']){	
							wp_set_object_terms($post_id,$category_data['term_id'], 'product_cat');
						}
					}else{
						$term = get_term_by('slug', sanitize_title($product['category_name']), 'product_cat');
						wp_set_object_terms($post_id, $term->term_id, 'product_cat');
					}
				}catch(Exception $e){
					$this->setLog('Category insert: '.$e->getMessage());
				}

				try{
					if(term_exists(sanitize_title($product['condition']),'product_brand') ){
						$term = get_term_by('slug', sanitize_title($product['condition']), 'product_brand'); 

					}
					wp_set_post_terms( $post_id, array($term->term_id), 'product_brand' );
				}catch(Exception $e){
					$this->setLog('Set Product Brand'.$e->getMessage());
				}
				$product_detail->save();
			}

			if(!$post_id){
				$new_product = array(
					'post_title' => $product['product_title'],
					'post_status' => 'publish',
					'post_type' => 'product',
					'post_content' => str_replace(array("'", "\""), "", strip_tags($product['description'])),
					'post_author' => $vendor_id,
				);
				$post_id = wp_insert_post($new_product, true);

				$product_detail = new WC_Product_Simple($post_id);
				$errors = $product_detail->set_props(array(
					'sku' => $product['product_id'],
					'purchase_note' => '',
					'downloadable' => false,
					'virtual' => false,
					'featured' => false,
					'catalog_visibility' => 'visible',
					'regular_price' => $product['regular_price'],
					'manage_stock' => 'yes',
					'stock_status' => wc_clean( $product['stock_status'] ),
					'stock' => $product['quantity'],
				));

				update_post_meta( $post_id, '_ebay_user_id', $ebay_user_id );

				if(!file_exists($file)){
					$attach_id = $this->Generate_Featured_Image($product['featured_image'],$post_id);
					if($attach_id){
						set_post_thumbnail( $post_id, $attach_id );
					}
				}else{
					$attach_id = attachment_url_to_postid($file_url );
					if($attach_id){
						set_post_thumbnail( $post_id, $attach_id );
					}
				}
				if(isset($product['gallery_images']) && !empty($product['gallery_images'])){
					$gallery_images = explode(',', $product['gallery_images']);
					if(sizeof($gallery_images)>0){
						$attach_ids = array();
						foreach ($gallery_images as $gallery_image) {
							if(!file_exists($upload_dir . '/' . $this->get_img_token($gallery_image).'.jpg')){
								$attach_ids[] = $this->Generate_Featured_Image($gallery_image,$post_id);
							}else{
								$attach_ids[] = attachment_url_to_postid($upload_url . '/' . $this->get_img_token($gallery_image).'.jpg');
							}
						}
						update_post_meta($post_id, '_product_image_gallery', implode(',',$attach_ids));
					}
				}

				if(isset($product['category_name']) && !empty($product['category_name'])){
					if(!term_exists(sanitize_title($product['category_name']))){
						$category_data = wp_insert_term( $product['category_name'], 'product_cat', array(
							'slug' => sanitize_title($product['category_name'])
						) );
						if(!$this->is_cat_db($product['category_id'])){
							$wpdb->delete( $this->cat_tablename, array( 'api_cat_id' => $product['category_id'] ) );
							if(isset($category_data['term_id']) && $category_data['term_id']){
								try{
									$cat_ins = $wpdb->insert( $this->cat_tablename , array(
										'term_id'     =>  $category_data['term_id'],
										'api_cat_id'  => $product['category_id']
									));
								}catch(Exception $e){
									$this->setLog('Custom category insert: '.$e->getMessage());
								}
							}
						}
						wp_set_object_terms($post_id,$category_data['term_id'], 'product_cat');
					}else{
						try{
							$term = get_term_by('slug', sanitize_title($product['category_name']), 'product_cat');
							if(!is_wp_error($term) && !$this->get_db_category($product['category_id'])){
								$cat_exe = $wpdb->insert( $this->cat_tablename , array(
									'term_id'     =>  $term->term_id,
									'api_cat_id'  => $product['category_id']
								));
							}
							wp_set_object_terms($post_id, $term->term_id, 'product_cat');
						}catch(Exception $e){
							$this->setLog('Custom category insert: '.$e->getMessage());
						}
					}
				}
				if(isset($product['feedback_score']) && !empty($product['feedback_score'])){
					add_post_meta($post_id,'ebay_product_feedback_score',$product['feedback_score']);
				}
				if(isset($product['feedback_percent']) && !empty($product['feedback_percent'])){
					add_post_meta($post_id,'ebay_product_feedback_percent',$product['feedback_percent']);
				}

				if(term_exists(sanitize_title($product['condition']),'product_brand') ){
					$term = get_term_by('slug', sanitize_title($product['condition']), 'product_brand'); 
					try{
						wp_set_post_terms( $post_id, array($term->term_id), 'product_brand' );
					}catch(Exception $e){
						$this->setLog('Set Product Brand'.$e->getMessage());
					}

				}
				$product_detail->save();
				//wc_delete_product_transients( $post_id );
			}
		}catch(Exception $e){
			$this->setLog('Product Creating: '.$e->getMessage());
		}
		//if(isset($product_import_setting['wcfm_ie_form_setting_revise']) && $product_import_setting['wcfm_ie_form_setting_revise'] == 'true'){
		$wpdb->delete( $this->modified_products, array( 'vendor_id' => $vendor_id,'ebay_user_id' => $ebay_user_id,'ebay_item_id'=>$product['product_id']));
			//	}
		
		$wpdb->query("UPDATE ".$this->request_tablename." SET done = done + 1 WHERE vendor_id = '".$vendor_id."' AND type = 'products' ");
		$vendor_product_request = getCurrentVendorRequest($vendor_id,'products');
		$socket_msg = array();
		if(sizeof($vendor_product_request)>0){
			if($vendor_product_request['total_products'] && $vendor_product_request['done']){
				$socket_msg['percentage'] = ($vendor_product_request['done']/$vendor_product_request['total_products']*100);
				$socket_msg['done'] = $vendor_product_request['done'];
				$socket_msg['total'] = $vendor_product_request['total_products'];
				$this->SetSocket()->trigger('fizno', 'importing_'.$vendor_id, $socket_msg );
			}
		}
		return $post_id;
	}
	// public function insertOrder($order,$vendor_id){
	// 	global $wpdb;
	// 	try{
	// 		$order_id = $wpdb->get_var("SELECT p.ID FROM ".$wpdb->prefix."api_order ao LEFT JOIN ".$wpdb->prefix."posts p ON(p.ID = ao.order_id) WHERE ao.api_order_id = '".$order['order_id']."'  ");
	// 		$this->disableEmailNotifications();
	// 		if(!$order_id){
	// 			$order_data                        = array();
	// 			$order_data[ 'post_status' ]       = 'wc-' . apply_filters( 'woocommerce_default_order_status', sanitize_title($order['order_status']) );
	// 			$order_data[ 'post_author' ]       = $vendor_id;
	// 			$order_data[ 'post_title' ]        = 'Order &ndash; '.date('F j, Y @ h:i A', strtotime( $order['order_created'] ) );
	// 			$order_data[ 'post_content' ]      = "";
	// 			$order_data[ 'post_type' ]         = 'shop_order';
	// 			$order_data[ 'comment_status' ]    = "closed";
	// 			$order_data[ 'ping_status' ]       = 'closed';
	// 			$order_id = wp_insert_post( apply_filters( 'woocommerce_new_order_data', $order_data ), true );
	// 			$exist_api_order_id = $wpdb->get_var("SELECT order_id FROM ".$wpdb->prefix."api_order WHERE api_order_id = '".$order['order_id']."' LIMIT 1 ");
	// 			if(!$exist_api_order_id){
	// 				$wpdb->insert( $this->order_tablename , array(
	// 					'order_id'     =>  $order_id,
	// 					'api_order_id'  => $order['order_id']
	// 				));
	// 			}
	//            // update_post_meta($order_id, 'transaction_id', $order['transaction_id'], true); 
	// 			update_post_meta($order_id, '_payment_method_title', esc_attr($order['payment_method']), true);
	// 			update_post_meta($order_id, '_order_total', esc_attr($order['order_total']), true);
	// 			update_post_meta($order_id, '_completed_date', get_the_date( $order['shipped_date'], 'Y-m-d H:i:s e'), true);
	// 			update_post_meta($order_id, '_paid_date', get_the_date( esc_attr($order['paid_date']), 'Y-m-d H:i:s e'), true);
	// 			$shipping_address = json_decode($order['shipping_address'],true);
	// 			$item = json_decode($order['item'],true);
	// 			$order = wc_get_order( $order_id );
	//             // $product_item_id = $order->add_product( wc_get_product( $product_id ));
	//             // wc_add_order_item_meta($product_item_id,"meta_key","meta_values");
	// 			@list( $shipping_firstname, $shipping_lastname ) = explode( " ", $shipping_address['name'], 2 );
	// 			$user_firstname  = sanitize_user( $shipping_firstname, true );
	// 			$user_lastname   = sanitize_user( $shipping_lastname, true );
	// 			$addressShipping = array(
	// 				'first_name' => $user_firstname,
	// 				'last_name' => $user_lastname,
 //            		//'email'      => $user_email_id,
	// 				'phone'      => stripslashes(!is_array($shipping_address['phone'])?$shipping_address['phone']:''),
	// 				'address_1'  => stripslashes(!is_array($shipping_address['address_1'])?$shipping_address['address_1']:''),
	// 				'address_2'  => stripslashes(!is_array($shipping_address['address_2'])?$shipping_address['address_2']:''),
	// 				'city'       => stripslashes(!is_array($shipping_address['city'])?$shipping_address['city']:''),
	// 				'state'      => stripslashes(!is_array($shipping_address['state_code'])?$shipping_address['state_code']:''),
	// 				'postcode'   => stripslashes(!is_array($shipping_address['postal_code'])?$shipping_address['postal_code']:''),
	// 				'country'    => stripslashes(!is_array($shipping_address['country_code'])?$shipping_address['country_code']:''),
	// 			);
	// 			$order->set_address( $addressShipping, 'shipping' );
	// 			$order->set_address( $addressShipping, 'billing' );
	// 			if(is_array($item) && sizeof($item)>0){
	// 				foreach ($item as $product) {
	// 					$product_id = $this->get_product_id_by_sku($product['product_id']);
	// 					if($product_id){
	// 						$order->add_product( wc_get_product($product_id), $product['qty']);
	// 					}
	// 					$user_id = $this->addCustomer($product);
	// 					if($user_id){
	// 						$order->set_customer_id( $user_id );
	// 					}
	// 					//update_post_meta($order_id, '_customer_user',get_user_by('login',$user_id ));
	// 				}
	// 			}
	// 			$note = __("This order is synced from the eBay.");
	// 			$order->add_order_note( $note );
	// 			$order->calculate_totals();
	// 			$order->save();
	// 		}
	// 	}catch(Exception $e){
	// 		$this->setLog('Creating order: '.$e->getMessage());
	// 	}
	// 	return true;
	// }
	// public function insertCategory($category){
	// 	global $wpdb;
	// 	try{
	// 		if(!term_exists(sanitize_title($category['category_name']))){
	// 			$category_data = wp_insert_term( $category['category_name'], 'product_cat', array(
	// 				//'parent'        => $category['category_parent_id'],
	// 				'slug'        => sanitize_title($category['category_name'])
	// 			) );
	// 			if(!$this->is_cat_db($category['category_id'])){

	// 				$wpdb->delete( $this->cat_tablename, array( 'api_cat_id' => $category['category_id'] ) );

	// 				$wpdb->insert( $this->cat_tablename , array(
	// 					'term_id'     =>  $category_data['term_id'],
	// 					'api_cat_id'  => $category['category_id']
	// 				));
	// 			}

	// 			if($this->get_db_category($category['category_parent_id'])){
	// 				wp_update_term( $category_data['term_id'], 'product_cat', array(
	// 					'parent'=> $this->get_db_category($category['category_parent_id'])
	// 				));
	// 			}
	// 		}else{
	// 			$term = get_term_by('slug', sanitize_title($category['category_name']), 'product_cat');

	// 			if(!$this->get_db_category($category['category_id'])){
	// 				$wpdb->insert( $this->cat_tablename , array(
	// 					'term_id'     =>  $term->term_id,
	// 					'api_cat_id'  => $category['category_id']
	// 				));
	// 			}
	// 			wp_update_term($term->term_id, 'product_cat', array(
	// 				'slug'	=> sanitize_title($category['category_name']),	
	// 				'parent'=> $this->get_db_category($category['category_parent_id'])
	// 			));
	// 		}
	// 		return true;
	// 	}catch(Exception $e){
	// 		echo $e->getMessage();
	// 	}

	// }
	public function Generate_Featured_Image( $image_url, $post_id){
		$filename       = basename($this->get_img_token($image_url).'.jpg');
	    $image_data       = file_get_contents($image_url); // Get image data
	    if(!$image_data){
	    	return;
	    }
	    if( wp_mkdir_p( $this->upload_dir['path'] ) ) {
	    	$file = $this->upload_dir['path'] . '/' . $filename;
	    } else {
	    	$file = $this->upload_dir['basedir'] . '/' . $filename;
	    }
	    // $this->setLog('Filename:'. $filename);
	    // $this->setLog('Image URL:'. $file);
	    file_put_contents( $file, $image_data );
	    $wp_filetype = wp_check_filetype( $filename, null );
	    $attachment = array(
	    	'post_mime_type' => $wp_filetype['type'],
	    	'post_title'     => sanitize_file_name( $filename ),
	    	'post_content'   => '',
	    	'post_status'    => 'inherit'
	    );
	    $attach_id = wp_insert_attachment( $attachment, $file, $post_id );
	    $this->setLog('Attach ID:'. $attach_id);
	    require_once(ABSPATH . 'wp-admin/includes/image.php');
	    $attach_data = wp_generate_attachment_metadata( $attach_id, $file );
	    wp_update_attachment_metadata( $attach_id, $attach_data );
	    return $attach_id;
	}
	public function get_product_id_by_sku($sku) {
		global $wpdb;
		$product_id = $wpdb->get_var("SELECT post_id FROM $wpdb->postmeta pm LEFT JOIN $wpdb->posts p ON(pm.post_id = p.ID) WHERE pm.meta_key='_sku' AND pm.meta_value='".$sku."' AND p.post_status IN ('publish','draft','private','pending') AND p.post_type = 'product'  LIMIT 1");
		if($product_id){
			return (int)$product_id;
		}else{
			return false;
		}
	}
	public function wpie_get_product_cat_image($images = "", $cat_id = "") {
		$image_list = explode(',', $images);
		$new_product_cat_errors = array();
		if (!empty($image_list)) {
			foreach ($image_list as $image_index => $image_url) {
				if ($image_url != "") {
					$image_url          = str_replace(' ', '%20', trim($image_url));
					$parsed_url         = parse_url($image_url);
					$pathinfo           = pathinfo($parsed_url['path']);
					$allowed_extensions = array('jpg', 'jpeg', 'gif', 'png');
					$url_ext            = explode('.', $image_url);
					if (!empty($url_ext)) {
						$image_ext = strtolower(end($url_ext));
					} else {
						$image_ext = "";
					}
					if (!in_array($image_ext, $allowed_extensions)) {
						$new_product_cat_errors[] = sprintf(__('A valid file extension wasn\'t found in %s. Extension found was %s. Allowed extensions are: %s.', WPIE_TEXTDOMAIN), $image_url, $image_ext, implode(', ', $allowed_extensions));
						continue;
					}
					$dest_filename = wp_unique_filename($this->upload_dir['path'], $pathinfo['basename']);
					$dest_path = $this->upload_dir['path'] . '/' . $dest_filename;
					$dest_url = $this->upload_dir['url'] . '/' . $dest_filename;
					if (ini_get('allow_url_fopen')) {
						if (!copy($image_url, $dest_path)) {
							$http_status = $http_response_header[0];
							$new_product_cat_errors[] = sprintf(__('%s encountered while attempting to download %s', WPIE_TEXTDOMAIN), $http_status, $image_url);
						}
					} elseif (function_exists('curl_init')) {
						$ch = curl_init($image_url);
						$fp = fopen($dest_path, "wb");
						$options = array(
							CURLOPT_FILE            => $fp,
							CURLOPT_HEADER          => 0,
							CURLOPT_FOLLOWLOCATION  => 1,
							CURLOPT_TIMEOUT         => 60);
						curl_setopt_array($ch, $options);
						curl_exec($ch);
						$http_status = intval(curl_getinfo($ch, CURLINFO_HTTP_CODE));
						curl_close($ch);
						fclose($fp);
						if ($http_status != 200) {
							unlink($dest_path);
							$new_product_cat_errors[] = sprintf(__('HTTP status %s encountered while attempting to download %s', WPIE_TEXTDOMAIN), $http_status, $image_url);
						}
					} else {
						$new_product_cat_errors[] = sprintf(__('Looks like %s is off and %s is not enabled. No images were imported.', WPIE_TEXTDOMAIN), '<code>allow_url_fopen</code>', '<code>cURL</code>');
						break;
					}
					if (!file_exists($dest_path)) {
						$new_product_cat_errors[] = sprintf(__('Couldn\'t download file %s.', WPIE_TEXTDOMAIN), $image_url);
						continue;
					}
					$new_post_image_paths[] = array(
						'path' => $dest_path,
						'source' => $image_url
					);
				}
			}
			if (!empty($new_post_image_paths)) {
				foreach ($new_post_image_paths as $image_index => $dest_path_info) {
					if (!file_exists($dest_path_info['path'])) {
						$new_product_cat_errors[] = sprintf(__('Couldn\'t find local file %s.', WPIE_TEXTDOMAIN), $dest_path_info['path']);
						continue;
					}
					$dest_url = str_ireplace(ABSPATH, home_url('/'), $dest_path_info['path']);
					$path_parts = pathinfo($dest_path_info['path']);
					$wp_filetype = wp_check_filetype($dest_path_info['path']);
					$attachment = array(
						'guid'            => $dest_url,
						'post_mime_type'  => $wp_filetype['type'],
						'post_title'      => preg_replace('/\.[^.]+$/', '', $path_parts['filename']),
						'post_content'    => '',
						'post_status'     => 'inherit'
					);
					$attachment_id = wp_insert_attachment($attachment, $dest_path_info['path']);
					if ($attachment_id && $attachment_id > 0) {
						update_term_meta($cat_id, 'thumbnail_id', $attachment_id);
						require_once(ABSPATH . 'wp-admin/includes/image.php');
						$attach_data = wp_generate_attachment_metadata($attachment_id, $dest_path_info['path']);
						wp_update_attachment_metadata($attachment_id, $attach_data);
					}
				}
			}
		}
		return $new_product_cat_errors;
	}
	public function get_img_token($image_url){
		$tokens = explode('/', $image_url);
		return $tokens[sizeof($tokens)-2];
	}
	public function is_cat_db($api_cat_id){
		global $wpdb;
		$sql = $wpdb->get_var("SELECT tm.term_id FROM ".$this->cat_tablename." ac LEFT JOIN ".$wpdb->prefix."terms tm ON(ac.term_id = tm.term_id) WHERE ac.api_cat_id = '".$api_cat_id."' ");
		if ($sql) {
			return $sql;
		}
	}
	public function get_db_category($api_cat_id){
		global $wpdb;
		$sql = $wpdb->get_var("SELECT term_id FROM ".$this->cat_tablename." WHERE api_cat_id = '".$api_cat_id."' LIMIT 1 ");
		if($sql){
			return $sql;
		}
	}
	public function get_vendor_request($ebay_user_id,$vendor_id,$type){
		global $wpdb;
		$result = $wpdb->get_row("SELECT * FROM ".$this->request_tablename." WHERE vendor_id='".$vendor_id."' AND type='".$type."' AND ebay_user_id = '".$ebay_user_id."' LIMIT 1");
		return (array)$result;
	}
	// public function set_product_category(){
	// 	$category_data = wp_insert_term( $getCategory['Name'], 'product_cat', array(
	// 		//'description' => ((isset($getCategory['Description']))?$getCategory['Description']:''),
	// 		//'parent'      => ((!empty($parent_id))?$parent_id:''),
	// 		'slug'        => sanitize_title($getCategory['Name'].$exist_term_slug)
	// 	) );
	// 	$wpdb->insert( $api_table, array(
	// 		'term_id'     =>  $category_data['term_id'],
	// 		'api_cat_id'  =>  $getCategory['Id']
	// 	));
	// }
	public function addCustomer( $details ) {
		if ( $user_id = email_exists( $details['buyer_email'] ) ) {
			return $user_id;
		}
		$buyer_user_id    = $details['buyer_user_id'];
		$user_name       = $details['buyer_user_id'];
		@list( $shipping_firstname, $shipping_lastname ) = explode( " ", $details['shipping_name'], 2 );
		$user_firstname  = sanitize_user( $shipping_firstname, true );
		$user_lastname   = sanitize_user( $shipping_lastname, true );
		$user_fullname   = sanitize_user( $details['shipping_name'], true );
		$random_password = wp_generate_password( $length=12, $include_standard_special_chars=false );
		$user_id = username_exists( $buyer_user_id );
		if ( $user_id ) {
			return $user_id;
		}
		$wp_user = array(
			'user_login' => $user_name,
			'user_email' => $details['buyer_email'],
			'first_name' => $user_firstname,
			'last_name' => $user_lastname,
			'user_pass' => $random_password,
			'role' => 'customer'
		);
		$user_id = wp_insert_user( $wp_user ) ;
		if ( is_wp_error($user_id)) {
			return false;
		}
		return $user_id;
	}
	public function disableEmailNotifications() {
		add_filter( 'woocommerce_email_enabled_new_order', array( $this, 'returnFalse' ), 10, 2 );
		add_filter( 'woocommerce_email_enabled_customer_completed_order', array( $this, 'returnFalse' ), 10, 2 );
		add_filter( 'woocommerce_email_enabled_customer_processing_order', array( $this, 'returnFalse' ), 10, 2 );
		add_filter( 'woocommerce_email_enabled_pip_email_invoice', array( $this, 'returnFalse' ), 10, 2 );
		add_filter( 'woocommerce_email_enabled_pip_email_packing_list', array( $this, 'returnFalse' ), 10, 2 );
		add_filter( 'woocommerce_email_enabled_pip_email_pick_list', array( $this, 'returnFalse' ), 10, 2 );
	}
	public function returnFalse( $param1, $param2 = false ) {
		return false;
	}
	public function wcfm_ie_create_all_products_file($cron_arg){
		global $wpdb;
		$vendor_id = $cron_arg['vendor_id'];
		$ebay_user_id 	= $cron_arg['ebay_user_id'];
		$wcfm_ie_setting = get_option('wcfm_ie_form_setting');
		$from_date = $cron_arg['end_date'];
		$end_date = date('Y-m-d',strtotime($from_date.'+120 days'));
		update_user_meta($vendor_id,'wcfm_ie_product_current_page',1);

		$ebay_user_id 						= $ebay_user_id;
		$call_name 							= 'products';
		$ebay_user_info 					= $this->geteBayVendor($vendor_id);
		$ebay_user_info['call_name'] 		= 'GetSellerList';
		$ebay_user_info['from_date'] 		= $from_date;
		$ebay_user_info['end_date'] 		= $end_date;
		$ebay_user_info['ebay_user_id'] 	= $ebay_user_info['ebay_user_id'];
		$ebay_user_info['ebay_token'] 		= $ebay_user_info['ebay_token'];
		$this->addCsvFileData($ebay_user_info,$vendor_id);
	}
	public function check_ebay_auth(){
		if(isset($_GET['username']) && !empty($_GET['username'])){
			global $wpdb;
			$headers = array(
				'Content-Type: text/xml',
				'X-EBAY-API-COMPATIBILITY-LEVEL:877',
				'X-EBAY-API-DEV-NAME:'.$this->dev_id,
				'X-EBAY-API-APP-NAME:'.$this->app_id,
				'X-EBAY-API-CERT-NAME:'.$this->cert_id,
				'X-EBAY-API-SITEID:'.$this->site_id,
				'X-EBAY-API-CALL-NAME:FetchToken'
			);
			$xml = '<?xml version="1.0" encoding="utf-8"?>
			<FetchTokenRequest xmlns="urn:ebay:apis:eBLBaseComponents">
			<SessionID>'.$_SESSION['ebay_session_id_temp'].'</SessionID>
			</FetchTokenRequest>';
			$ch  = curl_init('https://api.ebay.com/ws/api.dll');
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			$responsexml = curl_exec($ch);
			curl_close($ch);
			$xml_response = simplexml_load_string($responsexml);
			$json_response = json_encode($xml_response);
			$result = json_decode($json_response,TRUE);
			if($result['Ack']='Success'){
				$ebay_user_id = $_GET['username'];
				$vendor_id = get_current_user_id();
				$expire = $result['HardExpirationTime'];
				$exist_vendor = (array)$wpdb->get_row("SELECT ebay_user_id FROM ".$this->tablename." WHERE vendor_id='".$vendor_id."' AND ebay_user_id = '".$ebay_user_id."' LIMIT 1");
				if(!$exist_vendor){
					$wpdb->query("UPDATE ".$this->tablename." SET 
						is_primary     =  0
						WHERE vendor_id = '".$vendor_id."'
						");
					$wpdb->insert( $this->tablename , array(
						'vendor_id'			=>  $vendor_id,
						'ebay_user_id'		=>  $ebay_user_id,
						'ebay_country'		=>  0,
						'ebay_token'		=>  $result['eBayAuthToken'],
						'is_primary'		=>  1,
						'expire'			=>	$expire,
						'date_added'		=>  date('Y-m-d h:i:s'),
						'date_modified'		=>  date('Y-m-d h:i:s'),
					));
					//$wpdb->query($wpdb->prepare("UPDATE ".$this->tablename." SET ebay_token='".$result['eBayAuthToken']."'"));
					addAlert('success','Succesfully to added eBay account.');
					wp_redirect( get_wcfm_url().'/ebay-import-export-settings/' );
					die;
				}else{
					addAlert('danger','<b>'.$exist_vendor['ebay_user_id'].'</b> is already registered.');
					wp_redirect( get_wcfm_url().'/ebay-import-export-settings/' );
					die;
				}
			}else{
				wp_redirect( get_wcfm_url().'/ebay-import-export-settings/' );
				die;
			}
			unset($_SESSION['ebay_session_id_temp']);
		}
	}
	public function eBaySellerSelected(){
		if(isset($_GET['ebay_user_name']) && !empty($_GET['ebay_user_name']) && $_GET['selected']=='true'){
			global $wpdb;
			$vendor_id = get_current_user_id();
			$wpdb->query("UPDATE ".$this->tablename." SET 
				is_primary     =  '0'
				WHERE vendor_id = '".$vendor_id."'
				");
			$wpdb->query("UPDATE ".$this->tablename." SET 
				is_primary     =  '1'
				WHERE vendor_id = '".$vendor_id."' AND ebay_user_id = '".$_GET['ebay_user_name']."'
				");
			wp_redirect( get_wcfm_url().'/ebay-import-export-settings/' );
			die;
		}
	}
	public function redirect_to_signin_ebay(){
		if(isset($_GET['purpose']) && !empty($_GET['purpose']) && $_GET['purpose'] == 'import'){
			$run_name = 'bhanu_chauhan-bhanucha-fiznoi-okngjrdn';
			$headers = array(
				'Content-Type: text/xml',
				'X-EBAY-API-COMPATIBILITY-LEVEL:877',
				'X-EBAY-API-DEV-NAME:'.$this->dev_id,
				'X-EBAY-API-APP-NAME:'.$this->app_id,
				'X-EBAY-API-CERT-NAME:'.$this->cert_id,
				'X-EBAY-API-SITEID:'.$this->site_id,
				'X-EBAY-API-CALL-NAME:GetSessionID'
			);
			$xml = '<?xml version="1.0" encoding="utf-8"?>
			<GetSessionIDRequest xmlns="urn:ebay:apis:eBLBaseComponents">
			<RuName>'.$run_name.'</RuName>
			</GetSessionIDRequest>';
			$ch  = curl_init('https://api.ebay.com/ws/api.dll');
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			$responsexml = curl_exec($ch);
			curl_close($ch);
			$xml_response = simplexml_load_string($responsexml);
			$json_response = json_encode($xml_response);
			$result = json_decode($json_response,TRUE);
			$session_id = $result['SessionID'];
			$_SESSION['ebay_session_id_temp']	=	$session_id;
			wp_redirect('https://signin.ebay.com/ws/eBayISAPI.dll?SignIn&RUName='.$run_name.'&SessID='.$session_id);
			die;
		}
	}
	function add_wcfm_ie_setting_save(){
		if((isset($_POST['action']) && $_POST['action'] =='add_wcfm_ie_setting_save') && (isset($_POST['ebay_item_importer']) && !empty($_POST['ebay_item_importer'])) ){
			global $wpdb;
			$vendors = geteBayVendors();
			if(!$vendors){
				addAlert('danger','Connect atleast one account before importing.');
				wp_redirect( get_wcfm_url().'/ebay-import-export-settings/');
				die;
			}
			$vendor_id = get_current_user_id();
			// $this->clear_seller_single_schedule_event($vendor_id,'products','wcfm_schedule_import_products');
			$this->clear_seller_single_schedule_event($vendor_id,'products','addEbayVendorProductData');
			// $this->clear_seller_single_schedule_event($vendor_id,'products','wcfm_ie_create_all_products_file');
			// $this->clear_seller_single_schedule_event($vendor_id,'products','addWcfmEbayProductHook');
			$wpdb->delete( $this->cron_request_tablename, array( 'vendor_id' => $vendor_id,'type' => 'products','hook_name'=>'wcfm_schedule_import_products' ) );
			$primary_account_details = $this->geteBayVendor($vendor_id);
			if(sizeof($primary_account_details)==0){
				addAlert('danger','Please select the primary account.');
				$res['type'] = 200;  
				$res['redirect_url'] = get_wcfm_url().'/ebay-import-export-settings/';  
				echo json_encode($res);
				die;
			}
			$vendor_setting_fields = array();
			$res = array();
			if(isset($_POST['ebay_item_importer']['discount_price']) && !empty($_POST['ebay_item_importer']['discount_price'])){
				$discount_price = 1;
			}else{
				$discount_price = 0;
			}
			if(isset($_POST['ebay_item_importer']['import_items_without_bin']) && !empty($_POST['ebay_item_importer']['import_items_without_bin'])){
				$without_bin = 1;
			}else{
				$without_bin = 0;
			}
			if(isset($_POST['ebay_item_importer']['import_unsold_items']) && !empty($_POST['ebay_item_importer']['import_unsold_items'])){
				$unsold_items = 1;
			}else{
				$unsold_items = 0;
			}
			if(isset($_POST['ebay_item_importer']['import_feedback']) && !empty($_POST['ebay_item_importer']['import_feedback'])){
				$import_feedback = 1;
			}else{
				$import_feedback = 0;
			}
			if(isset($_POST['ebay_item_importer']['quantity_update_on_ebay']) && !empty($_POST['ebay_item_importer']['quantity_update_on_ebay'])){
				$quantity_update_on_ebay = 1;
			}else{
				$quantity_update_on_ebay = 0;
			}
			if(isset($_POST['ebay_item_importer']['synchronize']) && !empty($_POST['ebay_item_importer']['synchronize'])){
				$days_synchronize = 1;
				if(isset($_POST['ebay_item_importer']['days_to_activate']) && !empty($_POST['ebay_item_importer']['days_to_activate'])){
					$days_to_activate = $_POST['ebay_item_importer']['days_to_activate'];
				}else{
					$days_to_activate = 0;
				}
			}else{
				$days_synchronize = 0;
			}
			
			$vendor_setting_fields = array(
				'wcfm_ie_form_setting_min_date'					=>	$from_date,
				'wcfm_ie_form_setting_sold_items'				=>	$_POST['ebay_item_importer']['sold_items'],
				'wcfm_ie_form_setting_revise'					=>	$_POST['ebay_item_importer']['revise'],
				'wcfm_ie_form_setting_discount_price'			=>	$discount_price,
				'wcfm_ie_form_setting_discount_amount'			=>	$_POST['ebay_item_importer']['discount_amount'],
				'wcfm_ie_form_setting_without_bin'				=>	$without_bin,
				'wcfm_ie_form_setting_unsold_items'				=>	$unsold_items,
				'wcfm_ie_form_setting_import_feedback'			=>	$import_feedback,
				'wcfm_ie_form_setting_quantity_update_on_ebay'	=>	$quantity_update_on_ebay,
				'wcfm_ie_form_setting_days_synchronize'			=>	$days_synchronize,
				'wcfm_ie_form_setting_days_to_activate'			=>	$days_to_activate,
			);
			$vendor_all_setting = get_option('wcfm_ie_form_setting');
			$vendor_all_setting[$vendor_id] = $vendor_setting_fields;
			update_option('wcfm_ie_form_setting',$vendor_all_setting);

			/**** Add cron event ********/
			
			$date_added = date('Y-m-d h:i:s');
			$user_timezone = getCurrentVisitorCountry();
			if(isset($user_timezone['geoplugin_timezone']) && !empty($user_timezone['geoplugin_timezone'])){
				$current_timezone = $user_timezone['geoplugin_timezone'];
				$dt = new DateTime();
				$dt->setTimezone(new DateTimeZone($current_timezone));
				$date_added = $dt->format('Y-m-d h:i:s');
			}
			$from_date = $this->min_date;
			$end_date = date('Y-m-d',strtotime($this->min_date.'+120 days'));
			$wpdb->insert($this->request_tablename , array(
				"vendor_id"     =>  $vendor_id,
				"type"  		=> "products",
				"from_date"  	=> $from_date,
				"end_date"  	=> $end_date,
				"date_added"  	=> $date_added,
			));
			$lastid = $wpdb->insert_id;
			$args = array(array ('request_id'=>$lastid));

			$this->wcfm_schedule_single_event( time() + 30 , "addEbayVendorProductData", $args );

			/**** Add cron event ********/


			$wpdb->insert($this->cron_request_tablename , array(
				"vendor_id"     		=> 	$vendor_id,
				"type"  				=> "products",
				"hook_name"  			=> "addEbayVendorProductData",
				"from_date"  			=> date('Y-m-d h:i:s'),
				"expire"  				=> date('Y-m-d h:i:s', time() + 30 ),
				"date_added"  			=> date('Y-m-d h:i:s'),
			));

			update_user_meta($vendor_id,'ebay_in_queued',true);
			update_user_meta($vendor_id,'ebay_proccessing_bar',false);

			if($days_synchronize && $days_to_activate){
				if($lastid){
					$wpdb->insert($this->cron_request_tablename , array(
						"vendor_id"     		=> 	$vendor_id,
						"type"  				=> "products",
						"hook_name"  			=> "wcfm_schedule_import_products",
						"from_date"  			=> date('Y-m-d h:i:s'),
						"expire"  				=> date('Y-m-d h:i:s', strtotime('+'.$days_to_activate.' days', strtotime(date('Y-m-d h:i:s')))),
						"date_added"  			=> date('Y-m-d h:i:s'),
					));
				}
			}
			addAlert('success','Successfully Saved Setting');
			wp_redirect( get_wcfm_url().'/ebay-import-export-settings/');
			die;
		}
	}
	public function addEbayVendorProductData($cron){
		if(sizeof($cron)>0){
			$request_id = $cron['request_id'];
			$pagination_arg = array();
			$get_request_info 					= $this->getRequestByRequestId($request_id);
			if(!$get_request_info){
				return false;
			}
			$vendor_id 							= $get_request_info['vendor_id'];
			$wcfm_ie_setting 					= get_option('wcfm_ie_form_setting');
			$from_date 							= date('Y-m-d',strtotime($get_request_info['from_date']));
			$end_date 							= date('Y-m-d',strtotime($from_date.'+120 days'));
			$call_name 							= $get_request_info['type'];
			$ebay_user_info 					= $this->geteBayVendor($vendor_id);
			$ebay_user_id 						= $ebay_user_info['ebay_user_id'];
			$ebay_user_info['call_name'] 		= 'GetSellerList';
			$ebay_user_info['from_date'] 		= $from_date;
			$ebay_user_info['end_date'] 		= $end_date;
			$ebay_user_info['ebay_user_id'] 	= $ebay_user_info['ebay_user_id'];
			$ebay_user_info['ebay_token'] 		= $ebay_user_info['ebay_token'];
			update_user_meta($vendor_id,'wcfm_ie_product_current_page',1);
			$filename 	=	$this->file_path.$this->product_file_name.$vendor_id.'_'.$ebay_user_id.'.csv';
			$fh = fopen($filename, 'w');
			fputcsv($fh, $this->product_header_column);
			fclose($fh);
			$this->addCsvFileData($ebay_user_info,$vendor_id);
			exit();
		}
	}
	public function wcfm_ie_cancel_request(){
		if(isset($_POST['action']) && $_POST['action'] == 'wcfm_ie_cancel_request' && $_POST['type'] =='products'){
			global $wpdb;
			$vendor_id = get_current_user_id();
			$this->clear_seller_single_schedule_event($vendor_id,'products','addEbayVendorProductData');
			// $this->clear_seller_single_schedule_event($vendor_id,'products','wcfm_ie_create_all_products_file');
			// $this->clear_seller_single_schedule_event($vendor_id,'products','addWcfmEbayProductHook');
			$wpdb->delete( $this->request_tablename, array( 'vendor_id' => $vendor_id,'type' => 'products' ) );
			update_user_meta($vendor_id,'ebay_in_queued',false);
			update_user_meta($vendor_id,'ebay_proccessing_bar',false);
			$socket_msg['msg'] = 'Cancelled Importing Request';
			$this->SetSocket()->trigger('fizno', 'cancelled_request_'.$vendor_id, $socket_msg );
			addAlert('success','Successfully Product Import Request Cancelled.');
			wp_redirect(get_wcfm_url().'/ebay-import-export-settings/');
			die;
		}
	}
	public function wcfm_ie_remove_schedule(){
		if(isset($_POST['action']) && $_POST['action'] == 'remove_schedule' && $_POST['type'] =='products'){
			global $wpdb;
			$vendor_id = get_current_user_id();
			$this->clear_seller_single_schedule_event($vendor_id,"products","wcfm_schedule_import_products");
			$wpdb->delete( $this->cron_request_tablename, array( 'vendor_id' => $vendor_id,'type' => 'products','hook_name'=>'wcfm_schedule_import_products' ) );
			wp_redirect(get_wcfm_url().'/ebay-import-export-settings/');
			die;
		}
	}
	public function wcfm_quantity_update_on_ebay_product($order_id,$old_status,$new_status){
		$wcfm_ie_options = array(); 
		$wcfm_ie_options = get_option('wcfm_ie_form_setting');
		$order = wc_get_order( $order_id );
		$order_status = array('processing','completed');
		if (in_array($new_status,$order_status )){
			$stock_reduced = get_post_meta( $order_id, '_order_stock_reduced', true );
			if(isset($stock_reduced) && $stock_reduced =='yes'){
				foreach ($order->get_items() as $item) {
					$product_id = $item->get_product_id();
					$product = wc_get_product($product_id);
					$vendor_id = get_post_field('post_author',$product_id);
					$item_id = $product->get_sku();
					$order_product_qty = $item->get_quantity();
					$product_quantity = get_post_meta( $product_id, '_stock', true );
					if(isset($wcfm_ie_options[$vendor_id]['wcfm_ie_form_setting_quantity_update_on_ebay']) && $wcfm_ie_options[$vendor_id]['wcfm_ie_form_setting_quantity_update_on_ebay']){
						$result = $this->wcfm_update_item_quantity_ebay_api($vendor_id,$item_id,$product_quantity);
						if($result['Ack'] == 'Failure'){
							$error = 'Stock level can\'t be reduce of this <b>'.$product->get_title().'</b> in the eBay because we are getting an error:- <br>'.$result['Errors']['ShortMessage'];
							$order->add_order_note( $error );
						}
					}
					$seller_prefrences = $this->geteBaySellerPreferencesRequest($vendor_id);
					if($seller_prefrences['Ack']=='Success' && $seller_prefrences['OutOfStockControlPreference']=='false'){
						$end_result_data = $this->endItemEbay($vendor_id,$item_id);
						if($end_result_data['Ack'] == 'Failure'){
							$error = 'We are unable to move to "Sold" this <b>'.$product->get_title().'</b> in the eBay because we are getting an error:- <br>'.$end_result_data['Errors']['ShortMessage'];
							$order->add_order_note( $error );
						}
					}
				}
			}
		}
	}
	public function wcfm_update_item_quantity_ebay_api($vendor_id,$item_id,$qty){
		$ebay_user_info = $this->geteBayVendor($vendor_id);
		$headers = array(
			'Content-Type: text/xml',
			'X-EBAY-API-COMPATIBILITY-LEVEL:877',
			'X-EBAY-API-DEV-NAME:'.$this->dev_id,
			'X-EBAY-API-APP-NAME:'.$this->app_id,
			'X-EBAY-API-CERT-NAME:'.$this->cert_id,
			'X-EBAY-API-SITEID:'.$this->site_id,
			'X-EBAY-API-CALL-NAME:ReviseItem'
		);

		$xml = '<?xml version="1.0" encoding="utf-8"?>
		<ReviseItemRequest xmlns="urn:ebay:apis:eBLBaseComponents">
		<RequesterCredentials>
		<eBayAuthToken>'.$ebay_user_info['ebay_token'].'</eBayAuthToken>
		</RequesterCredentials>
		<ErrorLanguage>en_US</ErrorLanguage>
		<WarningLevel>High</WarningLevel>
		<Item>
		<ItemID>'.$item_id.'</ItemID>
		<Quantity>'.$qty.'</Quantity>
		</Item>
		</ReviseItemRequest>';
		$ch  = curl_init('https://api.ebay.com/ws/api.dll');
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$responsexml = curl_exec($ch);
		curl_close($ch);
		$xml_response = simplexml_load_string($responsexml);
		$json_response = json_encode($xml_response);
		$result = json_decode($json_response,TRUE);
		return $result;
	}
	public function wcfm_importing_site_title () {
		return "Fetching the products please wait...";
	}
	public function wcfm_importing_deafult_title () {
		return "eBay import export setting";
	}
	public function wcfm_importing_progressbar_site_title($progress_text){
		$vendor_id = get_current_user_id(); 
		$vendor_product_request = getCurrentVendorRequest($vendor_id,'products');
		$proccessing_bar = false;
		if(sizeof($vendor_product_request)>0){
			$proccessing_bar = get_user_meta($vendor_id,'ebay_proccessing_bar',true);
		}
		if($proccessing_bar == true){
			$title_text = '0 / '.$vendor_product_request['total_products'];
			if($vendor_product_request['total_products'] && $vendor_product_request['done']){
				$percentage = ceil($vendor_product_request['done']/$vendor_product_request['total_products']*100);
				$title_text = $vendor_product_request['done'] .' of '.$vendor_product_request['total_products'] .' - '.$percentage.'%';
			}
		}
		return $title_text;
	}
	public function wcfm_schedule_import_products($cron_arg){
		global $wpdb;
		$vendor_id = $cron_arg['vendor_id']; 
		$from_date = $this->min_date;
		$end_date = date('Y-m-d',strtotime($this->min_date.'+120 days'));
		$wpdb->insert($this->request_tablename , array(
			'vendor_id'     =>  $vendor_id,
			'type'  		=> 'products',
			'from_date'  	=> $from_date,
			'end_date'  	=> $end_date,
		));
		$lastid = $wpdb->insert_id;
		$arg['request_id'] = $lastid;
		$this->addEbayVendorProductData($arg);
	}
	public function wcfm_ie_clear_all_crons( $hook ) {
		$crons = _get_cron_array();
		if ( empty( $crons ) ) {
			return;
		}
		foreach( $crons as $timestamp => $cron ) {
			if ( ! empty( $cron[$hook] ) )  {
				unset( $crons[$timestamp][$hook] );
			}
			if ( empty( $crons[$timestamp] ) ) {
				unset( $crons[$timestamp] );
			}
		}
		_set_cron_array( $crons );
		return $crons;
	}
	public function check_seller_product_import_schedules(){
		global $wpdb;
		$get_product_schedule = $this->getCronSchedules();
		if(sizeof($get_product_schedule)>0){
			$crons = _get_cron_array();
			$get_seller_setting_detail = array();
			$get_seller_setting_detail = get_option('wcfm_ie_form_setting');
			foreach ($get_product_schedule as $schedule) {
				$args = array(
					'vendor_id'=>$schedule['vendor_id']
				);
				if(time() > strtotime($schedule['expire'])){
					$this->clear_seller_single_schedule_event($schedule['vendor_id'],'products',$schedule['hook_name']);
					$seller_cron_schedules = $get_seller_setting_detail[$schedule['vendor_id']];
					$expire_days =  date('Y-m-d h:i:s', strtotime('+'.$seller_cron_schedules['wcfm_ie_form_setting_days_to_activate'].' days', strtotime($schedule['expire'])));
					$wpdb->update($this->cron_request_tablename, 
						array(
							'expire'=> $expire_days,
						),
						array(
							'wcfm_ie_cron_request_id'=> $schedule['wcfm_ie_cron_request_id'],
						)
					);
					$this->wcfm_schedule_single_event($expire_days, $schedule['hook_name'],array($args) );
				}else{
					if(!array_key_exists(strtotime($schedule['expire']),$crons)) {
						$this->wcfm_schedule_single_event( strtotime($schedule['expire']), $schedule['hook_name'], $args );
					}
				}
			}
		}
	}
	public function clear_seller_single_schedule_event($vendor_id,$type,$hook){
		global $wpdb;
		if(empty($vendor_id)) return;
		$seller_schedules = $this->getSellerSingleCronScheduleEvent($vendor_id,$type,$hook);
		$crons = _get_cron_array();
		if(sizeof($seller_schedules)>0){
			if ( empty( $crons ) ) return;
			foreach ($seller_schedules as $seller_schedule) {
				if(array_key_exists(strtotime($seller_schedule['expire']),$crons)){
					unset($crons[strtotime($seller_schedule['expire'])]);
					$wpdb->delete( $this->cron_request_tablename, array( 'vendor_id' => $vendor_id,'type' => $type,'hook_name'=>$hook,'expire'=> $seller_schedule['expire'] ) );
				}
			}
		}
		_set_cron_array( $crons );
		return true;
	}
	public function endItemEbay($vendor_id,$item_id){
		$ebay_user_info = $this->geteBayVendor($vendor_id);
		$headers = array(
			'Content-Type: text/xml',
			'X-EBAY-API-COMPATIBILITY-LEVEL:877',
			'X-EBAY-API-DEV-NAME:'.$this->dev_id,
			'X-EBAY-API-APP-NAME:'.$this->app_id,
			'X-EBAY-API-CERT-NAME:'.$this->cert_id,
			'X-EBAY-API-SITEID:'.$this->site_id,
			'X-EBAY-API-CALL-NAME:EndItem'
		);
		$xml = '<?xml version="1.0" encoding="utf-8"?>
		<EndItemRequest xmlns="urn:ebay:apis:eBLBaseComponents">
		<RequesterCredentials>
		<eBayAuthToken>'.$ebay_user_info['ebay_token'].'</eBayAuthToken>
		</RequesterCredentials>
		<ItemID>'.$item_id.'</ItemID>
		<EndingReason>NotAvailable</EndingReason>
		</EndItemRequest>';
		$ch  = curl_init('https://api.ebay.com/ws/api.dll');
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$responsexml = curl_exec($ch);
		curl_close($ch);
		$xml_response = simplexml_load_string($responsexml);
		$json_response = json_encode($xml_response);
		$result = json_decode($json_response,TRUE);
		return $result;
	}
	function geteBaySellerPreferencesRequest($vendor_id){
		$ebay_user_info = $this->geteBayVendor($vendor_id);
		$headers = array(
			'Content-Type: text/xml',
			'X-EBAY-API-COMPATIBILITY-LEVEL:877',
			'X-EBAY-API-DEV-NAME:'.$this->dev_id,
			'X-EBAY-API-APP-NAME:'.$this->app_id,
			'X-EBAY-API-CERT-NAME:'.$this->cert_id,
			'X-EBAY-API-SITEID:'.$this->site_id,
			'X-EBAY-API-CALL-NAME:GetUserPreferences'
		);
		$xml = '<?xml version="1.0" encoding="utf-8"?> 
		<GetUserPreferencesRequest xmlns="urn:ebay:apis:eBLBaseComponents"> 
		<RequesterCredentials> 
		<eBayAuthToken>'.$ebay_user_info['ebay_token'].'</eBayAuthToken> 
		</RequesterCredentials> 
		<ShowOutOfStockControlPreference>true</ShowOutOfStockControlPreference>
		</GetUserPreferencesRequest>';
		$ch  = curl_init('https://api.ebay.com/ws/api.dll');
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		$responsexml = curl_exec($ch);
		curl_close($ch);
		$xml_response = simplexml_load_string($responsexml);
		$json_response = json_encode($xml_response);
		$result = json_decode($json_response,TRUE);
		return $result;
	}
	public function SetSocket(){
		$pusher = new Pusher\Pusher( $this->socket_app_key, $this->socket_app_secret, $this->socket_app_id, $this->socket_options );
		return $pusher;
	}
	public function setLog($insert) {
		file_put_contents($this->log_file, date("D M d, Y 'h:i a ").$insert."\r\n", FILE_APPEND);
	}

	public function getLog() {
		$content = @file_get_contents($this->log_file);
		return $content;
	}
	public function wcfm_schedule_single_event( $timestamp, $hook, $args = array(), $wp_error = false ) {
		if ( ! is_numeric( $timestamp ) || $timestamp <= 0 ) {
			if ( $wp_error ) {
				return new WP_Error(
					'invalid_timestamp',
					__( 'Event timestamp must be a valid Unix timestamp.' )
				);
			}
			return false;
		}
		$event = (object) array(
			'hook'      => $hook,
			'timestamp' => $timestamp,
			'schedule'  => false,
			'args'      => $args,
		);
		$pre = apply_filters( 'pre_schedule_event', null, $event, $wp_error );
		if ( null !== $pre ) {
			if ( $wp_error && false === $pre ) {
				return new WP_Error(
					'pre_schedule_event_false',
					__( 'A plugin prevented the event from being scheduled.' )
				);
			}
			if ( ! $wp_error && is_wp_error( $pre ) ) {
				return false;
			}
			return $pre;
		}
		$crons     = (array) _get_cron_array();
		$key       = md5( serialize( $event->args ) );
		$duplicate = false;
		if ( $event->timestamp < time() + 10 * MINUTE_IN_SECONDS ) {
			$min_timestamp = 0;
		} else {
			$min_timestamp = $event->timestamp - 10 * MINUTE_IN_SECONDS;
		}
		if ( $event->timestamp < time() ) {
			$max_timestamp = time() + 10 * MINUTE_IN_SECONDS;
		} else {
			$max_timestamp = $event->timestamp + 10 * MINUTE_IN_SECONDS;
		}
		foreach ( $crons as $event_timestamp => $cron ) {
			if ( $event_timestamp < $min_timestamp ) {
				continue;
			}
			if ( $event_timestamp > $max_timestamp ) {
				break;
			}
			if ( isset( $cron[ $event->hook ][ $key ] ) ) {
				$duplicate = true;
				break;
			}
		}
		if ( $duplicate ) {
			if ( $wp_error ) {
				return new WP_Error(
					'duplicate_event',
					__( 'A duplicate event already exists.' )
				);
			}
			return false;
		}
		$event = apply_filters( 'schedule_event', $event );
		if ( ! $event ) {
			if ( $wp_error ) {
				return new WP_Error(
					'schedule_event_false',
					__( 'A plugin disallowed this event.' )
				);
			}
			return false;
		}
		$crons[ $event->timestamp ][ $event->hook ][ $key ] = array(
			'schedule' => $event->schedule,
			'args'     => $event->args,
		);
		uksort( $crons, 'strnatcasecmp' );

		return _set_cron_array( $crons, $wp_error );
	}
}

/****** suggest

INSERT INTO `wp_postmeta` (`post_id`,`meta_key`,`meta_value`) 
  VALUES ( $post_id,  'artist_name' , $artist) 
         ( $post_id,  'song_length' , $length )
		 ( $post_id,  'song_genre' , $genre ) ...`
 
 INSERT INTO wp_posts (post_author,post_date,post_date_gmt,post_content,post_title,post_status,post_type,comment_status,ping_status,post_name,post_modified,post_modified_gmt,guid) VALUES (...

 suggest */
//update_post_meta( 51907, '_product_version', WC_VERSION );